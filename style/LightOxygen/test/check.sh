#!/bin/sh
# Usage: test/check.sh [base_url]   (admin / secret123 must exist)
B=${1:-http://127.0.0.1:8085}
J=$(mktemp); fail=0
ok() { if [ "$2" = 0 ]; then echo "ok   $1"; else echo "FAIL $1"; fail=1; fi; }
has() { printf '%s' "$2" | grep -q "$3"; ok "$1" $?; }

G=$(curl -s "$B/index.php")
navof() { printf '%s' "$1" | tr -d '\n' | grep -o '<nav.*</nav>'; }
hid() { has "$1" "$(printf '%s' "$2" | tr -d '\n')" '<div id="lo-dropdown" hidden aria-hidden="true">'; }
hid "guest stock block hidden" "$G"
has "guest navlogin in nav" "$(printf '%s' "$G" | tr -d '\n' | grep -o '<nav.*</nav>')" 'id="navlogin"'
has "guest keywords input" "$G" 'name="keywords"'
has "guest api-csrf meta" "$G" '<meta name="api-csrf"'

L=$(curl -s -c "$J" -b "$J" "$B/login.php")
TOK=$(printf '%s' "$L" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
ACT=$(printf '%s' "$L" | grep -o '<form[^>]*action="[^"]*login.php[^"]*"' | head -1 | sed 's/.*action="//;s/"//;s/&amp;/\&/g')
case "$ACT" in http*) ;; *) ACT="$B/$ACT";; esac
curl -s -o /dev/null -c "$J" -b "$J" --data-urlencode "csrf_token=$TOK" -d "form_sent=1&req_username=admin&req_password=secret123&login=Login" --data-urlencode "redirect_url=$B/index.php" "$ACT"

U=$(curl -s -b "$J" "$B/index.php")
has "user navlogout in nav" "$(navof "$U")" 'id="navlogout"'
hid "user stock block hidden" "$U"
has "user details is lo-user-menu" "$U" '<details class="lo-auth lo-user-menu"'
AD=$(curl -s -b "$J" "$B/admin/index.php")
has "admin menu in lo-admin-menu" "$(printf '%s' "$AD" | tr -d '\n')" '<aside id="lo-admin-menu">[^<]*<div class="admin-menu '
hid "admin stock block hidden" "$AD"
# misc.php asks for confirmation without a token; post the confirm form it returns
C=$(curl -s -b "$J" "$B/misc.php?action=markread")
CT=$(printf '%s' "$C" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
CA=$(printf '%s' "$C" | grep -o '<form[^>]*action="[^"]*misc.php[^"]*"' | head -1 | sed 's/.*action="//;s/"//;s/&amp;/\&/g')
case "$CA" in http*) ;; *) CA="$B/$CA";; esac
M=$(curl -s -b "$J" -D - --data-urlencode "csrf_token=$CT" "$CA")
printf '%s' "$M" | head -1 | grep -q 302; ok "markread 302" $?
printf '%s' "$M" | grep -qi 'warning:'; [ $? = 1 ]; ok "markread no warning" $?
H=$(curl -s "$B/help.php?section=bbcode")
printf '%s' "$H" | grep -qi 'warning:\|fatal'; [ $? = 1 ]; ok "help no warning" $?
IX=$(curl -s "$B/index.php")
has "index activity panel" "$(printf '%s' "$IX" | tr -d '\n')" 'data-island="activity-panel".*<li><a href='
VT=$(curl -s "$B/viewtopic.php?id=2")
printf '%s' "$VT" | grep -q 'data-island="activity-panel"'; [ $? = 1 ]; ok "viewtopic no activity panel" $?
grep -rq activity-panel "$(dirname "$0")/../dist/assets"; ok "built js has activity-panel" $?
# brief says #qpost; core prints the container as #brd-qpost
QP=$(curl -s -b "$J" "$B/viewtopic.php?id=2")
has "viewtopic quick-post form" "$QP" 'id="brd-qpost"'
grep -rq quick-reply "$(dirname "$0")/../dist/assets"; ok "built js has quick-reply" $?

