<?php

namespace Achiev;

class WatchCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			$cond = $this->buildCond();
			$dbr = wfGetDB( DB_SLAVE );
			if ( empty( $cond ) ) {
				$res = $dbr->select(
					['page', 'watchlist'],
					'COUNT(DISTINCT page_id)',
					[ 'page_is_redirect' => 0 ],
					__METHOD__,
					[],
					[
						'watchlist' => [
							'INNER JOIN',
							[ 'wl_namespace=page_namespace', 'wl_title=page_title', 'wl_user' => $user->getId() ]
						]
					]
				);
			} else {
				if ( isset( $cond['page'] ) ) {
					$res = $dbr->select(
						['page', 'watchlist'],
						'COUNT(DISTINCT page_id)',
						[
							$cond['subpage'] ? $dbr->makeList(
								[ 'page_title' => $cond['page'], 'page_title ' . $dbr->buildLike( $cond['page'] . '/', $dbr->anyString()) ],
								$dbr::LIST_OR
							) : $dbr->makeList(
								[ 'page_title' => $cond['page'] ],
								$dbr::LIST_AND
							),
							'page_namespace' => $cond['ns'],
							'page_is_redirect' => 0
						],
						__METHOD__,
						[],
						[
							'watchlist' => [
								'INNER JOIN',
								[ 'wl_namespace=page_namespace', 'wl_title=page_title', 'wl_user' => $user->getId() ]
							]
						]
					);
				} else {
					if ( isset( $cond['cat'] ) ) {
						$res = $dbr->select(
							['page', 'watchlist', 'categorylinks'],
							'COUNT(DISTINCT page_id)',
							isset( $cond['ns'] ) ? [ 'page_namespace' => $cond['ns'] ] : [],
							__METHOD__,
							[],
							[
								'watchlist' => [
									'INNER JOIN',
									[ 'wl_namespace=page_namespace', 'wl_title=page_title', 'wl_user' => $user->getId() ]
								],
								'categorylinks' => [
									'INNER JOIN',
									[ 'cl_from=page_id', 'cl_to' => $cond['cat'] ]
								]
							]
						);
					} elseif ( isset( $cond['ns'] ) ) {
						$res = $dbr->select(
							['page', 'watchlist'],
							'COUNT(DISTINCT page_id)',
							[ 'page_namespace' => $cond['ns'], 'page_is_redirect' => 0 ],
							__METHOD__,
							[],
							[
								'watchlist' => [
									'INNER JOIN',
									[ 'wl_namespace=page_namespace', 'wl_title=page_title', 'wl_user' => $user->getId() ]
								]
							]
						);
					}
				}
			}

			if ( $res === false || !$dbr->numRows( $res ) ) {
				$count = 0;
			}
			$row = $dbr->fetchRow( $res );

			if ( $row !== false ) {
				$count = (int)reset( $row );
			} else {
				$count = 0;
			}
			return $count;
		} else {
			return $this->config['init'];
		}
	}

	public function updateHook ( $opt, $user, $article = null ) { // WatchArticleComplete UnwatchArticleComplete WatchArticleClearComplete
		if ( $this->isBlocked( $user ) ) return;
		if ( $opt[1] == 'WatchArticleClearComplete' ) {
			$this->updateUserCountDB( $user, 0 );
			$this->updateAchievSafe( $user );
			return;
		}

		if ( !$article->exists() ) return;
		if ( $opt[1] == 'WatchArticleComplete' ) {
			$diff = 1;
		} else {
			$diff = -1;
		}
		
		$cond = $this->buildCond();
		if ( empty( $cond ) ) {
			$this->addUserCountDB( $user, $diff );
			$this->updateAchievSafe( $user );
		} else {
			$inpage = true;
			$inns = true;
			$incat = true;
			$title = $article->getTitle();
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
				$this->addUserCountDB( $user, $diff );
				$this->updateAchievSafe( $user );
			}
		}
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
			'init' => 'current',
		];
	}
}