import { useEffect, useState } from 'react';
import { apiGet } from '../api.js';

export default function ActivityPanel({ limit = 8, refresh = 60, max = 16, labels, topics: t0 = [], online: o0 = null, avatars = {}, defaultAvatar, topicUrl, userUrl }) {
	const [topics, setTopics] = useState(t0);
	const [online, setOnline] = useState(o0);
	const [at, setAt] = useState(() => new Date());

	useEffect(() => {
		const load = async () => {
			try {
				const [r, o] = await Promise.all([apiGet(`/topics/recent?limit=${limit}`), o0 ? apiGet('/online') : null]);
				if (!Array.isArray(r.data) || (o && !(o.data && Array.isArray(o.data.users)))) return;
				setTopics(r.data);
				if (o) setOnline(o.data);
				setAt(new Date());
			} catch {
				// keep the last data silently
			}
		};
		if (!(refresh > 0)) return undefined; // the board turned polling off
		load();
		const id = setInterval(load, refresh * 1000);
		return () => clearInterval(id);
	}, [limit, refresh, !!o0]);

	const link = (tpl, id) => tpl.replace('987654321', id);

	return (
		<>
			<h2>{labels.activity}</h2>
			<h3>{labels.recent}</h3>
			<ul>
				{topics.map((x) => (
					<li key={x.id}>
						<a href={link(topicUrl, x.id)} dir="auto">{x.subject}</a>
						<span dir="auto">{x.last_poster} · {x.last_post_formatted}</span>
					</li>
				))}
			</ul>
			{online && (
				<>
					<h3>{labels.online}</h3>
					{/* a row of avatars, the name as title and for screen readers; past `max` a +N count */}
					<p className="lo-online">
						{online.users.slice(0, max).map((u) => {
							const src = u.avatar_url || avatars[u.id];
							const inner = (
								<>
									<span aria-hidden="true">
										<span className={`lo-avatar lo-avatar-sm${src ? '' : ' lo-avatar-default'}`}>
											<img src={src || defaultAvatar} alt="" width="24" height="24" />
										</span>
									</span>
									<span className="sr-only">{u.username}</span>
								</>
							);
							return userUrl
								? <a key={u.id} className="lo-who" href={link(userUrl, u.id)} title={u.username}>{inner}</a>
								: <span key={u.id} className="lo-who" title={u.username}>{inner}</span>;
						})}
						{online.users.length > max && (
							<span className="lo-more" title={`${online.users.length - max} ${labels.more}`}>+{online.users.length - max}</span>
						)}
						{online.guests > 0 && <span className="lo-guests" dir="auto">{online.guests} {labels.guests}</span>}
					</p>
				</>
			)}
			<p className="lo-updated">{labels.updated} {at.toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })}</p>
		</>
	);
}
