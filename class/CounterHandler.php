<?php

namespace Achiev;

class CounterHandler {
	static protected $configs = [
		'static' => ['StaticCounter',[]],
		'editcount' => ['EditCountCounter',['ArticleEditUpdates']],
		'edittop' => ['EditTopCounter',['ArticleEditUpdates']],
		'friendcount' => ['FriendCountCounter',['NewFriendAccepted', 'RelationshipRemovedByUserID']],
		'friendtop' => ['FriendTopCounter',['NewFriendAccepted', 'RelationshipRemovedByUserID']],
		'foecount' => ['FoeCountCounter',['NewFoeAccepted', 'RelationshipRemovedByUserID']],
		'foetop' => ['FoeTopCounter',['NewFoeAccepted', 'RelationshipRemovedByUserID']],
		'frienduser' => ['FriendUserCounter',['NewFriendAccepted', 'RelationshipRemovedByUserID']],
		'watch' => ['WatchCounter',['WatchArticleComplete', 'UnwatchArticleComplete', 'WatchArticleClearComplete']],
		'usergroup' => ['UserGroupCounter',['UserAddGroup', 'UserRemoveGroup']],
		'userprop' => ['UserPropCounter',['UserSaveOptions']],
		'useravatar' => ['UserAvatarCounter',['NewAvatarUploaded']],
		'useremail' => ['UserEmailCounter',['ConfirmEmailComplete']],
		//'usersetting' => ['UserSettingCounter',['UserSaveSettings']],
		'registerday' => ['RegisterDayCounter',['Achievement::d']],
		'viewtop' => ['ViewTopCounter',['PageViewUpdates']],
		'viewcount' => ['ViewCountCounter',['PageViewUpdates']],
		'random' => ['RandomCounter',['PageViewUpdates']],
	];

	static public function configs () {
		return self::$configs;
	}

	static public function newFromName ( $name ) {
		$cn = self::getClassname( $name );
		if ( $cn ) {
			new $cn();
		} else {
			return null;
		}
	}

	static public function getClassname ( $name, $ns = false ) {
		return isset( self::$configs[$name][0] ) ? ($ns?__NAMESPACE__ . '\\':'').self::$configs[$name][0] : '';
	}

	static public function init () {
		global $wgAutoloadClasses, $wgHooks;

		foreach ( self::$configs as $name => &$config ) {
			$classname = __NAMESPACE__ . '\\' . $config[0];
			$wgAutoloadClasses[$classname] = __DIR__ . '/counters/' . $config[0] . '.php';
			
			foreach ( $config[1] as $hook ) {
				$wgHooks[$hook][] = ['Achiev\\AchievementHandler::handleHook', [$name, $hook]];
			}
		}
	}

}