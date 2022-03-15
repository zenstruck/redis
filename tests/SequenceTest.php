<?php

namespace Zenstruck\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Zenstruck\Redis;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class SequenceTest extends TestCase
{
    use RedisProvider;

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function sequence(Redis $redis): void
    {
        $this->assertSame(
            [
                true,
                [true, 43],
                true,
                ['43', 1],
                true,
            ],
            $redis->sequence()
                ->ping()
                ->multi()
                    ->set('x', '42')
                    ->incr('x')
                ->exec()
                ->ping()
                ->multi()
                    ->get('x')
                    ->del('x')
                ->exec()
                ->ping()
                ->exec()
        );

        $this->assertSame([], $redis->sequence()->exec());
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function transaction(Redis $redis): void
    {
        $this->assertSame(
            [
                true,
                true,
                43,
                true,
                '43',
                1,
                true,
            ],
            $redis->transaction()
                ->ping()
                ->set('x', '42')
                ->incr('x')
                ->ping()
                ->get('x')
                ->del('x')
                ->ping()
                ->exec()
        );

        $this->assertSame([], $redis->transaction()->exec());
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function cannot_call_multi_within_transaction(Redis $redis): void
    {
        $sequence = $redis->transaction();

        $this->expectException(\LogicException::class);

        $sequence->multi();
    }
}
