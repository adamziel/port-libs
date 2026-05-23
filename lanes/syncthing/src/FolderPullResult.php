<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderPullResult
{
    /**
     * @param list<array{path:string, error:string}> $errors
     * @param array{type:string, data:array{folder:string, errors:list<array{path:string, error:string}>}}|null $folderErrorsEvent
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $changed,
        public readonly int $promotedPullErrors,
        public readonly array $errors,
        public readonly ?array $folderErrorsEvent = null,
    ) {
        if ($this->changed < 0 || $this->promotedPullErrors < 0) {
            throw new \InvalidArgumentException('Folder pull result counters must not be negative');
        }
        if (($this->folderErrorsEvent === null) !== ($this->promotedPullErrors === 0)) {
            throw new \InvalidArgumentException('FolderErrors event presence must match promoted pull errors');
        }
        foreach ($this->errors as $error) {
            if (($error['path'] ?? '') === '' || ($error['error'] ?? '') === '') {
                throw new \InvalidArgumentException('Folder pull errors require path and error fields');
            }
        }
    }
}
