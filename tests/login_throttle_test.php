<?php
if (PHP_SAPI !== 'cli') exit;
// login.php: five wrong passwords for one username lock that username for the address; another account still logs in.
//   FORUM_BASE=http://127.0.0.1:8085 php tests/login_throttle_test.php   (local board only: it clears cache/throttle.json)

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

$login = fn(string $user, string $pass) => req("$base/login.php", array('form_sent' => '1', 'req_username' => $user, 'req_password' => $pass,
	'redirect_url' => "$base/index.php", 'csrf_token' => $token), $jar);

for ($i = 1; $i <= 5; $i++)
	$body = $login('apitest', 'wrong-password');
check(strpos($body, 'Incorrect username') !== false, 'fifth wrong password is still answered normally');
check(strpos($login('apitest', 'pass1234'), 'Too many failed logins') !== false, 'sixth try is refused, even with the right password');
check(stripos($login('admin', 'secret123'), 'Location:') !== false, 'another username from the same address still logs in');

@unlink($throttle);
@unlink($jar);
echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
