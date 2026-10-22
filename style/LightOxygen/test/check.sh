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
# only when the pun_pm extension is installed with its global nav link on
case "$(navof "$U")" in *nav_pun_pm*) has "pun_pm nav item has lo-nav-item" "$(navof "$U")" 'id="nav_pun_pm" class="lo-nav-item"';; esac
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
# a crafted /\host path must not become an off-site redirect
X=$(curl -s -D - -o /dev/null --path-as-is "$B/\\evil.com/../index.php?lo_layout=wide" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: *//')
case "$X" in "$B/"*) r=0;; *) r=1;; esac; ok "crafted path redirects on-site ($X)" $r
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
# ---------- Default avatars ----------
[ -f assets/avatar-default.svg ]; ok "avatar-default.svg exists" $?
T=$(curl -s "$B/viewtopic.php?id=41")
has "topic 41 shows real avatars" "$T" 'img/avatars/'
CSS=$(cat dist/assets/*.css)
has "css carries the :has() default avatar fallback" "$CSS" 'post-author:not(:has(.useravatar)):before'
UL=$(curl -s "$B/userlist.php" | tr -d '\n')
has "userlist styles real avatars by profile link" "$(curl -s "$B/userlist.php")" 'td.tc0 a\[href='
has "userlist css carries the default avatar circle" "$CSS" '#brd-userlist td.tc0 a:before'
[ "$(curl -s -o /dev/null -w '%{http_code}' "$B/style/LightOxygen/assets/avatar-default.svg")" = 200 ]; ok "default avatar svg is served" $?
# Rozhin has no avatar: no rule for her profile link, a rule for Hemin's
RID=$(curl -s "$B/userlist.php" | grep -o 'profile.php?id=[0-9]*">Rozhin' | grep -o '[0-9]*')
UH=$(curl -s "$B/userlist.php")
printf '%s' "$UH" | grep -q "a\[href=\"[^\"]*id=$RID\"\]"; [ $? = 1 ]; ok "user without avatar has no avatar rule (falls back to the default)" $?
has "user with avatar has a rule" "$UH" 'a\[href="[^"]*id=11"\]::before{background-image:url("[^"]*img/avatars/11'
has "activity panel props carry avatars" "$(curl -s "$B/index.php")" 'defaultAvatar'
# ---------- Task 11: hero, member avatars, starter avatars, pinned group, RTL pills ----------
has "guest index has the hero" "$(curl -s "$B/index.php")" 'class="lo-hero"'
HD=$(curl -s "$B/index.php" | tr -d '\n' | grep -o '<p class="lo-hero-desc">[^<]*</p>')
[ -n "$HD" ]; ok "hero description is escaped text (no markup inside its <p>)" $?
curl -s "$B/index.php" | tr -d '\n' | grep -o '<section class="lo-hero">.*</section>' | grep -q '<p><p\|<p class="lo-hero-desc"><p'; [ $? = 1 ]; ok "hero has no nested <p>" $?
curl -s -b "$J" "$B/index.php" | grep -q 'class="lo-hero"'; [ $? = 1 ]; ok "logged-in index has no hero" $?
curl -s "$B/viewforum.php?id=1" | grep -q 'class="lo-hero"'; [ $? = 1 ]; ok "viewforum has no hero" $?
ON=$(curl -s -b "$J" "$B/index.php" | tr -d '\n' | grep -o '<p class="lo-online">.*</p><p class="lo-updated">')
has "online block shows avatars" "$ON" 'lo-avatar\|img/avatars/'
has "online members are avatar links with a title" "$ON" '<a class="lo-who" href="[^"]*" title="admin"'
grep -rq topic-avatars "$(dirname "$0")/../dist/assets"; ok "built js has topic-avatars" $?
has "css styles the new-posts pill" "$CSS" '.item-newposts a'
RZ=$(mktemp)
L=$(curl -s -c "$RZ" -b "$RZ" "$B/login.php")
TOK=$(printf '%s' "$L" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -c "$RZ" -b "$RZ" --data-urlencode "csrf_token=$TOK" -d "form_sent=1&req_username=Rozhin&req_password=pass1234&login=Login" --data-urlencode "redirect_url=$B/index.php" "$ACT"
VK=$(curl -s -b "$RZ" "$B/viewforum.php?id=2")
has "kurdish viewforum 2 is kurdish" "$VK" 'dir="rtl"'
has "kurdish viewforum 2 has the new-posts pill" "$VK" 'class="item-newposts"'
has "kurdish viewforum 2 has the pinned group class" "$VK" 'id="brd-main" class="lo-has-pinned"'
has "kurdish viewforum 2 carries the translated group labels" "$VK" '\-\-lo-l-pinned:'
has "viewforum mounts topic-avatars" "$VK" 'data-topic-avatars'
curl -s -b "$RZ" "$B/viewforum.php?id=3" | grep -q 'lo-has-pinned'; [ $? = 1 ]; ok "forum without sticky topics has no pinned group" $?
has "search results mount topic-avatars" "$(curl -s -b "$RZ" "$B/search.php?action=show_recent")" 'data-topic-avatars'
rm -f "$RZ"
rm -f "$J"; exit $fail
