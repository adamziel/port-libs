<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderCompletion
{
    public const REMOTE_UNKNOWN = 'unknown';
    public const REMOTE_NOT_SHARING = 'notSharing';
    public const REMOTE_PAUSED = 'paused';
    public const REMOTE_VALID = 'valid';

    /**
     * @var array<string, true>
     */
    private const REMOTE_STATES = [
        self::REMOTE_UNKNOWN => true,
        self::REMOTE_NOT_SHARING => true,
        self::REMOTE_PAUSED => true,
        self::REMOTE_VALID => true,
    ];

    private function __construct(
        public readonly float $completionPct,
        public readonly int $globalBytes,
        public readonly int $needBytes,
        public readonly int $globalItems,
        public readonly int $needItems,
        public readonly int $needDeletes,
        public readonly int $sequence,
        public readonly string $remoteState,
    ) {
        foreach ([
            'globalBytes' => $this->globalBytes,
            'needBytes' => $this->needBytes,
            'globalItems' => $this->globalItems,
            'needItems' => $this->needItems,
            'needDeletes' => $this->needDeletes,
            'sequence' => $this->sequence,
        ] as $label => $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Folder completion ' . $label . ' must not be negative');
            }
        }
        self::assertRemoteState($this->remoteState);
    }

    public static function fromCounts(
        FolderCounts $global,
        FolderCounts $need,
        int $sequence = 0,
        string $remoteState = self::REMOTE_VALID,
        int $downloadedBytes = 0,
    ): self {
        if ($sequence < 0) {
            throw new \InvalidArgumentException('Folder completion sequence must not be negative');
        }
        self::assertRemoteState($remoteState);

        $adjustedNeed = $need->subtractDownloadedBytes($downloadedBytes);

        return self::fromTotals(
            globalBytes: $global->bytes,
            needBytes: $adjustedNeed->bytes,
            globalItems: $global->items(),
            needItems: $adjustedNeed->items(),
            needDeletes: $adjustedNeed->deleted,
            sequence: $sequence,
            remoteState: $remoteState,
        );
    }

    public function add(self $other): self
    {
        return self::fromTotals(
            globalBytes: $this->globalBytes + $other->globalBytes,
            needBytes: $this->needBytes + $other->needBytes,
            globalItems: $this->globalItems + $other->globalItems,
            needItems: $this->needItems + $other->needItems,
            needDeletes: $this->needDeletes + $other->needDeletes,
            sequence: $this->sequence,
            remoteState: $this->remoteState,
        );
    }

    /**
     * @return array{
     *     completion: float,
     *     globalBytes: int,
     *     needBytes: int,
     *     globalItems: int,
     *     needItems: int,
     *     needDeletes: int,
     *     sequence: int,
     *     remoteState: string
     * }
     */
    public function map(): array
    {
        return [
            'completion' => $this->completionPct,
            'globalBytes' => $this->globalBytes,
            'needBytes' => $this->needBytes,
            'globalItems' => $this->globalItems,
            'needItems' => $this->needItems,
            'needDeletes' => $this->needDeletes,
            'sequence' => $this->sequence,
            'remoteState' => $this->remoteState,
        ];
    }

    private static function fromTotals(
        int $globalBytes,
        int $needBytes,
        int $globalItems,
        int $needItems,
        int $needDeletes,
        int $sequence,
        string $remoteState,
    ): self {
        if ($globalBytes === 0) {
            $completion = 100.0;
        } else {
            $completion = 100.0 * (1.0 - ($needBytes / $globalBytes));
        }

        if ($needBytes === 0 && $needDeletes > 0) {
            $completion = 95.0;
        }

        return new self(
            completionPct: $completion,
            globalBytes: $globalBytes,
            needBytes: $needBytes,
            globalItems: $globalItems,
            needItems: $needItems,
            needDeletes: $needDeletes,
            sequence: $sequence,
            remoteState: $remoteState,
        );
    }

    private static function assertRemoteState(string $remoteState): void
    {
        if (!isset(self::REMOTE_STATES[$remoteState])) {
            throw new \InvalidArgumentException('Unknown remote folder state');
        }
    }
}
