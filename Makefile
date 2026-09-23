# MyNet repo 门禁 —— 统一入口
# 用法：make lint | make test | make guard | make check | make install-hooks
# 语言：PHP + 知识库

.PHONY: lint test guard check install-hooks

lint:
	@echo "▸ lint"
	@if [ -x vendor/bin/pint ]; then vendor/bin/pint --test; elif [ -f composer.json ]; then echo "(composer install 后启用 pint)"; else echo "(无 PHP 工具链时跳过)"; fi

test:
	@echo "▸ test"
	@if [ -x vendor/bin/phpunit ]; then php vendor/bin/phpunit; else echo "(无 phpunit 时跳过)"; fi

guard:
	@echo "▸ guard（安全红线）"
	@sh .githooks/guard.sh

check: guard lint test
	@echo "✓ check 通过"

install-hooks:
	@git config core.hooksPath .githooks
	@chmod +x .githooks/*
	@echo "✓ hooks 已安装（core.hooksPath = .githooks）"
