const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

export const apiBase = () => meta('api-base');

// `route=` style bases take extra params with & instead of ?
const url = (path) => {
	const base = apiBase();
	return base.endsWith('route=') ? base + path.replace('?', '&') : base + path;
};

async function parse(res) {
	const json = await res.json().catch(() => ({}));
	if (!res.ok) throw Object.assign(new Error(json.error?.message ?? `HTTP ${res.status}`), { status: res.status });
	return json;
}

export const apiGet = (path) => fetch(url(path), { credentials: 'same-origin' }).then(parse);

export const apiPost = (path, body) =>
	fetch(url(path), {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': meta('api-csrf') },
		body: JSON.stringify(body),
	}).then(parse);
