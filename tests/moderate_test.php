<?php
if (PHP_SAPI !== 'cli') exit;
// A moderator of forum 1 only must not be able to delete posts in a forum 2 topic by passing fid=1, see the address of a
// forum 2 post, see a forum 2 subject on the move form, or move a topic into a forum its group cannot read (or into forum 1).
//   FORUM_BASE=http://127.0.0.1:8085 php tests/moderate_test.php
// Creates a throwaway moderator, one topic in forum 1 and one in forum 2, hides forum 3 from the moderators group for
// a moment, and restores all of it afterwards.

define('FORUM_ROOT', dirname(__DIR__).'/');
require FORUM_ROOT.'include/essentials.php';
// the InnoDB layer opens a transaction on connect; commit it so the web server sees each write right away
$forum_db->end_transaction();

$base = rtrim(getenv('FORUM_BASE') ?: 'http://127.0.0.1:8085', '/');
$now = time();
$fail = 0;

function q(string $sql)
{
	global $forum_db;
	$r = $forum_db->query($sql);
	if ($r === false)
		throw new RuntimeException('query failed: '.$sql);
	return $r;
}

function make_topic(int $forum_id, string $poster, int $poster_id): array
{
	global $forum_db, $db_prefix, $now;
	q("INSERT INTO {$db_prefix}topics (poster, subject, posted, last_post, last_poster, forum_id) VALUES ('$poster', 'moderate_test', $now, $now, '$poster', $forum_id)");
	$tid = (int)$forum_db->insert_id();
	$ids = array();
	foreach (array('first', 'reply') as $m)
	{
		q("INSERT INTO {$db_prefix}posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES ('$poster', $poster_id, '127.0.0.1', '$m', $now, $tid)");
		$ids[] = (int)$forum_db->insert_id();
	}
	q("UPDATE {$db_prefix}topics SET first_post_id={$ids[0]}, last_post_id={$ids[1]}, num_replies=1 WHERE id=$tid");
	return array($tid, $ids[1]);
}

function post_exists(int $id): bool
{
	global $forum_db, $db_prefix;
	return (int)$forum_db->result(q("SELECT COUNT(*) FROM {$db_prefix}posts WHERE id=$id")) === 1;
}

// GET the url, or POST $fields and answer the CSRF confirmation page with the token it hands out; return the final body
function mt_request(string $url, ?array $fields, string $cookie): string
{
	$send = function (?array $f) use ($url, $cookie) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => $cookie));
		if ($f !== null)
			curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($f)));
		$body = (string)curl_exec($ch);
		curl_close($ch);
		return $body;
	};
	$body = $send($fields);
	if ($fields !== null && preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', $body, $m))
		$body = $send($fields + array('csrf_token' => $m[1]));
	return $body;
}

function mt_delete_post(string $url, int $post_id, string $cookie): string
{
	return mt_request($url, array('delete_posts_comply' => '1', 'req_confirm' => '1', 'posts' => (string)$post_id), $cookie);
}

function check(bool $ok, string $label): void
{
	global $fail;
	$fail += $ok ? 0 : 1;
	echo ($ok ? 'ok   ' : 'FAIL ').$label."\n";
}

function topic_forum(int $tid): int
{
	global $forum_db, $db_prefix;
	return (int)$forum_db->result(q("SELECT forum_id FROM {$db_prefix}topics WHERE id=$tid"));
}

$username = 'modtest_tmp';
$salt = 'modtestsalt1';
$hash = forum_hash('modtest-pass', $salt);
$old_mods = $forum_db->result(q("SELECT moderators FROM {$db_prefix}forums WHERE id=1"));
$uid = 0;
$topics = array();
$perm3 = $forum_db->fetch_assoc(q("SELECT * FROM {$db_prefix}forum_perms WHERE group_id=4 AND forum_id=3")) ?: null;
$perm3_set = false;

