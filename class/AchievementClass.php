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

	public function getStageName ( $stage = 0, $sep = ':' ) {
		return $this->id . ( $stage > 0 ? $sep . $stage : '' );
	}

	public static function sepStageName ( $stagename = '', $sep = ':' ) {
		$list = explode( ':', $stagename, 3 ) + [ '', 0, 0 ];
		$list[1] = intval( $list[1] );
		$list[2] = intval( $list[2] );
		return $list;
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
		return $this->getConfig( 'reset', false );
	}

	public function isAwardable () {
		return $this->getConfig( 'awardable', false );
	}

	public function isRemovable () {
		return $this->getConfig( 'removable', false );
	}

	public function isMultiple () {
		return $this->getConfig( 'multiple', false ) && ( $this->getReset() || $this->isStatic () );
	}

	public function isActive () {
		return $this->getConfig( 'active', false ) && $this->inActiveRange();
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
		return is_array( $this->getConfig( 'threshold', false ) );
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

	public function getStageScore ( $stage = 0, $total = false ) {
		$dscore = 0;
		if ( $this->isStaged() ) {
			if ( !$this->isValidStage( $stage ) ) return 0;
			if ( !$total ) {
				// 非排名的多阶段成就的分数需要减去前面所有阶段分数的总和，防止各个阶段重复累积太多分数
				$thresholds = $this->getConfig( 'threshold' );
				$i = array_search( $stage, $thresholds, true ) + ( $this->isStageReversed() ? 1: -1 );
				if ( isset( $thresholds[$i] ) ) $dscore = Self::getStageScore( $thresholds[$i], true );
			}
		} else {
			 $stage = $this->getConfig( 'threshold' );
		}
		if ( $stage == 0 ) return 0;

		$uscore = 0; // 底分
		$multiply = $this->getConfig( 'scoremul', 0 ); // 管理员设定的乘数

		if ( $this->getCounterConfig('init', false) === 'current' ) {
			$icurr = 10; // 持续成就buff，初始值为current的排名成就需要长时间的努力
			$frate = 1; // 初始值为current不受更新频率影响
			$trate = 1; // 初始值为current不受更新频率影响
		} else {
			$icurr = 1; // 初始值为其他的成就
			$frate = 1; // 更新频率debuff，排名成就更新频率越高颁发次数越多，越容易获得
			$trate = 1; // 更新频率buff，非排名成就更新频率越高达成成就条件可用的时间越少，越难获得
			switch( $this->getReset() ){
				case 'd': // 日更
					$frate = 0.1;
					$trate = 10;
					break;
				case 'w': // 周更
					$frate = 0.5;
					$trate = 6;
					break;
				case 'm': // 月更
					$frate = 2;
					$trate = 4;
					break;
			}
		}

		$rate = 0.5; // 词条筛选buff，词条筛选条件越多成就越难
		if ($this->getCounterConfig('cat', false)||$this->getCounterConfig('ns', false)){
			$rate = 1.5; // 以分类或命名空间筛选
		}
		if ($this->getCounterConfig('page', false)){
			$rate = 2; // 以页面名称筛选，假设仅有少量符合的页面
		}

		switch( $this->getType() ){
			case 'static':
				 // 静态成就给予基本分，强烈建议设定scoremul来控制此分数
				if ( $this->isStaged() ) {
					$uscore = 5 * sqrt($stage); // 多阶成就根据给分阶段给分
				} else {
					$uscore = 1; // 单阶成就给1分
				}
				break;
			case 'watch':
				$uscore = 0; // 以上类型由于太容易、很难控制等原因不给予分数
				break;
			case 'viewcount': case 'viewtop': case 'random':
				$uscore += 1; // 半随机成就勉强给1分
				break;
			case 'editcount':
				$uscore += $trate * $rate * 5 * sqrt($stage) / 2 - $dscore;
				break;
			case 'edittop':
				$uscore += $icurr * $frate * $rate * 100 / $stage;
				break;
			case 'friendcount': case 'foecount':
				$uscore += $trate * 2 * 5 * sqrt($stage) - $dscore;
				break;
			case 'friendtop': case 'foetop':
				$uscore += $icurr * $frate * 5 / $stage;
				break;
			case 'usergroup': 
				$uscore += 10;
				break;
			case 'registerday': 
				$uscore += $stage / 50 - $dscore;
				break;
			default : 
				$uscore += 1;
				break;
		}
		return max(0, ceil( $multiply * $uscore ) );
	}

	public function getAfterLinkMsg ( $stage = 0, $plain = true ) {
		$text = wfMessage( 'achievtitle-afterlink' )->rawParams(
			wfMessage( $this->getNameMsgKey( $stage ) )->plain()
		)->text();
		if ( $plain ) {
			return strip_tags( $text );
		} else {
			if ( !is_null( $this->getConfig( 'image' ) ) ) {
				$image = $this->getConfig( 'image' );
			} else {
				$image = null;
			}
			return \Html::openElement( 'a', [ 'class' => 'achievtitle', 'title' => $this->getDescMsg( $stage ), 'src' => $image ] ) . $text;
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
				while( $row = $res->fetchRow() ) {
					list( , $stage ) = self::sepStageName( $row['ac_id'] );
					$oldacs[] = $stage;
					$oldtss[] = wfTimestampOrNull( TS_UNIX, $row['ac_date'] );
				}
			}
			if ( $this->isRemovable() ) {
				foreach ( $oldacs as $oldac ) {
					if ( !$this->isValidStage( $oldac ) || !$this->isQualify( $maincount, $oldac ) ) {
						try {
							$this->removeFrom( $user, $oldac );
						} catch ( \Exception $e ) {
							$note .= '-failed';
						}
					}
				}
			}
			foreach ( $this->config['threshold'] as $threshold ) {
				if ( $this->isQualify( $maincount, $threshold ) ) {
					$pos = array_search( $threshold, $oldacs );
					if ( $this->isMultiple() || $pos === false || is_null( $oldtss[$pos] ) ) {
						try {
							$this->awardTo( $user, $threshold, $maincount );
						} catch ( \Exception $e ) {
							$note .= '-failed';
						}
					}
				}
			}
		} else {
			$note .= 'nostage';
			$threshold = $this->config['threshold'];
			if ( $this->isQualify( $maincount, $threshold ) ) {
				$note .= '-qualify';
				if ( $this->isMultiple() || is_null( $maindate ) ) {
					$note .= '-award';
					try {
						$this->awardTo( $user, 0, $maincount );
					} catch ( \Exception $e ) {
						$note .= '-failed';
					}
				}
			} else {
				$note .= '-dequalify';
				if ( $this->isRemovable() && !is_null( $maindate ) ) {
					$note .= '-remove';
					try {
						$this->removeFrom( $user );
					} catch ( \Exception $e ) {
						$note .= '-failed';
					}
				}
			}
		}
		if ( $clearCache ) AchievementHandler::clearUserCache( $user );
		return $note;
	}

	public function addStaticCountTo ( \User $user, &$count = 0 ) {
		if ( $this->isBlocked( $user ) ) throw new AchievError( 'blocked-user' );
		if ( !$this->isAwardable() || !$this->isActive() ) throw new AchievError( 'achiev-notawardable' );
		$this->counter->updateHook( ['static', 'Achievement::addStaticCountTo'], $user, $count );
		AchievementHandler::clearUserCache( $user );
		return true;
	}

	public function awardStaticTo ( \User $user, $stage = 0, $count = 0 ) {
		if ( $this->isBlocked( $user ) ) throw new AchievError( 'blocked-user' );
		if ( !$this->isAwardable() || !$this->isActive() ) throw new AchievError( 'achiev-notawardable' );
		$this->awardTo( $user, $stage, $count );
		AchievementHandler::clearUserCache( $user );
		return true;
	}

	public function removeStaticFrom ( \User $user, $stage = 0 ) {
		if ( $this->isBlocked( $user ) ) throw new AchievError( 'blocked-user' );
		if ( !$this->isAwardable() || !$this->isActive() ) throw new AchievError( 'achiev-notawardable' );
		$this->removeFrom( $user, $stage );
		AchievementHandler::clearUserCache( $user );
		return true;
	}

	private function awardTo ( \User $user, $stage = 0, $setvalue = 0 ) {
		$setvalue = (int)$setvalue;
		if ( $this->isStaged() ) {
			if ( $this->isValidStage( $stage ) ) {
				$wid = $id = $this->getStageName( $stage );
				$setvalue = max( $setvalue, $stage );
			} else {
				throw new AchievError( 'invalid-stage' );
			}
		} else {
			$wid = $id = $this->id;
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
				throw new AchievError( 'achiev-conflict' );
			}
		}
		
		$already = $dbw->selectField(
			'achievements',
			[ 'ac_date' ],
			[ 'ac_user' => $user->getId(), 'ac_id' => $id, 'ac_date IS NOT NULL' ],
			__METHOD__
		);

		if ( $this->isMultiple() ) {
			if ( $already ) {
				if ( !$this->isStaged() ) $wid .= ':';
				$previd = $dbw->selectField(
					'achievements',
					[ 'ac_id' ],
					[ 'ac_user' => $user->getId(), 'ac_id' . $dbw->buildLike( $wid . ':', $dbw->anyString() ), 'ac_date IS NOT NULL' ],
					__METHOD__,
					[ 'ORDER BY' => 'ac_date DESC' ]
				);
				if ( $previd === false ) {
					$wid .= ':1';
				} else {
					list( , , $prev ) = self::sepStageName( $previd );
					$wid .= ':' . ( $prev + 1 );
				}
			}
		} else {
			if ( $already ) {
				throw new AchievError( 'achiev-already' );
			}
		}
		
		$dbw->update(
			'achievements',
			[ 'ac_date' => wfTimestamp( TS_MW ) ],
			[ 'ac_user' => $user->getId(), 'ac_id' => $wid ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		if ( $dbw->affectedRows() == 0 ) {
			$dbw->insert(
				'achievements',
				[ 'ac_user' => $user->getId(), 'ac_id' => $wid, 'ac_count' => $setvalue, 'ac_date' => wfTimestamp( TS_MW ) ]
			);
		}
		$failed = $dbw->affectedRows() == 0;
		if ( $failed ) {
			throw new AchievError( 'award-failed' );
		}
			
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
		return true;
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
		$aff = $dbw->affectedRows();

		if ( $this->isMultiple() ) {
			$dbw->delete(
				'achievements',
				[ 'ac_user' => $user->getId(), 'ac_id' . $dbw->buildLike( $id . ( $this->isStaged()?'':':' ) . ':', $dbw->anyString() ) ],
				__METHOD__
			);
		}
		$failed = $aff + $dbw->affectedRows() == 0;
		if ( $failed ) {
			throw new AchievError( 'remove-failed' );
		}

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
		return true;
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
			'scoremul' => 1,
			'multiple' => false,
			'exclude' => false,
			'removable' => false,
			'awardable' => false,
			'hidden' => false,
			'active' => true,
		];
	}
}