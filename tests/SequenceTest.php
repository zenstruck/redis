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
    public function sequence_with_result_alias(): void
    {
        $this->assertSame(
            [
                'alias1' => [true, 'alias2' => 43],
                'alias3' => 44,
                2 => ['alias4' => '44', 1 => 1],
            ],
            $this->createRedis()->sequence($this->transactionKey())
                ->transaction()
                    ->set('x', '42')
                    ->incr('x')->as('alias2')
                ->commit()->as('alias1')
                ->incr('x')->as('alias3')
                ->transaction()
                    ->get('x')->as('alias4')
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

    /**
     * @test
     */
    public function cannot_alias_if_no_command_run(): void
    {
        $sequence = $this->createRedis()->sequence($this->transactionKey());

        $this->expectException(\LogicException::class);

        $sequence->as('alias');
    }

    /**
     * @test
     */
    public function cannot_alias_if_no_nested_transaction_command_run(): void
    {
        $sequence = $this->createRedis()->sequence($this->transactionKey())
            ->set('x', 'y')->as('alias1')
            ->transaction()
        ;

        $this->expectException(\LogicException::class);

        $sequence->as('alias2');
    }

    protected function transactionKey(): ?string
    {
        return null;
    }

    abstract protected function createRedis(): Redis;
}
