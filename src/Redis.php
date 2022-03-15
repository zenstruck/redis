<?php

namespace Zenstruck;

use Zenstruck\Redis\DsnFactory;
use Zenstruck\Redis\Sequence;
use Zenstruck\Redis\Utility\ExpiringSet;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @mixin \Redis
 */
final class Redis
{
    private \Closure|\Redis|\RedisArray|\RedisCluster $client;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|callable():(\Redis|\RedisArray|\RedisCluster) $client
     */
    private function __construct(callable|\Redis|\RedisArray|\RedisCluster $client)
    {
        $this->client = \is_callable($client) ? \Closure::fromCallable($client) : $client;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->client()->{$method}(...$arguments);
    }

    public static function wrap(self|\Redis|\RedisArray|\RedisCluster $client): self
    {
        if ($client instanceof self) {
            return $client;
        }

        return new self($client);
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function create(string $dsn, array $options = []): self
    {
        return new self(new DsnFactory($dsn, $options));
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function createClient(string $dsn, array $options = []): \Redis|\RedisArray|\RedisCluster
    {
        return self::create($dsn, $options)->client();
    }

    public function client(): \Redis|\RedisArray|\RedisCluster
    {
        if ($this->client instanceof \Closure) {
            $this->client = $this->client->__invoke();
        }

        return $this->client;
    }

    public function instanceFor(string $key): self
    {
        $client = $this->client();

        if ($client instanceof \Redis) {
            return $this;
        }

        if ($client instanceof \RedisArray) {
            return new self($client->_instance($client->_target($key)));
        }

        throw new \LogicException('todo - RedisCluster...');
    }

    public function sequence(): Sequence
    {
        $client = $this->client();

        if ($client instanceof \RedisCluster) {
            throw new \LogicException('todo...');
        }

        if ($client instanceof \RedisArray) {
            throw new \LogicException('You must first choose an instance (with "instanceFor()") before creating a sequence.');
        }

        return new Sequence($client->pipeline(), false);
    }

    public function transaction(): Sequence
    {
        $client = $this->client();

        if ($client instanceof \RedisArray) {
            throw new \LogicException('You must first choose an instance (with "instanceFor()") before creating a transaction.');
        }

        return new Sequence($client->multi(), true);
    }

    public function expiringSet(string $key): ExpiringSet
    {
        return new ExpiringSet($key, $this);
    }
}
