<?php

namespace Achiev;

abstract class Counter {
	protected $achiev = null;
	protected $id = null;
	protected $config = null;
	protected $positioncache = [];

	public function __construct ( Achievement $achiev ) {
		$this->achiev = $achiev;
		$this->id = $this->achiev->getID();
		$this->config = array_merge( $this->defaultConfig(), $this->achiev->getCounterConfig() );
	}

	public function getID () {
		return $this->id;
	}

	public function getConfig ( $index = null, $default = null ) {
		return is_null( $index ) ? $this->config : ( isset( $this->config[$index] ) ? $this->config[$index] : $default );
	}

	public function isActive () {
		return $this->achiev->isActive();
	}

	public function isStageReversed () {
		return false;
	}

	public function hasRealThreshold () {
		return false;
	}

	public function getRealThreshold ( $index = 0 ) {
		return null;
	}

	public function &getAchiev () {
		return $this->achiev;
	}

	public function updateAchiev ( \User $user, $clearCache = true ) {
		return $this->achiev->updateAchiev( $user, $clearCache );
	}

	public function updateAchievSafe ( \User $user, $clearCache = true ) {
		if ( $this->achiev->getReset() == false ) {
			return $this->achiev->updateAchiev( $user, $clearCache );
		}
		return false;
	}

	public function resetCount ( \User $user, $clearCache = true ) {
		if ( !$this->isActive() ) return;
		$value = $this->initUserCount( $user, 0 );
		if ( !is_null( $value ) ) {
			$this->updateUserCountDB( $user, $value );
		}
		$this->updateAchiev( $user, $clearCache );
	}

	public function initUserCount ( \User $user, $value = 0 ) {
		return intval( $this->config['init'] ) + $value;
	}

	public function isBlocked ( $user ) {
		return !($user instanceof \User) || $user->isAnon() || $user->isBlocked();
	}

	public function isQualify ( $count, $threshold = 1 ) {
		return $count >= $threshold;
	}

	protected function initUserCountDB ( \User $user, $value = 0 ) {
		if ( is_null( $this->id ) ) return false;
		$value = $this->initUserCount( $user, intval( $value ) );
		if ( is_null( $value ) ) return false;
		$value = max(intval( $value ), 0);
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'achievements',
			[ 'ac_user' => $user->getId(), 'ac_id' => $this->id, 'ac_count' => $value ]
		);
		return true;
	}

	protected function updateUserCountDB ( \User $user, $value = 0 ) {
		if ( is_null( $this->id ) ) return false;
		$value = max(intval( $value ), 0);
		$dbw = wfGetDB( DB_MASTER );

		if ( $value == 0 ) {
			$dbw->update(
				'achievements',
				[ 'ac_count' => $value ],
				[ 'ac_user' => $user->getId(), 'ac_id' => $this->id ],
				__METHOD__,
				[ 'LIMIT' => 1 ]
			);
		} else {
			$dbw->upsert(
				'achievements',
				[ 'ac_user' => $user->getId(), 'ac_id' => $this->id, 'ac_count' => $value ],
				[ 'ac_user', 'ac_id' ],
				[ 'ac_count' => $value ],
				__METHOD__
			);
		}
		return true;
	}

	protected function addUserCountDB ( \User $user, $value = 0 ) {
		if ( is_null( $this->id ) ) return false;
		$value = intval( $value );
		if ( $value == 0 ) return true;
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'achievements',
			[ 'ac_count=ac_count' . ($value>0?'+'.abs($value):'-'.abs($value)) ],
			[ 'ac_user' => $user->getId(), 'ac_id' => $this->id ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		if ( $dbw->affectedRows() == 0 ) {
			$this->initUserCountDB( $user, $value );
		}
		return true;
	}

	public function getPositionCount ( $pos = 1 ) {
		if ( !isset( $this->positioncache[$pos] ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			
			$this->positioncache[$pos] = (int)($dbr->selectField(
				'achievements',
				'ac_count',
				[ 'ac_id' => $this->id ],
				__METHOD__,
				[ 'ORDER BY' => 'ac_count DESC', 'OFFSET' => max( $pos - 1, 0 ) ]
			));
		}
		return $this->positioncache[$pos];
	}

	public function defaultConfig () {
		return [
			'init' => 0
		];
	}
	
}