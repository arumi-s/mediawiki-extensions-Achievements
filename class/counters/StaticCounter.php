<?php

namespace Achiev;

class StaticCounter extends Counter  {

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		return $this->config['init'];
	}

	public function updateHook ( $opt, $user, &$count = null ) {
		if ( $this->isBlocked( $user ) ) return;
		$count = is_null( $count ) ? 1 : intval( $count );
		$this->addUserCountDB( $user, $count );
		$this->updateAchievSafe( $user );
	}

	public function defaultConfig () {
		return [
			'init' => 0,
		];
	}
}