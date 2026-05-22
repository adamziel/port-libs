<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceStore
{
    public const PREVIOUS_ANY = 'any';
    public const PREVIOUS_MUST_EXIST = 'must-exist';
    public const PREVIOUS_MUST_NOT_EXIST = 'must-not-exist';
    public const PREVIOUS_MUST_EXIST_AND_MATCH = 'must-exist-and-match';
    public const PREVIOUS_EXISTING_MUST_MATCH = 'existing-must-match';

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

    public function update(
        string $name,
        ReferenceTarget $target,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $algorithm = 'sha1',
    ): ResolvedReference {
        $physicalName = $this->dereferenceName($this->physicalName($name), $deref, $algorithm);
        $physicalTarget = $this->physicalTarget($target);
        $existing = $this->tryFindPhysical($physicalName, $algorithm);

        $this->assertPreviousValueAllowsUpdate($physicalName, $physicalTarget, $existing, $previous, $expectedTarget);

        if ($existing !== null && self::targetsEqual($existing->target, $physicalTarget)) {
            return $this->storeRelativeReference($existing);
        }

        $reference = new LooseReference($physicalName, $physicalTarget);
        $this->loose->write($reference);

        return $this->storeRelativeReference(ResolvedReference::fromLoose($reference));
    }

    public function deleteReference(
        string $name,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $algorithm = 'sha1',
    ): ?ResolvedReference {
        if ($previous === self::PREVIOUS_MUST_NOT_EXIST) {
            throw new \InvalidArgumentException('Must-not-exist constraints are invalid for reference deletion');
        }

        $physicalName = $this->dereferenceName($this->physicalName($name), $deref, $algorithm);
        $existing = $this->tryFindPhysical($physicalName, $algorithm);

        $this->assertPreviousValueAllowsDeletion($physicalName, $existing, $previous, $expectedTarget);

        if ($existing === null) {
            return null;
        }

        if ($existing->source !== 'loose') {
            throw new \RuntimeException('Packed reference transaction rewrites are not implemented');
        }

        $this->loose->delete($physicalName);

        return $this->storeRelativeReference($existing);
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

    private function physicalName(string $name): string
    {
        ReferenceName::assertValid($name);

        return $this->namespacePrefix === null ? $name : $this->namespacePrefix . $name;
    }

    private function physicalTarget(ReferenceTarget $target): ReferenceTarget
    {
        if ($this->namespacePrefix === null || !$target->isSymbolic()) {
            return $target;
        }

        if (str_starts_with($target->value, $this->namespacePrefix)) {
            return $target;
        }

        return ReferenceTarget::symbolic($this->namespacePrefix . $target->value);
    }

    private function dereferenceName(string $physicalName, bool $deref, string $algorithm): string
    {
        if (!$deref) {
            return $physicalName;
        }

        $seen = [];
        $name = $physicalName;
        while (true) {
            if (isset($seen[$name])) {
                throw new \RuntimeException("Symbolic reference cycle while resolving {$physicalName}");
            }
            $seen[$name] = true;

            $reference = $this->loose->tryRead($name, $algorithm);
            if ($reference === null || !$reference->target->isSymbolic()) {
                return $name;
            }

            $name = $reference->target->value;
        }
    }

    private function tryFindPhysical(string $physicalName, string $algorithm): ?ResolvedReference
    {
        $loose = $this->loose->tryRead($physicalName, $algorithm);
        if ($loose !== null) {
            return ResolvedReference::fromLoose($loose);
        }

        if ($this->packed === null) {
            return null;
        }

        $packed = $this->packed->tryFind($physicalName);
        return $packed === null ? null : ResolvedReference::fromPacked($packed);
    }

    private function assertPreviousValueAllowsUpdate(
        string $physicalName,
        ReferenceTarget $new,
        ?ResolvedReference $existing,
        string $previous,
        ?ReferenceTarget $expectedTarget,
    ): void {
        match ($previous) {
            self::PREVIOUS_ANY => null,
            self::PREVIOUS_MUST_EXIST => $existing === null
                ? throw new \RuntimeException("Reference must exist before update: {$physicalName}")
                : null,
            self::PREVIOUS_MUST_NOT_EXIST => $existing !== null && !self::targetsEqual($existing->target, $new)
                ? throw new \RuntimeException("Reference must not exist before update: {$physicalName}")
                : null,
            self::PREVIOUS_MUST_EXIST_AND_MATCH => $this->assertExpectedTargetMatches(
                $physicalName,
                $existing,
                $expectedTarget,
                true,
            ),
            self::PREVIOUS_EXISTING_MUST_MATCH => $this->assertExpectedTargetMatches(
                $physicalName,
                $existing,
                $expectedTarget,
                false,
            ),
            default => throw new \InvalidArgumentException("Unknown reference previous-value constraint: {$previous}"),
        };
    }

    private function assertPreviousValueAllowsDeletion(
        string $physicalName,
        ?ResolvedReference $existing,
        string $previous,
        ?ReferenceTarget $expectedTarget,
    ): void {
        match ($previous) {
            self::PREVIOUS_ANY => null,
            self::PREVIOUS_MUST_EXIST => $existing === null
                ? throw new \RuntimeException("Reference must exist before deletion: {$physicalName}")
                : null,
            self::PREVIOUS_MUST_EXIST_AND_MATCH => $this->assertExpectedTargetMatches(
                $physicalName,
                $existing,
                $expectedTarget,
                true,
            ),
            self::PREVIOUS_EXISTING_MUST_MATCH => $this->assertExpectedTargetMatches(
                $physicalName,
                $existing,
                $expectedTarget,
                false,
            ),
            default => throw new \InvalidArgumentException("Unknown reference previous-value constraint: {$previous}"),
        };
    }

    private function assertExpectedTargetMatches(
        string $physicalName,
        ?ResolvedReference $existing,
        ?ReferenceTarget $expectedTarget,
        bool $mustExist,
    ): void {
        if ($expectedTarget === null) {
            throw new \InvalidArgumentException('Expected-target constraints require an expected target');
        }

        $expected = $this->physicalTarget($expectedTarget);
        if ($existing === null) {
            if ($mustExist) {
                throw new \RuntimeException("Reference must exist before transaction: {$physicalName}");
            }

            return;
        }

        if (!self::targetsEqual($existing->target, $expected)) {
            throw new \RuntimeException("Reference is out of date: {$physicalName}");
        }
    }

    private static function targetsEqual(ReferenceTarget $left, ReferenceTarget $right): bool
    {
        return $left->kind === $right->kind && $left->value === $right->value;
    }
}
