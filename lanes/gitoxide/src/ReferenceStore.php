<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceStore
{
    private readonly LooseReferenceStore $loose;
    private readonly ?string $namespacePrefix;

    public function __construct(
        private readonly string $gitDirectory,
        private readonly ?PackedReferences $packed = null,
        ?string $namespace = null,
    ) {
        $this->loose = new LooseReferenceStore($gitDirectory);
        $this->namespacePrefix = $namespace === null ? null : ReferenceName::expandNamespace($namespace);
    }

    public static function at(string $gitDirectory, string $algorithm = 'sha1', ?string $namespace = null): self
    {
        $packedPath = rtrim($gitDirectory, '/\\') . '/packed-refs';
        $packed = is_file($packedPath) ? PackedReferences::open($packedPath, $algorithm) : null;

        return new self($gitDirectory, $packed, $namespace);
    }

    public function withNamespace(string $namespace): self
    {
        return new self($this->gitDirectory, $this->packed, $namespace);
    }

    public function looseStore(): LooseReferenceStore
    {
        return $this->loose;
    }

    public function tryFind(string $name, string $algorithm = 'sha1'): ?ResolvedReference
    {
        foreach (self::lookupCandidates($name) as [$candidate, $allowPacked]) {
            $lookupName = $this->namespacePrefix === null ? $candidate : $this->namespacePrefix . $candidate;
            $loose = $this->loose->tryRead($lookupName, $algorithm);
            if ($loose !== null) {
                return $this->storeRelativeReference(ResolvedReference::fromLoose($loose));
            }

            if ($allowPacked && $this->packed !== null) {
                $packed = $this->packed->tryFind($lookupName);
                if ($packed !== null) {
                    return $this->storeRelativeReference(ResolvedReference::fromPacked($packed));
                }
            }
        }

        return null;
    }

    public function find(string $name, string $algorithm = 'sha1'): ResolvedReference
    {
        $reference = $this->tryFind($name, $algorithm);
        if ($reference === null) {
            throw new \RuntimeException("Reference not found: {$name}");
        }

        return $reference;
    }

    /**
     * @return list<ResolvedReference>
     */
    public function all(string $algorithm = 'sha1'): array
    {
        return $this->prefixed('refs/', $algorithm);
    }

    /**
     * @return list<ResolvedReference>
     */
    public function prefixed(string $prefix, string $algorithm = 'sha1'): array
    {
        $lookupPrefix = $this->namespacePrefix === null ? $prefix : $this->namespacePrefix . $prefix;
        ReferenceName::assertValidPartial(rtrim($lookupPrefix, '/'));

        $byName = [];
        foreach ($this->loose->prefixed($lookupPrefix, $algorithm) as $reference) {
            $resolved = $this->storeRelativeReference(ResolvedReference::fromLoose($reference));
            $byName[$resolved->name] = $resolved;
        }

        if ($this->packed !== null) {
            foreach ($this->packed->prefixed($lookupPrefix) as $reference) {
                $resolved = $this->storeRelativeReference(ResolvedReference::fromPacked($reference));
                $byName[$resolved->name] ??= $resolved;
            }
        }

        ksort($byName, SORT_STRING);

        return array_values($byName);
    }

    /**
     * @return list<ResolvedReference>
     */
    public function looseAll(string $algorithm = 'sha1'): array
    {
        return $this->loosePrefixed('refs/', $algorithm);
    }

    /**
     * @return list<ResolvedReference>
     */
    public function loosePrefixed(string $prefix, string $algorithm = 'sha1'): array
    {
        $lookupPrefix = $this->namespacePrefix === null ? $prefix : $this->namespacePrefix . $prefix;
        ReferenceName::assertValidPartial(rtrim($lookupPrefix, '/'));

        return array_map(
            fn (LooseReference $reference): ResolvedReference => $this->storeRelativeReference(
                ResolvedReference::fromLoose($reference)
            ),
            $this->loose->prefixed($lookupPrefix, $algorithm),
        );
    }

    /**
     * @return list<array{0:string,1:bool}>
     */
    private static function lookupCandidates(string $name): array
    {
        if (
            str_starts_with($name, 'refs/')
            || str_starts_with($name, 'main-worktree/')
            || str_starts_with($name, 'worktrees/')
        ) {
            ReferenceName::assertValid($name);
            return [[$name, true]];
        }

        ReferenceName::assertValidPartial($name);

        $candidates = [];
        if (ReferenceName::isPseudoRef($name)) {
            $candidates[] = [$name, false];
        }

        foreach (['', 'tags', 'heads', 'remotes'] as $inbetween) {
            $candidate = 'refs/' . ($inbetween === '' ? '' : $inbetween . '/') . $name;
            ReferenceName::assertValid($candidate);
            $candidates[] = [$candidate, true];
        }

        if ($name !== 'HEAD') {
            $remoteHead = 'refs/remotes/' . $name . '/HEAD';
            ReferenceName::assertValid($remoteHead);
            $candidates[] = [$remoteHead, false];
        }

        return $candidates;
    }

    private function storeRelativeReference(ResolvedReference $reference): ResolvedReference
    {
        if ($this->namespacePrefix === null) {
            return $reference;
        }

        if (!str_starts_with($reference->name, $this->namespacePrefix)) {
            return $reference;
        }

        $target = $reference->target;
        if ($target->isSymbolic() && str_starts_with($target->value, $this->namespacePrefix)) {
            $target = ReferenceTarget::symbolic(substr($target->value, strlen($this->namespacePrefix)));
        }

        return $reference->withNameAndTarget(substr($reference->name, strlen($this->namespacePrefix)), $target);
    }
}
