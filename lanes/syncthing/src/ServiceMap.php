<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ServiceMap
{
    public const ERR_SERVICE_NOT_FOUND = 'service not found';

    /**
     * @var array<string|int, mixed>
     */
    private array $services = [];

    /**
     * @var array<string|int, bool>
     */
    private array $running = [];

    public function __construct(
        private readonly mixed $start = null,
        private readonly mixed $stop = null,
    ) {
        if ($this->start !== null && !is_callable($this->start)) {
            throw new \InvalidArgumentException('ServiceMap start hook must be callable');
        }
        if ($this->stop !== null && !is_callable($this->stop)) {
            throw new \InvalidArgumentException('ServiceMap stop hook must be callable');
        }
    }

    public function add(string|int $key, mixed $service): void
    {
        if ($this->isRunning($key)) {
            $this->stop($key);
        }

        $this->services[$key] = $service;
        $this->running[$key] = true;
        $this->startService($key, $service);
    }

    public function get(string|int $key): mixed
    {
        return $this->services[$key] ?? null;
    }

    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->services);
    }

    public function isRunning(string|int $key): bool
    {
        return ($this->running[$key] ?? false) === true && $this->has($key);
    }

    public function stop(string|int $key): void
    {
        if (!$this->isRunning($key)) {
            return;
        }

        $service = $this->services[$key];
        $this->running[$key] = false;
        $this->stopService($key, $service);
    }

    public function stopAndWait(string|int $key): ?\Throwable
    {
        if (!$this->has($key)) {
            return self::serviceNotFound();
        }

        if (!$this->isRunning($key)) {
            return null;
        }

        try {
            $this->stop($key);
        } catch (\Throwable $throwable) {
            return $throwable;
        }

        return null;
    }

    public function remove(string|int $key): bool
    {
        $found = $this->has($key);
        if ($this->isRunning($key)) {
            $this->stop($key);
        }

        unset($this->services[$key], $this->running[$key]);

        return $found;
    }

    public function removeAndWait(string|int $key): ?\Throwable
    {
        $error = $this->stopAndWait($key);
        unset($this->services[$key], $this->running[$key]);

        return $error;
    }

    /**
     * @param callable(string|int, mixed):(?\Throwable) $callback
     */
    public function each(callable $callback): ?\Throwable
    {
        foreach ($this->services as $key => $service) {
            $error = $callback($key, $service);
            if ($error instanceof \Throwable) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @return list<string|int>
     */
    public function keys(): array
    {
        return array_values(array_keys($this->services));
    }

    /**
     * @return list<string|int>
     */
    public function runningKeys(): array
    {
        $keys = [];
        foreach ($this->running as $key => $running) {
            if ($running && $this->has($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function count(): int
    {
        return count($this->services);
    }

    public static function serviceNotFound(): \RuntimeException
    {
        return new \RuntimeException(self::ERR_SERVICE_NOT_FOUND);
    }

    private function startService(string|int $key, mixed $service): void
    {
        if ($this->start === null) {
            return;
        }

        ($this->start)($key, $service);
    }

    private function stopService(string|int $key, mixed $service): void
    {
        if ($this->stop === null) {
            return;
        }

        $error = ($this->stop)($key, $service);
        if ($error instanceof \Throwable) {
            throw $error;
        }
    }
}
