<?php

use Achiev\AchievementHandler;

class ExtAchievement {

	// 暂时没用的解析函数
	public static function init ( Parser &$parser ) {
		$parser->setFunctionHook( 'achieve', 'ExtAchievement::achieve' );
		return true;
	}

	// 暂时没用的解析函数
	public static function achieve ( $parser, $username ) {
		return '';
	}

	// 添加在所有页面中显示成就效果的模组
	static public function onBeforePageDisplay ( $out, &$sk ) {
		$out->addModules( 'ext.achievement' );
		return true;
	}

	// 添加在用户设定页面中显示成就列表的模组
	static public function onSpecialPageAfterExecute ( $special ) {
		if ( $special->getName() === 'Preferences' ) {
			$out = $special->getOutput();
			$out->addModules( [ 'ext.pref.achievement' ] );
		}
		return true;
	}

	// 生成成就列表区块
	static public function buildAchievBlock ( &$achiev, $tss = null, &$counts, $user ) {
		global $wgLang, $wgAchievementsScoring;

		$admin = is_null( $tss );
		$block = Html::openElement( 'div', [ 'class' => 'achiev-item' ] );

		$thresholds = $achiev->getConfig( 'threshold' );
		$realts = $achiev->hasRealThreshold();
		
		$image = '';
		if ( !is_null( $achiev->getConfig( 'image' ) ) ) {
			$image = Html::rawElement( 'img', [ 'src' => $achiev->getConfig( 'image' ) ] );
		} else {
			if ( $achiev->isStaged() ) {
				global $wgAchievementsIconStaged;
				$image = Html::rawElement( 'img', [ 'src' => $wgAchievementsIconStaged ] );
			} else {
				global $wgAchievementsIconNormal;
				$image = Html::rawElement( 'img', [ 'src' => $wgAchievementsIconNormal ] );
			}
		}
		
		$block .= Html::rawElement( 'div', [ 'class' => 'achiev-image' ], $image );

		$block .= Html::openElement( 'div', [ 'class' => 'achiev-body' ] );
		$block .= Html::rawElement( 'div', [ 'class' => 'achiev-name' ], $achiev->getNameMsg() );
		
		$stageBlocks = '';
		$lastStage = 0;
		$ativeStage = -1;
		$achievers = [];
		if ( !$achiev->isStatic() && $achiev->isStaged() ) {
			if ( $achiev->isStageReversed() ) {
				$thresholds = array_reverse( $thresholds, true );
				$half = count( $thresholds ) - (int)ceil( count( $thresholds ) / 2 );
			} else {
				$half = (int)ceil( count( $thresholds ) / 2 );
			}
			foreach ( $thresholds as $i => $threshold ) {
				if ( $i == $half ) {
					$stageBlocks .= '<br />';
				}
				if ( $admin || isset( $tss[$threshold] ) ) {
					$score = $wgAchievementsScoring ? $achiev->getStageScore( $threshold ) : 0;
					$stageBlocks .= self::tooltip(
						'div', $i + 1,
						$achiev->getNameMsg( $threshold ) . '<br />' .
						$achiev->getDescMsg( $threshold ) . '<div class="achiev-note">' .
						($score>0?wfMessage( 'achiev-award-score' )->rawParams( $score )->text() . '<br />':'') .
						wfMessage( 'achiev-award-date' )->rawParams(
							'<br />' . self::dateList( $wgLang, $user, is_null( $tss ) ? null : $tss[$threshold] )
						)->text() . '</div>',
						'achiev-block achiev-block-c'
					);
					;
					$lastStage = $i;
					$achievers[] = AchievementHandler::countAchievers( $achiev->getID(), $threshold, $admin );
				} elseif ( $ativeStage == -1 ) {
					$score = $wgAchievementsScoring ? $achiev->getStageScore( $threshold ) : 0;
					$ativeStage = $i;
					$stageBlocks .= self::tooltip(
						'div', $i + 1,
						$achiev->getNameMsg( $threshold ) . '<br />' .
						$achiev->getDescMsg( $threshold ) . '<div class="achiev-note">' .
						($score>0?wfMessage( 'achiev-award-score' )->rawParams( $score )->text() . '<br />':'') .
						wfMessage( 'achiev-award-none' )->text() . '</div>',
						'achiev-block achiev-block-a'
					);
					$achievers[] = AchievementHandler::countAchievers( $achiev->getID(), $threshold, $admin );
				} else {
					$stageBlocks .= self::tooltip(
						'div', $i + 1,
						wfMessage( 'achiev-mystery' )->text(),
						'achiev-block'
					);
					$achievers[] = wfMessage( 'achiev-achievers-count-mystery' )->text();
				}
				
			}
		}
		if ( count( $achievers ) == 0 ) {
			$achievers[] = AchievementHandler::countAchievers( $achiev->getID(), null, $admin );
		}

		$block .= Html::rawElement( 'div', [ 'class' => 'achiev-desc',  ], $achiev->getDescMsg() );
		
		$progBar = '';
		
		if ( !$achiev->isStatic() ) {
			$progTop = 0;
			$progBot = 1;
			if ( $achiev->isStaged() ) {
				if ( $ativeStage == -1 ) $ativeStage = $lastStage;
				$threshold = $thresholds[$ativeStage];
				$stagename = $achiev->getID();
				$nowcomplete = $admin || ( isset( $tss[$threshold] ) && !$achiev->isMultiple() );
				if ( $realts ) {
					if ( $nowcomplete ) {
						$stagename = $achiev->getStageName( $threshold );
						$progBot = $progTop = max(isset( $counts[$stagename] ) ? $counts[$stagename] : 0, 1);
					} else {
						$progTop = isset( $counts[$stagename] ) ? $counts[$stagename] : 0;
						$progBot = $achiev->getRealThreshold( $threshold );
					}			
				} else {
					$progTop = isset( $counts[$stagename] ) ? $counts[$stagename] : 0;
					$progBot = $threshold;
				}
			} else {
				$stagename = $achiev->getID();
				$nowcomplete = $admin || ( isset( $tss[0] ) && !$achiev->isMultiple() );
				if ( $realts ) {
					if ( $nowcomplete ) {
						$progBot = $progTop = max(isset( $counts[$stagename] ) ? $counts[$stagename] : 0, 1);
					} else {
						$progTop = isset( $counts[$stagename] ) ? $counts[$stagename] : 0;
						$progBot = $achiev->getRealThreshold( $thresholds );
					}
				} else {
					$progTop = isset( $counts[$stagename] ) ? $counts[$stagename] : 0;
					$progBot = $thresholds;
				}
			}
			if ( $admin ) $progTop = $progBot;
			$progBot = max( 1, $progBot );
			$progTop = min( max( 0, $progTop ), $progBot );
			$percent = round( max( 0, min( $progTop / $progBot, 1 ) ) * 100, 0 );
			$progtext = wfMessage( 'achiev-progtext' )->rawParams( $progTop, $progBot, $percent )->text();
			
			$progBar = Html::rawElement( 'div', [ 'class' => 'achiev-bar', 'title' => $progtext ],
				Html::rawElement( 'div', [ 'class' => 'achiev-barfill' . ($nowcomplete ? ' achiev-barfill-c' : ''), 'style' => 'width:' . $percent.'%' ], '' )
			) . Html::rawElement( 'div', [ 'class' => 'achiev-bartext' ], $progtext );
		}
		
		$block .= Html::rawElement( 'div', [ 'class' => 'achiev-prog' ], $progBar );

		$footnotes = [];
		if ( $achiev->getConfig( 'hidden', false ) ) {
			$footnotes[] = self::noteTooltip( 'hidden' );
		} else {
			if ( $achiev->isActive() ) {
				$footnotes[] = self::noteTooltip( 'active' );
			} else {
				$footnotes[] = self::noteTooltip( 'inactive' );
			}
			$activerange = $achiev->getConfig( 'activerange', false );
			if ( !empty( $activerange ) ) {
				$footnotes[] = self::tooltip(
					'span',
					wfMessage( 'achiev-config-activerange' )->text(),
					wfMessage( 'achiev-config-activerange-desc' )->rawParams(
						empty( $activerange[0] ) ? '' : $wgLang->userTimeAndDate( $activerange[0], $user ),
						empty( $activerange[1] ) ? '' : $wgLang->userTimeAndDate( $activerange[1], $user )
					)->text()
				);
			}
			if ( $achiev->isMultiple() ) {
				$footnotes[] = self::noteTooltip( 'multiple' );
			}
			if ( $achiev->isRemovable() ) {
				$footnotes[] = self::noteTooltip( 'removable' );
			}
			if ( $achiev->isAwardable() ) {
				$footnotes[] = self::noteTooltip( 'awardable' );
			}
			if ( $achiev->getConfig( 'reset', false ) ) {
				$footnotes[] = self::noteTooltip( 'reset-' . $achiev->getConfig( 'reset', '' ) );
			}
		}

		$footnotes[] = self::tooltip(
			'span',
			wfMessage( 'achiev-achievers-count-wrap' )->rawParams( implode( wfMessage( 'achiev-achievers-count-sep' )->text(), $achievers ) )->text(),
			wfMessage( 'achiev-achievers-count-desc' )->text()
		);

		if ( !$achiev->isStaged() ) {
			$score = $wgAchievementsScoring ? $achiev->getStageScore() : 0;
			if ( $score > 0 ) $footnotes[] = wfMessage( 'achiev-award-score' )->rawParams( $score )->text();
			if ( $admin || isset( $tss[0] ) ) {
				if ( isset( $tss[0] ) && is_array( $tss[0] ) && count( $tss[0] ) > 1 ) {
					$footnotes[] = self::tooltip (
						'span',
						wfMessage( 'achiev-award-date' )->rawParams(  )->text(),
						self::dateList( $wgLang, $user, $tss[0] )
					);
				} else {
					$footnotes[] = wfMessage( 'achiev-award-date' )->rawParams( self::dateList( $wgLang, $user, $tss[0] ) )->text();
				}
			} else {
				$footnotes[] = wfMessage( 'achiev-award-none' )->text();
			}
		}

		$block .= Html::rawElement( 'div', [ 'class' => 'achiev-note' ], implode( wfMessage( 'achiev-footnote-sep' )->text(), $footnotes ) );
		
		$block .= Html::closeElement( 'div' );

		if ( $stageBlocks !== '' ) $block .= Html::rawElement( 'div', [ 'class' => 'achiev-stage' ], $stageBlocks );

		$block .= Html::closeElement( 'div' );
		
		return $block;
	}

