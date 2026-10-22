<?php

define('FORUM_ROOT', '../');
define('FORUM_REQUEST_AJAX', 1);
define('FORUM_PAGE', 'api');

require __DIR__.'/response.php';
require __DIR__.'/router.php';
require __DIR__.'/auth.php';

$api_config = require __DIR__.'/config.php';

if (function_exists('api_bearer_to_cookie'))
	api_bearer_to_cookie();

require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/parser.php';

foreach (glob(__DIR__.'/routes/*.php') as $api_route_file)
	require $api_route_file;

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

try
{
	api_dispatch($_SERVER['REQUEST_METHOD'], $_GET['route'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}
catch (Throwable $e)
{
	api_error('internal', $e->getMessage(), 500);
}
