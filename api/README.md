# PunBB JSON API

A plain-PHP JSON API over the forum core (PHP 8.3+, no Composer). It reuses the forum's own
permissions, flood control, parser and search index, and works on MySQL, PostgreSQL and SQLite3.

Examples below use `http://localhost` as the board root. Timestamps are Unix seconds; the
`*_formatted` fields use the board's time format. Example responses are shortened.

## Routing

Two equivalent forms:

- `/api/v1/<path>` with Apache and `api/.htaccess` (`mod_rewrite`; it rewrites to `index.php?route=/<path>`
  relative to the `api/` directory, so it works in a subdirectory install, and it passes the `Authorization`
  header through to PHP for PHP-FPM/CGI setups).
- `/api/index.php?route=/<path>` anywhere, including the PHP built-in server (`php -S`), which has no `.htaccess`.

Extra query parameters are passed alongside, for example `/api/v1/search?q=hello` or
`/api/index.php?route=/search&q=hello`. Examples below use the `/api/v1` form.

## Authentication

Reads are public (subject to guest permissions). Writes need a logged-in user plus one of:

1. **Bearer token.** `POST /auth/login` returns a `token`; send `Authorization: Bearer <token>`.
   The header is the only way: a `?token=` query parameter is ignored. Bearer requests need no CSRF header,
   and their responses carry no `Set-Cookie`. The token is the forum's cookie value: it holds the account's
   password hash and stays valid until it expires (the board's visit timeout, or 14 days with `remember`) or the
   password changes, which invalidates every token for that account. `POST /auth/logout` does not revoke it.
   Treat it as a password: mobile and desktop clients should keep it in the platform's secure storage
   (Keychain, Android Keystore), never in logs or URLs.
2. **Session cookie** (same-site browser pages). `login` also sets the forum cookie. Every
   cookie-authenticated write must send `X-CSRF-Token` with the value from the page's
   `<meta name="api-csrf" content="...">` (the token is `generate_form_token('api')`), or from `GET /me`, whose
   `csrf_token` field is present for cookie-authenticated callers only (never for bearer). A wrong or
   missing header gives `403 csrf`. The `/preview` endpoint also requires it for guests.

`POST /auth/login` and `POST /auth/register` accept a body only as `Content-Type: application/json` (which a
cross-site page cannot send without a CORS preflight) or together with the caller's `X-CSRF-Token` (a guest gets
one from `GET /me`). Anything else, such as a plain HTML form posted from another site, gets `415
unsupported_media_type`, so another site cannot sign a visitor in to its own account.

### Rate limits

Counted per client address in `cache/throttle.json`; over the limit the answer is `429 too_many_attempts`
with a `Retry-After` header (seconds).

| Endpoint | Limit |
|---|---|
| `POST /auth/login` | 5 failed logins per account in 15 minutes (none once the address has 100), then one attempt per account per minute; the check runs before the password, so a correct password waits too, but never longer than a minute after the last failure |
| `POST /preview` | 30 per minute |
| `GET /search` | 60 per minute |

## Responses and errors

Success: `{"data": ..., "meta": {...}}`. `meta` (`page`, `pages`, `total`) appears on paginated lists
(`?page=N`).

Error: `{"error": {"code": "...", "message": "...", "fields": {...}}}`. `fields` is an object, empty
unless the error is field-specific.

| Status | `code` | When |
|---|---|---|
| 400 | `bad_request` | Missing or too short `q` |
| 401 | `unauthenticated` | Write without login |
| 401 | `invalid_credentials` | Wrong username or password |
| 403 | `csrf` | Missing or invalid `X-CSRF-Token` on a cookie-authenticated write |
| 403 | `forbidden` | No permission: post, reply, edit, delete; the board is not readable by the user's group (every endpoint except `/auth/*`, `/config`, `/me`); no permission to list users (`/users`, `/users/{id}`) or to search; the online list is disabled; registering while logged in; a banned user (any endpoint) |
| 403 | `registration_closed` | Registrations are off |
| 404 | `not_found` | Unknown route, forum, topic, post or user |
| 405 | `method_not_allowed` | Path exists with another method |
| 415 | `unsupported_media_type` | `/auth/login` or `/auth/register` with a body that is not JSON and no valid `X-CSRF-Token` |
| 422 | `validation` | Invalid input; `fields` maps field name to message |
| 429 | `flood` | Posting too fast; `error.fields.retry_after` is the seconds to wait |
| 429 | `registration_flood` | Another registration from this IP within the last hour |
| 429 | `too_many_attempts` | A rate limit above; the `Retry-After` header gives the seconds to wait |
| 503 | `maintenance` | The board is in maintenance mode and the caller is not an administrator (`/auth/login` and `/config` still answer) |
| 500 | `internal` | Unexpected error (details only in debug mode) |

