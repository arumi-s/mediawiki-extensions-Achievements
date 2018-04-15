<?php

namespace Achiev;

class AchievementHandler {
	static protected $list = [];

	static public function configs () {
		global $wgAchievementsConfigs;
		return $wgAchievementsConfigs;
	}

	static public function handleHook ( $opt ) {
		$achievs = self::AchievementsFromCounter( $opt[0] );
		foreach ( $achievs as &$ac ) {
			if ( !$ac->isStatic() && $ac->isActive() ) {
				$counter = $ac->getCounter();
				call_user_func_array( [$counter, 'updateHook'], func_get_args() );
			}
		}
	}

	static public function &AchievementFromID ( $id ) {
		global $wgAchievementsConfigs;
		if ( $id === '' || !isset( $wgAchievementsConfigs[$id] ) ) {
			$err = false;
			return $err;
		}
		if ( !isset( self::$list[$id] ) ) self::$list[$id] = new Achievement( $id, $wgAchievementsConfigs[$id] );
		return self::$list[$id];
	}

	static public function &AchievementFromStagedID ( $id, &$stage = 0, &$count = 0 ) {
		if ( $id === '' ) {
			$err = false;
			return $err;
		}

		list ( $name, $stage, $count ) = Achievement::sepStageName( $id );

		return self::AchievementFromID( $name );
	}

	static public function AchievementsFromAll () {
		global $wgAchievementsConfigs;
		$res = [];
		foreach ( $wgAchievementsConfigs as $id => &$config ) {
			$ac = self::AchievementFromID( $id );
			if ( $ac ) $res[] = $ac;
		}
		return $res;
	}

	static public function AchievementsFromCounter ( $countername = false ) {
		global $wgAchievementsConfigs;
		$res = [];
		foreach ( $wgAchievementsConfigs as $id => &$config ) {
			$value = isset( $config['type'] ) ? $config['type'] : false;
			if ( $value === $countername ) {
				$ac = self::AchievementFromID( $id );
				if ( $ac ) $res[] = $ac;
			}
		}
		return $res;
	}

	static public function AchievementsFromReset ( $reset = false ) {
		global $wgAchievementsConfigs;
		$res = [];
		foreach ( $wgAchievementsConfigs as $id => &$config ) {
			$value = isset( $config['reset'] ) ? $config['reset'] : false;
			if ( $value === $reset ) {
				$ac = self::AchievementFromID( $id );
				if ( $ac ) $res[] = $ac;
			}
		}
		return $res;
	}

	static public function sortAchievements ( &$res ) {
		$order = array_keys( self::configs() );
		usort( $res, function ( &$a, &$b ) use ( $order ) {
			$an = $a->getID();
			$bn = $b->getID();
			if ($an == $bn) return 0;
			return array_search( $an, $order ) < array_search( $bn, $order ) ? -1 : 1;
		} );
	}

	static public function updateUserAchievs ( $user ) {
		if ( $user instanceof \User && !$user->isAnon() && !$user->isBlocked() ) {
			$list = self::AchievementsFromReset( false );

			foreach ( $list as &$achiev ) {
				if ( !$achiev->isStatic() && $achiev->isActive() && !$achiev->isMultiple() ) {
					$achiev->updateAchiev( $user, false );
				}
			}
			self::clearUserCache ( $user, 'refresh' );
		}
	}

	static public function getUserTitle ( $user, $plain = true ) {
		if ( $user instanceof \User && !$user->isAnon() && !$user->isBlocked() ) {
			$title = $user->getOption( 'achievtitle', '' );
			if ( self::quickCheckUserAchiev( $user, $title ) ) {
				$stage = 0;
				$achiev = self::AchievementFromStagedID( $title, $stage );
				if ( $achiev !== false ) {
					return $achiev->getAfterLinkMsg( $stage, $plain );
				}
			}
		}
		return false;
	}

	static public function getAchievers ( $stagename, $stage = null ) {
		if ( $stagename === '' ) return [];
		if ( !is_null( $stage ) && $stage > 0 ) {
			$stagename .= ':' . $stage;
		}
		
		$stage = 0;
		$achiev = self::AchievementFromStagedID( $stagename, $stage );
		if ( $achiev === false ) {
			return [];
		}
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'achievements',
			'ac_user',
			[ 'ac_id' => $achiev->getStageName( $stage ), 'ac_date IS NOT NULL' ]
		);

