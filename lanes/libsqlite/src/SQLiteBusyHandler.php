<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBusyHandler
{
    /**
     * @param list<int> $delays
     */
    private function __construct(
        private readonly int $timeoutMilliseconds,
        private readonly array $delays
    ) {
        if ($timeoutMilliseconds < 0) {
            throw new \InvalidArgumentException('SQLite busy timeout cannot be negative');
        }

        foreach ($delays as $delay) {
            if ($delay < 0) {
                throw new \InvalidArgumentException('SQLite busy retry delay cannot be negative');
            }
        }
    }

    public static function timeout(int $milliseconds): self
    {
        return new self($milliseconds, self::defaultDelays($milliseconds));
    }

    /**
     * @param list<int> $delays
     */
    public static function withDelays(int $timeoutMilliseconds, array $delays): self
    {
        return new self($timeoutMilliseconds, array_values($delays));
    }

    /**
     * @return array{timeout_ms:int,retry_count:int,total_sleep_ms:int,will_retry:bool,will_timeout:bool,attempts:list<array{attempt:int,prior_elapsed_ms:int,sleep_ms:int,elapsed_after_sleep_ms:int,continue:bool}>}
     */
    public function plan(?callable $callback = null): array
    {
        $elapsed = 0;
        $attempts = [];

        foreach ($this->delays as $index => $delay) {
            if ($elapsed >= $this->timeoutMilliseconds) {
                break;
            }

            $sleep = min($delay, $this->timeoutMilliseconds - $elapsed);
            if ($sleep <= 0) {
                break;
            }

            $shouldContinue = $callback === null ? true : (bool) $callback($index, $elapsed, $sleep);
            $attempts[] = [
                'attempt' => $index,
                'prior_elapsed_ms' => $elapsed,
                'sleep_ms' => $sleep,
                'elapsed_after_sleep_ms' => $elapsed + $sleep,
                'continue' => $shouldContinue,
            ];

            if (!$shouldContinue) {
                break;
            }

            $elapsed += $sleep;
        }

        return [
            'timeout_ms' => $this->timeoutMilliseconds,
            'retry_count' => count(array_filter($attempts, static fn (array $attempt): bool => $attempt['continue'])),
            'total_sleep_ms' => $elapsed,
            'will_retry' => $attempts !== [],
            'will_timeout' => $elapsed >= $this->timeoutMilliseconds && $this->timeoutMilliseconds > 0,
            'attempts' => $attempts,
        ];
    }

    /**
     * @return array{status:string,busy:bool,operation:string,timeout_ms:int,total_sleep_ms:int,retry_count:int,attempts:list<array{attempt:int,prior_elapsed_ms:int,sleep_ms:int,elapsed_after_sleep_ms:int,continue:bool}>}
     */
    public function lockedOperationPlan(string $operation, bool $lockAvailable, ?callable $callback = null): array
    {
        if ($operation === '') {
            throw new \InvalidArgumentException('SQLite busy operation name cannot be empty');
        }

        if ($lockAvailable) {
            return [
                'status' => 'ready',
                'busy' => false,
                'operation' => $operation,
                'timeout_ms' => $this->timeoutMilliseconds,
                'total_sleep_ms' => 0,
                'retry_count' => 0,
                'attempts' => [],
            ];
        }

        $plan = $this->plan($callback);

        return [
            'status' => $plan['will_timeout'] ? 'busy-timeout' : 'busy-cancelled',
            'busy' => true,
            'operation' => $operation,
            'timeout_ms' => $plan['timeout_ms'],
            'total_sleep_ms' => $plan['total_sleep_ms'],
            'retry_count' => $plan['retry_count'],
            'attempts' => $plan['attempts'],
        ];
    }

    /**
     * @return list<int>
     */
    private static function defaultDelays(int $timeoutMilliseconds): array
    {
        if ($timeoutMilliseconds <= 0) {
            return [];
        }

        $delays = [];
        $elapsed = 0;
        $delay = 1;
        while ($elapsed < $timeoutMilliseconds) {
            $sleep = min($delay, $timeoutMilliseconds - $elapsed);
            $delays[] = $sleep;
            $elapsed += $sleep;
            $delay = min(100, $delay * 2);
        }

        return $delays;
    }
}
