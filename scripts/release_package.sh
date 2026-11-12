#!/bin/sh
# Package a tracked-source release with production composer dependencies.
set -eu

out=${1:?usage: release_package.sh OUT_DIR VERSION PROJECT}
version=${2:?usage: release_package.sh OUT_DIR VERSION PROJECT}
project=${3:?usage: release_package.sh OUT_DIR VERSION PROJECT}

staging=$(mktemp -d "${TMPDIR:-/tmp}/mynet-release-package.XXXXXX")
trap 'rm -rf "$staging"' EXIT

git archive HEAD | tar -x -C "$staging"
[ -f public/build/manifest.json ] || {
  echo "✗ 缺少 public/build/manifest.json，拒绝打出无前端资源的包" >&2
  exit 1
}
mkdir -p "$staging/public"
cp -a public/build "$staging/public/build"
mkdir -p "$staging/storage/framework/cache" "$staging/storage/framework/sessions" \
  "$staging/storage/framework/views" "$staging/storage/logs" "$staging/bootstrap/cache"

cd "$staging"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f .env auth.json

tar -czf "$out/$project-$version.tar.gz" -C "$staging" .
