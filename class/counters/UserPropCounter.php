<?php

namespace Achiev;

class UserPropCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			$prop = trim( $user->getOption( $this->config['prop'] ) );
			if ( $prop !== '' ) {
				if ( $this->config['value'] === '' || trim( $this->config['value'] ) === $prop ) {
					return 1;
				} else {
					return 0;
				}
			} else {
				return 0;
			}
		} else {
			return $this->config['init'];
		}
	}

	public function updateHook ( $opt, $user, $props ) { // UserSaveOptions
		if ( $this->isBlocked( $user ) ) return;
		$prop = trim( $user->getOption( $this->config['prop'] ) );
		if ( $prop !== '' ) {
			if ( $this->config['value'] === '' || trim( $this->config['value'] ) === $prop ) {
				$this->updateUserCountDB( $user, 1 );
			} else {
				$this->updateUserCountDB( $user, 0 );
			}
		} else {
			$this->updateUserCountDB( $user, 0 );
		}
		$this->updateAchievSafe( $user );
	}

	public function defaultConfig () {
		return [
			'prop' => 'nickname',
			'value' => '',
			'init' => 'current',
		];
	}
}