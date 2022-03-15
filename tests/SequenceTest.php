<?php

namespace Zenstruck\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Zenstruck\Redis;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class SequenceTest extends TestCase
{
    use RedisProvider;

    /**
     * @test
     */
    public function sequence(): void
    {
        $this->assertSame(
            [
                true,
                [true, 43],
                true,
                ['43', 1],
                true,
            ],
            $this->createRedis()->sequence()
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

        $this->assertSame([], $this->createRedis()->sequence()->exec());
    }

    /**
     * @test
     */
    public function transaction(): void
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
            $this->createRedis()->transaction()
                ->ping()
                ->set('x', '42')
                ->incr('x')
                ->ping()
                ->get('x')
                ->del('x')
                ->ping()
                ->exec()
        );

        $this->assertSame([], $this->createRedis()->transaction()->exec());
    }

    /**
     * @test
     */
    public function cannot_call_multi_within_transaction(): void
    {
        $sequence = $this->createRedis()->transaction();

        $this->expectException(\LogicException::class);

        $sequence->multi();
    }

    abstract protected function createRedis(): Redis;
}
