<?php

namespace Achiev;

class FoeCountCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			$dbr = wfGetDB( DB_MASTER );
			
			return (int)($dbr->selectField(
				'user_relationship',
				'COUNT(*) AS rc',
				array( 'r_user_id' => $user->getId(), 'r_type' => 2 ),
				__METHOD__
			));
		} else {
			return $this->config['init'] + $value;
		}
	}

	public function updateHook ( $opt, $user1, $user2, $type = null ) { // NewFoeAccepted, RelationshipRemovedByUserID
		if ( $opt[1] == 'NewFoeAccepted' ) {
			$diff = 1;
		} else {
			if ( $type != 2 ) return;
			$diff = -1;
		}
		$user1 = self::hookToUser( $user1 );
		$user2 = self::hookToUser( $user2 );
		if ( !$this->isBlocked( $user1 ) ) $this->addUserCountDB( $user1, $diff );
		if ( !$this->isBlocked( $user2 ) ) $this->addUserCountDB( $user2, $diff );
		if ( !$this->isBlocked( $user1 ) ) $this->updateAchievSafe( $user1 );
		if ( !$this->isBlocked( $user2 ) ) $this->updateAchievSafe( $user2 );
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
			'init' => 0,
		];
	}
}