<?php

namespace Achiev;

use \SpecialPage;
use \User;
use \Html;
use \HTMLForm;

class SpecialManageAchievements extends SpecialPage {
	
	protected $successMessage = '';

	const MODE_LIST = 1;
	const MODE_PROG = 2;
	const MODE_ACHIEVER = 3;
	const MODE_TITLE = 4;
	const MODE_RECENT = 5;
	const MODE_USER = 6;
	const MODE_MSG = 7;
	const MODE_RANKING = 8;
	const MODE_SCORING = 9;
	const MODE_TOKEN = 20;
	const MODE_TOKENS = 21;
	const MODE_RANDOM = 99;

	function __construct() {
		parent::__construct( 'ManageAchievements', 'manageachievements' );
	}

	public function doesWrites() {
		return true;
	}

	function getGroupName() {
		return 'users';
	}
	
	public static function getMode( $request, $par ) {
		$mode = strtolower( $request->getVal( 'action', $par ) );

		switch ( $mode ) {
			case 'list':
			case self::MODE_LIST:
				return self::MODE_LIST;
			case 'prog':
			case self::MODE_PROG:
				return self::MODE_PROG;
			case 'achiever':
			case self::MODE_ACHIEVER:
				return self::MODE_ACHIEVER;
			case 'title':
			case self::MODE_TITLE:
				return self::MODE_TITLE;
			case 'recent':
			case self::MODE_RECENT:
				return self::MODE_RECENT;
			case 'user':
			case self::MODE_USER:
				return self::MODE_USER;
			case 'msg':
			case self::MODE_MSG:
				return self::MODE_MSG;
			case 'ranking':
			case self::MODE_RANKING:
				return self::MODE_RANKING;
			case 'scoring':
			case self::MODE_SCORING:
				return self::MODE_SCORING;
			case 'token':
			case self::MODE_TOKEN:
				return self::MODE_TOKEN;
			case 'tokens':
			case self::MODE_TOKENS:
				return self::MODE_TOKENS;
			case 'random':
			case self::MODE_RANDOM:
				return self::MODE_RANDOM;
			default:
				return false;
		}
	}

