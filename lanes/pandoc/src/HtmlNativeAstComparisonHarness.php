<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlNativeAstComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'normalized-ast-comparison-not-full-html-parity';
    private const CLAIM = 'Compares local PHP HTML reader output with paired .native fixtures by normalized AST shape; reader provenance, table review metadata, and NativeReader constructor provenance are excluded, but no upstream Haskell runner or full HTML5 tree-construction parity is asserted.';

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'attrConstructor' => true,
        'attrNative' => true,
        'alignmentConstructor' => true,
        'alignmentNative' => true,
        'caption' => true,
        'colSpanConstructor' => true,
        'colSpanNative' => true,
        'constructor' => true,
        'header' => true,
        'htmlAttributes' => true,
        'legacyTableCellBlocksNative' => true,
        'meta' => true,
        'native' => true,
        'nativeInlineConstructors' => true,
        'nativeInlineParts' => true,
        'rowSpanConstructor' => true,
        'rowSpanNative' => true,
        'sourceFormat' => true,
        'tableGeometry' => true,
        'widths' => true,
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $htmlDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($htmlDirectory)) {
            return $this->skippedReport($htmlDirectory, 'html-native-fixture-directory-missing');
        }

        $htmlFiles = $this->filesByBasename($htmlDirectory, 'html');
        $nativeFiles = $this->filesByBasename($htmlDirectory, 'native');
        $pairNames = array_values(array_intersect(array_keys($htmlFiles), array_keys($nativeFiles)));
        sort($pairNames, SORT_STRING);

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $htmlParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $categoryCounts = [];

        foreach ($pairNames as $pairName) {
            $htmlResult = $this->readHtml($htmlFiles[$pairName]);
            if ($htmlResult['ok']) {
                ++$htmlParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$htmlResult['ok'] || !$nativeResult['ok']) {
                $parseFailures[] = [
                    'fixture' => $pairName,
                    'htmlError' => $htmlResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $htmlDocument */
            $htmlDocument = $htmlResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $htmlAst = $this->normalizedNode($htmlDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($htmlAst === $nativeAst) {
                ++$matchCount;
                continue;
            }

            $difference = $this->firstDifference($htmlAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }
            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'htmlTopTypes' => $this->topTypeSequence($htmlDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedPairCount = count($pairNames);
        $mismatchCount = $bothParsedCount - $matchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-html-native-ast',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'html-native-normalized-ast-comparison',
            'upstreamHtmlDirectory' => $htmlDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'htmlParsedCount' => $htmlParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'parseFailureCount' => count($parseFailures),
            'normalizedAstMatchCount' => $matchCount,
            'normalizedAstMismatchCount' => $mismatchCount,
            'normalizedAstMatchPercent' => self::percent($matchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus(count($parseFailures), $mismatchCount, $comparedPairCount),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedPairCount,
                count($parseFailures),
                $matchCount,
                $mismatchCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc HTML/native AST comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamHtmlDirectory=' . (string) ($report['upstreamHtmlDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'pairs: total=%d compared=%d parsedBoth=%d parseFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown')
        );

        $mismatches = $report['mismatchComparisons'] ?? [];
        if (is_array($mismatches) && $mismatches !== []) {
            $lines[] = 'mismatchExamples:';
            foreach ($mismatches as $mismatch) {
                if (!is_array($mismatch)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($mismatch['fixture'] ?? 'unknown')
                    . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required HTML mapped parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['htmlParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $htmlDirectory, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-html-native-ast',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'html-native-normalized-ast-comparison',
            'upstreamHtmlDirectory' => $htmlDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalPairCount' => 0,
            'comparedPairCount' => 0,
            'htmlParsedCount' => 0,
            'nativeParsedCount' => 0,
            'bothParsedCount' => 0,
            'parseFailureCount' => 0,
            'normalizedAstMatchCount' => 0,
            'normalizedAstMismatchCount' => 0,
            'normalizedAstMatchPercent' => null,
            'astParityStatus' => 'not-evaluated-source-directory-unavailable',
            'parseFailures' => [],
            'mismatchComparisons' => [],
            'mismatchCategories' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type after table-cell Plain wrappers are normalized',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline text after adjacent text nodes are coalesced',
                'table head/body/body-row-header structure through rowHeadColumns',
            ],
            'excludes' => [
                'HTML reader document metadata and microdata review attrs',
                'NativeReader constructor provenance attrs',
                'table geometry review packets',
                'default table widths and empty captions/feet',
                'derived table-cell header flags when rowHeadColumns carries the semantic row-header contract',
            ],
            'doesNotAssert' => [
                'upstream Haskell/Cabal runner execution',
                'full HTML5 tree construction parity',
                'browser DOM repair behavior',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filesByBasename(string $directory, string $extension): array
    {
        $files = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
            $files[basename($path, '.' . $extension)] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readHtml(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read HTML fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new HtmlReader())->read($bytes), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new NativeReader())->read($native), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'table_cell'], true)) {
                continue;
            }
            if ($key === 'caption' && $value === '') {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            $normalizedValue = $this->normalizedValue($value);
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $this->normalizedChildren($node->children),
        ];
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        if (isset(self::IGNORED_ATTRS[$key]) && self::IGNORED_ATTRS[$key] === true) {
            return true;
        }

        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors');
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $node = $this->normalizedNode($child);
            if ($this->isEmptyTableFootNode($node)) {
                continue;
            }
            if ($node['type'] === 'plain') {
                foreach ($node['children'] as $plainChild) {
                    $this->appendNormalizedChild($normalized, $plainChild);
                }
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedChild(array &$normalized, array $node): void
    {
        $lastIndex = count($normalized) - 1;
        if ($lastIndex >= 0 && $this->isPlainTextNode($normalized[$lastIndex]) && $this->isPlainTextNode($node)) {
            $normalized[$lastIndex]['attrs']['text'] .= $node['attrs']['text'];
            return;
        }

        $normalized[] = $node;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? []) === []
            && ($node['children'] ?? []) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return is_float($value) ? round($value, 6) : $value;
        }

        $normalized = [];
        foreach ($value as $key => $child) {
            $normalized[$key] = $this->normalizedValue($child);
        }
        if (array_is_list($normalized)) {
            return $normalized;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function firstDifference(array $left, array $right, string $path = '$'): ?string
    {
        if ($left['type'] !== $right['type']) {
            return "{$path}.type html={$left['type']} native={$right['type']}";
        }
        if ($left['attrs'] !== $right['attrs']) {
            return "{$path}.attrs html=" . json_encode($left['attrs'], JSON_UNESCAPED_SLASHES)
                . ' native=' . json_encode($right['attrs'], JSON_UNESCAPED_SLASHES);
        }
        if (count($left['children']) !== count($right['children'])) {
            return "{$path}.children count html=" . count($left['children']) . ' native=' . count($right['children']);
        }
        foreach ($left['children'] as $index => $leftChild) {
            $difference = $this->firstDifference($leftChild, $right['children'][$index], $path . '.children[' . $index . ']');
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mismatchCategories(string $difference): array
    {
        $categories = [];
        if (str_contains($difference, '.type')) {
            $categories[] = 'node-type-difference';
        }
        if (str_contains($difference, '.attrs')) {
            $categories[] = 'attribute-difference';
        }
        if (str_contains($difference, '.children count')) {
            $categories[] = 'child-count-difference';
        }

        return $categories === [] ? ['normalized-ast-difference'] : array_values(array_unique($categories));
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @param array<string, array{category: string, count: int, examples: list<string>}> $categoryCounts
     */
    private function addCategory(array &$categoryCounts, string $category, string $fixture, int $maxExamples): void
    {
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = ['category' => $category, 'count' => 0, 'examples' => []];
        }

        ++$categoryCounts[$category]['count'];
        if (count($categoryCounts[$category]['examples']) < $maxExamples) {
            $categoryCounts[$category]['examples'][] = $fixture;
        }
    }

    private static function astParityStatus(int $parseFailureCount, int $mismatchCount, int $comparedPairCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-native-pairs';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $directoryPresent,
        int $comparedPairCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        $astEvidence = $directoryPresent
            ? "native pairs={$comparedPairCount}; parse failures={$parseFailureCount}; normalized matches={$matchCount}; normalized mismatches={$mismatchCount}"
            : 'HTML/native fixture directory absent; native AST comparison did not run';
        $astCovered = $directoryPresent
            && $comparedPairCount > 0
            && $parseFailureCount === 0
            && $mismatchCount === 0
            && $matchCount === $comparedPairCount;

        return [
            [
                'rank' => 1,
                'id' => 'checked-in-html-native-ast-equality',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $astEvidence,
                'evidenceRequired' => 'Compare every same-basename checked-in HTML/native fixture with zero parse failures and zero normalized AST mismatches.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-html-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'This harness compares checked-in HTML/native fixtures but does not run upstream Haskell/Tasty HTML reader tests.',
                'evidenceRequired' => 'Record reproducible upstream Tests.Readers.HTML runner results when a Haskell runner is available.',
            ],
            [
                'rank' => 3,
                'id' => 'full-html5-tree-construction-coverage',
                'status' => 'open',
                'currentEvidence' => "The current checked-in gate covers {$comparedPairCount} paired fixture(s).",
                'evidenceRequired' => 'Broaden fixture coverage across HTML5 parsing, DOM repair, raw HTML boundaries, metadata, tables, and inline semantics.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s]: %s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $count, int $total): ?float
    {
        return $total === 0 ? null : round(($count / $total) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        return is_float($percent) || is_int($percent) ? number_format((float) $percent, 2) . '%' : 'n/a';
    }
}
