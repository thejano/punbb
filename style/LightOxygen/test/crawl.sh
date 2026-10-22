#!/bin/sh
# Usage: test/crawl.sh [base_url]   (admin / secret123 must exist; run against the dev board)
# Crawls the page list as guest and as admin, English and Kurdish, both layouts and looks; exit 1 on any PHP notice.
B=${1:-http://127.0.0.1:8085}
MYSQL=${MYSQL:-/Users/Shared/DBngin/mysql/8.0.33/bin/mysql}
DB="$MYSQL -uroot -h127.0.0.1 punbb2"
ROOT=$(cd "$(dirname "$0")/../../.." && pwd)
T=$(mktemp -d)
ORIG_LANG=$($DB -N -e "SELECT conf_value FROM forum_config WHERE conf_name='o_default_lang'")
ORIG_ADMIN=$($DB -N -e "SELECT language FROM forum_users WHERE username='admin'")
restore() {
	$DB -e "UPDATE forum_config SET conf_value='$ORIG_LANG' WHERE conf_name='o_default_lang'; UPDATE forum_users SET language='$ORIG_ADMIN' WHERE username='admin'"
	rm -f "$ROOT/cache/cache_config.php"; rm -rf "$T"
}
trap restore EXIT

PID=$($DB -N -e "SELECT p.id FROM forum_posts p JOIN forum_users u ON u.id=p.poster_id WHERE p.topic_id=41 AND u.username='admin' ORDER BY p.id LIMIT 1")
PAGES="index.php viewforum.php?id=2 viewtopic.php?id=41 userlist.php search.php search.php?action=search&keywords=hello
search.php?action=show_new search.php?action=show_recent search.php?action=show_unanswered profile.php?id=2
profile.php?section=essentials&id=2 profile.php?section=identity&id=2 profile.php?section=settings&id=2
profile.php?section=signature&id=2 profile.php?section=avatar&id=2 profile.php?section=admin&id=2 register.php login.php
help.php misc.php?action=rules post.php?fid=1 post.php?tid=41 edit.php?id=$PID moderate.php?fid=1 extern.php?action=feed&type=rss"
for f in $(cd "$ROOT/admin" && ls *.php | grep -v -e '^install.php$' -e '^db_update.php$' -e '^settings.php$'); do PAGES="$PAGES admin/$f"; done
for s in setup features announcement email registration lightoxygen; do PAGES="$PAGES admin/settings.php?section=$s"; done

hits=0; n=0
for lang in English Kurdish; do
	$DB -e "UPDATE forum_config SET conf_value='$lang' WHERE conf_name='o_default_lang'; UPDATE forum_users SET language='$lang' WHERE username='admin'"
	rm -f "$ROOT/cache/cache_config.php"
	for who in guest admin; do
		J="$T/$who-$lang"; : > "$J"
		if [ $who = admin ]; then
			L=$(curl -s -c "$J" -b "$J" "$B/login.php")
			TOK=$(printf '%s' "$L" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
			ACT=$(printf '%s' "$L" | grep -o '<form[^>]*action="[^"]*login.php[^"]*"' | head -1 | sed 's/.*action="//;s/"//;s/&amp;/\&/g')
			case "$ACT" in http*) ;; *) ACT="$B/$ACT";; esac
			curl -s -o /dev/null -c "$J" -b "$J" --data-urlencode "csrf_token=$TOK" -d "form_sent=1&req_username=admin&req_password=secret123&login=Login" --data-urlencode "redirect_url=$B/index.php" "$ACT"
		fi
		for layout in sidebar wide; do for skin in calm meko; do
			for p in $PAGES; do
				out=$(curl -s -L -b "$J" -c "$J" -b "lo_layout=$layout; lo_skin=$skin" "$B/$p")
				n=$((n+1))
				if printf '%s' "$out" | grep -qiE 'warning:|fatal|deprecated:|notice:'; then
					hits=$((hits+1)); echo "HIT $lang/$who/$layout/$skin $p"
					printf '%s' "$out" | grep -iE -m2 'warning:|fatal|deprecated:|notice:' | cut -c1-200
				fi
			done
		done; done
	done
done
echo "pages checked: $n, hits: $hits"
[ $hits = 0 ]
