<?php

namespace Zenstruck;

use Zenstruck\Redis\DsnFactory;
use Zenstruck\Redis\Sequence;
use Zenstruck\Redis\Utility\ExpiringSet;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @template T
 *
 * @mixin \Redis
 * @mixin T
 *
 * @implements \IteratorAggregate<int,Redis<T>>
 */
final class Redis implements \Countable, \IteratorAggregate
{
    private const CLUSTER_NODE_METHODS = ['SAVE', 'BGSAVE', 'FLUSHDB', 'FLUSHALL', 'DBSIZE', 'BGREWRITEAOF', 'LASTSAVE', 'INFO', 'CLIENT', 'CLUSTER', 'CONFIG', 'PUBSUB', 'SLOWLOG', 'RANDOMKEY', 'PING', 'SCAN'];

    private \Closure|\Redis|\RedisArray|\RedisCluster $client;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|callable():(\Redis|\RedisArray|\RedisCluster) $client
     * @param list<mixed>|null $node
     */
    private function __construct(callable|\Redis|\RedisArray|\RedisCluster $client, private ?array $node = null)
    {
        $this->client = \is_callable($client) ? \Closure::fromCallable($client) : $client;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $client = $this->realClient();

        if ($client instanceof \RedisCluster && $this->node && \in_array(\mb_strtoupper($method), self::CLUSTER_NODE_METHODS, true)) {
            $arguments = [$this->node, ...$arguments];
        }

        return $client->{$method}(...$arguments);
    }

    /**
     * Create an instance from an existing PhpRedis instance.
     *
     * @template U of \Redis|\RedisArray|\RedisCluster
     *
     * @param U|self<U> $client
     *
     * @return self<U>
     */
    public static function wrap(self|\Redis|\RedisArray|\RedisCluster $client): self
    {
        if ($client instanceof self) {
            return $client;
        }

        return new self($client);
    }

    /**
     * Create a "lazy" instance from a DSN. The connection is not made
     * until the first command is called.
     *
     * @param array<string,mixed> $options {@see DsnFactory::DEFAULT_OPTIONS}
     *
     * @return self<\Redis|\RedisArray|\RedisCluster>
     */
    public static function create(string $dsn, array $options = []): self
    {
        return new self(new DsnFactory($dsn, $options));
    }

    /**
     * Create a PhpRedis client instance from a DSN.
     *
     * @param array<string,mixed> $options {@see DsnFactory::DEFAULT_OPTIONS}
     */
    public static function createClient(string $dsn, array $options = []): \Redis|\RedisArray|\RedisCluster
    {
        return self::create($dsn, $options)->realClient();
    }

    /**
     * Get the "real" PhpRedis client.
     */
    public function realClient(): \Redis|\RedisArray|\RedisCluster
    {
        if ($this->client instanceof \Closure) {
            $this->client = $this->client->__invoke();
        }

        return $this->client;
    }

    /**
     * Create a command sequence/pipeline with a unified API. Uses
     * {@see \Redis::pipeline()} if applicable.
     *
     * For {@see \RedisArray}, the first command must be a "key command"
     * in order to get the correct host - {@see Sequence::KEY_COMMANDS}.
     *
     * For {@see \RedisCluster}, the API is the same but commands are
     * executed atomically.
     */
    public function sequence(): Sequence
    {
        return new Sequence($this, false);
    }

    /**
     * Create a command transaction with a unified API using
     * {@see \Redis::multi()}.
     *
     * For {@see \RedisArray}, the first command must be a "key command"
     * in order to get the correct host - {@see Sequence::KEY_COMMANDS}.
     */
    public function transaction(): Sequence
    {
        return new Sequence($this, true);
    }

    /**
     * Create a new {@see ExpiringSet}.
     */
    public function expiringSet(string $key): ExpiringSet
    {
        return new ExpiringSet($key, $this);
    }

    /**
     * Count the number of underlying clients.
     *
     * @see \Redis        is always 1
     * @see \RedisArray   is the number of hosts
     * @see \RedisCluster is the number of "masters"
     */
    public function count(): int
    {
        $client = $this->realClient();

        return match ($client::class) {
            \RedisArray::class => \count($client->_hosts()),
            \RedisCluster::class => \count($client->_masters()),
            default => 1,
        };
    }

    /**
     * Iterate over the underlying clients.
     *
     * @see \Redis        iterates over a single instance
     * @see \RedisArray   iterates over the hosts (proxied)
     * @see \RedisCluster iterates over the "masters" and removes the
     *                    need to pass "node parameters" to certain
     *                    commands {@see CLUSTER_NODE_METHODS}
     */
    public function getIterator(): \Traversable
    {
        $client = $this->realClient();

        if ($client instanceof \Redis) {
            yield $this;

            return;
        }

        if ($client instanceof \RedisArray) {
            foreach ($client->_hosts() as $host) {
                yield new self($client->_instance($host));
            }

            return;
        }

        foreach ($client->_masters() as $node) {
            yield new self($client, $node);
        }
    }
}
