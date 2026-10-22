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

api_route('GET', '/online', function () {
	global $forum_db;

	$query = array(
		'SELECT'	=> 'o.user_id, o.ident',
		'FROM'		=> 'online AS o',
		'WHERE'		=> 'o.idle=0',
		'ORDER BY'	=> 'o.ident'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$users = array();
	$guests = 0;
	while ($row = $forum_db->fetch_assoc($result))
	{
		if ($row['user_id'] > 1)
			$users[] = ['id' => (int)$row['user_id'], 'username' => $row['ident']];
		else
			++$guests;
	}

	api_json(['users' => $users, 'guests' => $guests]);
});
