<?php

namespace Zenstruck\Redis\Tests\Sequence;

use Zenstruck\Redis;
use Zenstruck\Redis\Tests\SequenceTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RedisArraySequenceTest extends SequenceTest
{
    /**
     * @test
     */
    public function cannot_create_sequence_without_instance_key(): void
    {
        $redis = Redis::create(self::redisArrayDsn());

        $this->expectException(\LogicException::class);

        $redis->sequence();
    }

    /**
     * @test
     */
    public function cannot_create_transaction_without_instance_key(): void
    {
        $redis = Redis::create(self::redisArrayDsn());

        $this->expectException(\LogicException::class);

        $redis->transaction();
    }

    protected function transactionKey(): ?string
    {
        return 'foo';
    }

    protected function createRedis(): Redis
    {
        return Redis::create(self::redisArrayDsn());
    }
}
