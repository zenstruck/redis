# zenstruck/redis

[![CI Status](https://github.com/zenstruck/redis/workflows/CI/badge.svg)](https://github.com/zenstruck/redis/actions?query=workflow%3ACI)
[![Code Coverage](https://codecov.io/gh/zenstruck/redis/branch/1.x/graph/badge.svg?token=D7SCESSN3Q)](https://codecov.io/gh/zenstruck/redis)

Lazy proxy for [php-redis](https://github.com/phpredis/phpredis) with DX helpers,
utilities and a unified API.

`Zenstruck\Redis` is a unified proxy for `\Redis|\RedisArray|\RedisCluster`. With
a few exceptions and considerations, the API is the same no matter the underlying
client. This allows you to use the same API in development, where you are likely
just using `\Redis`, and production, where you could be using `\RedisArray` or
`\RedisCluster`.

The proxy is lazy in that, if created via a DSN, doesn't instantiate the underlying
client until a command is executed.

This library integrates well with [Symfony](#symfony-framework) and a recipe is
available.

## Installation

```bash
composer require zenstruck/redis
```

## Redis Factory

Creating a Redis client instance is done via a _DSN_ string. The DSN must use the following format:

```
redis[s]://[pass@][ip|host|socket[:port]][/db-index][?{option}={value}...]
```

### Redis Proxy Factory

It is recommended to use the proxy whenever possible. It has the following
benefits over using the _real_ client:

1. **Lazy**: a connection is not established until a Redis command is actually called.
2. **Encapsulated**: for the most part, knowledge of the _real_ client is not required.
   You don't need to change your usage depending on the client used. _There are
   [some](#sequencespipelines-and-transactions) [exceptions](#countableiterable) to this._
3. **Developer Experience (DX)**: use the [fluent sequence and transaction api](#sequencespipelines-and-transactions).

Here are some examples creating the proxy from a DSN.

```php
use Zenstruck\Redis;

$proxy = Redis::create('redis://localhost'); // Zenstruck\Redis<\Redis>
$proxy = Redis::create('redis://localhost?redis_sentinel=sentinel_service'); // Zenstruck\Redis<\Redis> (using Redis Sentinel)
$proxy = Redis::create('redis:?host[host1]&host[host2]'); // Zenstruck\Redis<\RedisArray>
$proxy = Redis::create('redis:?host[host1]&host[host2]&redis_cluster=1'); // Zenstruck\Redis<\RedisCluster>
```

You can also create a Proxy from an exising instance of `\Redis|\RedisArray|\RedisCluster`:

```php
use Zenstruck\Redis;

/** @var \Redis|\RedisArray|\RedisCluster $client */

$proxy = Redis::wrap($client)
```

### Redis _Real_ Client Factory

An instance of `\Redis|\RedisArray|\RedisCluster` can be created directly:

```php
use Zenstruck\Redis;

$client = Redis::createClient('redis://localhost'); // \Redis
$client = Redis::createClient('redis://localhost?redis_sentinel=sentinel_service'); // \Redis (using Redis Sentinel)
$client = Redis::createClient('redis:?host[host1]&host[host2]'); // \RedisArray
$client = Redis::createClient('redis:?host[host1]&host[host2]&redis_cluster=1'); // \RedisCluster
```

### Factory Options

Certain Redis options can be set via your _DSN_'s query parameters or passed
as an array to the second parameter of `Zenstruck\Redis::create/createClient()`.

#### Prefix

You can set a prefix for all keys:

```php
use Zenstruck\Redis;

$proxy = Redis::create('redis://localhost?prefix=app:');
$proxy = Redis::create('redis://localhost', ['prefix' => 'app:']); // equivalent to above
```

#### Serializer Option

By default, Redis stores all scalar/null values as strings and objects/arrays
as "Array"/"Object". In order to store properly typed values and objects/arrays,
you must configure a Redis serializer:

```php
use Zenstruck\Redis;

// PHP: serialize/unserialize values
$proxy = Redis::create('redis://localhost?serializer=php');
$proxy = Redis::create('redis://localhost', ['serializer' => \Redis::SERIALIZER_PHP]); // equivalent to above

// JSON: json_encode/json_decode values (doesn't work for objects)
$proxy = Redis::create('redis://localhost?serializer=json');
$proxy = Redis::create('redis://localhost', ['serializer' => \Redis::SERIALIZER_JSON]); // equivalent to above
```

**NOTE**: There is a performance trade off when using Redis serialization. Consider
creating a separate client for operations/logic that requires serialization.

## Redis Proxy API

```php
/** @var Zenstruck\Redis $proxy */

// call any \Redis|\RedisArray|\RedisCluster method
$proxy->set('mykey', 'value');
$proxy->get('mykey'); // "value"

// get the "real" client
$proxy->realClient(); // \Redis|\RedisArray|\RedisCluster
```

### Sequences/Pipelines and Transactions

The proxy has a fluent, auto-completable API for Redis pipelines and transactions:

```php
/** @var Zenstruck\Redis $proxy */

// use \Redis::multi()
$results = $proxy->transaction()
    ->set('x', '42')
    ->incr('x')
    ->get('x')->as('value') // alias the result of this command
    ->del('x')
    ->execute() // the results of the above transaction as an array (keyed by index of command or alias if set)
;

$results['value']; // "43" (result of ->get())
$results[3]; // true (result of ->del())

// use \Redis::pipeline() - see note below about \RedisCluster
$proxy->sequence()
    ->set('x', '42')
    ->incr('x')
    ->get('x')->as('value') // alias the result of this command
    ->del('x')
    ->execute() // the results of the above sequence as an array (keyed by index of command of alias if set)
;

$results['value']; // "43" (result of ->get())
$results[3]; // true (result of ->del())
```

**NOTE:** When using `sequence()` with `\RedisCluster`, the commands are executed
atomically as pipelines are not supported.

**NOTE:** When using `sequence()`/`transaction()` with a `\RedisArray` instance, the
first command in the sequence/transaction must be a "key-based command"
(ie `get()`/`set()`). This is to choose the node the transaction is run on.

### Countable\Iterable

`Zenstruck\Redis` is countable and iterable. There are some differences when
counting/iterating depending on the underlying client:

- `\Redis`: count is always 1 and iterates over itself once
- `\RedisArray`: count is the number of hosts and iterates over each host wrapped
  in a proxy.
- `\RedisCluser`: count is the number of _masters_ and iterates over each _master_
  with _node parameters_ pre-set. This enables running [node commands](https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#directed-node-commands)
  on each _master_ without passing node parameters to these commands (when iterating)

```php
/** @var Zenstruck\Redis $proxy */

$proxy->count(); // 1 if \Redis, # hosts if \RedisArray, # "masters" if \RedisCluster

foreach ($proxy as $node) {
    $proxy->flushAll(); // this is permitted even for \RedisCluster (which typically requires a $nodeParams argument)
}
```

**NOTE:** If running commands that require being run on each host/_master_ it is recommended
to iterate and run even if using `\Redis`. This allows a seamless transition to
`\RedisArray`/`\RedisCluster` later.

## Utilities

### ExpiringSet

`Zenstruck\Redis\Utility\ExpiringSet` encapsulates the concept of a _Redis expiring
set_: a set (unordered list with no duplicates) whose members expire after a time.
Each read/write operation on the set _prunes_ expired members.

```php
/** @var Zenstruck\Redis $client */

$set = $client->expiringSet('my-set'); // redis key to store the set

$set->add('member1', 600); // set add "member1" that expires in 10 minutes
$set->add('member1', new \DateInterval::createFromDateString('5 minutes')); // can use \DateInterval for the TTL
$set->add('member1', new \DateTime('+5 minutes')); // use \DateTimeInterface to set specific expiry timestamp

$set->remove('member1'); // explicitly remove a member

$set->all(); // array - all unexpired members

$set->contains('member'); // true/false

$set->clear(); // clear all items

$set->prune(); // explicitly "prune" the set (remove expired members)

count($set); // int - number of unexpired members

foreach ($set as $member) {
    // iterate over unexpired members
}

// fluent
$set
    ->add('member1', 600)
    ->add('member2', 600)
    ->remove('member1')
    ->remove('member2')
    ->prune()
    ->clear()
;
```

**NOTE**: In order to use complex types (arrays/objects) as members, your redis
client must be [configured with a serializer](#serializer-option).

Below is a pseudocode example using this object for tracking active users on a
website. When authenticated users login or request a page, their username is added
to the set with a 5-minute idle time-to-live (TTL). A user is considered _active_
within this time. On logout, they are removed from the set. If a user has not made
a request within their last TTL, they are removed from the set.

```php
/** @var Zenstruck\Redis $client */

$set = $client->expiringSet('active-users');
$ttl = \DateInterval::createFromDateString('5 minutes');

// LOGIN EVENT:
$set->add($event->getUsername(), $ttl);

// LOGOUT EVENT:
$set->remove($event->getUsername());

// REQUEST EVENT:
$set->add($event->getUsername(), $ttl);

// ADMIN MONITORING DASHBOARD WIDGET
$activeUserCount = count($set);
$activeUsernames = $set->all(); // [user1, user2, ...]

// ADMIN USER CRUD LISTING
foreach ($users as $user) {
    $isActive = $set->contains($user->getUsername()); // bool
    // ...
}
```

## Integrations

### Symfony Framework

Add a supported Redis [DSN](#redis-factory) environment variable:

```bash
# .env

REDIS_DSN=redis://localhost
```

Configure services:

```yaml
# config/packages/zenstruck_redis.yaml

services:

    # Proxy that is autowireable
    Zenstruck\Redis:
        factory: ['Zenstruck\Redis', 'create']
        arguments: ['%env(REDIS_DSN)%']

    # Separate proxy's that have different prefixes
    redis1:
        class: Zenstruck\Redis
        factory: ['Zenstruck\Redis', 'create']
        arguments: ['%env(REDIS_DSN)%', { prefix: 'prefix1:' }]
    redis2:
        class: Zenstruck\Redis
        factory: ['Zenstruck\Redis', 'create']
        arguments: ['%env(REDIS_DSN)%', { prefix: 'prefix2:' }]

    # Separate proxy that uses PHP serialization
    serialization_redis:
        class: Zenstruck\Redis
        factory: ['Zenstruck\Redis', 'create']
        arguments: ['%env(REDIS_DSN)%', { serializer: php }]

    # expiring set service
    active_users:
        class: Zenstruck\Redis\Utility\ExpiringSet
        factory: ['@Zenstruck\Redis', 'expiringSet']
        arguments:
            - active_users # redis key

    # Specific clients that are autowireable
    Redis:
        class: Redis
        factory: ['Zenstruck\Redis', 'createClient']
        arguments: ['%env(REDIS_DSN)%'] # note REDIS_DSN must be for \Redis client

    RedisArray:
        class: RedisArray
        factory: ['Zenstruck\Redis', 'createClient']
        arguments: ['%env(REDIS_DSN)%'] # note REDIS_DSN must be for \RedisArray client

    RedisCluster:
        class: RedisCluster
        factory: ['Zenstruck\Redis', 'createClient']
        arguments: ['%env(REDIS_DSN)%'] # note REDIS_DSN must be for \RedisCluster client
```

Use `Zenstruck\Redis` for session storage (see [Symfony Docs](https://symfony.com/doc/current/session/database.html#store-sessions-in-a-key-value-database-redis)
for more details/options):

```yaml
# config/services.yaml

# Assumes "Zenstruck\Redis" is available as a service and symfony/expression-language is installed
services:
    redis_session_handler:
        class:  Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler
        arguments:
            - "@=service('Zenstruck\\\\Redis').realClient()"

# config/packages/framework.yaml
framework:
    # ...
    session:
        handler_id: Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler
```

## Contributing

Running the test suite:

```bash
composer install
docker compose up -d # setup redis, redis-cluster, redis-sentinel
vendor/bin/phpunit -c phpunit.docker.xml
```

## Credit

Much of the code to create php-redis clients from a _DSN_ has been taken and modified
from the [Symfony Framework](https://github.com/symfony/symfony/blob/8e8207bb72d7f2cb8be355994ad2fcfa97c00f74/src/Symfony/Component/Cache/Traits/RedisTrait.php).
