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
                [true, 43],
                44,
                ['44', 1],
            ],
            $this->createRedis()->sequence()
                ->multi()
                    ->set('x', '42')
                    ->incr('x')
                ->exec()
                ->incr('x')
                ->multi()
                    ->get('x')
                    ->del('x')
                ->exec()
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
                43,
                '43',
                1,
            ],
            $this->createRedis()->transaction()
                ->set('x', '42')
                ->incr('x')
                ->get('x')
                ->del('x')
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
