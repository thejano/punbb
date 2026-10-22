<?php
if (PHP_SAPI !== 'cli') exit;
// Login throttle, in process with a scratch cache folder: failures on address A never delay the same account on address B; A
// gets refused after 100 failures; one address spraying made-up names stays within a bounded number of entries; the file is
// capped at 5000 entries. Over HTTP: five wrong passwords for one (account, address) pair slow it to one try a minute (accent
// and case variants share the count); another account still logs in; an off-site redirect_url falls back to the forum;
// extern.php Basic auth is throttled; 100 failures from one address give 429 for that address.
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
require FORUM_ROOT.'include/utf8/utf8.php';
require FORUM_ROOT.'include/functions.php';
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
@unlink(FORUM_CACHE_DIR.'throttle.json');
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
@unlink($jar);
echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
