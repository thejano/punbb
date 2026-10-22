<?php

$GLOBALS['api_routes'] = [];

function api_route(string $method, string $pattern, callable $handler): void
{
	$regex = preg_replace_callback('/\{(\w+)\}/', fn($m) => '(?P<'.$m[1].'>'.($m[1] === 'id' ? '[0-9]+' : '[^/]+').')', $pattern);
	$GLOBALS['api_routes'][] = [strtoupper($method), '#^'.$regex.'$#', $handler];
}

function api_dispatch(string $method, string $path): void
{
	$method = strtoupper($method);
	if (strpos($path, API_BASE_PATH) === 0)
		$path = substr($path, strlen(API_BASE_PATH));
	$path = '/'.trim($path, '/');
	if ($path === '/')
		$path = '';

	api_access_gate($path === '' ? '/' : $path);

	$path_matched = false;
	foreach ($GLOBALS['api_routes'] as [$m, $regex, $handler])
	{
		if (!preg_match($regex, $path === '' ? '/' : $path, $caps))
			continue;
		$path_matched = true;
		if ($m !== $method)
			continue;
		$handler(array_filter($caps, 'is_string', ARRAY_FILTER_USE_KEY));
		return;
	}

	if ($path_matched)
		api_error('method_not_allowed', 'Method not allowed', 405);
	api_error('not_found', 'Not found', 404);
}
