#!/bin/bash
BASE="http://127.0.0.1:8000/api"
PASS=0; FAIL=0

check() {
    local label="$1"
    local result="$2"
    local expect="$3"
    if echo "$result" | grep -q "$expect"; then
        echo "  [PASS] $label"
        PASS=$((PASS+1))
    else
        echo "  [FAIL] $label | got: $(echo $result | head -c 200)"
        FAIL=$((FAIL+1))
    fi
}

echo "=========================================="
echo " PikPakGo Full API QA Test"
echo "=========================================="

echo -e "\n--- SETUP: Login ---"
ADMIN_TOKEN=$(curl -s -X POST $BASE/auth/login -H "Content-Type: application/json" \
  -d '{"email":"admin@pikpakgo.com","password":"Admin123!"}' | python -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('token',''))")
CUST_TOKEN=$(curl -s -X POST $BASE/auth/login -H "Content-Type: application/json" \
  -d '{"email":"customer@pikpakgo.com","password":"Test123!"}' | python -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('token',''))")
echo "  Admin token: $([ -n "$ADMIN_TOKEN" ] && echo OBTAINED || echo MISSING)"
echo "  Customer token: $([ -n "$CUST_TOKEN" ] && echo OBTAINED || echo MISSING)"

echo -e "\n--- HEALTH ---"
check "[1] Health" "$(curl -s $BASE/health)" '"status":"ok"'

echo -e "\n--- PUBLIC SEARCH ---"
check "[2] Search Properties" \
  "$(curl -s -X POST $BASE/public/search/properties -H 'Content-Type: application/json' -d '{"checkIn":"2027-06-01","checkOut":"2027-06-07","guests":2}')" \
  '"success":true'

check "[3] Search Hotels" \
  "$(curl -s -X POST $BASE/public/search/hotels -H 'Content-Type: application/json' -d '{"checkIn":"2027-06-01","checkOut":"2027-06-07","guests":2}')" \
  '"success":true'

check "[4] Destinations" "$(curl -s $BASE/public/search/destinations)" '"success":true'
check "[5] Popular Destinations" "$(curl -s $BASE/public/search/popular-destinations)" '"success":true'

echo -e "\n--- GUEST SESSION ---"
SESS=$(curl -s -X POST $BASE/public/guest/session/create -H 'Content-Type: application/json' -d '{}')
check "[6] Create Guest Session" "$SESS" '"session_id"'
SESSION_ID=$(echo $SESS | python -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('session_id',''))")

check "[7] Get Guest Session" "$(curl -s $BASE/public/guest/session/$SESSION_ID)" '"success":true'

echo -e "\n--- AUTH ---"
check "[8] Register Customer" \
  "$(curl -s -X POST $BASE/auth/register -H 'Content-Type: application/json' \
    -d '{"first_name":"Jane","last_name":"Test","email":"jane_final_qa@example.com","password":"Test123!","password_confirmation":"Test123!","user_type":"customer"}')" \
  '"success"'

check "[9] Login Admin" "$([ -n "$ADMIN_TOKEN" ] && echo ok)" "ok"
check "[10] Auth Me (Admin)" \
  "$(curl -s $BASE/auth/me -H "Authorization: Bearer $ADMIN_TOKEN")" \
  '"user_type":"admin"'

check "[11] Auth Me (Customer)" \
  "$(curl -s $BASE/auth/me -H "Authorization: Bearer $CUST_TOKEN")" \
  '"user_type":"customer"'

check "[12] No Auth Rejected (401)" \
  "$(curl -s -o /dev/null -w "%{http_code}" $BASE/auth/me)" \
  "401"

echo -e "\n--- PUBLIC PROPERTIES ---"
check "[13] List Properties" "$(curl -s $BASE/public/properties)" '"success":true'

echo -e "\n--- OWNERREZ DIRECT APIs ---"
check "[14] OwnerRez Properties" \
  "$(curl -s $BASE/ownerrez/properties)" '"success":true'

check "[15] OwnerRez Property Detail" \
  "$(curl -s $BASE/ownerrez/properties/orp5b27f9x)" '"success":true'

check "[16] OwnerRez Availability" \
  "$(curl -s -X POST $BASE/ownerrez/properties/orp5b27f9x/availability \
    -H 'Content-Type: application/json' \
    -d '{"checkin":"2027-06-01","checkout":"2027-06-07","guests":2}')" \
  '"available"'

check "[17] OwnerRez Pricing/Quote" \
  "$(curl -s -X POST $BASE/ownerrez/properties/orp5b27f9x/pricing \
    -H 'Content-Type: application/json' \
    -d '{"checkin":"2027-06-01","checkout":"2027-06-07","guests":2}')" \
  '"success":true'

check "[18] OwnerRez Create Booking" \
  "$(curl -s -X POST $BASE/ownerrez/bookings \
    -H 'Content-Type: application/json' \
    -d '{"propertyId":"orp5b27f9x","checkin":"2027-12-01","checkout":"2027-12-07","guests":2,"guest":{"firstName":"QA","lastName":"Test","email":"qa@example.com","phone":"+12125551234"}}')" \
  '"reservationStatus":"CONFIRMED"'

echo -e "\n--- ADMIN APIs (auth required) ---"
check "[19] Admin Properties List" \
  "$(curl -s $BASE/admin/properties -H "Authorization: Bearer $ADMIN_TOKEN")" \
  '"success":true'

check "[20] Admin Properties Sync" \
  "$(curl -s -X POST $BASE/admin/properties/sync \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H 'Content-Type: application/json' -d '{"provider":"ownerrez"}')" \
  '"success":true'

MU_RESP=$(curl -s -X POST $BASE/admin/pricing-markups \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"QA Test 10%","markup_type":"percentage","markup_percentage":10,"provider":"ownerrez","is_active":true}')
check "[21] Admin Create Pricing Markup" "$MU_RESP" '"success":true'
MU_ID=$(echo $MU_RESP | python -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)

check "[22] Admin List Pricing Markups" \
  "$(curl -s $BASE/admin/pricing-markups -H "Authorization: Bearer $ADMIN_TOKEN")" \
  '"success":true'

check "[23] Admin Bookings List" \
  "$(curl -s $BASE/admin/bookings -H "Authorization: Bearer $ADMIN_TOKEN")" \
  '"success":true'

check "[24] Admin Blocked for Customer (403)" \
  "$(curl -s -o /dev/null -w "%{http_code}" $BASE/admin/properties -H "Authorization: Bearer $CUST_TOKEN")" \
  "403"

check "[25] Swagger Docs Available" \
  "$(curl -s -o /dev/null -w "%{http_code}" $BASE/documentation)" \
  "200"

echo -e "\n=========================================="
echo " Results: $PASS passed, $FAIL failed"
echo "=========================================="
