# PanBB 2 developer guide

This guide covers three things: how the forum core is put together and how to call it from your own code, how the JSON API works, and how the LightOxygen theme is built. The user-facing README stays in `README.md`; the full endpoint reference is in `api/README.md`.

## Repository layout

| Path | What it is |
|---|---|
| `*.php` in the root | The page scripts (index, viewforum, viewtopic, post, edit, profile, search, login, register, ...). Each one bootstraps the core, runs its queries, and prints its HTML into the theme's template. |
| `admin/` | Admin pages plus `install.php` and `db_update.php`. |
| `include/common.php` | The core bootstrap. Defining `FORUM_ROOT` and requiring this file gives you the database layer, the configuration, the current user and every helper. |
| `include/functions.php` | Most helpers: users, topics, posts, permissions, links, formatting, errors. |
| `include/parser.php` | BBCode: `preparse_bbcode()` normalises input, `parse_message()` renders HTML. |
| `include/search_idx.php` | Search index maintenance. |
| `include/dblayer/` | Database layers for `mysqli`, `mysqli_innodb`, `pgsql` and `sqlite3`. All SQL goes through `$forum_db->query_build()`. |
| `include/template/` | Stock page shells (`main.tpl`, `admin.tpl`, `help.tpl`, `redirect.tpl`). A style can override any of them. |
| `lang/` | Language packs. `English` and `Kurdish` ship. |
| `style/` | Styles. `LightOxygen` is the default; `Oxygen` and `Oxygen-RTL` remain. |
| `extensions/` | PunBB 1.4 extensions. They hook into core, not into the theme. |
| `api/` | The JSON API (plain PHP, no dependencies). |
| `cache/` | Generated cache files. Must be writable. |

## Running locally

```
php -S 127.0.0.1:8080
```

Then open `http://127.0.0.1:8080/admin/install.php`. SQLite 3 needs no server: give a file name such as `forum.sqlite3` as the database name. For MySQL use the `utf8mb4` character set. The installer writes `config.php`; it is gitignored.

Requirements: PHP 8.3 or newer with `mbstring`, and one of `mysqli`, `pgsql` or `sqlite3`.

## Using the core from your own code

Every script, page or tool that touches the forum starts the same way:

```php
<?php
define('FORUM_ROOT', __DIR__.'/');   // the forum root, with a trailing slash
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/parser.php';  // only if you render or accept BBCode
```

After that you have:

- `$forum_db`, the database layer. Build queries as arrays and run them with `$forum_db->query_build($query)`. Table names are given without the prefix; the layer adds it. Escape strings with `$forum_db->escape()`. Read rows with `fetch_assoc()`, `fetch_row()`, `result()`, `num_rows()`.
- `$forum_config`, every board setting (`o_board_title`, `o_default_lang`, `o_disp_topics_default`, `o_censoring`, ...).
- `$forum_user`, the current user (guest or cookie-authenticated), including the group permission flags: `g_read_board`, `g_view_users`, `g_search`, `g_post_topics`, `g_post_replies`, `g_edit_posts`, `g_delete_posts`, `g_delete_topics`, `g_post_flood`, plus `is_guest` and `is_admmod`.
- `$lang_common`, and any other pack you require from `lang/<language>/`.

Example: a readable-forums query, the same permission join the pages use.

```php
$query = array(
	'SELECT'	=> 'f.id, f.forum_name',
	'FROM'		=> 'forums AS f',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'	=> 'forum_perms AS fp',
			'ON'		=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1)',
	'ORDER BY'	=> 'f.disp_position'
);
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
while ($cur_forum = $forum_db->fetch_assoc($result))
	echo forum_htmlencode($cur_forum['forum_name']);
```

### Functions you will call most

