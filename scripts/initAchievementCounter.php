<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false )
	$IP = dirname( __FILE__ ) . '/../../..';

require_once( "$IP/maintenance/Maintenance.php" );

class InitAchievementCounter extends Maintenance {
	function __construct() {
		parent::__construct();
		$this->mDescription = "Initialize ALL or specific Achievement Counter(s) in DB.";
		$this->setBatchSize( 1000 );
		$this->addOption( 'achiev', 'Achievement ID to initialize', false, true );
		$this->addOption( 'counter', 'Counter Type to initialize', false, true );
		$this->addOption( 'all', 'Initialize all Achievements', false, false );
		$this->addOption( 'alluser', 'Is querying all user', false, false );
		$this->addOption( 'reset', 'Includes resetable achievements', false, false );
		$this->addOption( 'suppress', 'Suppress Echo events', false, false );
	}

	function execute() {
		$id = trim($this->getOption( 'achiev', false ));
		$counter = $this->getOption( 'counter', false );
		$all = $this->getOption( 'all', false );
		$alluser = $this->getOption( 'alluser', false );
		$reset = $this->getOption( 'reset', false );
		$suppress = $this->getOption( 'suppress', false );
		
		$this->output( "Initializing...\n" );
		if ( $suppress ) Achiev\Achievement::suppressEchoEvents();

		if ( $id ) {
			$list = [Achiev\AchievementHandler::AchievementFromID( $id )];
		} elseif ( $counter ) {
			$list = Achiev\AchievementHandler::AchievementsFromCounter( $counter );
		} elseif ( $all ) {
			$list = Achiev\AchievementHandler::AchievementsFromAll();
		} else {
			return;
		}

		$counterlist = [];
		foreach ( $list as $achiev ) {
			if ( !$achiev ) {
				$this->output( " invalid id.\n" );
				continue;
			}
			$this->output( "Initializing " . $achiev->getID() . " ...\n" );
			if ( !$reset && $achiev->getReset() ) {
				$this->output( " resetable achievements cannot be initialized.\n" );
				continue;
			}
			if ( !$achiev->isActive() ) {
				$this->output( " inactive achievements cannot be initialized.\n" );
				continue;
			}
			if ( $achiev->isStatic() ) {
				$this->output( " static achievements cannot be initialized.\n" );
				continue;
			}
			
			$counterlist[] = $achiev->getCounter();
		}
		if ( count( $counterlist ) == 0 ) return;

		$dbw = $this->getDB( DB_MASTER );
		$i = 0;
		$maxUserId = 0;
		do {
			$this->output( "...start prog $maxUserId\n" );
			$res = $dbw->select( 'user',
				User::selectFields(),
				$alluser ? array( 'user_id > ' . $maxUserId ) : array( 'user_editcount > 0', 'user_id > ' . $maxUserId ),
				__METHOD__,
				array(
					'ORDER BY' => 'user_id',
					'LIMIT' => $this->mBatchSize,
				)
			);

			foreach ( $res as $row ) {
				$user = User::newFromRow( $row );
				foreach ( $counterlist as $c ) {
					$c->resetCount( $user );
				}
				++$i;
			}
			$maxUserId = $row->user_id;
		} while ( $res->numRows() );

		$this->output( "user count $i, user maxid $maxUserId\n" );
		$this->output( "... end.\n" );
		if ( $suppress ) Achiev\Achievement::restoreEchoEvents();
	}
}

$maintClass = 'InitAchievementCounter';
require_once( RUN_MAINTENANCE_IF_MAIN );
