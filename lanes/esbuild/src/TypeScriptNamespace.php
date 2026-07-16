<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class TypeScriptNamespace
{
    /**
     * @param list<TypeScriptNamespaceMember> $members
     */
    public function __construct(
        public readonly string $name,
        public readonly string $qualifiedName,
        public readonly ?string $parent,
        public readonly bool $exported,
        public readonly bool $declared,
        public readonly int $offset,
        public readonly array $members = [],
    ) {
    }

    /**
     * @return list<TypeScriptNamespaceMember>
     */
    public function runtimeExportedMembers(): array
    {
        return array_values(array_filter(
            $this->members,
            static fn (TypeScriptNamespaceMember $member): bool => $member->exported && !$member->declared && !$member->typeOnly
        ));
    }
}
