<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceTransactionEdit
{
    public const CHANGE_UPDATE = 'update';
    public const CHANGE_DELETE = 'delete';

    public const REFLOG_AND_REFERENCE = 'and-reference';
    public const REFLOG_ONLY = 'only';

    public function __construct(
        public readonly string $change,
        public readonly string $name,
        public readonly ?ReferenceTarget $previousTarget,
        public readonly ?ReferenceTarget $newTarget,
        public readonly string $reflogMode,
        public readonly bool $updatesReference,
    ) {
        if (!in_array($change, [self::CHANGE_UPDATE, self::CHANGE_DELETE], true)) {
            throw new \InvalidArgumentException("Unknown reference transaction edit change: {$change}");
        }
        if (!in_array($reflogMode, [self::REFLOG_AND_REFERENCE, self::REFLOG_ONLY], true)) {
            throw new \InvalidArgumentException("Unknown reference transaction reflog mode: {$reflogMode}");
        }
    }

    public static function update(
        string $name,
        ?ReferenceTarget $previousTarget,
        ReferenceTarget $newTarget,
        string $reflogMode,
        bool $updatesReference,
    ): self {
        return new self(
            self::CHANGE_UPDATE,
            $name,
            $previousTarget,
            $newTarget,
            $reflogMode,
            $updatesReference,
        );
    }

    public static function delete(
        string $name,
        ?ReferenceTarget $previousTarget,
        string $reflogMode,
        bool $updatesReference,
    ): self {
        return new self(
            self::CHANGE_DELETE,
            $name,
            $previousTarget,
            null,
            $reflogMode,
            $updatesReference,
        );
    }
}
