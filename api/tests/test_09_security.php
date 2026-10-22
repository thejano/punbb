<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$web = preg_replace('#/api/.*$#', '', getenv('API_BASE') ?: 'http://127.0.0.1:8085/api/v1');
$get = function (string $url) { $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]); $b = (string)curl_exec($ch); $s = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch); return [$s, $b]; };

// C1: test scripts and the API config are not served
foreach (['api/tests/fixture.php?x+unflood+apitest', 'api/tests/run.php', 'api/tests/lib.php', 'api/tests/test_01_misc.php', 'api/config.php'] as $path)
	check($get($web.'/'.$path)[0] === 404, $path.' is 404 over the web');

// M1: cross-site form or text/plain bodies cannot log in or register; a form with the guest's X-CSRF-Token can
foreach (['/auth/login', '/auth/register'] as $path)
{
	$r = api_call('POST', $path, 'username=admin&password=secret123&email=x%40example.com', ['Content-Type: application/x-www-form-urlencoded', 'Origin: https://evil.example']);
	check($r['status'] === 415 && ($r['json']['error']['code'] ?? '') === 'unsupported_media_type' && stripos($r['headers'], 'Set-Cookie: forum_cookie') === false, 'form-encoded '.$path.' without token is 415');
}
$r = api_call('POST', '/auth/login', '{"username":"admin","password":"secret123"}', ['Content-Type: text/plain']);
check($r['status'] === 415, 'text/plain JSON login is 415');
$guest_csrf = api_call('GET', '/me')['json']['data']['csrf_token'] ?? '';
$r = api_call('POST', '/auth/login', 'username=admin&password=secret123', ['Content-Type: application/x-www-form-urlencoded', 'X-CSRF-Token: '.$guest_csrf]);
check($r['status'] === 200 && !empty($r['json']['data']['token']), 'form-encoded login with the guest X-CSRF-Token works');

// M2: bearer responses do not echo the token in Set-Cookie
$token = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'])['json']['data']['token'] ?? '';
$r = api_call('GET', '/me', null, ['Authorization: Bearer '.$token]);
check(($r['json']['data']['id'] ?? 0) === 2 && stripos($r['headers'], 'Set-Cookie') === false, 'bearer /me sends no Set-Cookie');

// L3: nosniff on API responses, errors included
check(stripos(api_call('GET', '/config')['headers'], 'X-Content-Type-Options: nosniff') !== false, 'API response has nosniff');
check(stripos(api_call('GET', '/nope')['headers'], 'X-Content-Type-Options: nosniff') !== false, 'API error has nosniff');

// L1: last_visit only for self, admins and moderators
check(!array_key_exists('last_visit', api_call('GET', '/users/2')['json']['data'] ?? ['last_visit' => 1]), 'guest /users/2 has no last_visit');
check(isset(api_call('GET', '/users/2', null, ['Authorization: Bearer '.$token])['json']['data']['last_visit']), 'admin /users/2 has last_visit');

// M3: five failures for one username, then 429 with Retry-After, even for the right password
$name = 'nobody'.time();
for ($i = 0; $i < 5; $i++)
	$r = api_call('POST', '/auth/login', ['username' => $name, 'password' => 'wrong']);
check($r['status'] === 401, 'fifth wrong password is still 401');
$r = api_call('POST', '/auth/login', ['username' => strtoupper($name), 'password' => 'wrong']);
check($r['status'] === 429 && ($r['json']['error']['code'] ?? '') === 'too_many_attempts' && preg_match('/^Retry-After: [1-9]\d*/mi', $r['headers']), 'sixth wrong password is 429 too_many_attempts with Retry-After');
