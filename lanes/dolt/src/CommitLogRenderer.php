<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CommitLogRenderer
{
    public const OPERATION_ADDED = 'added';
    public const OPERATION_DELETED = 'deleted';
    public const OPERATION_MODIFIED = 'modified';

    /**
     * Render projected `dolt_log` rows in the CLI-oriented formats used by
     * upstream `dolt log`.
     *
     * @param list<array<string, scalar|null>> $rows
     * @param array{graph?:bool, oneline?:bool, parents?:bool, stat?:bool, diffStats?:array<string, list<array<string, mixed>>>, diffStatsByCommit?:array<string, list<array<string, mixed>>>} $options
     */
    public function render(array $rows, array $options = []): string
    {
        $graph = $this->boolOption($options, 'graph');
        $oneline = $this->boolOption($options, 'oneline');
        $parents = $this->boolOption($options, 'parents');
        $stat = $this->boolOption($options, 'stat');

        if ($graph) {
            return $this->renderGraph($rows, $oneline);
        }

        $statsByCommit = $this->normalizeStatsByCommit($options['diffStatsByCommit'] ?? $options['diffStats'] ?? []);

        $lines = [];
        foreach ($rows as $row) {
            $commitHash = $this->requiredString($row['commit_hash'] ?? null, 'Dolt log commit_hash');
            $parentHashes = $this->parentHashes($row['parents'] ?? null);
            if ($oneline) {
                $lines[] = $this->oneline($row, $commitHash, $parentHashes, $parents);
                if ($stat && count($parentHashes) === 1) {
                    array_push($lines, ...$this->diffStatLines($statsByCommit[$commitHash] ?? []));
                }
                continue;
            }

            array_push($lines, ...$this->defaultLines($row, $commitHash, $parentHashes, $parents));
            if ($stat && count($parentHashes) === 1) {
                $statLines = $this->diffStatLines($statsByCommit[$commitHash] ?? []);
                if ($statLines !== []) {
                    array_push($lines, ...$statLines);
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    private function renderGraph(array $rows, bool $oneline): string
    {
        if ($rows === []) {
            return '';
        }

        $commits = $this->graphCommits($rows);
        $commits = $this->computeGraphColumns($commits);
        if ($oneline) {
            $this->expandGraphForOneline($commits);
        } else {
            $this->expandGraphForMetadata($commits);
        }

        $graph = $this->drawGraph($commits);
        foreach ($graph as $i => $line) {
            $graph[$i] = $this->trimGraphLine($line);
        }

        return implode("\n", $oneline
            ? $this->graphOnelineLines($graph, $commits)
            : $this->graphDefaultLines($graph, $commits));
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}>
     */
    private function graphCommits(array $rows): array
    {
        $commits = [];
        foreach ($rows as $row) {
            $hash = $this->requiredString($row['commit_hash'] ?? null, 'Dolt log commit_hash');
            $commits[] = [
                'hash' => $hash,
                'parents' => $this->parentHashes($row['parents'] ?? null),
                'row' => $row,
                'children' => [],
                'col' => -1,
                'graphRow' => count($commits),
                'messageLines' => [],
            ];
        }

        $children = [];
        foreach ($commits as $commit) {
            foreach ($commit['parents'] as $parent) {
                $children[$parent][] = $commit['hash'];
            }
        }

        foreach ($commits as $i => $commit) {
            $commits[$i]['children'] = $children[$commit['hash']] ?? [];
        }

        return $commits;
    }

    /**
     * @param list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}> $commits
     * @return list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}>
     */
    private function computeGraphColumns(array $commits): array
    {
        $columns = [];
        $colPositions = [];
        $commitsByHash = [];
        foreach ($commits as $i => $commit) {
            $commitsByHash[$commit['hash']] = $i;
        }

        foreach ($commits as $index => $commit) {
            $branchChildren = [];
            foreach ($commit['children'] as $childHash) {
                $child = $commits[$commitsByHash[$childHash]];
                if (($child['parents'][0] ?? null) === $commit['hash']) {
                    $branchChildren[] = $childHash;
                }
            }

            $commitCol = -1;
            if ($commit['children'] === []) {
                $columns[] = [['start' => $index, 'end' => $index]];
                $commitCol = count($columns) - 1;
            } elseif ($branchChildren !== []) {
                $branchChildCols = [];
                foreach ($branchChildren as $childHash) {
                    if (isset($colPositions[$childHash])) {
                        $branchChildCols[] = $colPositions[$childHash];
                    }
                }
                if ($branchChildCols === []) {
                    throw new \RuntimeException("Dolt log graph child column missing for {$commit['hash']}.");
                }

                $commitCol = min($branchChildCols);
                $this->updateGraphColumnEnd($columns, $commitCol, $index);
                foreach ($branchChildCols as $childCol) {
                    if ($childCol !== $commitCol) {
                        $this->updateGraphColumnEnd($columns, $childCol, $index - 1);
                    }
                }
            } else {
                $minChildRow = PHP_INT_MAX;
                $maxChildCol = -1;
                foreach ($commit['children'] as $childHash) {
                    $child = $commits[$commitsByHash[$childHash]];
                    $minChildRow = min($minChildRow, $child['graphRow']);
                    $maxChildCol = max($maxChildCol, $colPositions[$childHash] ?? -1);
                }

                $availableCol = -1;
                for ($i = $maxChildCol + 1; $i < count($columns); $i++) {
                    $lastPath = $columns[$i][count($columns[$i]) - 1];
                    if ($minChildRow >= $lastPath['end']) {
                        $availableCol = $i;
                        break;
                    }
                }

                if ($availableCol === -1) {
                    $columns[] = [['start' => $minChildRow + 1, 'end' => $index]];
                    $commitCol = count($columns) - 1;
                } else {
                    $commitCol = $availableCol;
                    $columns[$availableCol][] = ['start' => $minChildRow + 1, 'end' => $index];
                }
            }

            $colPositions[$commit['hash']] = $commitCol;
            $commits[$index]['col'] = $commitCol;
        }

        return $commits;
    }

    /**
     * @param list<list<array{start:int, end:int}>> $columns
     */
    private function updateGraphColumnEnd(array &$columns, int $col, int $end): void
    {
        if (!isset($columns[$col])) {
            throw new \RuntimeException("Dolt log graph column {$col} does not exist.");
        }
        $last = count($columns[$col]) - 1;
        $columns[$col][$last]['end'] = $end;
    }

    /**
     * @param list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}> $commits
     */
    private function expandGraphForOneline(array &$commits): void
    {
        $commitsByHash = [];
        foreach ($commits as $i => $commit) {
            $commitsByHash[$commit['hash']] = $i;
        }

        $posY = 0;
        foreach ($commits as $i => $commit) {
            $commits[$i]['col'] *= 2;
            $commits[$i]['messageLines'] = [
                str_replace("\n", ' ', $this->stringValue($commit['row']['message'] ?? null, 'Dolt log message')),
            ];

            if ($i > 0) {
                $posY++;
                foreach ($commit['children'] as $childHash) {
                    if (!isset($commitsByHash[$childHash])) {
                        continue;
                    }
                    $child = $commits[$commitsByHash[$childHash]];
                    $horizontal = abs($commits[$i]['col'] - $child['col']);
                    $posY = max($posY, $horizontal + $child['graphRow']);
                }
            }

            $commits[$i]['graphRow'] = $posY;
        }
    }

    /**
     * @param list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}> $commits
     */
    private function expandGraphForMetadata(array &$commits): void
    {
        $posY = 0;
        foreach ($commits as $i => $commit) {
            $commits[$i]['col'] *= 2;
            $commits[$i]['graphRow'] = $posY;
            $commits[$i]['messageLines'] = explode(
                "\n",
                $this->stringValue($commit['row']['message'] ?? null, 'Dolt log message'),
            );
            $posY += $this->graphCommitHeight($commits[$i]) + 1;
        }
    }

    /**
     * @param array{parents:list<non-empty-string>, messageLines:list<string>} $commit
     */
    private function graphCommitHeight(array $commit): int
    {
        return 4 + count($commit['messageLines']) + (count($commit['parents']) > 1 ? 1 : 0);
    }

    /**
     * @param list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}> $commits
     * @return list<list<string>>
     */
    private function drawGraph(array $commits): array
    {
        $maxWidth = 0;
        $maxHeight = 0;
        $commitsByHash = [];
        foreach ($commits as $i => $commit) {
            $maxWidth = max($maxWidth, $commit['col']);
            $maxHeight = max($maxHeight, $commit['graphRow']);
            $commitsByHash[$commit['hash']] = $i;
        }

        $heightOfLastCommit = $this->graphCommitHeight($commits[count($commits) - 1]);
        $graph = array_fill(0, $maxHeight + $heightOfLastCommit, []);
        foreach ($graph as $i => $_) {
            $graph[$i] = array_fill(0, $maxWidth + 2, ' ');
        }

        foreach ($commits as $commit) {
            $col = $commit['col'];
            $row = $commit['graphRow'];
            $graph[$row][$col] = '*';

            foreach ($commit['parents'] as $parentHash) {
                if (!isset($commitsByHash[$parentHash])) {
                    continue;
                }

                $parent = $commits[$commitsByHash[$parentHash]];
                $parentCol = $parent['col'];
                $parentRow = $parent['graphRow'];

                if ($parentCol === $col) {
                    for ($r = $row + 1; $r < $parentRow; $r++) {
                        if ($graph[$r][$col] === ' ') {
                            $graph[$r][$col] = '|';
                        }
                    }
                    continue;
                }

                if ($parentCol < $col) {
                    $horizontal = $col - $parentCol;
                    $vertical = $parentRow - $row;
                    if ($horizontal > $vertical) {
                        for ($i = 1; $i < $vertical; $i++) {
                            $graph[$parentRow - $i][$parentCol + $horizontal - $vertical + $i] = '/';
                        }
                        for ($i = $parentCol; $i < $parentCol + ($horizontal - $vertical) + 1; $i++) {
                            if ($graph[$parentRow][$i] === ' ') {
                                $graph[$parentRow][$i] = '-';
                            }
                        }
                    } else {
                        for ($i = $parentCol + 1; $i < $col; $i++) {
                            $graph[$parentRow + $parentCol - $i][$i] = '/';
                        }
                        for ($i = $parentRow + $parentCol - $col; $i > $row; $i--) {
                            if ($graph[$i][$col] === ' ') {
                                $graph[$i][$col] = '|';
                            }
                        }
                    }
                    continue;
                }

                $horizontal = $parentCol - $col;
                $vertical = $parentRow - $row;
                if ($vertical > $horizontal) {
                    for ($i = $col + 1; $i < $parentCol; $i++) {
                        $graph[$row + $i - $col][$i] = '\\';
                    }
                    for ($i = $row + $parentCol - $col; $i < $parentRow; $i++) {
                        if ($graph[$i][$parentCol] === ' ') {
                            $graph[$i][$parentCol] = '|';
                        }
                    }
                } else {
                    for ($i = 0; $i < $vertical; $i++) {
                        $graph[$parentRow - $i][$parentCol - $i] = '\\';
                    }
                    for ($i = $col + 1; $i < $parentCol - $vertical + 1; $i++) {
                        if ($graph[$row][$i] === ' ') {
                            $graph[$row][$i] = '-';
                        }
                    }
                }
            }
        }

        return $graph;
    }

    /**
     * @param list<string> $line
     * @return list<string>
     */
    private function trimGraphLine(array $line): array
    {
        $last = count($line) - 1;
        while ($last >= 0 && trim($line[$last]) === '') {
            $last--;
        }

        return $last < 0 ? [] : array_slice($line, 0, $last + 1);
    }

    /**
     * @param list<list<string>> $graph
     * @param list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}> $commits
     * @return list<string>
     */
    private function graphOnelineLines(array $graph, array $commits): array
    {
        $lines = [];
        $previousRow = null;
        foreach ($commits as $i => $commit) {
            if ($previousRow !== null) {
                for ($row = $previousRow + 1; $row < $commit['graphRow']; $row++) {
                    $lines[] = implode('', $graph[$row]);
                }
            }

            $lines[] = $this->graphLineText($graph, $commit['graphRow'], 0)
                . ' commit '
                . $commit['hash']
                . ($i === 0 ? '' : ' ')
                . $this->refsSuffix($commit['row'])
                . ' '
                . implode(' ', $commit['messageLines']);
            $previousRow = $commit['graphRow'];
        }

        return $lines;
    }

    /**
     * @param list<list<string>> $graph
     * @param list<array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, children:list<non-empty-string>, col:int, graphRow:int, messageLines:list<string>}> $commits
     * @return list<string>
     */
    private function graphDefaultLines(array $graph, array $commits): array
    {
        $lines = [];
        $last = count($commits) - 1;
        for ($i = 0; $i < $last; $i++) {
            $commit = $commits[$i];
            $nextRow = $commits[$i + 1]['graphRow'];
            $startRow = $commit['graphRow'];
            $startCol = $commit['col'] + 1;
            for ($j = $startRow; $j < $nextRow; $j++) {
                $startCol = max($startCol, count($graph[$j]));
            }

            array_push($lines, ...$this->graphCommitMetadataLines($graph, $commit, $startCol));
            $height = $this->graphCommitHeight($commit);
            foreach ($commit['messageLines'] as $j => $messageLine) {
                $row = $startRow + $height - count($commit['messageLines']) + $j;
                $lines[] = $this->graphLineText($graph, $row, $startCol) . ' ' . "\t" . $messageLine;
            }
            for ($j = $startRow + $height; $j < $nextRow; $j++) {
                $lines[] = implode('', $graph[$j]);
            }
        }

        $lastCommit = $commits[$last];
        array_push(
            $lines,
            ...$this->graphCommitMetadataLines($graph, $lastCommit, count($graph[$lastCommit['graphRow']])),
        );
        foreach ($lastCommit['messageLines'] as $messageLine) {
            $lines[] = "\t" . $messageLine;
        }

        return $lines;
    }

    /**
     * @param list<list<string>> $graph
     * @param array{hash:non-empty-string, parents:list<non-empty-string>, row:array<string, scalar|null>, graphRow:int} $commit
     * @return list<string>
     */
    private function graphCommitMetadataLines(array $graph, array $commit, int $col): array
    {
        $row = $commit['graphRow'];
        $lines = [
            $this->graphLineText($graph, $row, $col)
                . ' commit '
                . $commit['hash']
                . $this->graphRefsSuffix($commit['row']),
        ];

        $mergeOffset = 0;
        if (count($commit['parents']) > 1) {
            $mergeOffset = 1;
            $lines[] = $this->graphLineText($graph, $row + 1, $col)
                . ' Merge: '
                . implode(' ', $commit['parents']);
        }

        $lines[] = $this->graphLineText($graph, $row + 1 + $mergeOffset, $col)
            . ' Author: '
            . $this->requiredString($commit['row']['author'] ?? $commit['row']['committer'] ?? null, 'Dolt log author')
            . ' <'
            . $this->requiredString($commit['row']['author_email'] ?? $commit['row']['email'] ?? null, 'Dolt log author_email')
            . '>';
        $lines[] = $this->graphLineText($graph, $row + 2 + $mergeOffset, $col)
            . ' Date: '
            . $this->requiredString($commit['row']['author_date'] ?? $commit['row']['date'] ?? null, 'Dolt log date');
        $lines[] = implode('', $graph[$row + 3 + $mergeOffset]);

        return $lines;
    }

    /**
     * @param list<list<string>> $graph
     */
    private function graphLineText(array $graph, int $row, int $col): string
    {
        $graphLine = implode('', $graph[$row]);
        $padding = $col > count($graph[$row]) ? str_repeat(' ', $col - count($graph[$row])) : '';

        return $graphLine . $padding;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function refsSuffix(array $row): string
    {
        $refs = $this->nullableString($row['refs'] ?? null, 'Dolt log refs');

        return $refs !== null && $refs !== '' ? ' (' . $refs . ')' : '';
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function graphRefsSuffix(array $row): string
    {
        $refs = $this->nullableString($row['refs'] ?? null, 'Dolt log refs');

        return $refs !== null && $refs !== '' ? '(' . $refs . ') ' : '';
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<non-empty-string> $parentHashes
     */
    private function oneline(array $row, string $commitHash, array $parentHashes, bool $parents): string
    {
        $head = $commitHash;
        if ($parents && $parentHashes !== []) {
            $head .= ' ' . implode(' ', $parentHashes);
        }

        $refs = $this->nullableString($row['refs'] ?? null, 'Dolt log refs');
        if ($refs !== null && $refs !== '') {
            $head .= ' (' . $refs . ')';
        }

        return $head . ' ' . str_replace("\n", ' ', $this->stringValue($row['message'] ?? null, 'Dolt log message'));
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<non-empty-string> $parentHashes
     * @return list<string>
     */
    private function defaultLines(array $row, string $commitHash, array $parentHashes, bool $parents): array
    {
        $head = 'commit ' . $commitHash;
        if ($parents && $parentHashes !== []) {
            $head .= ' ' . implode(' ', $parentHashes);
        }

        $refs = $this->nullableString($row['refs'] ?? null, 'Dolt log refs');
        if ($refs !== null && $refs !== '') {
            $head .= ' (' . $refs . ')';
        }

        $lines = [$head];
        if (count($parentHashes) > 1) {
            $lines[] = 'Merge: ' . implode(' ', $parentHashes);
        }

        $author = $this->requiredString($row['author'] ?? $row['committer'] ?? null, 'Dolt log author');
        $authorEmail = $this->requiredString($row['author_email'] ?? $row['email'] ?? null, 'Dolt log author_email');
        $date = $this->requiredString($row['author_date'] ?? $row['date'] ?? null, 'Dolt log date');
        $lines[] = 'Author: ' . $author . ' <' . $authorEmail . '>';
        $lines[] = 'Date:  ' . $date;
        $lines[] = '';

        foreach (explode("\n", $this->stringValue($row['message'] ?? null, 'Dolt log message')) as $messageLine) {
            $lines[] = "\t" . $messageLine;
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param list<array{table:non-empty-string, operation:string, adds:int, modifications:int, deletes:int}> $stats
     * @return list<string>
     */
    private function diffStatLines(array $stats): array
    {
        $modified = [];
        $added = [];
        $deleted = [];
        foreach ($stats as $stat) {
            if ($stat['operation'] === self::OPERATION_MODIFIED) {
                $total = $stat['adds'] + $stat['modifications'] + $stat['deletes'];
                if ($total > 0) {
                    $modified[$stat['table']] = $stat;
                }
                continue;
            }
            if ($stat['operation'] === self::OPERATION_ADDED) {
                $added[] = $stat['table'];
                continue;
            }
            if ($stat['operation'] === self::OPERATION_DELETED) {
                $deleted[] = $stat['table'];
            }
        }

        ksort($modified, SORT_STRING);
        sort($added, SORT_STRING);
        sort($deleted, SORT_STRING);

        $lines = [];
        if ($modified !== []) {
            $maxNameLen = max(array_map('strlen', array_keys($modified)));
            $maxModCount = 0;
            $rowsAdded = 0;
            $rowsModified = 0;
            $rowsDeleted = 0;
            foreach ($modified as $stat) {
                $modCount = $stat['adds'] + $stat['modifications'] + $stat['deletes'];
                $maxModCount = max($maxModCount, $modCount);
                $rowsAdded += $stat['adds'];
                $rowsModified += $stat['modifications'];
                $rowsDeleted += $stat['deletes'];
            }

            $modCountStrLen = strlen((string) $maxModCount);
            foreach ($modified as $table => $stat) {
                $modCount = $stat['adds'] + $stat['modifications'] + $stat['deletes'];
                $lines[] = ' '
                    . str_pad($table, $maxNameLen)
                    . ' | '
                    . str_pad((string) $modCount, $modCountStrLen)
                    . ' '
                    . $this->visualizeChanges($stat, $maxModCount);
            }

            $lines[] = sprintf(
                ' %d tables changed, %d rows added(+), %d rows modified(*), %d rows deleted(-)',
                count($modified),
                $rowsAdded,
                $rowsModified,
                $rowsDeleted,
            );
        }

        foreach ($added as $table) {
            $lines[] = ' ' . $table . ' added';
        }
        foreach ($deleted as $table) {
            $lines[] = ' ' . $table . ' deleted';
        }

        return $lines;
    }

    /**
     * @param array{adds:int, modifications:int, deletes:int} $stat
     */
    private function visualizeChanges(array $stat, int $maxMods): string
    {
        if ($maxMods <= 0) {
            return '';
        }

        return str_repeat('+', $this->visualLength($stat['adds'], $maxMods))
            . str_repeat('*', $this->visualLength($stat['modifications'], $maxMods))
            . str_repeat('-', $this->visualLength($stat['deletes'], $maxMods));
    }

    private function visualLength(int $count, int $maxMods): int
    {
        if ($count <= 0) {
            return 0;
        }

        return min($count, (int) (30 * ($count / $maxMods)));
    }

    /**
     * @param array<string, list<array<string, mixed>>> $statsByCommit
     * @return array<string, list<array{table:non-empty-string, operation:string, adds:int, modifications:int, deletes:int}>>
     */
    private function normalizeStatsByCommit(array $statsByCommit): array
    {
        $normalized = [];
        foreach ($statsByCommit as $commitHash => $stats) {
            if (!is_string($commitHash) || $commitHash === '') {
                throw new \InvalidArgumentException('Dolt log diffStatsByCommit keys must be commit hashes.');
            }
            if (!is_array($stats)) {
                throw new \InvalidArgumentException("Dolt log diff stats for {$commitHash} must be a list.");
            }

            $normalized[$commitHash] = [];
            foreach ($stats as $stat) {
                if (!is_array($stat)) {
                    throw new \InvalidArgumentException("Dolt log diff stat for {$commitHash} must be an array.");
                }
                $normalized[$commitHash][] = $this->normalizeStat($stat, $commitHash);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $stat
     * @return array{table:non-empty-string, operation:string, adds:int, modifications:int, deletes:int}
     */
    private function normalizeStat(array $stat, string $commitHash): array
    {
        $table = $this->requiredString(
            $stat['table'] ?? $stat['table_name'] ?? $stat['name'] ?? null,
            "Dolt log diff stat {$commitHash} table",
        );
        $operation = $this->normalizeOperation($stat['operation'] ?? $stat['diff_type'] ?? null, $commitHash, $table);

        return [
            'table' => $table,
            'operation' => $operation,
            'adds' => $this->nonNegativeInt($stat['adds'] ?? $stat['rows_added'] ?? $stat['added'] ?? 0, "{$commitHash} {$table} adds"),
            'modifications' => $this->nonNegativeInt($stat['modifications'] ?? $stat['rows_modified'] ?? $stat['modified'] ?? 0, "{$commitHash} {$table} modifications"),
            'deletes' => $this->nonNegativeInt($stat['deletes'] ?? $stat['rows_deleted'] ?? $stat['deleted'] ?? 0, "{$commitHash} {$table} deletes"),
        ];
    }

    private function normalizeOperation(mixed $operation, string $commitHash, string $table): string
    {
        if (!is_string($operation) || $operation === '') {
            throw new \InvalidArgumentException("Dolt log diff stat {$commitHash} {$table} operation must be a non-empty string.");
        }

        return match (strtolower($operation)) {
            'added', 'add', 'tableadded', 'table_added' => self::OPERATION_ADDED,
            'deleted', 'removed', 'dropped', 'drop', 'tabledeleted', 'tableremoved', 'table_deleted', 'table_removed' => self::OPERATION_DELETED,
            'modified', 'changed', 'tablemodified', 'table_modified' => self::OPERATION_MODIFIED,
            default => throw new \InvalidArgumentException("Unsupported Dolt log diff stat operation for {$commitHash} {$table}: {$operation}"),
        };
    }

    /**
     * @return list<non-empty-string>
     */
    private function parentHashes(mixed $parents): array
    {
        if ($parents === null || $parents === '') {
            return [];
        }
        if (!is_string($parents)) {
            throw new \InvalidArgumentException('Dolt log parents must be a string or null.');
        }

        $hashes = [];
        foreach (explode(',', $parents) as $parent) {
            $parent = trim($parent);
            if ($parent === '') {
                continue;
            }
            $hashes[] = $parent;
        }

        return $hashes;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function boolOption(array $options, string $name): bool
    {
        $value = $options[$name] ?? false;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Dolt log {$name} option must be a boolean.");
        }

        return $value;
    }

    private function requiredString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$label} must be a non-empty string.");
        }

        return $value;
    }

    private function nullableString(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$label} must be a string or null.");
        }

        return $value;
    }

    private function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$label} must be a string.");
        }

        return $value;
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Dolt log diff stat {$label} must be a non-negative integer.");
        }

        return $value;
    }
}
