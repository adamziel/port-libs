<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class IpynbReader
{
    private const MAX_CELLS = 200;
    private const MAX_CELL_SOURCE_BYTES = 1048576;
    private const MAX_EXECUTION_COUNT = 2147483647;

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
        $nbformat = $notebook['nbformat'] ?? null;
        $nbformatMinor = $notebook['nbformat_minor'] ?? null;
        $cellIdsRequired = $this->cellIdsRequired($nbformat, $nbformatMinor);
        $language = $this->metadataString($metadata['language_info'] ?? null, 'name')
            ?? $this->metadataString($metadata['kernelspec'] ?? null, 'language')
            ?? '';
        $metadataKeys = $this->metadataKeys($metadata);

        $blocks = [];
        $cellSummaries = [];
        $diagnostics = [];
        $outputAggregateDiagnostics = [];
        $markdownCellCount = 0;
        $codeCellCount = 0;
        $rawCellCount = 0;
        $attachmentCount = 0;
        $outputCount = 0;
        $unsupportedResourceCount = 0;
        $outputBytePresenceCount = 0;
        $outputMimeBundleCount = 0;
        $outputRepeatedMimeBundleKeyCount = 0;
        $cellExecutionCountPresentCount = 0;
        $cellExecutionCountValidCount = 0;
        $outputExecutionCountRecordCount = 0;
        $outputExecutionCountMismatchCount = 0;

        foreach ($cells as $cell) {
            $cellIndex = count($cellSummaries);
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $source = $this->normalizeSource($cell['source'] ?? '', "IPYNB cell {$cellIndex} source");
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} exceeds the bounded native reader source limit");
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments);
            $cellId = $this->cellIdValue($cell);
            $cellIdDiagnostics = $this->cellIdDiagnostics($cell, $cellType, $cellIndex, $cellIdsRequired);
            $executionSummary = $this->executionCountSummary($cell, $cellType, $cellIndex, $cellId);
            $outputSummary = $this->outputSummary($outputs, $executionSummary['validInteger'], $cellType, $cellIndex, $cellId);
            $cellDiagnostics = array_merge($cellIdDiagnostics, $executionSummary['diagnostics'], $outputSummary['diagnostics']);
            $resourceDiagnostics = $this->cellResourceDiagnostics($attachmentSummary, $outputSummary);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['bytePresenceCount'];
            $outputBytePresenceCount += $outputSummary['bytePresenceCount'];
            $outputMimeBundleCount += $outputSummary['mimeBundleCount'];
            $outputRepeatedMimeBundleKeyCount += count($outputSummary['repeatedMimeBundleKeys']);
            $outputExecutionCountRecordCount += $outputSummary['executionCountRecordCount'];
            $outputExecutionCountMismatchCount += $outputSummary['executionCountMismatchCount'];
            if ($executionSummary['present']) {
                $cellExecutionCountPresentCount++;
            }
            if ($executionSummary['valid']) {
                $cellExecutionCountValidCount++;
            }
            foreach ($cellDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            foreach ($outputSummary['aggregateDiagnostics'] as $diagnostic) {
                $outputAggregateDiagnostics[] = $diagnostic;
            }

            $attributes = [
                'data-ipynb-cell-index' => (string) $cellIndex,
                'data-ipynb-cell-type' => $cellType,
            ];
            if ($cellId !== null) {
                $attributes['data-ipynb-cell-id'] = $cellId;
            }
            if ($attachmentSummary['count'] > 0) {
                $attributes['data-ipynb-attachment-count'] = (string) $attachmentSummary['count'];
            }
            if ($outputSummary['count'] > 0) {
                $attributes['data-ipynb-output-count'] = (string) $outputSummary['count'];
                $attributes['data-ipynb-output-indexes'] = implode(' ', array_map(static fn (int $index): string => (string) $index, $outputSummary['indexes']));
                $attributes['data-ipynb-output-display-order'] = implode(' ', $outputSummary['orderTypes']);
            }
            if ($outputSummary['mimeTypes'] !== []) {
                $attributes['data-ipynb-output-mime-types'] = implode(' ', $outputSummary['mimeTypes']);
            }
            if ($outputSummary['repeatedMimeBundleKeys'] !== []) {
                $attributes['data-ipynb-output-repeated-mime-keys'] = implode(' ', $outputSummary['repeatedMimeBundleKeys']);
            }
            if ($outputSummary['bytePresenceCount'] > 0) {
                $attributes['data-ipynb-output-byte-policy'] = 'metadata-only';
                $attributes['data-ipynb-output-byte-presence-count'] = (string) $outputSummary['bytePresenceCount'];
            }
            if ($outputSummary['executionCountMismatchCount'] > 0) {
                $attributes['data-ipynb-output-execution-count-mismatch-count'] = (string) $outputSummary['executionCountMismatchCount'];
            }
            if ($outputSummary['executionCountValues'] !== []) {
                $attributes['data-ipynb-output-execution-counts'] = implode(' ', array_map(static fn (int $count): string => (string) $count, $outputSummary['executionCountValues']));
            }
            if ($outputSummary['errorNames'] !== []) {
                $attributes['data-ipynb-output-error-names'] = implode(' ', $outputSummary['errorNames']);
            }
            if ($outputSummary['streamNames'] !== []) {
                $attributes['data-ipynb-output-stream-names'] = implode(' ', $outputSummary['streamNames']);
            }
            if ($outputSummary['aggregateDiagnostics'] !== []) {
                $attributes['data-ipynb-output-aggregate-diagnostic-count'] = (string) count($outputSummary['aggregateDiagnostics']);
            }
            if ($resourceDiagnostics !== []) {
                $attributes['data-ipynb-diagnostics'] = implode(' ', $resourceDiagnostics);
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostic-count'] = (string) count($cellDiagnostics);
            }
            if ($cellTags !== []) {
                $attributes['data-ipynb-cell-tags'] = implode(' ', $cellTags);
            }
            if ($executionSummary['validInteger'] !== null) {
                $attributes['data-ipynb-execution-count'] = (string) $executionSummary['validInteger'];
            }

            $children = match ($cellType) {
                'markdown' => $this->markdownCellBlocks($source),
                'code' => [$this->codeCellBlock($source, $language, $cellIndex, $executionSummary['validInteger'])],
                'raw' => [$this->rawCellBlock($source, $cellIndex)],
                default => [$this->unsupportedCellBlock($source, $cellType, $cellIndex)],
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
                'ipynbCellIndex' => $cellIndex,
                'ipynbAttachmentCount' => $attachmentSummary['count'],
                'ipynbAttachmentNames' => $attachmentSummary['names'],
                'ipynbAttachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputOrderTypes' => $outputSummary['orderTypes'],
                'ipynbOutputIndexes' => $outputSummary['indexes'],
                'ipynbOutputMimeTypes' => $outputSummary['mimeTypes'],
                'ipynbOutputSummaries' => $outputSummary['outputs'],
                'ipynbOutputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'ipynbOutputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'ipynbOutputRepeatedMimeBundleKeys' => $outputSummary['repeatedMimeBundleKeys'],
                'ipynbOutputRepeatedMimeBundleRecords' => $outputSummary['repeatedMimeBundleRecords'],
                'ipynbOutputAggregateDiagnostics' => $outputSummary['aggregateDiagnostics'],
                'ipynbOutputExecutionCounts' => $outputSummary['executionCounts'],
                'ipynbOutputExecutionCountValues' => $outputSummary['executionCountValues'],
                'ipynbOutputExecutionCountRecordCount' => $outputSummary['executionCountRecordCount'],
                'ipynbOutputExecutionCountMismatchCount' => $outputSummary['executionCountMismatchCount'],
                'ipynbOutputErrorNames' => $outputSummary['errorNames'],
                'ipynbOutputStreamNames' => $outputSummary['streamNames'],
                'ipynbUnsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
                'ipynbUnsupportedResourceDiagnostics' => $resourceDiagnostics,
                'ipynbCellMetadataKeys' => $cellMetadataKeys,
                'ipynbCellTags' => $cellTags,
                'ipynbExecutionCountPresent' => $executionSummary['present'],
                'ipynbExecutionCountValid' => $executionSummary['valid'],
                'ipynbExecutionCountType' => $executionSummary['type'],
                'ipynbDiagnostics' => $cellDiagnostics,
            ];
            if ($cellId !== null) {
                $cellAttrs['ipynbCellId'] = $cellId;
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellAttrs['ipynbExecutionCount'] = $cell['execution_count'];
            }

            $blocks[] = new AstNode('div', $cellAttrs, $children);
            $cellSummaries[] = [
                'index' => $cellIndex,
                'type' => $cellType,
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputOrderTypes' => $outputSummary['orderTypes'],
                'outputIndexes' => $outputSummary['indexes'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'outputSummaries' => $outputSummary['outputs'],
                'outputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'outputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'outputRepeatedMimeBundleKeys' => $outputSummary['repeatedMimeBundleKeys'],
                'outputAggregateDiagnosticCount' => count($outputSummary['aggregateDiagnostics']),
                'outputAggregateDiagnostics' => $outputSummary['aggregateDiagnostics'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
                'executionCountPresent' => $executionSummary['present'],
                'executionCountValid' => $executionSummary['valid'],
                'diagnosticCount' => count($cellDiagnostics),
                'diagnostics' => $cellDiagnostics,
                'resourceDiagnostics' => $resourceDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
            ];
            if ($cellId !== null) {
                $cellSummaries[array_key_last($cellSummaries)]['id'] = $cellId;
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellSummaries[array_key_last($cellSummaries)]['executionCount'] = $cell['execution_count'];
            }
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
            'notebookOutputBytePresenceCount' => $outputBytePresenceCount,
            'notebookOutputMimeBundleCount' => $outputMimeBundleCount,
            'notebookOutputRepeatedMimeBundleKeyCount' => $outputRepeatedMimeBundleKeyCount,
            'notebookOutputAggregateDiagnosticCount' => count($outputAggregateDiagnostics),
            'notebookOutputAggregateDiagnostics' => $outputAggregateDiagnostics,
            'notebookCellIdsRequired' => $cellIdsRequired,
            'notebookCellExecutionCountPresentCount' => $cellExecutionCountPresentCount,
            'notebookCellExecutionCountValidCount' => $cellExecutionCountValidCount,
            'notebookOutputExecutionCountRecordCount' => $outputExecutionCountRecordCount,
            'notebookOutputExecutionCountMismatchCount' => $outputExecutionCountMismatchCount,
            'notebookDiagnosticCount' => count($diagnostics),
            'notebookDiagnostics' => $diagnostics,
            'notebookNbformat' => $nbformat,
            'notebookNbformatMinor' => $nbformatMinor,
            'notebookMetadataKeys' => $metadataKeys,
            'notebookKernelName' => $this->metadataString($metadata['kernelspec'] ?? null, 'name'),
            'notebookLanguage' => $language,
            'notebookCells' => $cellSummaries,
            'notebookResourcePolicy' => [
                'state' => $unsupportedResourceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $unsupportedResourceCount > 0 ? ['external-notebook-resource-bytes-blocked'] : [],
            ],
            'notebookOutputBytePolicy' => [
                'state' => $outputBytePresenceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $outputBytePresenceCount > 0 ? ['ipynb-rich-output-bytes-blocked'] : [],
            ],
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

    private function codeCellBlock(string $source, string $language, int $index, ?int $executionCount): AstNode
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
        if ($executionCount !== null) {
            $attrs['attributes']['data-ipynb-execution-count'] = (string) $executionCount;
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

    private function cellIdsRequired(mixed $nbformat, mixed $nbformatMinor): bool
    {
        if (!is_int($nbformat)) {
            return false;
        }
        if ($nbformat > 4) {
            return true;
        }

        return $nbformat === 4 && is_int($nbformatMinor) && $nbformatMinor >= 5;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function cellIdValue(array $cell): ?string
    {
        $id = $cell['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $cell
     * @return list<array<string, mixed>>
     */
    private function cellIdDiagnostics(array $cell, string $cellType, int $index, bool $required): array
    {
        if (!$required) {
            return [];
        }

        if (!array_key_exists('id', $cell)) {
            return [$this->diagnostic('missing-cell-id', $index, $cellType, null)];
        }

        $id = $cell['id'];
        if (!is_string($id) || $id === '') {
            return [$this->diagnostic('invalid-cell-id', $index, $cellType, null, [
                'valueType' => $this->valueKind($id),
            ])];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $cell
     * @return array{present:bool, valid:bool, validInteger:int|null, type:string, diagnostics:list<array<string, mixed>>}
     */
    private function executionCountSummary(array $cell, string $cellType, int $index, ?string $cellId): array
    {
        $present = array_key_exists('execution_count', $cell);
        $diagnostics = [];
        $type = 'missing';
        $valid = false;
        $validInteger = null;

        if (!$present) {
            if ($cellType === 'code') {
                $diagnostics[] = $this->diagnostic('missing-cell-execution-count', $index, $cellType, $cellId);
            }

            return [
                'present' => false,
                'valid' => false,
                'validInteger' => null,
                'type' => $type,
                'diagnostics' => $diagnostics,
            ];
        }

        $value = $cell['execution_count'];
        if ($value === null) {
            $type = 'null';
            $valid = true;
        } elseif (is_int($value)) {
            $type = 'integer';
            if ($value < 0 || $value > self::MAX_EXECUTION_COUNT) {
                $diagnostics[] = $this->diagnostic('cell-execution-count-out-of-range', $index, $cellType, $cellId, [
                    'value' => $value,
                    'min' => 0,
                    'max' => self::MAX_EXECUTION_COUNT,
                ]);
            } else {
                $valid = true;
                $validInteger = $value;
            }
        } else {
            $type = $this->valueKind($value);
            $diagnostics[] = $this->diagnostic('cell-execution-count-invalid-type', $index, $cellType, $cellId, [
                'valueType' => $type,
            ]);
        }

        if ($cellType !== 'code') {
            $diagnostics[] = $this->diagnostic('unexpected-cell-execution-count', $index, $cellType, $cellId, [
                'valueType' => $type,
            ]);
        }

        return [
            'present' => true,
            'valid' => $valid,
            'validInteger' => $validInteger,
            'type' => $type,
            'diagnostics' => $diagnostics,
        ];
    }

    private function normalizeSource(mixed $source, string $label): string
    {
        if (is_string($source)) {
            return $source;
        }

        if (is_array($source)) {
            $parts = [];
            foreach ($source as $index => $line) {
                if (!is_string($line)) {
                    throw new \InvalidArgumentException("{$label} entry {$index} is not a string");
                }
                $parts[] = $line;
            }

            return implode('', $parts);
        }

        throw new \InvalidArgumentException("{$label} must be a string or string array");
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
                if (!is_string($mimeType) || $mimeType === '' || !(is_scalar($data) || is_array($data))) {
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
     * @return array<string, mixed>
     */
    private function outputSummary(array $outputs, ?int $cellExecutionCount, string $cellType, int $cellIndex, ?string $cellId): array
    {
        $types = [];
        $orderTypes = [];
        $indexes = [];
        $mimeTypes = [];
        $mimeOccurrences = [];
        $outputRows = [];
        $mimeBundleCount = 0;
        $bytePresenceCount = 0;
        $executionCounts = [];
        $executionCountValues = [];
        $errorNames = [];
        $streamNames = [];
        $diagnostics = [];
        $policyDiagnostics = [];
        $aggregateDiagnostics = [];
        $mismatchCount = 0;

        foreach ($outputs as $output) {
            if (!is_array($output)) {
                continue;
            }
            $outputIndex = count($outputRows);
            $type = $output['output_type'] ?? null;
            $type = is_string($type) && $type !== '' ? $type : 'unknown';
            $types[] = $type;
            $orderTypes[] = $type;
            $indexes[] = $outputIndex;

            $dataMimeTypes = [];
            $data = $output['data'] ?? null;
            if (is_array($data)) {
                $dataMimeTypes = $this->mimeTypesFromBundle($data);
            }
            foreach ($dataMimeTypes as $mimeType) {
                $mimeTypes[] = $mimeType;
                $mimeOccurrences[$mimeType] ??= [];
                $mimeOccurrences[$mimeType][] = $outputIndex;
            }

            $outputPolicyDiagnostics = [];
            $hasMimeBundle = $dataMimeTypes !== [];
            $hasStreamPayload = $type === 'stream' && $this->hasStringOrStringList($output['text'] ?? null);
            $errorName = $this->nonEmptyString($output['ename'] ?? null);
            $hasErrorValue = $this->nonEmptyString($output['evalue'] ?? null) !== null;
            $tracebackLines = $this->stringList($output['traceback'] ?? null);
            $hasErrorPayload = $type === 'error' && ($errorName !== null || $hasErrorValue || $tracebackLines !== []);
            $hasBytePresence = $hasMimeBundle || $hasStreamPayload || $hasErrorPayload;

            if ($hasBytePresence) {
                $bytePresenceCount++;
                $outputPolicyDiagnostics[] = 'output-bytes-blocked';
            }
            if ($hasMimeBundle) {
                $mimeBundleCount++;
                $outputPolicyDiagnostics[] = 'output-mime-bundle-metadata-only';
            }
            if ($hasStreamPayload) {
                $outputPolicyDiagnostics[] = 'output-stream-bytes-blocked';
            }
            if ($type === 'error') {
                $outputPolicyDiagnostics[] = 'output-error-metadata-only';
                if ($tracebackLines !== []) {
                    $outputPolicyDiagnostics[] = 'output-error-traceback-bytes-blocked';
                }
            }
            foreach ($outputPolicyDiagnostics as $diagnostic) {
                $policyDiagnostics[] = $diagnostic;
            }

            $row = [
                'index' => $outputIndex,
                'type' => $type,
                'mimeTypes' => $dataMimeTypes,
                'metadataKeys' => isset($output['metadata']) && is_array($output['metadata']) ? $this->metadataKeys($output['metadata']) : [],
                'bytePresence' => $hasBytePresence ? 'present' : 'none',
                'byteExposure' => $hasBytePresence ? 'blocked' : 'none',
                'diagnostics' => array_values(array_unique($outputPolicyDiagnostics)),
            ];

            if (array_key_exists('execution_count', $output)) {
                $value = $output['execution_count'];
                $record = [
                    'outputIndex' => $outputIndex,
                    'outputType' => $type,
                    'valueType' => $this->valueKind($value),
                    'valid' => false,
                    'matchesCell' => null,
                ];

                if (is_int($value)) {
                    $record['executionCount'] = $value;
                    if ($value < 0 || $value > self::MAX_EXECUTION_COUNT) {
                        $diagnostics[] = $this->diagnostic('output-execution-count-out-of-range', $cellIndex, $cellType, $cellId, [
                            'outputIndex' => $outputIndex,
                            'outputType' => $type,
                            'value' => $value,
                            'min' => 0,
                            'max' => self::MAX_EXECUTION_COUNT,
                        ]);
                    } else {
                        $record['valid'] = true;
                        $executionCountValues[] = $value;
                        if ($cellExecutionCount !== null) {
                            $record['matchesCell'] = $value === $cellExecutionCount;
                            if ($value !== $cellExecutionCount) {
                                $mismatchCount++;
                                $diagnostics[] = $this->diagnostic('output-execution-count-mismatch', $cellIndex, $cellType, $cellId, [
                                    'outputIndex' => $outputIndex,
                                    'outputType' => $type,
                                    'cellExecutionCount' => $cellExecutionCount,
                                    'outputExecutionCount' => $value,
                                ]);
                            }
                        }
                    }
                } else {
                    $diagnostics[] = $this->diagnostic('output-execution-count-invalid-type', $cellIndex, $cellType, $cellId, [
                        'outputIndex' => $outputIndex,
                        'outputType' => $type,
                        'valueType' => $record['valueType'],
                    ]);
                }

                $executionCounts[] = $record;
                $row['executionCountRecord'] = $record;
                if (array_key_exists('executionCount', $record)) {
                    $row['executionCount'] = $record['executionCount'];
                }
            } elseif ($type === 'execute_result') {
                $diagnostics[] = $this->diagnostic('output-execution-count-missing', $cellIndex, $cellType, $cellId, [
                    'outputIndex' => $outputIndex,
                    'outputType' => $type,
                ]);
            }

            $streamName = $this->nonEmptyString($output['name'] ?? null);
            if ($type === 'stream' && $streamName !== null) {
                $row['streamName'] = $streamName;
                $streamNames[] = $streamName;
            }

            if ($type === 'error') {
                if ($errorName !== null) {
                    $row['errorName'] = $errorName;
                    $errorNames[] = $errorName;
                }
                $row['errorValuePresent'] = $hasErrorValue;
                $row['tracebackLineCount'] = count($tracebackLines);
            }

            $outputRows[] = $row;
        }

        $uniqueOrderTypes = array_values(array_unique($orderTypes));
        if (count($orderTypes) > 1 && count($uniqueOrderTypes) > 1) {
            $aggregateDiagnostics[] = $this->diagnostic('mixed-output-display-order', $cellIndex, $cellType, $cellId, [
                'outputIndexes' => $indexes,
                'outputTypes' => $orderTypes,
                'uniqueOutputTypes' => $uniqueOrderTypes,
            ]);
        }

        $repeatedMimeBundleRecords = $this->repeatedMimeBundleRecords($mimeOccurrences);
        foreach ($repeatedMimeBundleRecords as $record) {
            $aggregateDiagnostics[] = $this->diagnostic('repeated-output-mime-bundle-key', $cellIndex, $cellType, $cellId, [
                'mimeType' => $record['mimeType'],
                'outputIndexes' => $record['outputIndexes'],
                'occurrenceCount' => $record['count'],
            ]);
        }
        sort($mimeTypes);

        return [
            'count' => count($outputs),
            'types' => array_values(array_unique($types)),
            'orderTypes' => $orderTypes,
            'indexes' => $indexes,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'outputs' => $outputRows,
            'mimeBundleCount' => $mimeBundleCount,
            'bytePresenceCount' => $bytePresenceCount,
            'repeatedMimeBundleKeys' => array_column($repeatedMimeBundleRecords, 'mimeType'),
            'repeatedMimeBundleRecords' => $repeatedMimeBundleRecords,
            'executionCounts' => $executionCounts,
            'executionCountValues' => array_values(array_unique($executionCountValues)),
            'executionCountRecordCount' => count($executionCounts),
            'executionCountMismatchCount' => $mismatchCount,
            'errorNames' => $this->sortedUniqueStrings($errorNames),
            'streamNames' => $this->sortedUniqueStrings($streamNames),
            'diagnostics' => $diagnostics,
            'aggregateDiagnostics' => $aggregateDiagnostics,
            'policyDiagnostics' => array_values(array_unique($policyDiagnostics)),
        ];
    }

    /**
     * @param array{count:int, names:list<string>, mimeTypes:list<string>} $attachmentSummary
     * @param array<string, mixed> $outputSummary
     * @return list<string>
     */
    private function cellResourceDiagnostics(array $attachmentSummary, array $outputSummary): array
    {
        $diagnostics = [];
        if ($attachmentSummary['count'] > 0) {
            $diagnostics[] = 'attachment-bytes-blocked';
        }
        foreach ($outputSummary['policyDiagnostics'] as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        return array_values(array_unique($diagnostics));
    }

    /**
     * @param array<string, list<int>> $mimeOccurrences
     * @return list<array{mimeType:string, outputIndexes:list<int>, count:int}>
     */
    private function repeatedMimeBundleRecords(array $mimeOccurrences): array
    {
        ksort($mimeOccurrences);
        $records = [];
        foreach ($mimeOccurrences as $mimeType => $outputIndexes) {
            if (count($outputIndexes) < 2) {
                continue;
            }
            $records[] = [
                'mimeType' => $mimeType,
                'outputIndexes' => $outputIndexes,
                'count' => count($outputIndexes),
            ];
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostic(string $issue, int $cellIndex, string $cellType, ?string $cellId, array $extra = []): array
    {
        $diagnostic = [
            'issue' => $issue,
            'cellIndex' => $cellIndex,
            'cellType' => $cellType,
        ];
        if ($cellId !== null) {
            $diagnostic['cellId'] = $cellId;
        }

        return $diagnostic + $extra;
    }

    private function valueKind(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_array($value)) {
            return 'array';
        }

        return get_debug_type($value);
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
        return $this->sortedUniqueStrings($this->stringList($value));
    }

    /**
     * @param array<string, mixed> $bundle
     * @return list<string>
     */
    private function mimeTypesFromBundle(array $bundle): array
    {
        $mimeTypes = [];
        foreach ($bundle as $mimeType => $payload) {
            if (!is_string($mimeType) || $mimeType === '' || !(is_scalar($payload) || is_array($payload))) {
                continue;
            }
            $mimeTypes[] = $mimeType;
        }
        sort($mimeTypes);

        return array_values(array_unique($mimeTypes));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    private function hasStringOrStringList(mixed $value): bool
    {
        return $this->stringList($value) !== [];
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function sortedUniqueStrings(array $strings): array
    {
        sort($strings);

        return array_values(array_unique($strings));
    }

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
