# Press review

[![Build Status](https://travis-ci.org/thierrymarianne/daily-press-review.svg?branch=master)](https://travis-ci.org/thierrymarianne/daily-press-review)

[ ![Codeship Status for thierrymarianne/daily-press-review](https://app.codeship.com/projects/24369620-8f96-0136-7068-0e8ef5ba2310/status?branch=master)](https://app.codeship.com/projects/304052)

Easing observation of Twitter lists to publish a daily press review

## Installation

### Requirements

Install git by following instructions from the [official documentation](https://git-scm.org/).

Install Docker by following instructions from the [official documentation](https://docs.docker.com/install/linux/docker-ce/ubuntu/).

### PHP

Build a PHP container image

```
make build-php-container
```

### MySQL

Initialize MySQL from `app/config/parameters.yml`

```
make initialize-mysql-volume
```

### RabbitMQ

Configure RabbitMQ privileges

```
make configure-rabbitmq-user-privileges
```

Set up AMQP fabric

```
make setup-amqp-fabric
```

List AMQP messages

```
make list-amqp-messages
```

## Usage

Run MySQL container

```
make run-mysql-container
```

Run RabbitMQ container

```
make run-rabbitmq-container
```

Produce messages from lists of members

```
make produce-amqp-messages-from-members-lists
```

Consume Twitter API from messages

```
make consume-twitter-api-messages
```

## Testing

Create the test database schema

```
make create-database-schema-test
``` 

Run unit tests with PHPUnit 

```
make run-php-unit-tests
```
