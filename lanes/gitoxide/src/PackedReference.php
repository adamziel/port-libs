<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackedReference
{
    public function __construct(
        public readonly string $name,
        public readonly ReferenceTarget $target,
        public readonly ?string $peeledObjectId = null,
    ) {
        ReferenceName::assertValid($name);
        if (!$target->isObject()) {
            throw new \InvalidArgumentException('Packed references must point directly at object ids');
        }
        if ($peeledObjectId !== null) {
            ReferenceTarget::assertValidObjectId($peeledObjectId, strlen($peeledObjectId) === 64 ? 'sha256' : 'sha1');
        }
    }

    public function targetObjectId(): string
    {
        return $this->target->value;
    }

    public function objectId(): string
    {
        return $this->peeledObjectId ?? $this->target->value;
    }
}
