<?php

// Runs before common.php so cookie_login() sees the bearer token as the session cookie.
function api_bearer_to_cookie(): void
{
	global $api_auth_is_bearer;

	$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
	if (preg_match('/^Bearer\s+(\S+)/i', $header, $m))
		$token = $m[1];
	else
		$token = (string)($_GET['token'] ?? '');

	if ($token === '')
		return;

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

function api_check_csrf(): void
{
	global $api_auth_is_bearer;

	if (!empty($api_auth_is_bearer))
		return;

	if (!hash_equals(api_csrf_token(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))
		api_error('csrf', 'Invalid CSRF token', 403);
}
