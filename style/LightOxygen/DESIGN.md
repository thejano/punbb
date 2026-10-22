# LightOxygen design notes

References studied: Discourse (topic list, avatar gutter, category sidebar), Flarum (permanent start/end sidebar of tags and filters, airy list rows), GitHub Discussions (category sidebar, pill labels, compact counts at the row end), NodeBB (live "recent / online" widgets), and the user's own LightWeb theme (3/9 split: content plus a sidebar of categories, filters, activity, stats; Bootstrap palette).

## Patterns chosen and where they land

| Pattern | PunBB region |
| --- | --- |
| Sticky, translucent top bar with icon nav, search and avatar menu | `#lo-header` (`lo_nav`, `lo_search`, `lo_auth`) |
| Content column plus persistent end-side sidebar: Browse, Filters, Activity, Stats | `main > #lo-content` + `#lo-sidebar` (`lo_sidebar()`) on index, viewforum, search results, userlist |
| Rows led by a rounded icon tile, title and excerpt, compact counts and last activity at the end side, soft hover lift | `.main-item`, `.item-subject`, `.item-info` (index, viewforum, search) |
| Pill badges (user title, post number, page numbers, active tab) | `.usertitle`, `.post-num`, `.paging`, `.main-menu .active` |
| Conversation cards with a compact author header (avatar, name, title pill) instead of a tall rail, ~72ch measure | `.post`, `.post-author`, `.posthead`, `.entry-content` |
| Composer card under the thread with a toolbar | `#brd-qpost` + the quick-reply island |
| Centred auth cards, form fields in grouped cards, sticky side tabs | login/register, `.main-frm`, `.main-menu` (profile), `#lo-admin-menu` |
| Clear empty states | `.main-message` (icon disc, centred text) |
| First-class dark mode from the same hues | `:root` token overrides under `prefers-color-scheme: dark` |

8px spacing scale (Tailwind steps of 2), 16px body text (17px line box for Kurdish), Inter + Vazirmatn.

## Rejected

- Hero/welcome strip for guests: needs new markup on the index; the board title, announcement and the sidebar already orient a visitor.
- Avatars on topic rows: core rows carry no avatar URL; a neutral icon tile keeps rows aligned instead.
- A hue per category: conflicts with the one-accent palette; categories use neutral headings and the accent only marks the current forum.
- Floating composer: the quick-reply form is core markup at the end of the page; a fixed composer would cover posts on phones.
- Infinite scroll and client routing: every page must work without JavaScript.

## Two layouts

`lo-layout-sidebar` (default): on `lg` the content and an 18rem sidebar on the end side (right in English, left in Kurdish); below `lg` the sidebar follows the content and Browse is a `<details>` rendered open (visitors may fold it). `lo-layout-wide`: the single-column board, the activity panel under the forum list on the index only. The search form page (`searchforums`) is intentionally without the sidebar; only search results carry it. The choice is the `lo_layout` cookie, switched by plain links (`?lo_layout=sidebar|wide`) in the header; the server sets the body class, so no JavaScript is needed.

## Palette (LightWeb)

Accent `#2f5f8a` (scale 50-900 around it; hover one step darker, focus ring `accent-600/25`), ink `#212529`, secondary ink `#495057`, muted `#6c757d`, lines `#dee2e6` / `#d3dae1`, surfaces `#ffffff` page and cards, `#f8f9fa` subtle, `#edf1f5` sunken. Status colours only for messages, badges and the online dot: success `#28a745`, danger `#dc3545`, warning `#ffc107`, info `#17a2b8`. Dark mode derives from `#343a40`: page `#1a1d21`, cards `#212529`, subtle `#2b3035`, lines `#41484f` (kept clearly apart from subtle), ink `#e9ecef`, accent text `#8db3d8`.

## Avatars

Every person shown carries an avatar or the default silhouette (`assets/avatar-default.svg`): posts 40px, lists and the activity panel 24px, the profile header 96px. Core markup gets the default from a CSS `:has()` fallback, theme markup from `lo_avatar()`; the user list matches real avatars to profile links with inline CSS from `lo_meta()`. The SVG hardcodes its colours and carries its own `prefers-color-scheme` block, because CSS variables cannot reach an `<img>`.

The quick-reply form shows no author line: core prints no person in the quick-post form. Topic-list search results show no avatar yet: topic lists get the starter's avatar in Task 11.
