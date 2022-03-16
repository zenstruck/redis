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
            $this->createRedis()->sequence($this->transactionKey())
                ->transaction()
                    ->set('x', '42')
                    ->incr('x')
                ->commit()
                ->incr('x')
                ->transaction()
                    ->get('x')
                    ->del('x')
                ->commit()
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
    public function cannot_call_transaction_within_transaction(): void
    {
        $sequence = $this->createRedis()->transaction($this->transactionKey());

        $this->expectException(\LogicException::class);

        $sequence->transaction();
    }

    /**
     * @test
     */
    public function cannot_commit_non_nested_transaction(): void
    {
        $sequence = $this->createRedis()->transaction($this->transactionKey());

        $this->expectException(\LogicException::class);

        $sequence->commit();
    }

    /**
     * @test
     */
    public function cannot_execute_nested_transaction(): void
    {
        $sequence = $this->createRedis()
            ->sequence($this->transactionKey())
            ->transaction()
        ;

        $this->expectException(\LogicException::class);

        $sequence->execute();
    }

    protected function transactionKey(): ?string
    {
        return null;
    }

    abstract protected function createRedis(): Redis;
}
