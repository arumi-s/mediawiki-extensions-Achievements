<?php

namespace Achiev;

use \ApiBase;
use \PermissionsError;
use \User;
use \Title;
use \MediaWiki\Block\AbstractBlock;
use \MediaWiki\MediaWikiServices;
use \ApiQuery;
use \ApiQueryBase;
use \wAvatar;

class ApiQueryAchievementInfo extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ai' );
	}
	
	public function execute() {
		$this->params = $this->extractRequestParams();
		$result = $this->getResult();

		if ( !is_null( $this->params['prop'] ) ) {
			$this->prop = array_flip( $this->params['prop'] );
		}

		$r = $this->getCurrentUserInfo();
		$result->addValue( 'query', $this->getModuleName(), $r );
	}

	protected function getCurrentUserInfo() {
		global $wgAchievementsScoring;
		
		$user = $this->getUser();
		$vals = [];
		$vals['id'] = (int)$user->getId();
		$vals['name'] = $user->getName();

		if ( $user->isAnon() ) {
			$vals['anon'] = true;
		}

		if ( isset( $this->prop['avatar'] ) && class_exists( 'wAvatar' ) ) {
			global $wgUploadPath;
			$id = $user->getId();
			$avatar = new wAvatar( $id, 'l' );
			$vals['avatar'] = $wgUploadPath . '/avatars/' . $avatar->getAvatarImage();
		}

		if ( isset( $this->prop['titlename'] ) || isset( $this->prop['titledesc'] ) || isset( $this->prop['titleimage'] ) ) {
			list( $achiev, $count ) = AchievementHandler::getUserTitleAchievement( $user );

			if ( isset( $this->prop['titlename'] ) ) {
				$vals['titlename'] = $achiev instanceof Achievement ? $achiev->getNameMsg( $count ) : '';
			}

			if ( isset( $this->prop['titledesc'] ) ) {
				$vals['titledesc'] = $achiev instanceof Achievement ? $achiev->getDescMsg( $count ): '';
			}
			
			if ( isset( $this->prop['titleimage'] ) ) {
				if ( $achiev instanceof Achievement ) {
					$image = '';
					if ( !is_null( $achiev->getConfig( 'image' ) ) ) {
						$image = $achiev->getConfig( 'image' );
					} else {
						if ( $achiev->isStaged() ) {
							global $wgAchievementsIconStaged;
							$image = $wgAchievementsIconStaged;
						} else {
							global $wgAchievementsIconNormal;
							$image = $wgAchievementsIconNormal;
						}
					}
					$vals['titleimage'] = $image;
				} else {
					$vals['titleimage'] = '';
				}
			}
		}
		
		if ( isset( $this->prop['achievementcount'] ) ){
			$vals['achievementcount'] = count( AchievementHandler::getUserAchievIDs( $user ) );
		}
		
		if ( $wgAchievementsScoring && isset( $this->prop['score'] ) || isset( $this->prop['level'] ) ) {
			$score = AchievementHandler::getUserScore( $user );
			
			if ( isset( $this->prop['score'] ) ) {
				$vals['score'] = $score;
			}
			
			if ( isset( $this->prop['level'] ) ) {
				$vals['level'] = AchievementHandler::Score2Level( $score );
			}
		}

		return $vals;
	}

	protected function getRateLimits() {
		$retval = [
			ApiResult::META_TYPE => 'assoc',
		];

		$user = $this->getUser();
		if ( !$user->isPingLimitable() ) {
			return $retval; // No limits
		}

		// Find out which categories we belong to
		$categories = [];
		if ( $user->isAnon() ) {
			$categories[] = 'anon';
		} else {
			$categories[] = 'user';
		}
		if ( $user->isNewbie() ) {
			$categories[] = 'ip';
			$categories[] = 'subnet';
			if ( !$user->isAnon() ) {
				$categories[] = 'newbie';
			}
		}
		$categories = array_merge( $categories, $user->getGroups() );

		// Now get the actual limits
		foreach ( $this->getConfig()->get( 'RateLimits' ) as $action => $limits ) {
			foreach ( $categories as $cat ) {
				if ( isset( $limits[$cat] ) && !is_null( $limits[$cat] ) ) {
					$retval[$action][$cat]['hits'] = (int)$limits[$cat][0];
					$retval[$action][$cat]['seconds'] = (int)$limits[$cat][1];
				}
			}
		}

		return $retval;
	}


	public function getAllowedParams() {
		global $wgAchievementsScoring;
		
		return [
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => array_merge( [
					'avatar', 'titlename', 'titledesc', 'titleimage', 'achievementcount',
				], ( $wgAchievementsScoring ? [ 'score', 'level' ] : [] ) ),
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&meta=achievementinfo&aiprop=titlename|titledesc'
				=> 'apihelp-query+achievementinfo-example-simple',
			'action=query&meta=achievementinfo&aiprop=avatar|score|level'
				=> 'apihelp-query+achievementinfo-example-data',
		];
	}

}