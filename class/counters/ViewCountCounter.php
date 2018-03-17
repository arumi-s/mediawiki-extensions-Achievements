<?php

namespace Achiev;

class ViewCountCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			return 0 + $value;
		} else {
			return $this->config['init'] + $value;
		}
	}

	public function updateHook ( $opt, \WikiPage $wikipage, \User $user ) { // PageViewUpdates
		if ( $this->isBlocked( $user ) || !$wikipage->exists() || AchievementHandler::quickCheckUserAchiev( $user, $this->id ) ) return;

		$cond = $this->buildCond();
		if ( empty( $cond ) ) {
			
		} else {
			$inpage = true;
			$inns = true;
			$incat = true;
			$title = $wikipage->getTitle();
			$dbr = wfGetDB( DB_SLAVE );
			if ( isset( $cond['page'] ) ) {
				if ( $title->inNamespace( $cond['ns'] ) && (is_array( $cond['page'] ) ? in_array( $title->getDBkey(), $cond['page'], true ) : $title->getDBkey() === $cond['page']) ) {
					$inpage = true;
				} else {
					if ( $cond['subpage'] && mb_substr( $title->getDBkey(), 0, mb_strlen( $cond['page'].'/' ) ) === $cond['page'].'/' ) {
						$inpage = true;
					} else {
						$inpage = false;
					}
				}
			} else {
				if ( isset( $cond['ns'] ) ) {
					$inns = $title->inNamespace( $cond['ns'] );
				}
				if ( isset( $cond['cat'] ) ) {
					$incat = (bool)$dbr->selectField(
						'categorylinks',
						'COUNT(1)',
						[ 'cl_from' => $title->getArticleID(), 'cl_to' => $cond['cat'] ]
					);
				}
			}
			if ( $inpage && $inns && $incat ) {
				
			} else {
				return;
			}
		}

		if ( $this->config['rate'] < 1 ) {
			$max = mt_getrandmax();
			if ( mt_rand( 0, $max ) > $max * $this->config['rate'] ) {
				return;
			}
			$n = 1;
		} else {
			$n = intval( $this->config['rate'] );
		}
		$this->addUserCountDB( $user, $n );
		$this->updateAchievSafe( $user );
	}

	protected function buildCond () {
		$cond = [];
		if ( isset( $this->config['page'] ) && $this->config['page'] !== '' ) {
			$cond['page'] = $this->config['page'];
			if ( isset( $this->config['subpage'] ) && $this->config['subpage'] ) {
				$cond['subpage'] = true;
			} else {
				$cond['subpage'] = false;
			}
			if ( isset( $this->config['ns'] ) && intval( $this->config['ns'] ) >= 0 ) {
				$cond['ns'] = intval( $this->config['ns'] );
			} else {
				$cond['ns'] = 0;
			}
		} else {
			if ( isset( $this->config['ns'] ) && intval( $this->config['ns'] ) >= 0 ) {
				$cond['ns'] = intval( $this->config['ns'] );
			}
			if ( isset( $this->config['cat'] ) && $this->config['cat'] !== '' ) {
				$cond['cat'] = $this->config['cat'];
			}
		}
		return $cond;
	}

	public function defaultConfig () {
		return [
			'init' => 0,
			'rate' => 1,
		];
	}
}