		if ( $res ) {
			$data = [];
			while ( $row = $res->fetchRow() ) {
				$data[] = (int)$row['ac_user'];
			}
			return $data;
		}
		return [];

	}

	static public function countAchievers ( $stagename, $stage = null, $refresh = false ) {
		if ( $stagename === '' ) return 0;
		if ( !is_null( $stage ) && $stage > 0 ) {
			$stagename .= ':' . $stage;
		}
		
		$cache = \ObjectCache::getMainWANInstance();
		$key = $cache->makeKey( 'achiev', 'count', $stagename );
		
		if ( $refresh ) $cache->delete( $key, 1 );

		return $cache->getWithSetCallback(
			$key,
			$cache::TTL_HOUR * 24, // Cache for 24 hours
			function ( $oldValue, &$ttl, &$setOpts ) use ( $stagename ) { // Function to generate the value on cache miss
				$stage = 0;
				$achiev = self::AchievementFromStagedID( $stagename, $stage );
				if ( $achiev === false ) {
					return 0;
				}
				$dbr = wfGetDB( DB_SLAVE );

				$setOpts = \Database::getCacheSetOptions( $dbr );

				return (int)$dbr->selectField(
					'achievements',
					'COUNT(1)',
					[ 'ac_id' => $achiev->getStageName( $stage ), 'ac_date IS NOT NULL' ]
				);
			}
		);
	}

	static public function getUserAchievIDs ( $user ) {
		if ( !($user instanceof \User) ) {
			return [];
		}
		$cache = \ObjectCache::getMainWANInstance();
		
		return $cache->getWithSetCallback(
			$cache->makeKey( 'achiev', 'user', 'achiev', $user->getId() ),
			$cache::TTL_HOUR * 120, // Cache for 120 hours
			function ( $oldValue, &$ttl, &$setOpts ) use ( $user ) { // Function to generate the value on cache miss
				if ( $user->isAnon() || $user->isBlocked() ) return [];
				$dbw = wfGetDB( DB_SLAVE );

				$setOpts = \Database::getCacheSetOptions( $dbw );

				$res = $dbw->select(
					'achievements',
					[ 'ac_id', 'ac_date' ],
					[ 'ac_user' => $user->getId(), 'ac_date IS NOT NULL' ],
					__METHOD__
				);
				if ( $res ) {
					$data = [];
					while ( $row = $res->fetchRow() ) {
						$data[$row['ac_id']] = wfTimestamp( TS_UNIX, $row['ac_date'] );
					}
					ksort( $data, SORT_NATURAL );
					return $data;
				}
				return [];
			}
		);
	}

	static public function getUserAchievs ( $user ) {
		if ( !($user instanceof \User) ) {
			return [];
		}

		$data = self::getUserAchievIDs( $user );

		$list = [];
		foreach ( $data as $id => $ts ) {
			$achiev = self::AchievementFromStagedID( $id, $stage, $count );
			if ( $achiev === false || $count > 0 ) continue;
			$name = $achiev->getID();
			if ( $stage == 0 ) {
				$list[$name] = [ $ts ];
			} else {
				if ( !isset( $list[$name] ) ) $list[$name] = [];
				$list[$name][intval( $stage )] = $ts;
			}
		}
		return $list;
	}

	static public function getUserCounts ( $user ) {
		if ( !($user instanceof \User) ) {
			return [];
		}
		$dbw = wfGetDB( DB_SLAVE );

		$res = $dbw->select(
			'achievements',
			[ 'ac_id', 'ac_count' ],
			[ 'ac_user' => $user->getId() ],
			__METHOD__
		);
		if ( $res ) {
			$data = [];
			while ( $row = $res->fetchRow() ) {
				$data[$row['ac_id']] = intval( $row['ac_count'] );
			}
			ksort( $data, SORT_NATURAL );
			return $data;
		}
		return [];
	}

	static public function getUserScore ( $user ) {
		if ( !($user instanceof \User) ) {
			return 0;
		}

		$aclist = AchievementHandler::getUserAchievs( $user );
		$score = 0;
		foreach ( $aclist as $aid => $tss ) {
			$ac = AchievementHandler::AchievementFromID( $aid );
			foreach ( $tss as $stage => $ts ) {
				$score += $ac->getStageScore( $stage );
			}
		}
		return $score;
	}

	static public function Score2Level ( $score = 0 ) {
		$level = 0;
		while ( $level < 99 && $score >= Self::Level2Score( $level + 1 ) ) {
			++$level;
		}
		return $level;
	}
	
	static public function Level2Score ( $level = 0 ) {
		$level = max( 0, min( 99, $level ) );
		return $level * ( $level + 2 ) * 10;
	}

	static public function quickCheckUserAchiev ( $user, $id ) {
		if ( $id === '' || !($user instanceof \User) || $user->isAnon() || $user->isBlocked() ) {
			return false;
		}

		$list = self::getUserAchievIDs( $user );

		return isset( $list[$id] ) ? $list[$id] : false;
	}

	// $mode Use 'refresh' to clear now; otherwise before DB commit
	static public function clearUserCache ( $user, $mode = 'changed' ) {
		if ( !($user instanceof \User) ) {
			return;
		}

		$cache = \ObjectCache::getMainWANInstance();
		$key = $cache->makeKey( 'achiev', 'user', 'achiev', $user->getId() );
		if ( $mode === 'refresh' ) {
			$cache->delete( $key, 1 );
		} else {
			wfGetDB( DB_MASTER )->onTransactionPreCommitOrIdle(
				function() use ( $cache, $key ) {
					$cache->delete( $key );
				},
				__METHOD__
			);
		}
	}

}