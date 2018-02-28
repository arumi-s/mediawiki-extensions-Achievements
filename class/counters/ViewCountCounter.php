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

	public function defaultConfig () {
		return [
			'init' => 0,
			'rate' => 1,
		];
	}
}