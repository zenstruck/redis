<?php

/*
 * This file is part of the zenstruck/redis package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Redis\Utility;

use Zenstruck\Redis;

/**
 * An encapsulated Redis set whose members expire.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @template T
 *
 * @implements \IteratorAggregate<int,T>
 */
final class ExpiringSet implements \Countable, \IteratorAggregate
{
    /** @var Redis<\Redis|\RedisArray|\RedisCluster> */
    private Redis $client;

    /** @var list<mixed> */
    private array $cachedList;

    private bool $usingSerialization;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|Redis<\Redis|\RedisArray|\RedisCluster> $client
     */
    public function __construct(private string $key, \Redis|\RedisArray|\RedisCluster|Redis $client)
    {
        $this->client = Redis::wrap($client);
    }

    /**
     * @param T                  $value
     * @param int|float          $expiry time-to-live in seconds
     * @param \DateInterval      $expiry time-to-live
     * @param \DateTimeInterface $expiry specific expiry timestamp
     *
     * @return self<T>
     */
    public function add(mixed $value, int|float|\DateInterval|\DateTimeInterface $expiry): self
    {
        if (!\is_scalar($value) && null !== $value && !$this->usingSerialization()) {
            throw new \LogicException('Cannot add non-scalar values as the Redis client was not configured with serialization.');
        }

        $time = \microtime(true);

        if (\is_numeric($expiry)) {
            $expiry = $time + $expiry;
        }

        if ($expiry instanceof \DateTimeInterface) {
            $expiry = (float) $expiry->format('U.u');
        }

        if ($expiry instanceof \DateInterval) {
            $expiry = $time + (float) \DateTime::createFromFormat('U', '0')->add($expiry)->format('U.u');
        }

        $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, $time)
            ->zAdd($this->key, $expiry, $value)
            ->execute()
        ;

        unset($this->cachedList);

        return $this;
    }

    /**
     * @param T $value
     *
     * @return self<T>
     */
    public function remove(mixed $value): self
    {
        $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, \microtime(true))
            ->zRem($this->key, $value)
            ->execute()
        ;

        unset($this->cachedList);

        return $this;
    }

    /**
     * @param T $value
     */
    public function contains(mixed $value): bool
    {
        $result = $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, \microtime(true))
            ->zRank($this->key, $value)->as('position')
            ->execute()
        ;

        return false !== $result['position'];
    }

    /**
     * @return T[]
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

    /**
     * @return self<T>
     */
    public function prune(): self
    {
        $this->client->zRemRangeByScore($this->key, 0, \microtime(true));

        unset($this->cachedList);

        return $this;
    }

    /**
     * @return self<T>
     */
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
        if (isset($this->cachedList)) {
            return \count($this->cachedList);
        }

        return $this->client->transaction()
            ->zRemRangeByScore($this->key, 0, \microtime(true))
            ->zCard($this->key)->as('count')
            ->execute()['count']
        ;
    }

    private function usingSerialization(): bool
    {
        return $this->usingSerialization ??= (bool) \array_values((array) $this->client->getOption(\Redis::OPT_SERIALIZER))[0];
    }
}