	static public function dateList ( $lang, $user, $ts ) {
		if ( is_array( $ts ) ) {
			foreach( $ts as &$t ) {
				$t = $lang->userTimeAndDate( $t, $user );
			}
			return implode('<br />', $ts);
		}
		return $lang->userTimeAndDate( $ts, $user );
	}

	static public function noteTooltip ( $name ) {
		return self::tooltip(
			'span',
			wfMessage( 'achiev-config-' . $name )->text(),
			wfMessage( 'achiev-config-' . $name . '-desc' )->text()
		);
	}

	static public function tooltip ( $tag, $html, $title, $class = '' ) {
		return Html::rawElement( $tag, [ 'class' => 'note-tooltip' . ( $class === '' ? '' : ' ' . $class ), 'title' => $title ], $html );
	}

	// 在用户设定页面生成可用称号列表和成就列表
	static public function onGetPreferences ( $user, &$preferences ) {
		if ( $user->isAnon() || $user->isBlocked() ) {
			return true;
		}

		$usertitle = $user->getOption( 'achievtitle', '' );
		// AchievementHandler::updateUserAchievs( $user );
		$allachievs = AchievementHandler::AchievementsFromAll();
		AchievementHandler::sortAchievements( $allachievs );
		$userachievs = AchievementHandler::getUserAchievs( $user );
		$counts = AchievementHandler::getUserCounts( $user );
		
		$table = '';
		$options = [];
		$options[wfMessage( 'achievtitle-none' )->text()] = '';
		foreach ( $allachievs as &$achiev ) {
			$id = $achiev->getID();
			if ( empty( $userachievs[$id] ) ) {
				if ( !$achiev->getConfig( 'hidden', false ) ) {
					$table .= self::buildAchievBlock( $achiev, [], $counts, $user );
				}
			} else {
				$tss = $userachievs[$id];
				foreach ( $tss as $stage => $ts ) {
					$message = $achiev->getAfterLinkMsg( $stage, false ) . '</a>';
					$options[$message] = $achiev->getStageName( $stage );
				}
				$table .= self::buildAchievBlock( $achiev, $tss, $counts, $user );
			}
		}
		
		$preferences['achievtitle'] = array(
			'type' => 'radio',
			'label-message' => 'pref-achievtitle',
			'section' => 'achievements/achievtitle',
			'options' => $options,
			'default' => ( array_search( $usertitle, $options ) !== false ? $usertitle : '' ),
			'help-message' => 'pref-achievtitle-help',
		);
		$preferences['achievlist'] = array(
			'type' => 'info',
			'label-message' => 'pref-achievlist',
			'default' => '<tr><td>'.$table.'</td></tr>',
			'section' => 'achievements/achievlist',
			'raw' => 1,
			'rawrow' => 1,
		);
		return true;
	}

