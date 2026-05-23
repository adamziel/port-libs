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
    ) {
        foreach ($this->availableBlockIndexes as $index) {
            if (!is_int($index) || $index < 0) {
                throw new \InvalidArgumentException('Available block indexes must be non-negative integers');
            }
        }
        if ($this->finalized && $this->dbUpdateType === '') {
            throw new \InvalidArgumentException('Finalized pull results must include a database update type');
        }
    }

    /**
     * @return array{closed:bool, finalized:bool, error:?string, tempName:string, finalName:string, availableBlockIndexes:list<int>, dbUpdateType:string}
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
        ];
    }
}
