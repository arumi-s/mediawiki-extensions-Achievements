<?php

namespace Achiev;

class UserAvatarCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			return $user->getEmailAuthenticationTimestamp() ? 1 : 0;
		} else {
			return $this->config['init'];
		}
	}

	public function updateHook ( $opt, $user ) { // ConfirmEmailComplete
		if ( $this->isBlocked( $user ) ) return;
		$this->updateUserCountDB( $user, 1 );
		$this->updateAchievSafe( $user );
	}

	public function defaultConfig () {
		return [
			'init' => 'current',
		];
	}
}