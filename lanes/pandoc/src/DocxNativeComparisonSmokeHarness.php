<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxNativeComparisonSmokeHarness
{
    private const DEFAULT_MAX_EXAMPLES = 5;

    /** @var list<string> */
    private const TEXT_NODE_TYPES = [
        'text',
        'code',
        'code_block',
        'math',
        'raw_block',
        'raw_html',
        'raw_markdown',
        'raw_tex',
        'raw_inline',
        'raw_html_inline',
        'raw_tex_inline',
    ];

    /** @var list<string> */
    private const BLOCKISH_NODE_TYPES = [
        'blockquote',
        'bullet_list',
        'definition',
        'definition_item',
        'definition_list',
        'div',
        'figure',
        'heading',
        'line',
        'line_block',
        'list_item',
        'ordered_list',
        'paragraph',
        'plain',
        'table',
        'table_body',
        'table_cell',
        'table_foot',
        'table_head',
        'table_row',
        'term',
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $docxDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($docxDirectory)) {
            return [
                'status' => 'skipped',
                'reason' => 'upstream-cache-missing',
                'upstreamDocxDirectory' => $docxDirectory,
                'docxArtifactCount' => 0,
                'nativeArtifactCount' => 0,
                'totalPairCount' => 0,
                'comparedPairCount' => 0,
                'docxParsedCount' => 0,
                'nativeParsedCount' => 0,
                'bothParsedCount' => 0,
                'parseFailureCount' => 0,
                'sameTextCount' => 0,
                'sameTopTypeSequenceCount' => 0,
                'semanticGapPairCount' => 0,
                'knownSemanticGapCategories' => [],
                'parseFailures' => [],
                'comparisons' => [],
            ];
        }

        $docxFiles = $this->filesByBasename($docxDirectory, 'docx');
        $nativeFiles = $this->filesByBasename($docxDirectory, 'native');
        $pairNames = array_values(array_intersect(array_keys($docxFiles), array_keys($nativeFiles)));
        sort($pairNames);

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $docxParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $sameTextCount = 0;
        $sameTopTypeSequenceCount = 0;
        $semanticGapPairCount = 0;
        $parseFailures = [];
        $comparisons = [];
        $categoryCounts = [];

        foreach ($pairNames as $pairName) {
            $docxResult = $this->readDocx($docxFiles[$pairName]);
            if ($docxResult['ok']) {
                ++$docxParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$docxResult['ok'] || !$nativeResult['ok']) {
                $failure = [
                    'fixture' => $pairName,
                    'docxError' => $docxResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $parseFailures[] = $failure;
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $docxDocument */
            $docxDocument = $docxResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $docxText = $this->plainText($docxDocument);
            $nativeText = $this->plainText($nativeDocument);
            $docxTopTypes = $this->topTypeSequence($docxDocument);
            $nativeTopTypes = $this->topTypeSequence($nativeDocument);
            $sameText = $docxText === $nativeText;
            $sameTopTypes = $docxTopTypes === $nativeTopTypes;

            if ($sameText) {
                ++$sameTextCount;
            }
            if ($sameTopTypes) {
                ++$sameTopTypeSequenceCount;
            }

            $categories = [];
            if (!$sameText || !$sameTopTypes) {
                ++$semanticGapPairCount;
                $categories = $this->semanticGapCategories($pairName, $docxText, $sameText, $sameTopTypes);
                foreach ($categories as $category) {
                    $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
                }
            }

            if (count($comparisons) < $maxExamples) {
                $comparisons[] = [
                    'fixture' => $pairName,
                    'sameText' => $sameText,
                    'sameTopTypeSequence' => $sameTopTypes,
                    'docxTopTypes' => $docxTopTypes,
                    'nativeTopTypes' => $nativeTopTypes,
                    'categories' => $categories,
                ];
            }
        }

        ksort($categoryCounts);

        return [
            'status' => 'completed',
            'reason' => null,
            'upstreamDocxDirectory' => $docxDirectory,
            'docxArtifactCount' => count($docxFiles),
            'nativeArtifactCount' => count($nativeFiles),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => count($pairNames),
            'docxParsedCount' => $docxParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'parseFailureCount' => count($parseFailures),
            'sameTextCount' => $sameTextCount,
            'sameTopTypeSequenceCount' => $sameTopTypeSequenceCount,
            'semanticGapPairCount' => $semanticGapPairCount,
            'knownSemanticGapCategories' => array_values($categoryCounts),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'comparisons' => $comparisons,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX/native smoke: ' . (string) $report['status'],
            'upstreamDocxDirectory=' . (string) $report['upstreamDocxDirectory'],
        ];

        if (($report['status'] ?? '') === 'skipped') {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');

            return implode("\n", $lines) . "\n";
        }

        $lines[] = sprintf(
            'artifacts: docx=%d native=%d totalPairs=%d comparedPairs=%d',
            (int) $report['docxArtifactCount'],
            (int) $report['nativeArtifactCount'],
            (int) $report['totalPairCount'],
            (int) $report['comparedPairCount'],
        );
        $lines[] = sprintf(
            'parsed: docx=%d native=%d both=%d parseFailures=%d',
            (int) $report['docxParsedCount'],
            (int) $report['nativeParsedCount'],
            (int) $report['bothParsedCount'],
            (int) $report['parseFailureCount'],
        );
        $lines[] = sprintf(
            'same: text=%d topTypeSequence=%d semanticGapPairs=%d',
            (int) $report['sameTextCount'],
            (int) $report['sameTopTypeSequenceCount'],
            (int) $report['semanticGapPairCount'],
        );

        $categories = $report['knownSemanticGapCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'knownSemanticGapCategories:';
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $examples = $category['examples'] ?? [];
                $exampleText = is_array($examples) && $examples !== []
                    ? ' examples=' . implode(',', array_map('strval', $examples))
                    : '';
                $lines[] = sprintf(
                    '- %s count=%d%s',
                    (string) ($category['category'] ?? 'unknown'),
                    (int) ($category['count'] ?? 0),
                    $exampleText,
                );
            }
        }

        return implode("\n", $lines) . "\n";
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
        ksort($files);

        return $files;
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readDocx(string $path): array
    {
        try {
            return ['ok' => true, 'document' => (new DocxReader())->readDocxFile($path), 'error' => null];
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

    private function plainText(AstNode $document): string
    {
        $parts = [];
        $this->appendPlainText($document, $parts);

        return trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '');
    }

    /**
     * @param list<string> $parts
     */
    private function appendPlainText(AstNode $node, array &$parts): void
    {
        if (in_array($node->type, self::TEXT_NODE_TYPES, true)) {
            $text = $node->attr('text', '');
            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }

            return;
        }

        if (in_array($node->type, ['linebreak', 'softbreak', 'space'], true)) {
            $parts[] = ' ';

            return;
        }

        $before = count($parts);
        foreach ($node->children as $child) {
            $this->appendPlainText($child, $parts);
        }

        if (count($parts) > $before && in_array($node->type, self::BLOCKISH_NODE_TYPES, true)) {
            $parts[] = "\n";
        }

        if (count($parts) === $before && $node->children === []) {
            $text = $node->attr('text', '');
            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @return list<string>
     */
    private function semanticGapCategories(string $fixture, string $docxText, bool $sameText, bool $sameTopTypes): array
    {
        $fixtureLower = strtolower($fixture);
        $categories = [];

        if (!$sameText && preg_match('/<\/?(?:w|wp|a|r|v|o):/i', $docxText) === 1) {
            $categories[] = 'raw-openxml-field-or-bookmark-markup';
        }

        $keywordCategories = [
            'comment-or-annotation-order' => ['comment'],
            'drawing-image-object-placeholders' => ['diagram', 'drawing', 'image', 'textbox', 'vml'],
            'field-bookmark-cross-reference-resolution' => ['anchor', 'bookmark', 'cross_reference', 'empty_field', 'field', 'instrtext', 'link', 'pageref'],
            'list-numbering-and-item-boundaries' => ['dummy_item', 'list', 'task_list'],
            'metadata-core-properties-normalization' => ['document-properties', 'metadata'],
            'section-header-footer-boundaries' => ['0_level_headers', 'header', 'footer', 'section'],
            'style-whitespace-normalization' => ['char_style', 'drop_cap', 'formatting', 'indentation', 'normalize', 'punctuation', 'style', 'tabs', 'unicode', 'verbatim'],
            'table-grid-caption-span' => ['table'],
            'tracked-change-revision-policy' => ['deletion', 'insertion', 'move', 'paragraph_insertion', 'scrubbed', 'track_changes'],
        ];

        foreach ($keywordCategories as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($fixtureLower, $needle)) {
                    $categories[] = $category;
                    break;
                }
            }
        }

        if (!$sameTopTypes) {
            $categories[] = 'block-structure-boundary';
        }
        if (!$sameText) {
            $categories[] = 'text-normalization';
        }

        $categories = array_values(array_unique($categories));
        if ($categories === []) {
            $categories[] = 'uncategorized-semantic-drift';
        }

        return $categories;
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
}
