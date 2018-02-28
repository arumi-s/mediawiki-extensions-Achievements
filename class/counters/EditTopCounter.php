<?php

namespace Achiev;

class EditTopCounter extends EditCountCounter  {

	public function isBlocked ( $user ) {
		return !($user instanceof \User) || $user->isAnon() || $user->isBlocked() || $user->isBot();
	}

	public function isStageReversed () {
		return true;
	}

	public function hasRealThreshold () {
		return true;
	}

	public function getRealThreshold ( $pos = 0 ) {
		return $this->getPositionCount( 1 );
	}

	public function isQualify ( $count, $threshold = 1 ) {
		return $count > 0 && $count === $this->getPositionCount( $threshold );
	}

	public function defaultConfig () {
		return [
			'init' => 0,
		];
	}
}