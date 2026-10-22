<?php
if (PHP_SAPI !== 'cli') exit;
// Login throttle, in process with a scratch cache folder: failures on address A never delay the same account on address B; A
// gets refused after 100 failures; one address spraying made-up names stays within a bounded number of entries; the file is
// capped at 5000 entries. Over HTTP: five wrong passwords for one (account, address) pair slow it to one try a minute (accent
// and case variants share the count); another account still logs in; an off-site redirect_url falls back to the forum;
// extern.php Basic auth is throttled; 100 failures from one address give 429 for that address. With a throwaway member: the
// auth cookie holds the login token, never the password hash; a tampered mac, a swapped id and an old-format cookie are
// refused; web and API logout rotate the token, so old cookies and bearer tokens stop working; a password change ends the
// other sessions; a forgotten-password key cannot confirm an e-mail change and an e-mail change key cannot reset a password.
//   FORUM_BASE=http://127.0.0.1:8085 php tests/login_test.php   (local board only: it clears cache/throttle.json)

$base = rtrim(getenv('FORUM_BASE') ?: 'http://127.0.0.1:8085', '/');
$throttle = dirname(__DIR__).'/cache/throttle.json';
$jar = tempnam(sys_get_temp_dir(), 'lt');
$fail = 0;

function req(string $url, ?array $post, string $jar): string
{
	$ch = curl_init($url);
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar));
	if ($post !== null)
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	$out = (string)curl_exec($ch);
	curl_close($ch);
	return $out;
}

function check(bool $ok, string $label): void
{
	global $fail;
	$fail += $ok ? 0 : 1;
	echo ($ok ? 'ok   ' : 'FAIL ').$label."\n";
}


// in process, against a scratch cache folder
define('FORUM_ROOT', dirname(__DIR__).'/');
define('FORUM_CACHE_DIR', sys_get_temp_dir().'/lt-cache-'.getmypid().'/');
@mkdir(FORUM_CACHE_DIR);
require FORUM_ROOT.'include/essentials.php';
// the InnoDB layer opens a transaction on connect; commit it so the web server sees each write right away
$forum_db->end_transaction();
$entries = fn() => count(json_decode((string)@file_get_contents(FORUM_CACHE_DIR.'throttle.json'), true) ?: array());

// forum_login_throttle() reserves each attempt as a failure before the password is checked, so attempts that never finish
// (a parallel burst) count too: 50 reservations for one pair get exactly 5 through
$_SERVER['REMOTE_ADDR'] = '203.0.113.1';
$granted = 0;
for ($i = 0; $i < 50; $i++)
	$granted += forum_login_throttle(2, 'admin') ? 1 : 0;
check($granted === 5, 'reserving 50 attempts without completing any grants '.$granted.' (the pair limit is 5)');
check(!forum_login_throttle(2, 'admin', $retry) && $retry > 0 && $retry <= 60, 'address A is then slowed for that account (retry in '.$retry.' s)');
$_SERVER['REMOTE_ADDR'] = '203.0.113.2';
check(forum_login_throttle(2, 'admin'), 'the same account on address B is not delayed by A');
$_SERVER['REMOTE_ADDR'] = '203.0.113.1';
for ($i = 0; $i < 95; $i++)
	forum_login_throttle(0, 'made-up-'.$i);
check(!forum_login_throttle(0, 'fresh-name') && !forum_login_throttle(3, 'other'), 'address A is refused for every account after 100 failures');
$_SERVER['REMOTE_ADDR'] = '203.0.113.2';
check(forum_login_throttle(2, 'admin'), 'address B still logs in');
forum_login_throttle_clear(2, 'admin');
$stored = json_decode(file_get_contents(FORUM_CACHE_DIR.'throttle.json'), true);
check(!isset($stored[sha1('login:203.0.113.2:user:2')]) && count($stored[sha1('login:203.0.113.2')]) === 1, 'a correct password clears the pair and gives back its address hit');

@unlink(FORUM_CACHE_DIR.'throttle.json');
$_SERVER['REMOTE_ADDR'] = '203.0.113.3';
for ($i = 0; $i < 2000; $i++)
	forum_login_throttle(0, 'spray-'.$i);
check($entries() <= 201, 'one address spraying 2000 made-up names leaves '.$entries().' entries (address + 100 pairs x 2)');
for ($i = 0; $i < 5100; $i++)
	forum_throttle('cap-'.$i, 5, 900);
