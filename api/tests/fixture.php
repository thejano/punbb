<?php
// CLI helper for tests:
//   fixture.php hide|show <forum_id> <group_id> | close|open <topic_id> | readboard <group_id> <0|1>
//   fixture.php ban|unban <username> | maint <0|1> | unflood <username>
// common.php unsets $argv, so read it first.
$args = $_SERVER['argv'];
$_SERVER += ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'];
define('FORUM_ROOT', dirname(__DIR__, 2).'/');
define('FORUM_REQUEST_AJAX', 1);
define('FORUM_PAGE', 'api');
define('FORUM_TURN_OFF_MAINT', 1);
require FORUM_ROOT.'include/common.php';
require_once FORUM_ROOT.'include/cache.php';

[, $cmd, $a, $b] = $args + [null, '', '', ''];
$p = $forum_db->prefix;
$run = fn(string $sql) => $forum_db->query($sql) or error(__FILE__, __LINE__);

if ($cmd === 'hide')
{
	$run('DELETE FROM '.$p.'forum_perms WHERE group_id='.(int)$b.' AND forum_id='.(int)$a);
	$run('INSERT INTO '.$p.'forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES ('.(int)$b.', '.(int)$a.', 0, 0, 0)');
}
elseif ($cmd === 'show')
	$run('DELETE FROM '.$p.'forum_perms WHERE group_id='.(int)$b.' AND forum_id='.(int)$a);
elseif ($cmd === 'close' || $cmd === 'open')
	$run('UPDATE '.$p.'topics SET closed='.($cmd === 'close' ? 1 : 0).' WHERE id='.(int)$a);
elseif ($cmd === 'readboard')
	$run('UPDATE '.$p.'groups SET g_read_board='.($b === '1' ? 1 : 0).' WHERE g_id='.(int)$a);
elseif ($cmd === 'ban' || $cmd === 'unban')
{
	$run('DELETE FROM '.$p.'bans WHERE username=\''.$forum_db->escape($a).'\'');
	if ($cmd === 'ban')
		$run('INSERT INTO '.$p.'bans (username, ban_creator) VALUES (\''.$forum_db->escape($a).'\', 2)');
	generate_bans_cache();
}
elseif ($cmd === 'unflood')
	$run('UPDATE '.$p.'users SET last_post=NULL WHERE username=\''.$forum_db->escape($a).'\'');
elseif ($cmd === 'maint')
{
	$run('DELETE FROM '.$p.'config WHERE conf_name=\'o_maintenance\'');
	$run('INSERT INTO '.$p.'config (conf_name, conf_value) VALUES (\'o_maintenance\', \''.($a === '1' ? 1 : 0).'\')');
	generate_config_cache();
}
else
{
	fwrite(STDERR, "usage: fixture.php hide|show <forum_id> <group_id> | close|open <topic_id> | readboard <group_id> <0|1> | ban|unban <username> | maint <0|1> | unflood <username>\n");
	exit(1);
}

$forum_db->end_transaction();
