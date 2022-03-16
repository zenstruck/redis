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
                43,
                44,
                '44',
                1,
            ],
            $this->createRedis()->sequence($this->transactionKey())
                ->set('x', '42')
                ->incr('x')
                ->incr('x')
                ->get('x')
                ->del('x')
                ->execute()
        );

        $this->assertSame([], $this->createRedis()->sequence($this->transactionKey())->execute());
    }

    /**
     * @test
     */
    public function sequence_with_result_alias(): void
    {
        $this->assertSame(
            [
                0 => true,
                'alias1' => 43,
                'alias2' => 44,
                'alias3' => '44',
                4 => 1,
            ],
            $this->createRedis()->sequence($this->transactionKey())
                ->set('x', '42')
                ->incr('x')->as('alias1')
                ->incr('x')->as('alias2')
                ->get('x')->as('alias3')
                ->del('x')
                ->execute()
        );

        $this->assertSame([], $this->createRedis()->sequence($this->transactionKey())->execute());
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
            $this->createRedis()->transaction($this->transactionKey())
                ->set('x', '42')
                ->incr('x')
                ->get('x')
                ->del('x')
                ->execute()
        );

        $this->assertSame([], $this->createRedis()->transaction($this->transactionKey())->execute());
    }

    /**
     * @test
     */
    public function transaction_with_result_alias(): void
    {
        $this->assertSame(
            [
                true,
                'alias1' => 43,
                2 => '43',
                'alias2' => 1,
            ],
            $this->createRedis()->transaction($this->transactionKey())
                ->set('x', '42')
                ->incr('x')->as('alias1')
                ->get('x')
                ->del('x')->as('alias2')
                ->execute()
        );

        $this->assertSame([], $this->createRedis()->transaction($this->transactionKey())->execute());
    }

    /**
     * @test
     */
    public function cannot_alias_if_no_command_run(): void
    {
        $sequence = $this->createRedis()->sequence($this->transactionKey());

        $this->expectException(\LogicException::class);

        $sequence->as('alias');
    }

    protected function transactionKey(): ?string
    {
        return null;
    }

    abstract protected function createRedis(): Redis;
}