check($entries() === 5000, 'the file is capped at 5000 entries');
check(forum_throttle('cap-5099', 1, 900, false) === false, 'the newest entry survives the cap');
array_map('unlink', glob(FORUM_CACHE_DIR.'*'));
@rmdir(FORUM_CACHE_DIR);

// over HTTP, against the board (all from 127.0.0.1)
@unlink($throttle);
preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', req("$base/login.php", null, $jar), $m);
$token = $m[1] ?? '';
check($token !== '', 'login form hands out a token');

$login = fn(string $user, string $pass, string $to = '') => req("$base/login.php", array('form_sent' => '1', 'req_username' => $user, 'req_password' => $pass,
	'redirect_url' => $to ?: "$base/index.php", 'csrf_token' => $token), $jar);

for ($i = 1; $i <= 5; $i++)
	$body = $login('apitest', 'wrong-password');
check(strpos($body, 'Incorrect username') !== false, 'fifth wrong password is still answered normally');
check(strpos($login('apitest', 'pass1234'), 'Too many failed logins') !== false, 'sixth try is refused, even with the right password');
check(strpos($login('ÀPITÉST', 'pass1234'), 'Too many failed logins') !== false, 'an accent and case variant of the name shares the count');
// each from a fresh guest session: a logged-in member posting the guest token would get the CSRF confirm page
$fresh_login = function (string $to, string $user = 'admin', string $pass = 'secret123') use ($base) {
	$jar = tempnam(sys_get_temp_dir(), 'lt');
	preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', req("$base/login.php", null, $jar), $m);
	$out = req("$base/login.php", array('form_sent' => '1', 'req_username' => $user, 'req_password' => $pass, 'redirect_url' => $to, 'csrf_token' => $m[1] ?? ''), $jar);
	@unlink($jar);
	return $out;
};
$ok_login = fn(string $user, string $pass) => stripos($fresh_login("$base/index.php", $user, $pass), "Location: $base/index.php?login=1") !== false;
check($ok_login('admin', 'secret123'), 'another username from the same address still logs in');

// a minute after the last failure the right password gets through: shift every recorded failure back 61 seconds
$age = function () use ($throttle) {
	$all = json_decode((string)file_get_contents($throttle), true);
	file_put_contents($throttle, json_encode(array_map(fn($v) => array_map(fn($t) => $t - 61, $v), $all)));
};
$age();
check($ok_login('apitest', 'pass1234'), 'a minute after the last failure the right password logs in (delay, not lockout)');

foreach (array('http://evil.example/x', '//evil.example/x', $base.'.evil.example/x') as $to)
	check(preg_match('#^Location: (.*)$#mi', $fresh_login($to), $loc) && strpos(trim($loc[1]), $base.'/') === 0, "redirect_url $to stays on the forum");

// extern.php Basic auth goes through the same throttle
$extern = function (string $user, string $pass) use ($base) {
	$ch = curl_init("$base/extern.php?action=feed&type=rss");
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => "$user:$pass"));
	curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	return $code;
};
for ($i = 1; $i <= 5; $i++)
	$code = $extern('apitest', 'wrong-password');
check($code !== 429, 'extern.php: fifth wrong password is still answered normally');
check($extern('apitest', 'pass1234') === 429, 'extern.php: sixth try gets 429, even with the right password');
check(strpos($fresh_login("$base/index.php", 'apitest', 'pass1234'), 'Too many failed logins') !== false, 'extern.php failures count for login.php too');
$age();
check($extern('apitest', 'pass1234') === 200, 'extern.php: a minute later the right password gets the feed');
check($ok_login('apitest', 'pass1234'), 'and its success cleared the pair for login.php');

// 100 failures from one address: that address is then refused (429), even with a correct password
@unlink($throttle);
for ($i = 1; $i <= 100; $i++)
	$fresh_login("$base/index.php", 'ghost'.$i.'-'.time(), 'wrong-password');
$out = $fresh_login("$base/index.php");
check(strpos($out, ' 429 ') !== false && strpos($out, 'Too many failed logins') !== false, 'past the address limit the address gets 429');

@unlink($throttle);

