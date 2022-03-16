# zenstruck/redis

Lazy proxy for [php-redis](https://github.com/phpredis/phpredis) with DX helpers,
utilities and a unified API.

`Zenstruck\Redis` is a unified proxy for `\Redis|\RedisArray|\RedisCluster`. With
a few exceptions and considerations, the API is the same no matter the underlying
client. This allows you to use the same API in development, where you are likely
just using `\Redis`, and production, where you could be using `\RedisArray` or
`\RedisCluster`.

The proxy is lazy in that, if created via a DSN, doesn't instantiate the underlying
client until a command is executed.

## Installation

```bash
composer require zenstruck/redis
```

## Create Proxy

Creating a proxy is done via a _DSN_ string. Here are some examples:

```php
use Zenstruck\Redis;

$proxy = Redis::create('redis://localhost'); // Zenstruck\Redis wrapping \Redis

$proxy = Redis::create('redis://localhost?redis_sentinel=sentinel_service'); // Zenstruck\Redis wrapping \Redis (using Redis Sentinel)

$proxy = Redis::create('redis:?host[host1]&host[host2]'); // Zenstruck\Redis wrapping \RedisArray

$proxy = Redis::create('redis:?host[host1]&host[host2]&redis_cluster=1'); // Zenstruck\Redis wrapping \RedisCluster
```

**NOTE:** Add `?prefix={my-prefix}` to the DSN to prefix all keys.

You can also create a Proxy from an exising instance of `\Redis|\RedisArray|\RedisCluster`:

```php
use Zenstruck\Redis;

/** @var \Redis|\RedisArray|\RedisCluster $client */

$proxy = Redis::wrap($client)
```

### Create Client

An instance of `\Redis|\RedisArray|\RedisCluster` can be created directly:

```php
use Zenstruck\Redis;

$client = Redis::createClient('redis://localhost'); // \Redis

$client = Redis::createClient('redis://localhost?redis_sentinel=sentinel_service'); // \Redis (using Redis Sentinel)

$client = Redis::createClient('redis:?host[host1]&host[host2]'); // \RedisArray

$client = Redis::createClient('redis:?host[host1]&host[host2]&redis_cluster=1'); // \RedisCluster
```

## Proxy API

```php
/** @var Zenstruck\Redis $proxy */

// call any \Redis|\RedisArray|\RedisCluster method
$proxy->set('mykey', 'value');
$proxy->get('mykey'); // "value"

// get the underlying client
$proxy->client(); // \Redis|\RedisArray|\RedisCluster
```

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

### Sequences/Pipelines and Transactions

The proxy has a fluent, auto-completable API for Redis pipelines and transactions:

```php
/** @var Zenstruck\Redis $proxy */

// use \Redis|\RedisArray|\RedisCluster::multi()
$results = $proxy->transaction()
    ->set('x', '42')
    ->incr('x')
    ->get('x')->as('value') // alias the result of this command
    ->del('x')
    ->execute() // the results of the above transaction as an array (keyed by index of command or alias if set)
;

$results['value']; // "43" (result of ->get())
$results[3]; // true (result of ->del())

// use \Redis|\RedisArray::pipeline() - see note below about \RedisCluster
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

**NOTE:** When using `sequence()`/`transaction()` with a `\RedisArray` instance, a
key must be passed to the method (so `\RedisArray` knows which host to run on).
If there is the possibility \RedisArray could be used in the future, it's
recommended to always pass the key (it's ignored if not using `\RedisArray`):

```php
/** @var Zenstruck\Redis $proxy */

$results = $proxy->sequence('x') // pass key
    ->set('x', '42')
    ->incr('x')
    // ...
;

$results = $proxy->transaction('x') // pass key
    ->set('x', '42')
    ->incr('x')
    // ...
;
```

## Utilities

### ExpiringSet

## Integrations

### Symfony Framework

## Credit

Much of the code to create php-redis clients from a _DSN_ has been taken and modified
from the [Symfony Framework](https://github.com/symfony/symfony/blob/8e8207bb72d7f2cb8be355994ad2fcfa97c00f74/src/Symfony/Component/Cache/Traits/RedisTrait.php).
