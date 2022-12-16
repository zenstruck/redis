<?php

/*
 * This file is part of the zenstruck/redis package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $this->assertInstanceOf($expectedClient, Redis::create($dsn)->realClient());
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
        $this->assertInstanceOf($expectedClient, Redis::wrap($redis->realClient())->realClient());
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function basic_operations(Redis $redis): void
    {
        $this->assertSame(0, $redis->exists('foo'));

        $redis->set('foo', 'bar');

        $this->assertSame('bar', $redis->get('foo'));
        $this->assertSame(1, $redis->exists('foo'));

        $redis->del('foo');

        $this->assertSame(0, $redis->exists('foo'));
    }

    /**
     * @test
     * @dataProvider redisDsnProvider
     */
    public function can_add_prefix(string $dsn, string $class): void
    {
        $redis = Redis::create($dsn, ['prefix' => '_my-prefix:']);

        $redis->set('foo', 'bar');

        $keys = $redis->keys('*');

        if (\RedisArray::class === $class) {
            $keys = $keys[\array_key_first($keys)];
        }

        $this->assertSame(['_my-prefix:foo'], $keys);
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function is_countable(Redis $redis, string $class): void
    {
        $expectedCount = match ($class) {
            \RedisArray::class => \count($redis->_hosts()),
            \RedisCluster::class => \count($redis->_masters()),
            default => 1,
        };

        $this->assertCount($expectedCount, $redis);
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function is_iterable(Redis $redis, string $class): void
    {
        $expectedCount = match ($class) {
            \RedisArray::class => \count($redis->_hosts()),
            \RedisCluster::class => \count($redis->_masters()),
            default => 1,
        };

        $results = [];

        foreach ($redis as $node) {
            $results[] = $node->ping();
        }

        $this->assertSame(\array_fill(0, $expectedCount, true), $results);
    }

    /**
     * @test
     * @dataProvider redisProvider
     */
    public function default_serializer(Redis $redis): void
    {
        $redis->set('foo', ['bar']);
        $redis->set('bar', new \stdClass());
        $redis->set('baz', 17);

        $this->assertSame(['Array', 'Object', '17'], $redis->mGet(['foo', 'bar', 'baz']));
    }

    /**
     * @test
     * @dataProvider redisSerializerProvider
     */
    public function can_configure_serializer(Redis $redis, int $type): void
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';

        $redis->set('foo', ['bar']);
        $redis->set('bar', $obj);
        $redis->set('baz', 17);
        $redis->set('qux', null);

        $this->assertSame(['bar'], $redis->get('foo'));
        $this->assertSame(17, $redis->get('baz'));
        $this->assertEquals(\Redis::SERIALIZER_JSON === $type ? ['foo' => 'bar'] : $obj, $redis->get('bar'));
        $this->assertNull($redis->get('qux'));
    }
}
