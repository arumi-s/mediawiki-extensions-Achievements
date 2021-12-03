<?php

namespace Achiev;

class EditCountCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			$cond = $this->buildCond();
			if ( empty( $cond ) ) {
				return $user->getEditCount() + $value;
			}
			$dbr = wfGetDB( DB_SLAVE );
			if ( isset( $cond['page'] ) ) {
				$res = $dbr->select(
					['page', 'revision_actor_temp'],
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
						'revision_actor_temp' => [
							'INNER JOIN',
							[ 'revactor_page=page_id', 'revactor_actor' => $user->getActorId() ]
						]
					]
				);
			} else {
				if ( isset( $cond['cat'] ) ) {
					$res = $dbr->select(
						['page', 'revision_actor_temp', 'categorylinks'],
						'COUNT(DISTINCT page_id)',
						isset( $cond['ns'] ) ? [ 'page_namespace' => $cond['ns'] ] : [],
						__METHOD__,
						[],
						[
							'revision_actor_temp' => [
								'INNER JOIN',
								[ 'revactor_page=page_id', 'revactor_actor' => $user->getActorId() ]
							],
							'categorylinks' => [
								'INNER JOIN',
								[ 'cl_from=page_id', 'cl_to' => $cond['cat'] ]
							]
						]
					);
				} elseif ( isset( $cond['ns'] ) ) {
					$res = $dbr->select(
						['page', 'revision_actor_temp'],
						'COUNT(DISTINCT page_id)',
						[ 'page_namespace' => $cond['ns'], 'page_is_redirect' => 0 ],
						__METHOD__,
						[],
						[
							'revision_actor_temp' => [
								'INNER JOIN',
								[ 'revactor_page=page_id', 'revactor_actor' => $user->getActorId() ]
							]
						]
					);
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
			return $count + $value;
		} else {
			return $this->config['init'] + $value;
		}
	}

	public function isBlocked ( $user ) {
		return !($user instanceof \User) || $user->isAnon() || $user->isBlocked() || $user->isBot();
	}

	public function updateHook ( $opt, $article, $editInfo, $changed ) { // ArticleEditUpdates
		if ( !$changed ) return;
		$user = \User::newFromId( $article->getUser() );
		if ( $this->isBlocked( $user ) ) return;
		$title = $article->getTitle();
		if ( !$title->exists() ) return;
		$cond = $this->buildCond();
		if ( empty( $cond ) ) {
			$this->addUserCountDB( $user, 1 );
			$this->updateAchievSafe( $user );
		} else {
			$inpage = true;
			$inns = true;
			$incat = true;
			$dbr = wfGetDB( DB_SLAVE );
			if ( isset( $cond['page'] ) ) {
				if ( $title->inNamespace( $cond['ns'] ) && $title->getDBkey() === $cond['page'] ) {
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
					$cats = $editInfo->output->mCategories;
					$incat = !empty( $cats ) && isset( $cats[$cond['cat']] );
				}
			}
			if ( $inpage && $inns && $incat ) {
				$prev = (int)$dbr->selectField(
					'revision_actor_temp',
					'COUNT(1)',
					[
						'revactor_page' => $title->getArticleID(),
						'revactor_actor' => $user->getActorId(),
						'revactor_rev <> ' . (int)($editInfo->output->getCacheRevisionId())
					]
				);
				if ( $prev === 0 ) {
					$this->addUserCountDB( $user, 1 );
					$this->updateAchievSafe( $user );
				}
			}
		}
	}

	public function updateHook2 ( $opt, $wikiPage, \Revision $rev, $baseID, \User $user ) { // NewRevisionFromEditComplete
		if ( $this->isBlocked( $user ) ) return;
		$cond = $this->buildCond();
		if ( empty( $cond ) ) {
			$this->addUserCountDB( $user, 1 );
			$this->updateAchievSafe( $user );
		} else {
			$inpage = true;
			$inns = true;
			$incat = true;
			$title = $wikiPage->getTitle();
			if ( $title->exists() ) {
				$dbr = wfGetDB( DB_SLAVE );
				if ( isset( $cond['page'] ) ) {
					if ( $title->inNamespace( $cond['ns'] ) && $title->getDBkey() === $cond['page'] ) {
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
					$prev = (int)$dbr->selectField(
						['revision_actor_temp', 'revision'],
						'COUNT(1)',
						[ 'revactor_page' => $title->getArticleID(), 'revactor_actor' => $user->getActorId() ],
						__METHOD__,
						[],
						[
							'revision' => [
								'INNER JOIN',
								[ 'revactor_page=rev_id', 'rev_parent_id > 0' ]
							]
						]
					);
					if ( $prev <= 1 ) {
						$this->addUserCountDB( $user, 1 );
						$this->updateAchievSafe( $user );
					}
				}
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