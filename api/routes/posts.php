<?php

// Posts with poster and online status for the given ids, in id order (the join query of viewtopic.php).
function api_posts_by_ids(array $ids, array $topic): array
{
	global $forum_db;

	if (empty($ids))
		return array();

	$query = array(
		'SELECT'	=> 'u.title, u.url, u.location, u.num_posts, u.registered, u.avatar, u.avatar_width, u.avatar_height, p.id, p.topic_id, p.poster AS username, p.poster_id, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by, g.g_id, g.g_user_title, o.user_id AS is_online',
		'FROM'		=> 'posts AS p',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'users AS u',
				'ON'			=> 'u.id=p.poster_id'
			),
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			),
			array(
				'LEFT JOIN'		=> 'online AS o',
				'ON'			=> '(o.user_id=u.id AND o.user_id!=1 AND o.idle=0)'
			)
		),
		'WHERE'		=> 'p.id IN ('.implode(',', array_map('intval', $ids)).')',
		'ORDER BY'	=> 'p.id'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$posts = array();
	while ($row = $forum_db->fetch_assoc($result))
		$posts[] = api_post_shape($row, $topic);

	return $posts;
}

api_route('GET', '/topics/{id}/posts', function ($p) {
	global $forum_db, $forum_config, $forum_user;

	$topic = api_topic_or_404((int)$p['id']);
	$per_page = (int)$forum_user['disp_posts'] ?: (int)$forum_config['o_disp_posts_default'];
	$page = api_page((int)$topic['num_replies'] + 1, $per_page);

	$query = array(
		'SELECT'	=> 'p.id',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.(int)$topic['id'],
		'ORDER BY'	=> 'p.id',
		'LIMIT'		=> $page['offset'].','.$per_page
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$ids = array();
	while ($row = $forum_db->fetch_assoc($result))
		$ids[] = $row['id'];

	api_json(api_posts_by_ids($ids, $topic), 200, ['page' => $page['page'], 'pages' => $page['pages'], 'total' => (int)$topic['num_replies'] + 1]);
});

api_route('GET', '/posts/{id}', function ($p) {
	global $forum_db;

	$query = array(
		'SELECT'	=> 'p.topic_id',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.id='.(int)$p['id']
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);
	if (!$row)
		api_error('not_found', 'Post not found', 404);

	$post = api_posts_by_ids(array((int)$p['id']), api_topic_or_404((int)$row['topic_id']));
	if (!$post)
		api_error('not_found', 'Post not found', 404);

	api_json($post[0]);
});

// Parser errors are returned as an array of messages in fields.message.
api_route('POST', '/preview', function () {
	global $lang_post;

	api_check_csrf();
	api_throttle('preview:'.get_remote_address(), 30, 60);
	api_load_lang_post();

	$in = api_input();
	$raw = api_str($in, 'message');
	if (strlen($raw) > FORUM_MAX_POSTSIZE_BYTES)
		api_error('validation', 'Invalid message', 422, ['message' => [sprintf($lang_post['Too long message'], forum_number_format(strlen($raw)), forum_number_format(FORUM_MAX_POSTSIZE_BYTES))]]);

	$errors = array();
	$message = preparse_bbcode(forum_linebreaks(trim($raw)), $errors);
	if ($message == '')
		$errors[] = $lang_post['No message'];
	if (!empty($errors))
		api_error('validation', 'Invalid message', 422, ['message' => array_values($errors)]);

	api_json(['html' => parse_message($message, !empty($in['hide_smilies']))]);
});

api_route('POST', '/topics/{id}/posts', function ($p) {
	global $forum_db, $forum_config, $forum_user;

	api_require_user();
	api_check_csrf();
	$topic = api_topic_or_404((int)$p['id']);
	if (!api_can_reply($topic, $topic))
		api_error('forbidden', 'You do not have permission to reply in this topic', 403);
	api_flood_check();

	$in = api_input();
	$message = api_str($in, 'message');
	$fields = api_validate_message($message, api_is_moderator($topic));
	if ($fields)
		api_error('validation', 'Invalid post', 422, $fields);

	$query = array(
		'SELECT'	=> 's.user_id',
		'FROM'		=> 'subscriptions AS s',
		'WHERE'		=> 's.topic_id='.(int)$topic['id'].' AND s.user_id='.(int)$forum_user['id']
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$is_subscribed = (bool)$forum_db->fetch_assoc($result);

	// An omitted "subscribe" keeps the current subscription; the form's unchecked box would drop it.
	$subscribe = array_key_exists('subscribe', $in) ? !empty($in['subscribe']) : $is_subscribed;
	$on = $forum_config['o_subscriptions'] == '1';

	$post_info = array(
		'is_guest'		=> $forum_user['is_guest'],
		'poster'		=> $forum_user['username'],
		'poster_id'		=> $forum_user['id'],
		'poster_email'	=> null,
		'subject'		=> $topic['subject'],
		'message'		=> $message,
		'hide_smilies'	=> !empty($in['hide_smilies']) ? 1 : 0,
		'posted'		=> time(),
		'subscr_action'	=> ($on && $subscribe && !$is_subscribed) ? 1 : (($on && !$subscribe && $is_subscribed) ? 2 : 0),
		'topic_id'		=> (int)$topic['id'],
		'forum_id'		=> (int)$topic['forum_id'],
		'update_user'	=> true,
		'update_unread'	=> true
	);
	add_post($post_info, $new_pid);

	api_json(['post_id' => (int)$new_pid], 201);
});

// Loads the post and its topic; 404s if either is missing or hidden.
function api_post_and_topic_or_404(int $id): array
{
	global $forum_db;

	$query = array(
		'SELECT'	=> 'p.id, p.topic_id, p.poster_id, p.hide_smilies',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.id='.$id
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$post = $forum_db->fetch_assoc($result);
	if (!$post)
		api_error('not_found', 'Post not found', 404);

	return array($post, api_topic_or_404((int)$post['topic_id']));
}

api_route('PUT', '/posts/{id}', function ($p) {
	global $forum_db, $forum_user;

	api_require_user();
	api_check_csrf();
	list($post, $topic) = api_post_and_topic_or_404((int)$p['id']);
	if (!api_can_edit_post($post, $topic, $topic))
		api_error('forbidden', 'You do not have permission to edit this post', 403);

	$admmod = api_is_moderator($topic);
	$in = api_input();
	$fields = array();

	// Mirrors edit.php: only the first post carries the subject.
	$subject = null;
	if ($post['id'] == $topic['first_post_id'] && isset($in['subject']) && is_scalar($in['subject']))
	{
		$subject = forum_trim(api_str($in, 'subject'));
		$fields += api_validate_subject($subject, $admmod);
	}

	$message = api_str($in, 'message');
	$fields += api_validate_message($message, $admmod);
	if ($fields)
		api_error('validation', 'Invalid post', 422, $fields);

	require_once FORUM_ROOT.'include/search_idx.php';

	if ($subject !== null)
	{
		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'subject=\''.$forum_db->escape($subject).'\'',
			'WHERE'		=> 'id='.(int)$topic['id'].' OR moved_to='.(int)$topic['id']
		);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
		update_search_index('edit', (int)$post['id'], $message, $subject);
	}
	else
		update_search_index('edit', (int)$post['id'], $message);

	$query = array(
		'UPDATE'	=> 'posts',
		'SET'		=> 'message=\''.$forum_db->escape($message).'\', hide_smilies=\''.(array_key_exists('hide_smilies', $in) ? (!empty($in['hide_smilies']) ? 1 : 0) : (int)$post['hide_smilies']).'\'',
		'WHERE'		=> 'id='.(int)$post['id']
	);
	if (empty($in['silent']) || !$admmod)
		$query['SET'] .= ', edited='.time().', edited_by=\''.$forum_db->escape($forum_user['username']).'\'';
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	if ($subject !== null)
		$topic['subject'] = $subject;
	api_json(api_posts_by_ids(array((int)$post['id']), $topic)[0]);
});

api_route('DELETE', '/posts/{id}', function ($p) {
	api_require_user();
	api_check_csrf();
	list($post, $topic) = api_post_and_topic_or_404((int)$p['id']);
	if (!api_can_delete_post($post, $topic, $topic))
		api_error('forbidden', 'You do not have permission to delete this post', 403);

	// Mirrors delete.php; delete_topic/delete_post already call sync_forum (the core has no update_forum).
	$is_topic = $post['id'] == $topic['first_post_id'];
	if ($is_topic)
		delete_topic((int)$topic['id'], (int)$topic['forum_id']);
	else
		delete_post((int)$post['id'], (int)$topic['id'], (int)$topic['forum_id']);

	api_json(['deleted' => $is_topic ? 'topic' : 'post', 'topic_id' => (int)$topic['id'], 'forum_id' => (int)$topic['forum_id']]);
});
