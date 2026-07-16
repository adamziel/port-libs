<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ScannerSubWalkDiagnostic
{
    public const STATUS_ALLOWED = 'allowed';
    public const STATUS_MISSING = 'missing';
    public const STATUS_MISSING_PARENT = 'missing-parent';
    public const STATUS_NOT_A_DIRECTORY = 'not-a-directory';
    public const STATUS_TRAVERSES_SYMLINK = 'traverses-symlink';

    public function __construct(
        public readonly string $sub,
        public readonly string $parent,
        public readonly string $status,
        public readonly ?string $path = null,
        public readonly ?string $message = null,
    ) {
        if (!in_array($this->status, [
            self::STATUS_ALLOWED,
            self::STATUS_MISSING,
            self::STATUS_MISSING_PARENT,
            self::STATUS_NOT_A_DIRECTORY,
            self::STATUS_TRAVERSES_SYMLINK,
        ], true)) {
            throw new \InvalidArgumentException('Unknown scanner sub-walk diagnostic status');
        }
    }

    public function shouldWalk(): bool
    {
        return $this->status === self::STATUS_ALLOWED;
    }

    public function isTraversalBlocked(): bool
    {
        return $this->status === self::STATUS_TRAVERSES_SYMLINK
            || $this->status === self::STATUS_NOT_A_DIRECTORY;
    }

    /**
     * @return array{sub:string,parent:string,status:string,path:?string,message:?string,shouldWalk:bool}
     */
    public function toArray(): array
    {
        return [
            'sub' => $this->sub,
            'parent' => $this->parent,
            'status' => $this->status,
            'path' => $this->path,
            'message' => $this->message,
            'shouldWalk' => $this->shouldWalk(),
        ];
    }
}
