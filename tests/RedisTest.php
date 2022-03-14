<?php

namespace Zenstruck\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Zenstruck\Redis;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RedisTest extends TestCase
{
    use RedisProvider;

    /**
     * @test
     * @dataProvider redisDsnProvider
     */
    public function create_proxy_from_dsn(string $dsn, string $expectedClient): void
    {
        $this->assertInstanceOf($expectedClient, Redis::create($dsn)->client());
    }

    /**
     * @test
     * @dataProvider redisDsnProvider
     */
    public function create_from_dsn(string $dsn, string $expectedClient): void
    {
        $this->assertInstanceOf($expectedClient, Redis::createClient($dsn));
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function wrap(Redis $redis, string $expectedClient): void
    {
        $this->assertSame($redis, Redis::wrap($redis));
        $this->assertInstanceOf($expectedClient, Redis::wrap($redis->client())->client());
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function basic_operation(Redis $redis): void
    {
        $redis->set('foo', 'bar');

        $this->assertSame('bar', $redis->get('foo'));
    }
}
