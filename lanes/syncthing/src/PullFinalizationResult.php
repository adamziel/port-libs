<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullFinalizationResult
{
    public const DB_UPDATE_HANDLE_FILE = 'dbUpdateHandleFile';

    /**
     * @param list<int> $availableBlockIndexes
     */
    public function __construct(
        public readonly bool $closed,
        public readonly bool $finalized,
        public readonly ?string $error,
        public readonly string $tempName,
        public readonly string $finalName,
        public readonly array $availableBlockIndexes = [],
        public readonly string $dbUpdateType = '',
        public readonly int $finalSize = 0,
        public readonly int $encryptionTrailerSize = 0,
        public readonly ?string $conflictName = null,
        public readonly array $scanNames = [],
        public readonly ?string $archivedName = null,
    ) {
        foreach ($this->availableBlockIndexes as $index) {
            if (!is_int($index) || $index < 0) {
                throw new \InvalidArgumentException('Available block indexes must be non-negative integers');
            }
        }
        foreach ($this->scanNames as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Scan names must be non-empty strings');
            }
        }
        if ($this->finalSize < 0 || $this->encryptionTrailerSize < 0) {
            throw new \InvalidArgumentException('Finalized sizes must not be negative');
        }
        if ($this->archivedName !== null && $this->archivedName === '') {
            throw new \InvalidArgumentException('Archived name must be null or non-empty');
        }
        if ($this->finalized && $this->dbUpdateType === '') {
            throw new \InvalidArgumentException('Finalized pull results must include a database update type');
        }
    }

    /**
     * @return array{closed:bool, finalized:bool, error:?string, tempName:string, finalName:string, availableBlockIndexes:list<int>, dbUpdateType:string, finalSize:int, encryptionTrailerSize:int, conflictName:?string, scanNames:list<string>, archivedName:?string}
     */
    public function toArray(): array
    {
        return [
            'closed' => $this->closed,
            'finalized' => $this->finalized,
            'error' => $this->error,
            'tempName' => $this->tempName,
            'finalName' => $this->finalName,
            'availableBlockIndexes' => $this->availableBlockIndexes,
            'dbUpdateType' => $this->dbUpdateType,
            'finalSize' => $this->finalSize,
            'encryptionTrailerSize' => $this->encryptionTrailerSize,
            'conflictName' => $this->conflictName,
            'scanNames' => $this->scanNames,
            'archivedName' => $this->archivedName,
        ];
    }
}
