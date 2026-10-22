import { useState } from 'react';
import { apiPost } from '../api.js';

// [label key, button text, bbcode tag]; labels come from lo_labels(), English as the fallback
const TOOLS = [
	['bold', 'B', 'b'],
	['italic', 'I', 'i'],
	['underline', 'U', 'u'],
	['quote', '"', 'quote'],
	['code', '</>', 'code'],
	['link', 'url', 'url'],
	['image', 'img', 'img'],
	['list', '•', 'list'],
];
const EN = { bbcode: 'Formatting', bold: 'Bold', italic: 'Italic', underline: 'Underline', quote: 'Quote', code: 'Code', link: 'Link', image: 'Image', list: 'List' };

function wrap(ta, tag) {
	const { selectionStart: a, selectionEnd: b, value } = ta;
	const sel = value.slice(a, b);
	const inner = tag === 'list' ? (sel || 'item').split('\n').map((l) => `[*]${l}`).join('\n') : sel;
	const text = `[${tag}]${inner}[/${tag}]`;
	ta.focus();
	// execCommand keeps the native undo stack; fall back when unsupported
	ta.setSelectionRange(a, b);
	if (!document.execCommand('insertText', false, text)) ta.value = value.slice(0, a) + text + value.slice(b);
	const pos = a + (sel ? text.length : tag.length + 2);
	ta.setSelectionRange(pos, pos);
}

export default function QuickReply({ textarea, previewLabel = 'Preview', labels }) {
	const l = { ...EN, ...labels };
	const [state, setState] = useState(null);
	const [busy, setBusy] = useState(false);

	const preview = async () => {
		setBusy(true);
		try {
			const r = await apiPost('/preview', { message: textarea.value });
			if (typeof r.data?.html !== 'string') throw new Error();
			setState({ html: r.data.html });
		} catch (e) {
			setState({ error: e.message && !/^HTTP /.test(e.message) ? e.message : 'Preview unavailable' });
		}
		setBusy(false);
	};

	return (
		<div className="lo-qr quick-reply">
			<div className="lo-qr-bar" role="toolbar" aria-label={l.bbcode}>
				{/* the tools scroll on narrow screens; Preview stays pinned at the end */}
				<div className="lo-qr-tools">
					{TOOLS.map(([key, text, tag]) => (
						<button key={tag} type="button" aria-label={l[key]} title={l[key]} onClick={() => wrap(textarea, tag)}>{text}</button>
					))}
				</div>
				<button type="button" className="lo-qr-preview" disabled={busy} onClick={preview}>{previewLabel}</button>
			</div>
			<div className="lo-qr-out" aria-live="polite">
				{state?.error && <p className="lo-qr-note">{state.error}</p>}
				{state?.html != null && <div className="lo-qr-html post-content" dangerouslySetInnerHTML={{ __html: state.html }} />}
			</div>
		</div>
	);
}
