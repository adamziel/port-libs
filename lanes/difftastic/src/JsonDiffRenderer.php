<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class JsonDiffRenderer
{
    private const MAX_HUNK_LINE_DISTANCE = 4;

    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
        private readonly SyntaxHighlightClassifier $highlightClassifier = new SyntaxHighlightClassifier(),
    ) {
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool} $options
     */
    public function renderFileDiff(string $old, string $new, string $path, string $language, array $options = []): string
    {
        return json_encode(
            $this->fileDiff($old, $new, $path, $language, $options),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool, forceBinary?: bool, binaryOverrides?: list<string>} $options
     */
    public function renderFileBytesDiff(string $oldBytes, string $newBytes, string $path, string $language, array $options = []): string
    {
        return json_encode(
            $this->fileBytesDiff($oldBytes, $newBytes, $path, $language, $options),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param list<array{old:string, new:string, path:string, language:string, options?:array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool}}> $files
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
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool, forceBinary?: bool, binaryOverrides?: list<string>} $options
     * @return array<string, mixed>
     */
    public function fileBytesDiff(string $oldBytes, string $newBytes, string $path, string $language, array $options = []): array
    {
        if (($options['forceBinary'] ?? false) === true) {
            return $this->statusFile('Binary', $path, $oldBytes === $newBytes ? 'unchanged' : 'changed');
        }

        $decoder = new FileContentDecoder();
        $binaryOverrides = $options['binaryOverrides'] ?? [];
        $old = $decoder->guessTextContent($oldBytes, $path, $binaryOverrides);
        $new = $decoder->guessTextContent($newBytes, $path, $binaryOverrides);

        if ($old === null || $new === null) {
            return $this->statusFile('Binary', $path, $oldBytes === $newBytes ? 'unchanged' : 'changed');
        }

        return $this->fileDiff($old, $new, $path, $language, $options);
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool} $options
     * @return array<string, mixed>
     */
    public function fileDiff(string $old, string $new, string $path, string $language, array $options = []): array
    {
        $old = $this->differ->normalizeTextForDiff($old, $options);
        $new = $this->differ->normalizeTextForDiff($new, $options);

        if ($old === $new) {
            return $this->statusFile($language, $path, 'unchanged');
        }
        if ($old === '') {
            return $this->statusFile($language, $path, 'created');
        }
        if ($new === '') {
            return $this->statusFile($language, $path, 'deleted');
        }

        $fallbackReason = $this->differ->textFallbackReason($old, $new, $options, $language);
        if ($fallbackReason === null && !$this->hasDisplayChanges($old, $new, $language, $options)) {
            return $this->statusFile($language, $path, 'unchanged');
        }

        $displayLanguage = $fallbackReason === null ? $language : 'Text (' . $fallbackReason . ')';
        $displayOptions = $options;
        if ($fallbackReason !== null) {
            $displayOptions['semanticHighlights'] = false;
        }
        [$alignedLines, $chunks] = $this->changedSections($old, $new, $displayOptions);

        $file = [];
        if ($alignedLines !== []) {
            $file['aligned_lines'] = $alignedLines;
        }
        if ($chunks !== []) {
            $file['chunks'] = $chunks;
        }
        $file['language'] = $displayLanguage;
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
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function hasDisplayChanges(string $old, string $new, string $language, array $options): bool
    {
        if ($this->isPlainTextLanguage($language, $options)) {
            return $old !== $new;
        }

        return $this->differ->hasChanges($old, $new, $options);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isPlainTextLanguage(string $language, array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? $language)), ['plain', 'plain-text', 'plaintext', 'text'], true);
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return array{0:list<array{0:?int, 1:?int}>, 1:list<list<array<string, mixed>>>}
     */
    private function changedSections(string $old, string $new, array $options): array
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);
        $multilineAtomChanges = $this->pairedMultilineAtomLineChanges($old, $new, $options);
        $alignedLines = [];
        $chunks = [];
        $chunk = [];
        $pending = [];
        $unchangedGap = 0;
        foreach ($this->diffLines($oldLines, $newLines) as $op) {
            if ($op['op'] === '=') {
                $this->flushChangedLineOps($pending, $oldLines, $newLines, $options, $multilineAtomChanges, $alignedLines, $chunk);
                $alignedLines[] = [$op['old'], $op['new']];
                if ($chunk !== []) {
                    $unchangedGap++;
                }
                continue;
            }

            if ($chunk !== [] && $unchangedGap >= self::MAX_HUNK_LINE_DISTANCE) {
                $chunks[] = $chunk;
                $chunk = [];
            }
            $unchangedGap = 0;
            $pending[] = $op;
        }

        $this->flushChangedLineOps($pending, $oldLines, $newLines, $options, $multilineAtomChanges, $alignedLines, $chunk);
        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return [$alignedLines, $chunks];
    }

    /**
     * @param list<array<string, mixed>> $pending
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param array{lhs:array<int, list<array{start:int, end:int, content:string, highlight:string}>>, rhs:array<int, list<array{start:int, end:int, content:string, highlight:string}>>} $multilineAtomChanges
     * @param list<array{0:?int, 1:?int}> $alignedLines
     * @param list<array<string, mixed>> $chunk
     */
    private function flushChangedLineOps(array &$pending, array $oldLines, array $newLines, array $options, array $multilineAtomChanges, array &$alignedLines, array &$chunk): void
    {
        if ($pending === []) {
            return;
        }

        $deleted = array_values(array_filter($pending, static fn (array $op): bool => $op['op'] === '-'));
        $inserted = array_values(array_filter($pending, static fn (array $op): bool => $op['op'] === '+'));
        $lineCount = max(count($deleted), count($inserted));
        for ($index = 0; $index < $lineCount; $index++) {
            $oldLineNumber = $deleted[$index]['old'] ?? null;
            $newLineNumber = $inserted[$index]['new'] ?? null;
            $alignedLines[] = [$oldLineNumber, $newLineNumber];

            $line = [];
            $pairedChanges = null;
            if ($oldLineNumber !== null && $newLineNumber !== null) {
                $pairedChanges = $this->pairedSideChanges(
                    $oldLineNumber,
                    $newLineNumber,
                    $oldLines[$oldLineNumber],
                    $newLines[$newLineNumber],
                    $options,
                    $multilineAtomChanges,
                );
            }

            if ($oldLineNumber !== null) {
                $line['lhs'] = $pairedChanges === null
                    ? $this->side(
                        $oldLineNumber,
                        $oldLines[$oldLineNumber],
                        null,
                        'lhs',
                        $options,
                        $multilineAtomChanges['lhs'],
                    )
                    : $this->sideFromChanges($oldLineNumber, $pairedChanges['lhs']);
            }
            if ($newLineNumber !== null) {
                $line['rhs'] = $pairedChanges === null
                    ? $this->side(
                        $newLineNumber,
                        $newLines[$newLineNumber],
                        null,
                        'rhs',
                        $options,
                        $multilineAtomChanges['rhs'],
                    )
                    : $this->sideFromChanges($newLineNumber, $pairedChanges['rhs']);
            }

            $chunk[] = $line;
        }

        $pending = [];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param array{lhs:array<int, list<array{start:int, end:int, content:string, highlight:string}>>, rhs:array<int, list<array{start:int, end:int, content:string, highlight:string}>>} $multilineAtomChanges
     * @return array{lhs:list<array{start:int, end:int, content:string, highlight:string}>, rhs:list<array{start:int, end:int, content:string, highlight:string}>}
     */
    private function pairedSideChanges(int $oldLineNumber, int $newLineNumber, string $oldLine, string $newLine, array $options, array $multilineAtomChanges): array
    {
        $lhsChanges = $multilineAtomChanges['lhs'][$oldLineNumber]
            ?? $this->pairedLineChanges($oldLine, $newLine, 'lhs', $options);
        $rhsChanges = $multilineAtomChanges['rhs'][$newLineNumber]
            ?? $this->pairedLineChanges($newLine, $oldLine, 'rhs', $options);

        if ($lhsChanges === [] && $rhsChanges === [] && $oldLine !== $newLine) {
            $lhsChanges = $this->wholeLineChanges($oldLine, $options);
            $rhsChanges = $this->wholeLineChanges($newLine, $options);
        }

        return ['lhs' => $lhsChanges, 'rhs' => $rhsChanges];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param array<int, list<array{start:int, end:int, content:string, highlight:string}>> $multilineAtomChanges
     * @return array{line_number:int, changes:list<array{start:int, end:int, content:string, highlight:string}>}
     */
    private function side(int $lineNumber, string $line, ?string $otherLine, string $side, array $options, array $multilineAtomChanges = []): array
    {
        $changes = $multilineAtomChanges[$lineNumber] ?? null;
        if ($changes === null) {
            $changes = $otherLine === null
                ? $this->fullLineChanges($line, $options)
                : $this->pairedLineChanges($line, $otherLine, $side, $options);
        }

        $changes = $otherLine === null ? $this->fillEmptyWholeLineChanges($line, $changes) : $changes;

        return $this->sideFromChanges($lineNumber, $changes);
    }

    /**
     * @param list<array{start:int, end:int, content:string, highlight:string}> $changes
     * @return array{line_number:int, changes:list<array{start:int, end:int, content:string, highlight:string}>}
     */
    private function sideFromChanges(int $lineNumber, array $changes): array
    {
        return [
            'line_number' => $lineNumber,
            'changes' => $changes,
        ];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return list<array{start:int, end:int, content:string, highlight:string}>
     */
    private function wholeLineChanges(string $line, array $options): array
    {
        return $this->fillEmptyWholeLineChanges($line, $this->fullLineChanges($line, $options));
    }

    /**
     * @param list<array{start:int, end:int, content:string, highlight:string}> $changes
     * @return list<array{start:int, end:int, content:string, highlight:string}>
     */
    private function fillEmptyWholeLineChanges(string $line, array $changes): array
    {
        if ($changes !== [] || $line === '') {
            return $changes;
        }

        return [[
            'start' => 0,
            'end' => strlen($line),
            'content' => $line,
            'highlight' => 'normal',
        ]];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return array{lhs:array<int, list<array{start:int, end:int, content:string, highlight:string}>>, rhs:array<int, list<array{start:int, end:int, content:string, highlight:string}>>}
     */
    private function pairedMultilineAtomLineChanges(string $old, string $new, array $options): array
    {
        $result = ['lhs' => [], 'rhs' => []];
        $oldAtoms = $this->multilineAtoms($old, $options);
        $newAtoms = $this->multilineAtoms($new, $options);
        $pairCount = min(count($oldAtoms), count($newAtoms));

        for ($index = 0; $index < $pairCount; $index++) {
            $oldAtom = $oldAtoms[$index];
            $newAtom = $newAtoms[$index];
            if ($oldAtom->kind !== $newAtom->kind) {
                continue;
            }

            $this->appendLineChanges(
                $result['lhs'],
                $this->multilineAtomWordChanges($old, $oldAtom, $newAtom->text),
            );
            $this->appendLineChanges(
                $result['rhs'],
                $this->multilineAtomWordChanges($new, $newAtom, $oldAtom->text),
            );
        }

        return $result;
    }

    /**
     * @param array{ignoreComments?: bool, language?: string} $options
     * @return list<Token>
     */
    private function multilineAtoms(string $source, array $options): array
    {
        $tokens = [];
        foreach ($this->differ->tokenize($source, $options) as $token) {
            if (($options['ignoreComments'] ?? false) === true && $token->kind === 'comment') {
                continue;
            }

            if (
                !in_array($token->kind, ['comment', 'string'], true)
                || (!str_contains($token->text, "\n") && $token->delimiterRole !== 'block-scalar')
            ) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @return array<int, list<array{start:int, end:int, content:string, highlight:string}>>
     */
    private function multilineAtomWordChanges(string $source, Token $target, string $oppositeText): array
    {
        $wordOps = $this->differ->diffWords($target->text, $oppositeText, ['splitNumbers' => true]);
        if (!$this->atomWordDiffHasCommonWords($wordOps)) {
            if ($target->delimiterRole === 'block-scalar') {
                return $this->blockScalarLineChanges($source, $target);
            }

            return [];
        }

        $lineStarts = $this->lineStartOffsets($source);
        $changes = [];
        $cursor = 0;
        $highlight = $target->kind === 'comment' ? 'comment' : 'string';

        foreach ($wordOps as $op) {
            if ($op['op'] === '+') {
                continue;
            }

            $relativePosition = strpos($target->text, $op['text'], $cursor);
            if ($relativePosition === false) {
                continue;
            }

            $cursor = $relativePosition + strlen($op['text']);
            if ($this->isAllWhitespace($op['text'])) {
                continue;
            }

            $start = $target->start + $relativePosition;
            $end = $start + strlen($op['text']);
            $lineNumber = $this->lineNumberForOffset($lineStarts, $start);
            $lineStart = $lineStarts[$lineNumber];
            $changes[$lineNumber][] = $this->byteSpanChange($source, $start, $end, $lineStart, $highlight);
        }

        return $changes;
    }

    /**
     * @return array<int, list<array{start:int, end:int, content:string, highlight:string}>>
     */
    private function blockScalarLineChanges(string $source, Token $target): array
    {
        $lineStarts = $this->lineStartOffsets($source);
        $changes = [];
        $offset = $target->start;

        while ($offset < $target->end) {
            $newline = strpos($source, "\n", $offset);
            $lineEnd = $newline === false || $newline > $target->end ? $target->end : $newline;
            if ($lineEnd > $offset && $source[$lineEnd - 1] === "\r") {
                $lineEnd--;
            }

            $line = substr($source, $offset, $lineEnd - $offset);
            $contentStart = $offset + strspn($line, " \t");
            $contentEnd = $offset + strlen(rtrim($line, " \t"));
            if ($contentStart < $contentEnd) {
                $lineNumber = $this->lineNumberForOffset($lineStarts, $contentStart);
                $lineStart = $lineStarts[$lineNumber];
                $changes[$lineNumber][] = $this->byteSpanChange($source, $contentStart, $contentEnd, $lineStart, 'string');
            }

            if ($newline === false || $newline + 1 >= $target->end) {
                break;
            }

            $offset = $newline + 1;
        }

        return $changes;
    }

    /**
     * @return list<int>
     */
    private function lineStartOffsets(string $source): array
    {
        $starts = [0];
        $offset = 0;
        while (($newline = strpos($source, "\n", $offset)) !== false) {
            $starts[] = $newline + 1;
            $offset = $newline + 1;
        }

        return $starts;
    }

    /**
     * @param list<int> $lineStarts
     */
    private function lineNumberForOffset(array $lineStarts, int $offset): int
    {
        $lineNumber = 0;
        foreach ($lineStarts as $index => $lineStart) {
            if ($lineStart > $offset) {
                break;
            }

            $lineNumber = $index;
        }

        return $lineNumber;
    }

    /**
     * @param array<int, list<array{start:int, end:int, content:string, highlight:string}>> $target
     * @param array<int, list<array{start:int, end:int, content:string, highlight:string}>> $source
     */
    private function appendLineChanges(array &$target, array $source): void
    {
        foreach ($source as $lineNumber => $changes) {
            foreach ($changes as $change) {
                $target[$lineNumber][] = $change;
            }

            usort(
                $target[$lineNumber],
                static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']],
            );
        }
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
        $oppositeOp = $side === 'lhs' ? '+' : '-';
        $changes = [];
        $cursor = 0;
        $pending = [];

        foreach ($this->differ->diff($oldLine, $newLine, $options) as $op) {
            if ($op['op'] !== '=') {
                $pending[] = $op;
                continue;
            }

            $this->flushChangedTokenOps($pending, $line, $targetOp, $oppositeOp, $options, $cursor, $changes);

            $position = $this->findFrom($line, $op['text'], $cursor);
            if ($position === null) {
                continue;
            }

            $cursor = $position + strlen($op['text']);
        }

        $this->flushChangedTokenOps($pending, $line, $targetOp, $oppositeOp, $options, $cursor, $changes);

        return $changes;
    }

    /**
     * @param list<array{op:string, text:string}> $pending
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{start:int, end:int, content:string, highlight:string}> $changes
     */
    private function flushChangedTokenOps(array &$pending, string $line, string $targetOp, string $oppositeOp, array $options, int &$cursor, array &$changes): void
    {
        if ($pending === []) {
            return;
        }

        $oppositeOps = array_values(array_filter(
            $pending,
            static fn (array $op): bool => $op['op'] === $oppositeOp,
        ));
        $usedOppositeIndexes = [];

        foreach ($pending as $op) {
            if ($op['op'] !== $targetOp) {
                continue;
            }

            $position = $this->findFrom($line, $op['text'], $cursor);
            if ($position === null) {
                continue;
            }

            $end = $position + strlen($op['text']);
            $highlight = $this->highlightForSpan($line, $position, $end, $options);
            $oppositeText = $this->matchingSplittableOpposite($op['text'], $oppositeOps, $usedOppositeIndexes, $options);
            if ($oppositeText !== null) {
                $wordChanges = $this->splitAtomWordChanges($line, $op['text'], $oppositeText, $position, $highlight);
                if ($wordChanges !== null) {
                    foreach ($wordChanges as $wordChange) {
                        $changes[] = $wordChange;
                    }
                    $cursor = $end;
                    continue;
                }
            }

            $changes[] = $this->byteSpanChange($line, $position, $end, 0, $highlight);
            $cursor = $end;
        }

        $pending = [];
    }

    /**
     * @param list<array{op:string, text:string}> $oppositeOps
     * @param array<int, bool> $usedOppositeIndexes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function matchingSplittableOpposite(string $targetText, array $oppositeOps, array &$usedOppositeIndexes, array $options): ?string
    {
        $targetHighlight = $this->highlightFor($targetText, $options);
        if (!in_array($targetHighlight, ['comment', 'string'], true)) {
            return null;
        }

        foreach ($oppositeOps as $index => $oppositeOp) {
            if (($usedOppositeIndexes[$index] ?? false) === true) {
                continue;
            }

            if ($this->highlightFor($oppositeOp['text'], $options) !== $targetHighlight) {
                continue;
            }

            $wordOps = $this->differ->diffWords($targetText, $oppositeOp['text'], ['splitNumbers' => true]);
            if (!$this->atomWordDiffHasCommonWords($wordOps)) {
                continue;
            }

            $usedOppositeIndexes[$index] = true;

            return $oppositeOp['text'];
        }

        return null;
    }

    /**
     * @return ?list<array{start:int, end:int, content:string, highlight:string}>
     */
    private function splitAtomWordChanges(string $line, string $targetText, string $oppositeText, int $start, string $highlight): ?array
    {
        $wordOps = $this->differ->diffWords($targetText, $oppositeText, ['splitNumbers' => true]);
        if (!$this->atomWordDiffHasCommonWords($wordOps)) {
            return null;
        }

        $changes = [];
        $cursor = $start;
        foreach ($wordOps as $op) {
            if ($op['op'] === '+') {
                continue;
            }

            $position = $this->findFrom($line, $op['text'], $cursor);
            if ($position === null) {
                continue;
            }

            $end = $position + strlen($op['text']);
            if ($op['op'] === '=' || !$this->isAllWhitespace($op['text'])) {
                $changes[] = $this->byteSpanChange($line, $position, $end, 0, $highlight);
            }
            $cursor = $end;
        }

        return $changes === [] ? null : $changes;
    }

    /**
     * @param list<array{op:string, text:string}> $wordOps
     */
    private function atomWordDiffHasCommonWords(array $wordOps): bool
    {
        $novelCount = 0;
        $unchangedCount = 0;

        foreach ($wordOps as $op) {
            if ($op['op'] === '=') {
                if ($op['text'] !== ' ') {
                    $unchangedCount++;
                }
                continue;
            }

            $novelCount++;
        }

        return $unchangedCount > 2 && $unchangedCount * 2 >= $novelCount;
    }

    private function isAllWhitespace(string $text): bool
    {
        return preg_match('/^\s*$/u', $text) === 1;
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
            $changes[] = $this->byteSpanChange($line, $position, $end, 0, $this->highlightForSpan($line, $position, $end, $options));
            $cursor = $end;
        }

        return $changes;
    }

    /**
     * Difftastic serializes JSON display columns as byte offsets, and slices
     * content by those same byte ranges. Keeping that centralized avoids
     * accidentally mixing UTF-8 character counts with byte positions.
     *
     * @return array{start:int, end:int, content:string, highlight:string}
     */
    private function byteSpanChange(string $source, int $absoluteStart, int $absoluteEnd, int $baseOffset, string $highlight): array
    {
        return [
            'start' => $absoluteStart - $baseOffset,
            'end' => $absoluteEnd - $baseOffset,
            'content' => substr($source, $absoluteStart, $absoluteEnd - $absoluteStart),
            'highlight' => $highlight,
        ];
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function highlightFor(string $text, array $options): string
    {
        $tokens = $this->differ->tokenize($text, $options);
        if ($tokens === []) {
            return 'normal';
        }

        return $this->highlightClassifier->highlightForToken($text, $tokens[0], $options);
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function highlightForSpan(string $source, int $start, int $end, array $options): string
    {
        foreach ($this->differ->syntaxErrorSpans($source, $options) as $span) {
            if ($span['start'] < $end && $span['end'] > $start) {
                return 'tree_sitter_error';
            }
        }

        $text = substr($source, $start, $end - $start);
        $tokens = $this->differ->tokenize($text, $options);
        if ($tokens === []) {
            return 'normal';
        }

        $token = $tokens[0];

        return $this->highlightClassifier->highlightForToken(
            $source,
            new Token(
                $token->kind,
                $token->text,
                $token->delimiterRole,
                $token->depth,
                $start + $token->start,
                $start + $token->end,
            ),
            $options,
        );
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
        return (new LineDiffer())->diff($oldLines, $newLines);
    }
}
