# LightOxygen design notes

References studied: Discourse (topic list, avatar gutter, category sidebar), Flarum (permanent start/end sidebar of tags and filters, airy list rows), GitHub Discussions (category sidebar, pill labels, compact counts at the row end), NodeBB (live "recent / online" widgets), and the user's own LightWeb theme (3/9 split: content plus a sidebar of categories, filters, activity, stats; Bootstrap palette).

## Patterns chosen and where they land

| Pattern | PunBB region |
| --- | --- |
| Sticky, translucent top bar with icon nav, search and avatar menu | `#lo-header` (`lo_nav`, `lo_search`, `lo_auth`) |
| Content column plus persistent end-side sidebar: Browse, Filters, Activity, Stats (on the index Activity, Filters, Stats: its forum list already shows every forum) | `main > #lo-content` + `#lo-sidebar` (`lo_sidebar()`) on index, viewforum, search results, userlist |
| Rows led by a rounded icon tile, title and excerpt, compact counts and last activity at the end side, soft hover lift | `.main-item`, `.item-subject`, `.item-info` (index, viewforum, search) |
| Pill badges (user title, post number, page numbers, active tab) | `.usertitle`, `.post-num`, `.paging`, `.main-menu .active` |
| Conversation cards with a compact author header (avatar, name, title pill) instead of a tall rail, ~72ch measure | `.post`, `.post-author`, `.posthead`, `.entry-content` |
| Composer card under the thread with a toolbar | `#brd-qpost` + the quick-reply island |
| Centred auth cards, form fields in grouped cards, sticky side tabs | login/register, `.main-frm`, `.main-menu` (profile), `#lo-admin-menu` |
| Clear empty states | `.main-message` (icon disc, centred text) |
| First-class dark mode from the same hues | `:root` token overrides under `prefers-color-scheme: dark` |

8px spacing scale (Tailwind steps of 2), 16px body text (17px line box for Kurdish), Inter + Vazirmatn.

## Rejected

- Floating composer: the quick-reply form is core markup at the end of the page; a fixed composer would cover posts on phones.
- Infinite scroll and client routing: every page must work without JavaScript.

## Two layouts

`lo-layout-sidebar` (default): on `lg` the content and an 18rem sidebar on the end side (right in English, left in Kurdish); below `lg` the sidebar follows the content and Browse is a `<details>` rendered open (visitors may fold it). `lo-layout-wide`: the single-column board, the activity panel under the forum list on the index only. The search form page (`searchforums`) is intentionally without the sidebar; only search results carry it. The choice is the `lo_layout` cookie, switched by plain links (`?lo_layout=sidebar|wide`) in the header; the server sets the body class, so no JavaScript is needed.

## Two skins