| Function | File | Use |
|---|---|---|
| `add_topic($post_info, &$new_tid, &$new_pid)` | `include/functions.php` | Create a topic with its first post. Updates counters, subscriptions and the search index. `$post_info` keys: `is_guest, poster, poster_id, poster_email, subject, message, hide_smilies, posted, subscribe, forum_id, forum_name, update_user, update_unread`. |
| `add_post($post_info, &$new_pid)` | `include/functions.php` | Reply to a topic. Keys: `is_guest, poster, poster_id, poster_email, subject, message, hide_smilies, posted, subscr_action, topic_id, forum_id, update_user, update_unread`. |
| `delete_post($post_id, $topic_id, $forum_id)`, `delete_topic($topic_id, $forum_id)` | `include/functions.php` | Delete and re-sync counters and the search index. |
| `add_user($user_info, &$new_uid)` | `include/functions.php` | Register a user. See `register.php` for the keys. |
| `preparse_bbcode($text, &$errors)` | `include/parser.php` | Validate and normalise BBCode before storing it. `$errors` receives language strings from `lang/<language>/post.php`, so require that pack first. |
| `parse_message($text, $hide_smilies)` | `include/parser.php` | Render stored BBCode to safe HTML. |
| `update_search_index($mode, $post_id, $message, $subject)` | `include/search_idx.php` | `'post'` or `'edit'`. `add_post`/`add_topic` call it for you. |
| `forum_link($forum_url['topic'], $id)` | `include/functions.php` | Build URLs so they follow the configured URL scheme. Keys are in `include/url/Default/forum_urls.php`. |
| `format_time($timestamp)` | `include/functions.php` | Dates in the current user's time zone and format. |
| `forum_htmlencode($str)` | `include/functions.php` | Escape anything you print. |
| `censor_words($str)` | `include/functions.php` | Apply the board's censor list when `o_censoring` is on. |
| `generate_form_token($target)` | `include/functions.php` | CSRF tokens bound to a target. Forms post to the exact URL the token was generated for. |
| `get_title($user)`, `generate_avatar_markup(...)` | `include/functions.php` | User title and avatar HTML. |
| `message($text)`, `error(__FILE__, __LINE__)`, `redirect($url, $text)` | `include/functions.php` | End the request with a message page, an error page, or a redirect. Under the API (`FORUM_PAGE === 'api'`) `message()` and `error()` return JSON instead. |
| `get_hook('hook_id')` | `include/functions.php` | Extension hook points. Extensions, not themes, use these. |

### Rules that live in the page scripts

Who may post, the flood limit, subject and message limits, and the registration checks are written inline in `post.php`, `edit.php`, `delete.php` and `register.php`, not in shared functions. The API mirrors them in `api/rules.php`, and each rule there carries a comment naming the page lines it copies. If you change a rule in a page, change it in `api/rules.php` too.

### Permissions in one paragraph

Board access is `g_read_board`. Forum access is the `forum_perms` row for the user's group (`read_forum`, `post_topics`, `post_replies`), with `NULL` meaning "use the group default" (`g_post_topics`, `g_post_replies`). Moderators are the admin group plus the usernames serialised in `forums.moderators`. Closed topics accept posts, edits and deletes from moderators only.

## The JSON API

The API lives in `api/` and is documented endpoint by endpoint in `api/README.md`. The short version:

- **Routing.** `/api/v1/<path>` through `api/.htaccess` on Apache, or `api/index.php?route=/<path>` anywhere (including the PHP built-in server).
- **Bootstrap.** `api/index.php` answers CORS preflights, maps a bearer token onto the forum cookie, then requires `include/common.php`, so the API sees exactly the user, permissions and bans the pages see.
- **Authentication.** Either the forum's own cookie (browser clients) with an `X-CSRF-Token` header, or `Authorization: Bearer <token>` from `POST /auth/login`. The token is the forum's cookie value, so core validates it. Browser clients read their CSRF token from `GET /me` (`csrf_token`) or from the theme's `<meta name="api-csrf">`.
- **Shapes.** `{ "data": ... }` for one item, `{ "data": [...], "meta": { "page", "pages", "total" } }` for lists, `{ "error": { "code", "message", "fields" } }` on failure with 400, 401, 403, 404, 405, 422, 429, 500 or 503.
- **Writes** go through the core functions above, so bans, permissions, censoring, flood limits, subscriptions and the search index all apply.
- **Endpoints.** config, me, online; auth login/logout/register; forums, forum topics, create topic; topic, topic posts, recent topics; post, reply, edit, delete, preview; search; users.

Example, from a terminal:

```
TOKEN=$(curl -s -X POST http://127.0.0.1:8080/api/index.php?route=/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"secret"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')

curl -s "http://127.0.0.1:8080/api/index.php?route=/topics/recent&limit=5" \
  -H "Authorization: Bearer $TOKEN"
```

### Adding an endpoint

