<?php
if (PHP_SAPI !== 'cli') exit;
// A moderator of forum 1 only must not be able to delete posts in a forum 2 topic by passing fid=1.
//   FORUM_BASE=http://127.0.0.1:8085 php tests/moderate_test.php
// Creates a throwaway moderator, one topic in forum 1 and one in forum 2, and removes them afterwards.

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

// POST the delete, answer the CSRF confirmation page with the token it hands out, return the final body
function mt_delete_post(string $url, int $post_id, string $cookie): string
{
	$fields = array('delete_posts_comply' => '1', 'req_confirm' => '1', 'posts' => (string)$post_id);
	$send = function (array $f) use ($url, $cookie) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($f), CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => $cookie));
		$body = (string)curl_exec($ch);
		curl_close($ch);
		return $body;
	};
	$body = $send($fields);
	if (preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', $body, $m))
		$body = $send($fields + array('csrf_token' => $m[1]));
	return $body;
}

$username = 'modtest_tmp';
$salt = 'modtestsalt1';
$hash = forum_hash('modtest-pass', $salt);
$old_mods = $forum_db->result(q("SELECT moderators FROM {$db_prefix}forums WHERE id=1"));
$uid = 0;
$topics = array();

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

	// control: the same moderator can delete a reply in a topic of the forum it moderates
	mt_delete_post("$base/moderate.php?fid=1&tid=$own_tid", $own_post, $cookie);
	$ok = !post_exists($own_post);
	$fail += $ok ? 0 : 1;
	echo ($ok ? 'ok   ' : 'FAIL ')."control: reply in the moderated forum is deleted\n";
}
finally
{
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
}

echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