```json
{"error":{"code":"flood","message":"Posting too fast","fields":{"retry_after":12}}}
```

## CORS

`api/config.php` returns `['cors_origin' => '']`. Empty disables CORS headers. Set it to an origin
(for example `https://app.example.com`) to send `Access-Control-Allow-*` headers allowing
`Content-Type`, `Authorization` and `X-CSRF-Token`; `OPTIONS` preflights answer `204`. Keep it a fixed origin:
never change the code to echo back the request's `Origin`. `api/config.php` is not served over the web
(`api/.htaccess` denies it, and it answers `404` when requested directly).

Every API response carries `X-Content-Type-Options: nosniff`.

## Request bodies

Send JSON (`Content-Type: application/json`); form-encoded bodies are accepted too (no `csrf_token` field
needed), except on login and registration without an `X-CSRF-Token` (see Authentication). A field of the wrong type, such as an array where text is expected, counts as empty.

## Endpoints

`$T` is a bearer token from login in the examples.

### Board

#### GET /config

```
curl http://localhost/api/v1/config
```
```json
{"data":{"board_title":"My PunBB forum","board_desc":"...","default_lang":"English","default_style":"LightOxygen","time_format":"H:i:s","date_format":"d-m-Y","regs_allow":true,"version":"2.0.0"}}
```

#### GET /me

The current user (a guest with `is_guest: true` when not logged in).

```
curl http://localhost/api/v1/me -H "Authorization: Bearer $T"
```
```json
{"data":{"id":2,"username":"admin","title":"Administrator","num_posts":43,"registered":1792627279,"registered_formatted":"Today","avatar_html":"","url":"","location":"","is_guest":false,"is_admmod":true,"language":"English","style":"LightOxygen","timezone":"0"}}
```
Cookie-authenticated callers also get `"csrf_token":"..."`, the value for the `X-CSRF-Token` header.

#### GET /online

`403 forbidden` when the board has the online list turned off.

```
curl http://localhost/api/v1/online
```
```json
{"data":{"users":[{"id":2,"username":"admin"}],"guests":1}}
```

### Auth

#### POST /auth/login

Body: `username`, `password`, optional `remember` (truthy: 14-day token). Error: `invalid_credentials`. The first
login of an email-verified registration moves the user to the default group, as the forum's login page does.

```
curl -X POST http://localhost/api/v1/auth/login -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"secret123"}'
```
```json
{"data":{"token":"MnxmNzZj...","expires":1792636038,"user":{"id":2,"username":"admin","title":"Administrator","num_posts":43,"registered":1792627279,"registered_formatted":"Today","avatar_html":"","url":"","location":""}}}
```

#### POST /auth/logout

Needs login (and CSRF for cookie sessions). Clears the cookie; a bearer token itself stays valid until it expires.

```
curl -X POST http://localhost/api/v1/auth/logout -H "Authorization: Bearer $T"
```
```json
{"data":{"ok":true}}
```

#### POST /auth/register

Body: `username`, `email`, `password` (ignored when the board verifies by email), optional `language`,
`timezone`, `dst`. Errors: `forbidden` (already logged in), `registration_closed`, `validation` (fields `username`, `password`,
`email`), `registration_flood`.

