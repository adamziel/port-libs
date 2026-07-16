<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class BranchesTable
{
    public const BRANCHES_COLUMNS = [
        'name',
        'hash',
        'latest_committer',
        'latest_committer_email',
        'latest_commit_date',
        'latest_commit_message',
        'remote',
        'branch',
        'dirty',
        'latest_author',
        'latest_author_email',
        'latest_author_date',
    ];

    public const REMOTE_BRANCHES_COLUMNS = [
        'name',
        'hash',
        'latest_committer',
        'latest_committer_email',
        'latest_commit_date',
        'latest_commit_message',
        'latest_author',
        'latest_author_email',
        'latest_author_date',
    ];

    public const BRANCH_ACTIVITY_COLUMNS = [
        'branch',
        'last_read',
        'last_write',
        'active_sessions',
        'system_start_time',
    ];

    /**
     * Project upstream `dolt_branches` or `dolt_remote_branches` rows.
     *
     * @param list<array<string, mixed>> $branches
     * @param array{remote?:bool, name?:string|null, lowerBound?:string|null, lowerInclusive?:bool, upperBound?:string|null, upperInclusive?:bool} $options
     * @return list<array<string, scalar|null>>
     */
    public function rows(array $branches, array $options = []): array
    {
        $remote = (bool) ($options['remote'] ?? false);
        $exactName = $this->nullableString($options['name'] ?? null, 'Dolt branch name filter');
        $lowerBound = $this->nullableString($options['lowerBound'] ?? null, 'Dolt branch lower bound');
        $upperBound = $this->nullableString($options['upperBound'] ?? null, 'Dolt branch upper bound');
        $lowerInclusive = (bool) ($options['lowerInclusive'] ?? false);
        $upperInclusive = (bool) ($options['upperInclusive'] ?? false);

        $rows = [];
        foreach ($branches as $i => $branch) {
            $row = $this->normalizeBranchRow($branch, $i, $remote);
            $name = $row['name'];
            if ($exactName !== null && $name !== $exactName) {
                continue;
            }
            if ($this->outOfLowerBound($name, $lowerBound, $lowerInclusive)) {
                continue;
            }
            if ($this->outOfUpperBound($name, $upperBound, $upperInclusive)) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $branches
     * @param array{name?:string|null, lowerBound?:string|null, lowerInclusive?:bool, upperBound?:string|null, upperInclusive?:bool} $options
     * @return list<array<string, scalar|null>>
     */
    public function remoteRows(array $branches, array $options = []): array
    {
        $options['remote'] = true;

        return $this->rows($branches, $options);
    }

    /**
     * Native projection of upstream `active_branch()`.
     *
     * Detached heads, non-Dolt database contexts, and missing branch refs return
     * null; matching branch names are resolved case-insensitively.
     *
     * @param list<array<string, mixed>|string> $branches
     */
    public function activeBranch(array $branches, ?string $currentBranch, bool $detachedHead = false): ?string
    {
        if ($detachedHead || $currentBranch === null || $currentBranch === '') {
            return null;
        }

        $wanted = $this->normalizeBranchName($currentBranch, false);
        foreach ($this->branchNames($branches) as $branch) {
            if (strcasecmp($branch, $wanted) === 0) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * Project upstream `dolt_branch_activity` rows.
     *
     * @param list<array<string, mixed>|string> $branches Current branch refs; deleted branches are excluded.
     * @param list<array<string, mixed>> $activity
     * @param array{trackingEnabled?:bool, systemStartTime?:string|null, activeSessions?:array<string,int>} $options
     * @return list<array{branch:string, last_read:string|null, last_write:string|null, active_sessions:int, system_start_time:string}>
     */
    public function activityRows(array $branches, array $activity, array $options = []): array
    {
        if (($options['trackingEnabled'] ?? true) === false) {
            throw new \RuntimeException("branch activity tracking is not enabled; enable it in the server config with 'behavior.branch_activity_tracking: true'");
        }

        $branchNames = $this->branchNames($branches);
        $systemStartTime = $this->nullableString($options['systemStartTime'] ?? null, 'Dolt branch activity system_start_time');
        $activeSessions = $this->normalizeActiveSessions($options['activeSessions'] ?? []);
        $activityByBranch = [];
        foreach ($activity as $i => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("Dolt branch activity {$i} must be an array.");
            }

            $branch = $this->normalizeBranchName(
                $this->requiredString($row['branch'] ?? null, "Dolt branch activity {$i} branch"),
                false
            );
            if ($branch === 'HEAD') {
                continue;
            }

            $rowSystemStart = $this->nullableString($row['system_start_time'] ?? null, "Dolt branch activity {$branch} system_start_time");
            $systemStartTime ??= $rowSystemStart;
            $activityByBranch[$branch] = [
                'last_read' => $this->nullableString($row['last_read'] ?? null, "Dolt branch activity {$branch} last_read"),
                'last_write' => $this->nullableString($row['last_write'] ?? null, "Dolt branch activity {$branch} last_write"),
                'active_sessions' => $this->normalizeActiveSessionCount($row['active_sessions'] ?? null, "Dolt branch activity {$branch} active_sessions"),
                'system_start_time' => $rowSystemStart,
            ];
        }

        if ($systemStartTime === null) {
            throw new \InvalidArgumentException('Dolt branch activity system_start_time is required.');
        }

        $rows = [];
        foreach ($branchNames as $branch) {
            $activityRow = $activityByBranch[$branch] ?? null;
            $rows[] = [
                'branch' => $branch,
                'last_read' => $activityRow['last_read'] ?? null,
                'last_write' => $activityRow['last_write'] ?? null,
                'active_sessions' => $activeSessions[$branch] ?? $activityRow['active_sessions'] ?? 0,
                'system_start_time' => $activityRow['system_start_time'] ?? $systemStartTime,
            ];
        }

        return $rows;
    }

    /**
     * @param mixed $branch
     * @return array<string, scalar|null>
     */
    private function normalizeBranchRow($branch, int $index, bool $remote): array
    {
        if (!is_array($branch)) {
            throw new \InvalidArgumentException("Dolt branch {$index} must be an array.");
        }

        $name = $this->normalizeBranchName(
            $this->requiredString($branch['name'] ?? null, "Dolt branch {$index} name"),
            $remote
        );
        $hash = $this->requiredString($branch['hash'] ?? $branch['commit_hash'] ?? null, "Dolt branch {$name} hash");

        $latestAuthor = $this->nullableString($branch['latest_author'] ?? $branch['author'] ?? null, "Dolt branch {$name} latest_author");
        $latestAuthorEmail = $this->nullableString($branch['latest_author_email'] ?? $branch['author_email'] ?? null, "Dolt branch {$name} latest_author_email");
        $latestCommitter = $this->nullableString($branch['latest_committer'] ?? $branch['committer'] ?? null, "Dolt branch {$name} latest_committer") ?? $latestAuthor;
        $latestCommitterEmail = $this->nullableString($branch['latest_committer_email'] ?? $branch['email'] ?? null, "Dolt branch {$name} latest_committer_email") ?? $latestAuthorEmail;
        $latestAuthor ??= $latestCommitter;
        $latestAuthorEmail ??= $latestCommitterEmail;

        $row = [
            'name' => $name,
            'hash' => $hash,
            'latest_committer' => $latestCommitter,
            'latest_committer_email' => $latestCommitterEmail,
            'latest_commit_date' => $this->nullableString($branch['latest_commit_date'] ?? $branch['date'] ?? null, "Dolt branch {$name} latest_commit_date"),
            'latest_commit_message' => $this->nullableString($branch['latest_commit_message'] ?? $branch['message'] ?? null, "Dolt branch {$name} latest_commit_message"),
        ];

        if (!$remote) {
            $row['remote'] = $this->nullableString($branch['remote'] ?? null, "Dolt branch {$name} remote") ?? '';
            $row['branch'] = $this->nullableString($branch['branch'] ?? null, "Dolt branch {$name} branch") ?? '';
            $row['dirty'] = $this->normalizeDirty($branch['dirty'] ?? false, $name);
        }

        $row['latest_author'] = $latestAuthor;
        $row['latest_author_email'] = $latestAuthorEmail;
        $row['latest_author_date'] = $this->nullableString($branch['latest_author_date'] ?? $branch['author_date'] ?? $branch['latest_commit_date'] ?? $branch['date'] ?? null, "Dolt branch {$name} latest_author_date");

        return $row;
    }

    /**
     * @param list<array<string, mixed>|string> $branches
     * @return list<non-empty-string>
     */
    private function branchNames(array $branches): array
    {
        $names = [];
        foreach ($branches as $i => $branch) {
            if (is_string($branch)) {
                $name = $this->normalizeBranchName($branch, false);
            } elseif (is_array($branch)) {
                $name = $this->normalizeBranchName(
                    $this->requiredString($branch['name'] ?? null, "Dolt branch {$i} name"),
                    false
                );
            } else {
                throw new \InvalidArgumentException("Dolt branch {$i} must be an array or string.");
            }
            if ($name === 'HEAD') {
                continue;
            }
            $names[$name] = true;
        }

        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    private function normalizeBranchName(string $name, bool $remote): string
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Dolt branch name must be a non-empty string.');
        }

        if (str_starts_with($name, 'refs/heads/')) {
            $name = substr($name, strlen('refs/heads/'));
        } elseif (str_starts_with($name, 'refs/remotes/')) {
            $name = substr($name, strlen('refs/remotes/'));
        } elseif (str_starts_with($name, 'remotes/')) {
            $name = substr($name, strlen('remotes/'));
        }

        if ($remote) {
            return 'remotes/' . $name;
        }

        return $name;
    }

    private function outOfLowerBound(string $name, ?string $lowerBound, bool $inclusive): bool
    {
        if ($lowerBound === null) {
            return false;
        }

        $cmp = strcmp($name, $lowerBound);

        return $inclusive ? $cmp < 0 : $cmp <= 0;
    }

    private function outOfUpperBound(string $name, ?string $upperBound, bool $inclusive): bool
    {
        if ($upperBound === null) {
            return false;
        }

        $cmp = strcmp($name, $upperBound);

        return $inclusive ? $cmp > 0 : $cmp >= 0;
    }

    /**
     * @param mixed $value
     */
    private function normalizeDirty($value, string $branchName): bool
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Dolt branch {$branchName} dirty must be a boolean.");
        }

        return $value;
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
     * @return array<string, int>
     */
    private function normalizeActiveSessions($value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Dolt branch activeSessions must be a map of branch names to counts.');
        }

        $normalized = [];
        foreach ($value as $branch => $count) {
            if (!is_string($branch) || $branch === '') {
                throw new \InvalidArgumentException('Dolt branch activeSessions keys must be non-empty strings.');
            }
            $normalized[$this->normalizeBranchName($branch, false)] = $this->normalizeActiveSessionCount($count, "Dolt branch {$branch} active_sessions");
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeActiveSessionCount($value, string $label): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("{$label} must be a non-negative integer.");
        }

        return $value;
    }
}
