#!/bin/bash
#
# import_gnb_v1.6.0.a.sh
#
# 在 mynet_ctl 生产服务器上执行：
#   1. 从 GitHub 下载所有 GNB ver1.6.0.a 二进制
#   2. 重命名为 CTL 标准格式（ver→v, tar.gz→tgz）
#   3. 调用 artisan release:import-gnb --source 入库
#
# 用法：
#   cd /path/to/mynet_ctl
#   bash import_gnb_v1.6.0.a.sh [--dry-run] [--fresh]
#
set -euo pipefail

VERSION="v1.6.0.a"
GH_VERSION="ver1.6.0.a"
GH_BASE="https://github.com/luoyueliang/opengnb/releases/download/${GH_VERSION}"
TMPDIR="/tmp/gnb_import_${VERSION}"

# 透传参数给 artisan
ARTISAN_EXTRA=""
for arg in "$@"; do
  ARTISAN_EXTRA="${ARTISAN_EXTRA} ${arg}"
done

# 自动检测 mynet_ctl 项目路径
if [ -f "artisan" ]; then
  CTL_DIR="$(pwd)"
elif [ -f "/data/app/mynet.club/ctl/artisan" ]; then
  CTL_DIR="/data/app/mynet.club/ctl"
elif [ -f "/var/www/mynet_ctl/artisan" ]; then
  CTL_DIR="/var/www/mynet_ctl"
else
  echo "ERROR: 找不到 mynet_ctl 项目目录，请在项目根目录执行或设置路径"
  exit 1
fi

echo "=== GNB ${VERSION} GitHub → CTL Import ==="
echo "CTL 项目: ${CTL_DIR}"
echo "临时目录: ${TMPDIR}"
echo ""

# GitHub release 的所有二进制文件（不含 source 和 checksums）
FILES=(
  gnb_darwin_amd64
  gnb_darwin_arm64
  gnb_linux_amd64
  gnb_linux_arm64
  gnb_linux_armv7-hardfp
  gnb_linux_armv7-softfp
  gnb_linux_mips64el
  gnb_linux_mips64
  gnb_linux_mipsel
  gnb_linux_mips
  gnb_linux_riscv64
  gnb_openwrt_amd64
  gnb_openwrt_arm64
  gnb_openwrt_armv7-hardfp
  gnb_openwrt_armv7-softfp
  gnb_openwrt_mips64el
  gnb_openwrt_mips64
  gnb_openwrt_mipsel
  gnb_openwrt_mips
  gnb_openwrt_riscv64
)

mkdir -p "${TMPDIR}"

echo "── 步骤 1: 从 GitHub 下载并重命名 ──"
echo ""

SUCCESS=0
FAIL=0

for base in "${FILES[@]}"; do
  GH_FILE="${base}_${GH_VERSION}.tar.gz"
  CTL_FILE="${base}_${VERSION}.tgz"
  URL="${GH_BASE}/${GH_FILE}"
  DEST="${TMPDIR}/${CTL_FILE}"

  if [ -f "${DEST}" ]; then
    echo "  SKIP ${CTL_FILE} (已存在)"
    SUCCESS=$((SUCCESS + 1))
    continue
  fi

  echo -n "  GET  ${CTL_FILE} ... "
  HTTP_CODE=$(curl -sL -w '%{http_code}' -o "${DEST}" "${URL}")

  if [ "${HTTP_CODE}" = "200" ]; then
    SIZE=$(ls -lh "${DEST}" | awk '{print $5}')
    echo "OK (${SIZE})"
    SUCCESS=$((SUCCESS + 1))
  else
    echo "FAIL (HTTP ${HTTP_CODE})"
    rm -f "${DEST}"
    FAIL=$((FAIL + 1))
  fi
done

echo ""
echo "下载完成: ${SUCCESS} 成功, ${FAIL} 失败"
echo ""

if [ ${SUCCESS} -eq 0 ]; then
  echo "ERROR: 没有成功下载任何文件"
  exit 1
fi

echo "── 步骤 2: 调用 artisan 导入 ──"
echo ""

cd "${CTL_DIR}"
php artisan release:import-gnb "${VERSION}" --source="${TMPDIR}" ${ARTISAN_EXTRA}

echo ""
echo "── 完成 ──"
echo "临时文件: ${TMPDIR}"
echo "如不再需要可删除: rm -rf ${TMPDIR}"
