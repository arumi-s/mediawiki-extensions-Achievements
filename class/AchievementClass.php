<?php

namespace Achiev;

class Achievement {
	static protected $send_echo_events = true;

	protected $id = null;
	protected $config = null;
	protected $counter = null;
	protected $static = false;

	public function __construct ( $id, $config ) {
		$this->id = $id;
		$this->config = array_merge( self::defaultConfig(), $config );
		
		if ( empty( $this->config['name'] ) ) $this->config['name'] = 'achiev-name-' . $id;
		if ( empty( $this->config['desc'] ) ) $this->config['desc'] = 'achiev-desc-' . $id;
		if ( $this->isStaged() ) sort( $this->config['threshold'], SORT_NUMERIC );
		
		$counterClass = CounterHandler::getClassname( $this->getType(), true );
		if ( $counterClass === '' || $counterClass === 'StaticCounter' ) {
			$this->static = true;
			$this->counter = new StaticCounter( $this );
		} else {
			$this->static = false;
			$this->counter = new $counterClass( $this );
		}
	}

	public function getID () {
		return $this->id;
	}

	public function getStageName ( $stage, $sep = ':' ) {
		return $this->id . ( $stage > 0 ? $sep . $stage : '' );
	}

	public function getConfig ( $index = null, $default = null ) {
		return is_null( $index ) ? $this->config : ( isset( $this->config[$index] ) ? $this->config[$index] : $default );
	}

	public function getType () {
		return $this->getConfig( 'type', 'static' );
	}

	public function &getCounter () {
		return $this->counter;
	}

	public function isStatic () {
		return $this->static;
	}

	public function getReset () {
		return $this->config['reset'];
	}

	public function isAwardable () {
		return $this->config['awardable'];
	}

	public function isRemovable () {
		return $this->config['removable'];
	}

	public function isActive () {
		return $this->config['active'] && $this->inActiveRange();
	}

	public function inActiveRange () {
		if ( empty( $this->config['activerange'] ) ) return true;
		$now = wfTimestampNow();
		if ( empty( $this->config['activerange'][0] ) ) {
			return $now < $this->config['activerange'][1];
		}
		if ( empty( $this->config['activerange'][1] ) ) {
			return $now >= $this->config['activerange'][0];
		}
		return $now < $this->config['activerange'][1] && $now >= $this->config['activerange'][0];
	}

	public function isBlocked ( $user ) {
		return $this->counter->isBlocked( $user );
	}

	public function isQualify ( $count, $threshold = null ) {
		return $this->counter->isQualify( $count, $threshold );
	}

	public function isStaged () {
		return is_array( $this->config['threshold'] );
	}

	public function isValidStage ( $stage = 0 ) {
		return $this->isStaged() && in_array( intval( $stage ), $this->config['threshold'] );
	}

	public function isStageReversed () {
		return $this->isStaged() && $this->counter->isStageReversed();
	}

	public function hasRealThreshold () {
		return $this->counter->hasRealThreshold();
	}

	public function getRealThreshold ( $index = 0 ) {
		return $this->counter->getRealThreshold( $index );
	}

	public function getRealThresholds () {
		if ( !$this->hasRealThreshold() ) return $this->getConfig( 'threshold' );
		if ( $this->isStaged() ) {
			$ts = $this->getConfig( 'threshold' );
			foreach ( $ts as &$threshold ) {
				$newthreshold = $this->getRealThreshold( $threshold );
				if ( !is_null( $newthreshold ) && $newthreshold > 0 ) $threshold = $newthreshold;
			}
			return $ts;
		} else {
			$threshold = $this->getConfig( 'threshold' );
			$newthreshold = $this->getRealThreshold( $threshold );
			if ( !is_null( $newthreshold ) && $newthreshold > 0 ) return $newthreshold;
			else return $threshold;
		}
	}

	public function getAfterLinkMsg ( $stage = 0, $plain = true ) {
		$text = wfMessage( 'achievtitle-afterlink' )->rawParams(
			wfMessage( $this->getNameMsgKey( $stage ) )->plain()
		)->text();
		if ( $plain ) {
			return strip_tags( $text );
		} else {
			return \Html::openElement( 'a', [ 'class' => 'achievtitle', 'title' => $this->getDescMsg( $stage ) ] ) . $text;
		}
	}

	public function getNameMsg ( $stage = 0 ) {
		return wfMessage( $this->getNameMsgKey( $stage ) )->text();
	}

	public function getDescMsg ( $stage = 0 ) {
		return wfMessage( $this->getDescMsgKey( $stage ) )->text();
	}

