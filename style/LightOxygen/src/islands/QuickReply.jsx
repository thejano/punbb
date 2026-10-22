import { useState } from 'react';
import { apiPost } from '../api.js';

const TOOLS = [
	['Bold', 'B', 'b'],
	['Italic', 'I', 'i'],
	['Underline', 'U', 'u'],
	['Quote', '"', 'quote'],
	['Code', '</>', 'code'],
	['Link', 'url', 'url'],
	['Image', 'img', 'img'],
	['List', '•', 'list'],
];

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

export default function QuickReply({ textarea, previewLabel = 'Preview' }) {
	const [state, setState] = useState(null);

	const preview = async () => {
		try {
			const r = await apiPost('/preview', { message: textarea.value });
			if (typeof r.data?.html !== 'string') throw new Error();
			setState({ html: r.data.html });
		} catch (e) {
			setState({ error: e.message && !/^HTTP /.test(e.message) ? e.message : 'Preview unavailable' });
		}
	};

	return (
		<div className="lo-qr quick-reply">
			<div className="lo-qr-bar" role="toolbar" aria-label="BBCode">
				{TOOLS.map(([label, text, tag]) => (
					<button key={tag} type="button" aria-label={label} title={label} onClick={() => wrap(textarea, tag)}>{text}</button>
				))}
				<button type="button" className="lo-qr-preview" onClick={preview}>{previewLabel}</button>
			</div>
			<div className="lo-qr-out" aria-live="polite">
				{state?.error && <p className="lo-qr-note">{state.error}</p>}
				{state?.html != null && <div className="lo-qr-html post-content" dangerouslySetInnerHTML={{ __html: state.html }} />}
			</div>
		</div>
	);
}
