<?php
namespace Achiev;

class AchievPresentationModel extends \EchoEventPresentationModel {
	protected $achiev = false;
	protected $stage = 0;

	protected function load () {
		if ( $this->achiev === false ) {
			try {
				$id = $this->event->getExtraParam( 'achievid' );
				$this->achiev = AchievementHandler::AchievementFromStagedID ( $id, $this->stage );
			} catch ( \Exception $e ) {
				$this->achiev = false;
			}
		}
		return $this->achiev !== false;
	}

	public function getIconType() {
		switch ( $this->type ) {
			case 'achiev-award':
				return 'site';
			case 'achiev-remove':
				return 'site';
		}
		return 'site';
	}

	public function getBodyMessage() {
		if ( $this->load() ) {
			$msg = $this->msg( 'notification-body-' . $this->type );
			$msg->plaintextParams( strip_tags($this->achiev->getNameMsg( $this->stage )) );
			$msg->plaintextParams( strip_tags($this->achiev->getDescMsg( $this->stage )) );
			return $msg;
		} else {
			return $this->msg( 'notification-body-achiev-invalid' );
		}
	}

	public function getPrimaryLink() {
		return [
			'url' => \SpecialPage::getTitleFor('Preferences')->getLocalURL() . '#mw-prefsection-achievements',
			'label' => $this->msg( 'notification-body-achiev-link' )->text(),
		];
	}

}