	public function getNameMsgKey ( $stage = 0 ) {
		if ( $this->isStaged() && $stage > 0 ) return $this->config['name'] . '-' . $stage;
		return $this->config['name'];
	}

	public function getDescMsgKey ( $stage = 0 ) {
		if ( $this->isStaged() && $stage > 0 ) return $this->config['desc'] . '-' . $stage;
		return $this->config['desc'];
	}

	public function listNameMsgKeys ( $includeMain = true ) {
		$list = [];
		if ( $includeMain ) $list[0] = $this->getNameMsgKey();
		if ( $this->isStaged() ) {
			foreach ( $this->config['threshold'] as $threshold ) {
				$list[$threshold] = $this->getNameMsgKey( $threshold );
			}
		}
		return $list;
	}

	public function listDescMsgKeys ( $includeMain = true ) {
		$list = [];
		if ( $includeMain ) $list[0] = $this->getDescMsgKey();
		if ( $this->isStaged() ) {
			foreach ( $this->config['threshold'] as $threshold ) {
				$list[$threshold] = $this->getDescMsgKey( $threshold );
			}
		}
		return $list;
	}
	
	public function getCounterConfig ( $index = null, $default = null ) {
		if ( is_null( $this->counter ) ) {
			return is_null( $index ) ?
				$this->config['counter'] :
				( isset( $this->config['counter'][$index] ) ? $this->config['counter'][$index] : null );
		}
		return $this->counter->getConfig( $index, $default );
	}

	public function checkAchiev ( \User $user, $stage = 0 ) {
		if ( $this->isBlocked( $user ) ) return false;
		$dbw = wfGetDB( DB_MASTER );
		$ts = wfTimestampOrNull( TS_UNIX, $dbw->selectField(
			'achievements',
			'ac_date',
			[ 'ac_user' => $user->getId(), 'ac_id' => $this->getStageName( $stage ), 'ac_date IS NOT NULL' ]
		) );
		if ( is_null( $ts ) ) return false;
		return $ts;
	}

	public function updateAchiev ( \User $user, $clearCache = true ) {
		if ( !$this->isActive() || $this->isBlocked( $user ) ) return;
		$note = '';
		$dbw = wfGetDB( DB_MASTER );
		
		$main = $dbw->selectRow(
			'achievements',
			[ 'ac_count', 'ac_date' ],
			[ 'ac_user' => $user->getId(), 'ac_id' => $this->id ]
		);
		if ( $main ) {
			$maincount = (int)($main->ac_count);
			$maindate = wfTimestampOrNull( TS_UNIX, $main->ac_date );
		} else {
			$maincount = 0;
			$maindate = null;
		}

		if ( $this->isStaged() ) {
			$res = $dbw->select(
				'achievements',
				[ 'ac_id', 'ac_date' ],
				[ 'ac_user' => $user->getId(), 'ac_id' . $dbw->buildLike( $this->id . ':', $dbw->anyString() ) ]
			);
			$oldacs = array();
			$oldtss = array();
			if ( $res ) {
				$len = strlen( $this->id ) + 1;
				while( $row = $res->fetchRow() ) {
					$oldacs[] = intval( substr( $row['ac_id'], $len ) );
					$oldtss[] = wfTimestampOrNull( TS_UNIX, $row['ac_date'] );
				}
			}
			if ( $this->isRemovable() ) {
				foreach ( $oldacs as $oldac ) {
					if ( !$this->isValidStage( $oldac ) || !$this->isQualify( $maincount, $oldac ) ) {
						$this->removeFrom( $user, $oldac );
					}
				}
			}
			foreach ( $this->config['threshold'] as $threshold ) {
				if ( $this->isQualify( $maincount, $threshold ) ) {
					$pos = array_search( $threshold, $oldacs );
					if ( $pos === false || is_null( $oldtss[$pos] ) ) {
						$this->awardTo( $user, $threshold, $maincount );
					}
				}
			}
		} else {
			$note .= 'nostage';
			$threshold = $this->config['threshold'];
			if ( $this->isQualify( $maincount, $threshold ) ) {
				$note .= '-qualify';
				if ( is_null( $maindate ) ) {
					$note .= '-award';
					$this->awardTo( $user, 0, $maincount );
				}
			} else {
				$note .= '-dequalify';
				if ( $this->isRemovable() && !is_null( $maindate ) ) {
					$note .= '-remove';
					$this->removeFrom( $user );
				}
			}
		}
		if ( $clearCache ) AchievementHandler::clearUserCache( $user );
		return $note;
	}

