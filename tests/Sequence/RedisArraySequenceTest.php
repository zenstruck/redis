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
final class RedisArraySequenceTest extends SequenceTest
{
    /**
     * @test
     */
    public function sequence_first_command_must_use_a_key(): void
    {
        $sequence = $this->createRedis()->sequence();

        $this->expectException(\LogicException::class);

        $sequence->keys('*');
    }

    /**
     * @test
     */
    public function transaction_first_command_must_use_a_key(): void
    {
        $transaction = $this->createRedis()->sequence();

        $this->expectException(\LogicException::class);

        $transaction->keys('*');
    }

    protected function createRedis(): Redis
    {
        return Redis::create(self::redisArrayDsn());
    }
}
