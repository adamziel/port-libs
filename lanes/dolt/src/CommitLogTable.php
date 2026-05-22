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
     * @param array{headHash?:string|null, includeAll?:bool, projectedColumns?:list<string>, showParents?:bool, showSignature?:bool, decorate?:string, limit?:int|null} $options
     * @return list<array<string, scalar|null>>
     */
    public function logRows(array $commits, array $options = []): array
    {
        $commits = $this->normalizeCommits($commits);
        $headHash = $this->nullableString($options['headHash'] ?? null, 'headHash');
        if ($headHash !== null && !isset($commits[$headHash])) {
            throw new \RuntimeException("Dolt log head commit not found: {$headHash}");
        }

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
        $limit = $this->normalizeLimit($options['limit'] ?? null);

        $visible = null;
        if ($headHash !== null && !($options['includeAll'] ?? false)) {
            $visible = $this->reachableHashes($commits, $headHash);
        }

        $rows = [];
        foreach ($this->orderedCommits($commits, $visible) as $commit) {
            $rows[] = $this->logRow($commit, $headHash, $showParents, $showSignature, $decorate);
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
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
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
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
     * @return array<string, array{commit_hash:non-empty-string, committer:non-empty-string, email:non-empty-string, date:non-empty-string, message:string, commit_order:int, parents:list<non-empty-string>, refs:list<non-empty-string>, signature:string|null, signatureVerification:string|null, author:non-empty-string, author_email:non-empty-string, author_date:non-empty-string}>
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
}
