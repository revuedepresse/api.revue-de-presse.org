#!/usr/bin/env bash
set -Eeuo pipefail

function add_system_user_group() {
    if [ $(cat /etc/group | grep "${WORKER_GID}" -c) -eq 0 ]; then
        groupadd \
            --gid "${WORKER_GID}" \
            service
    fi

    if [ $(cat /etc/passwd | grep "${WORKER_UID}" -c) -eq 0 ]; then
        useradd \
            --shell /usr/sbin/nologin \
            --uid ${WORKER_UID} \
            --gid ${WORKER_GID} \
            --no-user-group \
            --no-create-home \
            service
    fi
}

function clear_package_management_system_cache() {
    # Remove packages installed with apt except for tini
    apt-get remove --assume-yes build-essential gcc build-essential wget
    apt-get autoremove --assume-yes &&
    apt-get purge --assume-yes
    apt-get clean &&
    rm -rf /var/lib/apt/lists/*
}

function install_system_packages() {
    echo 'deb [trusted=yes] https://repo.symfony.com/apt/ /' | tee /etc/apt/sources.list.d/symfony-cli.list
    apt-get update
    apt-get install --assume-yes \
        apt-utils \
        ca-certificates \
        git \
        libcurl4-gnutls-dev \
        libicu-dev \
        libcurl4-gnutls-dev \
        libicu-dev \
        libjpeg-dev \
        libpng-dev \
        libpq-dev \
        libsodium-dev \
        procps \
        symfony-cli \
        tini \
        unzip \
        wget

    # Install blackfire profiling agent
    install_blackfire
}

function install_blackfire() {
    wget -q -O - https://packages.blackfire.io/gpg.key | \
    dd of=/usr/share/keyrings/blackfire-archive-keyring.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/blackfire-archive-keyring.asc] http://packages.blackfire.io/debian any main" | \
    tee /etc/apt/sources.list.d/blackfire.list

    apt update
    apt install blackfire blackfire-php --assume-yes
}

function install_php_extensions() {
    docker-php-ext-install \
        bcmath \
        mysqli \
        intl \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        sodium

    docker-php-ext-enable opcache

    wget https://github.com/xdebug/xdebug/archive/3.1.4.zip \
    --output-document /tmp/3.1.4.zip
    cd /tmp || exit
    unzip /tmp/3.1.4.zip
    cd xdebug-3.1.4 || exit
    phpize .
    ./configure --with-php-config="$(which php-config)"
    make
    make install

    wget https://github.com/DataDog/dd-trace-php/archive/0.74.0.tar.gz \
    --output-document=/tmp/datadog-php-tracer.tar.gz
    cd /tmp || exit
    tar -xvzf /tmp/datadog-php-tracer.tar.gz
    cd dd-trace-php-0.74.0 || exit
    phpize .
    ./configure --with-php-config="$(which php-config)"
    make
    make install

    wget https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
    --output-document=/tmp/datadog-setup.php
    cd /tmp || exit
    php datadog-setup.php \
    --php-bin all \
    --enable-appsec \
    --enable-profiling
}

function install_service_requirements() {
    add_system_user_group
    install_system_packages
    install_php_extensions
    clear_package_management_system_cache

    mkdir \
        --verbose \
        --parents \
        /var/www/api.revue-de-presse.org
}

function install_app_requirements() {
    local project_dir
    project_dir='/var/www/api.revue-de-presse.org'

    rm -f "${project_dir}/bin/behat"
    rm -f "${project_dir}/bin/doctrine"
    rm -f "${project_dir}/bin/doctrine-dbal"
    rm -f "${project_dir}/bin/doctrine-migrations"
    rm -f "${project_dir}/bin/google-cloud-batch"
    rm -f "${project_dir}/bin/jp.php"
    rm -f "${project_dir}/bin/patch-type-declarations"
    rm -f "${project_dir}/bin/php-parse"
    rm -f "${project_dir}/bin/phpunit"
    rm -f "${project_dir}/bin/simple-phpunit"
    rm -f "${project_dir}/bin/sql-formatter"
    rm -f "${project_dir}/bin/var-dump-server"
    rm -f "${project_dir}/bin/yaml-lint"
    rm -rf "${project_dir}"'/vendor'

    local APP_SECRET
    local GITHUB_API_TOKEN
    local WORKER_UID
    local WORKER_GID

    source "${project_dir}/.env.local"

    if [ -z "${APP_SECRET}" ];
    then

      printf 'A %s is expected as %s ("%s").%s' 'non-empty string' 'environment variable' 'APP_SECRET' $'\n'

      return 1

    fi

    if [ -z "${GITHUB_API_TOKEN}" ];
    then

      printf 'A %s is expected as %s ("%s").%s' 'non-empty string' 'environment variable' 'GITHUB_API_TOKEN' $'\n'

      return 1

    fi

    if [ -z "${WORKER_UID}" ];
    then

      printf 'A %s is expected as %s ("%s").%s' 'non-empty numeric' 'system user uid' 'WORKER_UID' $'\n'

      return 1

    fi

    if [ -z "${WORKER_GID}" ];
    then

      printf 'A %s is expected as %s ("%s").%s' 'non-empty numeric' 'system user gid' 'WORKER_GID' $'\n'

      return 1

    fi

    # [Command line github-oauth](https://getcomposer.org/doc/articles/authentication-for-private-packages.md#command-line-github-oauth)
    composer config -g github-oauth.github.com "${GITHUB_API_TOKEN}"

    composer install \
        --prefer-dist \
        --no-dev \
        --no-interaction \
        --classmap-authoritative

    chown --verbose -R "${WORKER_UID}:${WORKER_GID}" /scripts

    chmod     o-rwx /scripts
    chmod -R ug+rx  /scripts
    chmod -R  u+w   /scripts

    if [ ! -e '/templates/www.conf.dist' ];
    then
        echo 'Missing configuration file.'

        return 1
    fi

    \cat '/templates/www.conf.dist' | \
    \sed -E 's/__SOCK__/api.revue-de-presse.org/g' | \
    \sed -E 's/__UID__/'"${WORKER_UID}"'/g' | \
    \sed -E 's/__GID__/'"${WORKER_GID}"'/g' \
    > "${project_dir}/provisioning/containers/service/templates/www.conf" && \
    printf '%s.%s' 'Successfully copied php-fpm template' $'\n'

    echo '--- BEGIN ---'
    \cat '/templates/www.conf'
    echo '--- END ---'

    if [ ! -d "${project_dir}/.git" ];
    then
        rm --recursive --force --verbose "${project_dir}/.git"
    fi

    find "${project_dir}"  \
        -maxdepth 1 \
        -executable \
        -readable \
        -type d \
        -exec /bin/bash -c 'export file_path="{}" && \chown --recursive '"${WORKER_UID}"':'"${WORKER_GID}"' "${file_path}"' \; \
        -exec /bin/bash -c 'export file_path="{}" && \chmod --recursive og-rwx "${file_path}"' \; \
        -exec /bin/bash -c 'export file_path="{}" && \chmod --recursive g+rx "${file_path}"' \; && \
        printf '%s.%s' 'Successfully changed directories permissions' $'\n'

    find "${project_dir}" \
        -maxdepth 2 \
        -executable \
        -readable \
        -path "${project_dir}"'/var' \
        -type d \
        -exec /bin/bash -c 'export file_path="{}" && \chmod --recursive g+w "${file_path}"' \; && \
        printf '%s.%s' 'Successfully made var directories writable' $'\n'

    find "${project_dir}" \
        -type f \
        -readable \
        -exec /bin/bash -c 'export file_path="{}" && \chown '"${WORKER_UID}"':'"${WORKER_GID}"' "${file_path}"' \; \
        -exec /bin/bash -c 'export file_path="{}" && \chmod og-rwx "${file_path}"' \; \
        -exec /bin/bash -c 'export file_path="{}" && \chmod g+r "${file_path}"' \; && \
        printf '%s.%s' 'Successfully changed files permissions' $'\n'

    find "${project_dir}"  \
        -type f \
        -not -path "${project_dir}"'/bin' \
        -exec /bin/bash -c 'export file_path="{}" && \chown --recursive '"${WORKER_UID}"':'"${WORKER_GID}"' "${file_path}"' \; \
        -exec /bin/bash -c 'export file_path="{}" && \chmod --recursive ug+x "${file_path}"' \; && \
        printf '%s.%s' 'Successfully changed binaries permissions' $'\n'
}

set -Eeuo pipefail
