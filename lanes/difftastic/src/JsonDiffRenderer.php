<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class JsonDiffRenderer
{
    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
    ) {
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    public function renderFileDiff(string $old, string $new, string $path, string $language, array $options = []): string
    {
        return json_encode(
            $this->fileDiff($old, $new, $path, $language, $options),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param list<array{old:string, new:string, path:string, language:string, options?:array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string}}> $files
     */
    public function renderDirectoryDiff(array $files, bool $printUnchanged = false): string
    {
        $diffs = [];
        foreach ($files as $file) {
            $diff = $this->fileDiff(
                $file['old'],
                $file['new'],
                $file['path'],
                $file['language'],
                $file['options'] ?? [],
            );
            if ($printUnchanged || $diff['status'] !== 'unchanged') {
                $diffs[] = $diff;
            }
        }

        return json_encode($diffs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return array<string, mixed>
     */
    public function fileDiff(string $old, string $new, string $path, string $language, array $options = []): array
    {
        if (!$this->differ->hasChanges($old, $new, $options)) {
            return $this->statusFile($language, $path, 'unchanged');
        }
        if ($old === '') {
            return $this->statusFile($language, $path, 'created');
        }
        if ($new === '') {
            return $this->statusFile($language, $path, 'deleted');
        }

        [$alignedLines, $chunks] = $this->changedSections(explode("\n", $old), explode("\n", $new), $options);

        $file = [];
        if ($alignedLines !== []) {
            $file['aligned_lines'] = $alignedLines;
        }
        if ($chunks !== []) {
            $file['chunks'] = $chunks;
        }
        $file['language'] = $language;
        $file['path'] = $path;
        $file['status'] = 'changed';

        return $file;
    }

    /**
     * @return array{language:string, path:string, status:string}
     */
    private function statusFile(string $language, string $path, string $status): array
    {
        return [
            'language' => $language,
            'path' => $path,
            'status' => $status,
        ];
    }

    /**
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return array{0:list<array{0:?int, 1:?int}>, 1:list<list<array<string, mixed>>>}
     */
    private function changedSections(array $oldLines, array $newLines, array $options): array
    {
        $alignedLines = [];
        $chunks = [];
        $pending = [];
        foreach ($this->diffLines($oldLines, $newLines) as $op) {
            if ($op['op'] === '=') {
                $this->flushChangedLineOps($pending, $oldLines, $newLines, $options, $alignedLines, $chunks);
                $alignedLines[] = [$op['old'], $op['new']];
                continue;
            }

            $pending[] = $op;
        }

        $this->flushChangedLineOps($pending, $oldLines, $newLines, $options, $alignedLines, $chunks);

        return [$alignedLines, $chunks];
    }

    /**
     * @param list<array<string, mixed>> $pending
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{0:?int, 1:?int}> $alignedLines
     * @param list<list<array<string, mixed>>> $chunks
     */
    private function flushChangedLineOps(array &$pending, array $oldLines, array $newLines, array $options, array &$alignedLines, array &$chunks): void
    {
        if ($pending === []) {
            return;
        }

        $deleted = array_values(array_filter($pending, static fn (array $op): bool => $op['op'] === '-'));
        $inserted = array_values(array_filter($pending, static fn (array $op): bool => $op['op'] === '+'));
        $lineCount = max(count($deleted), count($inserted));
        $chunk = [];

        for ($index = 0; $index < $lineCount; $index++) {
            $oldLineNumber = $deleted[$index]['old'] ?? null;
            $newLineNumber = $inserted[$index]['new'] ?? null;
            $alignedLines[] = [$oldLineNumber, $newLineNumber];

            $line = [];
            if ($oldLineNumber !== null) {
                $line['lhs'] = $this->side(
                    $oldLineNumber,
                    $oldLines[$oldLineNumber],
                    $newLineNumber === null ? null : $newLines[$newLineNumber],
                    'lhs',
                    $options,
                );
            }
            if ($newLineNumber !== null) {
                $line['rhs'] = $this->side(
                    $newLineNumber,
                    $newLines[$newLineNumber],
                    $oldLineNumber === null ? null : $oldLines[$oldLineNumber],
                    'rhs',
                    $options,
                );
            }

            $chunk[] = $line;
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }
        $pending = [];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return array{line_number:int, changes:list<array{start:int, end:int, content:string, highlight:string}>}
     */
    private function side(int $lineNumber, string $line, ?string $otherLine, string $side, array $options): array
    {
        $changes = $otherLine === null
            ? $this->fullLineChanges($line, $options)
            : $this->pairedLineChanges($line, $otherLine, $side, $options);

        if ($changes === [] && $line !== '') {
            $changes[] = [
                'start' => 0,
                'end' => strlen($line),
                'content' => $line,
                'highlight' => 'normal',
            ];
        }

        return [
            'line_number' => $lineNumber,
            'changes' => $changes,
        ];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return list<array{start:int, end:int, content:string, highlight:string}>
     */
    private function pairedLineChanges(string $line, string $otherLine, string $side, array $options): array
    {
        $oldLine = $side === 'lhs' ? $line : $otherLine;
        $newLine = $side === 'rhs' ? $line : $otherLine;
        $targetOp = $side === 'lhs' ? '-' : '+';
        $changes = [];
        $cursor = 0;

        foreach ($this->differ->diff($oldLine, $newLine, $options) as $op) {
            if ($op['op'] !== '=' && $op['op'] !== $targetOp) {
                continue;
            }

            $position = $this->findFrom($line, $op['text'], $cursor);
            if ($position === null) {
                continue;
            }

            $end = $position + strlen($op['text']);
            if ($op['op'] === $targetOp) {
                $changes[] = [
                    'start' => $position,
                    'end' => $end,
                    'content' => $op['text'],
                    'highlight' => $this->highlightFor($op['text'], $options),
                ];
            }
            $cursor = $end;
        }

        return $changes;
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return list<array{start:int, end:int, content:string, highlight:string}>
     */
    private function fullLineChanges(string $line, array $options): array
    {
        $changes = [];
        $cursor = 0;
        foreach ($this->differ->tokenize($line, $options) as $token) {
            $position = $this->findFrom($line, $token->text, $cursor);
            if ($position === null) {
                continue;
            }

            $end = $position + strlen($token->text);
            $changes[] = [
                'start' => $position,
                'end' => $end,
                'content' => $token->text,
                'highlight' => $this->highlightFor($token->text, $options),
            ];
            $cursor = $end;
        }

        return $changes;
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function highlightFor(string $text, array $options): string
    {
        $tokens = $this->differ->tokenize($text, $options);
        $kind = $tokens[0]->kind ?? 'punctuation';

        return match ($kind) {
            'comment' => 'comment',
            'delimiter' => 'delimiter',
            'string' => 'string',
            default => 'normal',
        };
    }

    private function findFrom(string $line, string $text, int $cursor): ?int
    {
        if ($text === '') {
            return null;
        }

        $position = strpos($line, $text, $cursor);
        if ($position !== false) {
            return $position;
        }

        $position = strpos($line, $text);

        return $position === false ? null : $position;
    }

    /**
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @return list<array{op:string, old?:int, new?:int}>
     */
    private function diffLines(array $oldLines, array $newLines): array
    {
        $table = $this->lcsTable($oldLines, $newLines);
        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < count($oldLines) && $j < count($newLines)) {
            if ($oldLines[$i] === $newLines[$j]) {
                $ops[] = ['op' => '=', 'old' => $i, 'new' => $j];
                $i++;
                $j++;
                continue;
            }

            if ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['op' => '-', 'old' => $i];
                $i++;
            } else {
                $ops[] = ['op' => '+', 'new' => $j];
                $j++;
            }
        }

        while ($i < count($oldLines)) {
            $ops[] = ['op' => '-', 'old' => $i];
            $i++;
        }
        while ($j < count($newLines)) {
            $ops[] = ['op' => '+', 'new' => $j];
            $j++;
        }

        return $ops;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<list<int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $table = array_fill(0, count($a) + 1, array_fill(0, count($b) + 1, 0));
        for ($i = count($a) - 1; $i >= 0; $i--) {
            for ($j = count($b) - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }
}
