<?php

define('FORUM_ROOT', '../');
define('FORUM_REQUEST_AJAX', 1);
define('FORUM_PAGE', 'api');
// Form-encoded bodies carry no csrf_token; the API checks X-CSRF-Token itself.
define('FORUM_SKIP_CSRF_CONFIRM', 1);
// common.php would answer maintenance mode with an HTML page; it is reported below as JSON.
define('FORUM_TURN_OFF_MAINT', 1);
// Reads (polling clients) must not keep a member online or hold back last_visit. GET /me still counts as a visit: it hands out
// the CSRF token, which comes from the online row.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !preg_match('#/me/?$#', (string)($_GET['route'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))))
	define('FORUM_QUIET_VISIT', 1);

// JSON bodies carry HTML (message_html, avatar_html); browsers must not sniff them as a page.
header('X-Content-Type-Options: nosniff');

require __DIR__.'/response.php';
require __DIR__.'/router.php';
require __DIR__.'/auth.php';

$api_config = require __DIR__.'/config.php';

if (function_exists('api_bearer_to_cookie'))
	api_bearer_to_cookie();

if ($api_config['cors_origin'] !== '')
{
	header('Access-Control-Allow-Origin: '.$api_config['cors_origin']);
	header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
	http_response_code(204);
	exit;
}

require FORUM_ROOT.'include/common.php';
// cookie_login() refreshes the forum cookie, which for a bearer request would echo the token into response-header logs
if (!empty($api_auth_is_bearer))
	header_remove('Set-Cookie');
require FORUM_ROOT.'include/parser.php';
require __DIR__.'/rules.php';

foreach (glob(__DIR__.'/routes/*.php') as $api_route_file)
	require $api_route_file;

try
{
	api_dispatch($_SERVER['REQUEST_METHOD'], $_GET['route'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}
catch (Throwable $e)
{
	error_log('API exception: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
	api_error('internal', defined('FORUM_DEBUG') ? $e->getMessage() : 'Internal error', 500);
}
