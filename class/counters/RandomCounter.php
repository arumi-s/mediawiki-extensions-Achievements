<?php

namespace Achiev;

class RandomCounter extends Counter  {

	protected static function cache() {
		static $c = null;

		// Use main stash for persistent storage, and
		// wrap it with CachedBagOStuff for an in-process
		// cache.
		if ( is_null( $c ) ) {
			$c = new \CachedBagOStuff(
				\ObjectCache::getMainStashInstance()
			);
		}

		return $c;
	}

	public function initUserCount ( \User $user, $value = 0 ) {
		if ( $this->isBlocked( $user ) ) return;
		if ( $this->config['init'] === 'current' ) {
			return 0 + $value;
		} else {
			return $this->config['init'] + $value;
		}
	}

	protected function getNextTimestampMemcKey () {
		return wfMemcKey( 'achiev', 'rtime', $this->id );
	}

	protected function genNextTimestamp () {
		$min = strtotime( '+1 minutes' );
		$max = strtotime( '+' . $this->config['rate'] );

		// Generate random number using above bounds
		$val = rand( $min, $max );
		
		return $val;
	}

	public function getNextTimestamp () {
		$cache = self::cache();
		$key = $this->getNextTimestampMemcKey();
		$data = $cache->get( $key );

		if ( $data === false ) {
			$data = $this->genNextTimestamp();
			$cache->set( $key, $data, 0 );
		}
		return (int)$data;
	}

	public function clearNextTimestamp () {	
		$cache = self::cache();	
		$key = $this->getNextTimestampMemcKey();
		$data = $this->genNextTimestamp();
		$cache->set( $key, $data, 0 );
	}

	public function updateHook ( $opt, \WikiPage $wikipage, \User $user ) { // PageViewUpdates
		if ( $this->isBlocked( $user ) || !$wikipage->exists() || AchievementHandler::quickCheckUserAchiev( $user, $this->id ) ) return;
		if ( strtotime( 'now' ) > $this->getNextTimestamp() ) {
			$this->clearNextTimestamp();
			$this->addUserCountDB( $user, 1 );
			$this->updateAchievSafe( $user );
		}
	}

	public function defaultConfig () {
		return [
			'init' => 0,
			'rate' => '1 day',
		];
	}
}