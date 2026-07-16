<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CommitLogTable
{
    public const LOG_COLUMNS = [
        'commit_hash',
        'committer',
        'email',
        'date',
        'message',
        'commit_order',
        'parents',
        'refs',
        'signature',
        'author',
        'author_email',
        'author_date',
    ];

    public const COMMITS_COLUMNS = [
        'commit_hash',
        'committer',
        'email',
        'date',
        'message',
        'author',
        'author_email',
        'author_date',
    ];

    /**
     * Project upstream `dolt_log` / `dolt_log()` row metadata.
     *
     * The row schema is fixed. The `parents` and `signature` columns stay null
     * unless selected through `projectedColumns` or explicit show flags, matching
     * upstream's opt-in cost boundary.
     *
     * @param list<array<string, mixed>> $commits
     * @param array{headHash?:string|null, includeAll?:bool, all?:bool, revisionSpecs?:list<string>, revisions?:list<string>, notRevisionSpecs?:list<string>, notRevisions?:list<string>, tableNames?:list<string>, tables?:list<string>, projectedColumns?:list<string>, showParents?:bool, showSignature?:bool, decorate?:string, limit?:int|null, number?:int|null, n?:int|null, minParents?:int, min_parents?:int, merges?:bool} $options
     * @return list<array<string, scalar|null>>
     */
    public function logRows(array $commits, array $options = []): array
    {
        $commits = $this->normalizeCommits($commits);
        $headHash = $this->nullableString($options['headHash'] ?? null, 'headHash');
        if ($headHash !== null && !isset($commits[$headHash])) {
            throw new \RuntimeException("Dolt log head commit not found: {$headHash}");
        }

        $includeAll = $this->normalizeIncludeAll($options);
        $projected = $options['projectedColumns'] ?? null;
        if ($projected !== null) {
            $projected = $this->normalizeProjectedColumns($projected);
        }

        $showParents = $projected !== null
            ? in_array('parents', $projected, true)
            : (bool) ($options['showParents'] ?? false);
        $showSignature = $projected !== null
            ? in_array('signature', $projected, true)
            : (bool) ($options['showSignature'] ?? false);
        $decorate = $this->normalizeDecoration($options['decorate'] ?? 'short');
        $limit = $this->normalizeLimitOption($options);
        $minParents = $this->normalizeMinParents($options);
        if (array_key_exists('merges', $options)) {
            if (!is_bool($options['merges'])) {
                throw new \InvalidArgumentException('Dolt log merges must be a boolean.');
            }
            if ($options['merges']) {
                $minParents = 2;
            }
        }
        $tableNames = $this->normalizeOptionalStringList(
            $options['tableNames'] ?? $options['tables'] ?? null,
            'Dolt log tableNames',
        );
        if ($tableNames === []) {
            throw new \InvalidArgumentException('Dolt log tableNames must contain at least one table name.');
        }
        $revisionSpecs = $this->normalizeOptionalStringList(
            $options['revisionSpecs'] ?? $options['revisions'] ?? null,
            'Dolt log revisionSpecs',
        );
        $notRevisionSpecs = $this->normalizeOptionalStringList(
            $options['notRevisionSpecs'] ?? $options['notRevisions'] ?? null,
            'Dolt log notRevisionSpecs',
        ) ?? [];

        $visible = null;
        $logHeadHash = $headHash;
        if ($includeAll) {
            $allBranchHeads = $this->allBranchHeadHashes($commits, $headHash);
            $revisionSpecs = array_values(array_unique(array_merge($revisionSpecs ?? [], $allBranchHeads)));
        }

        if ($revisionSpecs !== null || $notRevisionSpecs !== []) {
            [$visible, $logHeadHash] = $this->visibleHashesForRevisionSpecs(
                $commits,
                $revisionSpecs ?? [],
                $notRevisionSpecs,
                $headHash,
            );
        } elseif ($headHash !== null) {
            $visible = $this->reachableHashes($commits, $headHash);
        }

        $rows = [];
        foreach ($this->orderedCommits($commits, $visible) as $commit) {
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
            if (count($commit['parents']) < $minParents) {
                continue;
            }
            if ($tableNames !== null && !$this->commitChangedAnyTable($commit, $commits, $tableNames)) {
                continue;
            }
            $rows[] = $this->logRow($commit, $logHeadHash, $showParents, $showSignature, $decorate);
        }

        return $rows;
    }

    /**
     * Project upstream `dolt_commits` rows, which expose all supplied branch
     * commits and omit log-only columns such as commit_order, parents, refs, and
     * signature.
     *
     * @param list<array<string, mixed>> $commits
     * @return list<array<string, scalar|null>>
     */
    public function commitsRows(array $commits, ?int $limit = null): array
    {
        $commits = $this->normalizeCommits($commits);
        $limit = $this->normalizeLimit($limit);

        $rows = [];
        foreach ($this->orderedCommits($commits) as $commit) {
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
            $rows[] = [
                'commit_hash' => $commit['commit_hash'],
                'committer' => $commit['committer'],
                'email' => $commit['email'],
                'date' => $commit['date'],
                'message' => $commit['message'],
                'author' => $commit['author'],
                'author_email' => $commit['author_email'],
                'author_date' => $commit['author_date'],
            ];
        }

        return $rows;
    }

    /**
     * Render upstream-shaped `dolt log` CLI text for bounded native scenarios.
     *
     * `logRows()` remains the semantic source for revision, table, and parent
     * filtering. The renderer then applies CLI-only output choices such as
     * `--oneline`, `--parents`, and `--stat`.
     *
     * @param list<array<string, mixed>> $commits
     * @param array{headHash?:string|null, includeAll?:bool, all?:bool, revisionSpecs?:list<string>, revisions?:list<string>, notRevisionSpecs?:list<string>, notRevisions?:list<string>, tableNames?:list<string>, tables?:list<string>, projectedColumns?:list<string>, showParents?:bool, showSignature?:bool, decorate?:string, stdoutIsTty?:bool, limit?:int|null, number?:int|null, n?:int|null, minParents?:int, min_parents?:int, merges?:bool, graph?:bool, oneline?:bool, parents?:bool, stat?:bool, diffStats?:array<string, list<array<string, mixed>>>, diffStatsByCommit?:array<string, list<array<string, mixed>>>} $options
     */
    public function renderLog(array $commits, array $options = []): string
    {
        $logOptions = $options;
        $graph = $options['graph'] ?? false;
        if (!is_bool($graph)) {
            throw new \InvalidArgumentException('Dolt log graph option must be a boolean.');
        }
        if (($options['decorate'] ?? null) === 'auto') {
            $stdoutIsTty = $options['stdoutIsTty'] ?? false;
            if (!is_bool($stdoutIsTty)) {
                throw new \InvalidArgumentException('Dolt log stdoutIsTty option must be a boolean.');
            }
            $logOptions['decorate'] = $stdoutIsTty ? 'short' : 'no';
        }
        unset(
            $logOptions['graph'],
            $logOptions['oneline'],
            $logOptions['parents'],
            $logOptions['stat'],
            $logOptions['stdoutIsTty'],
            $logOptions['diffStats'],
            $logOptions['diffStatsByCommit']
        );

        if (($options['parents'] ?? false) || ($options['stat'] ?? false) || $graph) {
            $logOptions['showParents'] = true;
        }

        return (new CommitLogRenderer())->render($this->logRows($commits, $logOptions), $options);
    }

    /**
     * @param array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string} $commit
     * @return array<string, scalar|null>
     */
    private function logRow(
        array $commit,
        ?string $headHash,
        bool $showParents,
        bool $showSignature,
        string $decorate,
    ): array {
        return [
            'commit_hash' => $commit['commit_hash'],
            'committer' => $commit['committer'],
            'email' => $commit['email'],
            'date' => $commit['date'],
            'message' => $commit['message'],
            'commit_order' => $commit['commit_order'],
            'parents' => $showParents ? implode(', ', $commit['parents']) : null,
            'refs' => $this->formatRefs($commit, $headHash, $decorate),
            'signature' => $showSignature ? ($commit['signatureVerification'] ?? $commit['signature'] ?? '') : null,
            'author' => $commit['author'],
            'author_email' => $commit['author_email'],
            'author_date' => $commit['author_date'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $commits
     * @return array<string, array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string, changedTables:list<non-empty-string>|null, tableHashes:array<string, non-empty-string>|null}>
     */
    private function normalizeCommits(array $commits): array
    {
        $normalized = [];
        foreach ($commits as $i => $commit) {
            if (!is_array($commit)) {
                throw new \InvalidArgumentException("Dolt commit {$i} must be an array.");
            }

            $hash = $this->requiredString($commit['commit_hash'] ?? null, "Dolt commit {$i} commit_hash");
            if (isset($normalized[$hash])) {
                throw new \InvalidArgumentException("Duplicate Dolt commit hash: {$hash}");
            }

            $author = $this->nullableString($commit['author'] ?? null, "Dolt commit {$hash} author");
            $authorEmail = $this->nullableString($commit['author_email'] ?? null, "Dolt commit {$hash} author_email");
            $committer = $this->nullableString($commit['committer'] ?? null, "Dolt commit {$hash} committer") ?? $author;
            $email = $this->nullableString($commit['email'] ?? null, "Dolt commit {$hash} email") ?? $authorEmail;
            $author ??= $committer;
            $authorEmail ??= $email;

            if ($committer === null || $email === null || $author === null || $authorEmail === null) {
                throw new \InvalidArgumentException("Dolt commit {$hash} requires committer/email or author/author_email metadata.");
            }

            $date = $this->requiredString($commit['date'] ?? null, "Dolt commit {$hash} date");
            $authorDate = $this->nullableString($commit['author_date'] ?? null, "Dolt commit {$hash} author_date") ?? $date;
            $message = $commit['message'] ?? '';
            if (!is_string($message)) {
                throw new \InvalidArgumentException("Dolt commit {$hash} message must be a string.");
            }

            $explicitOrder = $commit['commit_order'] ?? null;
            if ($explicitOrder !== null && (!is_int($explicitOrder) || $explicitOrder < 0)) {
                throw new \InvalidArgumentException("Dolt commit {$hash} commit_order must be a non-negative integer.");
            }

            $normalized[$hash] = [
                'commit_hash' => $hash,
                'committer' => $committer,
                'email' => $email,
                'date' => $date,
                'message' => $message,
                'commit_order' => $explicitOrder ?? -1,
                'parents' => $this->normalizeStringList($commit['parents'] ?? [], "Dolt commit {$hash} parents"),
                'refs' => $this->normalizeStringList($commit['refs'] ?? [], "Dolt commit {$hash} refs"),
                'signature' => $this->nullableString($commit['signature'] ?? null, "Dolt commit {$hash} signature"),
                'signatureVerification' => $this->nullableString($commit['signatureVerification'] ?? null, "Dolt commit {$hash} signatureVerification"),
                'author' => $author,
                'author_email' => $authorEmail,
                'author_date' => $authorDate,
                'changedTables' => $this->normalizeOptionalStringList(
                    $commit['changedTables'] ?? $commit['changed_tables'] ?? null,
                    "Dolt commit {$hash} changedTables",
                ),
                'tableHashes' => $this->normalizeTableHashes(
                    $commit['tableHashes'] ?? $commit['table_hashes'] ?? null,
                    "Dolt commit {$hash} tableHashes",
                ),
            ];
        }

        foreach (array_keys($normalized) as $hash) {
            $this->commitOrder($hash, $normalized, []);
        }

        return $normalized;
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string}> $commits
     * @param array<string, true> $visible
     * @return list<array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string}>
     */
    private function orderedCommits(array $commits, ?array $visible = null): array
    {
        $ordered = [];
        foreach ($commits as $hash => $commit) {
            if ($visible !== null && !isset($visible[$hash])) {
                continue;
            }
            $ordered[] = $commit;
        }

        usort($ordered, static function (array $a, array $b): int {
            return [$b['commit_order'], $b['date'], $b['commit_hash']]
                <=> [$a['commit_order'], $a['date'], $a['commit_hash']];
        });

        return $ordered;
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string}> $commits
     * @param array<string, true> $visiting
     */
    private function commitOrder(string $hash, array &$commits, array $visiting): int
    {
        if (!isset($commits[$hash])) {
            throw new \RuntimeException("Dolt commit parent not found: {$hash}");
        }
        if ($commits[$hash]['commit_order'] >= 0) {
            return $commits[$hash]['commit_order'];
        }
        if (isset($visiting[$hash])) {
            throw new \RuntimeException("Dolt commit graph contains a cycle at {$hash}.");
        }

        $visiting[$hash] = true;
        $maxParentOrder = 0;
        foreach ($commits[$hash]['parents'] as $parent) {
            $maxParentOrder = max($maxParentOrder, $this->commitOrder($parent, $commits, $visiting));
        }

        $commits[$hash]['commit_order'] = $maxParentOrder + 1;

        return $commits[$hash]['commit_order'];
    }

    /**
     * @param array<string, array{parents:list<non-empty-string>}> $commits
     * @return array<string, true>
     */
    private function reachableHashes(array $commits, string $headHash): array
    {
        $reachable = [];
        $stack = [$headHash];
        while ($stack !== []) {
            $hash = array_pop($stack);
            if (isset($reachable[$hash])) {
                continue;
            }
            if (!isset($commits[$hash])) {
                throw new \RuntimeException("Dolt commit parent not found: {$hash}");
            }
            $reachable[$hash] = true;
            foreach ($commits[$hash]['parents'] as $parent) {
                $stack[] = $parent;
            }
        }

        return $reachable;
    }

    /**
     * @param array<string, array{refs:list<non-empty-string>}> $commits
     * @return list<non-empty-string>
     */
    private function allBranchHeadHashes(array $commits, ?string $headHash): array
    {
        $heads = [];
        if ($headHash !== null) {
            $heads[$headHash] = true;
        }

        foreach ($commits as $hash => $commit) {
            foreach ($commit['refs'] as $ref) {
                if ($this->isBranchRef($ref)) {
                    $heads[$hash] = true;
                    break;
                }
            }
        }

        return array_keys($heads);
    }

    private function isBranchRef(string $ref): bool
    {
        return str_starts_with($ref, 'refs/heads/')
            || str_starts_with($ref, 'refs/remotes/')
            || str_starts_with($ref, 'remotes/');
    }

    /**
     * @param array{commit_hash:non-empty-string, parents:list<non-empty-string>, changedTables:list<non-empty-string>|null, tableHashes:array<string, non-empty-string>|null} $commit
     * @param array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, changedTables:list<non-empty-string>|null, tableHashes:array<string, non-empty-string>|null}> $commits
     * @param list<non-empty-string> $tableNames
     */
    private function commitChangedAnyTable(array $commit, array $commits, array $tableNames): bool
    {
        if ($commit['parents'] === []) {
            return false;
        }

        foreach ($tableNames as $tableName) {
            if ($this->commitChangedTable($commit, $commits, $tableName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{commit_hash:non-empty-string, parents:list<non-empty-string>, changedTables:list<non-empty-string>|null, tableHashes:array<string, non-empty-string>|null} $commit
     * @param array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, changedTables:list<non-empty-string>|null, tableHashes:array<string, non-empty-string>|null}> $commits
     */
    private function commitChangedTable(array $commit, array $commits, string $tableName): bool
    {
        if ($commit['tableHashes'] !== null) {
            return $this->tableHashChangedAgainstParents($commit, $commits, $tableName);
        }

        if ($commit['changedTables'] === null) {
            throw new \RuntimeException("Dolt commit {$commit['commit_hash']} requires changedTables or tableHashes for tableNames filtering.");
        }

        return in_array($tableName, $commit['changedTables'], true);
    }

    /**
     * @param array{commit_hash:non-empty-string, parents:list<non-empty-string>, tableHashes:array<string, non-empty-string>|null} $commit
     * @param array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, tableHashes:array<string, non-empty-string>|null}> $commits
     */
    private function tableHashChangedAgainstParents(array $commit, array $commits, string $tableName): bool
    {
        $parent0 = $commits[$commit['parents'][0]] ?? null;
        if ($parent0 === null) {
            throw new \RuntimeException("Dolt commit parent not found: {$commit['parents'][0]}");
        }
        if ($parent0['tableHashes'] === null || $commit['tableHashes'] === null) {
            throw new \RuntimeException("Dolt commit {$commit['commit_hash']} requires tableHashes on itself and parents for tableNames filtering.");
        }

        $childHas = array_key_exists($tableName, $commit['tableHashes']);
        $parent0Has = array_key_exists($tableName, $parent0['tableHashes']);
        $childHash = $childHas ? $commit['tableHashes'][$tableName] : null;
        $parent0Hash = $parent0Has ? $parent0['tableHashes'][$tableName] : null;

        $parent1 = null;
        if (isset($commit['parents'][1])) {
            $parent1 = $commits[$commit['parents'][1]] ?? null;
            if ($parent1 === null) {
                throw new \RuntimeException("Dolt commit parent not found: {$commit['parents'][1]}");
            }
            if ($parent1['tableHashes'] === null) {
                throw new \RuntimeException("Dolt commit {$commit['commit_hash']} requires tableHashes on itself and parents for tableNames filtering.");
            }
        }

        if ($parent1 === null) {
            if (!$childHas && !$parent0Has) {
                return false;
            }
            if (!$childHas || !$parent0Has) {
                return true;
            }

            return $childHash !== $parent0Hash;
        }

        $parent1Has = array_key_exists($tableName, $parent1['tableHashes']);
        $parent1Hash = $parent1Has ? $parent1['tableHashes'][$tableName] : null;

        if (!$childHas && !$parent0Has && !$parent1Has) {
            return false;
        }
        if (!$childHas) {
            return true;
        }
        if (!$parent0Has && !$parent1Has) {
            return true;
        }
        if (!$parent0Has) {
            return $childHash !== $parent1Hash;
        }
        if (!$parent1Has) {
            return $childHash !== $parent0Hash;
        }

        return $childHash !== $parent0Hash || $childHash !== $parent1Hash;
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string}> $commits
     * @param list<non-empty-string> $revisionSpecs
     * @param list<non-empty-string> $notRevisionSpecs
     * @return array{0:array<string, true>, 1:string|null}
     */
    private function visibleHashesForRevisionSpecs(
        array $commits,
        array $revisionSpecs,
        array $notRevisionSpecs,
        ?string $headHash,
    ): array {
        [$includeSpecs, $excludeSpecs, $threeDot] = $this->evaluateRevisionSpecs($revisionSpecs, $notRevisionSpecs);
        if ($includeSpecs === []) {
            if ($headHash === null) {
                throw new \InvalidArgumentException('Dolt log revision exclusions require a non-empty headHash.');
            }
            $includeSpecs = ['HEAD'];
        }

        $includeHashes = [];
        foreach ($includeSpecs as $spec) {
            $includeHashes[] = $this->resolveCommitSpec($commits, $spec, $headHash);
        }

        $excludeHashes = [];
        foreach ($excludeSpecs as $spec) {
            $excludeHashes[] = $this->resolveCommitSpec($commits, $spec, $headHash);
        }

        if ($threeDot) {
            if (count($includeHashes) !== 2) {
                throw new \InvalidArgumentException('Dolt log three-dot revisions require exactly two refs.');
            }
            $excludeHashes[] = $this->mergeBaseHash($commits, $includeHashes[0], $includeHashes[1]);
        }

        $visible = [];
        foreach ($includeHashes as $hash) {
            $visible += $this->reachableHashes($commits, $hash);
        }
        foreach ($excludeHashes as $hash) {
            foreach (array_keys($this->reachableHashes($commits, $hash)) as $excluded) {
                unset($visible[$excluded]);
            }
        }

        $logHeadHash = count($includeHashes) === 1 ? $includeHashes[0] : null;

        return [$visible, $logHeadHash];
    }

    /**
     * Map upstream `dolt_log()` revision arguments to include/exclude heads.
     *
     * @param list<non-empty-string> $revisionSpecs
     * @param list<non-empty-string> $notRevisionSpecs
     * @return array{0:list<non-empty-string>, 1:list<non-empty-string>, 2:bool}
     */
    private function evaluateRevisionSpecs(array $revisionSpecs, array $notRevisionSpecs): array
    {
        $includes = [];
        $excludes = [];
        foreach ($revisionSpecs as $spec) {
            if (str_starts_with($spec, '^')) {
                $negative = substr($spec, 1);
                if ($negative === '') {
                    throw new \InvalidArgumentException('Dolt log exclusion revision must be non-empty.');
                }
                $excludes[] = $negative;
                continue;
            }

            $includes[] = $spec;
        }

        foreach ($notRevisionSpecs as $spec) {
            if (str_starts_with($spec, '^')) {
                throw new \InvalidArgumentException("Dolt log --not revision cannot contain '^'.");
            }
            $excludes[] = $spec;
        }

        $rangeSpecs = array_values(array_filter(
            $includes,
            static fn (string $spec): bool => str_contains($spec, '..'),
        ));
        if ($rangeSpecs !== []) {
            if (count($includes) > 1 || $excludes !== []) {
                throw new \InvalidArgumentException("Dolt log revision cannot contain '..' or '...' if multiple revisions exist.");
            }

            return $this->splitRevisionRange($rangeSpecs[0]);
        }

        foreach ($excludes as $spec) {
            if (str_contains($spec, '..')) {
                throw new \InvalidArgumentException("Dolt log --not revision cannot contain '..'.");
            }
        }

        return [$includes, $excludes, false];
    }

    /**
     * @return array{0:list<non-empty-string>, 1:list<non-empty-string>, 2:bool}
     */
    private function splitRevisionRange(string $spec): array
    {
        if (str_contains($spec, '...')) {
            $parts = explode('...', $spec);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                throw new \InvalidArgumentException('Dolt log three-dot revision range requires two non-empty refs.');
            }

            return [[$parts[0], $parts[1]], [], true];
        }

        $parts = explode('..', $spec);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('Dolt log two-dot revision range requires two non-empty refs.');
        }

        return [[$parts[1]], [$parts[0]], false];
    }

    /**
     * @param array<string, array{parents:list<non-empty-string>, commit_order:int, date:non-empty-string, commit_hash:non-empty-string}> $commits
     */
    private function mergeBaseHash(array $commits, string $leftHash, string $rightHash): string
    {
        $common = array_intersect_key(
            $this->reachableHashes($commits, $leftHash),
            $this->reachableHashes($commits, $rightHash),
        );
        if ($common === []) {
            throw new \RuntimeException("Dolt commits {$leftHash} and {$rightHash} do not have a common ancestor.");
        }

        return $this->orderedCommits($commits, $common)[0]['commit_hash'];
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, refs:list<non-empty-string>}> $commits
     */
    private function resolveCommitSpec(array $commits, string $spec, ?string $headHash): string
    {
        return (new CommitGraph())->resolve(array_values($commits), $spec, $headHash);
    }

    /**
     * @param array{commit_hash:non-empty-string, refs:list<non-empty-string>} $commit
     */
    private function formatRefs(array $commit, ?string $headHash, string $decorate): string
    {
        if ($decorate === 'no') {
            return '';
        }

        $refs = [];
        foreach ($commit['refs'] as $ref) {
            $refs[] = $this->formatRefName($ref, $decorate);
        }
        if ($refs === []) {
            return '';
        }

        $prefix = $headHash === $commit['commit_hash'] ? 'HEAD -> ' : '';

        return $prefix . implode(', ', $refs);
    }

    private function formatRefName(string $ref, string $decorate): string
    {
        $isTag = str_starts_with($ref, 'refs/tags/') || str_starts_with($ref, 'tag: ');
        if (str_starts_with($ref, 'tag: ')) {
            $ref = substr($ref, 5);
        }

        if ($decorate !== 'full') {
            foreach (['refs/heads/', 'refs/remotes/', 'refs/tags/'] as $prefix) {
                if (str_starts_with($ref, $prefix)) {
                    $ref = substr($ref, strlen($prefix));
                    break;
                }
            }
        }

        return $isTag ? 'tag: ' . $ref : $ref;
    }

    /**
     * @param mixed $value
     */
    private function requiredString($value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$label} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function nullableString($value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$label} must be a non-empty string or null.");
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return list<non-empty-string>
     */
    private function normalizeStringList($value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("{$label} must be a list of strings.");
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("{$label} must contain only non-empty strings.");
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeProjectedColumns($value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Dolt log projectedColumns must be a list of strings.');
        }

        $columns = [];
        foreach ($value as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Dolt log projectedColumns must contain non-empty strings.');
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param mixed $value
     * @return list<non-empty-string>|null
     */
    private function normalizeOptionalStringList($value, string $label): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException("{$label} must be a list of strings.");
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("{$label} must contain only non-empty strings.");
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeDecoration($value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Dolt log decoration must be short, full, no, or auto.');
        }
        if ($value === 'auto') {
            return 'short';
        }
        if (!in_array($value, ['short', 'full', 'no'], true)) {
            throw new \InvalidArgumentException('Dolt log decoration must be short, full, no, or auto.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function normalizeLimit($value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('Dolt log limit must be a non-negative integer.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function normalizeLimitOption(array $options): ?int
    {
        $limits = [];
        foreach (['limit', 'number', 'n'] as $name) {
            if (!array_key_exists($name, $options) || $options[$name] === null) {
                continue;
            }
            $limits[$name] = $this->normalizeLimit($options[$name]);
        }

        if ($limits === []) {
            return null;
        }

        $unique = array_unique(array_values($limits), SORT_REGULAR);
        if (count($unique) > 1) {
            throw new \InvalidArgumentException('Dolt log limit, number, and n options must agree.');
        }

        return $unique[0];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function normalizeIncludeAll(array $options): bool
    {
        $includeAll = $options['includeAll'] ?? false;
        $all = $options['all'] ?? false;
        if (!is_bool($includeAll) || !is_bool($all)) {
            throw new \InvalidArgumentException('Dolt log includeAll must be a boolean.');
        }
        if (array_key_exists('includeAll', $options) && array_key_exists('all', $options) && $includeAll !== $all) {
            throw new \InvalidArgumentException('Dolt log includeAll and all options must agree.');
        }

        return $includeAll || $all;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function normalizeMinParents(array $options): int
    {
        $hasCamel = array_key_exists('minParents', $options);
        $hasSnake = array_key_exists('min_parents', $options);
        if (!$hasCamel && !$hasSnake) {
            return 0;
        }

        $value = $hasCamel ? $options['minParents'] : $options['min_parents'];
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('Dolt log minParents must be a non-negative integer.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<string, non-empty-string>|null
     */
    private function normalizeTableHashes($value, string $label): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException("{$label} must be a map of table names to hashes.");
        }

        $normalized = [];
        foreach ($value as $tableName => $hash) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException("{$label} keys must be non-empty table-name strings.");
            }
            if (!is_string($hash) || $hash === '') {
                throw new \InvalidArgumentException("{$label} values must be non-empty strings.");
            }
            $normalized[$tableName] = $hash;
        }

        return $normalized;
    }
}
