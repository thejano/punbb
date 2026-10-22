import { apiGet } from '../api.js';

// topic-avatars: core prints only the starter's name in topic rows, so look each name up once
// (GET /users?q=, cached in sessionStorage) and put a 24px avatar, real or default, before the subject.
const KEY = 'lo-topic-avatars';

function load() {
	try {
		return JSON.parse(sessionStorage.getItem(KEY) || '{}');
	} catch {
		return {};
	}
}

function save(cache) {
	try {
		sessionStorage.setItem(KEY, JSON.stringify(cache));
	} catch {
		// storage full or blocked: the next page asks again
	}
}

async function lookup(name) {
	// the endpoint needs two characters; shorter names keep the default
	if (name.length < 2) return '';
	const r = await apiGet(`/users?q=${encodeURIComponent(name)}`);
	const u = (r.data || []).find((x) => x.username.toLowerCase() === name.toLowerCase());
	const m = u && /\ssrc="([^"]+)"/.exec(u.avatar_html || '');
	return m ? m[1].replace(/&amp;/g, '&') : '';
}

export default async function topicAvatars(root, defaultAvatar) {
	const rows = [...root.querySelectorAll('.main-item')]
		.map((row) => ({ row, name: row.querySelector('.item-starter cite')?.textContent.trim() }))
		.filter((x) => x.name && !x.row.querySelector('.lo-starter'));
	if (!rows.length) return;

	const cache = load();
	const queue = [...new Set(rows.map((x) => x.name))].filter((n) => !(n in cache));
	// four lookups at a time; only answered lookups are cached, an error shows the default this time only
	const worker = async () => {
		while (queue.length) {
			const n = queue.shift();
			try {
				cache[n] = await lookup(n);
			} catch {
				// not cached: the default shows this time
			}
		}
	};
	await Promise.all(Array.from({ length: 4 }, worker));
	save(cache);

	for (const { row, name } of rows) {
		const hn = row.querySelector('.item-subject .hn');
		if (!hn) continue;
		const src = cache[name];
		const av = document.createElement('span');
		av.className = `lo-avatar lo-avatar-sm lo-starter${src ? '' : ' lo-avatar-default'}`;
		av.setAttribute('aria-hidden', 'true');
		const img = new Image(24, 24);
		img.alt = '';
		img.src = src || defaultAvatar;
		av.append(img);
		hn.prepend(av);
	}
}
