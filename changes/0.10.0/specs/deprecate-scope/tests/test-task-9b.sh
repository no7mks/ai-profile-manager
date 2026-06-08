#!/usr/bin/env bash
set -euo pipefail
./vendor/bin/phpunit --testsuite e2e --filter BootstrapAbilityInstallTest
