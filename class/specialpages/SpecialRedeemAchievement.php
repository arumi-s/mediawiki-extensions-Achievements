<?php

namespace Achiev;

class SpecialRedeemAchievement extends \SpecialPage {

	function __construct() {
		parent::__construct( 'RedeemAchievement' );
	}

	public function doesWrites() {
		return true;
	}

	function getGroupName() {
		return 'users';
	}

	private function log ( $text = '' ) {
		$hand = fopen( __DIR__ . '/log/' . date('Y-m') . '.log', 'a+' );
		if ( !$hand ) return false;
		if ( is_array( $text ) ) $text = implode( "\t", $text );
		fwrite( $hand, date('Y-m-d H:i:s') . "\t" . $text . "\n" );
		fclose( $hand );
		return true;
	}

	function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$lang = $this->getLanguage();

		// Set the page title, robot policies, etc.
		$this->setHeaders();

		$this->requireLogin( 'achiev-loginrequired' );

		if ( $user->isBlocked() ) {
			throw new \UserBlockedError( $user->getBlock() );
		}
		
		$this->checkReadOnly();
		if ( $par !== null ) {
			$tempToken = new Token( $par );
			$inputstring = $tempToken->toString();
		} else {
			$inputstring = '';
		}
		
		$this->showForm( $inputstring, $user->isAllowed( 'manageachievements' ) );

		return;
	}
	
	protected function showForm( $inputstring, $admin = false ) {
		global $wgAchievementsTokenLength;
		$formDescriptor = array(
			'token' => array(
				'label-message' => 'redeem-achiev-token',
				'class' => 'HTMLTextField',
				'default' => $inputstring,
                'help' => $this->msg('redeem-achiev-token-help' )->rawParams(
					Token::sampleToken('X'),
					$wgAchievementsTokenLength
				)->parse(),
			),
		);
		if ( $admin ) {
			$formDescriptor['user'] = array(
				'label-message' => 'redeem-achiev-user',
				'class' => 'HTMLTextField',
				'default' => '',
                'help-message' => 'redeem-achiev-user-help',
			);
			$formDescriptor['userlist'] = array(
				'label-message' => 'redeem-achiev-userlist',
				'class' => 'HTMLTextareaField',
				'rows' => '4',
				'cols' => '20',
				'default' => '',
                'help-message' => 'redeem-achiev-userlist-help',
			);
		}
		$htmlForm = new \HTMLForm( $formDescriptor, $this->getContext(), 'redeem-achiev' );

		$htmlForm->setTitle( $this->getTitle() );
		$htmlForm->setWrapperLegend( $this->msg('redeem-achiev-token-section' )->parse() );
		$htmlForm->setMethod( 'post' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );  

		$htmlForm->show();
	}

	public function processInput( $data ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $request->wasPosted() ) {
			$userToken = $data['token'];
			if ( $userToken ) {
				$userlist = null;
				if ( $user->isAllowed( 'manageachievements' ) ) {
					if ( $data['userlist'] !== '' ) {
						$userlist = explode( "\n", $data['userlist'] );
					} elseif ( $data['user'] !== '' ) {
						$user = \User::newFromName( $data['user'] );
					}
				}
				if ( is_array( $userlist ) ) {
					$ret = '';
					foreach ( $userlist as $username ) {
						$username = trim( $username );
						if ( $username === '' ) continue;
						$luser = \User::newFromName( $username );
						$prefix = $username . ': ';
						try {
							$result = Token::useToken( $luser, $userToken );
						} catch ( AchievError $e ) {
							$out->addHTML( $prefix . $e->getMessage() . '<br />' );
							continue;
						}
						if ( is_array( $result ) ) {
							list( $achiev, $stage, $count, $key ) = $result;
							if ( is_null( $count ) ) {
								$out->addHTML(
									$prefix . $this->msg( 'notification-body-achiev-award' )->rawParams($achiev->getNameMsg( $stage ), $achiev->getDescMsg( $stage ))->parse()
									. '<br />'
								);
							} else {
								$out->addHTML(
									$prefix . $this->msg( 'notification-body-achiev-addvalue' )->rawParams($achiev->getNameMsg(), $count)->parse()
									. '<br />'
								);
							}
						} else {
							 $e = new AchievError( 'award-failed' );
							 $out->addHTML( $prefix . $e->getMessage() . '<br />' );
							 continue;
						}
					}
				} else {
					$prefix = $user instanceof \User ? $user->getName() : '';
					try {
						$result = Token::useToken( $user, $userToken );
					} catch ( AchievError $e ) {
						$this->log([$prefix, Token::trimToken($userToken), $e->getType()]);
						return $prefix .  ': ' . $e->getMessage();
					}
					if ( is_array( $result ) ) {
						list( $achiev, $stage, $count, $key ) = $result;
						$this->log([$prefix, $key, 'award']);
						if ( is_null( $count ) ) {
							$out->addHTML(
								$prefix .  ': ' . $this->msg( 'notification-body-achiev-award' )->rawParams($achiev->getNameMsg( $stage ), $achiev->getDescMsg( $stage ))->parse()
							);
						} else {
							$out->addHTML(
								$prefix .  ': ' . $this->msg( 'notification-body-achiev-addvalue' )->rawParams($achiev->getNameMsg(), $count)->parse()
							);
						}
					} else {
						$e = new AchievError( 'award-failed' );
						$this->log([$prefix, Token::trimToken($userToken), $e->getType()]);
						return $prefix .  ': ' . $e->getMessage();
					}
				}
			}
		}
		return false;
	}

}