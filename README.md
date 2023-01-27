# Revue de presse

:gb: [Revue-de-presse.org](https://github.com/revuedepresse) serves press titles curated daily from official French Media Twitter accounts.

All development is delivered under free and open-source software licence.

:fr: [Revue-de-presse.org](https://github.com/revuedepresse) est un projet citoyen indépendant qui s'adresse aux journalistes et  
à toute personne s'intéressant à l'actualité et à l'influence des médias sur l'opinion.

![revue-de-presse.org continuous integration](https://github.com/thierrymarianne/api.revue-de-presse.org/actions/workflows/continuous-integration.yml/badge.svg)

## HTTP API

API serving daily short lists (10 items) of top news in France sorted by popularity.  
Said popularity is simply based on retweets fetched by calling Twitter APIs.

A variant of this project generalizing the principle of providing briefs from [Twitter Lists](https://help.twitter.com/en/using-twitter/twitter-lists)  
is also available from [snapshots.fr's git repository](https://github.com/thierrymarianne/snapshots.fr/tree/api)

## Installation

The shell scripts written for bash   
have been tested with Ubuntu 22.04 (`Jammy Jellyfish`).

### Requirements

Install git by following instructions from the [official documentation](https://git-scm.org/).

Install Docker by following instructions from the [official documentation](https://docs.docker.com/install/linux/docker-ce/ubuntu/).

Install Docker compose by following instructions from the [official documentation](https://docs.docker.com/compose/install/).

### Documentation

```
make help
```

## License

GNU General Public License v3.0 or later

See [COPYING](./COPYING) to see the full text.

## Acknowledgment

We're grateful towards all amazing contributors involved in the following  
communities, organizations and projects (in lexicographic order):

- [Blackfire](https://blackfire.io)
- [Composer](http://getcomposer.org/)
- [Datadog](https://datadoghq.eu/)
- [Debian](https://www.debian.org/)
- [Docker](docker.com)
- [Doctrine](https://www.doctrine-project.org/)
- [GitHub](https://github.com/)
- [JetBrains](https://jb.gg/OpenSourceSupport)
- [PHP](https://www.php.net/)
- [Symfony](https://symfony.com/)
- [Ubuntu](https://ubuntu.com/)

### Some of their logos

[![Blackfire](../http-api/doc/images/blackfire-io.png?raw=true)](https://blackfire.io)  
[![JetBrains](../http-api/doc/images/jetbrains-logo.png/raw=true)](https://jb.gg/OpenSourceSupport)
