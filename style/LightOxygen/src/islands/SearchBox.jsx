import { useEffect, useRef, useState } from 'react';
import { apiGet } from '../api.js';

export default function SearchBox({ action, placeholder, topic_url, list_id = 'lo-search-list' }) {
	const [q, setQ] = useState('');
	const [items, setItems] = useState([]);
	const [open, setOpen] = useState(false);
	const [active, setActive] = useState(-1);
	const input = useRef(null);
	const box = useRef(null);

	useEffect(() => {
		if (q.trim().length < 2) {
			setItems([]);
			return;
		}
		let stale = false;
		const t = setTimeout(async () => {
			try {
				const r = await apiGet(`/search?q=${encodeURIComponent(q.trim())}`);
				if (stale || !Array.isArray(r.data)) return;
				setItems(r.data.slice(0, 6));
				setActive(-1);
				setOpen(true);
			} catch {
				setItems([]);
			}
		}, 250);
		return () => {
			stale = true;
			clearTimeout(t);
		};
	}, [q]);

	useEffect(() => {
		const away = (e) => box.current && !box.current.contains(e.target) && setOpen(false);
		document.addEventListener('pointerdown', away);
		return () => document.removeEventListener('pointerdown', away);
	}, []);

	const shown = open && items.length > 0;
	const href = (it) => topic_url.replace('987654321', it.id);

	const onKey = (e) => {
		if (e.key === 'Escape') {
			e.preventDefault(); // type=search would clear the field
			setOpen(false);
			setActive(-1);
		}
		else if (shown && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
			e.preventDefault();
			const n = items.length;
			setActive((a) => (e.key === 'ArrowDown' ? (a + 1) % n : (a - 1 + n) % n));
		} else if (e.key === 'Enter' && shown && active >= 0) {
			e.preventDefault();
			location.href = href(items[active]);
		}
	};

	return (
		<div ref={box} className="lo-search-box">
			<form method="get" action={action} role="search">
				<input type="hidden" name="action" value="search" />
				<input
					ref={input}
					type="search"
					name="keywords"
					maxLength={100}
					placeholder={placeholder}
					aria-label={placeholder}
					autoComplete="off"
					role="combobox"
					aria-expanded={shown}
					aria-controls={list_id}
					aria-activedescendant={active >= 0 ? `${list_id}-${active}` : undefined}
					value={q}
					onChange={(e) => { setQ(e.target.value); setOpen(true); }}
					onKeyDown={onKey}
				/>
			</form>
			{shown && (
				<ul id={list_id} className="lo-suggest" role="listbox">
					{items.map((it, i) => (
						<li key={it.id} id={`${list_id}-${i}`} role="option" aria-selected={i === active}>
							<a href={href(it)} tabIndex={-1}>{it.subject}</a>
						</li>
					))}
				</ul>
			)}
		</div>
	);
}
