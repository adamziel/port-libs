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

    public const PACKED_DELETIONS_ONLY = 'deletions-only';
    public const PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES = 'deletions-and-non-symbolic-updates';
    public const PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE = 'deletions-and-non-symbolic-updates-remove-loose-source-reference';

    private readonly LooseReferenceStore $loose;
    private ?PackedReferences $packed;
    private readonly ?string $namespacePrefix;

    public function __construct(
        private readonly string $gitDirectory,
        ?PackedReferences $packed = null,
        ?string $namespace = null,
    ) {
        $this->loose = new LooseReferenceStore($gitDirectory);
        $this->packed = $packed;
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
        ?CommitSignature $committer = null,
        string $reflogMessage = '',
        bool $forceCreateReflog = false,
        string $packedRefsMode = self::PACKED_DELETIONS_ONLY,
        ?ObjectDatabase $objectDatabase = null,
    ): ResolvedReference {
        return $this->updateWithReport(
            $name,
            $target,
            $previous,
            $expectedTarget,
            $deref,
            $algorithm,
            $committer,
            $reflogMessage,
            $forceCreateReflog,
            $packedRefsMode,
            $objectDatabase,
        )->reference;
    }

    public function updateWithReport(
        string $name,
        ReferenceTarget $target,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $algorithm = 'sha1',
        ?CommitSignature $committer = null,
        string $reflogMessage = '',
        bool $forceCreateReflog = false,
        string $packedRefsMode = self::PACKED_DELETIONS_ONLY,
        ?ObjectDatabase $objectDatabase = null,
    ): ReferenceUpdateResult {
        self::assertPackedRefsMode($packedRefsMode);

        [$physicalName, $derefParents] = $this->dereferenceUpdateSplit($this->physicalName($name), $deref, $algorithm);
        $physicalTarget = $this->physicalTarget($target);
        $existing = $this->tryFindPhysical($physicalName, $algorithm);

        $this->assertPreviousValueAllowsUpdate($physicalName, $physicalTarget, $existing, $previous, $expectedTarget);
        $edits = $this->updateReport($derefParents, $existing?->target, $physicalTarget, $physicalName);

        if ($existing !== null && self::targetsEqual($existing->target, $physicalTarget)) {
            if (
                $packedRefsMode !== self::PACKED_DELETIONS_ONLY
                && $physicalTarget->isObject()
                && !$this->packedTargetEquals($physicalName, $physicalTarget, $algorithm)
            ) {
                $packedReference = $this->packedReferenceForUpdate(
                    $physicalName,
                    $physicalTarget,
                    $algorithm,
                    $objectDatabase,
                );
                $this->rewritePackedReferences(
                    [$physicalName => $packedReference],
                    [],
                    $algorithm,
                );

                if ($packedRefsMode === self::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE) {
                    $this->loose->delete($physicalName);

                    return new ReferenceUpdateResult(
                        $this->storeRelativeReference(ResolvedReference::fromPacked($packedReference)),
                        $edits,
                    );
                }
            }

            return new ReferenceUpdateResult($this->storeRelativeReference($existing), $edits);
        }

        $this->appendDereferenceParentReflogs(
            $derefParents,
            $existing?->target,
            $physicalTarget,
            $committer,
            $reflogMessage,
            $forceCreateReflog,
            $algorithm,
        );
        $this->maybeAppendReflog(
            $physicalName,
            $existing?->target,
            $physicalTarget,
            $committer,
            $reflogMessage,
            $forceCreateReflog,
            $algorithm,
        );

        if ($packedRefsMode !== self::PACKED_DELETIONS_ONLY && $physicalTarget->isObject()) {
            $packedReference = $this->packedReferenceForUpdate(
                $physicalName,
                $physicalTarget,
                $algorithm,
                $objectDatabase,
            );
            $this->rewritePackedReferences(
                [$physicalName => $packedReference],
                [],
                $algorithm,
            );

            if ($packedRefsMode === self::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE) {
                $this->loose->delete($physicalName);

                return new ReferenceUpdateResult(
                    $this->storeRelativeReference(ResolvedReference::fromPacked($packedReference)),
                    $edits,
                );
            }
        }

        $reference = new LooseReference($physicalName, $physicalTarget);
        $this->loose->write($reference);

        return new ReferenceUpdateResult($this->storeRelativeReference(ResolvedReference::fromLoose($reference)), $edits);
    }

    public function deleteReference(
        string $name,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $algorithm = 'sha1',
        bool $deleteReflog = true,
    ): ?ResolvedReference {
        if ($previous === self::PREVIOUS_MUST_NOT_EXIST) {
            throw new \InvalidArgumentException('Must-not-exist constraints are invalid for reference deletion');
        }

        if (!$deleteReflog) {
            $physicalName = $this->dereferenceName($this->physicalName($name), $deref, $algorithm);
            $existing = $this->tryFindPhysical($physicalName, $algorithm);

            $this->assertPreviousValueAllowsDeletion($physicalName, $existing, $previous, $expectedTarget);

            if ($existing === null) {
                return null;
            }

            if ($existing->source === 'loose') {
                $this->loose->delete($physicalName);
            }

            if ($this->packedHasPhysical($physicalName, $algorithm)) {
                $this->rewritePackedReferences([], [$physicalName], $algorithm);
            }

            return $this->storeRelativeReference($existing);
        }

        return $this->deleteWithReport(
            $name,
            $previous,
            $expectedTarget,
            $deref,
            $algorithm,
            ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
        )->reference;
    }

    public function deleteWithReport(
        string $name,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $algorithm = 'sha1',
        string $reflogMode = ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
    ): ReferenceDeleteResult {
        if ($previous === self::PREVIOUS_MUST_NOT_EXIST) {
            throw new \InvalidArgumentException('Must-not-exist constraints are invalid for reference deletion');
        }
        if (!in_array($reflogMode, [
            ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
            ReferenceTransactionEdit::REFLOG_ONLY,
        ], true)) {
            throw new \InvalidArgumentException("Unknown reference deletion reflog mode: {$reflogMode}");
        }

        [$physicalName, $derefParents] = $this->dereferenceUpdateSplit($this->physicalName($name), $deref, $algorithm);
        $existing = $this->tryFindPhysical($physicalName, $algorithm);

        $this->assertPreviousValueAllowsDeletion($physicalName, $existing, $previous, $expectedTarget);
        $edits = $this->deleteReport($derefParents, $existing?->target, $physicalName, $reflogMode);

        foreach ($derefParents as $parent) {
            $this->deleteReflog($parent['name']);
        }

        $this->deleteReflog($physicalName);

        if ($existing === null) {
            return new ReferenceDeleteResult(null, $edits);
        }

        if ($reflogMode === ReferenceTransactionEdit::REFLOG_ONLY) {
            return new ReferenceDeleteResult($this->storeRelativeReference($existing), $edits);
        }

        if ($existing->source === 'loose') {
            $this->loose->delete($physicalName);
        }

        if ($this->packedHasPhysical($physicalName, $algorithm)) {
            $this->rewritePackedReferences([], [$physicalName], $algorithm);
        }

        return new ReferenceDeleteResult($this->storeRelativeReference($existing), $edits);
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

    public function reflogExists(string $name): bool
    {
        return is_file($this->reflogPath($this->physicalName($name)));
    }

    public function reflogContents(string $name): ?string
    {
        $path = $this->reflogPath($this->physicalName($name));
        if (!is_file($path)) {
            return null;
        }

        return (string) file_get_contents($path);
    }

    public function appendReflog(
        string $name,
        ?ReferenceTarget $previous,
        ReferenceTarget $new,
        CommitSignature $committer,
        string $message = '',
        bool $forceCreate = false,
        string $algorithm = 'sha1',
    ): void {
        $physicalName = $this->physicalName($name);
        $this->appendPhysicalReflog($physicalName, $previous, $new, $committer, $message, $forceCreate, $algorithm);
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
        return $reference->withNameAndTarget(
            $this->storeRelativeName($reference->name),
            $this->storeRelativeTarget($reference->target),
        );
    }

    private function storeRelativeName(string $name): string
    {
        if ($this->namespacePrefix === null || !str_starts_with($name, $this->namespacePrefix)) {
            return $name;
        }

        return substr($name, strlen($this->namespacePrefix));
    }

    private function storeRelativeTarget(?ReferenceTarget $target): ?ReferenceTarget
    {
        if (
            $target === null
            || $this->namespacePrefix === null
            || !$target->isSymbolic()
            || !str_starts_with($target->value, $this->namespacePrefix)
        ) {
            return $target;
        }

        return ReferenceTarget::symbolic(substr($target->value, strlen($this->namespacePrefix)));
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

    /**
     * @return array{0:string,1:list<array{name:string,target:ReferenceTarget}>}
     */
    private function dereferenceUpdateSplit(string $physicalName, bool $deref, string $algorithm): array
    {
        if (!$deref) {
            return [$physicalName, []];
        }

        $parents = [];
        $seen = [];
        $name = $physicalName;
        while (true) {
            if (isset($seen[$name])) {
                throw new \RuntimeException("Symbolic reference cycle while resolving {$physicalName}");
            }
            $seen[$name] = true;

            $reference = $this->loose->tryRead($name, $algorithm);
            if ($reference === null || !$reference->target->isSymbolic()) {
                return [$name, $parents];
            }

            $parents[] = [
                'name' => $name,
                'target' => $reference->target,
            ];
            $name = $reference->target->value;
        }
    }

    /**
     * @param list<array{name:string,target:ReferenceTarget}> $derefParents
     * @return list<ReferenceTransactionEdit>
     */
    private function updateReport(
        array $derefParents,
        ?ReferenceTarget $previousTarget,
        ReferenceTarget $newTarget,
        string $leafName,
    ): array {
        $edits = [];
        foreach ($derefParents as $parent) {
            $edits[] = ReferenceTransactionEdit::update(
                $this->storeRelativeName($parent['name']),
                $this->storeRelativeTarget($parent['target']),
                $this->storeRelativeTarget($newTarget),
                ReferenceTransactionEdit::REFLOG_ONLY,
                false,
            );
        }

        $edits[] = ReferenceTransactionEdit::update(
            $this->storeRelativeName($leafName),
            $this->storeRelativeTarget($previousTarget),
            $this->storeRelativeTarget($newTarget),
            ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
            true,
        );

        return $edits;
    }

    /**
     * @param list<array{name:string,target:ReferenceTarget}> $derefParents
     * @return list<ReferenceTransactionEdit>
     */
    private function deleteReport(
        array $derefParents,
        ?ReferenceTarget $previousTarget,
        string $leafName,
        string $leafReflogMode,
    ): array {
        $edits = [];
        foreach ($derefParents as $parent) {
            $edits[] = ReferenceTransactionEdit::delete(
                $this->storeRelativeName($parent['name']),
                $this->storeRelativeTarget($parent['target']),
                ReferenceTransactionEdit::REFLOG_ONLY,
                false,
            );
        }

        $edits[] = ReferenceTransactionEdit::delete(
            $this->storeRelativeName($leafName),
            $this->storeRelativeTarget($previousTarget),
            $leafReflogMode,
            $leafReflogMode === ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
        );

        return $edits;
    }

    /**
     * @param list<array{name:string,target:ReferenceTarget}> $derefParents
     */
    private function appendDereferenceParentReflogs(
        array $derefParents,
        ?ReferenceTarget $leafPreviousTarget,
        ReferenceTarget $newTarget,
        ?CommitSignature $committer,
        string $message,
        bool $forceCreate,
        string $algorithm,
    ): void {
        if ($derefParents === [] || !$newTarget->isObject()) {
            return;
        }

        $previous = $leafPreviousTarget !== null && $leafPreviousTarget->isObject() ? $leafPreviousTarget : null;
        foreach ($derefParents as $parent) {
            $this->maybeAppendReflog(
                $parent['name'],
                $previous,
                $newTarget,
                $committer,
                $message,
                $forceCreate,
                $algorithm,
            );
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

    private function maybeAppendReflog(
        string $physicalName,
        ?ReferenceTarget $previous,
        ReferenceTarget $new,
        ?CommitSignature $committer,
        string $message,
        bool $forceCreate,
        string $algorithm,
    ): void {
        if ($committer === null && $message === '' && !$forceCreate) {
            return;
        }

        if ($committer === null) {
            throw new \InvalidArgumentException('Reflog updates need a committer signature');
        }

        $this->appendPhysicalReflog($physicalName, $previous, $new, $committer, $message, $forceCreate, $algorithm);
    }

    private function appendPhysicalReflog(
        string $physicalName,
        ?ReferenceTarget $previous,
        ReferenceTarget $new,
        CommitSignature $committer,
        string $message,
        bool $forceCreate,
        string $algorithm,
    ): void {
        if (!$new->isObject()) {
            return;
        }

        if ($previous !== null && !$previous->isObject()) {
            $previous = null;
        }

        if ($previous !== null && $previous->value === $new->value) {
            return;
        }

        if (str_contains($message, "\n") || str_contains($message, "\r")) {
            throw new \InvalidArgumentException('Reflog message must not contain newline bytes');
        }

        ReferenceTarget::assertValidObjectId($new->value, $algorithm);
        if ($previous !== null) {
            ReferenceTarget::assertValidObjectId($previous->value, $algorithm);
        }

        $path = $this->reflogPath($physicalName);
        if (!is_file($path) && !$forceCreate && !$this->shouldAutoCreateReflog($physicalName)) {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create reflog directory: {$directory}");
        }

        $old = $previous?->value ?? str_repeat('0', ReferenceTarget::hashHexLength($algorithm));
        $line = $old . ' ' . $new->value . ' ' . $committer->trimmed()->storageBytes();
        $line .= $message === '' ? "\n" : "\t{$message}\n";

        if (file_put_contents($path, $line, FILE_APPEND) === false) {
            throw new \RuntimeException("Unable to append reflog: {$physicalName}");
        }
    }

    private function deleteReflog(string $physicalName): void
    {
        $path = $this->reflogPath($physicalName);
        if (!is_file($path)) {
            return;
        }

        if (!unlink($path)) {
            throw new \RuntimeException("Unable to delete reflog: {$physicalName}");
        }

        $this->deleteEmptyParents(dirname($path), rtrim($this->gitDirectory, '/\\') . '/logs');
    }

    private function reflogPath(string $physicalName): string
    {
        ReferenceName::assertValid($physicalName);

        return rtrim($this->gitDirectory, '/\\') . '/logs/' . $physicalName;
    }

    private function shouldAutoCreateReflog(string $physicalName): bool
    {
        return $physicalName === 'HEAD'
            || str_starts_with($physicalName, 'refs/heads/')
            || str_starts_with($physicalName, 'refs/remotes/')
            || str_starts_with($physicalName, 'refs/notes/')
            || str_starts_with($physicalName, 'refs/worktree/');
    }

    /**
     * @param array<string, PackedReference> $updates
     * @param list<string> $deletions
     */
    private function rewritePackedReferences(array $updates, array $deletions, string $algorithm): void
    {
        foreach ($updates as $name => $reference) {
            if (!$reference instanceof PackedReference) {
                throw new \InvalidArgumentException('Packed reference updates must be packed reference instances');
            }
            if ($reference->name !== $name) {
                throw new \InvalidArgumentException('Packed reference update keys must match reference names');
            }
        }

        $byName = [];
        if ($this->packed === null && is_file($this->packedRefsPath())) {
            $this->packed = PackedReferences::open($this->packedRefsPath(), $algorithm);
        }

        foreach ($this->packed?->all() ?? [] as $reference) {
            $byName[$reference->name] = $reference;
        }

        foreach ($deletions as $name) {
            unset($byName[$name]);
        }

        foreach ($updates as $name => $reference) {
            $byName[$name] = $reference;
        }

        ksort($byName, SORT_STRING);

        if ($byName === []) {
            if (is_file($this->packedRefsPath()) && !unlink($this->packedRefsPath())) {
                throw new \RuntimeException('Unable to remove empty packed-refs file');
            }
            $this->packed = null;
            return;
        }

        $contents = "# pack-refs with: peeled fully-peeled sorted \n";
        foreach ($byName as $reference) {
            $contents .= $reference->target->value . ' ' . $reference->name . "\n";
            if ($reference->peeledObjectId !== null) {
                $contents .= '^' . $reference->peeledObjectId . "\n";
            }
        }

        $directory = dirname($this->packedRefsPath());
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create packed-refs directory: {$directory}");
        }

        if (file_put_contents($this->packedRefsPath(), $contents) === false) {
            throw new \RuntimeException('Unable to rewrite packed-refs file');
        }

        $this->packed = PackedReferences::fromBytes($contents, $algorithm);
    }

    private function packedReferenceForUpdate(
        string $physicalName,
        ReferenceTarget $target,
        string $algorithm,
        ?ObjectDatabase $objectDatabase,
    ): PackedReference {
        if (!$target->isObject()) {
            throw new \InvalidArgumentException('Packed reference updates must point at object ids');
        }

        return new PackedReference(
            $physicalName,
            $target,
            $this->resolvePeeledObjectId($target, $algorithm, $objectDatabase),
        );
    }

    private function resolvePeeledObjectId(
        ReferenceTarget $target,
        string $algorithm,
        ?ObjectDatabase $objectDatabase,
    ): ?string {
        if ($objectDatabase === null) {
            return null;
        }

        $current = $target->value;
        $peeled = null;
        $seen = [];
        while (true) {
            if (isset($seen[$current])) {
                throw new \RuntimeException("Tag peel cycle while resolving packed reference target: {$target->value}");
            }
            $seen[$current] = true;

            $object = $objectDatabase->read($current);
            if ($object->type !== 'tag') {
                return $peeled;
            }

            $tag = GitTag::parse($object->body, $algorithm);
            $peeled = $tag->target;
            $current = $tag->target;
        }
    }

    private function packedHasPhysical(string $physicalName, string $algorithm): bool
    {
        return $this->packed?->tryFind($physicalName) !== null
            || ($this->packed === null
                && is_file($this->packedRefsPath())
                && PackedReferences::open($this->packedRefsPath(), $algorithm)->tryFind($physicalName) !== null);
    }

    private function packedTargetEquals(string $physicalName, ReferenceTarget $target, string $algorithm): bool
    {
        if ($this->packed === null && is_file($this->packedRefsPath())) {
            $this->packed = PackedReferences::open($this->packedRefsPath(), $algorithm);
        }

        $packed = $this->packed?->tryFind($physicalName);

        return $packed !== null && self::targetsEqual($packed->target, $target);
    }

    private function packedRefsPath(): string
    {
        return rtrim($this->gitDirectory, '/\\') . '/packed-refs';
    }

    private function deleteEmptyParents(string $directory, string $boundary): void
    {
        $boundary = str_replace('\\', '/', rtrim($boundary, '/\\'));
        $current = str_replace('\\', '/', $directory);

        while ($current !== $boundary && str_starts_with($current, $boundary . '/')) {
            $entries = @scandir($current);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) !== 0) {
                break;
            }
            @rmdir($current);
            $current = str_replace('\\', '/', dirname($current));
        }
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

    private static function assertPackedRefsMode(string $mode): void
    {
        if (!in_array($mode, [
            self::PACKED_DELETIONS_ONLY,
            self::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES,
            self::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
        ], true)) {
            throw new \InvalidArgumentException("Unknown packed refs transaction mode: {$mode}");
        }
    }
}
