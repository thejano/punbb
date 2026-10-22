<?php
if (PHP_SAPI !== 'cli') exit;
// login.php: five wrong passwords for one account slow it to one try a minute (accent and case variants share the count);
// another account still logs in; 100 failures from one address never block a correct password; an off-site redirect_url
// falls back to the forum.
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

// 100 failures from one address (a proxy or NAT) for made-up names: a new name then gets one try a minute, the right password still works
for ($i = 1; $i <= 100; $i++)
	$fresh_login("$base/index.php", 'ghost'.$i.'-'.time(), 'wrong-password');
$ghost = 'ghost-new-'.time();
check(strpos($fresh_login("$base/index.php", $ghost, 'wrong-password'), 'Incorrect username') !== false, 'past the address limit a new name still gets one try');
check(strpos($fresh_login("$base/index.php", $ghost, 'wrong-password'), 'Too many failed logins') !== false, 'and then waits a minute');
check($ok_login('admin', 'secret123'), 'past the address limit an account without failures logs in');

foreach (array('http://evil.example/x', '//evil.example/x', $base.'.evil.example/x') as $to)
	check(preg_match('#^Location: (.*)$#mi', $fresh_login($to), $loc) && strpos(trim($loc[1]), $base.'/') === 0, "redirect_url $to stays on the forum");

@unlink($throttle);
@unlink($jar);
echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
