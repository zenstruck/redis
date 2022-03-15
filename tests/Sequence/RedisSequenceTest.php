<?php

namespace Zenstruck\Redis\Tests\Sequence;

use Zenstruck\Redis;
use Zenstruck\Redis\Tests\SequenceTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RedisSequenceTest extends SequenceTest
{
    protected function createRedis(): Redis
    {
        return Redis::create(self::redisDsn());
    }
}
