#!/bin/sh
# Package a tracked-source release with production composer dependencies.
set -eu

out=${1:?usage: release_package.sh OUT_DIR VERSION PROJECT}
version=${2:?usage: release_package.sh OUT_DIR VERSION PROJECT}
project=${3:?usage: release_package.sh OUT_DIR VERSION PROJECT}

staging=$(mktemp -d "${TMPDIR:-/tmp}/mynet-release-package.XXXXXX")
trap 'rm -rf "$staging"' EXIT

git archive HEAD | tar -x -C "$staging"
if [ -d public/build ]; then
  mkdir -p "$staging/public"
  cp -a public/build "$staging/public/build"
fi
mkdir -p "$staging/storage/framework/cache" "$staging/storage/framework/sessions" \
  "$staging/storage/framework/views" "$staging/storage/logs" "$staging/bootstrap/cache"

cd "$staging"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f .env auth.json

tar -czf "$out/$project-$version.tar.gz" -C "$staging" .
