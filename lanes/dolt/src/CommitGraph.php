<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CommitGraph
{
    /**
     * Native projection of upstream `has_ancestor(reference, ancestor)`.
     *
     * `$headHash` supplies the current-branch `HEAD` context used when either
     * commit spec is `HEAD` or an ancestor suffix is applied to `HEAD`.
     *
     * @param list<array<string, mixed>> $commits
     */
    public function hasAncestor(array $commits, string $reference, string $ancestor, ?string $headHash = null): bool
    {
        $normalized = $this->normalizeCommits($commits);
        $refHash = $this->resolveCommitHash($normalized, $reference, $headHash);
        $ancestorHash = $this->resolveCommitHash($normalized, $ancestor, $headHash);

        if ($refHash === $ancestorHash) {
            return true;
        }

        $stack = $normalized[$refHash]['parents'];
        $seen = [];
        while ($stack !== []) {
            $hash = array_pop($stack);
            if (isset($seen[$hash])) {
                continue;
            }
            if (!isset($normalized[$hash])) {
                throw new \RuntimeException("Dolt commit parent not found: {$hash}");
            }
            if ($hash === $ancestorHash) {
                return true;
            }

            $seen[$hash] = true;
            foreach ($normalized[$hash]['parents'] as $parent) {
                $stack[] = $parent;
            }
        }

        return false;
    }

    /**
     * Resolve a Dolt commit spec by hash, ref, `HEAD`, or an ancestor suffix.
     *
     * @param list<array<string, mixed>> $commits
     */
    public function resolve(array $commits, string $spec, ?string $headHash = null): string
    {
        return $this->resolveCommitHash($this->normalizeCommits($commits), $spec, $headHash);
    }

    /**
     * @return array{commit_spec:string, ancestor_spec:string, instructions:list<int>}
     */
    public static function splitAncestorSpec(string $spec): array
    {
        $clean = trim($spec);
        $caret = strpos($clean, '^');
        $tilde = strpos($clean, '~');
        if ($caret === false && $tilde === false) {
            return [
                'commit_spec' => $clean,
                'ancestor_spec' => '',
                'instructions' => [],
            ];
        }

        $idx = $caret === false ? $tilde : $caret;
        if ($tilde !== false && $tilde < $idx) {
            $idx = $tilde;
        }

        $ancestorSpec = substr($clean, $idx);

        return [
            'commit_spec' => substr($clean, 0, $idx),
            'ancestor_spec' => $ancestorSpec,
            'instructions' => self::parseAncestorSpec($ancestorSpec),
        ];
    }

    /**
     * Parse Dolt's ancestor spec grammar from `doltdb/ancestor_spec.go`.
     *
     * `^` and `^1` select the first parent, `^2` selects the second parent,
     * and `~N` expands to N first-parent hops.
     *
     * @return list<int>
     */
    public static function parseAncestorSpec(string $ancestorSpec): array
    {
        $instructions = [];
        $length = strlen($ancestorSpec);
        for ($i = 0; $i < $length; $i++) {
            $op = $ancestorSpec[$i];
            $start = $i;
            while ($i + 1 < $length && ctype_digit($ancestorSpec[$i + 1])) {
                $i++;
            }

            $num = 1;
            if ($start !== $i) {
                $num = (int) substr($ancestorSpec, $start + 1, $i - $start);
            }

            if ($op === '^') {
                if ($num !== 1 && $num !== 2) {
                    throw new \InvalidArgumentException('Invalid Dolt ancestor spec: ' . $ancestorSpec);
                }
                $instructions[] = $num - 1;
                continue;
            }

            if ($op === '~') {
                for ($j = 0; $j < $num; $j++) {
                    $instructions[] = 0;
                }
                continue;
            }

            throw new \InvalidArgumentException('Invalid Dolt ancestor spec: ' . $ancestorSpec);
        }

        return $instructions;
    }

    /**
     * @param list<array<string, mixed>> $commits
     * @return array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, refs:list<non-empty-string>}>
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

            $normalized[$hash] = [
                'commit_hash' => $hash,
                'parents' => $this->normalizeStringList($commit['parents'] ?? [], "Dolt commit {$hash} parents"),
                'refs' => $this->normalizeStringList($commit['refs'] ?? [], "Dolt commit {$hash} refs"),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, refs:list<non-empty-string>}> $commits
     */
    private function resolveCommitHash(array $commits, string $spec, ?string $headHash): string
    {
        $split = self::splitAncestorSpec($spec);
        $baseHash = $this->resolveBaseCommitHash($commits, $split['commit_spec'], $headHash);

        foreach ($split['instructions'] as $parentIndex) {
            if (!isset($commits[$baseHash])) {
                throw new \RuntimeException("Dolt commit not found while resolving ancestor spec: {$baseHash}");
            }
            $parents = $commits[$baseHash]['parents'];
            if (!array_key_exists($parentIndex, $parents)) {
                $humanIndex = $parentIndex + 1;
                throw new \RuntimeException("Dolt commit {$baseHash} does not have parent {$humanIndex}.");
            }
            $baseHash = $parents[$parentIndex];
        }

        if (!isset($commits[$baseHash])) {
            throw new \RuntimeException("Dolt commit not found: {$baseHash}");
        }

        return $baseHash;
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, parents:list<non-empty-string>, refs:list<non-empty-string>}> $commits
     */
    private function resolveBaseCommitHash(array $commits, string $spec, ?string $headHash): string
    {
        if ($spec === '') {
            throw new \InvalidArgumentException('Dolt commit spec must be a non-empty string.');
        }

        if ($spec === 'HEAD') {
            if ($headHash === null || $headHash === '') {
                throw new \InvalidArgumentException('Dolt HEAD resolution requires a non-empty current head hash.');
            }
            if (!isset($commits[$headHash])) {
                throw new \RuntimeException("Dolt HEAD commit not found: {$headHash}");
            }

            return $headHash;
        }

        if (isset($commits[$spec])) {
            return $spec;
        }

        $matches = [];
        foreach ($commits as $hash => $commit) {
            foreach ($commit['refs'] as $ref) {
                foreach ($this->refAliases($ref) as $alias) {
                    if ($alias === $spec) {
                        $matches[$hash] = true;
                    }
                }
            }
        }

        if (count($matches) === 1) {
            return array_key_first($matches);
        }
        if (count($matches) > 1) {
            throw new \RuntimeException("Ambiguous Dolt ref spec: {$spec}");
        }

        throw new \RuntimeException("Dolt ref or commit not found: {$spec}");
    }

    /**
     * @return list<string>
     */
    private function refAliases(string $ref): array
    {
        $aliases = [$ref];
        foreach (['refs/heads/', 'refs/tags/'] as $prefix) {
            if (str_starts_with($ref, $prefix)) {
                $aliases[] = substr($ref, strlen($prefix));
            }
        }

        return array_values(array_unique($aliases));
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
}
