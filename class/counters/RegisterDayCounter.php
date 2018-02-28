<?php

namespace Achiev;

class RegisterDayCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			return $this->getDays( $user );
		} else {
			return $this->config['init'];
		}
	}

	public function updateHook ( $opt, $user ) { // Achievement::d
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			$this->updateUserCountDB( $user, $this->getDays( $user ) );
		} else {
			$this->addUserCountDB( $user, 1 );
		}
		$this->updateAchievSafe( $user );
	}

	public function getDays ( $user ) {
		return (int)((wfTimestamp( TS_UNIX ) - wfTimestamp( TS_UNIX, $user->getRegistration() )) / 86400);
	}

	public function defaultConfig () {
		return [
			'prop' => 'nickname',
			'value' => '',
			'init' => 'current',
		];
	}
}