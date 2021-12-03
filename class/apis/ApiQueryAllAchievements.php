<?php

namespace Achiev;

use \ApiBase;
use \ApiQueryBase;
use \ApiQuery;

class ApiQueryAllAchievements extends ApiQueryBase {
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'aa' );
	}

	public function execute() {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'manageachievements' ) ) {
			throw new \PermissionsError( 'manageachievements' );
		}

		$params = $this->extractRequestParams();
		
		if ( $params['staged'] ){
			$achiev = AchievementHandler::AchievementFromStagedID( $params['prefix'], $stage );
			if ( $achiev && $achiev->isStaged() ) {
				$result = $this->getResult();
				foreach ( $achiev->getConfig( 'threshold', [] ) as $row ) {
					if ( $row < $stage ) continue;
					$data = [
						'id' => $achiev->getStageName( $row )
					];

					$fit = $result->addValue( [ 'query', $this->getModuleName() ], null, $data );
					if ( !$fit ) {
						$this->setContinueEnumParameter( 'from', $data['id'] );
						break;
					}
				}

				$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'u' );
			}
			return;
		}

		$res = AchievementHandler::AchievementsFromAll();
		AchievementHandler::sortAchievements( $res );

		/*$prop = $params['prop'];
		if ( !is_null( $prop ) ) {
			$prop = array_flip( $prop );
			$fld_blockinfo = isset( $prop['blockinfo'] );
			$fld_editcount = isset( $prop['editcount'] );
			$fld_groups = isset( $prop['groups'] );
			$fld_rights = isset( $prop['rights'] );
			$fld_registration = isset( $prop['registration'] );
			$fld_implicitgroups = isset( $prop['implicitgroups'] );
			$fld_centralids = isset( $prop['centralids'] );
		} else {
			$fld_blockinfo = $fld_editcount = $fld_groups = $fld_registration =
				$fld_rights = $fld_implicitgroups = $fld_centralids = false;
		}*/

		if ( !is_null( $params['prefix'] ) ) {
			$prefix = strtolower($params['prefix']);
			$prefixlength = strlen($prefix);
			$res = array_filter( $res, function ( $ac ) use( $prefix, $prefixlength ) {
				return strtolower(substr($ac->getID(), 0, $prefixlength)) === $prefix;
			} );
		}

		if ( $params['active'] ) {
			$res = array_filter( $res, function ( $ac ) {
				return $ac->isActive();
			} );
		}

		if ( !is_null($params['from']) ) {
			$start = 0;
			foreach ( $res as $pos => $ac ) {
				if ($ac->getID() === $params['from']) {
					$start = $pos;
					break;
				}
			}
			$res = array_slice( $res, $start );
		}

		if ( !is_null($params['to']) ) {
			$end = 0;
			foreach ( $res as $pos => $ac ) {
				if ($ac->getID() === $params['to']) {
					$start = $pos;
					break;
				}
			}
			$res = array_slice( $res, 0, $end );
		}
		
		if (isset($res[$params['limit']])) {
			$this->setContinueEnumParameter( 'from', $res[$params['limit']]->getID() );
		}

		$res = array_slice( $res, 0, $params['limit'] );

		$result = $this->getResult();
		foreach ( $res as $row ) {
			$data = [
				'id' => $row->getID()
			];

			$fit = $result->addValue( [ 'query', $this->getModuleName() ], null, $data );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'from', $data['id'] );
				break;
			}
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'u' );
	}

	public function getCacheMode( $params ) {
		return 'anon-public-user-private';
	}

	public function getAllowedParams() {
		return [
			'from' => null,
			'to' => null,
			'prefix' => null,
			'staged' => [
				ApiBase::PARAM_DFLT => false,
			],
			/*'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'blockinfo',
					'groups',
					'implicitgroups',
					'rights',
					'editcount',
					'registration',
					'centralids',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],*/
			'limit' => [
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'active' => [
				ApiBase::PARAM_DFLT => false,
				ApiBase::PARAM_HELP_MSG => [
					'apihelp-query+allusers-param-activeusers',
					$this->getConfig()->get( 'ActiveUserDays' )
				],
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=allusers&aufrom=Y'
				=> 'apihelp-query+allusers-example-Y',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Allusers';
	}
}
