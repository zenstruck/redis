<?php

/*
 * This file is part of the zenstruck/redis package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Redis;

use Symfony\Component\Cache\Traits\RedisTrait;

/**
 * @copyright Fabien Potencier <fabien@symfony.com>
 * @source https://github.com/symfony/symfony/blob/8e8207bb72d7f2cb8be355994ad2fcfa97c00f74/src/Symfony/Component/Cache/Traits/RedisTrait.php
 *
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class DsnFactory
{
    use RedisTrait;

    public function __construct(private string $dsn, private array $options = [])
    {
    }

    public function __invoke(): \Redis|\RedisArray|\RedisCluster
    {
        $client = self::createConnection($this->dsn, $this->options);

        if (!$client instanceof \Redis && !$client instanceof \RedisArray && !$client instanceof \RedisCluster) {
            throw new \LogicException(\sprintf('Only \Redis, \RedisArray and \RedisCluster are supported, got "%s".', \get_debug_type($client)));
        }

        if (isset($this->options['prefix'])) {
            $client->setOption(\Redis::OPT_PREFIX, $this->options['prefix']);
        }

        if (isset($this->options['serializer'])) {
            $client->setOption(\Redis::OPT_SERIALIZER, match ($this->options['serializer']) {
                'php' => \Redis::SERIALIZER_PHP,
                'json' => \Redis::SERIALIZER_JSON,
                'igbinary' => \Redis::SERIALIZER_IGBINARY,
                'msgpack' => \Redis::SERIALIZER_MSGPACK,
                default => $this->options['serializer'],
            });
        }

        return $client;
    }

    private static function configureClient(\Redis|\RedisArray|\RedisCluster $client, array $params): \Redis|\RedisArray|\RedisCluster
    {
        if ($params['prefix']) {
            $client->setOption(\Redis::OPT_PREFIX, $params['prefix']);
        }

        if ($params['serializer']) {
            $client->setOption(\Redis::OPT_SERIALIZER, match ($params['serializer']) {
                'php' => \Redis::SERIALIZER_PHP,
                'json' => \Redis::SERIALIZER_JSON,
                'igbinary' => \Redis::SERIALIZER_IGBINARY,
                'msgpack' => \Redis::SERIALIZER_MSGPACK,
                default => $params['serializer'],
            });
        }

        return $client;
    }
}