	public static function buildTools( $lang, $linkRenderer = null ) {
		if ( !$lang instanceof \Language ) {
			// back-compat where the first parameter was $unused
			global $wgLang;
			$lang = $wgLang;
		}
		if ( !$linkRenderer ) {
			$linkRenderer = \MediaWikiServices::getInstance()->getLinkRenderer();
		}

		$tools = [];
		$modes = [
			'list',
			'prog',
			'achiever',
			'title',
			'recent',
			'user',
			'msg',
			'ranking',
			'scoring',
			'token',
			'tokens',
		];

		foreach ( $modes as $mode ) {
			$tools[] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'ManageAchievements', $mode ),
				wfMessage( 'manageachievements-mode-' . $mode )->text()
			);
		}

		return Html::rawElement(
			'span',
			[ 'class' => 'mw-manageachievements-toollinks' ],
			$linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'ManageAchievements' ),
				wfMessage( 'manageachievements' )->text()
			) . wfMessage( 'parentheses' )->rawParams( $lang->pipeList( $tools ) )->escaped()
		);
	}

	function execute( $mode ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$lang = $this->getLanguage();

		// Set the page title, robot policies, etc.
		$this->setHeaders();

		if ( !$user->isAllowed( 'manageachievements' ) ) {
			throw new \PermissionsError( 'manageachievements' );
		}
		
		$this->checkReadOnly();

		$out->addSubtitle( 
			self::buildTools(
				$this->getLanguage(),
				$this->getLinkRenderer()
			)
		);

		$mode = self::getMode( $request, $mode );

		$target = $request->getVal( 'user' );

		if ( is_string( $target ) && ($target = trim( $target )) !== '' ) {
			$cuser = User::newFromName( $target );
		}

		switch ( $mode ) {
			case self::MODE_LIST:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-list' ) );
				$allachievs = AchievementHandler::AchievementsFromAll();
				AchievementHandler::sortAchievements( $allachievs );
				
				$table = '';
				foreach ( $allachievs as &$achiev ) {
					$id = $achiev->getID();
					$count = [];
					$table .= \ExtAchievement::buildAchievBlock( $achiev, null, $count, $user );
				}
				$out->addHTML( $table );
				$out->addModules( [ 'ext.pref.achievement' ] );
				break;
			case self::MODE_PROG:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-prog' ) );
				$this->showAchievForm( 'processProgInput' );
				break;
			case self::MODE_ACHIEVER:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-achiever' ) );
				$this->showAchievForm( 'processAchieverInput' );
				break;
			case self::MODE_TITLE:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-title' ) );
				$dbw = wfGetDB( DB_SLAVE );

				$res = $dbw->select(
					'user_properties',
					[ 'up_user', 'up_value' ],
					[ 'up_property' => 'achievtitle', 'up_value <> \'\'' ],
					__METHOD__,
					[ 'ORDER BY' => 'up_value ASC' ]
				);
				if ( $res ) {
					$out->addHTML( $this->msg( 'manage-achiev-user-count' )->rawParams( $dbw->numRows( $res ) )->escaped() . '<br/>' );
					$out->addHTML( '<ul>' );
					while ( $row = $res->fetchRow() ) {
						$stage = 0;
						$achiev = AchievementHandler::AchievementFromStagedID( $row['up_value'], $stage );
						if ( $achiev !== false ) {
							$out->addHTML('<li>' . User::whoIs( $row['up_user'] ) . ' '. $achiev->getAfterLinkMsg( $stage, false ) . '</a></li>' );
						}
						
					}
					$out->addHTML( '</ul>' );
				}
				break;
			case self::MODE_RECENT:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-recent' ) );
				$dbw = wfGetDB( DB_SLAVE );

				$res = $dbw->select(
					['echo_event', 'echo_notification'],
					[ 'event_type', 'event_agent_id', 'event_extra', 'notification_timestamp' ],
					[ 'event_type' => ['achiev-award','achiev-remove'] ],
					__METHOD__,
					[ 'ORDER BY' => 'event_id DESC', 'LIMIT' => 100 ],
					[
						'echo_notification' => [
							'LEFT JOIN',
							[ 'event_id=notification_event' ]
						]
					]
				);
				if ( $res ) {
					$out->addHTML( '<ul>' );
					while ( $row = $res->fetchRow() ) {
						$extra = unserialize( $row['event_extra'] );
						$stage = 0;
						$achiev = AchievementHandler::AchievementFromStagedID( $extra['achievid'], $stage );
						if ( $achiev !== false ) {
							$auser = \User::newFromId( $row['event_agent_id'] );
							$ts = wfTimestampOrNull( TS_UNIX, $row['notification_timestamp'] );
							
							$out->addHTML('<li>' . $auser->getName() . ': '. 
							$this->msg( 'notification-body-' . $row['event_type'] )->rawParams($achiev->getNameMsg( $stage ), $achiev->getDescMsg( $stage )).
							( $ts ? $this->msg( 'parentheses' )->rawParams( $lang->userTimeAndDate($ts, $user) )->escaped() : '' ).'</li>' );
						}
					}
					$out->addHTML( '</ul>' );
				}
				break;
			case self::MODE_USER:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-user' ) );
				$this->showUserForm( 'processUserInput' );
				$out->addModules( [ 'ext.pref.achievement' ] );
				break;
			case self::MODE_MSG:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-msg' ) );
				$list = AchievementHandler::AchievementsFromAll();
				$json = [];

				foreach ( $list as &$achiev ) {
					$namekeys = $achiev->listNameMsgKeys();
					$desckeys = $achiev->listDescMsgKeys();
					foreach ( $namekeys as $i => $msgkey ) {
						$json[$msgkey] = $this->msg( $msgkey )->exists() ? $this->msg( $msgkey )->plain() : '';
						$json[$desckeys[$i]] = $this->msg( $desckeys[$i] )->exists() ? $this->msg( $desckeys[$i] )->plain() : '';
					}
				}
				$out->addHTML( '<textarea rows="40">' . htmlspecialchars(
					preg_replace( '/^\s+/m', "\t", json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) )
				) . '</textarea>' );
				break;
			case self::MODE_RANKING:
				global $wgAchievementsScoring;

				$out->setPageTitle( $this->msg( 'manageachievements-mode-ranking' ) );
				$dbw = wfGetDB( DB_SLAVE );

				$res = $dbw->select(
					'achievements',
					[ 'ac_user', 'COUNT(1) as ac_count' ],
					[ 'ac_date IS NOT NULL' ],
					__METHOD__,
					[ 'ORDER BY' => 'ac_count DESC', 'GROUP BY' => 'ac_user', 'LIMIT' => 100 ]
				);
				if ( $res ) {
					$out->addHTML(
						Html::openElement( 'table', [ 'class' => 'wikitable sortable' ] ) .
						Html::openElement( 'tr' ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-ranking-user' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-ranking-achiev' )->text() ) .
						( $wgAchievementsScoring ?
							Html::rawElement( 'th', [], $this->msg( 'manage-achiev-ranking-score' )->text() ) .
							Html::rawElement( 'th', [], $this->msg( 'manage-achiev-ranking-level' )->text() )
							: ''
						) .
						Html::closeElement( 'tr' )
					);
					while ( $row = $res->fetchRow() ) {
						$cuser = User::newFromId( $row['ac_user'] );
						$uscore = AchievementHandler::getUserScore( $cuser );
						$ulevel = AchievementHandler::Score2Level( $uscore );
						$out->addHTML(
							Html::openElement( 'tr' ) .
							Html::rawElement( 'td', [], $cuser->getName() ) .
							Html::rawElement( 'td', [], $row['ac_count'] ) .
							( $wgAchievementsScoring ?
								Html::rawElement( 'td', [], $uscore ) .
								Html::rawElement( 'td', [], $ulevel )
								: ''
							) .
							Html::closeElement( 'tr' )
						);
					}
					$out->addHTML( Html::closeElement( 'table' ) );
				}
				break;
			case self::MODE_SCORING:
				global $wgAchievementsScoring;

				$out->setPageTitle( $this->msg( 'manageachievements-mode-scoring' ) );
				
				if ( !$wgAchievementsScoring ) {
					$out->addHTML( $this->msg( 'manage-achiev-disable-scoring' ) );
					break;
				}
				$allachievs = AchievementHandler::AchievementsFromAll();
				
				$list = [];
				foreach ( $allachievs as &$achiev ) {
					$thresholds = $achiev->getConfig('threshold', 1);
					if ( $achiev->isStaged() ) {
						foreach ( $thresholds as $stage ) {
							$c = $achiev->getStageScore($stage);
							if ( $c !== false ) $list[$achiev->getStageName($stage)] = $c;
						}
					} else {
						$c = $achiev->getStageScore();
						if ( $c !== false ) $list[$achiev->getStageName()] = $c;
					}
				}
				arsort($list, SORT_NUMERIC);
				$out->addHTML( '<ol>' );
				foreach ( $list as $stagename => $score ) {
					$achiev = AchievementHandler::AchievementFromStagedID( $stagename, $stage );
					if ( $achiev !== false ) {
						$out->addHTML( '<li>('.$stagename.') '.$achiev->getNameMsg( $stage ) . $this->msg( 'parentheses' )->rawParams( $achiev->getDescMsg( $stage ) )->text() .': '. $score .'</li>' );
					}
				}
				$out->addHTML( '</ol>' );
				break;
			case self::MODE_TOKEN:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-token' ) );
				$this->showTokenForm( 'processTokenInput' );
				break;
			case self::MODE_TOKENS:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-tokens' ) );
				$delete = trim( $request->getVal( 'delete', '' ) );
				
				if ( !empty( $delete ) ) {
					Token::deleteByHash( $delete );
					$out->redirect( SpecialPage::getTitleFor( 'ManageAchievements', 'tokens' ) );
					break;
				}

				$dbw = wfGetDB( DB_SLAVE );
				$keyprefix = str_replace( '@@', '', Token::getMemcKey( '@@' ) );

				$res = $dbw->select(
					'objectcache',
					[ 'keyname', 'exptime' ],
					[ 'keyname ' . $dbw->buildLike( $keyprefix, $dbw->anyString() ) ],
					__METHOD__,
					[ 'ORDER BY' => 'exptime ASC', 'LIMIT' => 1000 ]
				);
				if ( $res ) {
					$linkRenderer = $this->getLinkRenderer();

					$keylist = [];
					$explist = [];
					foreach ( $res as $row ) {
						$keylist[] = $row->keyname;
						$explist[$row->keyname] = $row->exptime;
					}
					$cache = \ObjectCache::getMainStashInstance();
					$datalist = $cache->getMulti( $keylist );
					
					$out->addHTML(
						Html::openElement( 'table', [ 'class' => 'wikitable sortable' ] ) .
						Html::openElement( 'tr' ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-hash' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-achievid' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-user' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-target' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-count' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-limit' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'manage-achiev-tokens-exptime' )->text() ) .
						Html::rawElement( 'th', [], $this->msg( 'delete' )->text() ) .
						Html::closeElement( 'tr' )
					);
					foreach ( $datalist as $key => &$data ) {
						if ( empty( $data ) || !isset( $data['achiev'] ) ) continue;
						$hash = str_replace( $keyprefix, '', $key );
						$out->addHTML( Html::openElement( 'tr' ) );
						$out->addHTML( Html::rawElement( 'td', [], $hash ) );
						$out->addHTML( Html::rawElement( 'td', [], $data['achiev'] ) );
						if ( !empty( $data['user'] ) ) {
							$out->addHTML( Html::rawElement( 'td', [], User::whoIs( $data['user'] ) ) );
						} else {
							$out->addHTML( Html::rawElement( 'td', [], '' ) );
						}
						if ( !empty( $data['target'] ) ) {
							$out->addHTML( Html::rawElement( 'td', [], User::whoIs( $data['target'] ) ) );
						} else {
							$out->addHTML( Html::rawElement( 'td', [], '' ) );
						}
						if ( isset( $data['count'] ) ) {
							$out->addHTML( Html::rawElement( 'td', [], $data['count'] ) );
						} else{
							$out->addHTML( Html::rawElement( 'td', [], '' ) );
						}
						if ( isset( $data['limit'] ) ) {
							$out->addHTML( Html::rawElement( 'td', [], $data['limit'] ) );
						} else{
							$out->addHTML( Html::rawElement( 'td', [], '' ) );
						}
						$out->addHTML( Html::rawElement( 'td', [ 'data-sort-value' => $explist[$key] ], $lang->userTimeAndDate( $explist[$key], $user ) ) );

						$out->addHTML( Html::rawElement( 'td', [], $linkRenderer->makeKnownLink(
							SpecialPage::getTitleFor( 'ManageAchievements', 'tokens' ),
							$this->msg( 'delete' )->text(),
							[],
							['delete' => $hash]
						) ) );
						$out->addHTML( Html::closeElement( 'tr' ) );
					}
					$out->addHTML( Html::closeElement( 'table' ) );
				}
				break;
			case self::MODE_RANDOM:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-random' ) );
				$list = AchievementHandler::AchievementsFromCounter( 'random' );
				$clear = trim( $request->getVal( 'clear', '' ) );
				$out->addHTML( '<ul>' );
				foreach ( $list as $ac ) {
					$cleared = false;
					if ( $ac->getID() === $clear ) {
						$ac->getCounter()->clearNextTimestamp();
						$cleared = true;
					}
					$out->addHTML( '<li>'.$ac->getID().': '.date('Y-m-d H:i:s', $ac->getCounter()->getNextTimestamp() ).($cleared?' (NEW)':'')."</li>\n" );
				}
				$out->addHTML( '</ul>' );
				break;
			default:
				
		}

		$out->addHTML( $this->successMessage );

		return;
	}

	protected function showAchievForm ( $callback ) {
		$formDescriptor = array(
			'achiev' => array(
				'label-message' => 'manage-achiev-name',
				'class' => 'HTMLTextField',
				'default' => '',
			),
		);

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'manage-achiev' );
		$htmlForm->setWrapperLegend( '' );

		$htmlForm->setMethod( 'get' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, $callback ] );  

		return $htmlForm->show();
	}

	protected function showUserForm ( $callback ) {
		$formDescriptor = array(
			'user' => array(
				'label-message' => 'manage-achiev-user',
				'class' => 'HTMLTextField',
				'default' => '',
			),
		);

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'manage-achiev' );
		$htmlForm->setWrapperLegend( '' );

		$htmlForm->setMethod( 'get' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, $callback ] );  

		return $htmlForm->show();
	}

	protected function showTokenForm ( $callback ) {
		$formDescriptor = array(
			'achiev' => array(
				'label-message' => 'manage-achiev-name',
				'class' => 'HTMLTextField',
				'default' => '',
			),
			'target' => array(
				'label-message' => 'manage-achiev-token-user',
				'class' => 'HTMLTextField',
				'default' => '',
			),
			'count' => array(
				'label-message' => 'manage-achiev-token-count',
				'class' => 'HTMLTextField',
				'default' => '',
			),
			'limit' => array(
				'label-message' => 'manage-achiev-token-limit',
				'class' => 'HTMLTextField',
				'default' => '1',
			),
			'time' => array(
				'label-message' => 'manage-achiev-token-time',
				'class' => 'HTMLTextField',
				'default' => '86400',
			),
		);

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'manage-achiev' );
		$htmlForm->setWrapperLegend( '' );

		$htmlForm->setMethod( 'post' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, $callback ] );  

		return $htmlForm->show();
	}

	public function processProgInput ( $data ) {
		if ( !empty( $data['achiev'] ) ) {
			$id = $data['achiev'];
			$achiev = AchievementHandler::AchievementFromID( $id );
			if ( $achiev ) {
				$dbw = wfGetDB( DB_SLAVE );

				$res = $dbw->select(
					'achievements',
					[ 'ac_user', 'ac_count' ],
					[ 'ac_id' => $id, 'ac_count <> 0' ],
					__METHOD__,
					[ 'ORDER BY' => 'ac_count DESC', 'LIMIT' => 200 ]
				);
				$count = $dbw->numRows( $res );
				$this->successMessage .= $achiev->getNameMsg() . $this->msg( 'manage-achiev-user-count' )->rawParams( $count == 200?'200+':$count )->escaped() . '<br/>';
				if ( $res ) {
					$lr = $this->getLinkRenderer();
					$this->successMessage .= '<ol>';
					while ( $row = $res->fetchRow() ) {
						$this->successMessage .= '<li>' . User::whoIs( $row['ac_user'] ) . ': ' . $row['ac_count'] . '</li>';
					}
					$this->successMessage .= '</ol>';
				}
			}
		}
		return false;
	}

	public function processAchieverInput ( $data ) {
		if ( !empty( $data['achiev'] ) ) {
			$id = $data['achiev'];
			$stage = 0;
			$achiev = AchievementHandler::AchievementFromStagedID( $id, $stage );
			if ( $achiev ) {
				$dbw = wfGetDB( DB_SLAVE );

				$list = AchievementHandler::getAchievers( $id );
				$this->successMessage .= $achiev->getNameMsg( $stage ) . $this->msg( 'manage-achiev-user-count' )->rawParams( count( $list ) )->escaped() . '<br/>';
				$this->successMessage .=  '<ul>';
				foreach ( $list as $userid ) {
					$this->successMessage .= '<li>'.User::whoIs( $userid ).'</li>';
				}
				$this->successMessage .=  '</ul>';
			}
		}
		return false;
	}

	public function processUserInput ( $data ) {
		if ( !empty( $data['user'] ) ) {
			if ( is_string( $data['user'] ) && ($data['user'] = trim( $data['user'] )) !== '' ) {
				$user = User::newFromName( $data['user'] );
			}
			if ( !($user instanceof User) || $user->isAnon() || $user->isBlocked() ) {
				return false;
			}
			AchievementHandler::updateUserAchievs( $user );
			$allachievs = AchievementHandler::AchievementsFromAll();
			AchievementHandler::sortAchievements( $allachievs );
			$userachievs = AchievementHandler::getUserAchievs( $user );
			$counts = AchievementHandler::getUserCounts( $user );
			
			$this->successMessage = '';
			foreach ( $allachievs as &$achiev ) {
				$id = $achiev->getID();
				if ( empty( $userachievs[$id] ) ) {
					if ( !$achiev->getConfig( 'hidden', false ) ) {
						$this->successMessage .= \ExtAchievement::buildAchievBlock( $achiev, [], $counts, $user );
					}
				} else {
					$tss = $userachievs[$id];
					$this->successMessage .= \ExtAchievement::buildAchievBlock( $achiev, $tss, $counts, $user );
				}
			}
		}
		return false;
	}
	
	public function processTokenInput ( $data ) {
		if ( !empty( $data['achiev'] ) ) {
			$stage = 0;
			$stagename = trim( $data['achiev'] );
			$achiev = AchievementHandler::AchievementFromStagedID( $stagename, $stage );
			if ( $achiev ) {
				$opt = [];
				if ( !empty( $data['target'] ) ) $opt['target'] = User::newFromName( $data['target'] );

				if ( !empty( $data['count'] ) ) $opt['count'] = intval( $data['count'] );

				if ( !empty( $data['limit'] ) ) $opt['limit'] = max( 1, intval( $data['limit'] ) );
				else $opt['limit'] = 1;

				if ( !empty( $data['time'] ) ) $opt['time'] = intval( $data['time'] );
				else $opt['time'] = 86400;
				
				$token = Token::getToken( $this->getUser(), $achiev, $stage, $opt );
				if ( $token ) {
					if ( $opt['target'] instanceof \User ) {
						$this->successMessage .= $this->msg( 'manage-achiev-token-user' )->parse() . $opt['target']->getName() . '<br />';
					}
					if ( isset( $opt['count'] ) ) {
						$this->successMessage .= $this->msg( 'manage-achiev-token-count' )->parse() . $opt['count'] . '<br />';
					}
					if ( isset( $opt['limit'] ) ) {
						$this->successMessage .= $this->msg( 'manage-achiev-token-limit' )->parse() . $opt['limit'] . '<br />';
					}
					if ( isset( $opt['time'] ) ) {
						$this->successMessage .= $this->msg( 'manage-achiev-token-time' )->parse() . $opt['time'] . '<br />';
					}
					$this->successMessage .= Html::element( 'input', ['type' =>'text', 'value' => $token->toString() ] ). '<br />';
					$this->successMessage .= Html::element( 'a', ['target' =>'_blank', 'href' => $token->toUrl() ], $token->toUrl() ). '<br />';
				} else {
					if ( $achiev->isAwardable() ) {
						return $this->msg( 'manage-achiev-token-fail' )->parse();
					} else {
						return $this->msg( 'manage-achiev-token-notawardable' )->parse();
					}
				}
			} else {
				return $this->msg( 'manage-achiev-token-invalid' )->parse();
			}
		}
		return false;
	}
}