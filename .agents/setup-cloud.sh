#!/usr/bin/env bash
# Development runtime only; does not migrate, seed, or deploy the application.
set -euo pipefail

if [[ "$(uname -s)/$(uname -m)" != Linux/x86_64 ]]; then
    echo 'This pinned runtime supports Linux x86_64 only.' >&2
    exit 1
fi

runtime_dir="${SEMINARYOS_RUNTIME_DIR:-/workspace/seminaryos-runtime}"
bin_dir="${SEMINARYOS_BIN_DIR:-$HOME/.local/bin}"
php_version=8.5.8
composer_version=2.11.1
php_sha=bfaa12e0f6f5788259bc20d0d3f08e1e851bce7f05d43e38fb77041e12a32663
composer_sha=6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c
mkdir -p "$runtime_dir/downloads" "$runtime_dir/php" "$bin_dir"
runtime_dir="$(cd "$runtime_dir" && pwd)"
bin_dir="$(cd "$bin_dir" && pwd)"

fetch_verified() {
    local url="$1" target="$2" checksum="$3"
    if [[ ! -f "$target" ]]; then
        curl --fail --location --silent --show-error --retry 2 --max-time 180 "$url" -o "$target.download"
        printf '%s  %s\n' "$checksum" "$target.download" | sha256sum --check --status
        mv "$target.download" "$target"
    fi
    # Never execute an artifact whose pinned checksum does not match.
    printf '%s  %s\n' "$checksum" "$target" | sha256sum --check --status
}

fetch_verified "https://dl.static-php.dev/static-php-cli/bulk/php-${php_version}-cli-linux-x86_64.tar.gz" \
    "$runtime_dir/downloads/php-${php_version}.tar.gz" "$php_sha"
fetch_verified "https://getcomposer.org/download/${composer_version}/composer.phar" \
    "$runtime_dir/downloads/composer.phar" "$composer_sha"
tar -xzf "$runtime_dir/downloads/php-${php_version}.tar.gz" -C "$runtime_dir/php" php

cat > "$runtime_dir/php.ini" <<'INI'
memory_limit = 512M
date.timezone = UTC
INI

# Refuse to replace an unrelated PHP or Composer command in the target directory.
for command_name in php composer; do
    if [[ -e "$bin_dir/$command_name" ]] && ! grep -q 'SeminaryOS Cloud runtime' "$bin_dir/$command_name"; then
        echo "Refusing to overwrite $bin_dir/$command_name" >&2
        exit 1
    fi
done
{
    printf '#!/usr/bin/env bash\n# SeminaryOS Cloud runtime\n'
    printf 'export PHPRC=%q\n' "$runtime_dir/php.ini"
    printf 'exec %q "$@"\n' "$runtime_dir/php/php"
} > "$bin_dir/php"
{
    printf '#!/usr/bin/env bash\n# SeminaryOS Cloud runtime\n'
    printf 'export COMPOSER_CACHE_DIR=%q\n' "$runtime_dir/composer-cache"
    printf 'exec %q %q "$@"\n' "$bin_dir/php" "$runtime_dir/downloads/composer.phar"
} > "$bin_dir/composer"
chmod u+x "$bin_dir/php" "$bin_dir/composer"
export PATH="$bin_dir:$PATH"
php -r '
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5) {
    throw new RuntimeException("PHP 8.5 is required.");
}
foreach (["ctype", "curl", "dom", "fileinfo", "filter", "hash", "iconv", "intl", "json", "libxml", "mbstring", "openssl", "pcntl", "pcre", "PDO", "Phar", "session", "sqlite3", "tokenizer", "xml", "xmlreader", "xmlwriter", "zip"] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException("Missing extension: ".$extension);
    }
}
$db = new PDO("sqlite::memory:");
echo "PDO SQLite ", $db->query("select sqlite_version()")->fetchColumn(), PHP_EOL;
'
php -v
composer --version
printf 'Runtime ready. Ensure %s is on PATH in future task shells.\n' "$bin_dir"
