<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false )
	$IP = dirname( __FILE__ ) . '/../../..';

require_once( "$IP/maintenance/Maintenance.php" );

class ResetAchievementCounter extends Maintenance {
	function __construct() {
		parent::__construct();
		$this->mDescription = "Initialize specific Achievement Counter(s) in DB.";
		$this->setBatchSize( 1000 );
		$this->addOption( 'i', 'interval', true, true );
		$this->addOption( 'nohook', 'interval', false, false );
	}

	private function log ( $text = '' ) {
		$hand = fopen( __DIR__ . '/log/' . date('Y-m') . '.log', 'a+' );
		if ( !$hand ) return false;
		if ( is_array( $text ) ) $text = implode( "\t", $text );
		fwrite( $hand, date('Y-m-d H:i:s') . "\t" . $text . "\n" );
		fclose( $hand );
		return true;
	}

	function execute() {
		$interval = trim($this->getOption( 'i', false ));	
		$yeshook = !$this->getOption( 'nohook', false );	

		if ( $interval ) {
			$interval = strtolower( $interval );
			$list = Achiev\AchievementHandler::AchievementsFromReset( $interval );

			$counterlist = [];
			$achievlist = [];
			foreach ( $list as $achiev ) {
				if ( !$achiev ) {
					continue;
				}
				$this->output( $achiev->getID() . "\n" );
				if ( $achiev->isStatic() ) {
					continue;
				}
				if ( !$achiev->isActive() ) {
					continue;
				}
				$removablelist[] = $achiev->isRemovable();
				$counterlist[] = $achiev->getCounter();
			}

			if ( count( $counterlist ) > 0 || Hooks::isRegistered( 'Achievement::' . $interval ) ) {
				$dbw = $this->getDB( DB_MASTER );
				$i = 0;
				$maxUserId = 0;
				do {
					$res = $dbw->select( 'user',
						User::selectFields(),
						array( 'user_id > ' . $maxUserId ),
						__METHOD__,
						array(
							'ORDER BY' => 'user_id',
							'LIMIT' => $this->mBatchSize,
						)
					);
					
					foreach ( $res as $row ) {
						$user = User::newFromRow( $row );
						if ( $yeshook ) Hooks::run( 'Achievement::' . $interval, [ $user, $this ] );
						foreach ( $counterlist as $n => $c ) {
							if ( !$removablelist[$n] ) $c->updateAchiev( $user, false );
							$c->resetCount( $user, false );
						}
						Achiev\AchievementHandler::clearUserCache( $user, 'refresh' );
						++$i;
					}
					$maxUserId = $row->user_id;
				} while ( $res->numRows() );

				$this->log( $interval );
			}
			if ( Hooks::isRegistered( 'RegularJobs::' . $interval ) ) {
				global $wgReplaceTextUser;
				if ( $wgReplaceTextUser != null ) {
					$jobuser = User::newFromName( $wgReplaceTextUser );
				} else {
					$jobuser = new User();
				}
				Hooks::run( 'RegularJobs::' . $interval, [ $jobuser, $this ] );
			}
			
		}
		$this->output( "end\n" );
	}
}

$maintClass = 'ResetAchievementCounter';
require_once( RUN_MAINTENANCE_IF_MAIN );
