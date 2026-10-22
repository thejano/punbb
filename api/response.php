<?php

define('API_BASE_PATH', '/api/v1');

function api_json(mixed $data, int $status = 200, ?array $meta = null): never
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	$out = ['data' => $data];
	if ($meta !== null)
		$out['meta'] = $meta;
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function api_error(string $code, string $message, int $status, array $fields = []): never
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['error' => ['code' => $code, 'message' => $message, 'fields' => (object)$fields]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function api_input(): array
{
	$type = $_SERVER['CONTENT_TYPE'] ?? '';
	if (stripos($type, 'application/x-www-form-urlencoded') !== false || stripos($type, 'multipart/form-data') !== false)
		return $_POST;

	$body = json_decode((string)file_get_contents('php://input'), true);
	return is_array($body) ? $body : [];
}

function api_page(int $total, int $per_page): array
{
	$pages = max(1, (int)ceil($total / max(1, $per_page)));
	$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
	return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per_page];
}

function api_user_public(array $u): array
{
	global $forum_config;

	return [
		'id' => (int)$u['id'],
		'username' => $u['username'],
		'title' => get_title($u),
		'num_posts' => (int)($u['num_posts'] ?? 0),
		'registered' => (int)($u['registered'] ?? 0),
		'registered_formatted' => format_time((int)($u['registered'] ?? 0), 1),
		'avatar_html' => $forum_config['o_avatars'] == '1' ? generate_avatar_markup((int)$u['id'], $u['avatar'] ?? '', $u['avatar_width'] ?? '', $u['avatar_height'] ?? '', $u['username'] ?? '') : '',
		'url' => $u['url'] ?? '',
		'location' => $u['location'] ?? '',
	];
}
