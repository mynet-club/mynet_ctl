#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CIDR 聚合工具 - 减少路由表规则数量

功能:
1. 合并相邻的 CIDR 段
2. 移除被父网段包含的子网段
3. 生成优化后的路由规则

用法:
    # 基础用法
    python3 aggregate_cidrs.py input.json -o output.json
    
    # 聚合非中国 IP（排除中国）
    python3 aggregate_cidrs.py geoip_all.json -o outbound_optimized.json --exclude CN
    
    # 只聚合中国 IP
    python3 aggregate_cidrs.py geoip_all.json -o cn_optimized.json --country CN
    
    # 从文本文件聚合（每行一个 CIDR）
    python3 aggregate_cidrs.py cidrs.txt -o optimized.txt --format text
    
    # 激进模式（更大程度聚合，可能损失精度）
    python3 aggregate_cidrs.py input.json -o output.json --aggressive
"""

import json
import ipaddress
import sys
from typing import List, Set, Union
from collections import defaultdict


# 定义需要过滤的局域网和保留地址段
PRIVATE_NETWORKS = [
    # RFC1918 私有地址
    ipaddress.ip_network('10.0.0.0/8'),
    ipaddress.ip_network('172.16.0.0/12'),
    ipaddress.ip_network('192.168.0.0/16'),
    # 本地回环
    ipaddress.ip_network('127.0.0.0/8'),
    # 链路本地地址
    ipaddress.ip_network('169.254.0.0/16'),
    # 组播地址
    ipaddress.ip_network('224.0.0.0/4'),
    # 保留地址
    ipaddress.ip_network('240.0.0.0/4'),
    # 0.0.0.0/8
    ipaddress.ip_network('0.0.0.0/8'),
]


def is_private_network(network: ipaddress.IPv4Network) -> bool:
    """
    判断一个网段是否为私有/保留地址
    """
    for private in PRIVATE_NETWORKS:
        # 检查是否有重叠
        if network.overlaps(private):
            return True
    return False


def is_private_network(network: ipaddress.IPv4Network) -> bool:
    """
    检查是否为私有/保留网段
    """
    # 私有网段
    if network.is_private:
        return True
    # 回环地址
    if network.is_loopback:
        return True
    # 链路本地地址
    if network.is_link_local:
        return True
    # 组播地址
    if network.is_multicast:
        return True
    # 保留地址
    if network.is_reserved:
        return True
    
    return False


def aggregate_ip_networks(cidrs: List[str], aggressive: bool = False, ipv4_only: bool = True, 
                         exclude_private: bool = True) -> List[str]:
    """
    聚合 CIDR 列表
    
    Args:
        cidrs: CIDR 字符串列表
        aggressive: 激进模式，更大程度聚合
        ipv4_only: 只处理 IPv4（默认 True，过滤 IPv6）
        exclude_private: 排除私有/保留网段（默认 True）
        
    Returns:
        聚合后的 CIDR 列表
    """
    if not cidrs:
        return []
    
    # 转换为 ipaddress 对象
    networks = []
    ipv6_count = 0
    private_count = 0
    
    for cidr in cidrs:
        try:
            net = ipaddress.ip_network(cidr, strict=False)
            
            # 过滤 IPv6
            if ipv4_only and net.version == 6:
                ipv6_count += 1
                continue
            
            # 过滤私有/保留网段
            if exclude_private and net.version == 4 and is_private_network(net):
                private_count += 1
                continue
            
            networks.append(net)
        except Exception as e:
            print(f"[WARN] 跳过无效 CIDR: {cidr} ({e})", file=sys.stderr)
            continue
    
    if ipv6_count > 0:
        print(f"[INFO] 已过滤 {ipv6_count} 条 IPv6 规则", file=sys.stderr)
    if private_count > 0:
        print(f"[INFO] 已过滤 {private_count} 条私有/保留网段", file=sys.stderr)
    
    if not networks:
        return []
    
    # 分离 IPv4 和 IPv6
    ipv4_networks = [n for n in networks if n.version == 4]
    ipv6_networks = [n for n in networks if n.version == 6]
    
    # 分别聚合
    result = []
    
    if ipv4_networks:
        # 使用 ipaddress 内置的 collapse_addresses 方法（自动合并相邻网段）
        collapsed = ipaddress.collapse_addresses(ipv4_networks)
        result.extend([str(n) for n in collapsed])
    
    if ipv6_networks:
        collapsed = ipaddress.collapse_addresses(ipv6_networks)
        result.extend([str(n) for n in collapsed])
    
    # 激进模式：进一步聚合（损失一些精度）
    if aggressive and result:
        result = aggressive_merge(result)
    
    # 分别排序 IPv4 和 IPv6，然后合并
    ipv4_result = sorted([r for r in result if ':' not in r], 
                         key=lambda x: ipaddress.ip_network(x))
    ipv6_result = sorted([r for r in result if ':' in r], 
                         key=lambda x: ipaddress.ip_network(x))
    
    return ipv4_result + ipv6_result


def aggressive_merge(cidrs: List[str], threshold: int = 90) -> List[str]:
    """
    激进聚合模式
    
    如果两个相邻网段的前缀差距很小（如 /24 和 /25），
    可以合并为更大的网段（如 /23），即使会包含一些额外的 IP
    
    Args:
        cidrs: CIDR 列表
        threshold: 利用率阈值（百分比），低于此值不聚合
        
    Returns:
        激进聚合后的 CIDR 列表
    """
    networks = [ipaddress.ip_network(c) for c in cidrs]
    
    # 按网段大小分组（IPv4 和 IPv6 分开）
    ipv4_by_prefix = defaultdict(list)
    ipv6_by_prefix = defaultdict(list)
    
    for net in networks:
        if net.version == 4:
            ipv4_by_prefix[net.prefixlen].append(net)
        else:
            ipv6_by_prefix[net.prefixlen].append(net)
    
    result = []
    
    # 处理 IPv4
    for prefix, nets in sorted(ipv4_by_prefix.items()):
        if prefix <= 8:  # 太大的网段不再聚合
            result.extend([str(n) for n in nets])
            continue
            
        # 尝试合并为上一级网段
        nets.sort()
        i = 0
        while i < len(nets):
            current = nets[i]
            
            # 查找相邻的网段
            if i + 1 < len(nets):
                next_net = nets[i + 1]
                
                # 检查是否可以合并为父网段
                parent_prefix = prefix - 1
                try:
                    # 计算父网段
                    parent = ipaddress.ip_network(f"{current.network_address}/{parent_prefix}", strict=False)
                    
                    # 检查两个网段是否都在这个父网段内
                    if current.subnet_of(parent) and next_net.subnet_of(parent):
                        # 计算利用率
                        covered = current.num_addresses + next_net.num_addresses
                        total = parent.num_addresses
                        utilization = (covered / total) * 100
                        
                        if utilization >= threshold:
                            # 合并
                            result.append(str(parent))
                            i += 2
                            continue
                except:
                    pass
            
            result.append(str(current))
            i += 1
    
    # 处理 IPv6（类似逻辑）
    for prefix, nets in sorted(ipv6_by_prefix.items()):
        if prefix <= 32:  # 太大的网段不再聚合
            result.extend([str(n) for n in nets])
            continue
        
        nets.sort()
        i = 0
        while i < len(nets):
            current = nets[i]
            
            if i + 1 < len(nets):
                next_net = nets[i + 1]
                parent_prefix = prefix - 1
                
                try:
                    parent = ipaddress.ip_network(f"{current.network_address}/{parent_prefix}", strict=False)
                    
                    if current.subnet_of(parent) and next_net.subnet_of(parent):
                        covered = current.num_addresses + next_net.num_addresses
                        total = parent.num_addresses
                        utilization = (covered / total) * 100
                        
                        if utilization >= threshold:
                            result.append(str(parent))
                            i += 2
                            continue
                except:
                    pass
            
            result.append(str(current))
            i += 1
    
    # 再次 collapse（合并相邻段）
    networks = [ipaddress.ip_network(c) for c in result]
    collapsed = ipaddress.collapse_addresses(networks)
    
    # 分别排序 IPv4 和 IPv6
    result_list = [str(n) for n in collapsed]
    ipv4_result = sorted([r for r in result_list if ':' not in r], 
                         key=lambda x: ipaddress.ip_network(x))
    ipv6_result = sorted([r for r in result_list if ':' in r], 
                         key=lambda x: ipaddress.ip_network(x))
    
    return ipv4_result + ipv6_result


def load_cidrs_from_json(filename: str, country: str = None, exclude: str = None) -> List[str]:
    """
    从 JSON 文件加载 CIDR 列表
    
    Args:
        filename: JSON 文件路径
        country: 只加载指定国家（如 "CN"）
        exclude: 排除指定国家（如 "CN"）
        
    Returns:
        CIDR 列表
    """
    with open(filename, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    cidrs = []
    
    if 'entries' in data:
        # GeoIP 格式
        for entry in data['entries']:
            country_code = entry.get('country_code', '')
            
            # 过滤国家
            if country and country_code != country:
                continue
            if exclude and country_code == exclude:
                continue
            
            # 提取 CIDR
            for cidr_info in entry.get('cidrs', []):
                cidrs.append(cidr_info['cidr'])
    elif isinstance(data, list):
        # 简单列表格式
        cidrs = data
    else:
        raise ValueError("不支持的 JSON 格式")
    
    return cidrs


def load_cidrs_from_text(filename: str) -> List[str]:
    """
    从文本文件加载 CIDR 列表（每行一个）
    """
    with open(filename, 'r', encoding='utf-8') as f:
        return [line.strip() for line in f if line.strip() and not line.startswith('#')]


def save_cidrs_to_json(cidrs: List[str], filename: str, metadata: dict = None):
    """
    保存 CIDR 列表为 JSON
    """
    output = {
        'metadata': metadata or {},
        'cidrs': cidrs,
        'count': len(cidrs)
    }
    
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)


def save_cidrs_to_text(cidrs: List[str], filename: str):
    """
    保存 CIDR 列表为文本（每行一个）
    """
    # 确保目录存在
    import os
    os.makedirs(os.path.dirname(filename) if os.path.dirname(filename) else '.', exist_ok=True)
    
    with open(filename, 'w', encoding='utf-8') as f:
        for cidr in cidrs:
            f.write(f"{cidr}\n")


def calculate_stats(original: List[str], aggregated: List[str]) -> dict:
    """
    计算统计信息
    """
    # 计算原始 IP 总数
    original_ips = 0
    for cidr in original:
        try:
            net = ipaddress.ip_network(cidr)
            # IPv6 数量太大，限制显示
            if net.version == 4:
                original_ips += net.num_addresses
        except:
            pass
    
    # 计算聚合后 IP 总数
    aggregated_ips = 0
    for cidr in aggregated:
        try:
            net = ipaddress.ip_network(cidr)
            if net.version == 4:
                aggregated_ips += net.num_addresses
        except:
            pass
    
    # 统计 IPv4/IPv6
    original_ipv4 = sum(1 for c in original if ':' not in c)
    original_ipv6 = len(original) - original_ipv4
    aggregated_ipv4 = sum(1 for c in aggregated if ':' not in c)
    aggregated_ipv6 = len(aggregated) - aggregated_ipv4
    
    reduction_rate = ((len(original) - len(aggregated)) / len(original) * 100) if original else 0
    
    return {
        'original': {
            'total': len(original),
            'ipv4': original_ipv4,
            'ipv6': original_ipv6,
            'ipv4_addresses': original_ips
        },
        'aggregated': {
            'total': len(aggregated),
            'ipv4': aggregated_ipv4,
            'ipv6': aggregated_ipv6,
            'ipv4_addresses': aggregated_ips
        },
        'reduction': {
            'rules_reduced': len(original) - len(aggregated),
            'reduction_rate': f"{reduction_rate:.2f}%",
            'ip_coverage': f"{(aggregated_ips / original_ips * 100) if original_ips else 0:.4f}%"
        }
    }


def main():
    import argparse
    
    parser = argparse.ArgumentParser(
        description='CIDR 聚合工具 - 优化路由规则数量',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
示例:
  # 聚合非中国 IP（用于代理）
  python3 aggregate_cidrs.py geoip_all.json -o outbound.json --exclude CN
  
  # 只聚合中国 IP（用于直连）
  python3 aggregate_cidrs.py geoip_all.json -o cn.json --country CN
  
  # 激进模式（更大压缩比）
  python3 aggregate_cidrs.py input.json -o output.json --aggressive
  
  # 文本格式输入输出
  python3 aggregate_cidrs.py cidrs.txt -o optimized.txt --format text
        """
    )
    
    parser.add_argument('input', help='输入文件（JSON 或 TXT）')
    parser.add_argument('-o', '--output', required=True, help='输出文件')
    parser.add_argument('-f', '--format', choices=['json', 'text'], default='json',
                        help='输出格式（默认: json）')
    parser.add_argument('-c', '--country', help='只处理指定国家（如 CN）')
    parser.add_argument('-e', '--exclude', help='排除指定国家（如 CN）')
    parser.add_argument('-a', '--aggressive', action='store_true',
                        help='激进聚合模式（更大压缩比，可能包含额外 IP）')
    parser.add_argument('--stats', help='保存统计信息到文件（JSON）')
    
    args = parser.parse_args()
    
    # 加载 CIDR
    print(f"[INFO] 加载 CIDR 列表: {args.input}")
    
    if args.input.endswith('.json'):
        cidrs = load_cidrs_from_json(args.input, args.country, args.exclude)
    else:
        cidrs = load_cidrs_from_text(args.input)
    
    print(f"[INFO] 原始 CIDR 数量: {len(cidrs)}")
    
    # 聚合
    print(f"[INFO] 开始聚合（仅 IPv4，排除私有网段）{'（激进模式）' if args.aggressive else ''}...")
    aggregated = aggregate_ip_networks(cidrs, args.aggressive, ipv4_only=True, exclude_private=True)
    
    print(f"[INFO] 聚合后 CIDR 数量: {len(aggregated)}")
    
    # 计算统计
    stats = calculate_stats(cidrs, aggregated)
    
    print(f"\n=== 聚合统计 ===")
    print(f"原始规则: {stats['original']['total']} 条")
    print(f"  - IPv4: {stats['original']['ipv4']} 条")
    print(f"  - IPv6: {stats['original']['ipv6']} 条")
    print(f"  - IPv4 地址总数: {stats['original']['ipv4_addresses']:,}")
    print(f"\n聚合后规则: {stats['aggregated']['total']} 条")
    print(f"  - IPv4: {stats['aggregated']['ipv4']} 条")
    print(f"  - IPv6: {stats['aggregated']['ipv6']} 条")
    print(f"  - IPv4 地址总数: {stats['aggregated']['ipv4_addresses']:,}")
    print(f"\n优化效果:")
    print(f"  - 减少规则: {stats['reduction']['rules_reduced']} 条")
    print(f"  - 压缩率: {stats['reduction']['reduction_rate']}")
    print(f"  - IP 覆盖率: {stats['reduction']['ip_coverage']}")
    
    # 保存结果
    print(f"\n[INFO] 保存结果: {args.output}")
    
    if args.format == 'json':
        metadata = {
            'source': args.input,
            'country': args.country,
            'exclude': args.exclude,
            'aggressive': args.aggressive,
            'stats': stats
        }
        save_cidrs_to_json(aggregated, args.output, metadata)
    else:
        save_cidrs_to_text(aggregated, args.output)
    
    # 保存统计信息
    if args.stats:
        print(f"[INFO] 保存统计信息: {args.stats}")
        with open(args.stats, 'w', encoding='utf-8') as f:
            json.dump(stats, f, indent=2, ensure_ascii=False)
    
    print(f"\n✅ 完成！")


if __name__ == '__main__':
    main()
