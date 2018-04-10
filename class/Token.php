<?php

namespace Achiev;

class Token {

	private $secret = '';
	private $achiev = '';
	private $new = false;

	/**
	 * @param string $secret Token secret
	 * @param Achievement $achiev Achievement
	 * @param bool $new Whether the secret was newly-created
	 */
	public function __construct( $secret, $new = false ) {
		$this->secret = $new ? $secret : self::trimToken( $secret );
		$this->new = $new;
	}

	static private function trimToken ( $string ) {
		return strtolower( preg_replace( '/[^0-9a-fA-F]/', '', (string)$string ) );
	}

	private function getTokenMemcKey () {
		return self::getMemcKey( hash_hmac( 'md5', $this->secret, false ) );
	}

	static public function getMemcKey ( $t ) {
		return wfMemcKey( 'achiev', 't', $t );
	}

	public static function getToken( $user, $achiev, $stage = 0, $addvalue = null, $target = null, $life = 0 ) {
		global $wgAchievementsTokenLength;
		if ( $wgAchievementsTokenLength <= 0 ) return false;
		if ( $user->isAnon() || $user->isBlocked() || !$user->isAllowed( 'manageachievements' ) ) return false;
		if ( !$achiev->isAwardable() || !$achiev->isActive() ) return false;
		
		$cache = \ObjectCache::getMainStashInstance();
		$secret = \MWCryptRand::generateHex( $wgAchievementsTokenLength );
		$token = new Self( $secret, true );
		
		$key = $token->getTokenMemcKey();

		$data = [
			'user' => $user->getId(),
			'achiev' => $achiev->getStageName( $stage ),
		];
		if ( !is_null( $addvalue ) ) {
			$data['count'] = $addvalue;
		}
		if ( $target instanceof \User && !$target->isAnon() ) {
			$data['target'] = $target->getId();
		}
		$cache->set( $key, $data, $life );

		return $token;
	}

	/**
	 * Get the string representation of the token
	 * @return string
	 */
	public function toString () {
		return trim( chunk_split( strtoupper( $this->secret ), 5, '-' ), '-' );
	}

	public function toLink () {
		$title = \Title::newFromText( 'RA', NS_SPECIAL );
		return $title->getPrefixedText() . '/' . $this->toString();
	}

	public function toUrl () {
		$title = \Title::newFromText( 'RA', NS_SPECIAL );
		return $title->getFullURL() . '/' . $this->toString();
	}

	public function __toString () {
		return $this->toString();
	}

	public static function useToken( \User $user, $string ) {
		if ( $string instanceof Token ) {
			$userToken = $string;
		} else {
			$userToken = new Self( $string );
		}
		if ( $userToken->toString() === '' ) throw new AchievError( 'empty-token' );

		$cache = \ObjectCache::getMainStashInstance();
		$key = $userToken->getTokenMemcKey();
		
		$data = $cache->get( $key );

		if ( $data === false ) {
			$cache->delete( $key );
			throw new AchievError( 'invalid-token' );
		}
		if ( empty( $data['user'] ) || !(($source = \User::newFromId( $data['user'] )) instanceof \User) ) {
			$cache->delete( $key );
			throw new AchievError( 'invalid-source' );
		}
		if ( $source->isAnon() || $source->isBlocked() || !$source->isAllowed( 'manageachievements' ) ) {
			$cache->delete( $key );
			throw new AchievError( 'blocked-source' );
		}
		if ( !empty( $data['target'] ) && $user->getId() !== $data['target'] ) {
			throw new AchievError( 'wrong-user' );
		}
		
		$stage = 0;
		$achiev = AchievementHandler::AchievementFromStagedID( isset( $data['achiev'] ) ? $data['achiev'] : '', $stage );
		if ( !$achiev ) {
			$cache->delete( $key );
			throw new AchievError( 'achiev-notexists' );
		}
		$count = null;
		if ( isset( $data['count'] ) ) {
			$count = $data['count'];
			$achiev->addStaticCountTo( $user, $count );
		} else {
			$achiev->awardStaticTo( $user, $stage );
		}
		$cache->delete( $key );

		return [$achiev, $stage, $count];
	}

	/**
	 * Indicate whether this token was just created
	 * @return bool
	 */
	public function wasNew() {
		return $this->new;
	}

}
