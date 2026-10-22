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
# core prints the redirect page as the body of the 302 (o_redirect_delay 0) or as the page itself
LR=$(curl -s -c "$J" -b "$J" --data-urlencode "csrf_token=$TOK" -d "form_sent=1&req_username=admin&req_password=secret123&login=Login" --data-urlencode "redirect_url=$B/index.php" "$ACT")
has "login redirect page is the theme's" "$LR" 'id="brd-redirect"'
hasnt0() { printf '%s' "$2" | grep -q "$3"; [ $? = 1 ]; ok "$1" $?; }
hasnt0 "redirect page has no theme placeholder" "$LR" '<!-- lo_'
hasnt0 "redirect page <html> is complete" "$(printf '%s' "$LR" | grep '<html')" '<html [^>]*<!--'
has "redirect page has the api meta" "$LR" '<meta name="api-base"'

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
has "index activity panel" "$(printf '%s' "$IX" | tr -d '\n')" 'data-island="activity-panel".*<li class="lo-act"><a href='
VT=$(curl -s "$B/viewtopic.php?id=2")
printf '%s' "$VT" | grep -q 'data-island="activity-panel"'; [ $? = 1 ]; ok "viewtopic no activity panel" $?
grep -rq activity-panel "$(dirname "$0")/../dist/assets"; ok "built js has activity-panel" $?
# the index main column lists every forum, so its sidebar has no Browse block; the activity rows lead with the last poster's avatar
hasnt0 "guest index has no browse block" "$IX" 'lo-browse'
has "viewforum 2 keeps the browse block" "$(curl -s "$B/viewforum.php?id=2")" 'class="lo-panel lo-browse"'
actof() { printf '%s' "$1" | tr -d '\n' | grep -o '<aside id="lo-activity">.*' | sed 's#</aside>.*##'; }
actrows() { actof "$1" | grep -o '<li class="lo-act">' | wc -l | tr -d ' '; }
has "index activity rows carry avatars" "$(actof "$IX")" '<li class="lo-act">.*\(lo-avatar\|img/avatars/\)'
[ "$(actrows "$IX")" = 10 ]; ok "index activity panel has 10 rows" $?
[ "$(actrows "$(curl -s "$B/viewforum.php?id=2")")" = 6 ]; ok "viewforum activity panel has 6 rows" $?
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

# ---------- Skin switch (calm / meko) ----------
has "default body lo-skin-calm" "$(curl -s "$B/index.php")" '<body class="[^"]*lo-skin-calm'
has "skin switch rendered" "$(curl -s "$B/viewtopic.php?id=2")" 'class="lo-skin-switch"'
has "both switches share one view menu" "$(curl -s "$B/index.php" | tr -d '\n')" '<details class="lo-view-menu">.*class="lo-layout-switch".*class="lo-skin-switch"'
has "admin index nav has navadmin" "$(navof "$(curl -s -b "$J" "$B/index.php")")" 'id="navadmin"'
has "admin index nav has navlogout" "$(navof "$(curl -s -b "$J" "$B/index.php")")" 'id="navlogout"'
S=$(mktemp)
R=$(curl -s -D - -o /dev/null -c "$S" -b "$S" "$B/viewforum.php?id=1&lo_skin=meko")
printf '%s' "$R" | head -1 | grep -q 302; ok "lo_skin=meko redirects" $?
has "the lo_skin cookie is HttpOnly and SameSite=Lax" "$R" 'lo_skin=meko;.*HttpOnly; SameSite=Lax'
has "theme pages send nosniff" "$(curl -s -D - -o /dev/null "$B/index.php")" 'X-Content-Type-Options: nosniff'
SL=$(printf '%s' "$R" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: *//')
case "$SL" in "$B/viewforum.php?id=1") r=0;; *) r=1;; esac; ok "skin redirect is the same URL under base_url ($SL)" $r
grep -q 'lo_skin	meko' "$S"; ok "lo_skin cookie set to meko" $?
has "body lo-skin-meko" "$(curl -s -b "$S" "$B/index.php")" '<body class="[^"]*lo-skin-meko'
curl -s -o /dev/null -c "$S" -b "$S" "$B/index.php?lo_skin=bogus"
grep -q 'lo_skin	bogus' "$S"; [ $? = 1 ]; ok "lo_skin=bogus does not set the cookie" $?
has "lo_skin=bogus keeps meko" "$(curl -s -b "$S" "$B/index.php")" '<body class="[^"]*lo-skin-meko'
has "bogus-only cookie falls back to calm" "$(curl -s -b 'lo_skin=bogus' "$B/index.php")" '<body class="[^"]*lo-skin-calm'
# o_lo_hero_mode=guests (the default) means guests under both looks
curl -s -b "$J" -b 'lo_skin=meko' "$B/index.php" | grep -q 'lo-hero'; [ $? = 1 ]; ok "hero_mode=guests: logged-in meko index has no lo-hero" $?
curl -s -b "$J" -b 'lo_skin=calm' "$B/index.php" | grep -q 'class="lo-hero"'; [ $? = 1 ]; ok "logged-in calm index has no hero" $?
X=$(curl -s -D - -o /dev/null --path-as-is "$B/\\evil.com/../index.php?lo_skin=meko" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: *//')
case "$X" in "$B/"*) r=0;; *) r=1;; esac; ok "crafted path redirects on-site for lo_skin ($X)" $r
rm -f "$S"