```
curl -X POST http://localhost/api/v1/auth/register -H 'Content-Type: application/json' \
  -d '{"username":"apitest","email":"apitest@example.com","password":"pass1234"}'
```
`201`:
```json
{"data":{"user":{"id":10,"username":"apitest","title":"Member","num_posts":0,"registered":1792630000,"registered_formatted":"Today","avatar_html":"","url":"","location":""}}}
```
When email verification is on the response is `202` with `{"data":{"verify_email":true}}`.

### Forums and topics

#### GET /forums

Categories with their readable forums. `can_post` says whether the current user may start a topic.

```
curl http://localhost/api/v1/forums
```
```json
{"data":[{"id":1,"name":"Test category","forums":[{"id":1,"name":"Test forum","desc":"This is just a test forum","cat_id":1,"num_topics":12,"num_posts":38,"last_post":1792630568,"last_post_formatted":"Today 00:56:08","last_post_id":46,"last_poster":"admin","redirect_url":"","can_post":false}]}]}
```

#### GET /forums/{id}

One forum (same shape as above). `404 not_found` if missing or not readable.

```
curl http://localhost/api/v1/forums/1
```

#### GET /forums/{id}/topics

Paginated, sticky first. Query: `page`.

```
curl 'http://localhost/api/v1/forums/1/topics?page=1'
```
```json
{"data":[{"id":1,"subject":"Test post","poster":"admin","posted":1792627279,"posted_formatted":"Today 00:01:19","last_post":1792627279,"last_post_formatted":"Today 00:01:19","last_post_id":1,"last_poster":"admin","num_replies":0,"num_views":6,"closed":false,"sticky":false,"forum_id":1,"moved_to":null}],"meta":{"page":1,"pages":1,"total":12}}
```

#### POST /forums/{id}/topics

Body: `subject`, `message`, optional `hide_smilies`, `subscribe`. Errors: `forbidden`, `flood`, `validation`
(fields `subject`, `message`).

```
curl -X POST http://localhost/api/v1/forums/1/topics -H "Authorization: Bearer $T" \
  -H 'Content-Type: application/json' -d '{"subject":"Hello","message":"First post"}'
```
`201`:
```json
{"data":{"topic_id":16,"post_id":47}}
```

#### GET /topics/{id}

One topic (same shape as in the list).

```
curl http://localhost/api/v1/topics/1
```

#### GET /topics/recent

Latest topics across readable forums. Query: `limit` (1 to 50, default 10). Each item is a topic object plus
`forum_name`, `last_poster_id` (or `null` for a guest poster) and `last_poster_avatar` (the last poster's avatar as an
absolute URL, or `null` when there is none or avatars are off; never HTML).

```
curl 'http://localhost/api/v1/topics/recent?limit=5'
```
Response: `{"data":[ <topic>, ... ]}`.

### Posts

A post object: `id`, `topic_id`, `poster` (public user), `message` (raw), `message_html`,
`hide_smilies`, `posted`, `posted_formatted`, `edited` (or `null`), `edited_by`, `is_online`,
`can_edit`, `can_delete` (for the current user).

#### GET /topics/{id}/posts

Paginated. Query: `page`.

```
curl http://localhost/api/v1/topics/1/posts
```
```json
{"data":[{"id":1,"topic_id":1,"poster":{"id":2,"username":"admin","title":"Administrator","num_posts":43,"registered":1792627279,"registered_formatted":"Today","avatar_html":"","url":"","location":""},"message":"Hello","message_html":"<p>Hello</p>","hide_smilies":false,"posted":1792627279,"posted_formatted":"Today 00:01:19","edited":null,"edited_by":null,"is_online":true,"can_edit":false,"can_delete":false}],"meta":{"page":1,"pages":1,"total":1}}
```

#### GET /posts/{id}

One post (same shape).

```
curl http://localhost/api/v1/posts/1
```

#### POST /topics/{id}/posts

Reply. Body: `message`, optional `hide_smilies`, `subscribe` (omitted keeps the current subscription).
Errors: `unauthenticated`, `forbidden` (closed topic for non-moderators), `flood`, `validation`.

```
curl -X POST http://localhost/api/v1/topics/1/posts -H "Authorization: Bearer $T" \
  -H 'Content-Type: application/json' -d '{"message":"A reply"}'
```
`201`:
```json
{"data":{"post_id":48}}
```

