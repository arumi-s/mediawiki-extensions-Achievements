<?php

$wgAchievementsIconStaged = 'images/template/achiev-staged.png';
$wgAchievementsIconNormal = 'images/template/achiev-normal.png';

// 不同计数器类型的成就有不同的默认参数值，多数情况下是不需要把所有参数写上的

$wgAchievementsConfigs = [
	// 编辑总数成就范例
	'edit'=> [
		'type' => 'editcount', // 成就计数器类型
		'counter' => [
			'init' => 'current', // 初始值设为当前编辑数
		],
		'reset' => false, // 不会周期性重置
		'threshold' => [100,1000,10000], // 分多个阶段（阈值），用户进度达到一定值就会获得对应成就
		'removable' => true, // 用户不满足成就条件时会失去此成就
		'awardable' => false, // 不能使用兑换码颁发
		'hidden' => false, // 不是隐藏成就
		'active' => true, // 活跃成就
	],

	// 编辑月排名成就范例
	'edit-month-rank'=> [
		'type' => 'edittop',
		'counter' => [
			'init' => 0, // 初始值设为0，每周期会初始化一次
		],
		'reset' => 'm', // 设定重置周期为每月
		'threshold' => [1,2,3], // 分多个阶段，xxxtop类成就的阈值表示排名
		'removable' => false, // 用户不满足成就条件时也不会失去此成就，不这样设的话每月重置时成就就会自动消失
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],

	// 注册年数成就范例
	'year'=> [
		'type' => 'registerday',
		'counter' => [
			'init' => 'current', // 初始值设为当前日数
		],
		'reset' => false,
		'threshold' => [365,730,1095,1461,1826,2191,2556,2922], // 使用日数表示年数
		'removable' => false,
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],

	// 好友总数成就范例
	'friend'=> [
		'type' => 'friendcount',
		'counter' => [
			'init' => 'current', // 初始值设为当前好友数
		],
		'reset' => false,
		'threshold' => [1,5,10,50],
		'removable' => true,
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],

	// 指定分类下的页面编辑数成就范例
	'edit-album'=> [
		'type' => 'editcount',
		'counter' => [
			'init' => 'current', // 初始值设为当前编辑数（指定分类下）
			'cat' => '同人专辑',
		],
		'reset' => false,
		'threshold' => [10,20,40],
		'removable' => true,
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],

	// 指定页面及子页面编辑数成就范例
	'edit-sandbox'=> [
		'type' => 'editcount',
		'counter' => [
			'init' => 'current', // 初始值设为当前编辑数（指定页面下）
			'page' => '沙盒',
			'subpage' => true,
		],
		'reset' => false,
		'threshold' => 1, // 单阶段成就，只有一个阶段，用户达到了就会触发
		'removable' => true,
		'awardable' => false,
		'hidden' => true, // 隐藏成就不会自动在用户成就列表中出现，只有拥有此成就的用户才能看到
		'active' => true,
	],

	// 成为管理员成就范例
	'sysop'=> [
		'type' => 'usergroup',
		'counter' => [
			'group' => 'sysop', // 用户组名称
		],
		'reset' => false,
		'threshold' => 1,
		'removable' => true,
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],

	// 用户设定（头衔）成就范例
	'achiev-title'=> [
		'type' => 'userprop',
		'counter' => [
			'prop' => 'achievtitle', // 用户设定的属性
			'value' => '', // 用户设定的值，可以指定匹配任何值，也可以留空表示匹配任何非空字串
		],
		'reset' => false,
		'threshold' => 1,
		'removable' => true,
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],

	// 用户上传头像成就范例
	'user-avatar'=> [
		'type' => 'useravatar',
		'counter' => [
		],
		'reset' => false,
		'threshold' => 1,
		'removable' => true,
		'awardable' => false,
		'hidden' => false,
		'active' => true,
	],
];
