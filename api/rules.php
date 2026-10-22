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
