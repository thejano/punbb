<?php
$r = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'wrong']);
check($r['status'] === 401 && ($r['json']['error']['code'] ?? '') === 'invalid_credentials', 'login wrong password 401');

$r = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123']);
$token = $r['json']['data']['token'] ?? '';
check($r['status'] === 200 && is_string($token) && $token !== '', 'login ok returns token');
check(($r['json']['data']['user']['username'] ?? '') === 'admin' && is_int($r['json']['data']['expires'] ?? null), 'login user and expires');
check(!isset($r['json']['data']['user']['email']) && !isset($r['json']['data']['user']['password']), 'login user has no private fields');

$r = api_call('GET', '/me', null, ['Authorization: Bearer '.$token]);
check(($r['json']['data']['id'] ?? 0) === 2 && ($r['json']['data']['is_guest'] ?? null) === false, 'me with bearer is admin');

$parts = explode('|', base64_decode($token));
$parts[1] = str_repeat('a', 40);
$r = api_call('GET', '/me', null, ['Authorization: Bearer '.base64_encode(implode('|', $parts))]);
check(($r['json']['data']['is_guest'] ?? null) === true, 'me with forged token is guest');

$r = api_call('POST', '/auth/logout', [], ['Authorization: Bearer '.$token]);
check($r['status'] === 200 && ($r['json']['data']['ok'] ?? null) === true, 'logout ok');
check(api_call('POST', '/auth/logout', [])['status'] === 401, 'logout as guest 401');

$r = api_call('POST', '/auth/register', ['username' => 'admin', 'email' => 'dupe@example.com', 'password' => 'pass1234']);
check($r['status'] === 422 && isset($r['json']['error']['fields']['username']), 'register existing username 422');

$allow = api_call('GET', '/config')['json']['data']['regs_allow'] ?? false;
if (!$allow)
{
	$r = api_call('POST', '/auth/register', ['username' => 'apitest', 'email' => 'apitest@example.com', 'password' => 'pass1234']);
	check($r['status'] === 403 && ($r['json']['error']['code'] ?? '') === 'registration_closed', 'register closed 403');
}
else
{
	$r = api_call('POST', '/auth/register', ['username' => 'apitest', 'email' => 'apitest@example.com', 'password' => 'pass1234']);
	if ($r['status'] === 201)
	{
		echo "note: register branch = created\n";
		check(($r['json']['data']['user']['id'] ?? 0) > 2, 'register created user id > 2');
	}
	else
	{
		echo "note: register branch = already exists\n";
		check($r['status'] === 422 && isset($r['json']['error']['fields']['username']), 'register apitest again 422 fields.username');
	}
}

$r = api_call('POST', '/auth/register', ['username' => 'shortpw', 'email' => 'bad', 'password' => 'x']);
check($r['status'] === 422 && isset($r['json']['error']['fields']['password'], $r['json']['error']['fields']['email']), 'register bad email/password 422');

$r = api_call('POST', '/preview', ['text' => 'x']);
check(in_array($r['status'], [403, 404], true), 'cookie POST /preview without csrf is 403 (or 404 until it exists)');
