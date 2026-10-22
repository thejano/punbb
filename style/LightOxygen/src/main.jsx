import './main.css';
import defaultAvatar from '../assets/avatar-default.svg';
import { createRoot } from 'react-dom/client';
import ActivityPanel from './islands/ActivityPanel.jsx';

import SearchBox from './islands/SearchBox.jsx';
import QuickReply from './islands/QuickReply.jsx';
import topicAvatars from './islands/topicAvatars.js';

export function mountIslands(registry) {
	document.querySelectorAll('[data-island]').forEach((el) => {
		const Component = registry[el.dataset.island];
		if (!Component) return;
		let props = {};
		try {
			props = JSON.parse(el.dataset.props || '{}');
		} catch {
			return;
		}
		createRoot(el).render(<Component {...props} />);
	});
}

mountIslands({ 'activity-panel': ActivityPanel, 'search-box': SearchBox });

// topic lists (viewforum, search results): the starter's avatar before each subject
const list = document.querySelector('#brd-main[data-topic-avatars]');
if (list) topicAvatars(list, defaultAvatar, list.dataset.canViewUsers !== '0');

// core prints the quick-post form itself (#brd-qpost); add the toolbar before its textarea
const ta = document.querySelector('#brd-qpost textarea[name="req_message"]');
if (ta) {
	const mount = document.createElement('div');
	ta.parentNode.insertBefore(mount, ta);
	const native = ta.form.querySelector('input[name="preview"]');
	// the island has its own preview button; hide the form's so there is one
	if (native) (native.closest('.submit') || native).hidden = true;
	let labels;
	try {
		labels = JSON.parse(document.getElementById('lo-qr-props')?.dataset.props || '{}').labels;
	} catch {
		labels = undefined;
	}
	createRoot(mount).render(<QuickReply textarea={ta} previewLabel={native?.value} labels={labels} />);
}

// the View menu is a <details>: Escape or a click outside closes it (without script it closes from its summary)
document.addEventListener('keydown', (e) => {
	if (e.key !== 'Escape') return;
	document.querySelectorAll('details.lo-view-menu[open]').forEach((d) => {
		const inside = d.contains(document.activeElement);
		d.open = false;
		if (inside) d.querySelector('summary').focus();
	});
});
document.addEventListener('pointerdown', (e) => {
	document.querySelectorAll('details.lo-view-menu[open]').forEach((d) => {
		if (!d.contains(e.target)) d.open = false;
	});
});

// a real avatar that fails to load becomes the default one
document.addEventListener('error', (e) => {
	const img = e.target;
	if (!(img instanceof HTMLImageElement) || img.dataset.fallback || !img.closest('.lo-avatar, .useravatar')) return;
	img.dataset.fallback = '1';
	img.removeAttribute('width');
	img.removeAttribute('height');
	img.src = defaultAvatar;
}, true);
