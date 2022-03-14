<?php

const SKIPPED_METHODS = [
    'exec',
    'pipeline',
    'multi',
    'connect',
    'open',
    'isConnected',
    'getHost',
    'getPort',
    'getDbNum',
    'getTimeout',
    'getReadTimeout',
    'getPersistentID',
    'getAuth',
    'pconnect',
    'popen',
    'close',
    'swapdb',
    'setOption',
    'getOption',
];

foreach ((new \ReflectionClass(RedisStub::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
    if (\str_starts_with($method->name, '_') || \in_array($method->name, SKIPPED_METHODS, true)) {
        continue;
    }

    $parameters = \array_map(
        function(ReflectionParameter $p) {
            $param = '$'.$p->name;

            if ($p->isVariadic()) {
                $param = '...'.$param;
            }

            if ($p->isDefaultValueAvailable()) {
                $default = $p->getDefaultValue();
                $param .= ' = '.match (true) {
                    null === $default => 'null',
                    \is_array($default) && !$default => '[]',
                    default => \var_export($default, true),
                };
            }

            return $param;
        },
        $method->getParameters(),
    );

    echo \sprintf(" * @method self %s(%s)\n", $method->name, \implode(', ', $parameters));
}

abstract class RedisStub
{
    public const AFTER = 'after';
    public const BEFORE = 'before';

    public const OPT_SERIALIZER = 1;
    public const OPT_PREFIX = 2;
    public const OPT_READ_TIMEOUT = 3;
    public const OPT_SCAN = 4;
    public const OPT_FAILOVER = 5;
    public const OPT_TCP_KEEPALIVE = 6;
    public const OPT_COMPRESSION = 7;
    public const OPT_REPLY_LITERAL = 8;
    public const OPT_COMPRESSION_LEVEL = 9;

    public const FAILOVER_NONE = 0;
    public const FAILOVER_ERROR = 1;
    public const FAILOVER_DISTRIBUTE = 2;
    public const FAILOVER_DISTRIBUTE_SLAVES = 3;

    public const SCAN_NORETRY = 0;
    public const SCAN_RETRY = 1;

    public const SCAN_PREFIX = 2;

    public const SCAN_NOPREFIX = 3;

    public const SERIALIZER_NONE = 0;
    public const SERIALIZER_PHP = 1;
    public const SERIALIZER_IGBINARY = 2;
    public const SERIALIZER_MSGPACK = 3;
    public const SERIALIZER_JSON = 4;

    public const COMPRESSION_NONE = 0;
    public const COMPRESSION_LZF = 1;
    public const COMPRESSION_ZSTD = 2;
    public const COMPRESSION_LZ4 = 3;

    public const COMPRESSION_ZSTD_MIN = 1;
    public const COMPRESSION_ZSTD_DEFAULT = 3;
    public const COMPRESSION_ZSTD_MAX = 22;

    public const ATOMIC = 0;
    public const MULTI = 1;
    public const PIPELINE = 2;

    public const REDIS_NOT_FOUND = 0;
    public const REDIS_STRING = 1;
    public const REDIS_SET = 2;
    public const REDIS_LIST = 3;
    public const REDIS_ZSET = 4;
    public const REDIS_HASH = 5;
    public const REDIS_STREAM = 6;

    abstract public function connect(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $reserved = null,
        $retryInterval = 0,
        $readTimeout = 0.0
    );

    #[Deprecated(replacement: '%class%->connect(%parametersList%)')]
    abstract public function open($host, $port = 6379, $timeout = 0.0, $reserved = null, $retryInterval = 0, $readTimeout = 0.0);

    abstract public function isConnected();

    abstract public function getHost();

    abstract public function getPort();

    abstract public function getDbNum();

    abstract public function getTimeout();

    abstract public function getReadTimeout();

    abstract public function getPersistentID();

    abstract public function getAuth();

    abstract public function pconnect($host, $port = 6379, $timeout = 0.0, $persistentId = null, $retryInterval = 0, $readTimeout = 0.0);

    #[Deprecated(replacement: '%class%->pconnect(%parametersList%)')]
    abstract public function popen($host, $port = 6379, $timeout = 0.0, $persistentId = '', $retryInterval = 0, $readTimeout = 0.0);

    abstract public function close();

    abstract public function swapdb(int $db1, int $db2);

    abstract public function setOption($option, $value);

    abstract public function getOption($option);

    abstract public function ping($message = null);

    abstract public function echo($message);

    abstract public function get($key);

    abstract public function set($key, $value, $timeout = null);

    abstract public function setEx($key, $ttl, $value);

    abstract public function pSetEx($key, $ttl, $value);

    abstract public function setNx($key, $value);

    abstract public function del($key1, ...$otherKeys);

    #[Deprecated(replacement: '%class%->del(%parametersList%)')]
    abstract public function delete($key1, $key2 = null, $key3 = null);

    abstract public function unlink($key1, $key2 = null, $key3 = null);

    abstract public function multi($mode = Redis::MULTI);

    abstract public function pipeline();

    abstract public function exec();

    abstract public function discard();

    abstract public function watch($key);

    abstract public function unwatch();

    abstract public function subscribe($channels, $callback);

    abstract public function psubscribe($patterns, $callback);

    abstract public function publish($channel, $message);

    abstract public function pubsub($keyword, $argument);

    abstract public function unsubscribe($channels = null);

    abstract public function punsubscribe($patterns = null);

    abstract public function exists($key);

    abstract public function incr($key);

    abstract public function incrByFloat($key, $increment);

    abstract public function incrBy($key, $value);

    abstract public function decr($key);

    abstract public function decrBy($key, $value);

    abstract public function lPush($key, ...$value1);

    abstract public function rPush($key, ...$value1);

    abstract public function lPushx($key, $value);

    abstract public function rPushx($key, $value);

    abstract public function lPop($key);

    abstract public function rPop($key);

    abstract public function blPop($keys, $timeout);

    abstract public function brPop(array $keys, $timeout);

    abstract public function lLen($key);

    #[Deprecated(replacement: '%class%->lLen(%parametersList%)')]
    abstract public function lSize($key);

    abstract public function lIndex($key, $index);

    #[Deprecated(replacement: '%class%->lIndex(%parametersList%)')]
    abstract public function lGet($key, $index);

    abstract public function lSet($key, $index, $value);

    abstract public function lRange($key, $start, $end);

    #[Deprecated(replacement: '%class%->lRange(%parametersList%)')]
    abstract public function lGetRange($key, $start, $end);

    abstract public function lTrim($key, $start, $stop);

    #[Deprecated(replacement: '%class%->lTrim(%parametersList%)')]
    abstract public function listTrim($key, $start, $stop);

    abstract public function lRem($key, $value, $count);

    #[Deprecated(replacement: '%class%->lRem(%parametersList%)')]
    abstract public function lRemove($key, $value, $count);

    abstract public function lInsert($key, $position, $pivot, $value);

    abstract public function sAdd($key, ...$value1);

    abstract public function sRem($key, ...$member1);

    #[Deprecated(replacement: '%class%->sRem(%parametersList%)')]
    abstract public function sRemove($key, ...$member1);

    abstract public function sMove($srcKey, $dstKey, $member);

    abstract public function sIsMember($key, $value);

    #[Deprecated(replacement: '%class%->sIsMember(%parametersList%)')]
    abstract public function sContains($key, $value);

    abstract public function sCard($key);

    abstract public function sPop($key, $count = 1);

    abstract public function sRandMember($key, $count = 1);

    abstract public function sInter($key1, ...$otherKeys);

    abstract public function sInterStore($dstKey, $key1, ...$otherKeys);

    abstract public function sUnion($key1, ...$otherKeys);

    abstract public function sUnionStore($dstKey, $key1, ...$otherKeys);

    abstract public function sDiff($key1, ...$otherKeys);

    abstract public function sDiffStore($dstKey, $key1, ...$otherKeys);

    abstract public function sMembers($key);

    #[Deprecated(replacement: '%class%->sMembers(%parametersList%)')]
    abstract public function sGetMembers($key);

    abstract public function sScan($key, &$iterator, $pattern = null, $count = 0);

    abstract public function getSet($key, $value);

    abstract public function randomKey();

    abstract public function select($dbIndex);

    abstract public function move($key, $dbIndex);

    abstract public function rename($srcKey, $dstKey);

    #[Deprecated(replacement: '%class%->rename(%parametersList%)')]
    abstract public function renameKey($srcKey, $dstKey);

    abstract public function renameNx($srcKey, $dstKey);

    abstract public function expire($key, $ttl);

    abstract public function pExpire($key, $ttl);

    #[Deprecated(replacement: '%class%->expire(%parametersList%)')]
    abstract public function setTimeout($key, $ttl);

    abstract public function expireAt($key, $timestamp);

    abstract public function pExpireAt($key, $timestamp);

    abstract public function keys($pattern);

    #[Deprecated(replacement: '%class%->keys(%parametersList%)')]
    abstract public function getKeys($pattern);

    abstract public function dbSize();

    abstract public function auth($password);

    abstract public function bgrewriteaof();

    abstract public function slaveof($host = '127.0.0.1', $port = 6379);

    abstract public function slowLog(string $operation, ?int $length = null);

    abstract public function object($string = '', $key = '');

    abstract public function save();

    abstract public function bgsave();

    abstract public function lastSave();

    abstract public function wait($numSlaves, $timeout);

    abstract public function type($key);

    abstract public function append($key, $value);

    abstract public function getRange($key, $start, $end);

    #[Deprecated]
    abstract public function substr($key, $start, $end);

    abstract public function setRange($key, $offset, $value);

    abstract public function strlen($key);

    abstract public function bitpos($key, $bit, $start = 0, $end = null);

    abstract public function getBit($key, $offset);

    abstract public function setBit($key, $offset, $value);

    abstract public function bitCount($key);

    abstract public function bitOp($operation, $retKey, $key1, ...$otherKeys);

    abstract public function flushDB();

    abstract public function flushAll();

    abstract public function sort($key, $option = null);

    abstract public function info($option = null);

    abstract public function resetStat();

    abstract public function ttl($key);

    abstract public function pttl($key);

    abstract public function persist($key);

    abstract public function mset(array $array);

    #[Deprecated(replacement: '%class%->mGet(%parametersList%)')]
    abstract public function getMultiple(array $keys);

    abstract public function mGet(array $array);

    abstract public function mSetNx(array $array);

    abstract public function rPopLPush($srcKey, $dstKey);

    abstract public function bRPopLPush($srcKey, $dstKey, $timeout);

    abstract public function zAdd($key, $options, $score1, $value1 = null, $score2 = null, $value2 = null, $scoreN = null, $valueN = null);

    abstract public function zRange($key, $start, $end, $withscores = null);

    abstract public function zRem($key, $member1, ...$otherMembers);

    #[Deprecated(replacement: '%class%->zRem(%parametersList%)')]
    abstract public function zDelete($key, $member1, ...$otherMembers);

    abstract public function zRevRange($key, $start, $end, $withscore = null);

    abstract public function zRangeByScore($key, $start, $end, array $options = []);

    abstract public function zRevRangeByScore($key, $start, $end, array $options = []);

    abstract public function zRangeByLex($key, $min, $max, $offset = null, $limit = null);

    abstract public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null);

    abstract public function zCount($key, $start, $end);

    abstract public function zRemRangeByScore($key, $start, $end);

    #[Deprecated(replacement: '%class%->zRemRangeByScore(%parametersList%)')]
    abstract public function zDeleteRangeByScore($key, $start, $end);

    abstract public function zRemRangeByRank($key, $start, $end);

    #[Deprecated(replacement: '%class%->zRemRangeByRank(%parametersList%)')]
    abstract public function zDeleteRangeByRank($key, $start, $end);

    abstract public function zCard($key);

    #[Deprecated(replacement: '%class%->zCard(%parametersList%)')]
    abstract public function zSize($key);

    abstract public function zScore($key, $member);

    abstract public function zRank($key, $member);

    abstract public function zRevRank($key, $member);

    abstract public function zIncrBy($key, $value, $member);

    abstract public function zUnionStore($output, $zSetKeys, ?array $weights = null, $aggregateFunction = 'SUM');

    #[Deprecated(replacement: '%class%->zUnionStore(%parametersList%)')]
    abstract public function zUnion($Output, $ZSetKeys, ?array $Weights = null, $aggregateFunction = 'SUM');

    abstract public function zInterStore($output, $zSetKeys, ?array $weights = null, $aggregateFunction = 'SUM');

    #[Deprecated(replacement: '%class%->zInterStore(%parametersList%)')]
    abstract public function zInter($Output, $ZSetKeys, ?array $Weights = null, $aggregateFunction = 'SUM');

    abstract public function zScan($key, &$iterator, $pattern = null, $count = 0);

    abstract public function bzPopMax($key1, $key2, $timeout);

    abstract public function bzPopMin($key1, $key2, $timeout);

    abstract public function zPopMax($key, $count = 1);

    abstract public function zPopMin($key, $count = 1);

    abstract public function hSet($key, $hashKey, $value);

    abstract public function hSetNx($key, $hashKey, $value);

    abstract public function hGet($key, $hashKey);

    abstract public function hLen($key);

    abstract public function hDel($key, $hashKey1, ...$otherHashKeys);

    abstract public function hKeys($key);

    abstract public function hVals($key);

    abstract public function hGetAll($key);

    abstract public function hExists($key, $hashKey);

    abstract public function hIncrBy($key, $hashKey, $value);

    abstract public function hIncrByFloat($key, $field, $increment);

    abstract public function hMSet($key, $hashKeys);

    abstract public function hMGet($key, $hashKeys);

    abstract public function hScan($key, &$iterator, $pattern = null, $count = 0);

    abstract public function hStrLen(string $key, string $field);

    abstract public function geoAdd($key, $longitude, $latitude, $member);

    abstract public function geoHash($key, ...$member);

    abstract public function geoPos(string $key, string $member);

    abstract public function geoDist($key, $member1, $member2, $unit = null);

    abstract public function geoRadius($key, $longitude, $latitude, $radius, $unit, ?array $options = null);

    abstract public function geoRadiusByMember($key, $member, $radius, $units, ?array $options = null);

    abstract public function config($operation, $key, $value);

    abstract public function eval($script, $args = [], $numKeys = 0);

    #[Deprecated(replacement: '%class%->eval(%parametersList%)')]
    abstract public function evaluate($script, $args = [], $numKeys = 0);

    abstract public function evalSha($scriptSha, $args = [], $numKeys = 0);

    #[Deprecated(replacement: '%class%->evalSha(%parametersList%)')]
    abstract public function evaluateSha($scriptSha, $args = [], $numKeys = 0);

    abstract public function script($command, $script);

    abstract public function getLastError();

    abstract public function clearLastError();

    abstract public function client($command, $value = '');

    abstract public function _prefix($value);

    abstract public function _unserialize($value);

    abstract public function _serialize($value);

    abstract public function dump($key);

    abstract public function restore($key, $ttl, $value);

    abstract public function migrate($host, $port, $key, $db, $timeout, $copy = false, $replace = false);

    abstract public function time();

    abstract public function scan(&$iterator, $pattern = null, $count = 0);

    abstract public function pfAdd($key, array $elements);

    abstract public function pfCount($key);

    abstract public function pfMerge($destKey, array $sourceKeys);

    abstract public function rawCommand($command, $arguments);

    abstract public function getMode();

    abstract public function xAck($stream, $group, $messages);

    abstract public function xAdd($key, $id, $messages, $maxLen = 0, $isApproximate = false);

    abstract public function xClaim($key, $group, $consumer, $minIdleTime, $ids, $options = []);

    abstract public function xDel($key, $ids);

    abstract public function xGroup($operation, $key, $group, $msgId = '', $mkStream = false);

    abstract public function xInfo($operation, $stream, $group);

    abstract public function xLen($stream);

    abstract public function xPending($stream, $group, $start = null, $end = null, $count = null, $consumer = null);

    abstract public function xRange($stream, $start, $end, $count = null);

    abstract public function xRead($streams, $count = null, $block = null);

    abstract public function xReadGroup($group, $consumer, $streams, $count = null, $block = null);

    abstract public function xRevRange($stream, $end, $start, $count = null);

    abstract public function xTrim($stream, $maxLen, $isApproximate);

    abstract public function sAddArray($key, array $values);
}
