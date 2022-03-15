<?php

namespace Zenstruck\Redis\Utility;

use Zenstruck\Redis;

/**
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

    public function push(mixed $value, int|\DateInterval $ttl): self
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = (float) \DateTime::createFromFormat('U', '0')->add($ttl)->format('U.u');
        }

        $time = \microtime(true);

        $this->client->instanceFor($this->key)->transaction()
            ->zRemRangeByScore($this->key, 0, $time)
            ->zAdd($this->key, $time + $ttl, $value)
            ->exec()
        ;

        unset($this->cachedList);

        return $this;
    }

    public function contains(mixed $value): bool
    {
        return \in_array($value, $this->all(), false);
    }

    /**
     * @return array<mixed>
     */
    public function all(): array
    {
        if (isset($this->cachedList)) {
            return $this->cachedList;
        }

        $time = \microtime(true);

        $result = $this->client->instanceFor($this->key)->transaction()
            ->zRemRangeByScore($this->key, 0, $time)
            ->zRangeByScore($this->key, $time, '+inf')
            ->exec()
        ;

        return $this->cachedList = $result[1] ?? [];
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

        unset($this->cachedList);

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
