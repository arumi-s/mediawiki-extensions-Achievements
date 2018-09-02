<?php

namespace Achiev;

class AchievError extends \Exception {
	protected $type = '';
	/**
	 * @param $type string
	 * @param $parameter string
	 */
	public function __construct ( $type, $parameter = '' ) {
		// Available $type:
		// 'invalid-stage', 'empty-token', 'invalid-token',
		// 'invalid-source', 'blocked-source', 'blocked-user', 'wrong-user',
		// 'achiev-notexists', 'achiev-notactive', 'achiev-notawardable',
		// 'achiev-conflict', 'achiev-already', 'award-failed'
		$this->type = $type;
		$this->message = wfMessage( 'achiev-error-' . $type, $parameter )->parse();
	}

	public function getType () {
		return $this->type;
	}

}