	public function addStaticCountTo ( \User $user, &$count = 0 ) {
		if ( $this->isBlocked( $user ) ) return false;
		if ( !$this->isAwardable() || !$this->isActive() ) return false;
		$this->counter->updateHook( ['static', 'Achievement::addStaticCountTo'], $user, $count );
		AchievementHandler::clearUserCache( $user );
		return true;
	}

	public function awardStaticTo ( \User $user, $stage = 0, $count = 0 ) {
		if ( $this->isBlocked( $user ) ) return false;
		if ( !$this->isAwardable() || !$this->isActive() ) return false;
		$s = $this->awardTo( $user, $stage, $count );
		AchievementHandler::clearUserCache( $user );
		return $s;
	}

	public function removeStaticFrom ( \User $user, $stage = 0 ) {
		if ( $this->isBlocked( $user ) ) return false;
		if ( !$this->isAwardable() || !$this->isActive() ) return false;
		$s = $this->removeFrom( $user, $stage );
		AchievementHandler::clearUserCache( $user );
		return $s;
	}

	private function awardTo ( \User $user, $stage = 0, $setvalue = 0 ) {
		$setvalue = (int)$setvalue;
		if ( $this->isStaged() ) {
			if ( $this->isValidStage( $stage ) ) {
				$id = $this->getStageName( $stage );
				$setvalue = max( $setvalue, $stage );
			} else {
				return false;
			}
		} else {
			$id = $this->id;
			$setvalue = max( $setvalue, $this->config['threshold'] );
		}
		$dbw = wfGetDB( DB_MASTER );
		
		$exclude = $this->getConfig( 'exclude', false );
		if ( !empty( $exclude ) ) {
			$already = $dbw->selectField(
				'achievements',
				[ 'ac_date' ],
				[ 'ac_user' => $user->getId(), 'ac_id' => $exclude, 'ac_date IS NOT NULL' ],
				__METHOD__
			);
			if ( $already ) {
				return 0;
			}
		}
		
		$already = $dbw->selectField(
			'achievements',
			[ 'ac_date' ],
			[ 'ac_user' => $user->getId(), 'ac_id' => $id, 'ac_date IS NOT NULL' ],
			__METHOD__
		);
		if ( $already ) {
			return 0;
		}
		
		$dbw->update(
			'achievements',
			[ 'ac_date' => wfTimestamp( TS_MW ) ],
			[ 'ac_user' => $user->getId(), 'ac_id' => $id ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		if ( $dbw->affectedRows() == 0 ) {
			$dbw->insert(
				'achievements',
				[ 'ac_user' => $user->getId(), 'ac_id' => $id, 'ac_count' => $setvalue, 'ac_date' => wfTimestamp( TS_MW ) ]
			);
		}
		$success = $dbw->affectedRows() != 0;
		if ( $success ) {
			\Hooks::run( 'AchievementAward', [ $user, $this, $stage ] );
			if ( self::$send_echo_events ) {
				\EchoEvent::create( [
					'type' => 'achiev-award',
					'extra' => [
						'achievid' => $id, 
						'notifyAgent' => true,
					],
					'agent' => $user,
				] );
			}
		}
		return $success;
	}

	private function removeFrom ( \User $user, $stage = 0 ) {
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->isStaged() ) {
			$id = $this->getStageName( $stage );
			$dbw->delete(
				'achievements',
				[ 'ac_user' => $user->getId(), 'ac_id' => $id ],
				__METHOD__
			);
		} else {
			$id = $this->id;
			$dbw->update(
				'achievements',
				[ 'ac_date' => null ],
				[ 'ac_user' => $user->getId(), 'ac_id' => $id ],
				__METHOD__,
				[ 'LIMIT' => 1 ]
			);
		}

		$success = $dbw->affectedRows() != 0;
		if ( $success ) {
			\Hooks::run( 'AchievementRemove', [ $user, $this, $stage ] );
			if ( self::$send_echo_events ) {
				\EchoEvent::create( [
					'type' => 'achiev-remove',
					'extra' => [
						'achievid' => $id,
						'notifyAgent' => true,
					],
					'agent' => $user,
				] );
			}
		}
		return $success;
	}

	static public function suppressEchoEvents () {
		self::$send_echo_events = false;
	}

	static public function restoreEchoEvents () {
		self::$send_echo_events = true;
	}

	static public function defaultConfig () {
		return [
			'reset' => false,
			'threshold' => 1,
			'exclude' => false,
			'removable' => false,
			'awardable' => false,
			'hidden' => false,
			'active' => true,
		];
	}
}