// login token cookie, with a throwaway member
$q = function (string $sql) use ($forum_db) {
	$r = $forum_db->query($sql);
	if ($r === false)
		throw new RuntimeException('query failed: '.$sql);
	return $r;
};
$row = fn(int $id) => $forum_db->fetch_assoc($q("SELECT * FROM {$db_prefix}users WHERE id=$id"));
// $session is a cookie jar file or a raw Cookie/Authorization header line; answers the API's /me
$me = function (string $session) use ($base) {
	$ch = curl_init("$base/api/index.php?route=/me");
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true));
	if (is_file($session))
		curl_setopt_array($ch, array(CURLOPT_COOKIEFILE => $session));
	else
		curl_setopt($ch, CURLOPT_HTTPHEADER, array($session));
	$out = json_decode((string)curl_exec($ch), true);
	curl_close($ch);
	return (int)($out['data']['id'] ?? 1);
};
$jar_cookie = function (string $jar) use ($cookie_name) {
	foreach (file($jar) as $line)
		if (count($f = explode("\t", trim($line))) === 7 && $f[5] === $cookie_name)
			return rawurldecode($f[6]);
	return '';
};
$web_login = function (string $user, string $pass) use ($base) {
	$jar = tempnam(sys_get_temp_dir(), 'lt');
	preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', req("$base/login.php", null, $jar), $m);
	req("$base/login.php", array('form_sent' => '1', 'req_username' => $user, 'req_password' => $pass, 'redirect_url' => "$base/index.php", 'csrf_token' => $m[1] ?? ''), $jar);
	return $jar;
};
$api_login = function (string $user, string $pass) use ($base) {
	$ch = curl_init("$base/api/index.php?route=/auth/login");
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => json_encode(array('username' => $user, 'password' => $pass)), CURLOPT_HTTPHEADER => array('Content-Type: application/json')));
	$out = json_decode((string)curl_exec($ch), true);
	curl_close($ch);
	return (string)($out['data']['token'] ?? '');
};
// POST $fields to the form at $url (its csrf_token taken from the page) as the session in $jar; return the final page
$form_post = function (string $url, array $fields, string $jar) {
	preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', req($url, null, $jar), $m);
	return req($url, $fields + array('csrf_token' => $m[1] ?? ''), $jar);
};

