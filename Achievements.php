<?php

namespace Achiev;
/**
 *
 * Achievements
 *
 * @author Arumi
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

define( 'ACHIV_VERSION', '0.4.3' );

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Achievements',
	'version' => ACHIV_VERSION,
	'author' => [ '[https://thwiki.cc/User:Arumi Arumi]' ],
	'url' => 'https://thwiki.cc/%E5%B8%AE%E5%8A%A9:%E6%88%90%E5%B0%B1%E6%89%A9%E5%B1%95',
	'descriptionmsg' => 'achievements-desc',
);

// 默认设定
$wgAchievementsIconStaged = $wgLogo;
$wgAchievementsIconNormal = $wgLogo;
$wgAchievementsTokenLength = 10;
$wgAchievementsConfigs = [];
$wgAchievementsScoring = false;

// 加载设定
include_once( __DIR__ . '/Achievements.settings.php' );

$wgMessagesDirs['Achievements'] = [ __DIR__ . '/i18n', __DIR__ . '/achievi18n' ];
$wgExtensionMessagesFiles['AchievementsMagic'] = __DIR__ . '/Achievements.magic.php';
$wgExtensionMessagesFiles['ManageAchievements'] = __DIR__ . '/Achievements.alias.php';

// 特殊页面管理成就
$wgSpecialPages['ManageAchievements'] = 'Achiev\\SpecialManageAchievements';
$wgAutoloadClasses['Achiev\\SpecialManageAchievements'] = __DIR__ . '/class/specialpages/SpecialManageAchievements.php';

// 特殊页面兑换成就
$wgSpecialPages['RedeemAchievement'] = 'Achiev\\SpecialRedeemAchievement';
$wgAutoloadClasses['Achiev\\SpecialRedeemAchievement'] = __DIR__ . '/class/specialpages/SpecialRedeemAchievement.php';

// Hook处理类
$wgAutoloadClasses['ExtAchievement'] = __DIR__ . '/Achievements_body.php';
// Achievement成就类
$wgAutoloadClasses['Achiev\\Achievement'] = __DIR__ . '/class/AchievementClass.php';
// Counter计数器类
$wgAutoloadClasses['Achiev\\Counter'] = __DIR__ . '/class/CounterClass.php';
// Token兑换码类
$wgAutoloadClasses['Achiev\\Token'] = __DIR__ . '/class/Token.php';
// Error收发错误类
$wgAutoloadClasses['Achiev\\AchievError'] = __DIR__ . '/class/ErrorClass.php';
// Echo模板类
$wgAutoloadClasses['Achiev\\AchievPresentationModel'] = __DIR__ . '/class/PresentationModel.php';

// 在所有页面中显示成就效果
$wgResourceModules['ext.achievement'] = array(
	'localBasePath' => __DIR__ . '/src',
	'remoteExtPath' => 'Achievements/src',
	'scripts' => 'achievements.js',
	'styles' => 'achievements.css',
	'dependencies' => [
		'jquery.tipsy'
	],
);

// 在用户设定页面中显示成就列表
$wgResourceModules['ext.pref.achievement'] = array(
	'localBasePath' => __DIR__ . '/src',
	'remoteExtPath' => 'Achievements/src',
	'scripts' => 'pref-achievements.js',
	'styles' => 'pref-achievements.css',
);

$wgHooks['ParserFirstCallInit'][] = 'ExtAchievement::init';
$wgHooks['GetPreferences'][] = 'ExtAchievement::onGetPreferences';
$wgHooks['UserSaveOptions'][] = 'ExtAchievement::onUserSaveOptions';

$wgHooks['BeforePageDisplay'][] = 'ExtAchievement::onBeforePageDisplay';

$wgHooks['PersonalUrls'][] = 'ExtAchievement::onPersonalUrls';
$wgHooks['HtmlPageLinkRendererEnd'][] = 'ExtAchievement::onHtmlPageLinkRendererEnd';
$wgHooks['SpecialPageAfterExecute'][] = 'ExtAchievement::onSpecialPageAfterExecute';

$wgHooks['BeforeCreateEchoEvent'][] = 'ExtAchievement::onBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'ExtAchievement::onEchoGetDefaultNotifiedUsers';

$wgHooks['UserProfileBeginLeft'][] = 'ExtAchievement::onUserProfileBeginLeft';

// 成就通知设定
$wgDefaultUserOptions['echo-subscriptions-web-achiev'] = true;
$wgDefaultUserOptions['echo-subscriptions-email-achiev'] = false;

require_once __DIR__ . '/class/AchievementHandler.php';
require_once __DIR__ . '/class/CounterHandler.php';

// 初始化Autoload及Hook
CounterHandler::init();


