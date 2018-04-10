<?php

namespace Achiev;

class AchievError extends \Exception {
	/**
	 * @param $msg string
	 * @param $parameter string
	 */
	public function __construct ( $msg, $parameter = '' ) {
		// Available $msg:
		// 'invalid-stage', 'empty-token', 'invalid-token',
		// 'invalid-source', 'blocked-source', 'blocked-user', 'wrong-user',
		// 'achiev-notexists', 'achiev-notawardable',
		// 'achiev-conflict', 'achiev-already', 'award-failed'
		$this->message = wfMessage( 'achiev-error-' . $msg, $parameter )->parse();
	}

}