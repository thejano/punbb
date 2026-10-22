<?php

// Runs before common.php so cookie_login() sees the bearer token as the session cookie.
function api_bearer_to_cookie(): void
{
	global $api_auth_is_bearer;

	// Header only: tokens in URLs leak into logs and would let a crafted link switch a browser's session.
	$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
	if (!preg_match('/^Bearer\s+(\S+)/i', $header, $m))
		return;
	$token = $m[1];

	$name = 'forum_cookie';
	if (preg_match('/^\s*\$cookie_name\s*=\s*([\'"])(.*?)\1/m', (string)@file_get_contents(FORUM_ROOT.'config.php'), $m))
		$name = $m[2];

	$_COOKIE[$name] = $token;
	$api_auth_is_bearer = true;
}

// Same format as login.php.
function api_token(int $user_id, string $password_hash, string $salt, int $expire): string
{
	return base64_encode($user_id.'|'.$password_hash.'|'.$expire.'|'.sha1($salt.$password_hash.forum_hash($expire, $salt)));
}

function api_require_user(): array
{
	global $forum_user;

	if ($forum_user['is_guest'])
		api_error('unauthenticated', 'Login required', 401);

	return $forum_user;
}

function api_csrf_token(): string
{
	return generate_form_token('api');
}

// Runs on every request, after common.php: the board-wide permissions core pages check on entry.
function api_access_gate(string $path): void
{
	global $forum_user, $forum_config;

	// Administrators must still be able to log in.
	if ($forum_config['o_maintenance'] && $forum_user['g_id'] > FORUM_ADMIN && $path !== '/auth/login' && $path !== '/config')
		api_error('maintenance', forum_trim(preg_replace('/\s+/', ' ', preg_replace('/<[^>]*>/', ' ', (string)$forum_config['o_maintenance_message']))), 503);

	if (preg_match('#^/(auth/|config$|me$)#', $path))
		return;

	if ($forum_user['g_read_board'] != '1')
		api_error('forbidden', 'You do not have permission to view this board', 403);

	if (preg_match('#^/users(?:/(\d+))?$#', $path, $m) && $forum_user['g_view_users'] == '0' && ($forum_user['is_guest'] || !isset($m[1]) || $forum_user['id'] != $m[1]))
		api_error('forbidden', 'You do not have permission to view users', 403);

	if ($path === '/search' && $forum_user['g_search'] == '0')
		api_error('forbidden', 'You do not have permission to use search', 403);
}

function api_check_csrf(): void
{
	global $api_auth_is_bearer;

	if (!empty($api_auth_is_bearer))
		return;

	if (!hash_equals(api_csrf_token(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))
		api_error('csrf', 'Invalid CSRF token', 403);
}

// Login and registration change who the browser is signed in as, so a cross-site form must not reach them: JSON bodies need a
// CORS preflight, other bodies need the caller's X-CSRF-Token (from GET /me or the page's meta tag).
function api_check_json_or_csrf(): void
{
	if (preg_match('#^application/json\s*(;|$)#i', (string)($_SERVER['CONTENT_TYPE'] ?? '')))
		return;

	if (!hash_equals(api_csrf_token(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))
		api_error('unsupported_media_type', 'Send a JSON body (Content-Type: application/json) or an X-CSRF-Token header', 415);
}

// At most $limit hits per $window seconds in $bucket, else 429 with Retry-After; $record false only checks. Hits are expiry
// times in cache/api_throttle.json, pruned on every write. Fails open when the cache folder is not writable.
function api_throttle(string $bucket, int $limit, int $window, bool $record = true): void
{
	$file = FORUM_CACHE_DIR.'api_throttle.json';
	$new = !file_exists($file);
	$fh = @fopen($file, 'c+');
	if ($fh === false)
		return;
	if ($new)
		@chmod($file, 0600);
	flock($fh, LOCK_EX);

	$now = time();
	$all = json_decode((string)stream_get_contents($fh), true);
	$all = is_array($all) ? $all : array();
	$key = sha1($bucket);
	$hits = array_values(array_filter((array)($all[$key] ?? array()), fn($t) => is_int($t) && $t > $now));

	if (count($hits) >= $limit)
	{
		flock($fh, LOCK_UN);
		fclose($fh);
		header('Retry-After: '.max(1, min($hits) - $now));
		api_error('too_many_attempts', 'Too many attempts, try again later', 429);
	}

	if ($record)
	{
		$hits[] = $now + $window;
		$all[$key] = $hits;
		foreach ($all as $k => $v)
			if (!is_array($v) || !array_filter($v, fn($t) => is_int($t) && $t > $now))
				unset($all[$k]);
		ftruncate($fh, 0);
		rewind($fh);
		fwrite($fh, json_encode($all));
	}

	flock($fh, LOCK_UN);
	fclose($fh);
}
