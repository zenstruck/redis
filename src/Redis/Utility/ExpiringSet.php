<?php

namespace Zenstruck\Redis\Utility;

use Zenstruck\Redis;

/**
 * An encapsulated Redis set whose members expire.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @implements \IteratorAggregate<int,mixed>
 */
final class ExpiringSet implements \Countable, \IteratorAggregate
{
    private Redis $client;

    /** @var list<mixed> */
    private array $cachedList;

    public function __construct(private string $key, \Redis|\RedisArray|\RedisCluster|Redis $client)
    {
        $this->client = Redis::wrap($client);
    }

    public function add(mixed $value, int|\DateInterval $ttl): self
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = (float) \DateTime::createFromFormat('U', '0')->add($ttl)->format('U.u');
        }

        $time = \microtime(true);

        $result = $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, $time)
            ->zAdd($this->key, $time + $ttl, $value)
            ->zRangeByScore($this->key, $time, '+inf')->as('list')
            ->execute()
        ;

        $this->cachedList = $result['list'];

        return $this;
    }

    public function remove(mixed $value): self
    {
        $result = $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, $time = \microtime(true))
            ->zRem($this->key, $value)
            ->zRangeByScore($this->key, $time, '+inf')->as('list')
            ->execute()
        ;

        $this->cachedList = $result['list'];

        return $this;
    }

    public function contains(mixed $value): bool
    {
        return \in_array($value, $this->all(), false);
    }

    /**
     * @return list<mixed>
     */
    public function all(): array
    {
        if (isset($this->cachedList)) {
            return $this->cachedList;
        }

        $time = \microtime(true);

        $result = $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, $time)
            ->zRangeByScore($this->key, $time, '+inf')->as('list')
            ->execute()
        ;

        return $this->cachedList = $result['list'] ?? [];
    }

    public function prune(): self
    {
        $this->client->zRemRangeByScore($this->key, 0, \microtime(true));

        unset($this->cachedList);

        return $this;
    }

    public function clear(): self
    {
        $this->client->del($this->key);
        $this->cachedList = [];

        return $this;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->all());
    }

    public function count(): int
    {
        return \count($this->all());
    }
}
