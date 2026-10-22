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
	$subject = forum_trim((string)($in['subject'] ?? ''));
	$message = (string)($in['message'] ?? '');
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
