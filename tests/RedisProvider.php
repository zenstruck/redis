<?php

namespace Zenstruck\Redis\Tests;

use Zenstruck\Redis;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait RedisProvider
{
    public static function redisProvider(): \Traversable
    {
        foreach (self::redisDsnProvider() as [$dsn, $class]) {
            yield [Redis::create($dsn), $class];
        }
    }

    public static function redisDsnProvider(): \Traversable
    {
        yield [self::redisDsn(), \Redis::class];
        yield [self::redisArrayDsn(), \RedisArray::class];

        if ($hosts = \getenv('REDIS_CLUSTER_HOSTS')) {
            yield [self::redisClusterDsn($hosts), \RedisCluster::class];
        }
    }

    public static function redisDsn(): string
    {
        return 'redis://'.\getenv('REDIS_HOST1');
    }

    public static function redisArrayDsn(): string
    {
        return \sprintf('redis:?host[%s]&host[%s]', \getenv('REDIS_HOST1'), \getenv('REDIS_HOST2'));
    }

    public static function redisClusterDsn(string $hosts): string
    {
        return 'redis:?host['.\str_replace(' ', ']&host[', $hosts).']&redis_cluster=1';
    }

    /**
     * @after
     */
    public static function resetRedis(): void
    {
        foreach (self::redisProvider() as [$client]) {
            // todo encapsulate
            $client = $client->client();

            if ($client instanceof \RedisCluster) {
                foreach ($client->_masters() as $node) {
                    $client->flushAll($node);
                }

                continue;
            }

            $client->flushAll();
        }
    }
}
