<?php
$fixture = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixture.php');
// ban and maint rewrite cache files; a server with opcache revalidates them only every 2 seconds.
$cached = function (string $args) use ($fixture) { shell_exec($fixture.' '.$args); sleep(3); };
$token = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'])['json']['data']['token'] ?? '';
$auth = ['Authorization: Bearer '.$token];
$utoken = api_call('POST', '/auth/login', ['username' => 'apitest', 'password' => 'pass1234'])['json']['data']['token'] ?? '';
$uauth = ['Authorization: Bearer '.$utoken];

// Board-wide read gate (guest group is 2)
try
{
	shell_exec($fixture.' readboard 2 0');
	$r = api_call('GET', '/forums');
	check($r['status'] === 403 && ($r['json']['error']['code'] ?? '') === 'forbidden', 'guest /forums 403 when board unreadable');
	check(api_call('GET', '/config')['status'] === 200, '/config still 200 when board unreadable');
	check(api_call('GET', '/me')['status'] === 200, '/me still 200 when board unreadable');
	check(api_call('GET', '/search?q=body')['status'] === 403, 'guest /search 403 when board unreadable');
}
finally
{
	shell_exec($fixture.' readboard 2 1');
}
check(api_call('GET', '/forums')['status'] === 200, '/forums readable again');

// Form-encoded POST: no core CSRF confirm page, no password echo
$r = api_call('POST', '/auth/login', 'username=admin&password=secret123', ['Content-Type: application/x-www-form-urlencoded']);
check($r['status'] === 200 && !empty($r['json']['data']['token']) && strpos($r['raw'], 'secret123') === false, 'form-encoded login works and does not echo the password');

// Bans and maintenance use the API error format
if ($utoken !== '')
{
	try
	{
		$cached('ban apitest');
		$r = api_call('GET', '/me', null, $uauth);
		check($r['status'] === 403 && ($r['json']['error']['code'] ?? '') === 'forbidden' && is_object((object)($r['json']['error']['fields'] ?? null)) && strpos($r['json']['error']['message'] ?? '<', '<') === false, 'banned user gets 403 forbidden JSON');
	}
	finally
	{
		$cached('unban apitest');
	}
	check(($r = api_call('GET', '/me', null, $uauth)) && ($r['json']['data']['is_guest'] ?? true) === false, 'unbanned user is back');
}
try
{
	$cached('maint 1');
	$r = api_call('GET', '/forums');
	check($r['status'] === 503 && ($r['json']['error']['code'] ?? '') === 'maintenance', 'maintenance gives 503 JSON for guests');
	check(api_call('GET', '/config')['status'] === 200, '/config answers in maintenance');
	check(api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'])['status'] === 200, 'admin can log in in maintenance');
	check(api_call('GET', '/forums', null, $auth)['status'] === 200, 'admin reads in maintenance');
}
finally
{
	$cached('maint 0');
}

// Delete rule: closed topic blocks the author
if ($utoken !== '')
{
	$tid = api_call('POST', '/forums/1/topics', ['subject' => 'Closed delete '.time(), 'message' => 'Body'], $auth)['json']['data']['topic_id'] ?? 0;
	shell_exec($fixture.' unflood apitest');
	$pid = api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Mine'], $uauth)['json']['data']['post_id'] ?? 0;
	check($pid > 0, 'closed-delete fixture reply created');
	try
	{
		shell_exec($fixture.' close '.$tid);
		check(api_call('DELETE', '/posts/'.$pid, null, $uauth)['status'] === 403, 'author cannot delete own post in closed topic');
		check(($p = api_call('GET', '/posts/'.$pid, null, $uauth)['json']['data'] ?? []) && ($p['can_delete'] ?? true) === false, 'can_delete false in closed topic');
	}
	finally
	{
		shell_exec($fixture.' open '.$tid);
	}
	check(api_call('DELETE', '/posts/'.$pid, null, $uauth)['status'] === 200, 'author deletes own post once reopened');
	api_call('DELETE', '/posts/'.(api_call('GET', '/topics/'.$tid.'/posts')['json']['data'][0]['id'] ?? 0), null, $auth);

	// PUT keeps hide_smilies when omitted
	$tid = api_call('POST', '/forums/1/topics', ['subject' => 'Smilies '.time(), 'message' => 'Body'], $auth)['json']['data']['topic_id'] ?? 0;
	$pid = api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Hidden :)', 'hide_smilies' => true], $auth)['json']['data']['post_id'] ?? 0;
	$r = api_call('PUT', '/posts/'.$pid, ['message' => 'Still hidden'], $auth);
	check(($r['json']['data']['hide_smilies'] ?? null) === true, 'PUT without hide_smilies keeps it');
	$r = api_call('PUT', '/posts/'.$pid, ['message' => 'Now shown', 'hide_smilies' => false], $auth);
	check(($r['json']['data']['hide_smilies'] ?? null) === false, 'PUT hide_smilies false clears it');
	api_call('DELETE', '/posts/'.(api_call('GET', '/topics/'.$tid.'/posts')['json']['data'][0]['id'] ?? 0), null, $auth);
}

