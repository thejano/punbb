<?php
// CLI only: SQLite add_field/field_exists stay idempotent for columns added by ALTER TABLE (db_update.php relies on it)
if (PHP_SAPI !== 'cli') exit;
define('FORUM_ROOT', dirname(__DIR__).'/'); define('FORUM', 1);
require FORUM_ROOT.'include/constants.php';
function error($m = '', $f = '', $l = 0) { echo "FAIL: $m $f:$l\n"; exit(1); }
require FORUM_ROOT.'include/dblayer/sqlite3.php';
$file = 'cache/test_field_'.getmypid().'.sqlite3';
chdir(FORUM_ROOT);
$db = new DBLayer('', '', '', $file, 'forum_', false);
$db->query("CREATE TABLE forum_users (\nid INTEGER PRIMARY KEY,\nsalt VARCHAR(12)\n)");
$db->query("INSERT INTO forum_users (salt) VALUES ('s')");
$ok = !$db->field_exists('users', 'login_token');
$db->add_field('users', 'login_token', 'VARCHAR(64)', false, '');
$ok = $ok && $db->field_exists('users', 'login_token') && $db->field_exists('users', 'salt');
$db->add_field('users', 'login_token', 'VARCHAR(64)', false, ''); // second run must be a no-op
$ok = $ok && $db->result($db->query('SELECT login_token FROM forum_users')) === '';
$db->close(); @unlink(FORUM_ROOT.$file);
echo $ok ? "all passed\n" : "FAIL\n"; exit($ok ? 0 : 1);
