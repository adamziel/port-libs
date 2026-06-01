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

    public const WRITE_REFLOG_NORMAL = 'normal';
    public const WRITE_REFLOG_ALWAYS = 'always';
    public const WRITE_REFLOG_DISABLE = 'disable';

    private readonly LooseReferenceStore $loose;
    private ?PackedReferences $packed;
    private readonly ?string $namespacePrefix;
    private ?array $packedRefsSnapshot;

    public function __construct(
        private readonly string $gitDirectory,
        ?PackedReferences $packed = null,
        ?string $namespace = null,
        private readonly string $writeReflogMode = self::WRITE_REFLOG_NORMAL,
        private readonly bool $prohibitWindowsDeviceNames = false,
    ) {
        self::assertWriteReflogMode($writeReflogMode);
        $this->loose = new LooseReferenceStore($gitDirectory);
        $this->packed = $packed;
        $this->namespacePrefix = $namespace === null ? null : ReferenceName::expandNamespace($namespace);
        $this->packedRefsSnapshot = $this->packedRefsFileSnapshot();
    }

    public static function at(
        string $gitDirectory,
        string $algorithm = 'sha1',
        ?string $namespace = null,
        string $writeReflogMode = self::WRITE_REFLOG_NORMAL,
        bool $prohibitWindowsDeviceNames = false,
    ): self
    {
        $packedPath = rtrim($gitDirectory, '/\\') . '/packed-refs';
        $packed = is_file($packedPath) ? PackedReferences::open($packedPath, $algorithm) : null;

        return new self($gitDirectory, $packed, $namespace, $writeReflogMode, $prohibitWindowsDeviceNames);
    }

    public function withNamespace(string $namespace): self
    {
        return new self($this->gitDirectory, $this->packed, $namespace, $this->writeReflogMode, $this->prohibitWindowsDeviceNames);
    }

    public function looseStore(): LooseReferenceStore
    {
        return $this->loose;
    }

    /**
     * Prepare loose-reference update lock files that can be rolled back without
     * committing the reference writes.
     *
     * @param array<string, ReferenceTarget> $updates
     */
    public function prepareLooseUpdateTransaction(
        array $updates,
        string $algorithm = 'sha1',
        ?CommitSignature $committer = null,
        string $reflogMessage = '',
        bool $forceCreateReflog = false,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $packedRefsMode = self::PACKED_DELETIONS_ONLY,
        ?ObjectDatabase $objectDatabase = null,
    ): PreparedReferenceTransaction
    {
        self::assertPackedRefsMode($packedRefsMode);

        $locks = [];
        $packedRefsUpdates = [];
        $packedRefsDeleteLoose = [];
        $writeReflog = $this->writeReflogMode !== self::WRITE_REFLOG_DISABLE
            && (
                $committer !== null
                || $reflogMessage !== ''
                || $forceCreateReflog
                || $this->writeReflogMode === self::WRITE_REFLOG_ALWAYS
            );

        $preparedUpdates = [];
        $preparedNames = [];
        foreach ($updates as $name => $target) {
            if (!$target instanceof ReferenceTarget) {
                throw new \InvalidArgumentException('Prepared reference updates must be keyed by name and contain ReferenceTarget values');
            }

            [$physicalName, $derefParents] = $this->dereferenceUpdateSplit($this->physicalName((string) $name), $deref, $algorithm);
            $physicalTarget = $this->physicalTarget($target);
            foreach ($derefParents as $parent) {
                $parentPhysicalName = $parent['name'];
                if (isset($preparedNames[$parentPhysicalName])) {
                    throw new \RuntimeException("A reference named \"{$this->storeRelativeName($parentPhysicalName)}\" has multiple prepared edits");
                }
                $preparedNames[$parentPhysicalName] = true;
            }
            if (isset($preparedNames[$physicalName])) {
                throw new \RuntimeException("A reference named \"{$this->storeRelativeName($physicalName)}\" has multiple prepared edits");
            }
            $preparedNames[$physicalName] = true;

            $preparedUpdates[] = [
                'physicalName' => $physicalName,
                'physicalTarget' => $physicalTarget,
                'derefParents' => $derefParents,
            ];
        }

        $forcePackedRefsLock = false;
        $hasPackablePackedRef = false;
        foreach ($preparedUpdates as $preparedUpdate) {
            $packedPhysicalName = $this->packedTransactionPhysicalName($preparedUpdate['physicalName']);
            if ($packedPhysicalName === null) {
                continue;
            }

            $hasPackablePackedRef = true;
            if ($packedRefsMode !== self::PACKED_DELETIONS_ONLY && $preparedUpdate['physicalTarget']->isObject()) {
                $forcePackedRefsLock = true;
                break;
            }
        }
        $packedRefsLockPath = $hasPackablePackedRef
            ? $this->preparePackedRefsLockForLooseTransaction($forcePackedRefsLock)
            : null;

        try {
            foreach ($preparedUpdates as $preparedUpdate) {
                $physicalName = $preparedUpdate['physicalName'];
                $physicalTarget = $preparedUpdate['physicalTarget'];
                $derefParents = $preparedUpdate['derefParents'];
                $existing = $this->tryFindPhysical($physicalName, $algorithm);
                $this->assertPreviousValueAllowsUpdate($physicalName, $physicalTarget, $existing, $previous, $expectedTarget);
                $packedPhysicalName = $this->packedTransactionPhysicalName($physicalName);
                $writesObjectToPackedRefs = $packedPhysicalName !== null
                    && $packedRefsMode !== self::PACKED_DELETIONS_ONLY
                    && $physicalTarget->isObject();
                $removesLooseSourceAfterPackedCommit = $packedRefsMode === self::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE
                    && $physicalTarget->isObject();

                if ($writesObjectToPackedRefs) {
                    $packedRefsUpdates[$packedPhysicalName] = $this->packedReferenceForUpdate(
                        $packedPhysicalName,
                        $physicalTarget,
                        $algorithm,
                        $objectDatabase,
                    );
                    if ($removesLooseSourceAfterPackedCommit) {
                        $packedRefsDeleteLoose[$physicalName] = true;
                    }
                }

                if ($derefParents !== []) {
                    $leafPreviousForReflog = $existing?->target;
                    foreach ($derefParents as $parent) {
                        $parentPhysicalName = $parent['name'];
                        $parentTargetPath = $this->referencePath($parentPhysicalName);
                        $parentLockPath = $parentTargetPath . '.lock';

                        if (is_file($parentLockPath) || is_dir($parentLockPath)) {
                            throw new \RuntimeException("A lock could not be obtained for reference \"{$this->storeRelativeName($parentPhysicalName)}\"");
                        }

                        $parentDirectory = dirname($parentLockPath);
                        if (!is_dir($parentDirectory) && !mkdir($parentDirectory, 0777, true) && !is_dir($parentDirectory)) {
                            throw new \RuntimeException("Unable to create prepared reference lock directory: {$parentDirectory}");
                        }

                        if (file_put_contents($parentLockPath, $physicalTarget->storageBytes(), LOCK_EX) === false) {
                            throw new \RuntimeException("Unable to write prepared reference lock: {$parentPhysicalName}");
                        }

                        $locks[] = [
                            'lockPath' => $parentLockPath,
                            'edit' => ReferenceTransactionEdit::update(
                                $this->storeRelativeName($parentPhysicalName),
                                $this->storeRelativeTarget($parent['target']),
                                $this->storeRelativeTarget($physicalTarget),
                                ReferenceTransactionEdit::REFLOG_ONLY,
                                false,
                            ),
                            'reflog' => $writeReflog ? [
                                'physicalName' => $parentPhysicalName,
                                'previousTarget' => $leafPreviousForReflog,
                                'newTarget' => $physicalTarget,
                                'committer' => $committer,
                                'message' => $reflogMessage,
                                'forceCreate' => $forceCreateReflog,
                                'algorithm' => $algorithm,
                                'writeMode' => $this->writeReflogMode,
                            ] : null,
                        ];
                    }
                }

                $targetPath = $this->referencePath($physicalName);
                $lockPath = $targetPath . '.lock';
                $leafReflogTarget = $this->leafReflogTargetForUpdate($physicalTarget, $previous, $expectedTarget);
                $edit = ReferenceTransactionEdit::update(
                    $this->storeRelativeName($physicalName),
                    $this->storeRelativeTarget($existing?->target),
                    $this->storeRelativeTarget($physicalTarget),
                    ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
                    true,
                );
                $reflog = $writeReflog && $leafReflogTarget !== null ? [
                    'physicalName' => $physicalName,
                    'previousTarget' => $physicalTarget->isSymbolic() ? null : $existing?->target,
                    'newTarget' => $leafReflogTarget,
                    'committer' => $committer,
                    'message' => $reflogMessage,
                    'forceCreate' => $forceCreateReflog,
                    'algorithm' => $algorithm,
                    'writeMode' => $this->writeReflogMode,
                ] : null;

                if ($existing !== null && self::targetsEqual($existing->target, $physicalTarget) && !$writesObjectToPackedRefs) {
                    $locks[] = [
                        'action' => PreparedReferenceTransaction::ACTION_NOOP,
                        'edit' => $edit,
                    ];
                    continue;
                }

                if ($writesObjectToPackedRefs) {
                    $locks[] = [
                        'action' => PreparedReferenceTransaction::ACTION_PACKED_UPDATE,
                        'edit' => $edit,
                        'reflog' => $reflog,
                    ];
                    continue;
                }

                if (is_file($lockPath) || is_dir($lockPath)) {
                    throw new \RuntimeException("A lock could not be obtained for reference \"{$this->storeRelativeName($physicalName)}\"");
                }

                $directory = dirname($lockPath);
                if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new \RuntimeException("Unable to create prepared reference lock directory: {$directory}");
                }

                if (file_put_contents($lockPath, $physicalTarget->storageBytes(), LOCK_EX) === false) {
                    throw new \RuntimeException("Unable to write prepared reference lock: {$physicalName}");
                }

                $locks[] = [
                    'lockPath' => $lockPath,
                    'edit' => $edit,
                    'reflog' => $reflog,
                ];
            }
        } catch (\Throwable $throwable) {
            (new PreparedReferenceTransaction($this->gitDirectory, $locks, $packedRefsLockPath))->rollback();

            throw $throwable;
        }

        $packedRefsUpdatePlan = $packedRefsUpdates === []
            ? null
            : [
                'updates' => $packedRefsUpdates,
                'deleteLoose' => array_keys($packedRefsDeleteLoose),
                'algorithm' => $algorithm,
            ];

        return new PreparedReferenceTransaction($this->gitDirectory, $locks, $packedRefsLockPath, null, $packedRefsUpdatePlan);
    }

    /**
     * Prepare loose-reference deletions behind lock files that can be rolled
     * back before commit.
     *
     * @param list<string> $names
     */
    public function prepareLooseDeleteTransaction(
        array $names,
        string $previous = self::PREVIOUS_ANY,
        ?ReferenceTarget $expectedTarget = null,
        bool $deref = false,
        string $algorithm = 'sha1',
        string $reflogMode = ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
    ): PreparedReferenceTransaction
    {
        if ($previous === self::PREVIOUS_MUST_NOT_EXIST) {
            throw new \InvalidArgumentException('Must-not-exist constraints are invalid for reference deletion');
        }
        if (!in_array($reflogMode, [
            ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
            ReferenceTransactionEdit::REFLOG_ONLY,
        ], true)) {
            throw new \InvalidArgumentException("Unknown reference deletion reflog mode: {$reflogMode}");
        }
        $locks = [];
        $preparedNames = [];
        $packedRefDeletions = [];
        $deletePlans = [];
        $hasPackableDeletion = false;
        $packedRefsLockPath = null;

        try {
            foreach ($names as $name) {
                if (!is_string($name)) {
                    throw new \InvalidArgumentException('Prepared reference deletions must be a list of reference names');
                }

                [$physicalName, $derefParents] = $this->dereferenceDeleteSplit($this->physicalName($name), $deref, $algorithm, $previous);
                [$existing, $brokenLooseExists] = $this->tryFindPhysicalForDeletion($physicalName, $algorithm);
                $this->assertPreviousValueAllowsDeletion($physicalName, $existing, $previous, $expectedTarget, $brokenLooseExists);

                foreach ($this->deleteReport($derefParents, $existing?->target, $physicalName, $reflogMode) as $edit) {
                    $editPhysicalName = $this->physicalName($edit->name);
                    if (isset($preparedNames[$editPhysicalName])) {
                        throw new \RuntimeException("A reference named \"{$edit->name}\" has multiple prepared edits");
                    }
                    $preparedNames[$editPhysicalName] = true;

                    $deletePlans[] = [
                        'edit' => $edit,
                        'physicalName' => $editPhysicalName,
                        'deleteReference' => $edit->updatesReference || ($editPhysicalName === $physicalName && $brokenLooseExists),
                    ];

                    if ($edit->reflogMode === ReferenceTransactionEdit::REFLOG_AND_REFERENCE) {
                        $packedPhysicalName = $this->packedTransactionPhysicalName($editPhysicalName);
                        if ($packedPhysicalName !== null) {
                            $hasPackableDeletion = true;
                            if ($this->packedHasPhysical($packedPhysicalName, $algorithm)) {
                                $packedRefDeletions[$packedPhysicalName] = true;
                            }
                        }
                    }
                }
            }

            if ($hasPackableDeletion) {
                $packedRefsLockPath = $this->preparePackedRefsLockForLooseTransaction();
            }

            foreach ($deletePlans as $deletePlan) {
                $edit = $deletePlan['edit'];
                $editPhysicalName = $deletePlan['physicalName'];
                $targetPath = $this->referencePath($editPhysicalName);
                $lockPath = $targetPath . '.lock';

                if (is_file($lockPath) || is_dir($lockPath)) {
                    throw new \RuntimeException("A lock could not be obtained for reference \"{$edit->name}\"");
                }

                $directory = dirname($lockPath);
                if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new \RuntimeException("Unable to create prepared reference lock directory: {$directory}");
                }

                if (file_put_contents($lockPath, '', LOCK_EX) === false) {
                    throw new \RuntimeException("Unable to write prepared reference deletion lock: {$editPhysicalName}");
                }

                $locks[] = [
                    'action' => PreparedReferenceTransaction::ACTION_DELETE,
                    'lockPath' => $lockPath,
                    'edit' => $edit,
                    'delete' => [
                        'physicalName' => $editPhysicalName,
                        'deleteReference' => $deletePlan['deleteReference'],
                        'deleteReflog' => true,
                    ],
                ];
            }
        } catch (\Throwable $throwable) {
            (new PreparedReferenceTransaction($this->gitDirectory, $locks, $packedRefsLockPath))->rollback();

            throw $throwable;
        }

        $packedRefsDeletionPlan = $packedRefDeletions === []
            ? null
            : [
                'deletions' => array_keys($packedRefDeletions),
                'algorithm' => $algorithm,
            ];

        return new PreparedReferenceTransaction($this->gitDirectory, $locks, $packedRefsLockPath, $packedRefsDeletionPlan);
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
        $packedPhysicalName = $this->packedTransactionPhysicalName($physicalName);
        $writesObjectToPackedRefs = $packedPhysicalName !== null
            && $packedRefsMode !== self::PACKED_DELETIONS_ONLY
            && $physicalTarget->isObject();
        $packedRefsLock = $packedPhysicalName !== null
            && $this->packedRefsTransactionNeedsLock($existing, $writesObjectToPackedRefs)
            ? $this->acquirePackedRefsLock()
            : null;

        try {
            if ($existing !== null && self::targetsEqual($existing->target, $physicalTarget)) {
                if (
                    $writesObjectToPackedRefs
                    && !$this->packedReferenceMatchesUpdate($packedPhysicalName, $physicalTarget, $algorithm, $objectDatabase)
                ) {
                    $packedReference = $this->packedReferenceForUpdate(
                        $packedPhysicalName,
                        $physicalTarget,
                        $algorithm,
                        $objectDatabase,
                    );
                    $this->rewritePackedReferences(
                        [$physicalName => $packedReference],
                        [],
                        $algorithm,
                        $packedRefsLock,
                    );
                    $packedRefsLock = null;

                    if ($packedRefsMode === self::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE) {
                        $this->loose->delete($physicalName);

                        return new ReferenceUpdateResult(
                            $this->storeRelativeReference(ResolvedReference::fromPacked($packedReference)),
                            $edits,
                        );
                    }

                    if ($existing->source === 'packed') {
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

            $leafReflogTarget = $this->leafReflogTargetForUpdate($physicalTarget, $previous, $expectedTarget);
            if ($leafReflogTarget !== null) {
                $this->maybeAppendReflog(
                    $physicalName,
                    $physicalTarget->isSymbolic() ? null : $existing?->target,
                    $leafReflogTarget,
                    $committer,
                    $reflogMessage,
                    $forceCreateReflog,
                    $algorithm,
                );
            }

            if ($writesObjectToPackedRefs) {
                $packedReference = $this->packedReferenceForUpdate(
                    $packedPhysicalName,
                    $physicalTarget,
                    $algorithm,
                    $objectDatabase,
                );
                $this->rewritePackedReferences(
                    [$physicalName => $packedReference],
                    [],
                    $algorithm,
                    $packedRefsLock,
                );
                $packedRefsLock = null;

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
        } finally {
            $this->releasePackedRefsLock($packedRefsLock);
        }
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
            $physicalName = $this->dereferenceDeleteName($this->physicalName($name), $deref, $algorithm, $previous);
            [$existing, $brokenLooseExists] = $this->tryFindPhysicalForDeletion($physicalName, $algorithm);

            $this->assertPreviousValueAllowsDeletion($physicalName, $existing, $previous, $expectedTarget, $brokenLooseExists);
            $packedPhysicalName = $this->packedTransactionPhysicalName($physicalName);
            $rewritesPackedRefs = $packedPhysicalName !== null && $this->packedHasPhysical($packedPhysicalName, $algorithm);
            $packedRefsLock = $packedPhysicalName !== null
                && $this->packedRefsTransactionNeedsLock($existing, $rewritesPackedRefs)
                ? $this->acquirePackedRefsLock()
                : null;

            try {
                if ($existing === null && !$brokenLooseExists) {
                    return null;
                }

                if (($existing?->source === 'loose') || $brokenLooseExists) {
                    $this->loose->delete($physicalName);
                }

                if ($rewritesPackedRefs) {
                    $this->rewritePackedReferences([], [$packedPhysicalName], $algorithm, $packedRefsLock);
                    $packedRefsLock = null;
                }

                return $existing === null ? null : $this->storeRelativeReference($existing);
            } finally {
                $this->releasePackedRefsLock($packedRefsLock);
            }
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

        [$physicalName, $derefParents] = $this->dereferenceDeleteSplit($this->physicalName($name), $deref, $algorithm, $previous);
        [$existing, $brokenLooseExists] = $this->tryFindPhysicalForDeletion($physicalName, $algorithm);

        $this->assertPreviousValueAllowsDeletion($physicalName, $existing, $previous, $expectedTarget, $brokenLooseExists);
        $edits = $this->deleteReport($derefParents, $existing?->target, $physicalName, $reflogMode);
        $packedPhysicalName = $this->packedTransactionPhysicalName($physicalName);
        $rewritesPackedRefs = $reflogMode === ReferenceTransactionEdit::REFLOG_AND_REFERENCE
            && $packedPhysicalName !== null
            && $this->packedHasPhysical($packedPhysicalName, $algorithm);
        $needsPackedRefsLock = $reflogMode === ReferenceTransactionEdit::REFLOG_AND_REFERENCE
            && $packedPhysicalName !== null
            && $this->packedRefsTransactionNeedsLock($existing, $rewritesPackedRefs);
        $packedRefsLock = $needsPackedRefsLock ? $this->acquirePackedRefsLock() : null;

        try {
            foreach ($derefParents as $parent) {
                $this->deleteReflog($parent['name']);
            }

            $this->deleteReflog($physicalName);

            if ($existing === null && !$brokenLooseExists) {
                return new ReferenceDeleteResult(null, $edits);
            }

            if ($reflogMode === ReferenceTransactionEdit::REFLOG_ONLY) {
                return new ReferenceDeleteResult($existing === null ? null : $this->storeRelativeReference($existing), $edits);
            }

            if (($existing?->source === 'loose') || $brokenLooseExists) {
                $this->loose->delete($physicalName);
            }

            if ($rewritesPackedRefs) {
                $this->rewritePackedReferences([], [$packedPhysicalName], $algorithm, $packedRefsLock);
                $packedRefsLock = null;
            }

            return new ReferenceDeleteResult($existing === null ? null : $this->storeRelativeReference($existing), $edits);
        } finally {
            $this->releasePackedRefsLock($packedRefsLock);
        }
    }

    public function tryFind(string $name, string $algorithm = 'sha1'): ?ResolvedReference
    {
        $this->refreshPackedReferencesIfChanged($algorithm);

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
        $this->refreshPackedReferencesIfChanged($algorithm);

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
     * Follow symbolic refs while iterating and return object-target references.
     *
     * Packed refs with stored peeled IDs use that value directly; loose tag
     * chains need an object database to peel beyond their referenced object.
     *
     * @return list<ResolvedReference>
     */
    public function prefixedPeeled(string $prefix, ?ObjectDatabase $objectDatabase = null, string $algorithm = 'sha1'): array
    {
        return array_map(
            fn (ResolvedReference $reference): ResolvedReference => $this->peeledReference(
                $reference,
                $objectDatabase,
                $algorithm,
            ),
            $this->prefixed($prefix, $algorithm),
        );
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

    /**
     * @return list<ReflogEntry>|null
     */
    public function reflogEntries(string $name, string $algorithm = 'any'): ?array
    {
        $contents = $this->reflogContents($name);
        if ($contents === null) {
            return null;
        }

        return ReflogEntry::parseAll($contents, $algorithm);
    }

    /**
     * @return list<ReflogEntry>|null
     */
    public function reflogEntriesReverse(string $name, string $algorithm = 'any'): ?array
    {
        $contents = $this->reflogContents($name);
        if ($contents === null) {
            return null;
        }

        return ReflogEntry::parseReverse($contents, $algorithm);
    }

    /**
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: ReflogEntry, error?: string}>|null
     */
    public function reflogEntryResults(string $name, string $algorithm = 'any'): ?array
    {
        $contents = $this->reflogContents($name);
        if ($contents === null) {
            return null;
        }

        return ReflogEntry::iterateForward($contents, $algorithm);
    }

    /**
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: ReflogEntry, error?: string}>|null
     */
    public function reflogEntryResultsReverse(string $name, string $algorithm = 'any'): ?array
    {
        $contents = $this->reflogContents($name);
        if ($contents === null) {
            return null;
        }

        return ReflogEntry::iterateReverse($contents, $algorithm);
    }

    /**
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: ReflogEntry, error?: string, bufferTooSmall?: bool}>|null
     */
    public function reflogEntryResultsReverseBounded(string $name, int $bufferSize = 4096, string $algorithm = 'any'): ?array
    {
        $contents = $this->reflogContents($name);
        if ($contents === null) {
            return null;
        }

        return ReflogEntry::iterateReverseBounded($contents, $bufferSize, $algorithm);
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

    public function followToObjectId(string $name, string $algorithm = 'sha1'): string
    {
        $reference = $this->followReferenceToObject($this->find($name, $algorithm), $algorithm);
        $objectId = $reference->targetObjectId();
        if ($objectId === null) {
            throw new \RuntimeException("Reference did not resolve to an object id: {$name}");
        }

        return $objectId;
    }

    public function peelToObjectId(string $name, ?ObjectDatabase $objectDatabase = null, string $algorithm = 'sha1'): string
    {
        $reference = $this->followReferenceToObject($this->find($name, $algorithm), $algorithm);
        if ($reference->peeledObjectId !== null) {
            return $reference->peeledObjectId;
        }

        $objectId = $reference->targetObjectId();
        if ($objectId === null) {
            throw new \RuntimeException("Reference did not resolve to an object id: {$name}");
        }

        if ($objectDatabase === null) {
            return $objectId;
        }

        return $this->peelObjectId(ReferenceTarget::object($objectId, $algorithm), $algorithm, $objectDatabase);
    }

    private function peeledReference(
        ResolvedReference $reference,
        ?ObjectDatabase $objectDatabase,
        string $algorithm,
    ): ResolvedReference {
        $followed = $this->followReferenceToObject($reference, $algorithm);
        if ($followed->peeledObjectId !== null) {
            return $followed->withNameAndTarget(
                $followed->name,
                ReferenceTarget::object($followed->peeledObjectId, $algorithm),
            );
        }

        $objectId = $followed->targetObjectId();
        if ($objectId === null) {
            throw new \RuntimeException("Reference did not resolve to an object id: {$reference->name}");
        }

        if ($objectDatabase !== null) {
            $objectId = $this->peelObjectId(ReferenceTarget::object($objectId, $algorithm), $algorithm, $objectDatabase);
        }

        return $followed->withNameAndTarget($followed->name, ReferenceTarget::object($objectId, $algorithm));
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

    private function followReferenceToObject(ResolvedReference $reference, string $algorithm): ResolvedReference
    {
        $start = $reference->name;
        $seen = [];
        $current = $reference;

        while ($current->target->isSymbolic()) {
            $current = $this->find($current->target->value, $algorithm);
            if (isset($seen[$current->name])) {
                throw new \RuntimeException("Symbolic reference cycle while peeling {$start}");
            }
            $seen[$current->name] = true;

            if (count($seen) >= 5) {
                throw new \RuntimeException("Symbolic reference depth limit exceeded while peeling {$start}");
            }
        }

        return $current;
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

        $physicalName = $this->namespacePrefix === null ? $name : $this->namespacePrefix . $name;
        $this->assertWindowsDeviceNamesAllowed($physicalName);

        return $physicalName;
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

    private function dereferenceDeleteName(string $physicalName, bool $deref, string $algorithm, string $previous): string
    {
        return $this->dereferenceDeleteSplit($physicalName, $deref, $algorithm, $previous)[0];
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
     * @return array{0:string,1:list<array{name:string,target:ReferenceTarget}>}
     */
    private function dereferenceDeleteSplit(string $physicalName, bool $deref, string $algorithm, string $previous): array
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

            try {
                $reference = $this->loose->tryRead($name, $algorithm);
            } catch (\InvalidArgumentException $exception) {
                if ($previous === self::PREVIOUS_ANY) {
                    return [$name, $parents];
                }

                if ($previous === self::PREVIOUS_MUST_EXIST) {
                    throw new \RuntimeException("Reference must exist before deletion: {$name}", 0, $exception);
                }

                if (in_array($previous, [self::PREVIOUS_MUST_EXIST_AND_MATCH, self::PREVIOUS_EXISTING_MUST_MATCH], true)) {
                    throw new \RuntimeException("Reference is out of date: {$name}", 0, $exception);
                }

                throw $exception;
            }

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

    private function leafReflogTargetForUpdate(
        ReferenceTarget $newTarget,
        string $previous,
        ?ReferenceTarget $expectedTarget,
    ): ?ReferenceTarget {
        if ($newTarget->isObject()) {
            return $newTarget;
        }

        if ($previous !== self::PREVIOUS_EXISTING_MUST_MATCH || $expectedTarget === null) {
            return null;
        }

        $expectedTarget = $this->physicalTarget($expectedTarget);

        return $expectedTarget->isObject() ? $expectedTarget : null;
    }

    private function tryFindPhysical(string $physicalName, string $algorithm): ?ResolvedReference
    {
        $this->refreshPackedReferencesIfChanged($algorithm);

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

    /**
     * @return array{0:?ResolvedReference,1:bool}
     */
    private function tryFindPhysicalForDeletion(string $physicalName, string $algorithm): array
    {
        try {
            return [$this->tryFindPhysical($physicalName, $algorithm), false];
        } catch (\InvalidArgumentException $exception) {
            if (!$this->loose->exists($physicalName)) {
                throw $exception;
            }

            return [null, true];
        }
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
        if ($this->writeReflogMode === self::WRITE_REFLOG_DISABLE) {
            return;
        }

        if ($committer === null && $message === '' && !$forceCreate && $this->writeReflogMode !== self::WRITE_REFLOG_ALWAYS) {
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
        if ($this->writeReflogMode === self::WRITE_REFLOG_DISABLE) {
            return;
        }

        if (!$new->isObject()) {
            return;
        }

        if ($previous !== null && !$previous->isObject()) {
            $previous = null;
        }

        if ($previous !== null && $previous->value === $new->value) {
            return;
        }

        if (str_contains($message, "\n")) {
            throw new \InvalidArgumentException('Reflog message must not contain newline bytes');
        }

        ReferenceTarget::assertValidObjectId($new->value, $algorithm);
        if ($previous !== null) {
            ReferenceTarget::assertValidObjectId($previous->value, $algorithm);
        }

        $path = $this->reflogPath($physicalName);
        $forceCreate = $forceCreate || $this->writeReflogMode === self::WRITE_REFLOG_ALWAYS;
        if (!is_file($path) && !$forceCreate && !$this->shouldAutoCreateReflog($physicalName)) {
            return;
        }

        if (is_dir($path) && !$this->removeEmptyDirectoryTree($path)) {
            throw new \RuntimeException("Unable to replace directory blocker with reflog: {$physicalName}");
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create reflog directory: {$directory}");
        }

        $line = ReflogEntry::appendLine($previous, $new, $committer, $message, $algorithm);

        if (file_put_contents($path, $line, FILE_APPEND) === false) {
            throw new \RuntimeException("Unable to append reflog: {$physicalName}");
        }
    }

    private function deleteReflog(string $physicalName): void
    {
        $path = $this->reflogPath($physicalName);
        if (is_dir($path)) {
            throw new \RuntimeException("Unable to delete reflog: {$physicalName}");
        }
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
        $physicalName = $this->reflogAutoCreateName($physicalName);

        return $physicalName === 'HEAD'
            || str_starts_with($physicalName, 'refs/heads/')
            || str_starts_with($physicalName, 'refs/remotes/')
            || str_starts_with($physicalName, 'refs/notes/')
            || str_starts_with($physicalName, 'refs/worktree/');
    }

    private function reflogAutoCreateName(string $physicalName): string
    {
        $name = $physicalName;
        while (str_starts_with($name, 'refs/namespaces/')) {
            $rest = substr($name, strlen('refs/namespaces/'));
            $slash = strpos($rest, '/');
            if ($slash === false) {
                return $physicalName;
            }
            $name = substr($rest, $slash + 1);
        }

        return $name;
    }

    /**
     * @param array<string, PackedReference> $updates
     * @param list<string> $deletions
     */
    private function rewritePackedReferences(array $updates, array $deletions, string $algorithm, $lock = null): void
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
        $this->refreshPackedReferencesIfChanged($algorithm);

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

        if (!is_resource($lock)) {
            $lock = $this->acquirePackedRefsLock();
        }
        $committed = false;

        try {
            if ($byName === []) {
                if (is_file($this->packedRefsPath()) && !unlink($this->packedRefsPath())) {
                    throw new \RuntimeException('Unable to remove empty packed-refs file');
                }

                $this->closePackedRefsLock($lock);
                $lock = null;
                if (is_file($this->packedRefsLockPath()) && !unlink($this->packedRefsLockPath())) {
                    throw new \RuntimeException('Unable to remove packed-refs lock file');
                }

                $this->packed = null;
                $this->packedRefsSnapshot = $this->packedRefsFileSnapshot();
                $committed = true;
                return;
            }

            $contents = "# pack-refs with: peeled fully-peeled sorted \n";
            foreach ($byName as $reference) {
                $contents .= $reference->target->value . ' ' . $reference->name . "\n";
                if ($reference->peeledObjectId !== null) {
                    $contents .= '^' . $reference->peeledObjectId . "\n";
                }
            }

            if (fwrite($lock, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Unable to write packed-refs lock file');
            }
            if (!fflush($lock)) {
                throw new \RuntimeException('Unable to flush packed-refs lock file');
            }
            $this->closePackedRefsLock($lock);
            $lock = null;

            if (!rename($this->packedRefsLockPath(), $this->packedRefsPath())) {
                throw new \RuntimeException('Unable to commit packed-refs lock file');
            }

            $this->packed = PackedReferences::fromBytes($contents, $algorithm);
            $this->packedRefsSnapshot = $this->packedRefsFileSnapshot();
            $committed = true;
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
            if (!$committed && is_file($this->packedRefsLockPath())) {
                @unlink($this->packedRefsLockPath());
            }
        }
    }

    private function preparePackedRefsLockForLooseTransaction(bool $force = false): ?string
    {
        if ($this->packedRefsLockPathExists()) {
            throw new \RuntimeException('The lock for the packed-ref file could not be obtained');
        }
        if (!$force && !$this->packedRefsPathExists()) {
            return null;
        }

        $lock = $this->acquirePackedRefsLock();
        $this->closePackedRefsLock($lock);

        return $this->packedRefsLockPath();
    }

    /**
     * @return resource
     */
    private function acquirePackedRefsLock()
    {
        $directory = dirname($this->packedRefsPath());
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create packed-refs directory: {$directory}");
        }

        $lock = @fopen($this->packedRefsLockPath(), 'x');
        if ($lock === false) {
            throw new \RuntimeException('The lock for the packed-ref file could not be obtained');
        }

        return $lock;
    }

    /**
     * @param resource $lock
     */
    private function closePackedRefsLock($lock): void
    {
        if (!fclose($lock)) {
            throw new \RuntimeException('Unable to close packed-refs lock file');
        }
    }

    private function releasePackedRefsLock($lock): void
    {
        if (!is_resource($lock)) {
            return;
        }

        $this->closePackedRefsLock($lock);
        if (is_file($this->packedRefsLockPath()) && !unlink($this->packedRefsLockPath())) {
            throw new \RuntimeException('Unable to remove packed-refs lock file');
        }
    }

    private function packedRefsTransactionNeedsLock(?ResolvedReference $existing, bool $rewritesPackedRefs): bool
    {
        return $rewritesPackedRefs
            || $existing?->source === 'packed'
            || $this->packedRefsPathExists()
            || $this->packedRefsLockPathExists();
    }

    private function packedRefsPathExists(): bool
    {
        $path = $this->packedRefsPath();
        clearstatcache(true, $path);

        return is_file($path) || is_dir($path);
    }

    private function packedRefsLockPathExists(): bool
    {
        $path = $this->packedRefsLockPath();
        clearstatcache(true, $path);

        return is_file($path) || is_dir($path);
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

        $peeled = $this->peelObjectId($target, $algorithm, $objectDatabase);

        return $peeled === $target->value ? null : $peeled;
    }

    private function peelObjectId(
        ReferenceTarget $target,
        string $algorithm,
        ObjectDatabase $objectDatabase,
    ): string {
        $current = $target->value;
        $seen = [];
        while (true) {
            if (isset($seen[$current])) {
                throw new \RuntimeException("Tag peel cycle while resolving packed reference target: {$target->value}");
            }
            $seen[$current] = true;

            $object = $objectDatabase->read($current);
            if ($object->type !== 'tag') {
                return $current;
            }

            $tag = GitTag::parse($object->body, $algorithm);
            $current = $tag->target;
        }
    }

    private function packedHasPhysical(string $physicalName, string $algorithm): bool
    {
        $this->refreshPackedReferencesIfChanged($algorithm);

        return $this->packed?->tryFind($physicalName) !== null;
    }

    private function packedReferenceMatchesUpdate(
        string $physicalName,
        ReferenceTarget $target,
        string $algorithm,
        ?ObjectDatabase $objectDatabase,
    ): bool
    {
        $this->refreshPackedReferencesIfChanged($algorithm);

        $packed = $this->packed?->tryFind($physicalName);

        if ($packed === null || !self::targetsEqual($packed->target, $target)) {
            return false;
        }

        if ($objectDatabase === null) {
            return true;
        }

        return $packed->peeledObjectId === $this->resolvePeeledObjectId($target, $algorithm, $objectDatabase);
    }

    private function packedTransactionPhysicalName(string $physicalName): ?string
    {
        ReferenceName::assertValid($physicalName);

        [$namespacePrefix, $relativeName] = $this->splitNamespacePrefix($physicalName);
        $category = ReferenceName::categoryAndShortName($relativeName);
        if ($category === null) {
            return $physicalName;
        }

        return match ($category['category']) {
            ReferenceName::CATEGORY_TAG,
            ReferenceName::CATEGORY_LOCAL_BRANCH,
            ReferenceName::CATEGORY_REMOTE_BRANCH,
            ReferenceName::CATEGORY_NOTE => $physicalName,
            ReferenceName::CATEGORY_MAIN_REF,
            ReferenceName::CATEGORY_LINKED_REF => $this->packedTransactionPhysicalNameFromShortName(
                $namespacePrefix,
                $category['shortName'],
            ),
            ReferenceName::CATEGORY_BISECT,
            ReferenceName::CATEGORY_REWRITTEN,
            ReferenceName::CATEGORY_WORKTREE_PRIVATE,
            ReferenceName::CATEGORY_PSEUDO_REF,
            ReferenceName::CATEGORY_MAIN_PSEUDO_REF,
            ReferenceName::CATEGORY_LINKED_PSEUDO_REF => null,
            default => $physicalName,
        };
    }

    private function packedTransactionPhysicalNameFromShortName(string $namespacePrefix, string $shortName): ?string
    {
        $shortCategory = ReferenceName::category($shortName);
        if ($shortCategory === null || ReferenceName::isWorktreePrivate($shortName)) {
            return null;
        }

        return $namespacePrefix . $shortName;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitNamespacePrefix(string $physicalName): array
    {
        $prefix = '';
        $relativeName = $physicalName;
        while (str_starts_with($relativeName, 'refs/namespaces/')) {
            $rest = substr($relativeName, strlen('refs/namespaces/'));
            $slash = strpos($rest, '/');
            if ($slash === false) {
                return ['', $physicalName];
            }

            $prefix .= 'refs/namespaces/' . substr($rest, 0, $slash) . '/';
            $relativeName = substr($rest, $slash + 1);
        }

        return [$prefix, $relativeName];
    }

    private function packedRefsPath(): string
    {
        return rtrim($this->gitDirectory, '/\\') . '/packed-refs';
    }

    private function packedRefsLockPath(): string
    {
        return $this->packedRefsPath() . '.lock';
    }

    private function refreshPackedReferencesIfChanged(string $algorithm): void
    {
        $snapshot = $this->packedRefsFileSnapshot();
        if ($snapshot === $this->packedRefsSnapshot) {
            return;
        }

        $this->packed = $snapshot === null ? null : PackedReferences::open($this->packedRefsPath(), $algorithm);
        $this->packedRefsSnapshot = $snapshot;
    }

    /**
     * @return ?array{mtime:int,size:int,hash:string}
     */
    private function packedRefsFileSnapshot(): ?array
    {
        $path = $this->packedRefsPath();
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return null;
        }

        return [
            'mtime' => (int) filemtime($path),
            'size' => (int) filesize($path),
            'hash' => (string) hash_file('sha1', $path),
        ];
    }

    private function referencePath(string $physicalName): string
    {
        ReferenceName::assertValid($physicalName);

        return rtrim($this->gitDirectory, '/\\') . '/' . $physicalName;
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

    private function removeEmptyDirectoryTree(string $directory): bool
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException("Unable to inspect reflog directory blocker: {$directory}");
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                if (!$this->removeEmptyDirectoryTree($path)) {
                    return false;
                }
                continue;
            }

            return false;
        }

        return @rmdir($directory) || !is_dir($directory);
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
        bool $brokenLooseExists = false,
    ): void {
        match ($previous) {
            self::PREVIOUS_ANY => null,
            self::PREVIOUS_MUST_EXIST => $existing === null && !$brokenLooseExists
                ? throw new \RuntimeException("Reference must exist before deletion: {$physicalName}")
                : null,
            self::PREVIOUS_MUST_EXIST_AND_MATCH => $brokenLooseExists
                ? throw new \RuntimeException("Reference is out of date: {$physicalName}")
                : $this->assertExpectedTargetMatches(
                $physicalName,
                $existing,
                $expectedTarget,
                true,
            ),
            self::PREVIOUS_EXISTING_MUST_MATCH => $brokenLooseExists
                ? throw new \RuntimeException("Reference is out of date: {$physicalName}")
                : $this->assertExpectedTargetMatches(
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

    private static function assertWriteReflogMode(string $mode): void
    {
        if (!in_array($mode, [
            self::WRITE_REFLOG_NORMAL,
            self::WRITE_REFLOG_ALWAYS,
            self::WRITE_REFLOG_DISABLE,
        ], true)) {
            throw new \InvalidArgumentException("Unknown reflog write mode: {$mode}");
        }
    }

    private function assertWindowsDeviceNamesAllowed(string $physicalName): void
    {
        if (!$this->prohibitWindowsDeviceNames) {
            return;
        }

        foreach (explode('/', $physicalName) as $component) {
            if (self::isWindowsDeviceNameComponent($component)) {
                throw new \RuntimeException("Illegal use of reserved Windows device name in \"{$physicalName}\"");
            }
        }
    }

    private static function isWindowsDeviceNameComponent(string $component): bool
    {
        $prefix = substr($component, 0, 3);
        if (strlen($prefix) < 3) {
            return false;
        }

        if (in_array(strtolower($prefix), ['aux', 'nul', 'prn'], true) && self::windowsDeviceNameEndsAt(substr($component, 3))) {
            return true;
        }

        if (
            strcasecmp($prefix, 'com') === 0
            && isset($component[3])
            && $component[3] >= '1'
            && $component[3] <= '9'
            && self::windowsDeviceNameEndsAt(substr($component, 4))
        ) {
            return true;
        }

        if (
            strcasecmp($prefix, 'lpt') === 0
            && isset($component[3])
            && ctype_digit($component[3])
            && self::windowsDeviceNameEndsAt(substr($component, 4))
        ) {
            return true;
        }

        if (strcasecmp($prefix, 'con') !== 0) {
            return false;
        }

        $rest = substr($component, 3);
        return self::windowsDeviceNameEndsAt($rest)
            || (strncasecmp($rest, 'in$', 3) === 0 && self::windowsDeviceNameEndsAt(substr($rest, 3)))
            || (strncasecmp($rest, 'out$', 4) === 0 && self::windowsDeviceNameEndsAt(substr($rest, 4)));
    }

    private static function windowsDeviceNameEndsAt(string $rest): bool
    {
        $rest = ltrim($rest, ' ');

        return $rest === '' || $rest[0] === '.' || $rest[0] === ':';
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
