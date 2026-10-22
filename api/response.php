<?php

define('API_BASE_PATH', '/api/v1');

function api_json(mixed $data, int $status = 200, ?array $meta = null): never
{
	global $forum_db;

	// Commit, as footer.php does for pages; api_error exits without committing, so failed requests roll back.
	if (isset($forum_db))
		$forum_db->end_transaction();

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

// A body string; arrays and other non-scalars read as ''.
function api_str(array $in, string $key): string
{
	return isset($in[$key]) && is_scalar($in[$key]) ? (string)$in[$key] : '';
}

function api_page(int $total, int $per_page): array
{
	$pages = max(1, (int)ceil($total / max(1, $per_page)));
	$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
	return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per_page];
}

function api_user_public(array $u): array
{
	global $forum_config, $forum_user;

	$out = [
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
	if ($forum_config['o_show_post_count'] != '1' && empty($forum_user['is_admmod']))
		unset($out['num_posts']);
	return $out;
}

// $topic is an api_topic_or_404() row; it also serves as the forum row (moderators).
function api_post_shape(array $row, array $topic): array
{
	return [
		'id' => (int)$row['id'],
		'topic_id' => (int)$row['topic_id'],
		'poster' => api_user_public(['id' => $row['poster_id']] + $row),
		'message' => $row['message'],
		'message_html' => parse_message($row['message'], (int)$row['hide_smilies']),
		'hide_smilies' => $row['hide_smilies'] == '1',
		'posted' => (int)$row['posted'],
		'posted_formatted' => format_time((int)$row['posted']),
		'edited' => $row['edited'] === null ? null : (int)$row['edited'],
		'edited_by' => $row['edited_by'],
		'is_online' => $row['is_online'] !== null,
		'can_edit' => api_can_edit_post($row, $topic, $topic),
		'can_delete' => api_can_delete_post($row, $topic, $topic),
	];
}
