<?php

function api_forum_or_404(int $id): array
{
	global $forum_db, $forum_user;

	$query = array(
		'SELECT'	=> 'f.id, f.forum_name, f.forum_desc, f.cat_id, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.sort_by, fp.post_topics, fp.post_replies',
		'FROM'		=> 'forums AS f',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$id
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);
	if (!$row)
		api_error('not_found', 'Forum not found', 404);

	return $row;
}

function api_topic_or_404(int $id): array
{
	global $forum_db, $forum_user;

	$query = array(
		'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'f.id=t.forum_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);
	if (!$row)
		api_error('not_found', 'Topic not found', 404);

	return $row;
}

function api_is_moderator(array $forum_row): bool
{
	global $forum_user;

	if ($forum_user['g_id'] == FORUM_ADMIN)
		return true;

	$mods = ($forum_row['moderators'] ?? '') != '' ? unserialize($forum_row['moderators'], ['allowed_classes' => false]) : array();
	return $forum_user['g_moderator'] == '1' && is_array($mods) && array_key_exists($forum_user['username'], $mods);
}

function api_forum_shape(array $f): array
{
	global $forum_user;

	$can_post = $f['redirect_url'] == '' && (($f['post_topics'] == '' && $forum_user['g_post_topics'] == '1') || $f['post_topics'] == '1' || api_is_moderator($f));

	return [
		'id' => (int)$f['id'],
		'name' => $f['forum_name'],
		'desc' => (string)$f['forum_desc'],
		'cat_id' => (int)$f['cat_id'],
		'num_topics' => (int)$f['num_topics'],
		'num_posts' => (int)$f['num_posts'],
		'last_post' => $f['last_post'] === null ? null : (int)$f['last_post'],
		'last_post_formatted' => $f['last_post'] ? format_time((int)$f['last_post']) : '',
		'last_post_id' => $f['last_post_id'] === null ? null : (int)$f['last_post_id'],
		'last_poster' => $f['last_poster'],
		'redirect_url' => (string)$f['redirect_url'],
		'can_post' => $can_post,
	];
}

function api_topic_shape(array $t): array
{
	return [
		'id' => (int)$t['id'],
		'subject' => $t['subject'],
		'poster' => $t['poster'],
		'posted' => (int)$t['posted'],
		'posted_formatted' => format_time((int)$t['posted']),
		'last_post' => (int)$t['last_post'],
		'last_post_formatted' => format_time((int)$t['last_post']),
		'last_post_id' => $t['last_post_id'] === null ? null : (int)$t['last_post_id'],
		'last_poster' => $t['last_poster'],
		'num_replies' => (int)$t['num_replies'],
		'num_views' => (int)$t['num_views'],
		'closed' => $t['closed'] == '1',
		'sticky' => $t['sticky'] == '1',
		'forum_id' => (int)$t['forum_id'],
		'moved_to' => $t['moved_to'] === null ? null : (int)$t['moved_to'],
	];
}

function api_can_edit_post(array $post, array $topic, array $forum): bool
{
	global $forum_user;

	if ($forum_user['is_guest'])
		return false;

	return api_is_moderator($forum) || ($post['poster_id'] == $forum_user['id'] && $forum_user['g_edit_posts'] == '1' && $topic['closed'] != '1');
}

function api_can_delete_post(array $post, array $topic, array $forum): bool
{
	global $forum_user;

	if ($forum_user['is_guest'])
		return false;

	if (api_is_moderator($forum))
		return true;

	// Mirrors delete.php:66-71: own post, in an open topic, with the matching group permission.
	$is_topic = $post['id'] == $topic['first_post_id'];
	return !(($forum_user['g_delete_posts'] == '0' && !$is_topic) || ($forum_user['g_delete_topics'] == '0' && $is_topic) || $post['poster_id'] != $forum_user['id'] || $topic['closed'] == '1');
}

// Mirrors post.php:93-96 (permission to post a topic): not a redirect forum, post_topics null falls back to g_post_topics, moderators always.
function api_can_post_topic(array $forum): bool
{
	global $forum_user;

	if ($forum['redirect_url'] != '')
		return false;

	return api_is_moderator($forum) || $forum['post_topics'] == '1' || ($forum['post_topics'] == '' && $forum_user['g_post_topics'] == '1');
}

// Mirrors post.php:93-96 (permission to reply): not a redirect forum, post_replies null falls back to g_post_replies, closed topics only for moderators.
function api_can_reply(array $topic, array $forum): bool
{
	global $forum_user;

	if (($forum['redirect_url'] ?? '') != '')
		return false;

	if (api_is_moderator($forum))
		return true;

	return $topic['closed'] != '1' && ($forum['post_replies'] == '1' || ($forum['post_replies'] == '' && $forum_user['g_post_replies'] == '1'));
}

// Mirrors post.php:113-115 (flood protection); responds 429 instead of collecting an error.
function api_flood_check(): void
{
	global $forum_user;

	$elapsed = time() - (int)$forum_user['last_post'];
	if ($forum_user['last_post'] != '' && $elapsed < $forum_user['g_post_flood'] && $elapsed >= 0)
		api_error('flood', 'Posting too fast', 429, ['retry_after' => (int)$forum_user['g_post_flood'] - $elapsed]);
}

function api_load_lang_post(): void
{
	global $forum_user, $lang_post;

	if (!isset($lang_post))
		require FORUM_ROOT.'lang/'.$forum_user['language'].'/post.php';
}

// Mirrors post.php:117-127 (subject checks); $admmod skips the all-caps rule.
function api_validate_subject(string $s, bool $admmod = false): array
{
	global $forum_config, $lang_post;

	api_load_lang_post();
	$s = forum_trim($s);

	if ($s == '')
		return ['subject' => $lang_post['No subject']];
	if (utf8_strlen($s) > FORUM_SUBJECT_MAXIMUM_LENGTH)
		return ['subject' => sprintf($lang_post['Too long subject'], FORUM_SUBJECT_MAXIMUM_LENGTH)];
	if ($forum_config['p_subject_all_caps'] == '0' && check_is_all_caps($s) && !$admmod)
		return ['subject' => $lang_post['All caps subject']];

	return array();
}

// Mirrors post.php:155-186 (message checks); $m is trimmed, linebreak-normalised and run through preparse_bbcode in place.
function api_validate_message(string &$m, bool $admmod = false): array
{
	global $forum_config, $lang_post;

	api_load_lang_post();
	$errors = array();
	$m = forum_linebreaks(forum_trim($m));

	if (strlen($m) > FORUM_MAX_POSTSIZE_BYTES)
		$errors[] = sprintf($lang_post['Too long message'], forum_number_format(strlen($m)), forum_number_format(FORUM_MAX_POSTSIZE_BYTES));
	else if ($forum_config['p_message_all_caps'] == '0' && check_is_all_caps($m) && !$admmod)
		$errors[] = $lang_post['All caps message'];

	if ($forum_config['p_message_bbcode'] == '1' || $forum_config['o_make_links'] == '1')
		$m = preparse_bbcode($m, $errors);

	if ($m == '')
		$errors[] = $lang_post['No message'];

	return $errors ? ['message' => implode(' ', $errors)] : array();
}