	// 检查设定头衔
	static public function onUserSaveOptions ( User $user, &$options ) {
		if ( isset( $options['achievtitle'] ) ) {
			$options['achievtitle'] = trim( $options['achievtitle'] );
			if ( AchievementHandler::quickCheckUserAchiev( $user, $options['achievtitle'] ) ) {
				return true;
			}
			$options['achievtitle'] = '';
		}

		return true;
	}

	// 在页面顶部用户名加上头衔
	static public function onPersonalUrls ( &$personal_urls, $title, SkinTemplate $skin ) {
		if ( isset( $personal_urls['userpage'] ) ) {
			$text = AchievementHandler::getUserTitle( $skin->getUser(), true );
			if ( $text !== false ) {
				$personal_urls['userpage']['text'] .= $text;
			}
		}
		return true;
	}

	// 在用户连接上加上头衔及头像
	static public function onHtmlPageLinkRendererEnd ( $linkRenderer, $target, $isKnown, &$html, &$attribs, &$ret ) {
		static $ns = null;
		if ( is_null( $ns ) ) {
			$ns = [ NS_USER ];
			if ( defined( 'NS_USER_WIKI' ) ) $ns[] = NS_USER_WIKI;
			if ( defined( 'NS_USER_PROFILE' ) ) $ns[] = NS_USER_PROFILE;
		}
		
		if ( in_array( $target->getNamespace(), $ns ) ) {
			if ( (strpos( $attribs['href'], 'action=edit&redlink=1' ) !== false || strpos( $attribs['href'], 'action=' ) === false) && strpos( $attribs['href'], 'oldid=' ) === false && strpos( $attribs['href'], '#' ) === false ) {
				$user = User::newFromName( $target->getDBkey() );
				if ( $user instanceof User && !$user->isAnon() ) {
					if ( class_exists( 'wAvatar' ) ) {
						global $wgUploadPath;
						$id = $user->getId();
						$avatar = new wAvatar( $id, 'm' );
						$avatar = Html::rawElement(
							'img',
							[
								'class' => 'useravatar',
								'src' => $wgUploadPath . '/avatars/' . $avatar->getAvatarImage()
							]
						);
						$html = new HtmlArmor( $avatar . HtmlArmor::getHtml( $html ) );
					}

					$text = AchievementHandler::getUserTitle( $user, false );
					if ( $text !== false ) {
						$html = new HtmlArmor( HtmlArmor::getHtml( $html ) . '</a>' . $text );
					}
				}
			}
		}
		return true;
	}
	