# ---------- Sidebar and layout switch ----------
for p in index.php viewforum.php?id=1 "search.php?action=show_recent" userlist.php; do
	has "sidebar on $p" "$(curl -s "$B/$p")" '<aside id="lo-sidebar"'
done
for p in viewtopic.php?id=2 post.php?fid=1 profile.php?id=2 admin/index.php; do
	curl -s -b "$J" "$B/$p" | grep -q 'id="lo-sidebar"'; [ $? = 1 ]; ok "no sidebar on $p" $?
done
curl -s "$B/login.php" | grep -q 'id="lo-sidebar"'; [ $? = 1 ]; ok "no sidebar on login.php" $?
has "default body lo-layout-sidebar" "$(curl -s "$B/index.php")" '<body class="lo-layout-sidebar'
K=$(mktemp)
R=$(curl -s -D - -o /dev/null -c "$K" -b "$K" "$B/viewforum.php?id=1&lo_layout=wide")
printf '%s' "$R" | head -1 | grep -q 302; ok "lo_layout=wide redirects" $?
printf '%s' "$R" | grep -i '^location:' | grep -q 'viewforum.php?id=1'; ok "redirect keeps the URL" $?
printf '%s' "$R" | grep -i '^location:' | grep -q 'lo_layout'; [ $? = 1 ]; ok "redirect drops lo_layout" $?
grep -q 'lo_layout	wide' "$K"; ok "lo_layout cookie set to wide" $?
W=$(curl -s -b "$K" "$B/index.php")
has "body lo-layout-wide" "$W" '<body class="lo-layout-wide'
has "wide index keeps activity panel" "$W" 'data-island="activity-panel"'
printf '%s' "$W" | grep -q 'class="lo-panel lo-browse"'; [ $? = 1 ]; ok "wide index has no browse block" $?
curl -s -b "$K" "$B/viewforum.php?id=1" | grep -q 'id="lo-sidebar"'; [ $? = 1 ]; ok "wide viewforum has no sidebar" $?
curl -s -o /dev/null -c "$K" -b "$K" "$B/index.php?lo_layout=sidebar"
has "lo_layout=sidebar restores" "$(curl -s -b "$K" "$B/index.php")" '<body class="lo-layout-sidebar'
rm -f "$K"

# ---------- No PHP warnings on the crawl list, as guest and as admin ----------
CRAWL="index.php viewforum.php?id=1 viewtopic.php?id=2 userlist.php search.php search.php?action=search&keywords=test search.php?action=show_recent search.php?action=show_unanswered
profile.php?id=2 profile.php?section=identity&id=2 profile.php?section=settings&id=2 profile.php?section=signature&id=2 profile.php?section=avatar&id=2 profile.php?section=admin&id=2
register.php login.php help.php?section=bbcode misc.php?action=rules post.php?fid=1 post.php?tid=2 edit.php?id=2 moderate.php?fid=1 moderate.php?fid=1&tid=2"
# db_update.php and install.php are left out: they are not board pages and may change the database
for a in bans categories censoring extensions forums groups index prune ranks reindex reports users; do CRAWL="$CRAWL admin/$a.php"; done
for s in setup features announcements registration email maintenance; do CRAWL="$CRAWL admin/settings.php?section=$s"; done
for who in guest admin; do
	bad=""
	for p in $CRAWL; do
		if [ $who = admin ]; then o=$(curl -s -b "$J" "$B/$p"); else o=$(curl -s "$B/$p"); fi
		printf '%s' "$o" | grep -Eqi '(warning|notice|deprecated)(</b>)?:|fatal error' && bad="$bad $p"
	done
	[ -z "$bad" ]; ok "no PHP warnings as $who${bad:+ (}$bad${bad:+)}" $?
done
rm -f "$J"; exit $fail