1. Add a route file under `api/routes/` or extend one: `api_route('GET', '/things/{id}', function ($p) { ... });`.
2. Read input with `api_input()` and `api_str($in, 'field')`, check access with `api_require_user()` and `api_check_csrf()` for writes, resolve records with `api_forum_or_404()` / `api_topic_or_404()`.
3. Return with `api_json($data)` (commits the transaction) or `api_error('code', 'message', 422, $fields)`.
4. Add a test file `api/tests/test_NN_name.php` and run the suite.

### Running the API tests

```
API_BASE=http://127.0.0.1:8080/api/index.php php api/tests/run.php
```

The suite creates an `apitest` user and a few topics in the database it runs against, so use a development database. It passes on MySQL 8, PostgreSQL 17 and SQLite 3.

## The LightOxygen theme

LightOxygen is a normal PunBB style folder, `style/LightOxygen/`, rendered server-side by the page scripts. Tailwind 4 and React 19 are built with Vite; the built files in `dist/` are committed, so a server never needs Node.

### Building

```
cd style/LightOxygen
npm install
npm run dev     # hot reload; writes dist/hot so the theme loads from the dev server
npm run build   # production build into dist/ with a manifest
```

### How it hooks into the forum

`header.php` loads `style/LightOxygen/main.tpl` (or `admin.tpl`, `help.tpl`, `redirect.tpl`) and then requires `LightOxygen.php`. That file echoes the asset tags into `<head>` and replaces the theme's own placeholders in the template (`<!-- lo_nav -->`, `<!-- lo_auth -->`, `<!-- lo_search -->`, `<!-- lo_sidebar -->`, `<!-- lo_meta -->`, `<!-- lo_activity -->`, `<!-- lo_body_class -->`, `<!-- lo_year -->`, `{{BASE_URL}}`, `{{THEME_URL}}`) using the helpers in `functions.php`. Every stock placeholder is kept, so core and extensions keep working. Core prints the page body into `<!-- forum_main -->`; the theme restyles that markup through its existing classes in `src/css/*.css`.

### Layout, direction and colours

- Two layouts, **sidebar** (default; content in the main column, categories, filters, activity and stats in a sidebar on the end side) and **wide** (single column), and two looks, **calm** (default) and **meko** (the look of the webchin.org/Meko board: blue header, hero for everyone, cards on a grey page). A visitor picks both from the "View" menu in the header; the choices are cookies, `lo_layout` and `lo_skin`, applied as body classes on the server, so they work without JavaScript. The redirects behind the switches are built from `$base_url` and the script name, never from the request URI.
- Pinned topics are grouped under a "Pinned" label inside a forum; closed, moved and unread topics carry markers. Users without an avatar get a default one everywhere a person is shown.
- One stylesheet serves Kurdish (`dir="rtl"`) and English (`dir="ltr"`) through logical CSS properties. Do not add `left`/`right` rules; use `start`/`end`.
- Colours are tokens in the `@theme` block of `src/main.css` (the LightWeb palette: primary `#2f5f8a` over neutral greys). Dark mode follows the system preference through the same tokens.

### Board settings (extension)

The theme reads its options through `lo_setting($key, $default)` from `$forum_config`, with code defaults, so it works without any extension. The bundled extension `extensions/lightoxygen_settings` installs the options and adds a section to Administration, Settings: hero (who sees it, title, tagline, background image upload or URL), board defaults for layout and look, whether visitors may switch, colour scheme (auto/light/dark), logo and favicon uploads, accent colour, font choice, activity-panel refresh, member avatar row, category colours, stats block, footer text, social links and a custom CSS box. Install it from Administration, Extensions. Uploads land in `img/lightoxygen/`.

### React islands

`src/main.jsx` mounts a component on every element with `data-island="<name>"`, passing `data-props` as JSON. Each island has server-rendered fallback content inside the element. Islands: `activity-panel` (recent topics and who is online, refreshed from the API), `search-box` (typeahead suggestions on the search form), and the quick-reply enhancer (BBCode toolbar and live preview through `POST /preview`). `src/api.js` reads the API base and CSRF token from the `<meta>` tags.

### Theme checks

```
sh style/LightOxygen/test/check.sh
```

The script crawls the pages as guest and as admin and asserts the theme's structure and the absence of PHP warnings. Screenshots taken during development live in `test/shots/` and are not committed.
