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
        yield [$_ENV['REDIS_HOST'], \Redis::class];

        // todo \RedisArray|\RedisCluster
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