	// 在Flow话题作者名称前加上头像
	static public function FlowAvatarHelper( $user ) {
		global $wgUploadPath;
		if ( class_exists( 'wAvatar' ) ) {
			global $wgUploadPath;
			$id = $user['id'];
			$avatar = new wAvatar( $id, 'm' );
			return [Html::rawElement(
				'img',
				[
					'class' => 'useravatar',
					'src' => $wgUploadPath . '/avatars/' . $avatar->getAvatarImage()
				]
			), 'raw'];
		}
		return '';
	}
	
	// 在Flow话题作者名称后加上头衔
	static public function FlowTitleHelper( $user ) {
		$user = User::newFromId( $user['id'] );
		if ( $user instanceof User && !$user->isAnon() ) {
			$text = AchievementHandler::getUserTitle( $user, false );
			if ( $text !== false ) return [$text, 'raw'];
		}
		return '';
	}

	// 在用户资料页加上成就信息
	static public function onUserProfileBeginLeft ( &$page ) {
		global $wgUserProfileDisplay;
		if ( $wgUserProfileDisplay['achiev'] == false ) {
			return true;
		}
		global $wgUser, $wgOut, $wgAchievementsScoring;

		$output = '';
		$po = '';
		$output .= '<div class="user-section-heading"><div class="user-section-title">' .
			wfMessage( 'user-achiev-info-title' )->escaped() .
			'</div><div class="user-section-actions"><div class="action-right">';
		if ( $wgUser->getName() == $page->user_name ) {
			$output .= '<a href="' . htmlspecialchars( \SpecialPage::getTitleFor('Preferences')->getLocalURL() . '#mw-prefsection-achievements' ) . '">' .
				wfMessage( 'prefs-achievements' )->escaped() . '</a>';
		}
		$usertitle = AchievementHandler::getUserTitle( $page->user, false );
		$po .= '<div><b>' . wfMessage( 'user-current-achiev-title' )->escaped() . '</b>' .
			($usertitle !== false ? $usertitle : wfMessage( 'achievtitle-none' )->text()) . '</a></div>';
		$po .= '<div><b>' . wfMessage( 'user-count-achiev-title' )->escaped() . '</b>' .
			(count( AchievementHandler::getUserAchievIDs( $page->user ) )) . '</div>';
		
		if ( $wgAchievementsScoring ) {
			$score = AchievementHandler::getUserScore( $page->user );
			$level = AchievementHandler::Score2Level( $score );
			
			$progPre = AchievementHandler::Level2Score( $level );
			$progBot = AchievementHandler::Level2Score( $level + 1 );
			$progTop = $score;
			$percent = round( max( 0, min( ($progTop - $progPre) / ($progBot - $progPre), 1 ) ) * 100, 0 );
			$progtext = wfMessage( 'achiev-progtext' )->rawParams( $progTop, $progBot, $percent )->text();
			
			$po .= '<div><b>' . wfMessage( 'user-achiev-level' )->escaped() . '</b>' .
				$level . '</div>';
			$po .= '<div><b>' . wfMessage( 'user-achiev-score' )->escaped() . '</b>' .
				$progtext . '</div>';
		}
		
		$output .= '</div><div class="visualClear"></div></div></div><div class="visualClear"></div>'.
		'<div class="profile-info-container">' . $po . '</div>';
		
		$wgOut->addHTML( $output );
		
		return true;
	}

	// Echo用
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories, &$icons ) {
		$notificationCategories['achiev'] = array(
			'priority' => 8,
			'tooltip' => 'echo-pref-tooltip-achiev',
		);

		$notifications['achiev-award'] = array(
			'category' => 'achiev',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => 'Achiev\\AchievPresentationModel',
			'notify-type-availability' => array(
				'email' => false,
			),
			'immediate' => true,
		);

		$notifications['achiev-remove'] = array(
			'category' => 'achiev',
			'group' => 'negative',
			'section' => 'message',
			'presentation-model' => 'Achiev\\AchievPresentationModel',
			'notify-type-availability' => array(
				'email' => false,
			),
			'immediate' => true,
		);

		return true;
	}

	// Echo用
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			case 'achiev-award':
			case 'achiev-remove':
				$users[] = $event->getAgent();
				break;
		}
		return true;
	}
}