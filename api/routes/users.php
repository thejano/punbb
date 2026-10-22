<?php

api_route('GET', '/users/{id}', function ($p) {
	global $forum_db;

	$id = (int)$p['id'];
	if ($id < 2)
		api_error('not_found', 'User not found', 404);

	$query = array(
		'SELECT'	=> 'u.id, u.username, u.title, u.num_posts, u.registered, u.url, u.location, u.signature, u.last_visit, u.last_post, u.avatar, u.avatar_width, u.avatar_height, g.g_id, g.g_user_title',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'u.id='.$id
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$u = $forum_db->fetch_assoc($result);
	if (!$u)
		api_error('not_found', 'User not found', 404);

	api_json(api_user_public($u) + [
		'group_id' => (int)$u['g_id'],
		'group_title' => $u['g_user_title'],
		'signature_html' => $u['signature'] != '' ? parse_signature($u['signature']) : '',
		'last_visit' => (int)$u['last_visit'],
		'last_post' => $u['last_post'] === null ? null : (int)$u['last_post'],
	]);
});

api_route('GET', '/users', function () {
	global $forum_db;

	$q = forum_trim((string)($_GET['q'] ?? ''));
	if (utf8_strlen($q) < 2)
		api_error('bad_request', 'q must be at least 2 characters', 400);

	$like = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q);
	$query = array(
		'SELECT'	=> 'u.id, u.username, u.title, u.num_posts, u.registered, u.url, u.location, u.avatar, u.avatar_width, u.avatar_height, g.g_id, g.g_user_title',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'u.id>1 AND u.username LIKE \''.$forum_db->escape($like).'%\'',
		'ORDER BY'	=> 'u.username',
		'LIMIT'		=> '20'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$users = array();
	while ($row = $forum_db->fetch_assoc($result))
		$users[] = api_user_public($row);

	api_json($users);
});
