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
		
		$this->showForm( $inputstring );

		return;
	}
	
	protected function showForm( $inputstring ) {
		$formDescriptor = array(
			'token' => array(
				'label-message' => 'redeem-achiev-token',
				'class' => 'HTMLTextField',
				'default' => $inputstring,
                'help-message' => 'redeem-achiev-token-help',
			),
		);

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
				$result = Token::useToken( $user, $userToken );
				if ( is_array( $result ) ) {
					list ( $success, $achiev, $stage, $count ) = $result;
					if ( $success ) {
						if ( is_null( $count ) ) {
							$out->addHTML(
								$this->msg( 'notification-body-achiev-award' )->rawParams($achiev->getNameMsg( $stage ), $achiev->getDescMsg( $stage ))->parse()
							);
						} else {
							$out->addHTML(
								$this->msg( 'notification-body-achiev-addvalue' )->rawParams($achiev->getNameMsg(), $count)->parse()
							);
						}
					} else {
						return $this->msg( 'achiev-error-award-failed' )->parse();
					}
				} else {
					 return $this->msg( 'achiev-error-' . $result )->parse();
				}
			}
		}
		return false;
	}

}