# ---------- Friendly URLs, polling, links, size ----------
php "$(dirname "$0")/pref_url_test.php"; ok "lo_pref_url behind rewrite.php (test/pref_url_test.php)" $?
hasnt0 "activity links leave no \$2 placeholder" "$(curl -s "$B/index.php")" '\$2'
has "only the index activity panel polls" "$(curl -s "$B/index.php")" '&quot;poll&quot;:true'
has "viewforum activity panel does not poll" "$(curl -s "$B/viewforum.php?id=1")" '&quot;poll&quot;:false'
has "topic lists say whether users can be looked up" "$(curl -s -b "$J" "$B/viewforum.php?id=1")" 'data-can-view-users="1"'
# API reads are quiet visits: two GET /online keep the admin's online.logged as it was
MYSQL=${MYSQL:-/Users/Shared/DBngin/mysql/8.0.33/bin/mysql}
if [ -x "$MYSQL" ]; then
	A="$B/api/index.php"
	AT=$(curl -s -X POST -H 'Content-Type: application/json' -d '{"username":"admin","password":"secret123"}' "$A?route=/auth/login" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
	curl -s -o /dev/null -H "Authorization: Bearer $AT" "$A?route=/me"
	sleep 1; curl -s -o /dev/null -H "Authorization: Bearer $AT" "$A?route=/online"
	q() { "$MYSQL" -uroot -h127.0.0.1 punbb2 -N -e "SELECT logged FROM forum_online WHERE user_id=2"; }
	L1=$(q); sleep 2; curl -s -o /dev/null -H "Authorization: Bearer $AT" "$A?route=/online"; L2=$(q)
	[ -n "$L1" ] && [ "$L1" = "$L2" ]; ok "GET /online does not refresh online.logged ($L1 $L2)" $?
else
	echo "skip online.logged check (set MYSQL to the mysql client)"
fi
CSZ=$(cat "$(dirname "$0")"/../dist/assets/*.css | wc -c | tr -d ' ')
[ "$CSZ" -le 135000 ]; ok "built CSS under 135 000 bytes ($CSZ)" $?

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
# ---------- Task 15: default hero image, View menu on phones ----------
has "empty o_lo_hero_bg: the hero shows the theme's default image" "$(curl -s "$B/index.php" | tr -d '\n' | grep -o '<section class="lo-hero"[^>]*>')" 'background:[^"]*url(&quot;[^&]*assets/hero-default.jpg'
[ -f assets/hero-default.jpg ]; ok "assets/hero-default.jpg is shipped" $?
grep -q '\.lo-view-menu {[^}]*hidden[^}]*md:block' src/css/header.css; [ $? = 1 ]; ok "the View menu is no longer hidden below md" $?
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
# ---------- Task 13: the lightoxygen_settings extension ----------
hasnt() { printf '%s' "$2" | grep -q "$3"; [ $? = 1 ]; ok "$1" $?; }
nowarn() { printf '%s' "$2" | grep -Eqi '(warning|notice|deprecated)(</b>)?:|fatal error'; [ $? = 1 ]; ok "$1 has no PHP warnings" $?; }
has "extensions page lists lightoxygen_settings" "$(curl -s -b "$J" "$B/admin/extensions.php?section=manage")" 'lightoxygen_settings'
SU="$B/admin/settings.php?section=lightoxygen"
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$J" "$SU")" = 200 ]; ok "settings page returns 200" $?
SP=$(curl -s -b "$J" "$SU")
has "settings page has the form" "$SP" 'name="form\[lo_hero_mode\]"'
has "settings form posts files" "$SP" 'enctype="multipart/form-data"'
nowarn "settings page" "$SP"
# lo_save -F ... : posts the whole form with the defaults, the given fields override them (PHP keeps the last value)
lo_save() {
	T=$(curl -s -b "$J" "$SU" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
	curl -s -b "$J" -L -F "csrf_token=$T" -F form_sent=1 -F 'form[lo_default_layout]=sidebar' -F 'form[lo_default_skin]=calm' -F 'form[lo_allow_switch]=1' \
		-F 'form[lo_hero_mode]=guests' -F 'form[lo_hero_title]=' -F 'form[lo_hero_text]=' -F 'form[lo_hero_bg]=' -F 'form[lo_show_member_avatars]=1' -F 'form[lo_category_colors]=1' \
		-F 'form[lo_footer_text]=' -F 'form[lo_accent]=' -F 'form[lo_font]=default' -F 'form[lo_color_scheme]=auto' -F 'form[lo_activity_refresh]=60' -F 'form[lo_show_stats]=1' -F 'form[lo_activity_count]=10' \
		-F 'form[lo_social_links]=' -F 'form[lo_custom_css]=' "$@" "$SU"
}
# temp files in one folder; on any exit: extension enabled again, defaults restored, uploads removed, temp files gone
LO_TMP=$(mktemp -d); LO_OFF=0
lo_flip_link() { curl -s -b "$J" "$B/admin/extensions.php?section=manage" | grep -o 'href="[^"]*flip=lightoxygen_settings[^"]*">[^<]*'; }
lo_flip() { curl -s -o /dev/null -b "$J" "$(lo_flip_link | head -1 | sed 's/href="//;s/".*//;s/&amp;/\&/g')"; }
lo_cleanup() {
	[ "$LO_OFF" = 1 ] && lo_flip
	lo_save -F 'lo_remove[hero]=x' -F 'lo_remove[logo]=x' -F 'lo_remove[favicon]=x' >/dev/null
	[ -x "$MYSQL" ] && "$MYSQL" -uroot -h127.0.0.1 punbb2 -e "UPDATE forum_users SET lo_layout=NULL, lo_skin=NULL, lo_scheme=NULL WHERE id IN (2,12)" 2>/dev/null
	rm -rf "$LO_TMP" "$J"
}
trap lo_cleanup EXIT
trap 'exit 1' INT TERM
heroof() { printf '%s' "$1" | tr -d '\n' | grep -o '<section class="lo-hero"[^>]*>'; }
lo_save -F 'form[lo_hero_mode]=none' >/dev/null
hasnt "hero_mode=none: guest index has no hero" "$(curl -s "$B/index.php")" 'class="lo-hero"'
lo_save -F 'form[lo_hero_mode]=all' >/dev/null
has "hero_mode=all: logged-in calm index has the hero" "$(curl -s -b "$J" -b 'lo_skin=calm' "$B/index.php")" 'class="lo-hero"'
R=$(lo_save -F 'form[lo_hero_mode]=bogus' -F 'form[lo_activity_refresh]=5' -F 'form[lo_accent]=#12345g' -F 'form[lo_social_links]=X|javascript:alert(1)')
has "invalid values re-render the page with an error" "$R" 'class="ct-box error-box"'
has "the error page keeps the form" "$R" 'name="form\[lo_hero_mode\]"'
has "config unchanged after the rejected save" "$(curl -s -b "$J" -b 'lo_skin=calm' "$B/index.php")" 'class="lo-hero"'
PNG=$LO_TMP/p.png; printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' | base64 -d > "$PNG"
TXT=$LO_TMP/t.png; printf 'not an image' > "$TXT"
has "a non-image upload is rejected" "$(lo_save -F "lo_hero_file=@$TXT;type=image/png;filename=x.png")" 'class="ct-box error-box"'
lo_save -F "lo_hero_file=@$PNG;type=image/png;filename=x.png" >/dev/null
[ -f "$(dirname "$0")/../../../img/lightoxygen"/hero.png ]; ok "upload stored as img/lightoxygen/hero.png" $?
SP=$(curl -s -b "$J" "$SU")
has "settings page names the uploaded file" "$SP" 'lo-ext-current.*hero.png'
hasnt "the URL field does not show the internal path" "$SP" 'name="form\[lo_hero_bg\]"[^>]*value="img/lightoxygen'
has "a save with the URL field empty keeps the upload" "$(lo_save >/dev/null; heroof "$(curl -s "$B/index.php")")" 'url(&quot;[^&]*img/lightoxygen/hero.png'
hasnt "unknown form keys are not written" "$(lo_save -F 'form[board_title]=Hacked' >/dev/null; curl -s "$B/index.php")" '>Hacked<'
has "uploaded hero background on the index" "$(heroof "$(curl -s "$B/index.php")")" 'url(&quot;[^&]*img/lightoxygen/hero.png'
has "typed hero URL on the index" "$(heroof "$(lo_save -F 'form[lo_hero_bg]=https://example.com/bg.jpg' >/dev/null; curl -s "$B/index.php")")" 'url(&quot;https://example.com/bg.jpg'
[ ! -f "$(dirname "$0")/../../../img/lightoxygen"/hero.png ]; ok "a typed URL replaces the uploaded file" $?
has "a javascript: hero URL is rejected" "$(lo_save -F 'form[lo_hero_bg]=javascript:alert(1)')" 'class="ct-box error-box"'
lo_save -F 'form[lo_accent]=#aa3300' -F 'form[lo_color_scheme]=dark' -F 'form[lo_allow_switch]=0' --form-string 'form[lo_footer_text]=<b>Hi</b>' -F 'form[lo_social_links]=Site|https://example.com/' \
	--form-string 'form[lo_custom_css]=body{}</st</styleyle><script>alert(1)</script>' -F 'form[lo_hero_title]=Welcome home' -F 'form[lo_category_colors]=0' -F 'form[lo_show_stats]=0' -F 'form[lo_activity_refresh]=0' -F 'form[lo_font]=system' >/dev/null
GI=$(curl -s "$B/index.php")
has "accent on <html>" "$GI" '<html [^>]*style="--color-accent-500:#aa3300'
has "color scheme on <html>" "$GI" '<html [^>]*data-theme="dark"'
hasnt "allow_switch=0 hides the view menu" "$GI" 'class="lo-view-menu"'
has "footer text escaped" "$GI" '&lt;b&gt;Hi&lt;/b&gt;'
has "social link in the footer" "$GI" 'href="https://example.com/"'
hasnt "custom css cannot close its <style>" "$GI" 'body{}</style\|body{}</st</style'
has "custom css printed" "$GI" 'body{}'
has "hero title setting" "$GI" 'lo-hero-title">Welcome home<'
has "category colours off" "$GI" '<body class="[^"]*lo-no-cat-colors'
hasnt "stats off" "$GI" 'class="lo-panel lo-stats"'
has "activity refresh 0 in the props" "$GI" '&quot;refresh&quot;:0'
hasnt "system font skips the web fonts" "$GI" 'fonts.googleapis.com'
nowarn "index with settings" "$GI"
has "settings form has the activity count" "$SP" 'name="form\[lo_activity_count\]"'
has "settings form has the index browse box" "$SP" 'name="form\[lo_index_browse\]"'
lo_save -F 'form[lo_index_browse]=1' -F 'form[lo_activity_count]=7' >/dev/null
IB=$(curl -s "$B/index.php")
has "index_browse=1 brings Browse back to the guest index" "$IB" 'class="lo-panel lo-browse"'
[ "$(actrows "$IB")" = 7 ]; ok "activity_count=7 renders 7 rows" $?
has "activity_count=7 is the island limit" "$IB" '&quot;limit&quot;:7'
has "an activity count of 31 is rejected" "$(lo_save -F 'form[lo_activity_count]=31')" 'class="ct-box error-box"'
has "an activity count of 4 is rejected" "$(lo_save -F 'form[lo_activity_count]=4')" 'class="ct-box error-box"'
lo_save >/dev/null
IB=$(curl -s "$B/index.php")
hasnt0 "index_browse back to 0" "$IB" 'lo-browse'
[ "$(actrows "$IB")" = 10 ]; ok "activity_count back to 10" $?
lo_save -F 'form[lo_default_layout]=wide' -F 'form[lo_default_skin]=meko' >/dev/null
has "board default layout and skin" "$(curl -s "$B/index.php")" '<body class="lo-layout-wide lo-skin-meko'
lo_save -F 'form[lo_default_skin]=meko' -F 'form[lo_show_member_avatars]=0' >/dev/null
hasnt "member avatars off hides the row under the hero" "$(curl -s "$B/index.php")" 'class="lo-members"'
lo_save -F 'form[lo_hero_mode]=guests' -F "lo_hero_file=@$PNG;type=image/png;filename=x.png" >/dev/null
# disable the extension: the theme falls back to its code defaults
lo_flip; LO_OFF=1
lo_flip_link | grep -q '>Enable$'; ok "the extension is now disabled" $?
DI=$(curl -s "$B/index.php")
has "disabled: guest index still renders the hero" "$DI" 'class="lo-hero"'
has "disabled: hero back to the default image" "$(heroof "$DI")" 'url(&quot;[^&]*assets/hero-default.jpg'
nowarn "index with the extension disabled" "$DI"
hasnt0 "disabled: no browse block on the index" "$DI" 'lo-browse'
[ "$(actrows "$DI")" = 10 ]; ok "disabled: 10 activity rows" $?
lo_flip; LO_OFF=0
lo_flip_link | grep -q '>Disable$'; ok "the extension is enabled again" $?
has "re-enabled: the uploaded hero is back" "$(heroof "$(curl -s "$B/index.php")")" 'url(&quot;[^&]*img/lightoxygen/hero.png'
lo_save -F 'lo_remove[hero]=Remove' >/dev/null
[ ! -f "$(dirname "$0")/../../../img/lightoxygen"/hero.png ]; ok "remove deletes the hero file" $?
has "after remove the hero shows the default image" "$(heroof "$(curl -s "$B/index.php")")" 'url(&quot;[^&]*assets/hero-default.jpg'
SVG=$LO_TMP/ok.svg; printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#2f5f8a"/></svg>' > "$SVG"
BAD=$LO_TMP/bad.svg; printf '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://evil.example/"><circle r="4" onclick="x()"/></a></svg>' > "$BAD"
has "an SVG logo with handlers or external links is rejected" "$(lo_save -F "lo_logo_file=@$BAD;type=image/svg+xml;filename=l.svg")" 'class="ct-box error-box"'
# each payload must be refused (stored XSS on the board origin otherwise)
NS='xmlns="http://www.w3.org/2000/svg"'
i=0
for x in "<svg $NS xmlns:s=\"http://www.w3.org/2000/svg\"><s:script>alert(1)</s:script></svg>" \
	"<svg $NS xmlns:h=\"http://www.w3.org/1999/xhtml\"><h:script>alert(1)</h:script></svg>" \
	"<svg $NS><animate attributeName=\"href\" values=\"&#106;avascript:alert(1)\"/></svg>" \
	"<svg $NS><set attributeName=\"href\" to=\"&#x6a;avascript:alert(1)\"/></svg>" \
	"<svg $NS><style>@imp\\ort url(//evil.example/x.css)</style></svg>" \
	"<!DOCTYPE svg [<!ENTITY x \"y\">]><svg $NS><text>&x;</text></svg>" \
	"<svg $NS><rect style=\"fill:red\"/></svg>" \
	"<svg $NS><circle fill=\"\\75rl(http://evil/x#a)\"/></svg>"; do
	i=$((i+1)); printf '%s' "$x" > "$LO_TMP/x$i.svg"
	has "SVG payload $i is rejected" "$(lo_save -F "lo_logo_file=@$LO_TMP/x$i.svg;type=image/svg+xml;filename=l.svg")" 'class="ct-box error-box"'
done
[ ! -f "$(dirname "$0")/../../../img/lightoxygen/logo.svg" ]; ok "no rejected SVG was stored" $?
printf '%s' "<svg $NS viewBox=\"0 0 8 8\"><defs><linearGradient id=\"g\"><stop offset=\"0\" stop-color=\"#2f5f8a\"/></linearGradient></defs><circle cx=\"4\" cy=\"4\" r=\"4\" fill=\"url(#g)\"/></svg>" > "$SVG"
lo_save -F "lo_logo_file=@$SVG;type=image/svg+xml;filename=l.svg" -F "lo_favicon_file=@$PNG;type=image/png;filename=f.png" >/dev/null
LI=$(curl -s "$B/index.php")
has "uploaded logo in the header" "$LI" 'class="lo-logo"[^>]*><img src="[^"]*img/lightoxygen/logo.svg?v='
has "uploaded favicon linked" "$LI" '<link rel="icon" href="[^"]*img/lightoxygen/favicon.png?v='
lo_save -F 'lo_remove[logo]=Remove' >/dev/null; lo_save -F 'lo_remove[favicon]=Remove' >/dev/null
has "logo removed: the theme logo is back" "$(curl -s "$B/index.php")" 'assets/logo.svg'
# ---------- Task 16: profile preferences, colour scheme, prefix suggestions, pun_pm and pun_attachment ----------
DB="$MYSQL -uroot -h127.0.0.1 punbb2 -N"
# lo_login user pass jar
lo_login() {
	L=$(curl -s -c "$3" -b "$3" "$B/login.php")
	T=$(printf '%s' "$L" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
	curl -s -o /dev/null -c "$3" -b "$3" --data-urlencode "csrf_token=$T" -d "form_sent=1&req_username=$1&req_password=$2&login=Login" --data-urlencode "redirect_url=$B/index.php" "$ACT"
}
# every successful control of the page's first frm-form as a urlencoded body (so a save changes only the overrides appended to it)
formbody() { php -r '$d=new DOMDocument; @$d->loadHTML(stream_get_contents(STDIN)); $x=new DOMXPath($d); $f=$x->query("//form[contains(@class,\"frm-form\")]")->item(0); $o=array();
	foreach ($x->query(".//input|.//select|.//textarea", $f) as $e) { $n=$e->getAttribute("name"); $t=strtolower($e->getAttribute("type")); if ($n==="" || in_array($t, array("submit","file"))) continue;
		if ($e->nodeName==="select") { foreach ($x->query(".//option[@selected]", $e) as $op) $o[]=urlencode($n)."=".urlencode($op->getAttribute("value")); continue; }
		if (in_array($t, array("checkbox","radio")) && !$e->hasAttribute("checked")) continue;
		$o[]=urlencode($n)."=".urlencode($e->nodeName==="textarea" ? $e->textContent : $e->getAttribute("value")); }
	echo implode("&", $o);'; }
for c in lo_layout lo_skin lo_scheme; do
	[ -n "$($DB -e "SHOW COLUMNS FROM forum_users LIKE '$c'")" ]; ok "users.$c column exists" $?
done
has "the manifest is version 1.2.1" "$(cat "$(dirname "$0")/../../../extensions/lightoxygen_settings/manifest.xml")" '<version>1.2.1</version>'
# a member's View-menu switch is stored in their row and follows them to a new session without the cookie
$DB -e "UPDATE forum_users SET lo_layout=NULL, lo_skin=NULL, lo_scheme=NULL WHERE id=2"
# without the switch link's lo_t token (a cross-site <img>, say) only the cookie changes
NT=$LO_TMP/notoken; cp "$J" "$NT"
curl -s -o /dev/null -b "$NT" -c "$NT" "$B/viewforum.php?id=1&lo_layout=wide"
[ "$($DB -e "SELECT lo_layout FROM forum_users WHERE id=2")" = NULL ]; ok "logged-in lo_layout=wide without lo_t leaves users.lo_layout unchanged" $?
grep -q 'lo_layout	wide' "$NT"; ok "logged-in lo_layout=wide without lo_t still sets the cookie" $?
curl -s -o /dev/null -b "$J" -c "$J" "$B/viewforum.php?id=1&lo_layout=wide&lo_t=bogus"
[ "$($DB -e "SELECT lo_layout FROM forum_users WHERE id=2")" = NULL ]; ok "a wrong lo_t is not stored" $?
WL=$(curl -s -b "$J" "$B/viewforum.php?id=1" | grep -o 'href="[^"]*lo_layout=wide[^"]*"' | head -1 | sed 's/href="//;s/"//;s/&amp;/\&/g')
case "$WL" in *lo_t=*) r=0;; *) r=1;; esac; ok "a member's switch link carries lo_t" $r
hasnt "a guest's switch link has no lo_t" "$(curl -s "$B/viewforum.php?id=1")" 'lo_t='
curl -s -o /dev/null -b "$J" -c "$J" "$WL"
[ "$($DB -e "SELECT lo_layout FROM forum_users WHERE id=2")" = wide ]; ok "logged-in lo_layout=wide with the link's lo_t is stored in users.lo_layout" $?
LT=$(printf '%s' "$WL" | sed 's/.*lo_t=//;s/&.*//')
curl -s -o /dev/null -b "$J" -c "$J" "$B/index.php?lo_skin=bogus&lo_t=$LT"
[ "$($DB -e "SELECT lo_skin FROM forum_users WHERE id=2")" = NULL ]; ok "lo_skin=bogus is not stored" $?
NJ=$LO_TMP/fresh; lo_login admin secret123 "$NJ"
grep -q 'lo_layout' "$NJ"; [ $? = 1 ]; ok "the new session has no lo_layout cookie" $?
has "the stored layout survives a new session" "$(curl -s -b "$NJ" "$B/index.php")" '<body class="lo-layout-wide'
curl -s -o /dev/null -b "$NJ" -c "$NJ" "$B/index.php?lo_layout=sidebar"
# guests: the cookie only
GJ=$LO_TMP/guest
curl -s -o /dev/null -c "$GJ" -b "$GJ" "$B/index.php?lo_scheme=dark"
grep -q 'lo_scheme	dark' "$GJ"; ok "guest lo_scheme=dark sets the cookie" $?
has "lo_scheme=dark sets data-theme=dark on <html>" "$(curl -s -b "$GJ" "$B/index.php" | grep '<html')" 'data-theme="dark"'
curl -s -o /dev/null -c "$GJ" -b "$GJ" "$B/index.php?lo_scheme=light"
has "lo_scheme=light sets data-theme=light" "$(curl -s -b "$GJ" "$B/index.php" | grep '<html')" 'data-theme="light"'
curl -s -o /dev/null -c "$GJ" -b "$GJ" "$B/index.php?lo_scheme=auto"
hasnt "lo_scheme=auto leaves data-theme off" "$(curl -s -b "$GJ" "$B/index.php" | grep '<html')" 'data-theme='
has "the View menu has the colour scheme group" "$(curl -s "$B/viewtopic.php?id=2" | tr -d '\n')" '<details class="lo-view-menu">.*class="lo-scheme-switch".*lo_scheme=dark'
has "css applies the dark tokens under data-theme=dark" "$CSS" '\[data-theme=dark\][^{]*{[^}]*--color-page:#1a1d21'
# the profile Appearance fieldset (Rozhin, Kurdish)
RJ=$LO_TMP/rozhin; lo_login Rozhin pass1234 "$RJ"
PU="$B/profile.php?section=settings&id=12"
PF=$(curl -s -b "$RJ" "$PU")
has "profile settings has the Appearance fieldset" "$PF" 'class="frm-group group[0-9]* lo-appearance"'
for c in lo_layout lo_skin lo_scheme; do has "profile settings has form[$c]" "$PF" "name=\"form\[$c\]\""; done
has "the Appearance labels are Kurdish for Rozhin" "$PF" 'lo-appearance"><legend class="group-legend"><strong>دەرکەوتن'
nowarn "profile settings" "$PF"
curl -s -o /dev/null -b "$RJ" -c "$RJ" --data "$(printf '%s' "$PF" | formbody)&form%5Blo_layout%5D=wide&form%5Blo_skin%5D=meko&form%5Blo_scheme%5D=dark&update=1" "$PU"
[ "$($DB -e "SELECT CONCAT(lo_layout,'/',lo_skin,'/',lo_scheme) FROM forum_users WHERE id=12")" = wide/meko/dark ]; ok "the profile saves layout, look and scheme" $?
RI=$(curl -s -b "$RJ" "$B/index.php")
has "the saved profile choice applies" "$RI" '<body class="lo-layout-wide lo-skin-meko'
has "the saved scheme applies" "$(printf '%s' "$RI" | grep '<html')" 'data-theme="dark"'
# a View-menu cookie from a link without lo_t (the saved profile value overrides it) is dropped by choosing the board default
curl -s -o /dev/null -b "$RJ" -c "$RJ" "$B/index.php?lo_skin=calm"
grep -q 'lo_skin	calm' "$RJ"; ok "a switch without lo_t sets only the cookie for a member" $?
PF=$(curl -s -b "$RJ" "$PU")
curl -s -o /dev/null -b "$RJ" -c "$RJ" --data "$(printf '%s' "$PF" | formbody)&form%5Blo_layout%5D=bogus&form%5Blo_skin%5D=&form%5Blo_scheme%5D=&update=1" "$PU"
[ "$($DB -e "SELECT CONCAT(IFNULL(lo_layout,'-'),IFNULL(lo_skin,'-'),IFNULL(lo_scheme,'-')) FROM forum_users WHERE id=12")" = '---' ]; ok "invalid and empty profile values store NULL (board default)" $?
grep -q 'lo_skin	' "$RJ"; [ $? = 1 ]; ok "the board default in the profile clears the lo_skin cookie" $?
# prefix suggestions through the real API
SG=$(curl -s "$B/api/index.php?route=/search&q=hel")
has "API search q=hel returns topics" "$SG" '"data":\[{"id":'
# pun_pm and pun_attachment inside the theme
PM=$(curl -s -b "$J" "$B/misc.php?section=pun_pm&pmpage=inbox")
has "pun_pm nav item has its icon" "$(navof "$PM")" 'id="nav_pun_pm" class="lo-nav-item[^"]*"><svg class="lo-icon"'
has "pm inbox renders" "$PM" 'class="pun_pm_list"\|This box is empty'
nowarn "pm inbox" "$PM"
nowarn "pm compose" "$(curl -s -b "$J" "$B/misc.php?section=pun_pm&pmpage=write")"
AT=$(curl -s -b "$J" "$B/viewtopic.php?id=2")
has "topic 2 shows its attachment box" "$AT" '<div class="attachments">'
nowarn "topic with an attachment" "$AT"
has "post form has the upload field" "$(curl -s -b "$J" "$B/post.php?fid=1")" 'name="attach_file"'
for s in '.pun_pm_list' '.pun_pm_msg' '#brd-main .attachments' '#nav_pun_pm' ; do has "css styles $s" "$CSS" "$s"; done
exit $fail
