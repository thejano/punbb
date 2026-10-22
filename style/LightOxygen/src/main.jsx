import './main.css';
import { createRoot } from 'react-dom/client';
import ActivityPanel from './islands/ActivityPanel.jsx';

// Task 6 fills these in; null keeps the server-rendered fallback untouched meanwhile
const SearchBox = null;
const QuickReply = null;

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

mountIslands({ 'activity-panel': ActivityPanel, 'search-box': SearchBox, 'quick-reply': QuickReply });
