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
    public function pipeline(Redis $redis): void
    {
        $this->assertSame(
            [
                true,
                [true, 43],
                true,
                ['43', 1],
                true
            ],
            $redis->pipeline()
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

        $this->assertSame([], $redis->pipeline()->exec());
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function multi(Redis $redis): void
    {
        $this->assertSame(
            [
                true,
                true,
                43,
                true,
                '43',
                1,
                true
            ],
            $redis->multi()
                ->ping()
                ->set('x', '42')
                ->incr('x')
                ->ping()
                ->get('x')
                ->del('x')
                ->ping()
                ->exec()
        );

        $this->assertSame([], $redis->multi()->exec());
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function cannot_call_multi_within_multi(Redis $redis): void
    {
        $sequence = $redis->multi();

        $this->expectException(\LogicException::class);

        $sequence->multi();
    }
}
