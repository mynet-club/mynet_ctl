#!/bin/bash
set -eu
source_dir="$(cd "$(dirname "$0")" && pwd)"
test_dir=$(mktemp -d)
trap 'rm -rf "$test_dir"' EXIT
mkdir -p "$test_dir/scripts" "$test_dir/bin" "$test_dir/output"
cp "$source_dir/update_proxy_lists.sh" "$test_dir/scripts/"
printf 'github.com\n' > "$test_dir/scripts/proxy-domains.txt"
printf '# empty direct list is valid\n' > "$test_dir/scripts/direct-domains.txt"
printf 'ads.example.com\n' > "$test_dir/scripts/ad-domains.txt"
printf 'malware.example.com\n' > "$test_dir/scripts/malware-domains.txt"
printf 'existing-ip-data\n' > "$test_dir/output/chinaip.txt"
cat > "$test_dir/bin/wget" <<'MOCK'
#!/bin/sh
[ "${FAIL_GEOIP:-0}" = 0 ] || exit 1
touch geoip.dat
MOCK
chmod +x "$test_dir/bin/wget"
cat > "$test_dir/scripts/generate_proxy_lists.py" <<'PY'
import pathlib,sys
out=pathlib.Path(sys.argv[2])
(out/'chinaip.txt').write_text('192.0.2.0/24\n')
(out/'interip.txt').write_text('198.51.100.0/24\n')
PY
# GeoIP failure must not block maintained lists or replace existing IP data.
if PATH="$test_dir/bin:$PATH" FAIL_GEOIP=1 bash "$test_dir/scripts/update_proxy_lists.sh" --output-dir "$test_dir/output" > "$test_dir/failure.log" 2>&1; then
    echo 'expected GeoIP failure' >&2; exit 1
fi
cmp "$test_dir/scripts/proxy-domains.txt" "$test_dir/output/proxy-domains.txt"
cmp "$test_dir/scripts/direct-domains.txt" "$test_dir/output/direct-domains.txt"
cmp "$test_dir/scripts/ad-domains.txt" "$test_dir/output/ad-domains.txt"
cmp "$test_dir/scripts/malware-domains.txt" "$test_dir/output/malware-domains.txt"
test "$(cat "$test_dir/output/chinaip.txt")" = existing-ip-data
PATH="$test_dir/bin:$PATH" bash "$test_dir/scripts/update_proxy_lists.sh" --output-dir "$test_dir/output" > "$test_dir/success.log" 2>&1
python3 - "$test_dir/output" <<'PY'
import pathlib,sys,json,hashlib
out=pathlib.Path(sys.argv[1]); meta=json.loads((out/'meta.json').read_text())
for file,key,count in [('proxy-domains.txt','proxy_domains',1),('direct-domains.txt','direct_domains',0),('ad-domains.txt','ad_domains',1),('malware-domains.txt','malware_domains',1)]:
    data=(out/file).read_bytes()
    assert meta[key]==dict(count=count,size=len(data),sha256=hashlib.sha256(data).hexdigest()),meta
assert meta['chinaip']['count']==1
print('domain publication before GeoIP, failure preservation, empty-list metadata: PASS')
PY