$tmp_name = 'logintest_tmp';
$tmp_salt = random_key(12);
$q("DELETE FROM {$db_prefix}users WHERE username='$tmp_name'");
$q("INSERT INTO {$db_prefix}users (username, group_id, password, salt, email, registered, language, style) VALUES ('$tmp_name', 3, '".forum_hash('first-pass', $tmp_salt)."', '$tmp_salt', 'logintest@example.com', ".time().", 'English', 'Oxygen')");
$tmp = (int)$forum_db->insert_id();
$jars = array();
try
{
	$jars[] = $a = $web_login($tmp_name, 'first-pass');
	$user = $row($tmp);
	$parts = explode('|', base64_decode($jar_cookie($a)));
	check($me($a) === $tmp, 'a web login gives a working session');
	check(preg_match('/^[0-9a-f]{64}$/', $user['login_token']) === 1, 'the first login made a 64-character login token');
	check(count($parts) === 4 && $parts[0] === (string)$tmp && $parts[1] === $user['login_token'], 'the cookie is id|login_token|expire|mac');
	check(strpos(base64_decode($jar_cookie($a)), $user['password']) === false, 'the cookie does not carry the password hash');

	$send = fn(array $p) => $me('Cookie: '.$cookie_name.'='.rawurlencode(base64_encode(implode('|', $p))));
	check($send($parts) === $tmp, 'the cookie replayed by hand works (control)');
	$bad = $parts;
	$bad[3] = substr($bad[3], 0, -1).(substr($bad[3], -1) === '0' ? '1' : '0');
	check($send($bad) === 1, 'a cookie with a tampered mac is a guest');
	$bad = $parts;
	$bad[2] = (string)((int)$bad[2] + 3600);
	check($send($bad) === 1, 'a cookie with a changed expiry is a guest');
	$bad = $parts;
	$bad[0] = '2';
	check($send($bad) === 1, 'a cookie with a swapped id is a guest');
	$exp = time() + 3600;
	$old = array($tmp, $user['password'], $exp, sha1($tmp_salt.$user['password'].forum_hash($exp, $tmp_salt)));
	check($send($old) === 1, 'an old-format cookie (id|password hash|expire|sha1) is a guest');

	// API logout rotates the token: the bearer it was sent with and the web session both end
	$bearer = $api_login($tmp_name, 'first-pass');
	check($bearer !== '' && $me('Authorization: Bearer '.$bearer) === $tmp, 'an API login gives a working bearer token');
	$ch = curl_init("$base/api/index.php?route=/auth/logout");
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$bearer)));
	curl_exec($ch);
	curl_close($ch);
	check($me('Authorization: Bearer '.$bearer) === 1, 'after API logout the old bearer token is a guest');
	check($send($parts) === 1, 'and so is the web cookie from before');
	check($row($tmp)['login_token'] !== $user['login_token'], 'the login token was replaced');

	// web logout rotates too: the logout link ends the API token and the other browser
	$jars[] = $a = $web_login($tmp_name, 'first-pass');
	$jars[] = $b = $web_login($tmp_name, 'first-pass');
	$bearer = $api_login($tmp_name, 'first-pass');
	check($me($a) === $tmp && $me($b) === $tmp && $me('Authorization: Bearer '.$bearer) === $tmp, 'two browsers and an API token are signed in');
	preg_match('#login\.php\?action=out&amp;id='.$tmp.'&amp;csrf_token=([0-9a-f]{40})#', req("$base/index.php", null, $a), $m);
	req("$base/login.php?action=out&id=$tmp&csrf_token=".($m[1] ?? ''), null, $a);
	check($me($a) === 1, 'web logout signs out this browser');
	check($me($b) === 1 && $me('Authorization: Bearer '.$bearer) === 1, 'and the other browser and the API token');

	// a password change keeps this session and ends the others
	$jars[] = $a = $web_login($tmp_name, 'first-pass');
	$jars[] = $b = $web_login($tmp_name, 'first-pass');
	$bearer = $api_login($tmp_name, 'first-pass');
	$form_post("$base/profile.php?action=change_pass&id=$tmp", array('form_sent' => '1', 'req_old_password' => 'first-pass', 'req_new_password1' => 'second-pass', 'req_new_password2' => 'second-pass'), $a);
	check($row($tmp)['password'] === forum_hash('second-pass', $tmp_salt), 'the password was changed');
	check($me($a) === $tmp, 'the session that changed it stays signed in');
	check($me($b) === 1 && $me('Authorization: Bearer '.$bearer) === 1, 'the other session and the API token are signed out');

	// activate_key holds either kind of key; each flow takes only its own
	$now = time();
	$q("UPDATE {$db_prefix}users SET activate_key='rk123456', activate_string=NULL, last_email_sent=$now WHERE id=$tmp");
	$out = req("$base/profile.php?action=change_email&id=$tmp&key=rk123456", null, $a);
	$user = $row($tmp);
	check(strpos($out, 'email activation key was incorrect') !== false && $user['email'] === 'logintest@example.com' && $user['activate_key'] === 'rk123456', 'a forgotten-password key does not confirm an e-mail change');
	$guest = tempnam(sys_get_temp_dir(), 'lt');
	$jars[] = $guest;
	check(strpos(req("$base/profile.php?action=change_pass&id=$tmp&key=rk123456", null, $guest), 'req_new_password1') !== false, 'control: the same key opens the new-password form');

	$q("UPDATE {$db_prefix}users SET activate_key='ek123456', activate_string='changed@example.com' WHERE id=$tmp");
	$out = req("$base/profile.php?action=change_pass&id=$tmp&key=ek123456", null, $guest);
	check(strpos($out, 'password activation key was incorrect') !== false && strpos($out, 'req_new_password1') === false, 'an e-mail change key does not open the new-password form');
	req("$base/profile.php?action=change_pass&id=$tmp&key=ek123456", array('form_sent' => '1', 'req_new_password1' => 'evil-pass', 'req_new_password2' => 'evil-pass'), $guest);
	check($row($tmp)['password'] === forum_hash('second-pass', $tmp_salt), 'nor can it set a password');
	req("$base/profile.php?action=change_email&id=$tmp&key=ek123456", null, $a);
	check($row($tmp)['email'] === 'changed@example.com', 'control: the e-mail change key confirms the change');
}
finally
{
	$q("DELETE FROM {$db_prefix}users WHERE id=$tmp");
	$q("DELETE FROM {$db_prefix}online WHERE user_id=$tmp");
	array_map('unlink', $jars);
}

@unlink($throttle);
@unlink($jar);
echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
