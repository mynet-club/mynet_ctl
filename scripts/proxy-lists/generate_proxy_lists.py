#!/usr/bin/env python3
"""
生成代理列表 - 从 geoip.dat 提取中国/国际 IP 段
一次性提取所有国家，避免重复解析 geoip.dat
"""

import sys
import subprocess
from pathlib import Path

# 导入 protobuf 模块
sys.path.insert(0, str(Path(__file__).parent))
from app.router.routercommon import common_pb2


def extract_all_countries(geoip_file):
    """一次性提取所有国家的 CIDR"""
    print("读取 geoip.dat...")
    with open(geoip_file, 'rb') as f:
        data = f.read()

    print("解析 protobuf...")
    geoip_list = common_pb2.GeoIPList()
    geoip_list.ParseFromString(data)

    print(f"发现 {len(geoip_list.entry)} 个国家")

    countries_data = {}
    for geoip in geoip_list.entry:
        country = geoip.country_code
        cidrs = []
        for cidr in geoip.cidr:
            ip_bytes = cidr.ip
            prefix = cidr.prefix
            if len(ip_bytes) == 4:
                ip_str = '.'.join(str(b) for b in ip_bytes)
            elif len(ip_bytes) == 16:
                import socket
                ip_str = socket.inet_ntop(socket.AF_INET6, ip_bytes)
            else:
                continue
            cidrs.append(f"{ip_str}/{prefix}")
        countries_data[country] = cidrs

    return countries_data


def run_aggregate(input_file, output_file, aggressive=False):
    """调用聚合工具"""
    script_dir = Path(__file__).parent
    aggregate_script = script_dir / "aggregate_cidrs.py"

    cmd = [
        "python3", str(aggregate_script), str(input_file),
        "-o", str(output_file), "-f", "text"
    ]
    if aggressive:
        cmd.append("-a")

    try:
        subprocess.run(cmd, capture_output=True, text=True, timeout=300, check=True)
        return True
    except Exception:
        return False


def main():
    if len(sys.argv) >= 2:
        geoip_file = Path(sys.argv[1])
        output_dir = Path(sys.argv[2]) if len(sys.argv) >= 3 else geoip_file.parent
    else:
        print("用法: generate_proxy_lists.py <geoip.dat> [output_dir]")
        sys.exit(1)

    if not geoip_file.exists():
        print(f"❌ 找不到文件: {geoip_file}")
        sys.exit(1)

    output_dir.mkdir(exist_ok=True)

    print("=" * 60)
    print("代理列表生成工具")
    print("=" * 60)
    print(f"输入: {geoip_file}")
    print(f"输出: {output_dir}")
    print()

    # 步骤1: 提取所有国家
    print("步骤 1/5: 提取所有国家数据...")
    countries_data = extract_all_countries(geoip_file)
    print(f"✓ 解析完成: {len(countries_data)} 个国家")
    print()

    # 步骤2: 分离中国和国际 IP
    print("步骤 2/5: 分离中国和国际 IP...")
    china_cidrs = countries_data.get('CN', [])
    inter_cidrs = []
    for country, cidrs in countries_data.items():
        if country != 'CN':
            inter_cidrs.extend(cidrs)

    print(f"✓ 中国 IP: {len(china_cidrs):,} 条")
    print(f"✓ 国际 IP: {len(inter_cidrs):,} 条 (来自 {len(countries_data) - 1} 个国家)")
    print()

    # 步骤3: 写入原始文件
    print("步骤 3/5: 写入原始文件...")
    chinaip_raw = output_dir / "chinaip_raw.txt"
    interip_raw = output_dir / "interip_raw.txt"

    with open(chinaip_raw, 'w') as f:
        for cidr in china_cidrs:
            f.write(f"{cidr}\n")
    with open(interip_raw, 'w') as f:
        for cidr in inter_cidrs:
            f.write(f"{cidr}\n")
    print("✓ 写入完成")
    print()

    # 步骤4: 合并自定义文件
    print("步骤 4/5: 合并自定义文件...")
    custom_inter = None
    custom_china = None
    for base_dir in [Path(__file__).parent, output_dir]:
        if not custom_inter and (base_dir / "custom_interip.txt").exists():
            custom_inter = base_dir / "custom_interip.txt"
        if not custom_china and (base_dir / "custom_chinaip.txt").exists():
            custom_china = base_dir / "custom_chinaip.txt"

    if custom_inter:
        with open(custom_inter) as f_in, open(interip_raw, 'a') as f_out:
            lines = f_in.readlines()
            f_out.writelines(lines)
            print(f"✓ 追加国际自定义: {len(lines)} 条")
    else:
        print("✓ 无国际自定义文件")
    if custom_china:
        with open(custom_china) as f_in, open(chinaip_raw, 'a') as f_out:
            lines = f_in.readlines()
            f_out.writelines(lines)
            print(f"✓ 追加中国自定义: {len(lines)} 条")
    else:
        print("✓ 无中国自定义文件")
    print()

    # 步骤5: 聚合
    print("步骤 5/5: 聚合生成最终文件...")
    interip_txt = output_dir / "interip.txt"
    chinaip_txt = output_dir / "chinaip.txt"

    print("  聚合国际 IP -> interip.txt...")
    if run_aggregate(interip_raw, interip_txt, aggressive=True):
        final_count = len(open(interip_txt).readlines())
        compression = (1 - final_count / len(inter_cidrs)) * 100 if len(inter_cidrs) > 0 else 0
        print(f"  ✓ 完成: {final_count:,} 条 (压缩率: {compression:.1f}%)")
    else:
        print("  ⚠️  聚合失败，使用原始数据")
        interip_raw.rename(interip_txt)

    print("  聚合中国 IP -> chinaip.txt...")
    if run_aggregate(chinaip_raw, chinaip_txt):
        final_count = len(open(chinaip_txt).readlines())
        print(f"  ✓ 完成: {final_count:,} 条")
    else:
        print("  ⚠️  聚合失败，使用原始数据")
        chinaip_raw.rename(chinaip_txt)

    print()

    # 清理临时文件
    for f in [chinaip_raw, interip_raw]:
        if f.exists():
            f.unlink()

    print("=" * 60)
    print("✅ 全部完成!")
    print("=" * 60)
    print(f"\n生成文件:")
    print(f"  {chinaip_txt} ({chinaip_txt.stat().st_size / 1024:.1f} KB)")
    print(f"  {interip_txt} ({interip_txt.stat().st_size / 1024:.1f} KB)")


if __name__ == "__main__":
    main()
