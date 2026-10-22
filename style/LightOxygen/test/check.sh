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
VF=$(curl -s "$B/viewforum.php?id=1")
printf '%s' "$VF" | grep -q 'data-island="activity-panel"'; [ $? = 1 ]; ok "viewforum no activity panel" $?
grep -rq activity-panel "$(dirname "$0")/../dist/assets"; ok "built js has activity-panel" $?
# brief says #qpost; core prints the container as #brd-qpost
QP=$(curl -s -b "$J" "$B/viewtopic.php?id=2")
has "viewtopic quick-post form" "$QP" 'id="brd-qpost"'
grep -rq quick-reply "$(dirname "$0")/../dist/assets"; ok "built js has quick-reply" $?
rm -f "$J"; exit $fail
