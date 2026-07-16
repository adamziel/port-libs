<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ResolvedReference
{
    private function __construct(
        public readonly string $name,
        public readonly ReferenceTarget $target,
        public readonly ?string $peeledObjectId,
        public readonly string $source,
    ) {
    }

    public static function fromLoose(LooseReference $reference): self
    {
        return new self($reference->name, $reference->target, null, 'loose');
    }

    public static function fromPacked(PackedReference $reference): self
    {
        return new self($reference->name, $reference->target, $reference->peeledObjectId, 'packed');
    }

    public function withNameAndTarget(string $name, ?ReferenceTarget $target = null): self
    {
        return new self($name, $target ?? $this->target, $this->peeledObjectId, $this->source);
    }

    public function kind(): string
    {
        return $this->target->kind;
    }

    public function targetObjectId(): ?string
    {
        return $this->target->isObject() ? $this->target->value : null;
    }

    public function objectId(): ?string
    {
        return $this->peeledObjectId ?? $this->targetObjectId();
    }
}
