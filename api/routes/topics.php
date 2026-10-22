<?php

api_route('GET', '/topics/{id}', function ($p) {
	api_json(api_topic_shape(api_topic_or_404((int)$p['id'])));
});

api_route('POST', '/forums/{id}/topics', function ($p) {
	global $forum_config, $forum_user;

	api_require_user();
	api_check_csrf();
	$forum = api_forum_or_404((int)$p['id']);
	if (!api_can_post_topic($forum))
		api_error('forbidden', 'You do not have permission to post topics in this forum', 403);
	api_flood_check();

	$in = api_input();
	$admmod = api_is_moderator($forum);
	$subject = forum_trim(api_str($in, 'subject'));
	$message = api_str($in, 'message');
	$fields = api_validate_subject($subject, $admmod) + api_validate_message($message, $admmod);
	if ($fields)
		api_error('validation', 'Invalid topic', 422, $fields);

	$post_info = array(
		'is_guest'		=> $forum_user['is_guest'],
		'poster'		=> $forum_user['username'],
		'poster_id'		=> $forum_user['id'],
		'poster_email'	=> null,
		'subject'		=> $subject,
		'message'		=> $message,
		'hide_smilies'	=> !empty($in['hide_smilies']) ? 1 : 0,
		'posted'		=> time(),
		'subscribe'		=> ($forum_config['o_subscriptions'] == '1' && !empty($in['subscribe'])),
		'forum_id'		=> (int)$forum['id'],
		'forum_name'	=> $forum['forum_name'],
		'update_user'	=> true,
		'update_unread'	=> true
	);
	add_topic($post_info, $new_tid, $new_pid);

	api_json(['topic_id' => (int)$new_tid, 'post_id' => (int)$new_pid], 201);
});

// Registered via glob after /topics/{id}; no clash because {id} only matches digits.
api_route('GET', '/topics/recent', function () {
	global $forum_db, $forum_user, $forum_config, $base_url;

	$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
	$query = array(
		'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.forum_id, f.forum_name, u.id AS uid, u.avatar, u.avatar_width, u.avatar_height',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'f.id=t.forum_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			),
			array(
				'LEFT JOIN'		=> 'users AS u',
				'ON'			=> 'u.username=t.last_poster'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL',
		'ORDER BY'	=> 't.last_post DESC',
		'LIMIT'		=> (string)$limit
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$topics = array();
	$ext = array(FORUM_AVATAR_GIF => 'gif', FORUM_AVATAR_JPG => 'jpg', FORUM_AVATAR_PNG => 'png');
	while ($row = $forum_db->fetch_assoc($result))
	{
		// a URL, never markup; null for a guest poster, no avatar or avatars turned off
		$avatar = ($forum_config['o_avatars'] == '1' && isset($ext[$row['avatar']]) && $row['avatar_width'] > 0 && $row['avatar_height'] > 0)
			? $base_url.'/'.$forum_config['o_avatars_dir'].'/'.$row['uid'].'.'.$ext[$row['avatar']] : null;
		$topics[] = api_topic_shape($row) + array(
			'forum_name' => $row['forum_name'],
			'last_poster_id' => $row['uid'] === null ? null : (int)$row['uid'],
			'last_poster_avatar' => $avatar,
		);
	}

	api_json($topics);
});
