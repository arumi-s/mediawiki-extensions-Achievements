<?php

namespace Achiev;

class FriendUserCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			$dbr = wfGetDB( DB_MASTER );
			
			return ($dbr->selectField(
				'user_relationship',
				'r_id',
				[ 'r_user_id' => $user->getId(), 'r_user_name_relation' => $this->config['name'], 'r_type' => 1 ],
				__METHOD__
			)) !== false ? 1 : 0;
		} else {
			return $this->config['init'];
		}
	}

	public function updateHook ( $opt, $user1, $user2, $type = null ) { // NewFriendAccepted, RelationshipRemovedByUserID
		if ( $opt[1] == 'NewFriendAccepted' ) {
			$diff = 1;
		} else {
			if ( $type != 1 ) return;
			$diff = 0;
		}
		$user1 = self::hookToUser( $user1 );
		$user2 = self::hookToUser( $user2 );
		if ( $user1 instanceof \User && $user2 instanceof \User ) {
			if ( !$this->isBlocked( $user1 ) && $user2->getName() === $this->config['name'] ) {
				$this->updateUserCountDB( $user1, $diff );
				$this->updateAchievSafe( $user1 );
			}
			if ( !$this->isBlocked( $user2 ) && $user1->getName() === $this->config['name'] ) {
				$this->updateUserCountDB( $user2, $diff );
				$this->updateAchievSafe( $user2 );
			}
		}
	}

	static protected function hookToUser ( $user ) {
		if ( $user instanceof User ) {
			return $user;
		} else {
			return \User::newFromId( $user );
		}
	}

	public function defaultConfig () {
		return [
			'init' => 'current',
			'name' => '',
		];
	}
}