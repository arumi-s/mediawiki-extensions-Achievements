<?php

namespace Achiev;

class UserGroupCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['group'] ) {
			return in_array( $this->config['group'], $user->getGroups() ) ? 1 : 0;
		} else {
			if ( $this->config['init'] == 'current' ) {
				$dbw = wfGetDB( DB_MASTER );
				
				return (int)($dbw->selectField(
					'user_groups',
					'COUNT(*) AS rc',
					array( 'ug_user' => $user->getId() ),
					__METHOD__
				)) + $value;
			} else {
				return $this->config['init'] + $value;
			}
		}
	}

	public function updateHook ( $opt, $user, $group ) { // UserAddGroup UserRemoveGroup
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['group'] ) {
			if ( $group === $this->config['group'] ) {
				$this->updateUserCountDB( $user, $opt[1] == 'UserAddGroup' ? 1 : 0 );
			}
		} else {
			$this->addUserCountDB( $user, $opt[1] == 'UserAddGroup' ? 1 : -1 );
		}
		$this->updateAchievSafe( $user );
	}

	static protected function hookToUser ( $user ) {
		if ( $user instanceof User ) {
			return $user;
		} elseif ( is_string( $user ) ) {
			return \User::newFromName( $user );
		} else {
			return \User::newFromId( $user );
		}
	}

	public function defaultConfig () {
		return [
			'init' => 'current',
			'group' => false,
		];
	}
}