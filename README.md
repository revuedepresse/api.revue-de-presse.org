# snapshots.fr worker

![worker.snapshots.fr continuous integration](https://github.com/thierrymarianne/snapshots.fr/actions/workflows/continuous-integration.yml/badge.svg)

Worker collecting publications from social media (Twitter) and [public lists](https://help.twitter.com/en/using-twitter/twitter-lists).

This project is developed from learnings acquired by building [revue-de-presse.org](https://revue-de-presse.org).

## Installation

The shell scripts written for bash   
have been tested with Ubuntu 22.04 (`Jammy Jellyfish`).

### Requirements

[Install git](https://git-scm.com/downloads).
> Git is a free and open source distributed version control system designed 
> to handle everything from small to very large projects with speed and efficiency.

[Install Docker Docker Engine](https://docs.docker.com/engine/install/)).
> Docker Engine is an open source containerization technology for building and containerizing your applications.

[Install Docker Compose](https://docs.docker.com/compose/install/)).
> Compose is a tool for defining and running multi-container Docker applications.

Install [jq](https://stedolan.github.io/jq/download/).
> jq is a lightweight and flexible command-line JSON processor.

### Documentation

```
make help
```

## License

GNU General Public License v3.0 or later

See [COPYING](./COPYING) to see the full text.
