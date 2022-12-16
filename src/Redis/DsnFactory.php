<?php

/*
 * This file is part of the zenstruck/redis package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Redis;

/**
 * @copyright Fabien Potencier <fabien@symfony.com>
 * @source https://github.com/symfony/symfony/blob/8e8207bb72d7f2cb8be355994ad2fcfa97c00f74/src/Symfony/Component/Cache/Traits/RedisTrait.php
 *
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class DsnFactory
{
    private const DEFAULT_OPTIONS = [
        'class' => null,
        'persistent' => 0,
        'persistent_id' => null,
        'timeout' => 30,
        'read_timeout' => 0,
        'retry_interval' => 0,
        'tcp_keepalive' => 0,
        'redis_cluster' => false,
        'redis_sentinel' => null,
        'dbindex' => 0,
        'failover' => 'none',
        'ssl' => null, // see https://php.net/context.ssl
        'prefix' => null,

        /**
         * One of: "php", "igbinary", "json", or {@see \Redis::SERIALIZER_*}.
         */
        'serializer' => null,
    ];

    public function __construct(private string $dsn, private array $options = [])
    {
    }

    public function __invoke(): \Redis|\RedisArray|\RedisCluster
    {
        if (\str_starts_with($this->dsn, 'redis:')) {
            $scheme = 'redis';
        } elseif (\str_starts_with($this->dsn, 'rediss:')) {
            $scheme = 'rediss';
        } else {
            throw new \InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s" does not start with "redis:" or "rediss".', $this->dsn));
        }

        $params = \preg_replace_callback('#^'.$scheme.':(//)?(?:(?:(?<user>[^:@]*+):)?(?<password>[^@]*+)@)?#', function($m) use (&$auth) {
            if (isset($m['password'])) {
                if (\in_array($m['user'], ['', 'default'], true)) {
                    $auth = $m['password'];
                } else {
                    $auth = [$m['user'], $m['password']];
                }

                if ('' === $auth) {
                    $auth = null;
                }
            }

            return 'file:'.($m[1] ?? '');
        }, $this->dsn);

        if (false === $params = \parse_url($params)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s".', $this->dsn));
        }

        $query = $hosts = [];

        $tls = 'rediss' === $scheme;
        $tcpScheme = $tls ? 'tls' : 'tcp';

        if (isset($params['query'])) {
            \parse_str($params['query'], $query);

            if (isset($query['host'])) {
                if (!\is_array($hosts = $query['host'])) {
                    throw new \InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s".', $this->dsn));
                }
                foreach ($hosts as $host => $parameters) {
                    if (\is_string($parameters)) {
                        \parse_str($parameters, $parameters);
                    }
                    if (false === $i = \mb_strrpos($host, ':')) {
                        $hosts[$host] = ['scheme' => $tcpScheme, 'host' => $host, 'port' => 6379] + $parameters;
                    } elseif ($port = (int) \mb_substr($host, 1 + $i)) {
                        $hosts[$host] = ['scheme' => $tcpScheme, 'host' => \mb_substr($host, 0, $i), 'port' => $port] + $parameters;
                    } else {
                        $hosts[$host] = ['scheme' => 'unix', 'path' => \mb_substr($host, 0, $i)] + $parameters;
                    }
                }
                $hosts = \array_values($hosts);
            }
        }

        if (isset($params['host']) || isset($params['path'])) {
            if (!isset($params['dbindex']) && isset($params['path'])) {
                if (\preg_match('#/(\d+)$#', $params['path'], $m)) {
                    $params['dbindex'] = $m[1];
                    $params['path'] = \mb_substr($params['path'], 0, -\mb_strlen($m[0]));
                } elseif (isset($params['host'])) {
                    throw new \InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s", the "dbindex" parameter must be a number.', $this->dsn));
                }
            }

            if (isset($params['host'])) {
                \array_unshift($hosts, ['scheme' => $tcpScheme, 'host' => $params['host'], 'port' => $params['port'] ?? 6379]);
            } else {
                \array_unshift($hosts, ['scheme' => 'unix', 'path' => $params['path']]);
            }
        }

        if (!$hosts) {
            throw new \InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s".', $this->dsn));
        }

        $params += $query + $this->options + self::DEFAULT_OPTIONS;

        if (isset($params['redis_sentinel']) && !\class_exists(\RedisSentinel::class)) {
            throw new \LogicException(\sprintf('Redis Sentinel support requires "redis" extension v5.2 or higher: "%s".', $this->dsn));
        }

        if ($params['redis_cluster'] && isset($params['redis_sentinel'])) {
            throw new \InvalidArgumentException(\sprintf('Cannot use both "redis_cluster" and "redis_sentinel" at the same time: "%s".', $this->dsn));
        }

        $class = $params['class'] ?? match (true) {
            false !== $params['redis_cluster'] => \RedisCluster::class,
            \count($hosts) > 1 => \RedisArray::class,
            default => \Redis::class,
        };

        if (\is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();

            $host = $hosts[0]['host'] ?? $hosts[0]['path'];
            $port = $hosts[0]['port'] ?? 0;

            if (isset($hosts[0]['host']) && $tls) {
                $host = 'tls://'.$host;
            }

            if (isset($params['redis_sentinel'])) {
                $sentinel = new \RedisSentinel($host, $port, $params['timeout'], (string) $params['persistent_id'], $params['retry_interval'], $params['read_timeout']);

                if (!$address = $sentinel->getMasterAddrByName($params['redis_sentinel'])) {
                    throw new \InvalidArgumentException(\sprintf('Failed to retrieve master information from master name "%s" and address "%s:%d".', $params['redis_sentinel'], $host, $port));
                }

                [$host, $port] = $address;
            }

            try {
                @$redis->{$connect}($host, $port, $params['timeout'], (string) $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...\defined('Redis::SCAN_PREFIX') ? [['stream' => $params['ssl'] ?? null]] : []);

                \set_error_handler(function($type, $msg) use (&$error) { $error = $msg; });

                try {
                    $isConnected = $redis->isConnected();
                } finally {
                    \restore_error_handler();
                }

                if (!$isConnected) {
                    $error = \preg_match('/^Redis::p?connect\(\): (.*)/', $error, $error) ? \sprintf(' (%s)', $error[1]) : '';

                    throw new \InvalidArgumentException(\sprintf('Redis connection "%s" failed: ', $this->dsn).$error.'.');
                }

                if ((null !== $auth && !$redis->auth($auth)) || ($params['dbindex'] && !$redis->select($params['dbindex']))) {
                    $e = \preg_replace('/^ERR /', '', $redis->getLastError());

                    throw new \InvalidArgumentException(\sprintf('Redis connection "%s" failed: ', $this->dsn).$e.'.');
                }

                if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                    $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                }
            } catch (\RedisException $e) {
                throw new \InvalidArgumentException(\sprintf('Redis connection "%s" failed: ', $this->dsn).$e->getMessage());
            }

            return self::configureClient($redis, $params);
        }

        if (\is_a($class, \RedisArray::class, true)) {
            foreach ($hosts as $i => $host) {
                $hosts[$i] = match ($host['scheme']) {
                    'tcp' => $host['host'].':'.$host['port'],
                    'tls' => 'tls://'.$host['host'].':'.$host['port'],
                    default => $host['path'],
                };
            }

            $params['lazy_connect'] = $params['lazy'] ?? true;
            $params['connect_timeout'] = $params['timeout'];

            try {
                $redis = new $class($hosts, $params);
            } catch (\RedisClusterException $e) {
                throw new \InvalidArgumentException(\sprintf('Redis connection "%s" failed: ', $this->dsn).$e->getMessage());
            }

            if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
            }

            return self::configureClient($redis, $params);
        }

        if (\is_a($class, \RedisCluster::class, true)) {
            foreach ($hosts as $i => $host) {
                $hosts[$i] = match ($host['scheme']) {
                    'tcp' => $host['host'].':'.$host['port'],
                    'tls' => 'tls://'.$host['host'].':'.$host['port'],
                    default => $host['path'],
                };
            }

            try {
                $redis = new $class(null, $hosts, $params['timeout'], $params['read_timeout'], (bool) $params['persistent'], $params['auth'] ?? '', ...\defined('Redis::SCAN_PREFIX') ? [$params['ssl'] ?? null] : []);
            } catch (\RedisClusterException $e) {
                throw new \InvalidArgumentException(\sprintf('Redis connection "%s" failed: ', $this->dsn).$e->getMessage());
            }

            if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
            }

            switch ($params['failover']) {
                case 'error':
                    $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_ERROR);
                    break;
                case 'distribute':
                    $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE);
                    break;
                case 'slaves':
                    $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE_SLAVES);
                    break;
            }

            return self::configureClient($redis, $params);
        }

        if (\class_exists($class, false)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a subclass of "Redis", "RedisArray" or "RedisCluster".', $class));
        }

        throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
    }

    private static function configureClient(\Redis|\RedisArray|\RedisCluster $client, array $params): \Redis|\RedisArray|\RedisCluster
    {
        if ($params['prefix']) {
            $client->setOption(\Redis::OPT_PREFIX, $params['prefix']);
        }

        if ($params['serializer']) {
            $client->setOption(\Redis::OPT_SERIALIZER, match ($params['serializer']) {
                'php' => \Redis::SERIALIZER_PHP,
                'json' => \Redis::SERIALIZER_JSON,
                'igbinary' => \Redis::SERIALIZER_IGBINARY,
                'msgpack' => \Redis::SERIALIZER_MSGPACK,
                default => $params['serializer'],
            });
        }

        return $client;
    }
}