try
{
	q("INSERT INTO {$db_prefix}users (username, group_id, password, salt, email, registered, language, style) VALUES ('$username', 4, '$hash', '$salt', 'modtest@example.com', $now, 'English', 'Oxygen')");
	$uid = (int)$forum_db->insert_id();
	q("UPDATE {$db_prefix}forums SET moderators='".$forum_db->escape(serialize(array($username => $uid)))."' WHERE id=1");

	list($own_tid, $own_post) = make_topic(1, $username, $uid);
	list($other_tid, $other_post) = make_topic(2, $username, $uid);
	$topics = array($own_tid, $other_tid);

	$expire = $now + 3600;
	$cookie = $cookie_name.'='.rawurlencode(base64_encode($uid.'|'.$hash.'|'.$expire.'|'.sha1($salt.$hash.forum_hash($expire, $salt))));

	// cross-forum: fid=1 (moderated) but the topic lives in forum 2
	$body = mt_delete_post("$base/moderate.php?fid=1&tid=$other_tid", $other_post, $cookie);
	$ok = post_exists($other_post);
	$fail += $ok ? 0 : 1;
	echo ($ok ? 'ok   ' : 'FAIL ')."post in forum 2 survives a delete sent with fid=1\n";
	$ok = strpos($body, 'Bad request') !== false;
	$fail += $ok ? 0 : 1;
	echo ($ok ? 'ok   ' : 'FAIL ')."the cross-forum request is refused with Bad request\n";

	// get_host: the address of a post only for moderators of its forum
	q("UPDATE {$db_prefix}posts SET poster_ip='10.9.8.7' WHERE id=$other_post");
	check(strpos(mt_request("$base/moderate.php?get_host=$other_post", null, $cookie), '10.9.8.7') === false, 'get_host of a forum 2 post is refused');
	check(strpos(mt_request("$base/moderate.php?get_host=$own_post", null, $cookie), '127.0.0.1') !== false, 'control: get_host of a forum 1 post answers');

	// single-topic move form: a forum 2 topic sent with fid=1 is refused, so its subject is not shown
	q("UPDATE {$db_prefix}topics SET subject='moderate_test_hidden' WHERE id=$other_tid");
	$body = mt_request("$base/moderate.php?fid=1&move_topics=$other_tid", null, $cookie);
	check(strpos($body, 'moderate_test_hidden') === false && strpos($body, 'Bad request') !== false, 'move form for a forum 2 topic is Bad request, no subject');

	// move target: not a forum the group cannot read, not the source forum
	q("REPLACE INTO {$db_prefix}forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES (4, 3, 0, 0, 0)");
	$perm3_set = true;
	foreach (array(3 => 'a forum the group cannot read', 1 => 'the forum it comes from') as $to => $label)
	{
		$body = mt_request("$base/moderate.php?fid=1", array('move_topics_to' => '1', 'topics' => (string)$own_tid, 'move_to_forum' => (string)$to), $cookie);
		check(topic_forum($own_tid) === 1 && strpos($body, 'Bad request') !== false, "move into $label is Bad request");
	}

	// control: the same moderator can delete a reply in a topic of the forum it moderates
	mt_delete_post("$base/moderate.php?fid=1&tid=$own_tid", $own_post, $cookie);
	$ok = !post_exists($own_post);
	$fail += $ok ? 0 : 1;
	echo ($ok ? 'ok   ' : 'FAIL ')."control: reply in the moderated forum is deleted\n";
}
finally
{
	if ($perm3_set)
	{
		q("DELETE FROM {$db_prefix}forum_perms WHERE group_id=4 AND forum_id=3");
		if ($perm3)
			q("INSERT INTO {$db_prefix}forum_perms (".implode(', ', array_keys($perm3)).") VALUES ('".implode("', '", array_map(array($forum_db, 'escape'), $perm3))."')");
	}
	foreach ($topics as $tid)
	{
		q("DELETE FROM {$db_prefix}posts WHERE topic_id=$tid");
		q("DELETE FROM {$db_prefix}topics WHERE id=$tid");
	}
	if ($uid)
	{
		q("DELETE FROM {$db_prefix}users WHERE id=$uid");
		q("DELETE FROM {$db_prefix}online WHERE user_id=$uid");
	}
	q("UPDATE {$db_prefix}forums SET moderators=".($old_mods === null || $old_mods === false ? 'NULL' : "'".$forum_db->escape($old_mods)."'")." WHERE id=1");
	sync_forum(1);
	sync_forum(2);
	sync_forum(3);
}

echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
