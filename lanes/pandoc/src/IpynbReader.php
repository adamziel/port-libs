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

        $blocks = [];
        $cellSummaries = [];
        $diagnostics = [];
        $markdownCellCount = 0;
        $codeCellCount = 0;
        $rawCellCount = 0;
        $attachmentCount = 0;
        $outputCount = 0;
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

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
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
            }
            if ($outputSummary['executionCountMismatchCount'] > 0) {
                $attributes['data-ipynb-output-execution-count-mismatch-count'] = (string) $outputSummary['executionCountMismatchCount'];
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostic-count'] = (string) count($cellDiagnostics);
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
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputExecutionCounts' => $outputSummary['executionCounts'],
                'ipynbOutputExecutionCountRecordCount' => $outputSummary['executionCountRecordCount'],
                'ipynbOutputExecutionCountMismatchCount' => $outputSummary['executionCountMismatchCount'],
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
            $cellSummary = [
                'index' => $cellIndex,
                'type' => $cellType,
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'outputCount' => $outputSummary['count'],
                'executionCountPresent' => $executionSummary['present'],
                'executionCountValid' => $executionSummary['valid'],
                'diagnosticCount' => count($cellDiagnostics),
                'diagnostics' => $cellDiagnostics,
            ];
            if ($cellId !== null) {
                $cellSummary['id'] = $cellId;
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellSummary['executionCount'] = $cell['execution_count'];
            }
            $cellSummaries[] = $cellSummary;
        }

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookAttachmentCount' => $attachmentCount,
            'notebookOutputCount' => $outputCount,
            'notebookCellIdsRequired' => $cellIdsRequired,
            'notebookCellExecutionCountPresentCount' => $cellExecutionCountPresentCount,
            'notebookCellExecutionCountValidCount' => $cellExecutionCountValidCount,
            'notebookOutputExecutionCountRecordCount' => $outputExecutionCountRecordCount,
            'notebookOutputExecutionCountMismatchCount' => $outputExecutionCountMismatchCount,
            'notebookDiagnosticCount' => count($diagnostics),
            'notebookDiagnostics' => $diagnostics,
            'notebookNbformat' => $nbformat,
            'notebookNbformatMinor' => $nbformatMinor,
            'notebookKernelName' => $this->metadataString($metadata['kernelspec'] ?? null, 'name'),
            'notebookLanguage' => $language,
            'notebookCells' => $cellSummaries,
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
     * @return array{count:int, names:list<string>}
     */
    private function attachmentSummary(array $attachments): array
    {
        $names = [];
        foreach ($attachments as $name => $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $names[] = (string) $name;
        }
        sort($names);

        return [
            'count' => count($names),
            'names' => $names,
        ];
    }

    /**
     * @param array<int, mixed> $outputs
     * @return array{count:int, types:list<string>, executionCounts:list<array<string, mixed>>, executionCountRecordCount:int, executionCountMismatchCount:int, diagnostics:list<array<string, mixed>>}
     */
    private function outputSummary(array $outputs, ?int $cellExecutionCount, string $cellType, int $cellIndex, ?string $cellId): array
    {
        $types = [];
        $executionCounts = [];
        $diagnostics = [];
        $mismatchCount = 0;

        foreach ($outputs as $outputIndex => $output) {
            if (!is_array($output)) {
                continue;
            }
            $type = $output['output_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $types[] = $type;
            }
            if (array_key_exists('execution_count', $output)) {
                $value = $output['execution_count'];
                $record = [
                    'outputIndex' => $outputIndex,
                    'outputType' => is_string($type) && $type !== '' ? $type : null,
                    'valueType' => $this->valueKind($value),
                    'valid' => false,
                    'matchesCell' => null,
                ];

                if (is_int($value)) {
                    $record['executionCount'] = $value;
                    if ($value < 0 || $value > self::MAX_EXECUTION_COUNT) {
                        $diagnostics[] = $this->diagnostic('output-execution-count-out-of-range', $cellIndex, $cellType, $cellId, [
                            'outputIndex' => $outputIndex,
                            'outputType' => $record['outputType'],
                            'value' => $value,
                            'min' => 0,
                            'max' => self::MAX_EXECUTION_COUNT,
                        ]);
                    } else {
                        $record['valid'] = true;
                        if ($cellExecutionCount !== null) {
                            $record['matchesCell'] = $value === $cellExecutionCount;
                            if ($value !== $cellExecutionCount) {
                                $mismatchCount++;
                                $diagnostics[] = $this->diagnostic('output-execution-count-mismatch', $cellIndex, $cellType, $cellId, [
                                    'outputIndex' => $outputIndex,
                                    'outputType' => $record['outputType'],
                                    'cellExecutionCount' => $cellExecutionCount,
                                    'outputExecutionCount' => $value,
                                ]);
                            }
                        }
                    }
                } else {
                    $diagnostics[] = $this->diagnostic('output-execution-count-invalid-type', $cellIndex, $cellType, $cellId, [
                        'outputIndex' => $outputIndex,
                        'outputType' => $record['outputType'],
                        'valueType' => $record['valueType'],
                    ]);
                }

                $executionCounts[] = $record;
            } elseif ($type === 'execute_result') {
                $diagnostics[] = $this->diagnostic('output-execution-count-missing', $cellIndex, $cellType, $cellId, [
                    'outputIndex' => $outputIndex,
                    'outputType' => $type,
                ]);
            }
        }

        return [
            'count' => count($outputs),
            'types' => array_values(array_unique($types)),
            'executionCounts' => $executionCounts,
            'executionCountRecordCount' => count($executionCounts),
            'executionCountMismatchCount' => $mismatchCount,
            'diagnostics' => $diagnostics,
        ];
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

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
