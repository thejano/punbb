import { useEffect, useState } from 'react';
import { apiGet } from '../api.js';

export default function ActivityPanel({ limit = 8, refresh = 60, labels, topics: t0 = [], online: o0 = null, avatars = {}, defaultAvatar, topicUrl, userUrl }) {
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
						<a href={link(topicUrl, x.id)}>{x.subject}</a>
						<span>{x.last_poster} · {x.last_post_formatted}</span>
					</li>
				))}
			</ul>
			{online && (
				<>
					<h3>{labels.online}</h3>
					<p className="lo-online">
						{online.users.map((u) => {
							const src = u.avatar_url || avatars[u.id];
							return (
								<span key={u.id} className="lo-who">
									<span className={`lo-avatar lo-avatar-sm${src ? '' : ' lo-avatar-default'}`} aria-hidden="true">
										<img src={src || defaultAvatar} alt="" width="24" height="24" />
									</span>
									{userUrl ? <a href={link(userUrl, u.id)}>{u.username}</a> : u.username}
								</span>
							);
						})}
						{online.guests > 0 && `${online.users.length ? ' · ' : ''}${online.guests} ${labels.guests}`}
					</p>
				</>
			)}
			<p className="lo-updated">{labels.updated} {at.toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })}</p>
		</>
	);
}