#### PUT /posts/{id}

Edit. Body: `message`, optional `hide_smilies` (unchanged when omitted); `subject` (first post only, renames the topic);
`silent` (moderators only, skips the "edited" mark). Returns the updated post.

```
curl -X PUT http://localhost/api/v1/posts/48 -H "Authorization: Bearer $T" \
  -H 'Content-Type: application/json' -d '{"message":"Edited"}'
```
Response: `{"data": <post>}`.

#### DELETE /posts/{id}

Deleting a topic's first post deletes the whole topic. As in the forum, a non-moderator may delete only their
own posts, never in a closed topic, and needs `g_delete_posts` (replies) or `g_delete_topics` (first post).

```
curl -X DELETE http://localhost/api/v1/posts/48 -H "Authorization: Bearer $T"
```
```json
{"data":{"deleted":"post","topic_id":1,"forum_id":1}}
```
(`"deleted":"topic"` when the first post was removed.)

#### POST /preview

Renders BBCode. Body: `message`, optional `hide_smilies`. Needs CSRF for cookie sessions (guests included). Messages over the board's post size limit and parser
problems come back as `422 validation` with an array in `fields.message`.

```
curl -X POST http://localhost/api/v1/preview -H "Authorization: Bearer $T" \
  -H 'Content-Type: application/json' -d '{"message":"[b]hi[/b]"}'
```
```json
{"data":{"html":"<p><strong>hi</strong></p>"}}
```

### Search and users

#### GET /search

Topics containing all the words in `q`. Paginated (`page`). `400 bad_request` without a searchable word.
Unless `q` ends with a space, its last word is a prefix (at least 3 characters): it matches any of up to 50 indexed
words starting with it (`%` and `_` are literal), so `q=hel` finds posts with "hello" or "help"; the other words match
exactly. A prefix of 1 or 2 characters matches exactly. Only the 500 most recently active matching topics are
returned (`meta.total` is at most 500).

```
curl 'http://localhost/api/v1/search?q=test'
```
Response: `{"data":[ <topic>, ... ],"meta":{"page":1,"pages":1,"total":1}}`.

#### GET /users/{id}

`num_posts` is omitted when the board hides post counts (administrators and moderators still see it; this applies
to every user object in the API) and `signature_html` when signatures are off. `last_visit` is only returned to the
member themselves, administrators and moderators.

```
curl http://localhost/api/v1/users/2
```
```json
{"data":{"id":2,"username":"admin","title":"Administrator","num_posts":43,"registered":1792627279,"registered_formatted":"Today","avatar_html":"","url":"","location":"","group_id":1,"group_title":"Administrator","signature_html":"","last_visit":1792630405,"last_post":1792630568}}
```

#### GET /users

Username prefix search, `q` of at least 2 characters, up to 20 results; `%` and `_` are literal.

```
curl 'http://localhost/api/v1/users?q=ad'
```
Response: `{"data":[ <public user>, ... ]}`.

## Tests

The scripts in `api/tests/` run from the command line only: each answers `404` to a web request, and
`api/tests/.htaccess` denies the folder on Apache. On nginx add `location ^~ /api/tests/ { deny all; }` to the
server block. Leave the folder out of production copies.

The suite runs against a live server and exits non-zero on any failure. It expects
an installed board with an `admin` user (password `secret123`), and with registration open it creates
an `apitest` user (a second run checks the duplicate is rejected). Topic and post tests create their own
data; `api/tests/fixture.php` toggles forum permissions, topic state, the guest read permission, bans and
maintenance mode directly in the database (the ban and maintenance tests wait a few seconds for opcache to
pick up the rewritten cache files). `run.php` deletes `cache/throttle.json` first, because the suite logs in
with wrong passwords on purpose.

```
php -S 127.0.0.1:8086 -d display_errors=1 -d error_reporting=E_ALL      # from the board root
API_BASE=http://127.0.0.1:8086/api/index.php php api/tests/run.php       # built-in server
API_BASE=http://localhost/api/v1 php api/tests/run.php                   # Apache with .htaccess
```
