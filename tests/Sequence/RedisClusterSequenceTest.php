<?php

namespace Zenstruck\Redis\Tests\Sequence;

use Zenstruck\Redis;
use Zenstruck\Redis\Tests\SequenceTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RedisClusterSequenceTest extends SequenceTest
{
    /**
     * @before
     */
    public function ensureRedisClusterAvailable(): void
    {
        if (!\getenv('REDIS_CLUSTER_HOSTS')) {
            $this->markTestSkipped('RedisCluster not available.');
        }
    }

    /**
     * @test
     */
    public function sequence(): void
    {
        $this->expectException(\LogicException::class);

        parent::sequence();
    }

    protected function createRedis(): Redis
    {
        return Redis::create(self::redisClusterDsn(\getenv('REDIS_CLUSTER_HOSTS')));
    }
}
