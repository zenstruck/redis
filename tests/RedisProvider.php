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

        // todo \RedisCluster
    }

    public static function redisDsn(): string
    {
        return 'redis://'.$_ENV['REDIS_HOST1'];
    }

    public static function redisArrayDsn(): string
    {
        return \sprintf('redis:?host[%s]&host[%s]', $_ENV['REDIS_HOST1'], $_ENV['REDIS_HOST2']);
    }

    /**
     * @after
     */
    public static function resetRedis(): void
    {
        foreach (self::redisProvider() as [$client]) {
            $client->flushAll();
        }
    }
}
