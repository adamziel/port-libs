<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class IpynbReader
{
    private const MAX_CELLS = 200;
    private const MAX_CELL_SOURCE_BYTES = 1048576;

    private readonly MarkdownReader $markdownReader;

    public function __construct(?MarkdownReader $markdownReader = null)
    {
        $this->markdownReader = $markdownReader ?? new MarkdownReader(['yamlMetadata' => false]);
    }

    public function read(string $json): AstNode
    {
        $notebook = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($notebook)) {
            throw new \InvalidArgumentException('IPYNB source must decode to a JSON object');
        }

        $cells = $notebook['cells'] ?? null;
        if (!is_array($cells)) {
            throw new \InvalidArgumentException('IPYNB notebook is missing a cells array');
        }
        if (count($cells) > self::MAX_CELLS) {
            throw new \InvalidArgumentException('IPYNB notebook exceeds the bounded native reader cell limit');
        }

        $metadata = isset($notebook['metadata']) && is_array($notebook['metadata']) ? $notebook['metadata'] : [];
        $language = $this->metadataString($metadata['language_info'] ?? null, 'name')
            ?? $this->metadataString($metadata['kernelspec'] ?? null, 'language')
            ?? '';

        $blocks = [];
        $cellSummaries = [];
        $markdownCellCount = 0;
        $codeCellCount = 0;
        $rawCellCount = 0;
        $attachmentCount = 0;
        $outputCount = 0;
        $unsupportedResourceCount = 0;
        $metadataKeys = $this->metadataKeys($metadata);
        $sourceShapeCounts = [
            'string' => 0,
            'list' => 0,
            'missing' => 0,
            'null' => 0,
        ];
        $sourceContentStateCounts = [
            'empty' => 0,
            'whitespace-only' => 0,
            'content' => 0,
        ];
        $sourceLineEndingCounts = [
            'lf' => 0,
            'crlf' => 0,
            'cr' => 0,
        ];
        $sourceFingerprintCounts = [];
        $sourceFingerprintIndexes = [];
        $totalSourceBytes = 0;
        $totalSourceLineCount = 0;
        $mixedLineEndingSourceCount = 0;
        $trailingLineEndingSourceCount = 0;

        foreach ($cells as $index => $cell) {
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$index} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $sourceReview = $this->sourceReview(
                array_key_exists('source', $cell) ? $cell['source'] : null,
                array_key_exists('source', $cell),
                "IPYNB cell {$index} source"
            );
            $source = $sourceReview['source'];
            $sourceSummary = $sourceReview['summary'];
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$index} exceeds the bounded native reader source limit");
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments);
            $outputSummary = $this->outputSummary($outputs);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['count'];
            $sourceShapeCounts[$sourceSummary['sourceShape']] = ($sourceShapeCounts[$sourceSummary['sourceShape']] ?? 0) + 1;
            $sourceContentStateCounts[$sourceSummary['sourceContentState']] = ($sourceContentStateCounts[$sourceSummary['sourceContentState']] ?? 0) + 1;
            foreach ($sourceSummary['sourceLineEndings'] as $lineEnding => $count) {
                $sourceLineEndingCounts[$lineEnding] = ($sourceLineEndingCounts[$lineEnding] ?? 0) + $count;
            }
            $sourceFingerprint = $sourceSummary['sourceFingerprint'];
            $sourceFingerprintCounts[$sourceFingerprint] = ($sourceFingerprintCounts[$sourceFingerprint] ?? 0) + 1;
            $sourceFingerprintIndexes[$sourceFingerprint][] = $index;
            $totalSourceBytes += $sourceSummary['sourceBytes'];
            $totalSourceLineCount += $sourceSummary['sourceLineCount'];
            if ($sourceSummary['sourceHasMixedLineEndings']) {
                $mixedLineEndingSourceCount++;
            }
            if ($sourceSummary['sourceHasTrailingLineEnding']) {
                $trailingLineEndingSourceCount++;
            }

            $attributes = [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => $cellType,
            ];
            if ($attachmentSummary['count'] > 0) {
                $attributes['data-ipynb-attachment-count'] = (string) $attachmentSummary['count'];
            }
            if ($outputSummary['count'] > 0) {
                $attributes['data-ipynb-output-count'] = (string) $outputSummary['count'];
            }
            if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
                $attributes['data-ipynb-execution-count'] = (string) $cell['execution_count'];
            }
            if ($cellTags !== []) {
                $attributes['data-ipynb-cell-tags'] = implode(' ', $cellTags);
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostics'] = implode(' ', $cellDiagnostics);
            }

            $children = match ($cellType) {
                'markdown' => $this->markdownCellBlocks($source),
                'code' => [$this->codeCellBlock($source, $language, $index, $cell)],
                'raw' => [$this->rawCellBlock($source, $index)],
                default => [$this->unsupportedCellBlock($source, $cellType, $index)],
            };

            if ($cellType === 'markdown') {
                $markdownCellCount++;
            } elseif ($cellType === 'code') {
                $codeCellCount++;
            } elseif ($cellType === 'raw') {
                $rawCellCount++;
            }

            $cellAttrs = [
                'classes' => ['ipynb-cell', 'ipynb-' . $cellType . '-cell'],
                'attributes' => $attributes,
                'ipynbCellType' => $cellType,
                'ipynbCellIndex' => $index,
                'ipynbAttachmentCount' => $attachmentSummary['count'],
                'ipynbAttachmentNames' => $attachmentSummary['names'],
                'ipynbAttachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputMimeTypes' => $outputSummary['mimeTypes'],
                'ipynbUnsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
                'ipynbUnsupportedResourceDiagnostics' => $cellDiagnostics,
                'ipynbCellMetadataKeys' => $cellMetadataKeys,
                'ipynbCellTags' => $cellTags,
            ];
            if (array_key_exists('id', $cell) && is_string($cell['id']) && $cell['id'] !== '') {
                $cellAttrs['ipynbCellId'] = $cell['id'];
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellAttrs['ipynbExecutionCount'] = $cell['execution_count'];
            }

            $blocks[] = new AstNode('div', $cellAttrs, $children);
            $cellSummaries[] = [
                'index' => $index,
                'type' => $cellType,
                'sourceShape' => $sourceSummary['sourceShape'],
                'sourcePartCount' => $sourceSummary['sourcePartCount'],
                'sourceBytes' => $sourceSummary['sourceBytes'],
                'sourceLineCount' => $sourceSummary['sourceLineCount'],
                'sourceLineEndingCount' => $sourceSummary['sourceLineEndingCount'],
                'sourceLineEndings' => $sourceSummary['sourceLineEndings'],
                'sourceHasTrailingLineEnding' => $sourceSummary['sourceHasTrailingLineEnding'],
                'sourceHasMixedLineEndings' => $sourceSummary['sourceHasMixedLineEndings'],
                'sourceContentState' => $sourceSummary['sourceContentState'],
                'sourceDigest' => $sourceSummary['sourceDigest'],
                'sourceFingerprint' => $sourceFingerprint,
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
                'diagnostics' => $cellDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
            ];
        }

        ksort($sourceFingerprintCounts);
        foreach ($cellSummaries as &$cellSummary) {
            $cellSummary['sourceFingerprintCount'] = $sourceFingerprintCounts[$cellSummary['sourceFingerprint']] ?? 1;
        }
        unset($cellSummary);

        $duplicateSourceFingerprints = [];
        $duplicateSourceCellCount = 0;
        foreach ($sourceFingerprintCounts as $sourceFingerprint => $count) {
            if ($count <= 1) {
                continue;
            }
            $duplicateSourceCellCount += $count;
            $duplicateSourceFingerprints[] = [
                'sourceFingerprint' => $sourceFingerprint,
                'count' => $count,
                'cellIndexes' => $sourceFingerprintIndexes[$sourceFingerprint] ?? [],
            ];
        }

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookAttachmentCount' => $attachmentCount,
            'notebookOutputCount' => $outputCount,
            'notebookUnsupportedResourceCount' => $unsupportedResourceCount,
            'notebookNbformat' => $notebook['nbformat'] ?? null,
            'notebookNbformatMinor' => $notebook['nbformat_minor'] ?? null,
            'notebookMetadataKeys' => $metadataKeys,
            'notebookKernelName' => $this->metadataString($metadata['kernelspec'] ?? null, 'name'),
            'notebookLanguage' => $language,
            'notebookCells' => $cellSummaries,
            'notebookResourcePolicy' => [
                'state' => $unsupportedResourceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $unsupportedResourceCount > 0 ? ['external-notebook-resource-bytes-blocked'] : [],
            ],
            'notebookSourceSummary' => [
                'cellCount' => count($cells),
                'totalSourceBytes' => $totalSourceBytes,
                'totalSourceLineCount' => $totalSourceLineCount,
                'sourceShapeCounts' => $sourceShapeCounts,
                'sourceLineEndingCounts' => $sourceLineEndingCounts,
                'emptySourceCount' => $sourceContentStateCounts['empty'],
                'whitespaceOnlySourceCount' => $sourceContentStateCounts['whitespace-only'],
                'contentSourceCount' => $sourceContentStateCounts['content'],
                'mixedLineEndingSourceCount' => $mixedLineEndingSourceCount,
                'trailingLineEndingSourceCount' => $trailingLineEndingSourceCount,
                'uniqueSourceFingerprintCount' => count($sourceFingerprintCounts),
                'duplicateSourceFingerprintCount' => count($duplicateSourceFingerprints),
                'duplicateSourceCellCount' => $duplicateSourceCellCount,
            ],
            'notebookSourceFingerprintCounts' => $sourceFingerprintCounts,
            'notebookDuplicateSourceFingerprints' => $duplicateSourceFingerprints,
        ], $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function markdownCellBlocks(string $source): array
    {
        if (trim($source) === '') {
            return [];
        }

        return $this->markdownReader->read($source)->children;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function codeCellBlock(string $source, string $language, int $index, array $cell): AstNode
    {
        $classes = ['ipynb-code-cell-source'];
        if ($language !== '') {
            array_unshift($classes, $this->sanitizeClassToken($language));
        }

        $attrs = [
            'classes' => array_values(array_filter($classes, static fn (string $class): bool => $class !== '')),
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => 'code',
            ],
            'text' => $source,
        ];
        if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
            $attrs['attributes']['data-ipynb-execution-count'] = (string) $cell['execution_count'];
        }

        return new AstNode('code_block', $attrs);
    }

    private function rawCellBlock(string $source, int $index): AstNode
    {
        return new AstNode('code_block', [
            'classes' => ['ipynb-raw-cell-source'],
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => 'raw',
            ],
            'text' => $source,
        ]);
    }

    private function unsupportedCellBlock(string $source, string $cellType, int $index): AstNode
    {
        return new AstNode('code_block', [
            'classes' => ['ipynb-unsupported-cell-source'],
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => $cellType,
            ],
            'text' => $source,
        ]);
    }

    private function cellType(mixed $cellType): string
    {
        if (!is_string($cellType) || $cellType === '') {
            return 'unknown';
        }

        $normalized = $this->sanitizeClassToken(strtolower($cellType));

        return $normalized === '' ? 'unknown' : $normalized;
    }

    /**
     * @return array{source:string, summary:array<string, mixed>}
     */
    private function sourceReview(mixed $source, bool $sourcePresent, string $label): array
    {
        $sourceShape = 'missing';
        $sourcePartCount = 0;
        $normalized = '';

        if (!$sourcePresent) {
            $sourceShape = 'missing';
        } elseif ($source === null) {
            $sourceShape = 'null';
        } elseif (is_string($source)) {
            $sourceShape = 'string';
            $sourcePartCount = 1;
            $normalized = $source;
        } elseif (is_array($source)) {
            $sourceShape = 'list';
            $sourcePartCount = count($source);
            $parts = [];
            foreach ($source as $index => $line) {
                if (!is_string($line)) {
                    throw new \InvalidArgumentException("{$label} entry {$index} is not a string");
                }
                $parts[] = $line;
            }
            $normalized = implode('', $parts);
        } else {
            throw new \InvalidArgumentException("{$label} must be a string, string array, null, or missing");
        }

        $sourceLineEndings = $this->sourceLineEndingCounts($normalized);
        $sourceLineEndingCount = array_sum($sourceLineEndings);
        $sourceHasTrailingLineEnding = $this->sourceHasTrailingLineEnding($normalized);
        $sourceDigest = hash('sha256', $normalized);

        return [
            'source' => $normalized,
            'summary' => [
                'sourceShape' => $sourceShape,
                'sourcePartCount' => $sourcePartCount,
                'sourceBytes' => strlen($normalized),
                'sourceLineCount' => $this->sourceLineCount($normalized, $sourceLineEndingCount, $sourceHasTrailingLineEnding),
                'sourceLineEndingCount' => $sourceLineEndingCount,
                'sourceLineEndings' => $sourceLineEndings,
                'sourceHasTrailingLineEnding' => $sourceHasTrailingLineEnding,
                'sourceHasMixedLineEndings' => count(array_filter($sourceLineEndings, static fn (int $count): bool => $count > 0)) > 1,
                'sourceContentState' => $this->sourceContentState($normalized),
                'sourceDigest' => [
                    'algorithm' => 'sha256',
                    'value' => $sourceDigest,
                ],
                'sourceFingerprint' => 'sha256:' . $sourceDigest,
            ],
        ];
    }

    /**
     * @return array{lf:int, crlf:int, cr:int}
     */
    private function sourceLineEndingCounts(string $source): array
    {
        $counts = [
            'lf' => 0,
            'crlf' => 0,
            'cr' => 0,
        ];
        if ($source === '' || preg_match_all('/\r\n|\r|\n/', $source, $matches) === false) {
            return $counts;
        }

        foreach ($matches[0] as $lineEnding) {
            if ($lineEnding === "\r\n") {
                $counts['crlf']++;
            } elseif ($lineEnding === "\r") {
                $counts['cr']++;
            } else {
                $counts['lf']++;
            }
        }

        return $counts;
    }

    private function sourceHasTrailingLineEnding(string $source): bool
    {
        return $source !== '' && preg_match('/(?:\r\n|\r|\n)\z/', $source) === 1;
    }

    private function sourceLineCount(string $source, int $lineEndingCount, bool $sourceHasTrailingLineEnding): int
    {
        if ($source === '') {
            return 0;
        }

        return $lineEndingCount + ($sourceHasTrailingLineEnding ? 0 : 1);
    }

    private function sourceContentState(string $source): string
    {
        if ($source === '') {
            return 'empty';
        }

        return trim($source) === '' ? 'whitespace-only' : 'content';
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array{count:int, names:list<string>, mimeTypes:list<string>}
     */
    private function attachmentSummary(array $attachments): array
    {
        $names = [];
        $mimeTypes = [];
        foreach ($attachments as $name => $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $names[] = (string) $name;
            foreach ($payload as $mimeType => $data) {
                if (!is_string($mimeType) || $mimeType === '' || !is_scalar($data)) {
                    continue;
                }
                $mimeTypes[] = $mimeType;
            }
        }
        sort($names);
        sort($mimeTypes);

        return [
            'count' => count($names),
            'names' => $names,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
        ];
    }

    /**
     * @param array<int, mixed> $outputs
     * @return array{count:int, types:list<string>, mimeTypes:list<string>}
     */
    private function outputSummary(array $outputs): array
    {
        $types = [];
        $mimeTypes = [];
        foreach ($outputs as $output) {
            if (!is_array($output)) {
                continue;
            }
            $type = $output['output_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $types[] = $type;
            }
            $data = $output['data'] ?? null;
            if (is_array($data)) {
                foreach ($data as $mimeType => $payload) {
                    if (!is_string($mimeType) || $mimeType === '' || !(is_scalar($payload) || is_array($payload))) {
                        continue;
                    }
                    $mimeTypes[] = $mimeType;
                }
            }
        }
        sort($mimeTypes);

        return [
            'count' => count($outputs),
            'types' => array_values(array_unique($types)),
            'mimeTypes' => array_values(array_unique($mimeTypes)),
        ];
    }

    /**
     * @param array{count:int, names:list<string>, mimeTypes:list<string>} $attachmentSummary
     * @param array{count:int, types:list<string>, mimeTypes:list<string>} $outputSummary
     * @return list<string>
     */
    private function cellDiagnostics(array $attachmentSummary, array $outputSummary): array
    {
        $diagnostics = [];
        if ($attachmentSummary['count'] > 0) {
            $diagnostics[] = 'attachment-bytes-blocked';
        }
        if ($outputSummary['count'] > 0) {
            $diagnostics[] = 'output-bytes-blocked';
        }
        if ($outputSummary['mimeTypes'] !== []) {
            $diagnostics[] = 'output-mime-bundle-metadata-only';
        }

        return $diagnostics;
    }

    /**
     * @param mixed $metadata
     */
    private function metadataString(mixed $metadata, string $key): ?string
    {
        if (!is_array($metadata)) {
            return null;
        }

        $value = $metadata[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function metadataKeys(array $metadata): array
    {
        $keys = [];
        foreach ($metadata as $key => $_value) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function metadataStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }
        sort($strings);

        return array_values(array_unique($strings));
    }

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
