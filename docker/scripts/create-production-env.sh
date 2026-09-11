#!/bin/sh

set -eu

project_directory=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
environment_file="$project_directory/.env"
template_file="$project_directory/docker.env.example"

if [ -e "$environment_file" ]; then
    echo "Refusing to overwrite the existing .env file." >&2
    exit 1
fi

umask 077
temporary_file="$environment_file.tmp"
trap 'rm -f "$temporary_file"' EXIT HUP INT TERM

while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
        APP_KEY=*)
            printf 'APP_KEY=base64:%s\n' "$(openssl rand -base64 32 | tr -d '\n')"
            ;;
        DB_PASSWORD=*)
            printf 'DB_PASSWORD=%s\n' "$(openssl rand -hex 32)"
            ;;
        MYSQL_ROOT_PASSWORD=*)
            printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 32)"
            ;;
        *)
            printf '%s\n' "$line"
            ;;
    esac
done < "$template_file" > "$temporary_file"

mv "$temporary_file" "$environment_file"
trap - EXIT HUP INT TERM

echo "Created .env with generated secrets. Review non-secret settings before deployment."
