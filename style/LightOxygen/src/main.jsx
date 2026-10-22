import './main.css';
import { createRoot } from 'react-dom/client';
import ActivityPanel from './islands/ActivityPanel.jsx';

import SearchBox from './islands/SearchBox.jsx';
import QuickReply from './islands/QuickReply.jsx';

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
