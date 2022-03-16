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
final class Redis implements \Countable
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

    /**
     * Create a command pipeline. Uses {@see \Redis::pipeline()} if
     * applicable. For \RedisCluser, the API is the same but commands
     * are executed atomically.
     *
     * @param string|null $key Required if RedisArray, ignored otherwise
     */
    public function sequence(?string $key = null): Sequence
    {
        $client = $this->client();

        if ($client instanceof \RedisArray) {
            if (null === $key) {
                throw new \LogicException(\sprintf('When using a RedisArray, a key must be passed to %s() to choose an instance.', __METHOD__));
            }

            $client = $client->_instance($client->_target($key));
        }

        if ($client instanceof \Redis) {
            $client = $client->pipeline();
        }

        return new Sequence($client, false);
    }

    /**
     * Create a command transaction (using {@see \Redis::multi()}).
     *
     * @param string|null $key Required if RedisArray, ignored otherwise
     */
    public function transaction(?string $key = null): Sequence
    {
        $client = $this->client();

        if ($client instanceof \RedisArray) {
            if (null === $key) {
                throw new \LogicException(\sprintf('When using a RedisArray, a key must be passed to %s() to choose an instance.', __METHOD__));
            }

            $client = $client->_instance($client->_target($key));
        }

        return new Sequence($client->multi(), true);
    }

    public function expiringSet(string $key): ExpiringSet
    {
        return new ExpiringSet($key, $this);
    }

    public function count(): int
    {
        $client = $this->client();

        return match ($client::class) {
            \RedisArray::class => \count($client->_hosts()),
            \RedisCluster::class => \count($client->_masters()),
            default => 1,
        };
    }
}