A second preference, "look": `lo-skin-calm` (default, everything above) and `lo-skin-meko` (the live board's look). The choice is the `lo_skin` cookie (`calm`|`meko`, one year, path `/`), read by `lo_skin()`, put on `<body>` with the layout class and switched by plain links `?lo_skin=calm|meko`. Both preferences sit in one header button, "View" (`lo_view_menu()`): a native `<details>` popover with a Layout group (sidebar pages only, `lo_layout_switch()`) and a Look group (`lo_skin_switch()`), links with `aria-pressed`, a 36px icon button beside the auth control at every width; below `md` the popover drops full-width under the sticky header so phones can switch too. One compact button instead of two segmented controls keeps a logged-in admin's whole nav visible at 1280; the header search is 10rem from `lg` (15rem from `2xl`), nav items a little tighter, and the nav's end-edge fade applies at every width (1.5rem from `lg`) in case the items still overflow. `LightOxygen.php` handles both parameters in one loop: a valid value sets the cookie, any value redirects to `lo_pref_url($name)`, the same URL without the parameter built from `$base_url` and the validated `SCRIPT_NAME` (`lo_layout_url()` is now a wrapper). Both groups render through `lo_pref_switch()`.

Meko is tokens plus a short list of overrides in `src/css/skins.css`, all under `.lo-skin-meko`; there is no second template.

- Tokens (`tokens.css`, set on `<body>` and on `<html>` through `:root:has(> body.lo-skin-meko)` so short pages have no white strip under the footer): page `#edf1f5`, sunken `#e4eaf0`, subtle `#f4f6f9` in light mode only (dark mode keeps the dark surfaces); `--color-link-hover` accent-700 (light) / `#b5cfe8` (dark).
- Header: solid accent-600 (`#2f5f8a`) in both colour schemes, white nav, search, View button and auth; the logo on a white disc.
- Index: the hero shows exactly as `o_lo_hero_mode` says, as under Calm (logged-in users get no Login / Register); Meko styles it as a full-width band with the tagline and the big search centred, then `lo_members()`: the twelve members with the most posts as 40px avatars ("Most active members"), as on the live board.
- Lists: each forum or topic row its own white card (16px padding, 12px gaps) on the grey page; subjects in the link blue; on the index each category heading is a white tile with its hue as a 4px top bar.
- Posts: 56px author avatar and a larger name. Form submit buttons in the primary blue. Links hover one step darker.
- Footer: dark slate `#343a40` across the page, light text.

Density: on viewforum 2 at 1280 meko is 1.01x calm in the sidebar layout (the sidebar sets the height) and 1.09x in the wide layout (content column 1.10x); the target is at most 1.25x.

## Palette (LightWeb)

Accent `#2f5f8a` (scale 50-900 around it; hover one step darker, focus ring `accent-600/25`), ink `#212529`, secondary ink `#495057`, muted `#6c757d`, lines `#dee2e6` / `#d3dae1`, surfaces `#ffffff` page and cards, `#f8f9fa` subtle, `#edf1f5` sunken. Status colours only for messages, badges and the online dot: success `#28a745`, danger `#dc3545`, warning `#ffc107`, info `#17a2b8`. Dark mode derives from `#343a40`: page `#1a1d21`, cards `#212529`, subtle `#2b3035`, lines `#41484f` (kept clearly apart from subtle), ink `#e9ecef`, accent text `#8db3d8`.

## Avatars

Every person shown carries an avatar or the default silhouette (`assets/avatar-default.svg`): posts 40px, lists and the activity panel 24px, the profile header 96px. Core markup gets the default from a CSS `:has()` fallback, theme markup from `lo_avatar()`; the user list matches real avatars to profile links with inline CSS from `lo_meta()`. The SVG hardcodes its colours and carries its own `prefers-color-scheme` block, because CSS variables cannot reach an `<img>`.

The quick-reply form shows no author line: core prints no person in the quick-post form.

## Adopted from the live board (Meko, LightWeb)

After a side-by-side with the live board, its strengths were brought in without giving up the dense list.

- **Guest hero** (`lo_hero()`, `<!-- lo_hero -->` under the header, index for guests only): board title, `o_board_desc`, a large search (the same GET form and search island as the header, which hides on that page) and Login / Register. A compact card in the content column over the theme's `assets/hero-default.jpg` (a soft blue and teal gradient with blurred circles, 1600x600) unless the admin sets a background.
- **Activity panel** (`lo_activity()` and `ActivityPanel`): each recent topic is one link row: the last poster's 32px avatar (real or default; the name as `title` and visually hidden text), the subject on one line with an ellipsis, then forum, reply count and time. The server joins `users` on `t.last_poster` (a guest poster gets the default) and passes `last_poster`, `last_poster_avatar` (URL or `null`), `forum_name`, `num_replies`, `last_post_formatted` per topic in `data-props`; the island reads the same fields from `GET /topics/recent`. The index lists `o_lo_activity_count` topics (default 10), other sidebar pages 6. The meta line keeps the page direction with the forum name and time isolated, so a Latin forum name does not flip a Kurdish line. In the wide layout the list is two columns from `lg`.
- **No duplicate Browse on the index**: the index sidebar order is Activity, Filters, Stats; the Browse block repeats the forum list there, so it appears only when `o_lo_index_browse` is `1`. Viewforum, search results and the user list keep Browse first.
- **Members as avatars** (`lo_activity()` and `ActivityPanel`): online members are a row of round 24px avatars (real or default), the name as `title` and visually hidden text; at most 16 (two rows in the sidebar), then a `+N` pill; guests as a short count after them.
- **Category colour** (index only): each category takes one of six hues by position (`:nth-child(6n+k of .main-category)` / `of .main-head`): accent, info, success, warning, danger, muted. The heading bar gets a 4px start-side line and a 9% tint, the forum icon tiles a 14% tint with the hue mixed into ink for the glyph; a forum with new posts gets an inset ring of its hue. Topic rows on viewforum keep the one accent.
- **Starter avatars** (`src/islands/topicAvatars.js`, mounted on `#brd-main[data-topic-avatars]` on viewforum and search results): core prints only the starter's name, so the script reads `.item-starter cite`, asks `GET /users?q=<name>` once per name (exact match, four lookups at a time; only answered lookups are cached in `sessionStorage`, a 403 is cached as no avatar, a viewer without permission to view users makes no lookups, any other failed lookup shows the default for this page view and is asked again on the next) and puts a 24px avatar before the subject. Without JavaScript the rows keep the icon tile only.
- **Pinned group**: core prints sticky topics first with `.sticky` on the row. `lo_main_attr()` puts `class="lo-has-pinned"` on `#brd-main` when page 1 of a forum has a sticky topic, and the translated labels as `--lo-l-pinned` / `--lo-l-topics`; CSS draws a label bar above the first sticky row and above the first row after the stickies. Rows: pin icon and tint (sticky), lock (closed), arrow and muted text (moved), filled accent tile plus a "new posts" pill (unread). Search results get the row treatment but no group labels: they are ordered by date, not stickiness.
- **Phone density**: on topic lists below `md` a row is the subject, then the starter and counts on one line (views, forum and last poster hidden; they wrap rather than clip). Below `lg`, on every page except the index, the sidebar shows only Filters: Browse repeats the crumbs and the jump menu, and Activity and Statistics belong to the index; stacking all four under a topic list made the phone viewforum taller than LightWeb's. With both changes viewforum and viewtopic at 390 stay under the 60%-of-LightWeb height target.

## RTL details

- Dates and counts: `unicode-bidi: isolate` on `.item-info li`, `.item-starter`, `.lo-stat`; `plaintext` on the last-post date and the activity lines, so `18-10-2026 02:33:21` keeps its order on Kurdish pages. Theme-printed subjects, activity lines and statistics carry `dir="auto"`; forum descriptions on the index take their own direction (`plaintext`).
- Core wraps the new-posts link in "( ... )" on the index and in a "pages" prefix on Kurdish topic rows; the wrapper text is set to zero size and the link is a small pill at the end of the subject. Core's status words (and the Oxygen image the Kurdish pack prints for sticky) are visually hidden: the icon tile says the same.
- Tailwind would turn core's `sticky` class into `position: sticky`; `@source not inline("sticky")` keeps that utility out, and the theme's own sticky elements set the property directly.

## Board settings (lightoxygen_settings)

The extension in `extensions/lightoxygen_settings` adds a section to `admin/settings.php` (listed under Extensions) and stores each setting as an `o_lo_*` config row. The style reads them only through `lo_setting($key, $default)`, which returns the code default unless the extension's `co_common` hook has defined `LO_SETTINGS`, so a disabled or missing extension leaves the style exactly as it was. Saving goes through core's settings code: the extension validates `$form` in `aop_new_section_validation` (any error shows the form again and nothing is written), then core writes the changed rows and regenerates the config cache.

- Board default layout and look apply to visitors without a valid cookie; with switching off, cookies are ignored and the View menu is not printed.
- Hero: `o_lo_hero_mode` means the same under both looks: `guests` (the default), `all` or `none`; the look changes only how the hero is styled. The background image (the admin's upload or URL, else `assets/hero-default.jpg`) sits under a 50% black overlay, inline on `.lo-hero`, so both looks keep their text white.
- Accent colour: set inline on `<html>` as `--color-accent-500/600` with 400, 700 and 800 mixed from it in CSS (`color-mix`); `--color-link` now follows `--color-accent-600` in light mode, dark mode keeps its own light link colour.
- Colour scheme: `data-theme="light|dark"` on `<html>`. The `dark` and `light` custom variants in `tokens.css` honour it before the system preference, for the tokens and for every `dark:` utility.
- Category colours off: `body.lo-no-cat-colors`, every category takes the accent hue. Member avatars off: no avatar row under the Meko hero, and the online list shows names (`body.lo-no-avatars`).
- Activity: `o_lo_activity_count` (5 to 30, default 10) is how many recent topics the index panel lists and the island's `limit`; `o_lo_index_browse` (`0` by default) puts the Browse block back in the index sidebar.
- Font `bahij` needs `assets/fonts/BahijMyriadArabicRegular.woff2` (and optionally the Bold file). The files are not shipped because no licence for redistributing them was found; until they are added the option is shown as unavailable.
- Custom CSS is printed in `<style id="lo-settings">` after the theme stylesheet, with every `</style` removed (repeatedly, so split sequences cannot rejoin).
