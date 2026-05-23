<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Small native analogue of rclone's fs/list.Helper.
 *
 * Backends use the upstream helper to batch recursive ListR entries before
 * calling a callback. The threshold is fixed at 100 entries in rclone.
 */
final class ListHelper
{
    public const DEFAULT_BATCH_SIZE = 100;

    /**
     * @var list<ObjectInfo>
     */
    private array $entries = [];

    /**
     * @param callable(list<ObjectInfo>): void $callback
     */
    public function __construct(
        private readonly mixed $callback,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        if (!is_callable($this->callback)) {
            throw new \InvalidArgumentException('list helper callback must be callable');
        }
        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('list helper batch size must be positive');
        }
    }

    public function add(?ObjectInfo $entry): void
    {
        if ($entry === null) {
            return;
        }

        $this->entries[] = $entry;
        $this->send($this->batchSize);
    }

    public function flush(): void
    {
        $this->send(1);
    }

    /**
     * @return list<ObjectInfo>
     */
    public function pending(): array
    {
        return $this->entries;
    }

    /**
     * Model fs/list.WithListP: collect entries supplied to a paged ListP
     * callback and return any entries gathered before a provider error.
     *
     * @param callable(callable(list<ObjectInfo>): void): void $listP
     * @return array{entries: list<ObjectInfo>, error: ?\Throwable}
     */
    public static function collectWithListP(callable $listP): array
    {
        $entries = [];

        try {
            $listP(static function (array $newEntries) use (&$entries): void {
                foreach ($newEntries as $entry) {
                    if (!$entry instanceof ObjectInfo) {
                        throw new \InvalidArgumentException('ListP callback entries must be ObjectInfo instances');
                    }
                    $entries[] = $entry;
                }
            });

            return ['entries' => $entries, 'error' => null];
        } catch (\Throwable $throwable) {
            return ['entries' => $entries, 'error' => $throwable];
        }
    }

    private function send(int $minEntries): void
    {
        if (count($this->entries) < $minEntries) {
            return;
        }

        $entries = $this->entries;
        try {
            ($this->callback)($entries);
        } finally {
            $this->entries = [];
        }
    }
}
