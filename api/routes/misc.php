<?php

api_route('GET', '/config', function () {
	global $forum_config;

	api_json([
		'board_title' => $forum_config['o_board_title'],
		'board_desc' => $forum_config['o_board_desc'],
		'default_lang' => $forum_config['o_default_lang'],
		'default_style' => $forum_config['o_default_style'],
		'time_format' => $forum_config['o_time_format'],
		'date_format' => $forum_config['o_date_format'],
		'regs_allow' => $forum_config['o_regs_allow'] == '1',
		'version' => FORUM_VERSION,
	]);
});

api_route('GET', '/me', function () {
	global $forum_user;

	api_json(api_user_public($forum_user) + [
		'is_guest' => (bool)$forum_user['is_guest'],
		'is_admmod' => (bool)$forum_user['is_admmod'],
		'language' => $forum_user['language'],
		'style' => $forum_user['style'],
		'timezone' => $forum_user['timezone'],
	]);
});
