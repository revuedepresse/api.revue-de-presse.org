#!/usr/bin/env bash
set -Eeuo pipefail

function _set_up_configuration_files() {
    if [ ! -e ./provisioning/containers/docker-compose.override.yaml ]; then
        cp ./provisioning/containers/docker-compose.override.yaml{.dist,}
    fi

    if [ ! -e ./.env.local ]; then
        cp --verbose ./.env.local{.dist,}
    fi

    if [ ! -e ./.env ]; then
        touch ./.env
    fi

    source ./.env.local
}

function _set_file_permissions() {
    local temporary_directory
    temporary_directory="${1}"

    if [ -z "${temporary_directory}" ];
    then
        printf 'A %s is expected as %s (%s).%s' 'non-empty string' '1st argument' 'temporary directory file path' $'\n'

        return 1;
    fi

    if [ ! -d "${temporary_directory}" ];
    then
        printf 'A %s is expected as %s (%s).%s' 'directory' '1st argument' 'temporary directory file path' $'\n'

        return 1;
    fi

    docker compose \
        -f ./provisioning/containers/docker-compose.yaml \
        -f ./provisioning/containers/docker-compose.override.yaml \
        run \
        --rm \
        --user root \
        --volume "${temporary_directory}:/tmp/remove-me" \
        app \
        /bin/bash -c 'chmod -R ug+w /tmp/remove-me'
}

function build() {
    local SERVICE_OWNER_UID
    local SERVICE_OWNER_GID

    _set_up_configuration_files

    if [ -z "${SERVICE_OWNER_UID}" ];
    then

      printf 'A %s is expected as %s ("%s").%s' 'non-empty numeric' 'system user uid' 'SERVICE_OWNER_UID' $'\n'

      return 1

    fi

    if [ -z "${SERVICE_OWNER_GID}" ];
    then

      printf 'A %s is expected as %s ("%s").%s' 'non-empty numeric' 'system user gid' 'SERVICE_OWNER_GID' $'\n'

      return 1

    fi

    docker compose \
        --file=./provisioning/containers/docker-compose.yaml \
        --file=./provisioning/containers/docker-compose.override.yaml \
        build \
        --build-arg "SERVICE_OWNER_UID=${SERVICE_OWNER_UID}" \
        --build-arg "SERVICE_OWNER_GID=${SERVICE_OWNER_GID}" \
        --no-cache \
        app \
        cache \
        service
}

function get_project_name() {
    local project_name

    project_name="$(
        docker compose \
        -f ./provisioning/containers/docker-compose.yaml \
        -f ./provisioning/containers/docker-compose.override.yaml \
        config --format json \
        | jq '.name' \
        | tr -d '"'
    )"

    echo "${project_name}"
}

function remove_running_container_and_image_in_debug_mode() {
    local container_name
    container_name="${1}"

    if [ -z "${container_name}" ];
    then

        printf 'A %s is expected as %s ("%s").%s' 'non-empty string' '1st argument' 'container name' $'\n'

        return 1

    fi

    local DEBUG

    source ./.env.local

    local project_name
    project_name="$(get_project_name)"

    docker ps -a |
        \grep "${project_name}" |
        \grep "${container_name}" |
        awk '{print $1}' |
        xargs -I{} docker rm -f {}

    if [ -n "${DEBUG}" ];
    then

        docker images -a |
            \grep "${project_name}" |
            \grep "${container_name}" |
            awk '{print $3}' |
            xargs -I{} docker rmi {}

    fi
}

function clean() {
    local temporary_directory
    temporary_directory="${1}"

    if [ -n "${temporary_directory}" ];
    then
        printf 'About to remove "%s".%s' "${temporary_directory}" $'\n'

        _set_file_permissions "${temporary_directory}"

        return 0
    fi

    remove_running_container_and_image_in_debug_mode 'app'
    remove_running_container_and_image_in_debug_mode 'service'
}

function clear_cache_warmup() {
    local SERVICE_OWNER_UID
    local SERVICE_OWNER_GID

    _set_up_configuration_files

    local reuse_existing_container
    reuse_existing_container="${1}"

    if [ -z "${reuse_existing_container}" ];
    then
        remove_running_container_and_image_in_debug_mode 'app'

        docker compose \
            -f ./provisioning/containers/docker-compose.yaml \
            -f ./provisioning/containers/docker-compose.override.yaml \
            up \
            --detach \
            app
    fi

    docker compose \
        -f ./provisioning/containers/docker-compose.yaml \
        -f ./provisioning/containers/docker-compose.override.yaml \
        exec \
        --user "${SERVICE_OWNER_UID}:${SERVICE_OWNER_GID}" \
        app \
        /bin/bash -c '. /scripts/clear-app-cache.sh'

    clean ''
}

function install() {
    local SERVICE_OWNER_UID
    local SERVICE_OWNER_GID

    _set_up_configuration_files

    clean ''

    docker compose \
        -f ./provisioning/containers/docker-compose.yaml \
        -f ./provisioning/containers/docker-compose.override.yaml \
        up \
        --force-recreate \
        --detach \
        app && \
    docker compose \
        -f ./provisioning/containers/docker-compose.yaml \
        -f ./provisioning/containers/docker-compose.override.yaml \
        exec \
        --user root \
        -T app \
        /bin/bash -c 'source /scripts/install-app-requirements.sh'

    clear_cache_warmup --reuse-existing-container
}

function start() {
    guard_against_missing_variables

    clean ''

    local command
    command=$(cat <<-SCRIPT
docker compose \
      --file=./provisioning/containers/docker-compose.yaml \
      --file=./provisioning/containers/docker-compose.override.yaml \
			up \
			--detach \
			service
SCRIPT
)

    echo 'About to execute "'"${command}"'"'
    /bin/bash -c "${command}"
}

function start_database() {
    remove_running_container_and_image_in_debug_mode 'database'

    local command
    command=$(cat <<-SCRIPT
docker compose \
      --file=./provisioning/containers/docker-compose.yaml \
      --file=./provisioning/containers/docker-compose.override.yaml \
			up \
			--detach \
			database
SCRIPT
)

    echo 'About to execute "'"${command}"'"'
    /bin/bash -c "${command}"
}

function stop() {
    docker compose \
        -f ./provisioning/containers/docker-compose.yaml \
        -f ./provisioning/containers/docker-compose.override.yaml \
        down
    remove_running_container_and_image_in_debug_mode 'app'
    remove_running_container_and_image_in_debug_mode 'service'
}

function run_php_unit_tests() {
    export SYMFONY_DEPRECATIONS_HELPER=disabled

    if [ -z "${DEBUG}" ];
    then
        bin/phpunit -c ./phpunit.xml.dist --process-isolation --stop-on-failure --stop-on-error
        return
    fi

    bin/phpunit -c ./phpunit.xml.dist --verbose --debug --stop-on-failure --stop-on-error
}

set +Eeuo pipefail