// Tokens only via the Authorization header
$r = api_call('GET', '/me?token='.urlencode($token));
check(($r['json']['data']['is_guest'] ?? null) === true, '?token= is ignored');

// Cookie session: login sets the cookie, /me exposes the CSRF token, writes need it
$jar = tempnam(sys_get_temp_dir(), 'apijar');
api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'], [], $jar);
$r = api_call('GET', '/me', null, [], $jar);
$csrf = $r['json']['data']['csrf_token'] ?? '';
check(($r['json']['data']['is_guest'] ?? true) === false && $csrf !== '', 'cookie jar session is logged in and /me has csrf_token');
check(!isset(api_call('GET', '/me', null, $auth)['json']['data']['csrf_token']), 'bearer /me has no csrf_token');
$r = api_call('POST', '/preview', ['message' => '[b]x[/b]'], [], $jar);
check($r['status'] === 403 && ($r['json']['error']['code'] ?? '') === 'csrf', 'cookie POST /preview without header is 403 csrf');
$r = api_call('POST', '/preview', ['message' => '[b]x[/b]'], ['X-CSRF-Token: '.$csrf], $jar);
check($r['status'] === 200 && strpos($r['json']['data']['html'] ?? '', '<strong>') !== false, 'cookie POST /preview with csrf header is 200');
unlink($jar);

// Array inputs
$r = api_call('POST', '/preview', ['message' => ['a']], ['Authorization: Bearer '.$token]);
check($r['status'] === 422 && $r['json'] !== null, 'array message is 422 with valid JSON');
$r = api_call('POST', '/forums/1/topics', ['subject' => ['a'], 'message' => ['b']], $auth);
check($r['status'] === 422 && $r['json'] !== null, 'array subject/message is 422');
$r = api_call('POST', '/auth/login', ['username' => ['a'], 'password' => ['b']]);
check($r['status'] === 401 && $r['json'] !== null, 'array credentials is 401 with valid JSON');
check(api_call('GET', '/users?q[]=ab')['status'] === 400, 'array q is 400');

// Preview size cap
$r = api_call('POST', '/preview', ['message' => str_repeat('a', 70000)], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['message']), 'oversize preview is 422');
$r = api_call('POST', '/preview', ['message' => ''], $auth);
check($r['status'] === 422 && ($r['json']['error']['fields']['message'][0] ?? '') !== 'Message is empty', 'empty preview uses the board message');

// Logged-in callers cannot register
check(api_call('POST', '/auth/register', ['username' => 'another1', 'email' => 'another1@example.com', 'password' => 'pass1234'], $auth)['status'] === 403, 'register while logged in is 403');

