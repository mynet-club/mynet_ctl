#!/bin/bash
#
# import_gnb_v1.6.4.sh
#
# 在 mynet_ctl 生产服务器上执行：
#   1. 从 GitHub 下载所有 GNB v1.6.4 二进制
#   2. 调用 artisan release:import-gnb --source 入库
#
# v1.6.4 变化说明：
#   - 文件已使用标准 .tgz 格式，无需重命名
#   - 版本标签使用标准 v 前缀（不再是 ver 前缀）
#   - 新增 openwrt-sdk 平台（6 个架构）
#   - 新增 gnb_openwrt_mipsel-muslsf（musl soft-float）
#   - 无 darwin 构建
#
# 用法：
#   cd /path/to/mynet_ctl
#   bash import_gnb_v1.6.4.sh [--dry-run] [--fresh]
#
set -euo pipefail

VERSION="v1.6.4"
GH_BASE="https://github.com/luoyueliang/opengnb/releases/download/${VERSION}"
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

# GitHub release 的所有二进制文件（v1.6.4 已使用 .tgz，无需重命名）
FILES=(
  # Linux
  gnb_linux_amd64_${VERSION}.tgz
  gnb_linux_arm64_${VERSION}.tgz
  gnb_linux_armv7-hardfp_${VERSION}.tgz
  gnb_linux_armv7-softfp_${VERSION}.tgz
  gnb_linux_mips64el_${VERSION}.tgz
  gnb_linux_mips64_${VERSION}.tgz
  gnb_linux_mipsel_${VERSION}.tgz
  gnb_linux_mips_${VERSION}.tgz
  gnb_linux_riscv64_${VERSION}.tgz
  # OpenWrt
  gnb_openwrt_amd64_${VERSION}.tgz
  gnb_openwrt_arm64_${VERSION}.tgz
  gnb_openwrt_armv7-hardfp_${VERSION}.tgz
  gnb_openwrt_armv7-softfp_${VERSION}.tgz
  gnb_openwrt_mips64el_${VERSION}.tgz
  gnb_openwrt_mips64_${VERSION}.tgz
  gnb_openwrt_mipsel-muslsf_${VERSION}.tgz
  gnb_openwrt_mipsel_${VERSION}.tgz
  gnb_openwrt_mips_${VERSION}.tgz
  gnb_openwrt_riscv64_${VERSION}.tgz
  # OpenWrt SDK（新增，musl libc SDK 版本）
  gnb_openwrt-sdk_amd64_${VERSION}.tgz
  gnb_openwrt-sdk_arm64_${VERSION}.tgz
  gnb_openwrt-sdk_armv7_${VERSION}.tgz
  gnb_openwrt-sdk_mipsel-sf_${VERSION}.tgz
  gnb_openwrt-sdk_mipsel_${VERSION}.tgz
  gnb_openwrt-sdk_mips_${VERSION}.tgz
)

mkdir -p "${TMPDIR}"

echo "── 步骤 1: 从 GitHub 下载 ──"
echo ""

SUCCESS=0
FAIL=0

for FILE in "${FILES[@]}"; do
  DEST="${TMPDIR}/${FILE}"
  URL="${GH_BASE}/${FILE}"

  if [ -f "${DEST}" ]; then
    echo "  SKIP ${FILE} (已存在)"
    SUCCESS=$((SUCCESS + 1))
    continue
  fi

  echo -n "  GET  ${FILE} ... "
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
