<?php
// Local runs on the PHP built-in server (no .htaccess):
//   API_BASE=http://127.0.0.1:8086/api/index.php php api/tests/run.php   (uses ?route=<path>)
// Behind Apache with api/.htaccess:
//   API_BASE=http://host/api/v1 php api/tests/run.php

$GLOBALS['api_failures'] = 0;

// $body: array (sent as JSON), string (sent raw, pass its Content-Type in $headers) or null. $jar: cookie jar file.
function api_call(string $method, string $path, array|string|null $body = null, array $headers = [], ?string $jar = null): array
{
	$base = getenv('API_BASE') ?: 'http://127.0.0.1:8085/api/v1';
	$url = strpos($base, 'index.php') !== false ? $base.'?route='.str_replace('?', '&', $path) : $base.$path;

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => array_merge(is_array($body) ? ['Content-Type: application/json'] : [], $headers),
	]);
	if ($jar !== null)
		curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
	if ($body !== null)
		curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
	$raw = (string)curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);

	$json = json_decode($raw, true);
	return ['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => $raw];
}

function check(bool $ok, string $label): void
{
	if (!$ok)
		$GLOBALS['api_failures']++;
	echo ($ok ? 'ok   ' : 'FAIL ').$label."\n";
}
