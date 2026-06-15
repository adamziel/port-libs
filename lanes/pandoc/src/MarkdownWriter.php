<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownWriter
{
    /** @var list<array{label:string, node:AstNode}> */
    private array $notes = [];

    /** @var array<string, bool> */
    private array $noteUsedLabels = [];

    /** @var list<array{label:string, url:string, title:string, attrs:array<string, mixed>}> */
    private array $references = [];

    /** @var array<string, int> */
    private array $referenceLabelUses = [];

    /** @var array<string, bool> */
    private array $referenceUsedLabels = [];

    /** @var array<string, string> */
    private array $referenceTargetLabels = [];

    /** @var array<string, string> */
    private array $abbreviationDefinitions = [];

    /** @var array<string, bool> */
    private array $numberedExampleLabels = [];

    /** @var array<string, string> */
    private array $yamlMetadataExplicitCollectionTags = [];

    /** @var array<string, string> */
    private array $yamlMetadataExplicitScalarTags = [];

    /** @var array<string, list<string>> */
    private array $yamlMetadataStandaloneCommentsByPath = [];

    /** @var array<string, list<string>> */
    private array $yamlMetadataTrailingCommentsByPath = [];

    private int $nextNoteNumber = 1;

    private int $lastReferenceIndex = 0;

    private int $fancyOrderedMarkerEscapeSuppression = 0;

    private int $plainTextTriggerEscapeSuppression = 0;

    /**
     * @param array{format?: string, extensions?: mixed, setextHeadings?: bool, referenceLinks?: bool, referenceLocation?: string, bulletListMarker?: string, softBreak?: string, yamlMetadata?: bool, rawHtml?: bool, rawTex?: bool, rawMarkdown?: bool, fencedCodeBlockStyle?: string, fencedCodeBlocks?: bool, htmlTableAutoFallback?: bool, autoHtmlTables?: bool, semanticTableHtmlFallback?: bool, tableStyle?: string, markdownTableFormat?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Markdown writer expects a document node');
        }

        $this->notes = [];
        $this->noteUsedLabels = [];
        $this->references = [];
        $this->referenceLabelUses = [];
        $this->referenceUsedLabels = [];
        $this->referenceTargetLabels = [];
        $this->abbreviationDefinitions = [];
        $this->numberedExampleLabels = $this->collectNumberedExampleLabels($document);
        $this->yamlMetadataExplicitCollectionTags = [];
        $this->yamlMetadataExplicitScalarTags = [];
        $this->yamlMetadataStandaloneCommentsByPath = [];
        $this->yamlMetadataTrailingCommentsByPath = [];
        $this->nextNoteNumber = 1;
        $this->lastReferenceIndex = 0;
        $this->fancyOrderedMarkerEscapeSuppression = 0;
        $this->plainTextTriggerEscapeSuppression = 0;

        $blocks = [];
        if ($this->yamlMetadataEnabled()) {
            $this->yamlMetadataExplicitCollectionTags = $this->yamlMetadataExplicitCollectionTags(
                $document->attr('yamlMetadataCollectionProvenance', [])
            );
            $this->yamlMetadataExplicitScalarTags = $this->yamlMetadataExplicitScalarTags(
                $document->attr('yamlMetadataScalarProvenance', [])
            );
            $this->yamlMetadataStandaloneCommentsByPath = $this->yamlMetadataStandaloneCommentsByPath(
                $document->attr('yamlMetadataCommentProvenance', [])
            );
            $this->yamlMetadataTrailingCommentsByPath = $this->yamlMetadataTrailingCommentsByPath(
                $document->attr('yamlMetadataCommentProvenance', [])
            );
            $metadataBlock = $this->renderYamlMetadataBlock($document->attr('meta', []));
            if ($metadataBlock !== []) {
                $blocks[] = implode("\n", $metadataBlock);
            }
        }

        foreach ($document->children as $index => $node) {
            if ($this->referenceLocation() === 'end_of_section' && $node->type === 'heading' && $index > 0) {
                $this->appendPendingDefinitions($blocks);
            }

            if ($index > 0 && $this->needsBlockSeparator($document->children[$index - 1], $node)) {
                $blocks[] = '<!-- -->';
            }

            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }

            if ($this->referenceLocation() === 'end_of_block') {
                $this->appendPendingDefinitions($blocks);
            }
        }
        $this->appendPendingDefinitions($blocks);

        return implode("\n\n", $blocks);
    }

    /**
     * @return list<string>
     */
    private function renderYamlMetadataBlock(mixed $metadata): array
    {
        $metadata = $this->yamlMetadataForWriting($metadata);
        if ($metadata === []) {
            return [];
        }

        $lines = ['---'];
        foreach ($metadata as $key => $value) {
            $path = $this->yamlMetadataPathWithSegment('', (string) $key);
            $this->appendYamlMetadataMappingLines($lines, (string) $key, $value, 0, $path);
        }
        $lines[] = '...';

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function yamlMetadataExplicitCollectionTags(mixed $provenance): array
    {
        if (!is_array($provenance)) {
            return [];
        }

        $tags = [];
        foreach ($provenance as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? '') !== 'yaml-collection') {
                continue;
            }

            $path = $entry['path'] ?? null;
            $tag = $entry['explicitTag'] ?? null;
            if (
                !is_string($path)
                || $path === ''
                || !is_string($tag)
            ) {
                continue;
            }

            if (in_array($tag, ['omap', 'pairs'], true) && ($entry['kind'] ?? '') === 'sequence') {
                $tags[$path] = $tag;
                continue;
            }

            if ($tag === 'set' && ($entry['kind'] ?? '') === 'mapping') {
                $tags[$path] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @return array<string, string>
     */
    private function yamlMetadataExplicitScalarTags(mixed $provenance): array
    {
        if (!is_array($provenance)) {
            return [];
        }

        $tags = [];
        foreach ($provenance as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? '') !== 'yaml-typed-scalar') {
                continue;
            }

            $path = $entry['path'] ?? null;
            if (
                !is_string($path)
                || $path === ''
                || ($entry['explicitTag'] ?? '') !== 'binary'
                || ($entry['scalarType'] ?? '') !== 'binary'
                || ($entry['valueKind'] ?? '') !== 'scalar'
            ) {
                continue;
            }

            $tags[$path] = 'binary';
        }

        return $tags;
    }

    /**
     * @return array<string, list<string>>
     */
    private function yamlMetadataStandaloneCommentsByPath(mixed $provenance): array
    {
        if (!is_array($provenance)) {
            return [];
        }

        $comments = [];
        foreach ($provenance as $entry) {
            if (
                !is_array($entry)
                || ($entry['type'] ?? '') !== 'yaml-comment'
                || ($entry['context'] ?? '') !== 'standalone'
            ) {
                continue;
            }

            $path = $entry['path'] ?? null;
            $comment = $entry['comment'] ?? null;
            if (!is_string($path) || $path === '' || !is_string($comment)) {
                continue;
            }

            $comment = trim($comment);
            if (!$this->isWritableYamlMetadataComment($comment)) {
                continue;
            }

            $comments[$path] ??= [];
            $comments[$path][] = $comment;
        }

        return $comments;
    }

    /**
     * @return array<string, list<string>>
     */
    private function yamlMetadataTrailingCommentsByPath(mixed $provenance): array
    {
        if (!is_array($provenance)) {
            return [];
        }

        $comments = [];
        foreach ($provenance as $entry) {
            if (
                !is_array($entry)
                || ($entry['type'] ?? '') !== 'yaml-comment'
                || ($entry['context'] ?? '') !== 'trailing'
            ) {
                continue;
            }

            $path = $entry['path'] ?? null;
            $comment = $entry['comment'] ?? null;
            if (!is_string($path) || $path === '' || !is_string($comment)) {
                continue;
            }

            $comment = trim($comment);
            if (!$this->isWritableYamlMetadataComment($comment)) {
                continue;
            }

            $comments[$path] ??= [];
            $comments[$path][] = $comment;
        }

        return $comments;
    }

    private function isWritableYamlMetadataComment(string $comment): bool
    {
        return $comment !== ''
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\r\n]/', $comment) !== 1;
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataStandaloneComments(array &$lines, string $path, int $indent): void
    {
        foreach ($this->yamlMetadataStandaloneCommentsByPath[$path] ?? [] as $comment) {
            $lines[] = str_repeat(' ', $indent) . '# ' . $comment;
        }
    }

    private function yamlMetadataTrailingCommentSuffix(string $path): string
    {
        $comments = $this->yamlMetadataTrailingCommentsByPath[$path] ?? [];
        $comment = $comments === [] ? null : $comments[count($comments) - 1];

        return is_string($comment) ? ' # ' . $comment : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function yamlMetadataForWriting(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }

        $filtered = [];
        foreach ($metadata as $key => $value) {
            $field = (string) $key;
            if (
                $field === ''
                || str_ends_with($field, 'Inlines')
                || str_ends_with($field, '_')
                || str_starts_with($field, '__yamlMetadata')
                || ($field === 'authors' && array_key_exists('author', $metadata) && $metadata['author'] === $value)
                || !$this->isYamlMetadataWritableValue($value)
            ) {
                continue;
            }

            $filtered[$field] = $value;
        }

        return $filtered;
    }

    private function isYamlMetadataWritableValue(mixed $value): bool
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (!is_int($key) && !is_string($key)) {
                return false;
            }

            if (!$this->isYamlMetadataWritableValue($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataMappingLines(array &$lines, string $key, mixed $value, int $indent, string $path): void
    {
        $prefix = str_repeat(' ', $indent) . $this->formatYamlMetadataKey($key);
        if (!$this->isYamlMetadataCollection($value)) {
            $this->appendYamlMetadataStandaloneComments($lines, $path, $indent);
            $this->appendYamlMetadataScalarMappingLines($lines, $prefix . ':', $value, $indent + 2, $path);
            return;
        }

        $orderedPairTag = $this->yamlMetadataOrderedPairTagForPath($path, $value);
        if ($orderedPairTag !== null) {
            $lines[] = $prefix . ': !!' . $orderedPairTag;
            $this->appendYamlMetadataOrderedPairSequenceLines($lines, $value, $indent + 2, $path);
            return;
        }

        if ($this->yamlMetadataSetTagForPath($path, $value)) {
            if ($value === []) {
                $lines[] = $prefix . ': !!set {}';
                return;
            }

            $lines[] = $prefix . ': !!set';
            $this->appendYamlMetadataSetLines($lines, $value, $indent + 2);
            return;
        }

        if ($value === []) {
            $lines[] = $prefix . ': []';
            return;
        }

        $lines[] = $prefix . ':';
        $this->appendYamlMetadataValueLines($lines, $value, $indent + 2, $path);
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataValueLines(array &$lines, mixed $value, int $indent, string $path): void
    {
        if (!$this->isYamlMetadataCollection($value)) {
            $this->appendYamlMetadataScalarValueLines($lines, $value, $indent, $path);
            return;
        }

        $orderedPairTag = $this->yamlMetadataOrderedPairTagForPath($path, $value);
        if ($orderedPairTag !== null) {
            $lines[] = str_repeat(' ', $indent) . '!!' . $orderedPairTag;
            $this->appendYamlMetadataOrderedPairSequenceLines($lines, $value, $indent, $path);
            return;
        }

        if ($this->yamlMetadataSetTagForPath($path, $value)) {
            if ($value === []) {
                $lines[] = str_repeat(' ', $indent) . '!!set {}';
                return;
            }

            $lines[] = str_repeat(' ', $indent) . '!!set';
            $this->appendYamlMetadataSetLines($lines, $value, $indent);
            return;
        }

        if (!array_is_list($value)) {
            foreach ($value as $key => $item) {
                $itemPath = $this->yamlMetadataPathWithSegment($path, (string) $key);
                $this->appendYamlMetadataMappingLines($lines, (string) $key, $item, $indent, $itemPath);
            }
            return;
        }

        $prefix = str_repeat(' ', $indent);
        foreach ($value as $index => $item) {
            $itemPath = $this->yamlMetadataPathWithSegment($path, $index);
            if (!$this->isYamlMetadataCollection($item)) {
                $this->appendYamlMetadataStandaloneComments($lines, $itemPath, $indent);
                $this->appendYamlMetadataScalarListItemLines($lines, $item, $indent, $itemPath);
                continue;
            }

            $orderedPairTag = $this->yamlMetadataOrderedPairTagForPath($itemPath, $item);
            if ($orderedPairTag !== null) {
                $lines[] = $prefix . '- !!' . $orderedPairTag;
                $this->appendYamlMetadataOrderedPairSequenceLines($lines, $item, $indent + 2, $itemPath);
                continue;
            }

            if ($this->yamlMetadataSetTagForPath($itemPath, $item)) {
                if ($item === []) {
                    $lines[] = $prefix . '- !!set {}';
                    continue;
                }

                $lines[] = $prefix . '- !!set';
                $this->appendYamlMetadataSetLines($lines, $item, $indent + 2);
                continue;
            }

            if ($item === []) {
                $lines[] = $prefix . '- []';
                continue;
            }

            if (!array_is_list($item)) {
                $this->appendYamlMetadataMappingListItemLines($lines, $item, $indent, $itemPath);
                continue;
            }

            $lines[] = $prefix . '-';
            $this->appendYamlMetadataValueLines($lines, $item, $indent + 2, $itemPath);
        }
    }

    /**
     * @param array<string|int, mixed> $map
     * @param list<string> $lines
     */
    private function appendYamlMetadataMappingListItemLines(array &$lines, array $map, int $indent, string $path): void
    {
        $prefix = str_repeat(' ', $indent);
        $first = true;
        foreach ($map as $key => $value) {
            $field = $this->formatYamlMetadataKey((string) $key);
            $itemPath = $this->yamlMetadataPathWithSegment($path, (string) $key);
            if (!$this->isYamlMetadataCollection($value)) {
                $this->appendYamlMetadataStandaloneComments($lines, $itemPath, $first ? $indent : $indent + 2);
                $this->appendYamlMetadataScalarMappingLines(
                    $lines,
                    $prefix . ($first ? '- ' : '  ') . $field . ':',
                    $value,
                    $indent + 4,
                    $itemPath
                );
                $first = false;
                continue;
            }

            $orderedPairTag = $this->yamlMetadataOrderedPairTagForPath($itemPath, $value);
            if ($orderedPairTag !== null) {
                $lines[] = $prefix . ($first ? '- ' : '  ') . $field . ': !!' . $orderedPairTag;
                $this->appendYamlMetadataOrderedPairSequenceLines($lines, $value, $indent + 4, $itemPath);
                $first = false;
                continue;
            }

            if ($this->yamlMetadataSetTagForPath($itemPath, $value)) {
                if ($value === []) {
                    $lines[] = $prefix . ($first ? '- ' : '  ') . $field . ': !!set {}';
                    $first = false;
                    continue;
                }

                $lines[] = $prefix . ($first ? '- ' : '  ') . $field . ': !!set';
                $this->appendYamlMetadataSetLines($lines, $value, $indent + 4);
                $first = false;
                continue;
            }

            if ($value === []) {
                $lines[] = $prefix . ($first ? '- ' : '  ') . $field . ': []';
                $first = false;
                continue;
            }

            $lines[] = $prefix . ($first ? '- ' : '  ') . $field . ':';
            $this->appendYamlMetadataValueLines($lines, $value, $indent + 4, $itemPath);
            $first = false;
        }
    }

    /**
     * @param list<array{key:mixed, value:mixed}> $pairs
     * @param list<string> $lines
     */
    private function appendYamlMetadataOrderedPairSequenceLines(array &$lines, array $pairs, int $indent, string $path): void
    {
        foreach ($pairs as $index => $pair) {
            $key = $this->formatYamlMetadataKey((string) $pair['key']);
            $value = $pair['value'] ?? null;
            $pairPath = $this->yamlMetadataPathWithSegment($path, $index);
            $valuePath = $this->yamlMetadataPathWithSegment($pairPath, (string) $pair['key']);
            $prefix = str_repeat(' ', $indent) . '- ' . $key . ':';

            if (!$this->isYamlMetadataCollection($value)) {
                $this->appendYamlMetadataStandaloneComments($lines, $valuePath, $indent);
                $this->appendYamlMetadataScalarMappingLines($lines, $prefix, $value, $indent + 4, $valuePath);
                continue;
            }

            $orderedPairTag = $this->yamlMetadataOrderedPairTagForPath($valuePath, $value);
            if ($orderedPairTag !== null) {
                $lines[] = $prefix . ' !!' . $orderedPairTag;
                $this->appendYamlMetadataOrderedPairSequenceLines($lines, $value, $indent + 4, $valuePath);
                continue;
            }

            if ($this->yamlMetadataSetTagForPath($valuePath, $value)) {
                if ($value === []) {
                    $lines[] = $prefix . ' !!set {}';
                    continue;
                }

                $lines[] = $prefix . ' !!set';
                $this->appendYamlMetadataSetLines($lines, $value, $indent + 4);
                continue;
            }

            if ($value === []) {
                $lines[] = $prefix . ' []';
                continue;
            }

            $lines[] = $prefix;
            $this->appendYamlMetadataValueLines($lines, $value, $indent + 4, $valuePath);
        }
    }

    private function yamlMetadataOrderedPairTagForPath(string $path, mixed $value): ?string
    {
        $tag = $this->yamlMetadataExplicitCollectionTags[$path] ?? null;
        if (($tag !== 'omap' && $tag !== 'pairs') || !$this->isYamlMetadataOrderedPairSequence($value)) {
            return null;
        }

        return $tag;
    }

    private function yamlMetadataSetTagForPath(string $path, mixed $value): bool
    {
        return ($this->yamlMetadataExplicitCollectionTags[$path] ?? null) === 'set'
            && $this->isYamlMetadataSetMap($value);
    }

    private function isYamlMetadataSetMap(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string|int, null> $set
     * @param list<string> $lines
     */
    private function appendYamlMetadataSetLines(array &$lines, array $set, int $indent): void
    {
        $prefix = str_repeat(' ', $indent) . '? ';
        foreach (array_keys($set) as $key) {
            $lines[] = $prefix . $this->formatYamlMetadataKey((string) $key);
        }
    }

    private function isYamlMetadataOrderedPairSequence(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_array($item) || !array_key_exists('key', $item) || !array_key_exists('value', $item)) {
                return false;
            }
        }

        return true;
    }

    private function yamlMetadataPathWithSegment(string $path, int|string $segment): string
    {
        $escaped = str_replace(['~', '/'], ['~0', '~1'], (string) $segment);

        return $path === '' ? '/' . $escaped : $path . '/' . $escaped;
    }

    private function isYamlMetadataCollection(mixed $value): bool
    {
        return is_array($value);
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataScalarValueLines(array &$lines, mixed $value, int $indent, string $path): void
    {
        $binaryScalar = is_string($value) ? $this->formatYamlMetadataExplicitBinaryScalar($value, $path) : null;
        if ($binaryScalar !== null) {
            $lines[] = str_repeat(' ', $indent) . $binaryScalar
                . $this->yamlMetadataTrailingCommentSuffix($path);
            return;
        }

        if (is_string($value)) {
            $blockHeader = $this->yamlMetadataBlockScalarHeader($value);
            if ($blockHeader !== null) {
                $lines[] = str_repeat(' ', $indent) . $blockHeader
                    . $this->yamlMetadataTrailingCommentSuffix($path);
                $this->appendYamlMetadataBlockScalarLines($lines, $value, $indent + 2);
                return;
            }
        }

        $lines[] = str_repeat(' ', $indent) . $this->formatYamlMetadataScalar($value)
            . $this->yamlMetadataTrailingCommentSuffix($path);
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataScalarListItemLines(array &$lines, mixed $value, int $indent, string $path): void
    {
        $prefix = str_repeat(' ', $indent);
        $binaryScalar = is_string($value) ? $this->formatYamlMetadataExplicitBinaryScalar($value, $path) : null;
        if ($binaryScalar !== null) {
            $lines[] = $prefix . '- ' . $binaryScalar
                . $this->yamlMetadataTrailingCommentSuffix($path);
            return;
        }

        if (is_string($value)) {
            $blockHeader = $this->yamlMetadataBlockScalarHeader($value);
            if ($blockHeader !== null) {
                $lines[] = $prefix . '- ' . $blockHeader
                    . $this->yamlMetadataTrailingCommentSuffix($path);
                $this->appendYamlMetadataBlockScalarLines($lines, $value, $indent + 2);
                return;
            }
        }

        $lines[] = $prefix . '- ' . $this->formatYamlMetadataScalar($value)
            . $this->yamlMetadataTrailingCommentSuffix($path);
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataScalarMappingLines(
        array &$lines,
        string $mappingPrefix,
        mixed $value,
        int $blockIndent,
        string $path
    ): void
    {
        $binaryScalar = is_string($value) ? $this->formatYamlMetadataExplicitBinaryScalar($value, $path) : null;
        if ($binaryScalar !== null) {
            $lines[] = $mappingPrefix . ' ' . $binaryScalar
                . $this->yamlMetadataTrailingCommentSuffix($path);
            return;
        }

        if (is_string($value)) {
            $blockHeader = $this->yamlMetadataBlockScalarHeader($value);
            if ($blockHeader !== null) {
                $lines[] = $mappingPrefix . ' ' . $blockHeader
                    . $this->yamlMetadataTrailingCommentSuffix($path);
                $this->appendYamlMetadataBlockScalarLines($lines, $value, $blockIndent);
                return;
            }
        }

        $lines[] = $mappingPrefix . ' ' . $this->formatYamlMetadataScalar($value)
            . $this->yamlMetadataTrailingCommentSuffix($path);
    }

    private function formatYamlMetadataExplicitBinaryScalar(string $value, string $path): ?string
    {
        if (($this->yamlMetadataExplicitScalarTags[$path] ?? null) !== 'binary') {
            return null;
        }

        return '!!binary ' . $this->doubleQuoteYamlMetadataString(base64_encode($value));
    }

    private function formatYamlMetadataKey(string $key): string
    {
        if ($this->isPlainYamlMetadataKey($key)) {
            return $key;
        }

        return $this->doubleQuoteYamlMetadataString($key);
    }

    private function isPlainYamlMetadataKey(string $key): bool
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $key) !== 1) {
            return false;
        }

        return !$this->isYamlMetadataAmbiguousPlainScalar($key);
    }

    private function yamlMetadataBlockScalarHeader(string $value): ?string
    {
        if (!str_contains($value, "\n") || rtrim($value, "\n") === '') {
            return null;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\r]/', $value) === 1) {
            return null;
        }

        $trailingNewlines = strlen($value) - strlen(rtrim($value, "\n"));
        $indentIndicator = $this->yamlMetadataBlockScalarNeedsExplicitIndent($value) ? '2' : '';
        if ($trailingNewlines === 0) {
            return '|' . $indentIndicator . '-';
        }

        if ($trailingNewlines === 1) {
            return '|' . $indentIndicator;
        }

        return '|' . $indentIndicator . '+';
    }

    private function yamlMetadataBlockScalarNeedsExplicitIndent(string $value): bool
    {
        $body = rtrim($value, "\n");
        $sawIndentedContent = false;
        foreach (explode("\n", $body) as $line) {
            if ($line === '') {
                continue;
            }

            if (!str_starts_with($line, ' ')) {
                return false;
            }

            $sawIndentedContent = true;
        }

        return $sawIndentedContent;
    }

    /**
     * @param list<string> $lines
     */
    private function appendYamlMetadataBlockScalarLines(array &$lines, string $value, int $indent): void
    {
        $prefix = str_repeat(' ', $indent);
        $trailingNewlines = strlen($value) - strlen(rtrim($value, "\n"));
        $body = rtrim($value, "\n");

        foreach (explode("\n", $body) as $line) {
            $lines[] = $line === '' ? '' : $prefix . $line;
        }

        if ($trailingNewlines > 1) {
            for ($index = 0; $index < $trailingNewlines; $index++) {
                $lines[] = '';
            }
        }
    }

    private function formatYamlMetadataScalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;
        if ($this->isPlainYamlMetadataScalar($value)) {
            return $value;
        }

        return $this->doubleQuoteYamlMetadataString($value);
    }

    private function isPlainYamlMetadataScalar(string $value): bool
    {
        if ($value === '' || $this->isYamlMetadataAmbiguousPlainScalar($value)) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\r\n\t]/', $value) === 1) {
            return false;
        }

        if (preg_match('/^[A-Za-z0-9_\/.:%#?&=+@~-]+$/', $value) !== 1) {
            return false;
        }

        return !str_starts_with($value, '-')
            && !str_starts_with($value, '?')
            && !str_starts_with($value, ':')
            && !str_starts_with($value, '#')
            && !str_starts_with($value, '!')
            && !str_starts_with($value, '&')
            && !str_starts_with($value, '*')
            && !str_starts_with($value, '%')
            && !str_starts_with($value, '@')
            && !str_starts_with($value, '`');
    }

    private function isYamlMetadataAmbiguousPlainScalar(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['true', 'false', 'null', '~', 'yes', 'no', 'on', 'off'], true)) {
            return true;
        }

        return is_numeric($value)
            || $this->isYamlMetadataSexagesimalNumericScalar(str_replace('_', '', trim($value)))
            || $this->isYamlMetadataSpecialFloatScalar($normalized)
            || $this->isYamlMetadataTimestampScalar($value)
            || preg_match('/^[+-]?0x[0-9a-f]+$/i', $value) === 1
            || preg_match('/^[+-]?0o[0-7]+$/i', $value) === 1
            || preg_match('/^[+-]?0b[01]+$/i', $value) === 1;
    }

    private function isYamlMetadataSpecialFloatScalar(string $value): bool
    {
        return in_array($value, ['.inf', '+.inf', '-.inf', '.nan', '+.nan', '-.nan'], true);
    }

    private function isYamlMetadataTimestampScalar(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value) === 1) {
            return true;
        }

        return preg_match(
            '/^\d{4}-\d{1,2}-\d{1,2}(?:[Tt]|[ \t]+)\d{1,2}:\d{2}:\d{2}(?:\.[0-9]+)?(?:[ \t]*(?:[Zz]|[+-]\d{1,2}(?::?\d{2})?))?$/',
            $value
        ) === 1;
    }

    private function isYamlMetadataSexagesimalNumericScalar(string $value): bool
    {
        if ($value === '' || !str_contains($value, ':')) {
            return false;
        }

        if ($value[0] === '+' || $value[0] === '-') {
            $value = substr($value, 1);
        }

        if ($value === '' || !str_contains($value, ':')) {
            return false;
        }

        $parts = explode(':', $value);
        if (count($parts) < 2) {
            return false;
        }

        $lastIndex = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($part === '') {
                return false;
            }

            if ($index === $lastIndex && str_contains($part, '.')) {
                if (preg_match('/^\d+(?:\.\d+)?$/', $part) !== 1) {
                    return false;
                }

                if ($index > 0 && (float) $part >= 60.0) {
                    return false;
                }

                continue;
            }

            if (preg_match('/^\d+$/', $part) !== 1) {
                return false;
            }

            if ($index > 0 && (int) $part > 59) {
                return false;
            }
        }

        return true;
    }

    private function doubleQuoteYamlMetadataString(string $value): string
    {
        $escaped = '';
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $value[$offset];
            $escaped .= match ($char) {
                '\\' => '\\\\',
                '"' => '\\"',
                "\n" => '\\n',
                "\r" => '\\r',
                "\t" => '\\t',
                "\0" => '\\0',
                default => ord($char) < 0x20 ? sprintf('\\x%02X', ord($char)) : $char,
            };
        }

        return '"' . $escaped . '"';
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $indent): array
    {
        if ($this->shouldRenderHtmlBlockFallback($node)) {
            return $this->renderHtmlBlockFallback($node, $indent);
        }

        return match ($node->type) {
            'paragraph', 'plain' => [str_repeat(' ', $indent) . $this->renderInlines($node->children)],
            'heading' => $this->renderHeading($node, $indent),
            'figure' => $this->renderFigure($node, $indent),
            'bullet_list' => $this->renderList($node, false, $indent),
            'ordered_list' => $this->renderList($node, true, $indent),
            'definition_list' => $this->renderDefinitionList($node, $indent),
            'line_block' => $this->renderLineBlock($node, $indent),
            'blockquote' => $this->renderBlockQuote($node, $indent),
            'div' => $this->renderDivBlock($node, $indent),
            'code_block' => $this->renderCodeBlock($node, $indent),
            'table' => $this->renderTable($node, $indent),
            'horizontal_rule' => [str_repeat(' ', $indent) . '* * *'],
            'raw_html', 'raw_tex', 'raw_markdown', 'raw_block' => $this->renderRawBlock($node, $indent),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderHtmlBlockFallback(AstNode $node, int $indent): array
    {
        $prefix = str_repeat(' ', $indent);

        return array_map(
            static fn (string $line): string => $prefix . $line,
            explode("\n", $this->renderHtmlBlock($node))
        );
    }

    private function shouldRenderHtmlBlockFallback(AstNode $node): bool
    {
        if ($this->requestsHtmlMarkdownFormat($node->attr('markdownBlockFormat', $node->attr('markdownFormat', '')))) {
            return true;
        }

        if (($node->type === 'bullet_list' || $node->type === 'ordered_list' || $node->type === 'definition_list')
            && $this->requestsHtmlMarkdownFormat($node->attr('markdownListFormat', ''))
        ) {
            return true;
        }

        if ($node->type === 'code_block' && $this->requestsHtmlMarkdownFormat($node->attr('markdownCodeBlockFormat', ''))) {
            return true;
        }

        if ($node->type !== 'bullet_list' && $node->type !== 'ordered_list') {
            return false;
        }

        return $this->listHasHtmlOnlyAttributes($node);
    }

    private function requestsHtmlMarkdownFormat(mixed $format): bool
    {
        if (!is_scalar($format)) {
            return false;
        }

        return in_array(strtolower(trim((string) $format)), ['html', 'html4', 'html5', 'raw_html', 'raw-html'], true);
    }

    private function listHasHtmlOnlyAttributes(AstNode $node): bool
    {
        if ($this->hasHtmlOnlyAttributes($node)) {
            return true;
        }

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item' && $item->type !== 'definition_item') {
                continue;
            }

            if ($this->hasHtmlOnlyAttributes($item)) {
                return true;
            }

            foreach ($item->children as $child) {
                if (($child->type === 'definition_term' || $child->type === 'definition') && $this->hasHtmlOnlyAttributes($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasHtmlOnlyAttributes(AstNode $node): bool
    {
        $attrs = $this->htmlAttributeMap($node);
        unset($attrs['start'], $attrs['type']);
        $attrs = $this->filterReviewMetadataHtmlAttributes($attrs);

        if ($attrs !== []) {
            return true;
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);

        return is_array($htmlAttributes)
            && strtolower((string) ($htmlAttributes['data-pandoc-writer'] ?? '')) === 'html';
    }

    /**
     * @param array<string, string> $attrs
     * @return array<string, string>
     */
    private function filterReviewMetadataHtmlAttributes(array $attrs): array
    {
        foreach ($attrs as $name => $value) {
            if ($this->isReviewMetadataHtmlAttribute($name) || $this->isMarkdownWriterMetadataHtmlAttribute($name)) {
                unset($attrs[$name]);
                continue;
            }

            if ($name === 'class') {
                $classes = array_values(array_filter(
                    preg_split('/\s+/', trim($value)) ?: [],
                    static fn (string $class): bool => $class !== ''
                        && !str_starts_with($class, 'docx-')
                        && !str_starts_with($class, 'odf-')
                ));

                if ($classes === []) {
                    unset($attrs[$name]);
                    continue;
                }

                $attrs[$name] = implode(' ', $classes);
            }
        }

        return $attrs;
    }

    private function isReviewMetadataHtmlAttribute(string $name): bool
    {
        return str_starts_with($name, 'data-docx-')
            || str_starts_with($name, 'data-odf-');
    }

    private function isMarkdownWriterMetadataHtmlAttribute(string $name): bool
    {
        return $name === 'data-example-label'
            || $name === 'example-label';
    }

    /**
     * @return list<string>
     */
    private function renderHeading(AstNode $node, int $indent): array
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));
        $text = $this->renderInlines($node->children);
        $attrs = $this->renderLinkAttributes($node);
        if ($attrs !== '') {
            $text .= ' ' . $attrs;
        }
        $prefix = str_repeat(' ', $indent);

        if ($indent === 0 && (bool) ($this->options['setextHeadings'] ?? false) && ($level === 1 || $level === 2)) {
            return [
                $text,
                str_repeat($level === 1 ? '=' : '-', max(1, strlen($text))),
            ];
        }

        return [$prefix . str_repeat('#', $level) . ' ' . $text];
    }

    /**
     * @return list<string>
     */
    private function renderFigure(AstNode $node, int $indent): array
    {
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                return [str_repeat(' ', $indent) . $this->renderImage($this->imageWithFigureAttrs($node, $child), [])];
            }
            if (
                in_array($child->type, ['paragraph', 'plain'], true)
                && count($child->children) === 1
                && $child->children[0]->type === 'image'
            ) {
                return [str_repeat(' ', $indent) . $this->renderImage($this->imageWithFigureAttrs($node, $child->children[0]), [])];
            }
        }

        $body = $this->renderBlockCollection($node->children);

        return $body === '' ? [] : explode("\n", $body);
    }

    /**
     * @return list<string>
     */
    private function renderLineBlock(AstNode $node, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . '|';
        $lines = [];

        foreach ($node->children as $line) {
            if ($line->type !== 'line') {
                continue;
            }

            $content = $line->children === []
                ? (string) $line->attr('text', '')
                : $this->renderInlines($line->children);
            $content = str_replace("\xC2\xA0", ' ', $content);
            $lines[] = rtrim($prefix . ($content === '' ? '' : ' ' . $content));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionList(AstNode $node, int $indent): array
    {
        $lines = [];
        $prefix = str_repeat(' ', $indent);

        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item' || $item->children === []) {
                continue;
            }

            if ($lines !== [] && end($lines) !== '') {
                $lines[] = '';
            }
            array_push($lines, ...$this->renderDefinitionTermLines($item->children[0], $indent));

            foreach (array_slice($item->children, 1) as $definition) {
                if ($definition->type !== 'definition') {
                    continue;
                }

                $definitionLines = $this->renderDefinitionBody($definition, $indent);
                if ($definitionLines !== []) {
                    array_push($lines, ...$definitionLines);
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionTermLines(AstNode $term, int $indent): array
    {
        $prefix = str_repeat(' ', $indent);
        $termInlines = in_array($term->type, ['definition_term', 'term'], true) ? $term->children : [$term];
        $segments = [[]];
        foreach ($termInlines as $inline) {
            if ($inline->type === 'linebreak') {
                $segments[] = [];
                continue;
            }
            $lastIndex = array_key_last($segments);
            if ($lastIndex === null) {
                $segments[] = [$inline];
                continue;
            }
            $segments[$lastIndex][] = $inline;
        }

        $lines = [];
        foreach ($segments as $segment) {
            $lines[] = $prefix . $this->renderInlines($segment);
        }

        return $lines === [] ? [$prefix] : $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBody(AstNode $definition, int $indent): array
    {
        $this->plainTextTriggerEscapeSuppression++;
        try {
            $body = $this->renderBlockCollection($definition->children, true);
        } finally {
            $this->plainTextTriggerEscapeSuppression--;
        }
        $markerPrefix = str_repeat(' ', $indent) . ':   ';
        $detachedMarker = str_repeat(' ', $indent) . ':';
        $continuationPrefix = str_repeat(' ', $indent + 4);

        if ($body === '') {
            return [rtrim($markerPrefix)];
        }

        if ($this->definitionBodyNeedsDetachedMarker($definition)) {
            $lines = [$detachedMarker, ''];
            foreach (explode("\n", $body) as $line) {
                $lines[] = $line === '' ? '' : $continuationPrefix . $line;
            }

            if ((bool) $definition->attr('loose', false)) {
                $lines[] = '';
            }

            return $lines;
        }

        $bodyLines = explode("\n", $body);
        $first = array_shift($bodyLines);
        $lines = [$markerPrefix . (string) $first];

        foreach ($bodyLines as $line) {
            $lines[] = $line === '' ? '' : $continuationPrefix . $line;
        }

        if ((bool) $definition->attr('loose', false)) {
            $lines[] = '';
        }

        return $lines;
    }

    private function definitionBodyNeedsDetachedMarker(AstNode $definition): bool
    {
        foreach ($definition->children as $child) {
            return in_array($child->type, ['code_block', 'heading', 'line_block', 'table'], true);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered, int $indent): array
    {
        $lines = [];
        $start = (int) $node->attr('start', 1);
        $index = 0;
        $listLoose = (bool) $node->attr('loose', false);

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            if ($item->attr('listHeader') === true) {
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                array_push($lines, ...$this->renderListHeaderItem($item, $indent));
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                continue;
            }

            $marker = $ordered ? $this->orderedListMarker($node, $item, $start + $index, $index) : $this->bulletListMarker($node);
            $itemLoose = $listLoose || (bool) $item->attr('loose', false);
            if ($itemLoose && $lines !== [] && end($lines) !== '') {
                $lines[] = '';
            }
            array_push($lines, ...$this->renderListItem($item, $marker, $indent));
            $index++;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderListHeaderItem(AstNode $item, int $indent): array
    {
        return $this->renderDivBlock(new AstNode('div', $item->attrs, $item->children), $indent);
    }

    private function orderedListMarker(AstNode $node, AstNode $item, int $number, int $index): string
    {
        $style = $this->orderedListMarkerStyle($node);
        $delimiter = $this->orderedListMarkerDelimiter($node);

        if ($style === 'example') {
            return $this->padOrderedListMarker('(@' . $this->numberedExampleLabel($item, $node, $index) . ')');
        }

        if ($style === 'default') {
            return $this->padOrderedListMarker('#' . ($delimiter === 'one_paren' ? ')' : '.'));
        }

        $label = match ($style) {
            'lower_alpha' => $this->alphaListLabel(max(1, $number), false),
            'upper_alpha' => $this->alphaListLabel(max(1, $number), true),
            'lower_roman' => strtolower($this->romanNumeral(max(1, $number))),
            'upper_roman' => $this->romanNumeral(max(1, $number)),
            default => (string) max(0, $number),
        };

        $marker = match ($delimiter) {
            'one_paren' => $label . ')',
            'two_parens' => '(' . $label . ')',
            default => $label . '.',
        };

        return $this->padOrderedListMarker($marker);
    }

    private function padOrderedListMarker(string $marker): string
    {
        if (strlen($marker) < 3) {
            $marker .= str_repeat(' ', 3 - strlen($marker));
        }

        return $marker . ' ';
    }

    private function numberedExampleLabel(?AstNode $item, ?AstNode $list = null, int $index = 0): string
    {
        foreach ([$item, $list] as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            $label = $this->numberedExampleLabelFromNode($node, $index);
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    private function numberedExampleLabelFromNode(AstNode $node, int $index = 0): string
    {
        foreach (['exampleLabel', 'label'] as $name) {
            $value = $node->attr($name, '');
            if (!is_scalar($value)) {
                continue;
            }

            $label = trim((string) $value);
            if ($this->isNumberedExampleLabel($label)) {
                return $label;
            }
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes)) {
            foreach (['data-example-label', 'example-label'] as $name) {
                $value = $attributes[$name] ?? '';
                if (!is_scalar($value)) {
                    continue;
                }

                $label = trim((string) $value);
                if ($this->isNumberedExampleLabel($label)) {
                    return $label;
                }
            }
        }

        $labels = $node->attr('exampleLabels', []);
        if (is_array($labels)) {
            $value = $labels[$index] ?? $labels[(string) $index] ?? '';
            if (is_scalar($value)) {
                $label = trim((string) $value);
                if ($this->isNumberedExampleLabel($label)) {
                    return $label;
                }
            }
        }

        return '';
    }

    private function isNumberedExampleLabel(string $label): bool
    {
        return $label !== '' && preg_match('/\A[A-Za-z0-9_-]+\z/u', $label) === 1;
    }

    /**
     * @return array<string, bool>
     */
    private function collectNumberedExampleLabels(AstNode $node): array
    {
        $labels = [];
        $this->collectNumberedExampleLabelsFromNode($node, $labels);

        return $labels;
    }

    /**
     * @param array<string, bool> $labels
     */
    private function collectNumberedExampleLabelsFromNode(AstNode $node, array &$labels): void
    {
        if ($node->type === 'ordered_list' && $this->orderedListMarkerStyle($node) === 'example') {
            $index = 0;
            foreach ($node->children as $item) {
                if ($item->type !== 'list_item') {
                    continue;
                }

                $label = $this->numberedExampleLabel($item, $node, $index);
                if ($label !== '') {
                    $labels[$label] = true;
                }
                $index++;
            }
        }

        foreach ($node->children as $child) {
            $this->collectNumberedExampleLabelsFromNode($child, $labels);
        }
    }

    private function bulletListMarker(?AstNode $node = null): string
    {
        if (array_key_exists('bulletListMarker', $this->options)) {
            return match ((string) $this->options['bulletListMarker']) {
                'plus' => '+ ',
                'star' => '* ',
                default => '- ',
            };
        }

        $marker = $node instanceof AstNode ? $node->attr('marker', $node->attr('bulletMarker', '')) : '';

        return match ((string) $marker) {
            '+', 'plus' => '+ ',
            '*', 'star' => '* ',
            default => '- ',
        };
    }

    private function alphaListLabel(int $number, bool $upper): string
    {
        $number = max(1, $number);
        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(ord('a') + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $upper ? strtoupper($label) : $label;
    }

    private function romanNumeral(int $number): string
    {
        $number = max(1, $number);
        if ($number >= 4000) {
            return '?';
        }

        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];
        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item, string $marker, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . $marker;
        $blockIndent = $indent + strlen($marker);
        $paragraphContinuationIndent = $blockIndent;
        $task = $item->attr('taskChecked', null);
        if (is_bool($task)) {
            $prefix .= $task ? '[x] ' : '[ ] ';
        }

        $inlineChildren = [];
        $lines = [];
        $hasFirstLine = false;
        $previousBlock = null;

        foreach ($item->children as $child) {
            if (!$hasFirstLine && $inlineChildren === [] && $this->canRenderInitialListCodeBlock($child)) {
                $lines = $this->appendInitialListCodeBlockLines($lines, $prefix, $indent, $marker, $child);
                $hasFirstLine = true;
                continue;
            }

            if ($this->isInlineNode($child)) {
                $inlineChildren[] = $child;
                continue;
            }

            if ($inlineChildren !== [] || !$hasFirstLine) {
                $lines[] = rtrim($prefix . $this->renderInlines($inlineChildren));
                $inlineChildren = [];
                $hasFirstLine = true;
            }

            if ($this->isInlineListItemBlock($child)) {
                if (count($lines) === 1 && rtrim($lines[0]) === rtrim($prefix)) {
                    $lines = [];
                    $lines = $this->appendInlineListItemLines(
                        $lines,
                        $prefix,
                        $paragraphContinuationIndent,
                        $this->renderInlines($child->children)
                    );
                    $previousBlock = $child;
                    continue;
                }

                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                foreach (explode("\n", $this->renderInlines($child->children)) as $line) {
                    $lines[] = str_repeat(' ', $paragraphContinuationIndent) . $line;
                }
                $previousBlock = $child;
                continue;
            }

            $nestedIndent = $blockIndent;
            if ($previousBlock instanceof AstNode && $this->needsBlockSeparator($previousBlock, $child)) {
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                $lines[] = str_repeat(' ', $blockIndent) . '<!-- -->';
                $lines[] = '';
                if (
                    ($child->type === 'bullet_list' || $child->type === 'ordered_list')
                    && $blockIndent >= $indent + 4
                ) {
                    $nestedIndent = $indent + 3;
                }
            }

            foreach ($this->renderBlock($child, $nestedIndent) as $nestedLine) {
                $lines[] = $nestedLine;
            }
            $previousBlock = $child;
        }

        if ($inlineChildren !== [] || !$hasFirstLine) {
            $lines = $this->appendInlineListItemLines(
                $lines,
                $prefix,
                $paragraphContinuationIndent,
                $this->renderInlines($inlineChildren)
            );
        }

        return $lines;
    }

    private function canRenderInitialListCodeBlock(AstNode $node): bool
    {
        $text = (string) $node->attr('text', '');

        return $node->type === 'code_block'
            && $text !== ''
            && !str_starts_with($text, "\n")
            && $this->renderCodeBlockAttributes($node) === ''
            && !(bool) ($this->options['fencedCodeBlocks'] ?? false);
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function appendInitialListCodeBlockLines(
        array $lines,
        string $prefix,
        int $indent,
        string $marker,
        AstNode $node
    ): array {
        $markerPadding = strlen($marker) - strlen(rtrim($marker, ' '));
        $additionalPadding = str_repeat(' ', max(0, 5 - $markerPadding));
        $continuationIndent = $indent + strlen($marker) + strlen($additionalPadding);
        $codeLines = explode("\n", (string) $node->attr('text', ''));
        $first = array_shift($codeLines);

        $lines[] = rtrim($prefix . $additionalPadding . (string) $first);
        foreach ($codeLines as $line) {
            $lines[] = str_repeat(' ', $continuationIndent) . $line;
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function appendInlineListItemLines(array $lines, string $prefix, int $continuationIndent, string $markdown): array
    {
        $inlineLines = explode("\n", $markdown);
        $first = array_shift($inlineLines);

        $lines[] = rtrim($prefix . (string) $first);
        foreach ($inlineLines as $line) {
            $lines[] = str_repeat(' ', $continuationIndent) . $line;
        }

        return $lines;
    }

    private function isInlineListItemBlock(AstNode $node): bool
    {
        return $node->type === 'paragraph' || $node->type === 'plain';
    }

    /**
     * @return list<string>
     */
    private function renderTable(AstNode $node, int $indent): array
    {
        $tableHeadRows = [];
        $bodyGroups = [];
        $directBodyRows = [];
        $footRows = [];
        foreach ($node->children as $child) {
            if ($child->type === 'table_head') {
                foreach ($child->children as $row) {
                    if ($row->type === 'table_row') {
                        $tableHeadRows[] = $row;
                    }
                }
                continue;
            }

            if ($child->type === 'table_body') {
                [$groupHeadRows, $groupBodyRows] = $this->tableBodyRows($child);
                $bodyGroups[] = [
                    'headRows' => $groupHeadRows,
                    'bodyRows' => $groupBodyRows,
                ];
                continue;
            }

            if ($child->type === 'table_foot') {
                foreach ($child->children as $row) {
                    if ($row->type === 'table_row') {
                        $footRows[] = $row;
                    }
                }
                continue;
            }

            if ($child->type === 'table_row') {
                $directBodyRows[] = $child;
            }
        }
        if ($directBodyRows !== []) {
            $bodyGroups[] = [
                'headRows' => [],
                'bodyRows' => $directBodyRows,
            ];
        }

        if ($tableHeadRows === [] && $bodyGroups === [] && $footRows === []) {
            return [];
        }

        $columnCount = $this->tableColumnCount($node);
        if ($columnCount === 0) {
            return [];
        }

        if ($this->shouldRenderHtmlTable($node, $columnCount)) {
            return $this->renderHtmlTable($node, $indent, $columnCount);
        }

        $hasBodyHeadRows = false;
        foreach ($bodyGroups as $group) {
            if ($group['headRows'] !== []) {
                $hasBodyHeadRows = true;
                break;
            }
        }

        if ($tableHeadRows === [] && !$hasBodyHeadRows) {
            $tableHeadRows[] = new AstNode('table_row', ['header' => true], array_fill(0, $columnCount, new AstNode('table_cell')));
        }

        $tableFormat = $this->markdownTableFormat($node);
        $escapeTablePipes = $tableFormat === 'pipe';
        $expandedHeadRows = $this->expandTableRows($tableHeadRows, $columnCount, $escapeTablePipes);
        $expandedBodyRows = [];
        foreach ($bodyGroups as $group) {
            $expandedGroupRows = $this->expandTableRows([...$group['headRows'], ...$group['bodyRows']], $columnCount, $escapeTablePipes);
            $expandedHeadRows = [
                ...$expandedHeadRows,
                ...array_slice($expandedGroupRows, 0, count($group['headRows'])),
            ];
            $expandedBodyRows = [
                ...$expandedBodyRows,
                ...array_slice($expandedGroupRows, count($group['headRows'])),
            ];
        }
        $expandedBodyRows = [
            ...$expandedBodyRows,
            ...$this->expandTableRows($footRows, $columnCount, $escapeTablePipes),
        ];
        $renderedRows = [...$expandedHeadRows, ...$expandedBodyRows];
        $widths = $this->tableColumnWidths($renderedRows, $this->tableColumnWidthHints($node, $columnCount), $columnCount);
        $alignments = $this->tableAlignments($node, $columnCount);
        $prefix = str_repeat(' ', $indent);
        $captionLine = $this->renderTableCaptionLine($node, $prefix);
        $captionSide = $this->tableCaptionSide($node);

        if ($tableFormat === 'simple') {
            return $this->renderSimpleTable($expandedHeadRows, $expandedBodyRows, $widths, $alignments, $prefix, $captionLine, $captionSide);
        }

        if ($tableFormat === 'grid') {
            $gridHeadRows = $this->expandTableRowsAsLineCells($tableHeadRows, $columnCount);
            $gridBodyRows = [];
            foreach ($bodyGroups as $group) {
                $expandedGroupRows = $this->expandTableRowsAsLineCells([...$group['headRows'], ...$group['bodyRows']], $columnCount);
                $gridHeadRows = [
                    ...$gridHeadRows,
                    ...array_slice($expandedGroupRows, 0, count($group['headRows'])),
                ];
                $gridBodyRows = [
                    ...$gridBodyRows,
                    ...array_slice($expandedGroupRows, count($group['headRows'])),
                ];
            }
            $gridBodyRows = [
                ...$gridBodyRows,
                ...$this->expandTableRowsAsLineCells($footRows, $columnCount),
            ];
            $gridWidths = $this->tableGridColumnWidths(
                [...$gridHeadRows, ...$gridBodyRows],
                $this->tableColumnWidthHints($node, $columnCount),
                $columnCount
            );

            return $this->renderGridTable($gridHeadRows, $gridBodyRows, $gridWidths, $alignments, $prefix, $captionLine, $captionSide);
        }

        $lines = [];

        if ($captionLine !== '' && $captionSide === 'top') {
            $lines[] = $captionLine;
            $lines[] = '';
        }

        foreach ($expandedHeadRows as $row) {
            $lines[] = $prefix . $this->renderPipeTableRow($row, $widths, $alignments);
        }
        $lines[] = $prefix . $this->renderPipeTableDelimiter($widths, $alignments);
        foreach ($expandedBodyRows as $row) {
            $lines[] = $prefix . $this->renderPipeTableRow($row, $widths, $alignments);
        }

        if ($captionLine !== '' && $captionSide !== 'top') {
            $lines[] = '';
            $lines[] = $captionLine;
        }

        return $lines;
    }

    private function markdownTableFormat(AstNode $node): string
    {
        $format = $node->attr(
            'markdownTableFormat',
            $this->options['markdownTableFormat'] ?? $this->options['tableStyle'] ?? ''
        );
        if (!is_scalar($format)) {
            return 'pipe';
        }

        $format = strtolower(trim(str_replace('_', '-', (string) $format)));

        return match ($format) {
            'simple', 'simple-table', 'simple-tables' => 'simple',
            'grid', 'grid-table', 'grid-tables' => 'grid',
            default => 'pipe',
        };
    }

    private function shouldRenderHtmlTable(AstNode $node, int $columnCount): bool
    {
        $autoFallback = (bool) ($this->options['htmlTableAutoFallback'] ?? false)
            || (bool) ($this->options['autoHtmlTables'] ?? false);

        return $columnCount > 0
            && (
                $this->tableRequestsHtmlFallback($node)
                || ($autoFallback && $this->tableRequiresHtmlFallback($node, $columnCount))
                || (
                    $this->tableRequestsSemanticHtmlFallback($node)
                    && $this->tableRequiresHtmlFallback($node, $columnCount)
                )
            );
    }

    private function tableRequestsHtmlFallback(AstNode $node): bool
    {
        $format = strtolower(trim((string) $node->attr('markdownTableFormat', '')));
        if (in_array($format, ['html', 'raw_html', 'raw-html'], true)) {
            return true;
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && strtolower((string) ($htmlAttributes['data-pandoc-writer'] ?? '')) === 'html') {
            return true;
        }

        return false;
    }

    private function tableRequestsSemanticHtmlFallback(AstNode $node): bool
    {
        $format = strtolower(trim((string) $node->attr('markdownTableFormat', '')));
        if (in_array($format, ['auto', 'auto_html', 'auto-html', 'preserve', 'preserve-html', 'semantic-html'], true)) {
            return true;
        }

        return (bool) ($this->options['semanticTableHtmlFallback'] ?? false);
    }

    private function tableRequiresHtmlFallback(AstNode $node, int $columnCount): bool
    {
        if (
            $this->tableHasHtmlOnlyAttributes($node)
            || $this->tableCaptionRequiresHtmlFallback($node)
            || $this->tableHasColumnSourceAttributes($node)
        ) {
            return true;
        }

        foreach ($node->children as $section) {
            if (in_array($section->type, ['table_head', 'table_body', 'table_foot'], true)) {
                if ($this->nodeHasSourceAttributes($section)) {
                    return true;
                }

                if ($section->type === 'table_body' && TableGeometry::rowHeadColumns($section, $columnCount) > 0) {
                    return true;
                }

                foreach ($this->tableSectionRowsForFallbackCheck($section) as $row) {
                    if ($this->tableRowRequiresHtmlFallback($row)) {
                        return true;
                    }
                }

                continue;
            }

            if ($section->type === 'table_row' && $this->tableRowRequiresHtmlFallback($section)) {
                return true;
            }
        }

        return false;
    }

    private function tableColumnCount(AstNode $node): int
    {
        $columnCount = TableGeometry::columnCount($node);
        $directRows = $this->tableDirectRows($node);
        if ($directRows !== []) {
            $columnCount = max($columnCount, TableGeometry::columnCountForRows($directRows));
        }

        return $columnCount;
    }

    /**
     * @return list<AstNode>
     */
    private function tableDirectRows(AstNode $node): array
    {
        return array_values(array_filter(
            $node->children,
            static fn (AstNode $child): bool => $child->type === 'table_row'
        ));
    }

    /**
     * @return list<AstNode>
     */
    private function tableSectionRowsForFallbackCheck(AstNode $section): array
    {
        $rows = $section->type === 'table_body' ? $this->tableBodyHeadRows($section) : [];
        foreach ($section->children as $row) {
            if ($row->type === 'table_row' && !in_array($row, $rows, true)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function tableRowRequiresHtmlFallback(AstNode $row): bool
    {
        if ($this->nodeHasSourceAttributes($row)) {
            return true;
        }

        foreach ($row->children as $cell) {
            if ($cell->type === 'table_cell' && $this->tableCellRequiresHtmlFallback($cell)) {
                return true;
            }
        }

        return false;
    }

    private function tableHasHtmlOnlyAttributes(AstNode $node): bool
    {
        if ($this->stringMapAttrNonEmpty($node, 'htmlAttributes')) {
            return true;
        }

        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes)) {
            return false;
        }

        foreach ($attributes as $name => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            if (!$this->isMarkdownTableAttribute((string) $name)) {
                return true;
            }
        }

        return false;
    }

    private function tableCaptionRequiresHtmlFallback(AstNode $node): bool
    {
        $captionSource = $node->attr('captionSource', []);
        if (is_array($captionSource)) {
            $sourceAttributes = $captionSource['sourceAttributes'] ?? [];
            if (is_array($sourceAttributes) && $this->sourceAttributeArrayNonEmpty($sourceAttributes)) {
                return true;
            }
        }

        foreach (['captionInlines', 'captionBlocks', 'shortCaptionInlines', 'shortCaptionBlocks'] as $name) {
            if ($this->nodeListHasHtmlAttributes($node->attr($name, []))) {
                return true;
            }
        }

        return false;
    }

    private function tableHasColumnSourceAttributes(AstNode $node): bool
    {
        $columnSources = $node->attr('columnSources', []);
        if (!is_array($columnSources)) {
            return false;
        }

        foreach ($columnSources as $source) {
            if (is_array($source) && $this->sourceAttributeArrayNonEmpty($source)) {
                return true;
            }
        }

        return false;
    }

    private function tableCellRequiresHtmlFallback(AstNode $cell): bool
    {
        if ($this->tableCellColspan($cell) > 1) {
            return true;
        }

        if ($this->tableCellRawRowspan($cell) !== 1) {
            return true;
        }

        if ((bool) $cell->attr('header', false)) {
            return true;
        }

        if (in_array((string) $cell->attr('align', ''), ['left', 'right', 'center'], true)) {
            return true;
        }

        if (in_array((string) $cell->attr('valign', ''), ['baseline', 'top', 'middle', 'bottom'], true)) {
            return true;
        }

        return $this->nodeHasSourceAttributes($cell)
            || $this->nodeListHasHtmlAttributes($cell->children);
    }

    private function tableCellColspan(AstNode $cell): int
    {
        return max(1, (int) $cell->attr('colspan', 1));
    }

    private function tableCellRawRowspan(AstNode $cell): int
    {
        $value = $cell->attr('rowspan', 1);
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^-?\d+$/', $value) !== 1) {
                return 1;
            }

            return (int) $value;
        }

        if (!is_int($value) && !is_float($value)) {
            return 1;
        }

        return (int) $value;
    }

    private function nodeHasSourceAttributes(AstNode $node): bool
    {
        return $this->scalarAttrNonEmpty($node, 'id')
            || $this->stringListAttrNonEmpty($node, 'classes')
            || $this->stringMapAttrNonEmpty($node, 'attributes')
            || $this->stringMapAttrNonEmpty($node, 'htmlAttributes');
    }

    private function scalarAttrNonEmpty(AstNode $node, string $name): bool
    {
        $value = $node->attr($name);

        return is_scalar($value) && trim((string) $value) !== '';
    }

    private function stringListAttrNonEmpty(AstNode $node, string $name): bool
    {
        $values = $node->attr($name, []);
        if (!is_array($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function stringMapAttrNonEmpty(AstNode $node, string $name): bool
    {
        $values = $node->attr($name, []);

        return is_array($values) && $this->stringMapNonEmpty($values);
    }

    /**
     * @param array<mixed, mixed> $source
     */
    private function sourceAttributeArrayNonEmpty(array $source): bool
    {
        if (
            (isset($source['id']) && is_scalar($source['id']) && trim((string) $source['id']) !== '')
            || $this->stringListNonEmpty($source['classes'] ?? [])
            || $this->stringMapNonEmpty($source['attributes'] ?? [])
            || $this->stringMapNonEmpty($source['htmlAttributes'] ?? [])
        ) {
            return true;
        }

        return false;
    }

    private function stringListNonEmpty(mixed $values): bool
    {
        if (!is_array($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function stringMapNonEmpty(mixed $values): bool
    {
        if (!is_array($values)) {
            return false;
        }

        foreach ($values as $key => $value) {
            if (trim((string) $key) !== '' && is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
    private function nodeHasHtmlAttributes(AstNode $node): bool
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            return false;
        }

        foreach ($htmlAttributes as $name => $value) {
            if (is_scalar($value) && trim((string) $name) !== '' && (string) $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function nodeListHasHtmlAttributes(mixed $nodes): bool
    {
        if (!is_array($nodes)) {
            return false;
        }

        foreach ($nodes as $node) {
            if ($node instanceof AstNode && $this->nodeTreeHasHtmlAttributes($node)) {
                return true;
            }
        }

        return false;
    }

    private function nodeTreeHasHtmlAttributes(AstNode $node): bool
    {
        if ($this->nodeHasHtmlAttributes($node)) {
            return true;
        }

        return $this->nodeListHasHtmlAttributes($node->children);
    }

    /**
     * @return list<AstNode>
     */
    private function tableSectionRowsWithBodyHeads(AstNode $section): array
    {
        $rows = [];
        if ($section->type === 'table_body') {
            array_push($rows, ...$this->tableBodyHeadRows($section));
        }

        foreach ($section->children as $row) {
            if ($row->type === 'table_row') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function renderHtmlTable(AstNode $node, int $indent, int $columnCount): array
    {
        $prefix = str_repeat(' ', $indent);
        $innerPrefix = str_repeat(' ', $indent + 2);
        $lines = [$prefix . '<table' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'];
        array_push($lines, ...$this->renderHtmlTableColgroup($node, $columnCount, $indent + 2));

        $caption = $this->renderHtmlTableCaption($node, $indent + 2);
        if ($caption !== '') {
            $lines[] = $caption;
        }

        $head = null;
        $bodies = [];
        $directBodyRows = [];
        $foot = null;
        foreach ($node->children as $child) {
            if ($child->type === 'table_head') {
                $head = $child;
                continue;
            }

            if ($child->type === 'table_body') {
                $bodies[] = $child;
                continue;
            }

            if ($child->type === 'table_foot') {
                $foot = $child;
                continue;
            }

            if ($child->type === 'table_row') {
                $directBodyRows[] = $child;
            }
        }
        if ($directBodyRows !== []) {
            $bodies[] = new AstNode('table_body', [], $directBodyRows);
        }

        if ($head instanceof AstNode && $this->tableSectionRowsWithBodyHeads($head) !== []) {
            $lines[] = $innerPrefix . '<thead' . $this->renderHtmlAttributes($this->htmlAttributeMap($head)) . '>';
            array_push($lines, ...$this->renderHtmlTableRows(
                $this->tableRowEntries($head, true),
                $node,
                $columnCount,
                $indent + 4
            ));
            $lines[] = $innerPrefix . '</thead>';
        }

        foreach ($bodies as $body) {
            if ($this->tableSectionRowsWithBodyHeads($body) === []) {
                continue;
            }

            $lines[] = $innerPrefix . '<tbody' . $this->renderHtmlAttributes($this->htmlAttributeMap($body)) . '>';
            array_push($lines, ...$this->renderHtmlTableRows(
                $this->tableBodyRowEntries($body, $columnCount),
                $node,
                $columnCount,
                $indent + 4
            ));
            $lines[] = $innerPrefix . '</tbody>';
        }

        if ($foot instanceof AstNode && $this->tableSectionRowsWithBodyHeads($foot) !== []) {
            $lines[] = $innerPrefix . '<tfoot' . $this->renderHtmlAttributes($this->htmlAttributeMap($foot)) . '>';
            array_push($lines, ...$this->renderHtmlTableRows(
                $this->tableRowEntries($foot, false),
                $node,
                $columnCount,
                $indent + 4
            ));
            $lines[] = $innerPrefix . '</tfoot>';
        }

        $lines[] = $prefix . '</table>';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderHtmlTableColgroup(AstNode $node, int $columnCount, int $indent): array
    {
        $widths = $node->attr('widths', []);
        if (!is_array($widths) || $widths === []) {
            return [];
        }

        $specs = TableGeometry::columnSpecs($node, $columnCount);
        $cols = [];
        foreach ($specs as $spec) {
            if (!is_numeric($spec['width'] ?? null)) {
                return [];
            }

            $attrs = [
                'style' => 'width:' . $this->formatHtmlTableWidth((float) $spec['width']),
            ];
            $alignment = (string) ($spec['alignment'] ?? 'default');
            if (in_array($alignment, ['left', 'right', 'center'], true)) {
                $attrs['data-pandoc-align'] = $alignment;
            }

            $cols[] = str_repeat(' ', $indent + 2) . '<col' . $this->renderHtmlAttributes($attrs) . ' />';
        }

        if ($cols === []) {
            return [];
        }

        return [
            str_repeat(' ', $indent) . '<colgroup>',
            ...$cols,
            str_repeat(' ', $indent) . '</colgroup>',
        ];
    }

    private function formatHtmlTableWidth(float $width): string
    {
        $formatted = rtrim(rtrim(number_format(max(0.0, $width) * 100, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    private function renderHtmlTableCaption(AstNode $node, int $indent): string
    {
        $content = $this->renderHtmlTableCaptionContent($node);
        $attrs = $this->htmlCaptionAttributeMap($node);
        $shortCaption = $this->plainHtmlTableShortCaption($node);
        if ($shortCaption !== '') {
            $attrs['data-pandoc-short-caption'] = $shortCaption;
        }

        if ($content === '' && $attrs === []) {
            return '';
        }

        return str_repeat(' ', $indent)
            . '<caption' . $this->renderHtmlAttributes($attrs) . '>'
            . $content
            . '</caption>';
    }

    private function renderHtmlTableCaptionContent(AstNode $node): string
    {
        $captionBlocks = $this->tableCaptionBlocksForWriting($node);
        if ($captionBlocks !== []) {
            return $this->renderHtmlBlocks($captionBlocks);
        }

        $captionInlines = $this->tableCaptionInlinesForWriting($node);
        if ($captionInlines !== []) {
            return $this->renderHtmlInlines($captionInlines);
        }

        return $this->escapeHtml($this->tableCaptionTextForWriting($node));
    }

    /**
     * @return list<AstNode>
     */
    private function htmlTableCaptionBlocks(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            return [];
        }

        return array_values(array_filter($blocks, static fn (mixed $block): bool => $block instanceof AstNode));
    }

    /**
     * @return array<string, string>
     */
    private function htmlCaptionAttributeMap(AstNode $node): array
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return [];
        }

        $sourceAttributes = $captionSource['sourceAttributes'] ?? [];
        if (!is_array($sourceAttributes)) {
            return [];
        }

        return $this->htmlAttributeMapFromSource($sourceAttributes);
    }

    private function plainHtmlTableShortCaption(AstNode $node): string
    {
        $shortCaptionBlocks = $this->tableShortCaptionBlocksForWriting($node);
        if ($shortCaptionBlocks !== []) {
            return $this->plainBlockText($shortCaptionBlocks);
        }

        $shortCaptionInlines = $this->tableShortCaptionInlinesForWriting($node);
        if ($shortCaptionInlines !== []) {
            return $this->plainInlineText($shortCaptionInlines);
        }

        return trim($this->tableShortCaptionTextForWriting($node));
    }

    /**
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function tableRowEntries(AstNode $section, bool $header): array
    {
        return $this->tableRowsEntries($section->children, $header);
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function tableRowsEntries(array $rows, bool $header, int $rowHeadColumns = 0): array
    {
        $entries = [];
        foreach ($rows as $row) {
            if ($row->type !== 'table_row') {
                continue;
            }

            $entries[] = [
                'row' => $row,
                'header' => $header || $row->attr('header') === true,
                'rowHeadColumns' => $rowHeadColumns,
            ];
        }

        return $entries;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function tableDirectRowEntries(array $rows): array
    {
        $entries = [];
        foreach ($rows as $row) {
            if ($row->type === 'table_row') {
                $entries[] = [
                    'row' => $row,
                    'header' => false,
                    'rowHeadColumns' => 0,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function directTableRowEntries(array $rows): array
    {
        $entries = [];
        foreach ($rows as $row) {
            if ($row->type === 'table_row') {
                $entries[] = [
                    'row' => $row,
                    'header' => false,
                    'rowHeadColumns' => 0,
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function tableBodyRowEntries(AstNode $body, int $columnCount): array
    {
        $entries = [];
        array_push($entries, ...$this->tableRowsEntries($this->tableBodyHeadRows($body), true));

        $rowHeadColumns = TableGeometry::rowHeadColumns($body, $columnCount);
        array_push($entries, ...$this->tableRowsEntries($body->children, false, $rowHeadColumns));

        return $entries;
    }

    /**
     * @param list<array{row:AstNode,header:bool,rowHeadColumns:int}> $rowEntries
     * @return list<string>
     */
    private function renderHtmlTableRows(array $rowEntries, AstNode $table, int $columnCount, int $indent): array
    {
        $rows = array_map(static fn (array $entry): AstNode => $entry['row'], $rowEntries);
        $lines = [];
        foreach (TableGeometry::layoutRows($rows, $columnCount) as $rowIndex => $layoutRow) {
            $entry = $rowEntries[$rowIndex] ?? ['header' => false, 'rowHeadColumns' => 0];
            $lines[] = $this->renderHtmlTableRow(
                $layoutRow,
                $table,
                (bool) ($entry['header'] ?? false),
                (int) ($entry['rowHeadColumns'] ?? 0),
                $indent
            );
        }

        return $lines;
    }

    /**
     * @param array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int}>} $layoutRow
     */
    private function renderHtmlTableRow(array $layoutRow, AstNode $table, bool $header, int $rowHeadColumns, int $indent): string
    {
        $row = $layoutRow['row'];
        $html = str_repeat(' ', $indent) . '<tr' . $this->renderHtmlAttributes($this->htmlAttributeMap($row)) . '>';
        foreach ($layoutRow['cells'] as $layoutCell) {
            $cell = $layoutCell['node'];
            $column = (int) $layoutCell['column'];
            $isHeaderCell = TableGeometry::isHeaderCell($header, $rowHeadColumns, $column, $cell);
            $tag = $isHeaderCell ? 'th' : 'td';
            $scope = $isHeaderCell ? $this->htmlTableHeaderScope($header, $rowHeadColumns, $column, $layoutCell) : '';
            $html .= '<' . $tag . $this->renderHtmlTableCellAttributes($table, $column, $layoutCell, $cell, $scope) . '>'
                . $this->renderHtmlTableCellContent($cell)
                . '</' . $tag . '>';
        }

        return $html . '</tr>';
    }

    /**
     * @param array{colspan:int,rowspan:int} $layoutCell
     */
    private function htmlTableHeaderScope(bool $headerRow, int $rowHeadColumns, int $column, array $layoutCell): string
    {
        $colspan = max(1, (int) $layoutCell['colspan']);
        $rowspan = max(1, (int) $layoutCell['rowspan']);

        if (!$headerRow && $rowHeadColumns > 0 && $column < $rowHeadColumns) {
            return $rowspan > 1 ? 'rowgroup' : 'row';
        }

        if ($headerRow) {
            return $colspan > 1 ? 'colgroup' : 'col';
        }

        return $rowspan > 1 ? 'rowgroup' : 'row';
    }

    /**
     * @param array{colspan:int,rowspan:int} $layoutCell
     */
    private function renderHtmlTableCellAttributes(AstNode $table, int $column, array $layoutCell, AstNode $cell, string $headerScope): string
    {
        $attrs = $this->htmlAttributeMap($cell);
        if ($headerScope !== '' && !isset($attrs['scope'])) {
            $attrs['scope'] = $headerScope;
        }

        $colspan = max(1, (int) $layoutCell['colspan']);
        $rowspan = max(1, (int) $layoutCell['rowspan']);
        if ($colspan > 1) {
            $attrs['colspan'] = (string) $colspan;
        }

        if ($rowspan > 1) {
            $attrs['rowspan'] = (string) $rowspan;
        }

        $styles = [];
        $sourceStyle = (string) ($attrs['style'] ?? '');
        if ($sourceStyle !== '') {
            $styles[] = rtrim($sourceStyle, ';');
        }

        $alignment = TableGeometry::cellAlignment($table, $column, $cell);
        if (
            in_array($alignment, ['left', 'right', 'center'], true)
            && preg_match('/(?:^|;)\s*text-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'text-align:' . $alignment;
        }

        $verticalAlignment = TableGeometry::cellVerticalAlignment($cell);
        if (
            in_array($verticalAlignment, ['baseline', 'top', 'middle', 'bottom'], true)
            && preg_match('/(?:^|;)\s*vertical-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'vertical-align:' . $verticalAlignment;
        }

        if ($styles !== []) {
            $attrs['style'] = implode('; ', $styles);
        }

        return $this->renderHtmlAttributes($attrs);
    }

    private function renderHtmlTableCellContent(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->escapeHtml((string) $cell->attr('text', ''));
        }

        $hasOnlyInlines = true;
        foreach ($cell->children as $child) {
            if (!$this->isInlineNode($child)) {
                $hasOnlyInlines = false;
                break;
            }
        }

        return $hasOnlyInlines ? $this->renderHtmlInlines($cell->children) : $this->renderHtmlBlocks($cell->children);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderHtmlBlocks(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderHtmlBlock($block);
        }

        return $html;
    }

    private function renderHtmlBlock(AstNode $node): string
    {
        return match ($node->type) {
            'paragraph', 'plain' => '<p>' . $this->renderHtmlInlines($node->children) . '</p>',
            'heading' => $this->renderHtmlHeading($node),
            'bullet_list' => $this->renderHtmlList($node, 'ul'),
            'ordered_list' => $this->renderHtmlList($node, 'ol'),
            'definition_list' => $this->renderHtmlDefinitionList($node),
            'line_block' => $this->renderHtmlLineBlock($node),
            'blockquote' => '<blockquote' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->renderHtmlBlocks($node->children)
                . '</blockquote>',
            'code_block' => '<pre><code' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->escapeHtml((string) $node->attr('text', ''))
                . '</code></pre>',
            'horizontal_rule' => '<hr />',
            'div' => '<div' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->renderHtmlBlocks($node->children)
                . '</div>',
            'table' => implode("\n", $this->renderHtmlTable($node, 0, TableGeometry::columnCount($node))),
            'raw_html', 'raw_block' => (string) $node->attr('text', $node->attr('html', '')),
            default => $this->isInlineNode($node)
                ? $this->renderHtmlInline($node)
                : $this->renderHtmlBlocks($node->children),
        };
    }

    private function renderHtmlHeading(AstNode $node): string
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));

        return '<h' . $level . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
            . $this->renderHtmlInlines($node->children)
            . '</h' . $level . '>';
    }

    private function renderHtmlList(AstNode $node, string $tag): string
    {
        $attrs = $this->htmlAttributeMap($node);
        if ($tag === 'ol' && (int) $node->attr('start', 1) !== 1) {
            $attrs['start'] = (string) (int) $node->attr('start', 1);
        }
        if ($tag === 'ol') {
            $type = $this->htmlOrderedListType($node);
            if ($type !== null && !isset($attrs['type'])) {
                $attrs['type'] = $type;
            }
        }
        if ($tag === 'ul' && $this->htmlListHasTaskItems($node)) {
            $this->appendHtmlClass($attrs, 'task-list');
        }

        $html = '<' . $tag . $this->renderHtmlAttributes($attrs) . '>';
        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            $itemAttrs = $this->htmlAttributeMap($item);
            $number = $item->attr('number', null);
            if (is_int($number) || (is_string($number) && preg_match('/\A-?\d+\z/', $number) === 1)) {
                $itemAttrs['value'] = (string) $number;
            }

            $html .= '<li' . $this->renderHtmlAttributes($itemAttrs) . '>'
                . $this->renderHtmlListItemContent($item)
                . '</li>';
        }

        return $html . '</' . $tag . '>';
    }

    private function htmlOrderedListType(AstNode $node): ?string
    {
        return match ((string) $node->attr('style', 'decimal')) {
            'lower_alpha' => 'a',
            'upper_alpha' => 'A',
            'lower_roman' => 'i',
            'upper_roman' => 'I',
            default => null,
        };
    }

    private function htmlListHasTaskItems(AstNode $node): bool
    {
        if ((bool) $node->attr('taskList', false)) {
            return true;
        }

        foreach ($node->children as $item) {
            if ($item->type === 'list_item' && is_bool($item->attr('taskChecked', null))) {
                return true;
            }
        }

        return false;
    }

    private function renderHtmlListItemContent(AstNode $item): string
    {
        $content = $this->renderHtmlBlocks($item->children);
        $task = $item->attr('taskChecked', null);
        if (!is_bool($task)) {
            return $content;
        }

        return '<input type="checkbox"' . ($task ? ' checked=""' : '') . ' />' . $content;
    }

    private function renderHtmlDefinitionList(AstNode $node): string
    {
        $html = '<dl' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>';

        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item' || $item->children === []) {
                continue;
            }

            $term = $item->children[0];
            $html .= '<dt' . $this->renderHtmlAttributes($this->htmlAttributeMap($term)) . '>'
                . $this->renderHtmlDefinitionTerm($term)
                . '</dt>';

            foreach (array_slice($item->children, 1) as $definition) {
                if ($definition->type !== 'definition') {
                    continue;
                }

                $html .= '<dd' . $this->renderHtmlAttributes($this->htmlAttributeMap($definition)) . '>'
                    . $this->renderHtmlBlocks($definition->children)
                    . '</dd>';
            }
        }

        return $html . '</dl>';
    }

    private function renderHtmlDefinitionTerm(AstNode $term): string
    {
        $children = in_array($term->type, ['definition_term', 'term'], true) ? $term->children : [$term];

        return $this->renderHtmlInlines($children);
    }

    private function renderHtmlLineBlock(AstNode $node): string
    {
        $attrs = $this->htmlAttributeMap($node);
        $this->appendHtmlClass($attrs, 'line-block');
        $lines = [];

        foreach ($node->children as $line) {
            if ($line->type !== 'line') {
                continue;
            }

            $lines[] = $line->children === []
                ? $this->escapeHtml((string) $line->attr('text', ''))
                : $this->renderHtmlInlines($line->children);
        }

        return '<div' . $this->renderHtmlAttributes($attrs) . '>' . implode("<br />\n", $lines) . '</div>';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderHtmlInlines(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderHtmlInline($node);
        }

        return $html;
    }

    private function renderHtmlInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => $this->escapeHtml((string) $node->attr('text', '')),
            'space' => ' ',
            'softbreak', 'linebreak' => '<br />',
            'code' => '<code' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->escapeHtml((string) $node->attr('text', ''))
                . '</code>',
            'emph' => '<em>' . $this->renderHtmlInlines($node->children) . '</em>',
            'strong' => '<strong>' . $this->renderHtmlInlines($node->children) . '</strong>',
            'strikeout' => '<del' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->renderHtmlInlines($node->children)
                . '</del>',
            'superscript' => '<sup' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->renderHtmlInlines($node->children)
                . '</sup>',
            'subscript' => '<sub' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->renderHtmlInlines($node->children)
                . '</sub>',
            'small_caps' => $this->renderHtmlSemanticSpan($node, 'smallcaps'),
            'underline' => $this->renderHtmlSemanticSpan($node, 'underline'),
            'span' => '<span' . $this->renderHtmlAttributes($this->htmlAttributeMap($node)) . '>'
                . $this->renderHtmlInlines($node->children)
                . '</span>',
            'quoted' => ((string) $node->attr('kind', 'double') === 'single' ? '&lsquo;' : '&ldquo;')
                . $this->renderHtmlInlines($node->children)
                . ((string) $node->attr('kind', 'double') === 'single' ? '&rsquo;' : '&rdquo;'),
            'link' => $this->renderHtmlLink($node),
            'image' => $this->renderHtmlImage($node),
            'math' => '<span class="math ' . ($node->attr('display') === true ? 'display' : 'inline') . '">'
                . $this->escapeHtml((string) $node->attr('text', ''))
                . '</span>',
            'citation', 'citation_group', 'note' => $this->escapeHtml($this->renderInline($node)),
            'raw_html_inline' => (string) $node->attr('text', $node->attr('html', '')),
            'raw_inline' => $this->isHtmlRawFormat(strtolower((string) $node->attr('format', '')))
                ? (string) $node->attr('text', $node->attr('html', ''))
                : $this->escapeHtml($this->renderRawInline($node)),
            'raw_markdown', 'raw_tex' => $this->escapeHtml($this->renderRawInline($node)),
            default => $this->renderHtmlInlines($node->children),
        };
    }

    private function renderHtmlSemanticSpan(AstNode $node, string $class): string
    {
        $attrs = $this->htmlAttributeMap($node);
        $this->appendHtmlClass($attrs, $class);

        return '<span' . $this->renderHtmlAttributes($attrs) . '>'
            . $this->renderHtmlInlines($node->children)
            . '</span>';
    }

    private function renderHtmlLink(AstNode $node): string
    {
        $attrs = $this->htmlAttributeMap($node);
        $attrs['href'] = (string) $node->attr('url', '');
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        return '<a' . $this->renderHtmlAttributes($attrs) . '>'
            . $this->renderHtmlInlines($node->children)
            . '</a>';
    }

    private function renderHtmlImage(AstNode $node): string
    {
        $attrs = $this->htmlAttributeMap($node);
        $attrs['src'] = (string) $node->attr('url', '');
        $attrs['alt'] = (string) $node->attr('alt', $this->plainInlineText($node->children));
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        return '<img' . $this->renderHtmlAttributes($attrs) . ' />';
    }

    /**
     * @param array<string, string> $attrs
     */
    private function appendHtmlClass(array &$attrs, string $class): void
    {
        $classes = preg_split('/\s+/', trim((string) ($attrs['class'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        array_unshift($classes, $class);
        $attrs['class'] = implode(' ', array_values(array_unique($classes)));
    }

    /**
     * @return array<string, string>
     */
    private function htmlAttributeMap(AstNode $node): array
    {
        return $this->htmlAttributeMapFromSource($node->attrs);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function htmlAttributeMapFromSource(array $source): array
    {
        $attrs = [];
        $htmlAttributes = $source['htmlAttributes'] ?? [];
        if (is_array($htmlAttributes)) {
            foreach ($this->normalizedAttributePairs($htmlAttributes) as $name => $value) {
                $name = strtolower($name);
                if ($name !== '' && !isset($attrs[$name])) {
                    $attrs[$name] = $value;
                }
            }
        }

        if (isset($source['id']) && is_scalar($source['id']) && trim((string) $source['id']) !== '' && !isset($attrs['id'])) {
            $attrs['id'] = trim((string) $source['id']);
        }

        $classes = [];
        if (isset($attrs['class'])) {
            $classes = preg_split('/\s+/', trim($attrs['class']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        foreach ($this->normalizedClassList($source['classes'] ?? $source['className'] ?? null) as $class) {
            $classes[] = $class;
        }
        if ($classes !== []) {
            $attrs['class'] = implode(' ', array_values(array_unique($classes)));
        }

        $attributes = $source['attributes'] ?? [];
        if (is_array($attributes)) {
            foreach ($this->normalizedAttributePairs($attributes) as $name => $value) {
                $name = strtolower($name);
                if ($name !== '' && !isset($attrs[$name])) {
                    $attrs[$name] = $value;
                }
            }
        }

        return $attrs;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function renderHtmlAttributes(array $attrs): string
    {
        $rendered = '';
        foreach (['id', 'class'] as $priority) {
            if (isset($attrs[$priority]) && $this->isAllowedHtmlAttribute($priority, $attrs[$priority])) {
                $rendered .= ' ' . $priority . '="' . $this->escapeHtml($attrs[$priority]) . '"';
                unset($attrs[$priority]);
            }
        }

        foreach ($attrs as $name => $value) {
            $name = strtolower(trim((string) $name));
            if (!$this->isAllowedHtmlAttribute($name, $value)) {
                continue;
            }

            $rendered .= ' ' . $name . '="' . $this->escapeHtml($value) . '"';
        }

        return $rendered;
    }

    private function isAllowedHtmlAttribute(string $name, string $value): bool
    {
        if ($name === '' || $value === '' || preg_match('/\A[a-z][a-z0-9:._-]*\z/', $name) !== 1) {
            return false;
        }

        if (str_starts_with($name, 'on')) {
            return false;
        }

        if ($name === 'style' && preg_match('/(?:expression|url\s*\()/i', $value) === 1) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || in_array($name, [
                'abbr',
                'accesskey',
                'align',
                'alt',
                'axis',
                'border',
                'cellpadding',
                'cellspacing',
                'char',
                'charoff',
                'class',
                'colspan',
                'contenteditable',
                'crossorigin',
                'datetime',
                'decoding',
                'dir',
                'download',
                'draggable',
                'fetchpriority',
                'frame',
                'headers',
                'height',
                'hidden',
                'href',
                'hreflang',
                'id',
                'itemid',
                'itemprop',
                'itemref',
                'itemscope',
                'itemtype',
                'lang',
                'loading',
                'name',
                'popover',
                'referrerpolicy',
                'rel',
                'reversed',
                'role',
                'rowspan',
                'rules',
                'scope',
                'sizes',
                'slot',
                'span',
                'spellcheck',
                'src',
                'srcset',
                'start',
                'style',
                'summary',
                'tabindex',
                'target',
                'title',
                'translate',
                'type',
                'usemap',
                'valign',
                'value',
                'width',
                'xml:lang',
            ], true);
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return list<AstNode>
     */
    /**
     * @return array{0:list<AstNode>,1:list<AstNode>}
     */
    private function tableBodyRows(AstNode $body): array
    {
        $rows = array_values(array_filter(
            $body->children,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));

        $headRows = $this->tableBodyHeadRows($body);
        if ($headRows !== []) {
            $bodyRows = array_values(array_filter(
                $rows,
                static fn (AstNode $row): bool => !in_array($row, $headRows, true)
            ));

            return [$headRows, $bodyRows];
        }

        $headRowCount = max(0, min(count($rows), (int) $body->attr('headRowCount', 0)));
        if ($headRowCount > 0) {
            return [
                array_slice($rows, 0, $headRowCount),
                array_slice($rows, $headRowCount),
            ];
        }

        return [[], $rows];
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodyHeadRows(AstNode $body): array
    {
        $rows = $body->attr('headRows', []);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn (mixed $row): bool => $row instanceof AstNode && $row->type === 'table_row'));
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<string>>
     */
    private function expandTableRows(array $rows, int $columnCount, bool $escapePipes = true): array
    {
        $expandedRows = [];
        foreach (TableGeometry::layoutRows($rows, $columnCount) as $layoutRow) {
            $cells = array_fill(0, $columnCount, '');
            foreach ($layoutRow['cells'] as $layoutCell) {
                $cells[$layoutCell['column']] = $this->renderTableCell($layoutCell['node'], $escapePipes);
            }

            $expandedRows[] = $cells;
        }

        return $expandedRows;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<list<string>>>
     */
    private function expandTableRowsAsLineCells(array $rows, int $columnCount): array
    {
        $expandedRows = [];
        foreach (TableGeometry::layoutRows($rows, $columnCount) as $layoutRow) {
            $cells = array_fill(0, $columnCount, ['']);
            foreach ($layoutRow['cells'] as $layoutCell) {
                $cells[$layoutCell['column']] = $this->renderTableCellLines($layoutCell['node']);
            }

            $expandedRows[] = $cells;
        }

        return $expandedRows;
    }

    private function renderTableCell(AstNode $cell, bool $escapePipes = true): string
    {
        if ($cell->children === []) {
            return $this->normalizeTableCellMarkdown($this->escapeText((string) $cell->attr('text', '')), $escapePipes);
        }

        $hasOnlyInlines = true;
        foreach ($cell->children as $child) {
            if (!$this->isInlineNode($child)) {
                $hasOnlyInlines = false;
                break;
            }
        }

        $markdown = $hasOnlyInlines ? $this->renderInlines($cell->children) : $this->renderBlockCollection($cell->children);

        return $this->normalizeTableCellMarkdown($markdown, $escapePipes);
    }

    /**
     * @return list<string>
     */
    private function renderTableCellLines(AstNode $cell): array
    {
        if ($cell->children === []) {
            $markdown = $this->escapeText((string) $cell->attr('text', ''));
        } else {
            $hasOnlyInlines = true;
            foreach ($cell->children as $child) {
                if (!$this->isInlineNode($child)) {
                    $hasOnlyInlines = false;
                    break;
                }
            }

            $markdown = $hasOnlyInlines ? $this->renderInlines($cell->children) : $this->renderBlockCollection($cell->children);
        }

        $markdown = str_replace('\\|', '|', $markdown);
        $markdown = str_replace(["\\\r\n", "\\\n", "\\\r"], "\n", $markdown);
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $markdown = str_replace("\xC2\xA0", ' ', $markdown);
        $lines = explode("\n", trim($markdown));

        return $lines === [] ? [''] : array_map(static fn (string $line): string => rtrim($line), $lines);
    }

    private function normalizeTableCellMarkdown(string $markdown, bool $escapePipes = true): string
    {
        if ($escapePipes) {
            $markdown = $this->escapeTableCellPipes($markdown);
        } else {
            $markdown = str_replace('\\|', '|', $markdown);
        }
        $markdown = str_replace("\\\r\n", "<br />", $markdown);
        $markdown = str_replace("\\\n", "<br />", $markdown);
        $markdown = str_replace("\\\r", "<br />", $markdown);

        return str_replace(["\r\n", "\r", "\n"], ['<br />', ' ', '<br />'], trim($markdown));
    }

    private function escapeTableCellPipes(string $markdown): string
    {
        return preg_replace('/(?<!\\\\)\|/', '\\\\|', $markdown) ?? $markdown;
    }

    /**
     * @param list<list<string>> $rows
     * @param mixed $relativeWidths
     * @return list<int>
     */
    private function tableColumnWidths(array $rows, mixed $relativeWidths, int $columnCount): array
    {
        $widths = array_fill(0, $columnCount, 3);
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index], UnicodeText::displayWidth($cell));
            }
        }

        if (is_array($relativeWidths)) {
            foreach (array_values($relativeWidths) as $index => $width) {
                if ($index < $columnCount && is_numeric($width) && (float) $width > 0.0) {
                    $widths[$index] = max($widths[$index], (int) ceil((float) $width * 40));
                }
            }
        }

        return $widths;
    }

    /**
     * @return list<float|null>
     */
    private function tableColumnWidthHints(AstNode $node, int $columnCount): array
    {
        $widths = $node->attr('widths', []);
        if (is_array($widths) && $widths !== []) {
            return array_values($widths);
        }

        $hints = [];
        foreach (TableGeometry::columnSpecs($node, $columnCount) as $spec) {
            $hints[] = isset($spec['width']) && is_numeric($spec['width']) ? (float) $spec['width'] : null;
        }

        return $hints;
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(AstNode $node, int $columnCount): array
    {
        return TableGeometry::alignments($node, $columnCount);
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     * @param list<string> $alignments
     */
    private function renderPipeTableRow(array $cells, array $widths, array $alignments): string
    {
        $parts = [];
        foreach ($cells as $index => $cell) {
            $parts[] = ' ' . $this->padTableCell($cell, $widths[$index], $alignments[$index]) . ' ';
        }

        return '|' . implode('|', $parts) . '|';
    }

    /**
     * @param list<list<string>> $headRows
     * @param list<list<string>> $bodyRows
     * @param list<int> $widths
     * @param list<string> $alignments
     * @return list<string>
     */
    private function renderSimpleTable(
        array $headRows,
        array $bodyRows,
        array $widths,
        array $alignments,
        string $prefix,
        string $captionLine,
        string $captionSide
    ): array {
        $lines = [];

        if ($captionLine !== '' && $captionSide === 'top') {
            $lines[] = $captionLine;
            $lines[] = '';
        }

        foreach ($headRows as $row) {
            $lines[] = $prefix . $this->renderSimpleTableRow($row, $widths, $alignments);
        }
        $lines[] = $prefix . $this->renderSimpleTableDelimiter($widths);
        foreach ($bodyRows as $row) {
            $lines[] = $prefix . $this->renderSimpleTableRow($row, $widths, $alignments);
        }

        if ($captionLine !== '' && $captionSide !== 'top') {
            $lines[] = '';
            $lines[] = $captionLine;
        }

        return $lines;
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     * @param list<string> $alignments
     */
    private function renderSimpleTableRow(array $cells, array $widths, array $alignments): string
    {
        $parts = [];
        foreach ($cells as $index => $cell) {
            $parts[] = $this->padTableCell($cell, $widths[$index], $alignments[$index]);
        }

        return rtrim(implode('  ', $parts));
    }

    /**
     * @param list<int> $widths
     */
    private function renderSimpleTableDelimiter(array $widths): string
    {
        return implode('  ', array_map(
            static fn (int $width): string => str_repeat('-', max(3, $width)),
            $widths
        ));
    }

    /**
     * @param list<list<list<string>>> $rows
     * @param mixed $relativeWidths
     * @return list<int>
     */
    private function tableGridColumnWidths(array $rows, mixed $relativeWidths, int $columnCount): array
    {
        $widths = array_fill(0, $columnCount, 3);
        foreach ($rows as $row) {
            foreach ($row as $index => $cellLines) {
                foreach ($cellLines as $line) {
                    $widths[$index] = max($widths[$index], UnicodeText::displayWidth($line));
                }
            }
        }

        if (is_array($relativeWidths)) {
            foreach (array_values($relativeWidths) as $index => $width) {
                if ($index < $columnCount && is_numeric($width) && (float) $width > 0.0) {
                    $widths[$index] = max($widths[$index], (int) ceil((float) $width * 40));
                }
            }
        }

        return $widths;
    }

    /**
     * @param list<list<list<string>>> $headRows
     * @param list<list<list<string>>> $bodyRows
     * @param list<int> $widths
     * @param list<string> $alignments
     * @return list<string>
     */
    private function renderGridTable(
        array $headRows,
        array $bodyRows,
        array $widths,
        array $alignments,
        string $prefix,
        string $captionLine,
        string $captionSide
    ): array {
        $lines = [];

        if ($captionLine !== '' && $captionSide === 'top') {
            $lines[] = $captionLine;
            $lines[] = '';
        }

        $lines[] = $prefix . $this->renderGridTableBorder($widths, '-');
        foreach ($headRows as $row) {
            foreach ($this->renderGridTableRow($row, $widths, $alignments) as $line) {
                $lines[] = $prefix . $line;
            }
        }
        $lines[] = $prefix . $this->renderGridTableBorder($widths, '=');
        if ($bodyRows === []) {
            $lines[] = $prefix . $this->renderGridTableBorder($widths, '-');
        }
        foreach ($bodyRows as $row) {
            foreach ($this->renderGridTableRow($row, $widths, $alignments) as $line) {
                $lines[] = $prefix . $line;
            }
            $lines[] = $prefix . $this->renderGridTableBorder($widths, '-');
        }

        if ($captionLine !== '' && $captionSide !== 'top') {
            $lines[] = '';
            $lines[] = $captionLine;
        }

        return $lines;
    }

    /**
     * @param list<int> $widths
     */
    private function renderGridTableBorder(array $widths, string $char): string
    {
        return '+' . implode('+', array_map(
            static fn (int $width): string => str_repeat($char, max(3, $width) + 2),
            $widths
        )) . '+';
    }

    /**
     * @param list<list<string>> $row
     * @param list<int> $widths
     * @param list<string> $alignments
     * @return list<string>
     */
    private function renderGridTableRow(array $row, array $widths, array $alignments): array
    {
        $height = 1;
        foreach ($row as $cellLines) {
            $height = max($height, count($cellLines));
        }

        $lines = [];
        for ($lineIndex = 0; $lineIndex < $height; $lineIndex++) {
            $parts = [];
            foreach ($row as $column => $cellLines) {
                $line = $cellLines[$lineIndex] ?? '';
                $parts[] = ' ' . $this->padTableCell($line, $widths[$column], $alignments[$column]) . ' ';
            }
            $lines[] = '|' . implode('|', $parts) . '|';
        }

        return $lines;
    }

    private function padTableCell(string $cell, int $width, string $alignment): string
    {
        return UnicodeText::padDisplay($cell, $width, $alignment);
    }

    /**
     * @param list<int> $widths
     * @param list<string> $alignments
     */
    private function renderPipeTableDelimiter(array $widths, array $alignments): string
    {
        $parts = [];
        foreach ($widths as $index => $width) {
            $dashCount = max(3, $width);
            $parts[] = match ($alignments[$index]) {
                'left' => ':' . str_repeat('-', $dashCount - 1),
                'right' => str_repeat('-', $dashCount - 1) . ':',
                'center' => ':' . str_repeat('-', max(1, $dashCount - 2)) . ':',
                default => str_repeat('-', $dashCount),
            };
        }

        return '|' . implode('|', $parts) . '|';
    }

    private function renderTableCaption(AstNode $node): string
    {
        $caption = '';
        $captionBlocks = $this->renderTableCaptionBlocks($this->tableCaptionBlocksForWriting($node));
        if ($captionBlocks !== '') {
            $caption = $captionBlocks;
        } else {
            $captionInlines = $this->tableCaptionInlinesForWriting($node);
            if ($captionInlines !== []) {
                $caption = $this->normalizeTableCaptionMarkdown($this->renderInlines($captionInlines));
            } else {
                $caption = $this->normalizeTableCaptionMarkdown($this->escapeText($this->tableCaptionTextForWriting($node)));
            }
        }

        $shortCaptionInlines = $this->tableShortCaptionInlinesForWriting($node);
        if ($shortCaptionInlines !== []) {
            $shortCaption = '[' . $this->normalizeTableCaptionMarkdown($this->renderInlines($shortCaptionInlines)) . ']';

            return $caption === '' ? $shortCaption : $shortCaption . ' ' . $caption;
        }

        $shortCaptionBlocks = $this->renderTableCaptionBlocks($this->tableShortCaptionBlocksForWriting($node));
        if ($shortCaptionBlocks !== '') {
            $shortCaption = '[' . $shortCaptionBlocks . ']';

            return $caption === '' ? $shortCaption : $shortCaption . ' ' . $caption;
        }

        $shortCaption = $this->tableShortCaptionTextForWriting($node);
        if ($shortCaption !== '') {
            $shortCaption = '[' . $this->normalizeTableCaptionMarkdown($this->escapeText($shortCaption)) . ']';

            return $caption === '' ? $shortCaption : $shortCaption . ' ' . $caption;
        }

        return $caption;
    }

    private function renderTableCaptionLine(AstNode $node, string $prefix): string
    {
        $caption = $this->renderTableCaption($node);
        $attrs = $this->renderTableAttributes($node);
        if ($caption === '' && $attrs === '') {
            return '';
        }

        return $prefix . ': ' . trim($caption . ($attrs === '' ? '' : ' ' . $attrs));
    }

    private function tableCaptionSide(AstNode $node): string
    {
        $side = strtolower(trim((string) $node->attr('captionSide', '')));
        if (in_array($side, ['top', 'bottom'], true)) {
            return $side;
        }

        $captionSource = $node->attr('captionSource', []);
        if (is_array($captionSource)) {
            $side = strtolower(trim((string) ($captionSource['captionSide'] ?? '')));
            if (in_array($side, ['top', 'bottom'], true)) {
                return $side;
            }

            foreach (['captionPlacement', 'position', 'sourcePosition'] as $name) {
                $placement = str_replace('_', '-', strtolower(trim((string) ($captionSource[$name] ?? ''))));
                if (in_array($placement, ['before-table', 'before-table-sections'], true)) {
                    return 'top';
                }
                if ($placement === 'after-table') {
                    return 'bottom';
                }
            }
        }

        return 'bottom';
    }

    /**
     * @return list<AstNode>
     */
    private function tableCaptionBlocksForWriting(AstNode $node): array
    {
        $blocks = $this->explicitCaptionBlocksFromValue($node->attr('captionBlocks', []));
        if ($blocks !== []) {
            return $blocks;
        }

        $source = $this->captionSourceValue($node, ['captionBlocks', 'blocks', 'longBlocks']);

        return $this->captionBlocksFromValue($source);
    }

    /**
     * @return list<AstNode>
     */
    private function tableCaptionInlinesForWriting(AstNode $node): array
    {
        $inlines = $this->captionInlinesFromValue($node->attr('captionInlines', []));
        if ($inlines !== []) {
            return $inlines;
        }

        $caption = $node->attr('caption');
        $inlines = $this->captionInlinesFromValue($caption);
        if ($inlines !== []) {
            return $inlines;
        }

        $source = $this->captionSourceValue($node, ['captionInlines', 'inlines', 'longInlines']);

        return $this->captionInlinesFromValue($source);
    }

    private function tableCaptionTextForWriting(AstNode $node): string
    {
        $text = $this->captionTextFromValue($node->attr('caption', ''));
        if ($text !== '') {
            return $text;
        }

        return $this->captionTextFromValue($this->captionSourceValue($node, ['caption', 'text', 'long']));
    }

    /**
     * @return list<AstNode>
     */
    private function tableShortCaptionBlocksForWriting(AstNode $node): array
    {
        $blocks = $this->explicitCaptionBlocksFromValue($node->attr('shortCaptionBlocks', []));
        if ($blocks !== []) {
            return $blocks;
        }

        $source = $this->captionSourceValue($node, ['shortCaptionBlocks', 'shortBlocks']);

        return $this->captionBlocksFromValue($source);
    }

    /**
     * @return list<AstNode>
     */
    private function tableShortCaptionInlinesForWriting(AstNode $node): array
    {
        $inlines = $this->captionInlinesFromValue($node->attr('shortCaptionInlines', []));
        if ($inlines !== []) {
            return $inlines;
        }

        $shortCaption = $node->attr('shortCaption');
        $inlines = $this->captionInlinesFromValue($shortCaption);
        if ($inlines !== []) {
            return $inlines;
        }

        $source = $this->captionSourceValue($node, ['shortCaptionInlines', 'shortInlines']);

        return $this->captionInlinesFromValue($source);
    }

    private function tableShortCaptionTextForWriting(AstNode $node): string
    {
        $text = $this->captionTextFromValue($node->attr('shortCaption', ''));
        if ($text !== '') {
            return $text;
        }

        return $this->captionTextFromValue($this->captionSourceValue($node, ['shortCaption', 'short', 'shortText']));
    }

    /**
     * @param list<string> $names
     */
    private function captionSourceValue(AstNode $node, array $names): mixed
    {
        $source = $node->attr('captionSource', []);
        if (!is_array($source)) {
            return null;
        }

        foreach ($names as $name) {
            if (array_key_exists($name, $source)) {
                return $source[$name];
            }
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function explicitCaptionBlocksFromValue(mixed $value): array
    {
        if ($value instanceof AstNode) {
            return [$value];
        }

        if (!is_array($value) || $value === []) {
            return [];
        }

        $nodes = array_values($value);

        return $this->allAstNodes($nodes) ? $nodes : [];
    }

    /**
     * @return list<AstNode>
     */
    private function captionBlocksFromValue(mixed $value): array
    {
        if ($value instanceof AstNode) {
            return $this->isInlineNode($value) ? [] : [$value];
        }

        if (!is_array($value) || $value === []) {
            return [];
        }

        if (isset($value['blocks'])) {
            return $this->captionBlocksFromValue($value['blocks']);
        }

        if (isset($value['captionBlocks'])) {
            return $this->captionBlocksFromValue($value['captionBlocks']);
        }

        $nodes = array_values($value);
        if (!$this->allAstNodes($nodes)) {
            return [];
        }

        foreach ($nodes as $node) {
            if (!$this->isInlineNode($node)) {
                return $nodes;
            }
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function captionInlinesFromValue(mixed $value): array
    {
        if ($value instanceof AstNode) {
            if ($this->isInlineNode($value)) {
                return [$value];
            }

            if (in_array($value->type, ['plain', 'paragraph'], true) && $this->allAstNodes($value->children)) {
                return $value->children;
            }

            return [];
        }

        if (!is_array($value) || $value === []) {
            return [];
        }

        foreach (['inlines', 'captionInlines', 'shortCaptionInlines'] as $name) {
            if (isset($value[$name])) {
                return $this->captionInlinesFromValue($value[$name]);
            }
        }

        $nodes = array_values($value);
        if (!$this->allAstNodes($nodes)) {
            return [];
        }

        foreach ($nodes as $node) {
            if (!$this->isInlineNode($node)) {
                return [];
            }
        }

        return $nodes;
    }

    private function captionTextFromValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        $inlines = $this->captionInlinesFromValue($value);
        if ($inlines !== []) {
            return $this->plainInlineText($inlines);
        }

        $blocks = $this->captionBlocksFromValue($value);
        if ($blocks !== []) {
            return $this->plainInlineText($this->flattenPlainHtmlBlockInlines($blocks));
        }

        if (is_array($value)) {
            foreach (['text', 'caption', 'shortCaption', 'shortText'] as $name) {
                if (array_key_exists($name, $value)) {
                    $text = $this->captionTextFromValue($value[$name]);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }

        return '';
    }

    private function renderTableCaptionBlocks(mixed $blocks): string
    {
        if (!is_array($blocks) || $blocks === [] || !$this->allAstNodes($blocks)) {
            return '';
        }

        $parts = [];
        foreach (array_values($blocks) as $block) {
            if (!$block instanceof AstNode) {
                return '';
            }

            $rendered = in_array($block->type, ['plain', 'paragraph'], true) && $this->allAstNodes($block->children)
                ? $this->renderInlines($block->children)
                : $this->renderBlockCollection([$block]);
            $rendered = $this->normalizeTableCaptionMarkdown($rendered);
            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        return implode('<br />', $parts);
    }

    private function normalizeTableCaptionMarkdown(string $markdown): string
    {
        $markdown = str_replace("\\\r\n", '<br />', $markdown);
        $markdown = str_replace("\\\n", '<br />', $markdown);
        $markdown = str_replace("\\\r", '<br />', $markdown);
        $markdown = str_replace(["\r\n", "\r", "\n"], ' ', $markdown);

        return trim(preg_replace('/[ \t]+/u', ' ', $markdown) ?? $markdown);
    }

    /**
     * @param list<mixed> $nodes
     */
    private function allAstNodes(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function renderTableAttributes(AstNode $node): string
    {
        return $this->renderAttributesTuple($this->tableAttrTuple($node), false);
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function tableAttrTuple(AstNode $node): array
    {
        $attrs = $this->linkAttrTuple($node);
        $attrs = $this->mergeAttributeTuples($attrs, $this->tableCaptionSourceAttrTuple($node));
        $attrs['attributes'] = array_filter(
            $attrs['attributes'],
            fn (string $value, string $name): bool => $this->isMarkdownTableAttribute($name),
            ARRAY_FILTER_USE_BOTH
        );

        return $attrs;
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function tableCaptionSourceAttrTuple(AstNode $node): array
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return ['id' => '', 'classes' => [], 'attributes' => []];
        }

        $sourceAttributes = $captionSource['sourceAttributes'] ?? [];
        if (!is_array($sourceAttributes)) {
            return ['id' => '', 'classes' => [], 'attributes' => []];
        }

        return $this->attributeTupleFromSourceAttributes($sourceAttributes);
    }

    /**
     * @param array<string, mixed> $source
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function attributeTupleFromSourceAttributes(array $source): array
    {
        $attrs = $this->htmlAttributeMapFromSource($source);
        $id = trim((string) ($attrs['id'] ?? ''));
        unset($attrs['id']);

        $classes = [];
        if (isset($attrs['class'])) {
            $classes = preg_split('/\s+/', trim($attrs['class']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            unset($attrs['class']);
        }

        return [
            'id' => $id,
            'classes' => array_values(array_unique($classes)),
            'attributes' => array_filter($attrs, static fn (string $value): bool => $value !== ''),
        ];
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $primary
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $secondary
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function mergeAttributeTuples(array $primary, array $secondary): array
    {
        if ($primary['id'] === '' && $secondary['id'] !== '') {
            $primary['id'] = $secondary['id'];
        }

        foreach ($secondary['classes'] as $class) {
            if (!in_array($class, $primary['classes'], true)) {
                $primary['classes'][] = $class;
            }
        }

        foreach ($secondary['attributes'] as $name => $value) {
            if (!array_key_exists($name, $primary['attributes'])) {
                $primary['attributes'][$name] = $value;
            }
        }

        return $primary;
    }

    private function isMarkdownTableAttribute(string $name): bool
    {
        $name = strtolower($name);
        if (str_starts_with($name, 'data-docx-')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || in_array($name, ['dir', 'lang', 'role', 'title', 'xml:lang'], true);
    }

    /**
     * @return list<string>
     */
    private function renderBlockQuote(AstNode $node, int $indent): array
    {
        $body = $this->renderBlockCollection($node->children);
        $prefix = str_repeat(' ', $indent) . '>';
        if ($body === '') {
            return [$prefix];
        }

        $lines = [];
        foreach (explode("\n", $body) as $line) {
            $lines[] = $line === '' ? $prefix : $prefix . ' ' . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderCodeBlock(AstNode $node, int $indent): array
    {
        $attrs = $this->renderCodeBlockAttributes($node);
        if ($attrs !== '' || (bool) ($this->options['fencedCodeBlocks'] ?? false)) {
            return $this->renderFencedCodeBlock($node, $attrs, $indent);
        }

        $info = $this->codeBlockInfo($node);
        if ($info !== '') {
            return $this->renderFencedCodeBlock($node, ' ' . $info, $indent);
        }

        $lines = [];
        $prefix = str_repeat(' ', $indent + 4);
        foreach (explode("\n", (string) $node->attr('text', '')) as $line) {
            $lines[] = $prefix . $line;
        }

        return $lines;
    }

    private function renderCodeBlockAttributes(AstNode $node): string
    {
        $attrs = $this->linkAttrTuple($node);
        if (
            $attrs['id'] === ''
            && $attrs['attributes'] === []
            && count($attrs['classes']) === 1
            && $this->isCodeBlockInfoString($attrs['classes'][0])
        ) {
            return $attrs['classes'][0];
        }

        return $this->renderAttributesTuple($attrs);
    }

    private function isCodeBlockInfoString(string $class): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_+.#-]*\z/u', $class) === 1;
    }

    private function codeBlockInfo(AstNode $node): string
    {
        $info = $node->attr('info', '');
        if (!is_scalar($info)) {
            return '';
        }

        return trim(preg_replace('/[ \t\r\n]+/', ' ', (string) $info) ?? (string) $info);
    }

    /**
     * @return list<string>
     */
    private function renderFencedCodeBlock(AstNode $node, string $attrs, int $indent): array
    {
        $prefix = str_repeat(' ', $indent);
        $text = (string) $node->attr('text', '');
        $fenceChar = (string) ($this->options['fencedCodeBlockStyle'] ?? 'backtick') === 'tilde' ? '~' : '`';
        if ($fenceChar === '`' && str_contains($attrs, '`')) {
            $fenceChar = '~';
        }
        $longestRun = $fenceChar === '~' ? $this->longestTildeRun($text) : $this->longestBacktickRun($text);
        $fence = str_repeat($fenceChar, max(3, $longestRun + 1));

        return [
            $prefix . $fence . $attrs,
            ...array_map(static fn (string $line): string => $prefix . $line, explode("\n", $text)),
            $prefix . $fence,
        ];
    }

    /**
     * @return list<string>
     */
    private function renderDivBlock(AstNode $node, int $indent): array
    {
        $alertType = $this->alertDivType($node);
        if ($alertType !== null) {
            return $this->renderAlertDivBlock($node, $alertType, $indent);
        }

        $attrs = $this->renderLinkAttributes($node);
        $prefix = str_repeat(' ', $indent);
        $body = $this->renderBlockCollection($node->children, true);
        $fenceLength = max(3, $this->longestColonRun($body) + 1);
        $fence = str_repeat(':', $fenceLength);
        $opening = rtrim($prefix . $fence . ($attrs === '' ? '' : ' ' . $attrs));
        $closing = $prefix . $fence;

        if ($body === '') {
            return [$opening, $closing];
        }

        return [
            $opening,
            ...array_map(static fn (string $line): string => $prefix . $line, explode("\n", $body)),
            $closing,
        ];
    }

    private function alertDivType(AstNode $node): ?string
    {
        $attrs = $this->linkAttrTuple($node);
        if ($attrs['id'] !== '' || $attrs['attributes'] !== [] || count($attrs['classes']) !== 1) {
            return null;
        }

        $type = strtolower($attrs['classes'][0]);

        return in_array($type, ['note', 'tip', 'important', 'warning', 'caution'], true) ? $type : null;
    }

    /**
     * @return list<string>
     */
    private function renderAlertDivBlock(AstNode $node, string $type, int $indent): array
    {
        $children = $node->children;
        if (($children[0] ?? null)?->type === 'div' && $children[0]->attr('classes', []) === ['title']) {
            array_shift($children);
        }

        $body = $this->renderBlockCollection($children);
        $prefix = str_repeat(' ', $indent) . '>';
        $lines = [$prefix . ' [!' . strtoupper($type) . ']'];
        if ($body === '') {
            return $lines;
        }

        foreach (explode("\n", $body) as $line) {
            $lines[] = $line === '' ? $prefix : $prefix . ' ' . $line;
        }

        return $lines;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $index => $node) {
            $previous = $nodes[$index - 1] ?? null;
            $text .= $this->renderInline(
                $node,
                array_slice($nodes, $index + 1),
                $index === 0 || $this->previousInlineRenderedLineBreak($previous),
                $previous instanceof AstNode && $this->shouldEscapeLeadingAttributeBrace($node, $previous)
            );
        }

        return $text;
    }

    private function previousInlineRenderedLineBreak(?AstNode $previous): bool
    {
        if ($previous === null) {
            return false;
        }

        return $previous->type === 'linebreak'
            || ($previous->type === 'softbreak' && $this->softBreakMarkdown() !== ' ');
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlineLabelInlines(array $nodes): string
    {
        $this->fancyOrderedMarkerEscapeSuppression++;
        try {
            return $this->renderInlines($nodes);
        } finally {
            $this->fancyOrderedMarkerEscapeSuppression--;
        }
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBracketedLabelInlines(array $nodes): string
    {
        $this->plainTextTriggerEscapeSuppression++;
        try {
            return $this->renderInlineLabelInlines($nodes);
        } finally {
            $this->plainTextTriggerEscapeSuppression--;
        }
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderInline(
        AstNode $node,
        array $following = [],
        bool $escapeDefinitionMarker = false,
        bool $escapeLeadingAttributeBrace = false
    ): string
    {
        return match ($node->type) {
            'text' => $this->escapeText($this->nodeText($node), $escapeDefinitionMarker, $escapeLeadingAttributeBrace),
            'space' => ' ',
            'softbreak' => $this->softBreakMarkdown(),
            'linebreak' => "\\\n",
            'code' => $this->renderCode($node),
            'emph' => $this->delimitInlineContent('*', '*', $this->renderInlineLabelInlines($node->children)),
            'strong' => $this->delimitInlineContent('**', '**', $this->renderInlineLabelInlines($node->children)),
            'strikeout' => $this->renderStrikeout($node),
            'superscript' => $this->renderScript($node, 'superscript', '^'),
            'subscript' => $this->renderScript($node, 'subscript', '~'),
            'small_caps' => $this->renderSmallCaps($node),
            'underline' => $this->renderUnderline($node),
            'span' => $this->renderSpan($node),
            'quoted' => $this->renderQuoted($node),
            'link' => $this->renderLink($node, $following),
            'image' => $this->renderImage($node, $following),
            'math' => $this->renderMath($node),
            'citation' => $this->renderCitation($node),
            'citation_group' => $this->renderCitationGroup($node),
            'raw_tex', 'raw_inline', 'raw_markdown', 'raw_html_inline' => $this->renderRawInline($node),
            'note' => $this->renderNoteReference($node),
            default => $this->renderInlines($node->children),
        };
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderLink(AstNode $node, array $following): string
    {
        $wikiLink = $this->markdownExtensionEnabled('wikilinks') ? $this->renderWikiLink($node) : null;
        if ($wikiLink !== null) {
            return $wikiLink;
        }

        if ($this->canRenderAutolink($node)) {
            return '<' . $this->autolinkText($node) . '>';
        }

        if (!$this->markdownExtensionEnabled('link_attributes') && $this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []]) {
            return $this->renderHtmlLink($node);
        }

        if ((bool) ($this->options['referenceLinks'] ?? false)) {
            return $this->renderReferenceLink($node, $following);
        }

        $title = $this->linkTitle($node);
        $titleMarkdown = $title === '' ? '' : ' "' . $this->escapeLinkTitle($title) . '"';

        return '[' . $this->renderBracketedLabelInlines($node->children) . ']('
            . $this->renderLinkDestination($this->linkUrl($node))
            . $titleMarkdown
            . ')'
            . $this->renderLinkAttributes($node);
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderImage(AstNode $node, array $following): string
    {
        if (!$this->markdownExtensionEnabled('link_attributes') && $this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []]) {
            return $this->renderHtmlImage($node);
        }

        return '!' . $this->renderLink(
            new AstNode('link', $this->imageLinkAttrs($node), $this->imageLabelNodesForLink($node)),
            $following
        );
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderReferenceLink(AstNode $node, array $following): string
    {
        $labelText = $this->renderBracketedLabelInlines($node->children);
        $plainLabel = $this->normalizeReferenceLabelText($this->plainInlineText($node->children));
        $referenceLabel = $this->registerReference(
            $plainLabel,
            $this->linkUrl($node),
            $this->linkTitle($node),
            $this->linkAttrTuple($node)
        );

        $shortcutable = $referenceLabel === $plainLabel && $this->canUseShortcutReference($following);
        if ($shortcutable) {
            return '[' . $labelText . ']';
        }

        $suffix = $referenceLabel === $plainLabel ? '[]' : '[' . $referenceLabel . ']';

        return '[' . $labelText . ']' . $suffix;
    }

    private function renderNoteReference(AstNode $node): string
    {
        $label = $this->registerNoteLabel($node);
        $this->notes[] = [
            'label' => $label,
            'node' => $node,
        ];

        return '[^' . $label . ']';
    }

    private function registerNoteLabel(AstNode $node): string
    {
        $preferred = $this->sourceNoteLabel($node);
        if ($preferred !== null) {
            return $this->uniqueNoteLabel($preferred);
        }

        do {
            $candidate = (string) $this->nextNoteNumber++;
        } while (isset($this->noteUsedLabels[strtolower($candidate)]));

        $this->noteUsedLabels[strtolower($candidate)] = true;

        return $candidate;
    }

    private function sourceNoteLabel(AstNode $node): ?string
    {
        $label = trim($this->scalarAttr($node, ['label', 'noteLabel']));
        if ($label === '' || preg_match('/[\[\]\s]/u', $label) === 1) {
            return null;
        }

        return $label;
    }

    private function uniqueNoteLabel(string $label): string
    {
        $candidate = $label;
        $suffix = 2;
        while (isset($this->noteUsedLabels[strtolower($candidate)])) {
            $candidate = $label . '-' . $suffix;
            $suffix++;
        }

        $this->noteUsedLabels[strtolower($candidate)] = true;

        return $candidate;
    }

    private function renderCitation(AstNode $node): string
    {
        $explicit = $this->explicitCitationMarkdown($node);
        if ($explicit !== null) {
            return $explicit;
        }

        if (
            $this->citationMode($node) === 'author_in_text'
            && $this->citationAffixMarkdown($node, 'prefix') === ''
        ) {
            $suffix = $this->citationSuffixMarkdown($node);
            $token = '@' . $this->citationIdentifierMarkdown($this->citationId($node));

            if ($suffix === '') {
                return $token;
            }

            if ($this->isSourceStyleCitationSuffix($suffix)) {
                return $token . ', ' . $suffix;
            }

            return $token . ' [' . $suffix . ']';
        }

        return '[' . $this->citationItemMarkdown($node) . ']';
    }

    private function renderCitationGroup(AstNode $node): string
    {
        $explicit = $this->explicitCitationMarkdown($node);
        if ($explicit !== null) {
            return $explicit;
        }

        $citations = $this->citationGroupChildren($node);
        if ($citations === []) {
            return '';
        }

        return '[' . implode('; ', array_map(fn (AstNode $citation): string => $this->citationItemMarkdown($citation), $citations)) . ']';
    }

    private function explicitCitationMarkdown(AstNode $node): ?string
    {
        foreach (['rendered', 'text'] as $name) {
            $value = $node->attr($name);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        $sourceInlines = $node->attr('citationSourceInlines', []);
        if (is_array($sourceInlines) && $sourceInlines !== [] && $this->allAstNodes(array_values($sourceInlines))) {
            $source = $this->plainInlineText(array_values($sourceInlines));

            return $source === '' ? null : $source;
        }

        if ($node->type === 'citation' && $node->children !== [] && $this->allAstNodes($node->children)) {
            $source = $this->plainInlineText($node->children);

            return $source === '' ? null : $source;
        }

        return null;
    }

    private function citationItemMarkdown(AstNode $citation): string
    {
        $prefix = $this->citationAffixMarkdown($citation, 'prefix');
        $token = ($this->citationMode($citation) === 'suppress_author' ? '-@' : '@')
            . $this->citationIdentifierMarkdown($this->citationId($citation));
        $suffix = $this->citationSuffixMarkdown($citation);
        $markdown = $prefix === '' ? $token : $prefix . ' ' . $token;

        return $suffix === '' ? $markdown : $markdown . ', ' . $suffix;
    }

    private function citationIdentifierMarkdown(string $id): string
    {
        if (preg_match('/\A[A-Za-z0-9_](?:[A-Za-z0-9_]|[:.#\/$%&+?<>~|-](?=[A-Za-z0-9_]))*\z/u', $id) === 1) {
            return $id;
        }

        return '{' . str_replace(['\\', '}', ']'], ['\\\\', '\\}', '\\]'], $id) . '}';
    }

    private function citationSuffixMarkdown(AstNode $citation): string
    {
        $suffix = $this->citationAffixMarkdown($citation, 'suffix');

        return $suffix === '' ? $this->citationAffixMarkdown($citation, 'locator') : $suffix;
    }

    private function isSourceStyleCitationSuffix(string $suffix): bool
    {
        return preg_match('/\\A(?:p{1,2}|ch|sec|fig)\\.\\s/u', $suffix) === 1;
    }

    private function citationAffixMarkdown(AstNode $citation, string $name): string
    {
        $value = $this->citationAffixValue($citation, $name);
        if (is_array($value) && $this->allAstNodes(array_values($value))) {
            return $this->renderInlines(array_values($value));
        }

        if ($value instanceof AstNode) {
            return $this->renderInline($value);
        }

        if (!is_scalar($value)) {
            return '';
        }

        return $this->escapeText(trim((string) $value));
    }

    /**
     * @return list<AstNode>
     */
    private function citationGroupChildren(AstNode $node): array
    {
        return array_values(array_filter(
            $node->children,
            static fn (AstNode $child): bool => $child->type === 'citation'
        ));
    }

    private function renderCode(AstNode $node): string
    {
        if (!$this->markdownExtensionEnabled('inline_code_attributes') && $this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []]) {
            return $this->renderHtmlInline($node);
        }

        $text = $this->nodeText($node, ['text', 'literal', 'code', 'value', 'content', 'string']);
        $delimiter = str_repeat('`', max(1, $this->longestBacktickRun($text) + 1));
        if (str_contains($text, '`') || str_starts_with($text, ' ') || str_ends_with($text, ' ')) {
            $text = ' ' . $text . ' ';
        }

        return $delimiter . $text . $delimiter . $this->renderLinkAttributes($node);
    }

    private function renderSpan(AstNode $node): string
    {
        $attrs = $this->renderLinkAttributes($node);
        $content = $attrs === ''
            ? $this->renderInlineLabelInlines($node->children)
            : $this->renderBracketedLabelInlines($node->children);
        $emojiAlias = $this->markdownEmojiAlias($node, $content);
        if ($emojiAlias !== null && $this->markdownExtensionEnabled('emoji')) {
            return ':' . $emojiAlias . ':';
        }

        $abbreviation = $this->markdownAbbreviation($node, $content);
        if ($abbreviation !== null && $this->markdownExtensionEnabled('abbreviations')) {
            $this->abbreviationDefinitions[$abbreviation['term']] = $abbreviation['title'];

            return $content;
        }

        if ($this->isMarkdownMarkSpan($node, $content) && $this->markdownExtensionEnabled('mark')) {
            return '==' . $content . '==';
        }

        if (!$this->markdownExtensionEnabled('bracketed_spans') && $this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []]) {
            return $this->renderHtmlInline($node);
        }

        $attrTuple = $this->linkAttrTuple($node);
        if (($attrTuple['classes'][0] ?? null) === 'mark') {
            $content = str_replace('==', '\\=\\=', $content);
        }
        $attrs = $this->renderAttributesTuple($attrTuple);

        return $attrs === '' ? $content : '[' . $content . ']' . $attrs;
    }

    /**
     * @return array{term:string, title:string}|null
     */
    private function markdownAbbreviation(AstNode $node, string $content): ?array
    {
        $attrs = $this->linkAttrTuple($node);
        if (
            $attrs['id'] !== ''
            || $attrs['classes'] !== ['abbr']
            || count($attrs['attributes']) !== 1
            || !isset($attrs['attributes']['title'])
        ) {
            return null;
        }

        $term = $this->plainInlineText($node->children);
        $title = trim((string) $attrs['attributes']['title']);
        if (
            $term === ''
            || $title === ''
            || str_contains($term, "\n")
            || str_contains($term, ']')
            || str_contains($title, "\n")
            || $content !== $this->escapeText($term)
        ) {
            return null;
        }

        return ['term' => $term, 'title' => $title];
    }

    private function isMarkdownMarkSpan(AstNode $node, string $content): bool
    {
        if ($content === '' || str_contains($content, '==')) {
            return false;
        }

        $attrs = $this->linkAttrTuple($node);

        return $attrs['id'] === ''
            && $attrs['classes'] === ['mark']
            && $attrs['attributes'] === [];
    }

    private function renderSmallCaps(AstNode $node): string
    {
        if (!$this->markdownExtensionEnabled('bracketed_spans')) {
            return $this->renderHtmlInline($node);
        }

        $attrs = $this->linkAttrTuple($node);
        array_unshift($attrs['classes'], 'smallcaps');

        return '[' . $this->renderBracketedLabelInlines($node->children) . ']' . $this->renderAttributesTuple($attrs);
    }

    private function renderUnderline(AstNode $node): string
    {
        if (!$this->markdownExtensionEnabled('underline')) {
            return $this->renderHtmlInline($node);
        }

        $attrs = $this->linkAttrTuple($node);
        array_unshift($attrs['classes'], 'underline');

        return '[' . $this->renderBracketedLabelInlines($node->children) . ']' . $this->renderAttributesTuple($attrs);
    }

    private function renderStrikeout(AstNode $node): string
    {
        if (!$this->markdownExtensionEnabled('strikeout')) {
            return $this->renderHtmlInline($node);
        }

        if ($this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []]) {
            return $this->renderAttributedSemanticSpan($node, 'strikeout');
        }

        return $this->delimitInlineContent('~~', '~~', $this->renderInlineLabelInlines($node->children));
    }

    private function renderScript(AstNode $node, string $semanticClass, string $delimiter): string
    {
        if (!$this->markdownExtensionEnabled($semanticClass)) {
            return $this->renderHtmlInline($node);
        }

        if ($this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []]) {
            return $this->renderAttributedSemanticSpan($node, $semanticClass);
        }

        return $this->delimitScriptContent($delimiter, $this->renderInlineLabelInlines($node->children));
    }

    private function renderAttributedSemanticSpan(AstNode $node, string $semanticClass): string
    {
        $attrs = $this->linkAttrTuple($node);
        array_unshift($attrs['classes'], $semanticClass);

        return '[' . $this->renderBracketedLabelInlines($node->children) . ']' . $this->renderAttributesTuple($attrs);
    }

    private function markdownEmojiAlias(AstNode $node, string $content): ?string
    {
        $attrs = $this->linkAttrTuple($node);
        if (
            $attrs['id'] !== ''
            || $attrs['classes'] !== ['emoji']
            || count($attrs['attributes']) !== 1
            || !isset($attrs['attributes']['data-emoji'])
        ) {
            return null;
        }

        $alias = (string) $attrs['attributes']['data-emoji'];
        if (
            preg_match('/\A[A-Za-z0-9_+-]+\z/', $alias) !== 1
            || !MarkdownEmojiAliases::aliasMatchesGlyph($alias, $content)
        ) {
            return null;
        }

        return $content === '' ? null : $alias;
    }

    private function renderQuoted(AstNode $node): string
    {
        if ((string) $node->attr('kind', 'double') === 'single') {
            return "\u{2018}" . $this->renderInlineLabelInlines($node->children) . "\u{2019}";
        }

        return "\u{201C}" . $this->renderInlineLabelInlines($node->children) . "\u{201D}";
    }

    private function renderMath(AstNode $node): string
    {
        if (!$this->markdownExtensionEnabled('tex_math_dollars')) {
            return $this->renderHtmlInline($node);
        }

        $text = $this->escapeMathText($this->nodeText($node, ['text', 'formula', 'math', 'value', 'literal', 'content', 'string']));
        if ($node->attr('display') === true) {
            return '$$' . $text . '$$' . $this->renderLinkAttributes($node);
        }

        return '$' . $text . '$' . $this->renderLinkAttributes($node);
    }

    private function softBreakMarkdown(): string
    {
        return (string) ($this->options['softBreak'] ?? 'preserve') === 'space' ? ' ' : "\n";
    }

    /**
     * @return list<string>
     */
    private function renderRawBlock(AstNode $node, int $indent): array
    {
        $format = strtolower($this->rawFormat($node));
        if (($node->type === 'raw_html' || $this->isHtmlRawFormat($format)) && $this->rawFormatAllowed($format, 'html')) {
            $text = $this->rawText($node, ['text', 'html', 'raw', 'content', 'literal', 'value']);
        } elseif (($node->type === 'raw_markdown' || $this->isMarkdownRawFormat($format)) && $this->rawFormatAllowed($format, 'markdown')) {
            $text = $this->rawText($node, ['text', 'markdown', 'raw', 'content', 'literal', 'value']);
        } elseif (($node->type === 'raw_tex' || $this->isTexRawFormat($format)) && $this->rawFormatAllowed($format, 'tex')) {
            $text = $this->rawText($node, ['text', 'tex', 'raw', 'content', 'literal', 'value']);
        } else {
            return [];
        }

        return array_map(
            static fn (string $line): string => str_repeat(' ', $indent) . $line,
            explode("\n", $text)
        );
    }

    private function renderRawInline(AstNode $node): string
    {
        $format = strtolower($this->rawFormat($node));
        if ($node->type === 'raw_html_inline') {
            if (!$this->rawFormatAllowed($format, 'html')) {
                return '';
            }

            return $this->rawText($node, ['text', 'html', 'raw', 'content', 'literal', 'value']);
        }

        if (($node->type === 'raw_markdown' || $this->isMarkdownRawFormat($format)) && $this->rawFormatAllowed($format, 'markdown')) {
            return $this->rawText($node, ['text', 'markdown', 'raw', 'content', 'literal', 'value']);
        }

        if (($node->type === 'raw_html_inline' || $this->isHtmlRawFormat($format)) && $this->rawFormatAllowed($format, 'html')) {
            return $this->rawText($node, ['text', 'html', 'raw', 'content', 'literal', 'value']);
        }

        if (($node->type === 'raw_tex' || $this->isTexRawFormat($format)) && $this->rawFormatAllowed($format, 'tex')) {
            return $this->rawText($node, ['text', 'tex', 'raw', 'content', 'literal', 'value']);
        }

        return '';
    }

    private function rawFormatAllowed(string $format, string $kind): bool
    {
        return match ($kind) {
            'html' => $this->rawHtmlEnabled(),
            'tex' => $this->rawTexEnabled(),
            'markdown' => $this->rawMarkdownEnabled() && $this->rawMarkdownFormatMatchesTarget($format),
            default => false,
        };
    }

    private function rawMarkdownFormatMatchesTarget(string $format): bool
    {
        $target = $this->writerFormatBase();
        if ($target === null) {
            return true;
        }

        $rawBase = $this->formatBase($format === '' ? 'markdown' : $format);
        if ($rawBase === null || !$this->isMarkdownRawFormat($rawBase)) {
            return false;
        }

        if (in_array($rawBase, ['markdown', 'pandoc'], true)) {
            return true;
        }

        return in_array($rawBase, $this->markdownFormatAliases($target), true);
    }

    /**
     * @return list<string>
     */
    private function markdownFormatAliases(string $format): array
    {
        return match ($format) {
            'gfm', 'markdown_github' => ['gfm', 'markdown_github', 'commonmark'],
            'commonmark' => ['commonmark'],
            'commonmark_x' => ['commonmark', 'commonmark_x', 'gfm', 'markdown_github'],
            'markdown_strict' => ['markdown', 'markdown_strict'],
            'markdown_phpextra' => ['markdown', 'markdown_phpextra'],
            'markdown_mmd' => ['markdown', 'markdown_mmd'],
            default => ['markdown', 'pandoc', 'markdown_strict', 'markdown_phpextra', 'markdown_mmd'],
        };
    }

    private function isMarkdownRawFormat(string $format): bool
    {
        return MarkdownFormatProfile::rawFamily($format) === 'markdown';
    }

    private function isHtmlRawFormat(string $format): bool
    {
        return MarkdownFormatProfile::rawFamily($format) === 'html';
    }

    private function isTexRawFormat(string $format): bool
    {
        return MarkdownFormatProfile::rawFamily($format) === 'tex';
    }

    private function yamlMetadataEnabled(): bool
    {
        return MarkdownFormatProfile::yamlMetadataEnabled($this->options, false);
    }

    private function rawHtmlEnabled(): bool
    {
        if (array_key_exists('rawHtml', $this->options)) {
            return MarkdownFormatProfile::rawHtmlEnabled($this->options, true);
        }

        return $this->markdownExtensionEnabled('raw_html');
    }

    private function rawTexEnabled(): bool
    {
        if (array_key_exists('rawTex', $this->options)) {
            return MarkdownFormatProfile::rawTexEnabled($this->options, true);
        }

        return $this->markdownExtensionEnabled('raw_tex');
    }

    private function rawMarkdownEnabled(): bool
    {
        if (array_key_exists('rawMarkdown', $this->options)) {
            return MarkdownFormatProfile::rawMarkdownEnabled($this->options, true);
        }

        return $this->markdownExtensionEnabled('raw_markdown');
    }

    private function markdownExtensionEnabled(string $extension): bool
    {
        $extension = $this->normalizeMarkdownExtensionName($extension);
        $enabled = $this->defaultMarkdownExtensionEnabled($extension);

        foreach ($this->writerFormatExtensionOverrides() as $name => $state) {
            if ($this->normalizeMarkdownExtensionName($name) === $extension) {
                $enabled = $state;
            }
        }

        foreach ($this->configuredMarkdownExtensionOverrides() as $name => $state) {
            if ($this->normalizeMarkdownExtensionName($name) === $extension) {
                $enabled = $state;
            }
        }

        return $enabled;
    }

    private function defaultMarkdownExtensionEnabled(string $extension): bool
    {
        $format = $this->writerFormatBase();
        if ($format === null || $format === 'commonmark_x') {
            return true;
        }

        if ($format === 'commonmark') {
            return !in_array($extension, [
                'abbreviations',
                'bracketed_spans',
                'emoji',
                'inline_code_attributes',
                'link_attributes',
                'mark',
                'raw_tex',
                'strikeout',
                'subscript',
                'superscript',
                'tex_math_dollars',
                'underline',
                'wikilinks',
            ], true);
        }

        if ($format === 'gfm' || $format === 'markdown_github') {
            return !in_array($extension, [
                'abbreviations',
                'bracketed_spans',
                'inline_code_attributes',
                'link_attributes',
                'mark',
                'raw_tex',
                'subscript',
                'superscript',
                'tex_math_dollars',
                'underline',
                'wikilinks',
            ], true);
        }

        return true;
    }

    private function normalizeMarkdownExtensionName(string $extension): string
    {
        $extension = strtolower(trim($extension));
        $extension = str_replace('-', '_', $extension);

        return match ($extension) {
            'attributes', 'native_spans', 'span_attributes' => 'bracketed_spans',
            'code_attributes' => 'inline_code_attributes',
            'emoji_shortcode', 'emoji_shortcodes' => 'emoji',
            'link_attribute', 'link_attrs', 'image_attributes' => 'link_attributes',
            'raw_latex', 'latex_macros' => 'raw_tex',
            'tex_math', 'math_dollars' => 'tex_math_dollars',
            'wiki_links' => 'wikilinks',
            default => $extension,
        };
    }

    private function writerFormatBase(): ?string
    {
        $format = $this->options['format'] ?? null;
        if (!is_scalar($format)) {
            return null;
        }

        return $this->formatBase((string) $format);
    }

    private function formatBase(string $format): ?string
    {
        $format = strtolower(trim($format));
        if ($format === '') {
            return null;
        }

        $format = str_replace('-', '_', $format);
        $parts = preg_split('/(?=[+-])/', $format, 2) ?: [$format];
        $base = ltrim((string) ($parts[0] ?? ''), '+');
        $base = match ($base) {
            'markdown+github' => 'markdown_github',
            'markdown+strict' => 'markdown_strict',
            default => $base,
        };

        return $base === '' ? null : $base;
    }

    /**
     * @return array<string, bool>
     */
    private function writerFormatExtensionOverrides(): array
    {
        $format = $this->options['format'] ?? null;
        if (!is_scalar($format)) {
            return [];
        }

        $format = str_replace('-', '_', strtolower(trim((string) $format)));
        preg_match_all('/([+-])([A-Za-z0-9_]+)/', $format, $matches, PREG_SET_ORDER);
        $overrides = [];
        foreach ($matches as $match) {
            $overrides[$match[2]] = $match[1] === '+';
        }

        return $overrides;
    }

    /**
     * @return array<string, bool>
     */
    private function configuredMarkdownExtensionOverrides(): array
    {
        $extensions = $this->options['extensions'] ?? [];
        if (is_string($extensions)) {
            $extensions = preg_split('/[\s,]+/', trim($extensions), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (!is_array($extensions)) {
            return [];
        }

        $overrides = [];
        foreach ($extensions as $name => $value) {
            if (is_int($name)) {
                if (!is_scalar($value)) {
                    continue;
                }

                $token = trim((string) $value);
                if ($token === '') {
                    continue;
                }

                $state = true;
                if ($token[0] === '+' || $token[0] === '-') {
                    $state = $token[0] === '+';
                    $token = substr($token, 1);
                }

                if ($token !== '') {
                    $overrides[$token] = $state;
                }
                continue;
            }

            if (!is_scalar($name)) {
                continue;
            }

            if (is_bool($value)) {
                $overrides[(string) $name] = $value;
                continue;
            }

            if (is_scalar($value)) {
                $normalized = strtolower(trim((string) $value));
                if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                    $overrides[(string) $name] = true;
                } elseif (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                    $overrides[(string) $name] = false;
                }
            }
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function registerReference(string $suggestedLabel, string $url, string $title, array $attrs): string
    {
        $targetKey = $url . "\0" . $title . "\0" . $this->attributeSignature($attrs);
        if (isset($this->referenceTargetLabels[$targetKey])) {
            return $this->referenceTargetLabels[$targetKey];
        }

        $label = $this->normalizeReferenceLabelText($suggestedLabel);
        if ($this->requiresGeneratedReferenceLabel($label)) {
            $actualLabel = $this->nextGeneratedReferenceLabel();
        } else {
            $key = strtolower($label);
            $use = $this->referenceLabelUses[$key] ?? 0;
            $this->referenceLabelUses[$key] = $use + 1;
            $actualLabel = $use === 0 && !isset($this->referenceUsedLabels[$key])
                ? $label
                : $this->nextGeneratedReferenceLabel();
        }

        $this->referenceUsedLabels[strtolower($actualLabel)] = true;
        $this->referenceTargetLabels[$targetKey] = $actualLabel;
        $this->references[] = [
            'label' => $actualLabel,
            'url' => $url,
            'title' => $title,
            'attrs' => $attrs,
        ];

        return $actualLabel;
    }

    private function requiresGeneratedReferenceLabel(string $label): bool
    {
        return $label === ''
            || strlen($label) > 999
            || str_contains($label, '[')
            || str_contains($label, ']');
    }

    private function nextGeneratedReferenceLabel(): string
    {
        do {
            $this->lastReferenceIndex++;
            $candidate = (string) $this->lastReferenceIndex;
        } while (isset($this->referenceUsedLabels[strtolower($candidate)]));

        return $candidate;
    }

    /**
     * @param list<AstNode> $following
     */
    private function canUseShortcutReference(array $following): bool
    {
        $next = $following[0] ?? null;
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation' || $next->type === 'citation_group') {
            return false;
        }

        if ($this->inlineStartsWithReferenceSuffixConflict($next)) {
            return false;
        }

        if ($next->type === 'softbreak' || $next->type === 'linebreak') {
            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        if ($next->type === 'raw_inline' || $next->type === 'raw_markdown' || $next->type === 'raw_html_inline') {
            return !$this->startsWithReferenceSuffixConflict((string) $next->attr(
                'text',
                $next->attr('markdown', $next->attr('html', ''))
            ));
        }

        if ($next->type !== 'text') {
            return true;
        }

        $text = (string) $next->attr('text', '');
        if ($text === '') {
            return $this->canUseShortcutReference(array_slice($following, 1));
        }

        if ($this->startsWithReferenceSuffixConflict($text)) {
            return false;
        }

        $withoutLeadingSpace = ltrim($text, " \t\r\n");
        if ($withoutLeadingSpace !== $text) {
            if ($withoutLeadingSpace !== '') {
                return !str_starts_with($withoutLeadingSpace, '[');
            }

            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        return true;
    }

    /**
     * @param list<AstNode> $following
     */
    private function canUseShortcutReferenceAfterWhitespace(array $following): bool
    {
        $next = $following[0] ?? null;
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation' || $next->type === 'citation_group') {
            return false;
        }

        if ($next->type === 'text') {
            $text = (string) $next->attr('text', '');

            return $text === '' || !str_starts_with(ltrim($text, " \t\r\n"), '[');
        }

        if ($next->type === 'raw_inline' || $next->type === 'raw_markdown' || $next->type === 'raw_html_inline') {
            $raw = (string) $next->attr('text', $next->attr('markdown', $next->attr('html', '')));

            return !str_starts_with(ltrim($raw, " \t\r\n"), '[');
        }

        return true;
    }

    private function startsWithReferenceSuffixConflict(string $text): bool
    {
        return str_starts_with($text, '[')
            || str_starts_with($text, '(')
            || str_starts_with($text, ':')
            || str_starts_with($text, '{')
            || str_starts_with($text, ' [');
    }

    private function inlineStartsWithReferenceSuffixConflict(AstNode $node): bool
    {
        if ($node->type === 'note') {
            return true;
        }

        if ($node->type === 'small_caps' || $node->type === 'underline') {
            return true;
        }

        if ($node->type === 'span') {
            $attrs = $this->linkAttrTuple($node);

            return $attrs['id'] !== ''
                || $attrs['classes'] !== []
                || $attrs['attributes'] !== [];
        }

        if ($node->type === 'strikeout') {
            return $this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []];
        }

        if ($node->type === 'superscript' || $node->type === 'subscript') {
            return $this->linkAttrTuple($node) !== ['id' => '', 'classes' => [], 'attributes' => []];
        }

        return false;
    }

    private function shouldEscapeLeadingAttributeBrace(AstNode $node, AstNode $previous): bool
    {
        return $node->type === 'text'
            && str_starts_with((string) $node->attr('text', ''), '{')
            && in_array($previous->type, ['link', 'image'], true);
    }

    private function delimitInlineContent(string $opener, string $closer, string $content): string
    {
        if ($content === '') {
            return '';
        }

        $leading = '';
        if (preg_match('/^\s+/u', $content, $match) === 1) {
            $leading = $match[0];
            $content = substr($content, strlen($leading));
        }

        $trailing = '';
        if (preg_match('/\s+$/u', $content, $match) === 1) {
            $trailing = $match[0];
            $content = substr($content, 0, strlen($content) - strlen($trailing));
        }

        return $leading . $opener . $content . $closer . $trailing;
    }

    private function delimitScriptContent(string $delimiter, string $content): string
    {
        $delimited = $this->delimitInlineContent($delimiter, $delimiter, str_replace(' ', '\\ ', $content));

        return str_replace("\xC2\xA0", '\\ ', $delimited);
    }

    private function escapeMathText(string $text): string
    {
        return preg_replace('/(?<!\\\\)\$/', '\\\$', $text) ?? $text;
    }

    private function escapeText(
        string $text,
        bool $escapeDefinitionMarker = true,
        bool $escapeLeadingAttributeBrace = false
    ): string
    {
        $escaped = '';
        $length = strlen($text);
        $lineStart = $escapeDefinitionMarker;
        $lineStartSpaces = 0;
        $definitionLineStart = $escapeDefinitionMarker;

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            $tail = substr($text, $i);

            if ($i === 0 && $escapeLeadingAttributeBrace && $char === '{') {
                $escaped .= '\\{';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($char === "\n") {
                $escaped .= "\n";
                $lineStart = true;
                $lineStartSpaces = 0;
                $definitionLineStart = true;
                continue;
            }

            if ($lineStart && $char === ' ' && $lineStartSpaces < 3) {
                $escaped .= ' ';
                $lineStartSpaces++;
                continue;
            }

            if ($lineStart && $char === '<' && $this->startsWithRawHtmlBlockTag($tail)) {
                $escaped .= '&lt;';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $this->startsWithSetextEqualsUnderline($tail)) {
                $escaped .= '\\=';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $char === '-' && $this->startsWithDashUnderlineOrThematicBreak($tail)) {
                $dashRun = strspn($tail, '-');
                $escaped .= str_repeat('\\-', $dashRun);
                $i += $dashRun - 1;
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $char === '#' && $this->startsWithAtxHeadingMarker($tail)) {
                $escaped .= '\\#';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && preg_match('/^([0-9]+)([.)])(?=[ \t]|$)/', $tail, $match) === 1) {
                $escaped .= $match[1] . '\\' . $match[2];
                $i += strlen($match[1]);
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $this->fancyOrderedMarkerEscapeSuppression === 0 && preg_match('/^#([.)])(?=[ \t]|$)/', $tail) === 1) {
                $escaped .= '\\#';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $this->fancyOrderedMarkerEscapeSuppression === 0 && $this->startsWithParenthesizedOrderedListMarker($tail, $lineStartSpaces > 0)) {
                $escaped .= '\\(';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $this->fancyOrderedMarkerEscapeSuppression === 0 && $this->matchFancyOrderedListMarker($tail, $match, $lineStartSpaces > 0)) {
                $escaped .= $match[1] . '\\' . $match[2];
                $i += strlen($match[1]);
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($lineStart && $this->startsWithBulletListMarker($tail)) {
                $escaped .= '\\' . $char;
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($definitionLineStart && $this->startsWithDefinitionMarker($tail)) {
                $escaped .= '\\' . $char;
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if (
                $char === '@'
                && $this->plainTextTriggerEscapeSuppression === 0
                && ($this->startsWithBareCitationMarker($text, $i) || $this->isBareEmailAtSign($text, $i))
            ) {
                $escaped .= '\\@';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($char === ':' && $this->plainTextTriggerEscapeSuppression === 0 && $this->isBareUriSchemeColon($text, $i)) {
                $escaped .= '\\:';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($char === '.' && $this->plainTextTriggerEscapeSuppression === 0 && $this->isBareWwwAutolinkDot($text, $i)) {
                $escaped .= '\\.';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if (str_starts_with($tail, '...')) {
                $dotRun = strspn($tail, '.');
                if ($dotRun > 3) {
                    $escaped .= str_repeat('\\.', $dotRun);
                    $i += $dotRun - 1;
                } else {
                    $escaped .= '\\...';
                    $i += 2;
                }
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if (str_starts_with($tail, '--')) {
                $dashRun = strspn($tail, '-');
                if ($dashRun > 2) {
                    $escaped .= str_repeat('\\-', $dashRun);
                    $i += $dashRun - 1;
                } else {
                    $escaped .= '\\--';
                    $i++;
                }
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if (str_starts_with($tail, ':::' )) {
                $colonRun = strspn($tail, ':');
                $escaped .= '\\' . str_repeat(':', $colonRun);
                $i += $colonRun - 1;
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if (str_starts_with($tail, '![')) {
                $escaped .= '\\![';
                $i++;
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if (str_starts_with($tail, '~~')) {
                $escaped .= '\\~\\~';
                $i++;
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($char === '&' && preg_match('/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]+);/', $tail) === 1) {
                $escaped .= '\\&';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($char === '\\') {
                $escaped .= '\\\\';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            if ($char === '_' && $this->isIntrawordUnderscore($text, $i)) {
                $escaped .= '_';
                $lineStart = false;
                $definitionLineStart = false;
                continue;
            }

            $escaped .= match ($char) {
                '[', ']', '`', '*', '_', '|', '^', '~', '$', '\'', '"' => '\\' . $char,
                '>', '<' => '\\' . $char,
                default => $char,
            };
            $lineStart = false;
            $definitionLineStart = false;
        }

        return $escaped;
    }

    private function longestColonRun(string $text): int
    {
        if (preg_match_all('/:+/', $text, $matches) < 1) {
            return 0;
        }

        return max(array_map('strlen', $matches[0]));
    }

    private function longestBacktickRun(string $text): int
    {
        if (preg_match_all('/`+/', $text, $matches) < 1) {
            return 0;
        }

        return max(array_map('strlen', $matches[0]));
    }

    private function longestTildeRun(string $text): int
    {
        if (preg_match_all('/~+/', $text, $matches) < 1) {
            return 0;
        }

        return max(array_map('strlen', $matches[0]));
    }

    private function startsWithAtxHeadingMarker(string $text): bool
    {
        $offset = strspn($text, '#');

        return $offset > 0 && ($offset === strlen($text) || $text[$offset] === ' ' || $text[$offset] === "\t");
    }

    private function startsWithBulletListMarker(string $text): bool
    {
        return preg_match('/^[*+-](?:[ \t]|$)/', $text) === 1;
    }

    private function startsWithRawHtmlBlockTag(string $text): bool
    {
        return preg_match(
            '/^<\/?(?:address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|pre|script|search|section|summary|style|table|tbody|td|tfoot|th|thead|title|tr|track|ul)(?=[\s>\/])/i',
            $text
        ) === 1;
    }

    private function startsWithSetextHeadingUnderline(string $text): bool
    {
        return preg_match('/^=+[ \t]*(?:\n|\z)/', $text) === 1;
    }

    private function startsWithDashUnderlineOrThematicBreak(string $text): bool
    {
        return preg_match('/^-{3,}[ \t]*(?:\n|\z)/', $text) === 1;
    }

    /**
     * @param array<int, string> $match
     */
    private function matchFancyOrderedListMarker(string $text, array &$match, bool $allowIndentedSingleSpace = false): bool
    {
        if (preg_match('/^([A-Za-z]+)([.)])(?=[ \t]|$)/', $text, $match) !== 1) {
            return false;
        }

        $spacesAfterMarker = strspn($text, " \t", strlen($match[1]) + 1);
        if ($allowIndentedSingleSpace && strlen($match[1]) === 1 && $spacesAfterMarker >= 1) {
            return true;
        }

        return $this->isFancyOrderedListMarkerToken($match[1], $match[2], $spacesAfterMarker);
    }

    private function startsWithParenthesizedOrderedListMarker(string $text, bool $allowIndentedSingleSpace = false): bool
    {
        if (preg_match('/^\(@\)(?=[ \t]|$)/', $text) === 1) {
            return true;
        }

        if (preg_match('/^\(@[A-Za-z0-9_-]+\)(?=[ \t])/', $text) === 1) {
            return true;
        }

        if (preg_match('/^\(([0-9]{1,9}|[A-Za-z]+)\)([ \t]*)/', $text, $match) !== 1) {
            return false;
        }

        if (($match[2] ?? '') === '' && strlen($match[0]) < strlen($text)) {
            return false;
        }

        if (ctype_digit($match[1])) {
            return true;
        }

        if ($allowIndentedSingleSpace && strlen($match[1]) === 1 && strlen($match[2]) >= 1) {
            return true;
        }

        return strlen($match[1]) === 1 && strlen($match[2]) >= 2;
    }

    private function isFancyOrderedListMarkerToken(string $token, string $delimiter, int $spacesAfterMarker): bool
    {
        if ($delimiter === '.' && $this->isRomanNumeralMarker($token)) {
            return strlen($token) > 1 || $spacesAfterMarker >= 2;
        }

        return strlen($token) === 1 && $spacesAfterMarker >= 2;
    }

    private function isRomanNumeralMarker(string $token): bool
    {
        return preg_match('/^(?=[MDCLXVI]+$)M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', strtoupper($token)) === 1;
    }

    private function startsWithDefinitionMarker(string $text): bool
    {
        return preg_match('/^[:~](?:[ \t]|$)/', $text) === 1;
    }

    private function startsWithCitationMarker(string $text, int $offset): bool
    {
        if (!isset($text[$offset + 1]) || preg_match('/[A-Za-z0-9_{]/', $text[$offset + 1]) !== 1) {
            return false;
        }

        if ($offset === 0) {
            return true;
        }

        if ($text[$offset - 1] === '(') {
            if (preg_match('/^\(@[A-Za-z0-9_-]+\)(?=[ \t])/', substr($text, $offset - 1)) === 1) {
                return false;
            }

            return $offset === 1 || $text[$offset - 2] === "\n" || $text[$offset - 2] === "\r";
        }

        return preg_match('/[ \t\r\n\[;,:]/', $text[$offset - 1]) === 1;
    }

    private function startsWithBareCitationMarker(string $text, int $offset): bool
    {
        if (($text[$offset] ?? '') !== '@') {
            return false;
        }

        $previous = $offset === 0 ? '' : $text[$offset - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return false;
        }

        if (
            $previous === '('
            && $this->isIndentedLineStart($text, $offset - 1)
            && $this->startsWithParenthesizedOrderedListMarker(substr($text, $offset - 1))
        ) {
            return false;
        }

        if ($previous === '(' && $this->startsWithKnownNumberedExampleReference($text, $offset)) {
            return false;
        }

        return preg_match(
            '/\G@(?:\{[^}\r\n]+\}|[A-Za-z0-9_:.#\/$%&+?<>~|-]*[A-Za-z0-9_#\/$%&+?<>~|-])/u',
            $text,
            $match,
            0,
            $offset
        ) === 1;
    }

    private function startsWithKnownNumberedExampleReference(string $text, int $offset): bool
    {
        return preg_match('/\G@([A-Za-z0-9_-]+)\)/', $text, $match, 0, $offset) === 1
            && isset($this->numberedExampleLabels[$match[1]]);
    }

    private function isBareEmailAtSign(string $text, int $offset): bool
    {
        if (($text[$offset] ?? '') !== '@') {
            return false;
        }

        $localStart = $offset;
        while (
            $localStart > 0
            && preg_match('/[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]/', $text[$localStart - 1]) === 1
        ) {
            $localStart--;
        }

        if ($localStart === $offset) {
            return false;
        }

        $previous = $localStart === 0 ? '' : $text[$localStart - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return false;
        }

        return preg_match(
            '/\G@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+/u',
            $text,
            $match,
            0,
            $offset
        ) === 1;
    }

    private function isBareUriSchemeColon(string $text, int $offset): bool
    {
        if (($text[$offset] ?? '') !== ':') {
            return false;
        }

        $schemeStart = $offset;
        while (
            $schemeStart > 0
            && preg_match('/[A-Za-z0-9+.-]/', $text[$schemeStart - 1]) === 1
        ) {
            $schemeStart--;
        }

        if ($schemeStart === $offset) {
            return false;
        }

        $scheme = strtolower(substr($text, $schemeStart, $offset - $schemeStart));
        if (!in_array($scheme, ['http', 'https', 'git', 'file', 'mailto', 'doi'], true)) {
            return false;
        }

        $previous = $schemeStart === 0 ? '' : $text[$schemeStart - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return false;
        }

        $pattern = match ($scheme) {
            'http', 'https', 'git', 'file' => '~\G' . preg_quote(substr($text, $schemeStart, $offset - $schemeStart), '~') . '://[^\s<>"\']+~iu',
            'mailto' => '~\Gmailto:[^\s<>"\']+~iu',
            'doi' => '~\Gdoi:10\.[^\s<>"\']+~iu',
            default => null,
        };

        return $pattern !== null && preg_match($pattern, $text, $match, 0, $schemeStart) === 1;
    }

    private function isBareWwwAutolinkDot(string $text, int $offset): bool
    {
        if (($text[$offset] ?? '') !== '.' || $offset < 3 || strcasecmp(substr($text, $offset - 3, 3), 'www') !== 0) {
            return false;
        }

        $start = $offset - 3;
        $previous = $start === 0 ? '' : $text[$start - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return false;
        }

        return preg_match('~\Gwww\.[^\s<>"\']+~iu', $text, $match, 0, $start) === 1
            && strlen($match[0]) > 4;
    }

    private function isIndentedLineStart(string $text, int $offset): bool
    {
        $lineStart = strrpos(substr($text, 0, $offset), "\n");
        $prefix = substr($text, $lineStart === false ? 0 : $lineStart + 1, $lineStart === false ? $offset : $offset - $lineStart - 1);

        return preg_match('/^[ \t]{0,3}$/', $prefix) === 1;
    }

    private function startsWithSetextEqualsUnderline(string $text): bool
    {
        return preg_match('/^=+[ \t]*(?:\n|\z)/', $text) === 1;
    }

    private function isIntrawordUnderscore(string $text, int $offset): bool
    {
        $previous = $text[$offset - 1] ?? '';
        $next = $text[$offset + 1] ?? '';

        return $previous !== ''
            && $next !== ''
            && preg_match('/[A-Za-z0-9]/', $previous) === 1
            && preg_match('/[A-Za-z0-9]/', $next) === 1;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => $this->nodeText($node, ['text', 'value', 'literal', 'code', 'content', 'string']),
                'math' => $this->nodeText($node, ['text', 'formula', 'math', 'value', 'literal', 'content', 'string']),
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainBlockText(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            $text = $this->plainNodeText($node);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        $text = implode(' ', $parts);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function plainNodeText(AstNode $node): string
    {
        if ($node->type === 'raw_html' || $node->type === 'raw_markdown' || $node->type === 'raw_block') {
            return trim((string) $node->attr('text', $node->attr('html', $node->attr('markdown', ''))));
        }

        if ($this->isInlineNode($node)) {
            return $this->plainInlineText([$node]);
        }

        if (in_array($node->type, ['paragraph', 'plain', 'heading', 'table_cell'], true)) {
            return $this->plainInlineText($node->children);
        }

        if ($node->type === 'code_block') {
            return trim((string) $node->attr('text', ''));
        }

        return $this->plainBlockText($node->children);
    }

    private function normalizeReferenceLabelText(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', $label) ?? $label);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBlockCollection(array $nodes, bool $sectionBoundaries = false): string
    {
        $blocks = [];
        $previous = null;
        foreach ($nodes as $node) {
            if ($sectionBoundaries && $this->referenceLocation() === 'end_of_section' && $node->type === 'heading' && $blocks !== []) {
                $this->appendPendingDefinitions($blocks);
            }

            if ($previous instanceof AstNode && $this->needsBlockSeparator($previous, $node)) {
                $blocks[] = '<!-- -->';
            }

            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
            $previous = $node;
        }

        if ($sectionBoundaries && $this->referenceLocation() === 'end_of_section') {
            $this->appendPendingDefinitions($blocks);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param list<string> $blocks
     */
    private function appendPendingDefinitions(array &$blocks): void
    {
        foreach ($this->pendingDefinitionBlocks() as $definitionBlock) {
            if ($definitionBlock !== '') {
                $blocks[] = $definitionBlock;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pendingDefinitionBlocks(): array
    {
        $blocks = [];
        while ($this->notes !== [] || $this->references !== [] || $this->abbreviationDefinitions !== []) {
            $notes = $this->notes;
            $references = $this->references;
            $abbreviations = $this->abbreviationDefinitions;
            $this->notes = [];
            $this->references = [];
            $this->abbreviationDefinitions = [];

            foreach ($notes as $note) {
                $blocks[] = $this->renderNoteDefinition($note['label'], $note['node']);
            }

            $referenceDefinitions = [];
            foreach ($references as $reference) {
                $referenceDefinitions[] = $this->renderReferenceDefinition($reference);
            }
            if ($referenceDefinitions !== []) {
                $blocks[] = implode("\n", $referenceDefinitions);
            }

            $abbreviationDefinitions = [];
            foreach ($abbreviations as $term => $title) {
                $abbreviationDefinitions[] = $this->renderAbbreviationDefinition($term, $title);
            }
            if ($abbreviationDefinitions !== []) {
                $blocks[] = implode("\n", $abbreviationDefinitions);
            }
        }

        return $blocks;
    }

    private function renderNoteDefinition(string $label, AstNode $node): string
    {
        $body = $this->renderBlockCollection($node->children);
        if ($body === '') {
            return '[^' . $label . ']:';
        }

        $lines = explode("\n", $body);
        if ($this->noteDefinitionStartsWithIndentedCodeBlock($node)) {
            $rendered = '[^' . $label . ']:';
            foreach ($lines as $line) {
                $rendered .= "\n" . ($line === '' ? '' : '    ' . $line);
            }

            return $rendered;
        }

        $first = array_shift($lines);
        $rendered = '[^' . $label . ']: ' . $first;
        foreach ($lines as $line) {
            $rendered .= "\n" . ($line === '' ? '' : '    ' . $line);
        }

        return $rendered;
    }

    private function noteDefinitionStartsWithIndentedCodeBlock(AstNode $node): bool
    {
        $first = $node->children[0] ?? null;

        return $first instanceof AstNode && $this->rendersIndentedCodeBlock($first);
    }

    /**
     * @param array{label:string, url:string, title:string, attrs:array<string, mixed>} $reference
     */
    private function renderReferenceDefinition(array $reference): string
    {
        $title = $reference['title'] === ''
            ? ''
            : ' "' . $this->escapeLinkTitle($reference['title']) . '"';
        $attrs = $this->renderAttributesTuple($reference['attrs']);

        return '  [' . $reference['label'] . ']: '
            . $this->renderLinkDestination($reference['url'])
            . $title
            . ($attrs === '' ? '' : ' ' . $attrs);
    }

    private function renderAbbreviationDefinition(string $term, string $title): string
    {
        $term = trim(preg_replace('/\s+/', ' ', $term) ?? $term);
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);

        return '*[' . $term . ']: ' . $title;
    }

    private function renderWikiLink(AstNode $node): ?string
    {
        $attrs = $this->linkAttrTuple($node);
        if (
            $attrs['id'] !== ''
            || $attrs['classes'] !== ['wikilink']
            || $attrs['attributes'] !== []
            || $this->linkTitle($node) !== ''
            || count($node->children) !== 1
            || $node->children[0]->type !== 'text'
        ) {
            return null;
        }

        $label = $this->nodeText($node->children[0]);
        $url = $this->linkUrl($node);
        if ($label === '' || $url === '' || str_contains($label, "\n") || str_contains($url, "\n")) {
            return null;
        }

        $target = $this->escapeWikiLinkComponent($url);
        if ($label === $url) {
            return '[[' . $target . ']]';
        }

        return '[[' . $this->escapeWikiLinkComponent($label) . '|' . $target . ']]';
    }

    private function escapeWikiLinkComponent(string $text): string
    {
        $text = strtr($text, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
        ]);

        $escaped = '';
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            $escaped .= match ($char) {
                '\\' => '\\\\',
                '|', ']' => '\\' . $char,
                default => $char,
            };
        }

        return $escaped;
    }

    private function canRenderAutolink(AstNode $node): bool
    {
        $url = $this->linkUrl($node);
        if (!$this->isUriLike($url) || $this->linkTitle($node) !== '') {
            return false;
        }

        $attrs = $this->linkAttrTuple($node);
        $classes = $attrs['classes'];
        $isEmailAutolink = $this->isMailtoUrl($url);
        if (
            $attrs['id'] !== ''
            || $attrs['attributes'] !== []
            || (
                $isEmailAutolink
                    ? ($classes !== [] && $classes !== ['email'])
                    : ($classes !== [] && $classes !== ['uri'])
            )
        ) {
            return false;
        }

        if (count($node->children) !== 1 || $node->children[0]->type !== 'text') {
            return false;
        }

        $label = $this->nodeText($node->children[0]);
        $suffix = $this->autolinkText($node);
        if (!$this->isSafeAutolinkText($node, $suffix)) {
            return false;
        }

        return $this->canRenderAutolinkText($suffix)
            && (
                $isEmailAutolink
                    ? $this->isValidEmailAutolinkText($suffix)
                    : $this->isValidUriAutolinkText($url)
            )
            && ($label === $suffix || $this->escapeUri($label) === $suffix);
    }

    private function autolinkText(AstNode $node): string
    {
        $url = $this->linkUrl($node);

        return $this->isMailtoUrl($url) ? substr($url, 7) : $url;
    }

    private function isMailtoUrl(string $url): bool
    {
        return str_starts_with(strtolower($url), 'mailto:');
    }

    private function canRenderAutolinkText(string $text): bool
    {
        return $text !== ''
            && preg_match('/[\s\x00-\x1F\x7F<>]/u', $text) !== 1;
    }

    private function isSafeAutolinkText(AstNode $node, string $text): bool
    {
        if (!$this->canRenderAutolinkText($text)) {
            return false;
        }

        $url = $this->linkUrl($node);
        if ($this->isMailtoUrl($url)) {
            return $this->isValidEmailAutolinkText($text);
        }

        return $this->isValidUriAutolinkText($url);
    }

    private function isValidUriAutolinkText(string $text): bool
    {
        if (preg_match('/\A([A-Za-z][A-Za-z0-9+.-]*):/', $text, $match) !== 1) {
            return false;
        }

        $schemeLength = strlen($match[1]);

        return $schemeLength >= 2 && $schemeLength <= 32;
    }

    private function isValidEmailAutolinkText(string $text): bool
    {
        return preg_match(
            '/\A[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@'
                . '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?'
                . '(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+\z/',
            $text
        ) === 1;
    }

    /**
     * @return list<AstNode>
     */
    private function imageLabelNodesForLink(AstNode $node): array
    {
        $labelNodes = $node->children;
        if ($labelNodes === []) {
            $alt = $this->imageAlt($node);
            if ($alt !== '') {
                $labelNodes = [new AstNode('text', ['text' => $alt])];
            }
        }

        $url = $this->linkUrl($node);
        if ($labelNodes === [] || (count($labelNodes) === 1 && $labelNodes[0]->type === 'text' && $this->nodeText($labelNodes[0]) === $url)) {
            return [new AstNode('text', ['text' => ''])];
        }

        return $labelNodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageLinkAttrs(AstNode $node): array
    {
        $attrs = [
            'url' => $this->linkUrl($node),
            'title' => $this->linkTitle($node),
        ];
        if ($node->attr('preserveAttributeValueBraces') === true) {
            $attrs['preserveAttributeValueBraces'] = true;
        }

        $linkAttrs = $this->linkAttrTuple($node);
        if ($linkAttrs['id'] !== '') {
            $attrs['id'] = $linkAttrs['id'];
        }
        if ($linkAttrs['classes'] !== []) {
            $attrs['classes'] = $linkAttrs['classes'];
        }
        if ($linkAttrs['attributes'] !== []) {
            $attrs['attributes'] = $linkAttrs['attributes'];
        }

        $alt = $this->imageAlt($node);
        if ($alt !== '') {
            $labelText = $this->plainInlineText($this->imageLabelNodesForLink($node));
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            if ($labelText !== '' && $labelText !== $alt && !array_key_exists('alt', $attributes)) {
                $attrs['attributes'] = ['alt' => $alt] + $attributes;
            }
        }

        return $attrs;
    }

    private function imageWithFigureAttrs(AstNode $figure, AstNode $image): AstNode
    {
        $attrs = $image->attrs;

        foreach (['id', 'classes'] as $name) {
            if (!array_key_exists($name, $attrs) && array_key_exists($name, $figure->attrs)) {
                $attrs[$name] = $figure->attrs[$name];
            }
        }

        $imageAttributes = $attrs['attributes'] ?? [];
        if (!is_array($imageAttributes)) {
            $imageAttributes = [];
        }

        $figureAttributes = $figure->attr('attributes', []);
        if (is_array($figureAttributes) && $figureAttributes !== []) {
            $attrs['attributes'] = $imageAttributes + $figureAttributes;
        } elseif ($imageAttributes !== []) {
            $attrs['attributes'] = $imageAttributes;
        }
        if (is_array($figure->attr('captionSource', null))) {
            $attrs['preserveAttributeValueBraces'] = true;
        }

        $caption = (string) $figure->attr('caption', '');
        $imageCaption = (string) $image->attr('caption', '');
        if (
            $caption !== ''
            && (
                $image->children === []
                || $figure->attr('renderCaptionInlines') === true
                || ($imageCaption !== '' && $caption !== $imageCaption)
            )
        ) {
            $captionInlines = $figure->attr('captionInlines', []);
            if ($figure->attr('renderCaptionInlines') === true && is_array($captionInlines) && $this->allAstNodes($captionInlines)) {
                return new AstNode('image', $attrs, array_values($captionInlines));
            }

            return new AstNode('image', $attrs, [new AstNode('text', ['text' => $caption])]);
        }

        return new AstNode('image', $attrs, $image->children);
    }

    private function isUriLike(string $url): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) === 1;
    }

    private function escapeUri(string $url): string
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9\\-._~:\\/?#\\[\\]@!$&\'()*+,;=%]/u',
            static fn (array $match): string => implode('', array_map(
                static fn (string $byte): string => sprintf('%%%02X', ord($byte)),
                str_split($match[0])
            )),
            $url
        ) ?? $url;
    }

    private function renderLinkDestination(string $url): string
    {
        $url = $this->escapeLinkDestinationControlCharacters($url);
        if (!$this->linkDestinationNeedsAngles($url)) {
            return $url;
        }

        return '<' . str_replace(
            ['\\', '<', '>', '"', "'"],
            ['\\\\', '\\<', '\\>', '\\"', "\\'"],
            $url
        ) . '>';
    }

    private function escapeLinkDestinationControlCharacters(string $url): string
    {
        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static fn (array $match): string => implode('', array_map(
                static fn (string $byte): string => sprintf('%%%02X', ord($byte)),
                str_split($match[0])
            )),
            $url
        ) ?? $url;
    }

    private function linkDestinationNeedsAngles(string $url): bool
    {
        return $url === ''
            || preg_match('/[\s\x00-\x1F\x7F<>()"\']/u', $url) === 1;
    }

    /**
     * @param list<string> $names
     */
    private function scalarAttr(AstNode $node, array $names, string $default = ''): string
    {
        foreach ($names as $name) {
            if (!array_key_exists($name, $node->attrs) || !is_scalar($node->attrs[$name])) {
                continue;
            }

            return (string) $node->attrs[$name];
        }

        return $default;
    }

    /**
     * @param list<string> $names
     */
    private function nodeText(AstNode $node, array $names = ['text', 'value', 'literal', 'content', 'string']): string
    {
        return $this->scalarAttr($node, $names);
    }

    private function rawFormat(AstNode $node): string
    {
        return $this->scalarAttr($node, ['format', 'rawFormat', 'formatName']);
    }

    /**
     * @param list<string> $names
     */
    private function rawText(AstNode $node, array $names): string
    {
        return $this->scalarAttr($node, $names);
    }

    private function linkUrl(AstNode $node): string
    {
        $url = $this->scalarAttr($node, ['url', 'href', 'src', 'uri']);
        if ($url !== '') {
            return $url;
        }

        return $this->targetUrl($node);
    }

    private function linkTitle(AstNode $node): string
    {
        $title = $this->scalarAttr($node, ['title', 'titleText', 'tooltip']);
        if ($title !== '') {
            return $title;
        }

        return $this->targetTitle($node);
    }

    private function targetUrl(AstNode $node): string
    {
        foreach (['target', 'destination'] as $name) {
            $value = $node->attr($name);
            if (is_scalar($value)) {
                return (string) $value;
            }
            if (is_array($value)) {
                $url = $this->targetArrayPart($value, 0, ['url', 'href', 'src', 'uri']);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    private function targetTitle(AstNode $node): string
    {
        foreach (['target', 'destination'] as $name) {
            $value = $node->attr($name);
            if (!is_array($value)) {
                continue;
            }

            $title = $this->targetArrayPart($value, 1, ['title', 'titleText', 'tooltip']);
            if ($title !== '') {
                return $title;
            }
        }

        return '';
    }

    /**
     * @param array<mixed> $value
     * @param list<string> $keys
     */
    private function targetArrayPart(array $value, int $index, array $keys): string
    {
        if (array_is_list($value) && isset($value[$index]) && is_scalar($value[$index])) {
            return (string) $value[$index];
        }

        foreach ($keys as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) {
                return (string) $value[$key];
            }
        }

        return '';
    }

    private function imageAlt(AstNode $node): string
    {
        return $this->scalarAttr($node, ['alt', 'altText', 'alternateText', 'description']);
    }

    private function citationId(AstNode $node): string
    {
        return $this->scalarAttr($node, ['id', 'citationId', 'identifier']);
    }

    private function citationMode(AstNode $node): string
    {
        $mode = $this->scalarAttr($node, ['mode']);
        if ($mode !== '') {
            return $this->normalizeCitationMode($mode);
        }

        return $this->normalizeCitationMode($this->scalarAttr($node, ['citationModeConstructor', 'citationMode']));
    }

    private function normalizeCitationMode(string $mode): string
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($mode)));

        return match ($normalized) {
            'authorintext', 'author_in_text' => 'author_in_text',
            'suppressauthor', 'suppress_author' => 'suppress_author',
            default => 'normal',
        };
    }

    private function citationAffixValue(AstNode $citation, string $name): mixed
    {
        $aliases = match ($name) {
            'prefix' => ['prefix', 'citationPrefix'],
            'suffix' => ['suffix', 'citationSuffix'],
            'locator' => ['locator', 'locatorText', 'citationLocator', 'citationLocatorText'],
            default => [$name],
        };

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $citation->attrs)) {
                return $citation->attrs[$alias];
            }
        }

        return '';
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function linkAttrTuple(AstNode $node): array
    {
        $htmlAttrs = $this->markdownHtmlAttributeTuple($node);
        $id = $htmlAttrs['id'];
        $explicitId = $this->normalizeAttributeIdentifierToken($this->scalarAttr($node, ['id', 'identifier', 'anchor']));
        if ($explicitId !== '') {
            $id = $explicitId;
        }

        $classes = $htmlAttrs['classes'];
        foreach (['classes', 'class', 'className'] as $name) {
            if (array_key_exists($name, $node->attrs)) {
                $this->appendNormalizedClasses($classes, $node->attrs[$name]);
            }
        }
        $classes = array_values(array_unique($classes));

        $attributes = $htmlAttrs['attributes'];
        foreach (['attributes', 'keyvals', 'keyValues', 'attributePairs', 'dataAttributes'] as $name) {
            if (array_key_exists($name, $node->attrs)) {
                $this->appendNormalizedAttributes($attributes, $node->attrs[$name]);
            }
        }

        $topLevelAttributeNames = ['dir', 'lang', 'role', 'xml:lang'];
        if (!in_array($node->type, ['link', 'image'], true)) {
            $topLevelAttributeNames[] = 'title';
        }

        foreach ($topLevelAttributeNames as $name) {
            if (!array_key_exists($name, $attributes) && is_scalar($node->attr($name))) {
                $value = trim((string) $node->attr($name));
                if ($value !== '') {
                    $attributes[$name] = $value;
                }
            }
        }
        $attributes = $this->removeRedundantDataAttributeAliases($attributes);

        return [
            'id' => $id,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $classes
     */
    private function appendNormalizedClasses(array &$classes, mixed $value): void
    {
        if (is_string($value)) {
            foreach (preg_split('/\s+/u', trim($value)) ?: [] as $class) {
                $class = $this->normalizeAttributeIdentifierToken($class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
            return;
        }

        if (!is_array($value)) {
            if (is_scalar($value)) {
                $class = $this->normalizeAttributeIdentifierToken((string) $value);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
            return;
        }

        foreach ($value as $class) {
            if (is_scalar($class)) {
                $class = $this->normalizeAttributeIdentifierToken((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendNormalizedAttributes(array &$attributes, mixed $value): void
    {
        if (!is_array($value)) {
            return;
        }

        if (!array_is_list($value)) {
            foreach ($value as $name => $item) {
                $this->appendNormalizedAttribute($attributes, (string) $name, $item);
            }
            return;
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item[0], $item[1])) {
                $this->appendNormalizedAttribute($attributes, (string) $item[0], $item[1]);
                continue;
            }

            if (isset($item['key'], $item['value'])) {
                $this->appendNormalizedAttribute($attributes, (string) $item['key'], $item['value']);
                continue;
            }

            if (isset($item['name'], $item['value'])) {
                $this->appendNormalizedAttribute($attributes, (string) $item['name'], $item['value']);
            }
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendNormalizedAttribute(array &$attributes, string $name, mixed $value): void
    {
        $name = $this->normalizeAttributeIdentifierToken($name);
        if ($name === '' || !is_scalar($value)) {
            return;
        }

        $attributes[$name] = (string) $value;
    }

    /**
     * @return list<string>
     */
    private function normalizedClassList(mixed $classes): array
    {
        $normalized = [];
        $this->appendNormalizedClasses($normalized, $classes);

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, string>
     */
    private function normalizedAttributePairs(mixed $attributes): array
    {
        $normalized = [];
        $this->appendNormalizedAttributes($normalized, $attributes);

        return array_filter($normalized, static fn (string $value): bool => $value !== '');
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function markdownHtmlAttributeTuple(AstNode $node): array
    {
        $tuple = [
            'id' => '',
            'classes' => [],
            'attributes' => [],
        ];

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            return $tuple;
        }

        foreach ($htmlAttributes as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $name = strtolower(trim((string) $name));
            $value = (string) $value;
            if ($name === '' || $value === '') {
                continue;
            }

            if ($name === 'id') {
                $tuple['id'] = $value;
                continue;
            }

            if ($name === 'class') {
                array_push(
                    $tuple['classes'],
                    ...preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []
                );
                continue;
            }

            if ($this->isStructuralHtmlAttributeForMarkdown($node, $name)) {
                continue;
            }

            $tuple['attributes'][$name] = $value;
        }
        $tuple['classes'] = array_values(array_unique($tuple['classes']));

        return $tuple;
    }

    private function isStructuralHtmlAttributeForMarkdown(AstNode $node, string $name): bool
    {
        if ($node->type === 'link' && ($name === 'href' || $name === 'src')) {
            return true;
        }

        if ($node->type === 'image' && $name === 'src') {
            return true;
        }

        return $name === 'title' && (string) $node->attr('title', '') !== '';
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    private function removeRedundantDataAttributeAliases(array $attributes): array
    {
        foreach ($attributes as $name => $value) {
            if (!str_starts_with($name, 'data-')) {
                continue;
            }

            $alias = substr($name, 5);
            if ($alias !== '' && isset($attributes[$alias]) && $attributes[$alias] === $value) {
                unset($attributes[$name]);
            }
        }

        return $attributes;
    }

    private function renderLinkAttributes(AstNode $node): string
    {
        return $this->renderAttributesTuple(
            $this->linkAttrTuple($node),
            $node->attr('preserveAttributeValueBraces') !== true
        );
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderAttributesTuple(array $attrs, bool $escapeValueBraces = true): string
    {
        $parts = [];
        if ($attrs['id'] !== '') {
            $parts[] = '#' . $this->escapeAttributeIdentifierToken($attrs['id']);
        }
        foreach ($attrs['classes'] as $class) {
            $parts[] = '.' . $this->escapeAttributeIdentifierToken($class);
        }
        foreach ($attrs['attributes'] as $name => $value) {
            $parts[] = $this->escapeAttributeIdentifierToken((string) $name)
                . '="'
                . $this->escapeAttributeValue($value, $escapeValueBraces)
                . '"';
        }

        return $parts === [] ? '' : '{' . implode(' ', $parts) . '}';
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function attributeSignature(array $attrs): string
    {
        $normalized = $attrs;
        if (isset($normalized['attributes']) && is_array($normalized['attributes'])) {
            ksort($normalized['attributes']);
        }

        return json_encode($normalized, JSON_THROW_ON_ERROR);
    }

    private function escapeAttributeIdentifierToken(string $value): string
    {
        return preg_replace_callback(
            '/[\\\\`"\'\s{}\[\]()=]/u',
            static fn (array $match): string => '\\' . $match[0],
            $this->normalizeAttributeIdentifierToken($value)
        ) ?? $value;
    }

    private function normalizeAttributeIdentifierToken(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? $value);
    }

    private function escapeAttributeValue(string $value, bool $escapeBraces = true): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? $value;

        $search = ['\\', '"'];
        $replace = ['\\\\', '\\"'];
        if ($escapeBraces) {
            $search[] = '{';
            $search[] = '}';
            $replace[] = '\\{';
            $replace[] = '\\}';
        }

        return str_replace($search, $replace, $value);
    }

    private function escapeLinkTitle(string $title): string
    {
        $title = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $title) ?? $title;

        return str_replace(['\\', '"'], ['\\\\', '\\"'], $title);
    }

    private function referenceLocation(): string
    {
        $location = (string) ($this->options['referenceLocation'] ?? 'end_of_document');

        return in_array($location, ['end_of_document', 'end_of_block', 'end_of_section'], true)
            ? $location
            : 'end_of_document';
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'emph',
            'strong',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'underline',
            'span',
            'quoted',
            'softbreak',
            'linebreak',
            'code',
            'link',
            'image',
            'math',
            'citation',
            'citation_group',
            'raw_tex',
            'raw_inline',
            'raw_markdown',
            'raw_html_inline',
            'note',
        ], true);
    }

    private function isListBlock(AstNode $node): bool
    {
        return $node->type === 'bullet_list' || $node->type === 'ordered_list' || $node->type === 'definition_list';
    }

    private function needsBlockSeparator(AstNode $previous, AstNode $current): bool
    {
        if (
            $this->rendersIndentedCodeBlock($previous)
            && $this->rendersIndentedCodeBlock($current)
        ) {
            return true;
        }

        if (!$this->isListBlock($previous)) {
            return false;
        }

        if ($current->type === 'code_block') {
            return true;
        }

        if ($previous->type === 'bullet_list' && $current->type === 'bullet_list') {
            return true;
        }

        if ($previous->type === 'definition_list' && $current->type === 'definition_list') {
            return true;
        }

        return $previous->type === 'ordered_list'
            && $current->type === 'ordered_list'
            && $this->orderedListMarkerStyle($previous) === $this->orderedListMarkerStyle($current);
    }

    private function rendersIndentedCodeBlock(AstNode $node): bool
    {
        return $node->type === 'code_block'
            && !(bool) ($this->options['fencedCodeBlocks'] ?? false)
            && $this->renderCodeBlockAttributes($node) === ''
            && $this->codeBlockInfo($node) === '';
    }

    private function orderedListSeparatorDelimiter(AstNode $node): string
    {
        $delimiter = $this->orderedListMarkerDelimiter($node);

        return $delimiter === 'default' ? 'period' : $delimiter;
    }

    private function orderedListMarkerStyle(AstNode $node): string
    {
        $style = $this->normalizeOrderedListEnum((string) $node->attr('style', 'decimal'));

        return match ($style) {
            'defaultstyle', 'default' => 'default',
            'decimal' => 'decimal',
            'example' => 'example',
            'lowerroman', 'lower_roman' => 'lower_roman',
            'upperroman', 'upper_roman' => 'upper_roman',
            'loweralpha', 'lower_alpha' => 'lower_alpha',
            'upperalpha', 'upper_alpha' => 'upper_alpha',
            default => 'decimal',
        };
    }

    private function orderedListMarkerDelimiter(AstNode $node): string
    {
        $delimiter = $this->normalizeOrderedListEnum((string) $node->attr('delimiter', 'period'));

        return match ($delimiter) {
            'defaultdelim', 'default' => 'default',
            'oneparen', 'one_paren' => 'one_paren',
            'twoparens', 'two_parens' => 'two_parens',
            'period' => 'period',
            default => 'period',
        };
    }

    private function normalizeOrderedListEnum(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return strtolower(str_replace(['-', ' '], '_', $value));
    }
}
