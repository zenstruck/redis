<?php

/*
 * This file is part of the zenstruck/redis package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    protected function createRedis(): Redis
    {
        return Redis::create(self::redisClusterDsn(\getenv('REDIS_CLUSTER_HOSTS')));
    }
}
