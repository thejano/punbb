<?php
// CLI helper for tests: php api/tests/fixture.php hide|show <forum_id> <group_id>
// common.php unsets $argv, so read it first.
$args = $_SERVER['argv'];
$_SERVER += ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'];
define('FORUM_ROOT', dirname(__DIR__, 2).'/');
define('FORUM_REQUEST_AJAX', 1);
define('FORUM_PAGE', 'api');
require FORUM_ROOT.'include/common.php';

[, $cmd, $fid, $gid] = $args + [null, '', 0, 0];
$fid = (int)$fid;
$gid = (int)$gid;

if ($cmd === 'hide')
	$forum_db->query('REPLACE INTO '.$forum_db->prefix.'forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES ('.$gid.', '.$fid.', 0, 0, 0)') or error(__FILE__, __LINE__);
elseif ($cmd === 'show')
	$forum_db->query('DELETE FROM '.$forum_db->prefix.'forum_perms WHERE group_id='.$gid.' AND forum_id='.$fid) or error(__FILE__, __LINE__);
else
{
	fwrite(STDERR, "usage: fixture.php hide|show <forum_id> <group_id>\n");
	exit(1);
}

$forum_db->end_transaction();
