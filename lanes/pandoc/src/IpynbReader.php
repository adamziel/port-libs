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

        $notebookSchemaDiagnostics = $this->notebookSchemaDiagnostics($notebook, $cells);
        $schemaDiagnostics = $notebookSchemaDiagnostics;
        $cellSchemaDiagnosticCount = 0;

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

        foreach ($cells as $index => $cell) {
            $cellIndex = is_int($index) ? $index : count($cellSummaries);
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $cellSchemaDiagnostics = $this->cellSchemaDiagnostics($cell, $cellType, $cellIndex);
            foreach ($cellSchemaDiagnostics as $diagnostic) {
                $schemaDiagnostics[] = $diagnostic;
            }
            $cellSchemaDiagnosticCount += count($cellSchemaDiagnostics);

            $source = $this->normalizeSource($cell['source'] ?? '', "IPYNB cell {$cellIndex} source");
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} exceeds the bounded native reader source limit");
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments);
            $outputSummary = $this->outputSummary($outputs);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];

            $attributes = [
                'data-ipynb-cell-index' => (string) $cellIndex,
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

            $children = match ($cellType) {
                'markdown' => $this->markdownCellBlocks($source),
                'code' => [$this->codeCellBlock($source, $language, $cellIndex, $cell)],
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
            ];
            if (array_key_exists('id', $cell) && is_string($cell['id']) && $cell['id'] !== '') {
                $cellAttrs['ipynbCellId'] = $cell['id'];
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellAttrs['ipynbExecutionCount'] = $cell['execution_count'];
            }
            if ($cellSchemaDiagnostics !== []) {
                $cellAttrs['ipynbCellSchemaDiagnosticCount'] = count($cellSchemaDiagnostics);
                $cellAttrs['ipynbCellSchemaDiagnostics'] = $cellSchemaDiagnostics;
            }

            $blocks[] = new AstNode('div', $cellAttrs, $children);
            $cellSummary = [
                'index' => $cellIndex,
                'type' => $cellType,
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'outputCount' => $outputSummary['count'],
            ];
            if ($cellSchemaDiagnostics !== []) {
                $cellSummary['schemaDiagnosticCount'] = count($cellSchemaDiagnostics);
                $cellSummary['schemaDiagnostics'] = $cellSchemaDiagnostics;
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
            'notebookNbformat' => $notebook['nbformat'] ?? null,
            'notebookNbformatMinor' => $notebook['nbformat_minor'] ?? null,
            'notebookKernelName' => $this->metadataString($metadata['kernelspec'] ?? null, 'name'),
            'notebookLanguage' => $language,
            'notebookSchemaByteExposurePolicy' => 'metadata-only',
            'notebookSchemaDiagnosticCount' => count($schemaDiagnostics),
            'notebookSchemaDiagnostics' => $schemaDiagnostics,
            'notebookSchemaReview' => $this->notebookSchemaReview(
                $notebook,
                count($cells),
                count($notebookSchemaDiagnostics),
                $cellSchemaDiagnosticCount,
                $schemaDiagnostics
            ),
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
     * @return array{count:int, types:list<string>}
     */
    private function outputSummary(array $outputs): array
    {
        $types = [];
        foreach ($outputs as $output) {
            if (!is_array($output)) {
                continue;
            }
            $type = $output['output_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $types[] = $type;
            }
        }

        return [
            'count' => count($outputs),
            'types' => array_values(array_unique($types)),
        ];
    }

    /**
     * @param array<string, mixed> $notebook
     * @param array<int|string, mixed> $cells
     * @return list<array<string, mixed>>
     */
    private function notebookSchemaDiagnostics(array $notebook, array $cells): array
    {
        $diagnostics = [];

        if (!array_key_exists('nbformat', $notebook)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-nbformat', 'notebook', 'nbformat', 'integer 4', 'missing');
        } elseif (!is_int($notebook['nbformat'])) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-nbformat', 'notebook', 'nbformat', 'integer 4', $this->jsonValueType($notebook['nbformat']));
        } elseif ($notebook['nbformat'] !== 4) {
            $diagnostics[] = $this->schemaDiagnostic('unsupported-nbformat', 'notebook', 'nbformat', 'integer 4', 'integer');
        }

        if (!array_key_exists('nbformat_minor', $notebook)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-nbformat-minor', 'notebook', 'nbformat_minor', 'non-negative integer', 'missing');
        } elseif (!is_int($notebook['nbformat_minor']) || $notebook['nbformat_minor'] < 0) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-nbformat-minor', 'notebook', 'nbformat_minor', 'non-negative integer', $this->jsonValueType($notebook['nbformat_minor']));
        }

        if (array_key_exists('metadata', $notebook) && !is_array($notebook['metadata'])) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-notebook-metadata', 'notebook', 'metadata', 'object', $this->jsonValueType($notebook['metadata']));
        }

        if (!array_is_list($cells)) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cells-shape', 'notebook', 'cells', 'array', 'object');
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $cell
     * @return list<array<string, mixed>>
     */
    private function cellSchemaDiagnostics(array $cell, string $cellType, int $index): array
    {
        $diagnostics = [];

        if (!array_key_exists('cell_type', $cell)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-cell-type', 'cell', 'cell_type', 'markdown, code, or raw', 'missing', $index);
        } elseif (!is_string($cell['cell_type']) || $cell['cell_type'] === '') {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cell-type', 'cell', 'cell_type', 'non-empty string', $this->jsonValueType($cell['cell_type']), $index);
        } elseif (!in_array($cellType, ['markdown', 'code', 'raw'], true)) {
            $diagnostics[] = $this->schemaDiagnostic('unsupported-cell-type', 'cell', 'cell_type', 'markdown, code, or raw', 'string', $index);
        }

        if (!array_key_exists('metadata', $cell)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-cell-metadata', 'cell', 'metadata', 'object', 'missing', $index);
        } elseif (!is_array($cell['metadata'])) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cell-metadata', 'cell', 'metadata', 'object', $this->jsonValueType($cell['metadata']), $index);
        }

        if (array_key_exists('id', $cell) && (!is_string($cell['id']) || $cell['id'] === '')) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cell-id', 'cell', 'id', 'non-empty string', $this->jsonValueType($cell['id']), $index);
        }

        if (array_key_exists('attachments', $cell)) {
            if (!is_array($cell['attachments'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-cell-attachments', 'cell', 'attachments', 'object', $this->jsonValueType($cell['attachments']), $index);
            } elseif ($cell['attachments'] !== [] && array_is_list($cell['attachments'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-cell-attachments', 'cell', 'attachments', 'object', 'array', $index);
            }
        }

        if ($cellType === 'code') {
            if (!array_key_exists('execution_count', $cell)) {
                $diagnostics[] = $this->schemaDiagnostic('missing-code-execution-count', 'cell', 'execution_count', 'integer or null', 'missing', $index);
            } elseif (!is_int($cell['execution_count']) && $cell['execution_count'] !== null) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-code-execution-count', 'cell', 'execution_count', 'integer or null', $this->jsonValueType($cell['execution_count']), $index);
            }

            if (!array_key_exists('outputs', $cell)) {
                $diagnostics[] = $this->schemaDiagnostic('missing-code-outputs', 'cell', 'outputs', 'array', 'missing', $index);
            } elseif (!is_array($cell['outputs'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-code-outputs', 'cell', 'outputs', 'array', $this->jsonValueType($cell['outputs']), $index);
            } elseif (!array_is_list($cell['outputs'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-code-outputs', 'cell', 'outputs', 'array', 'object', $index);
            }
        } elseif (array_key_exists('outputs', $cell)) {
            $diagnostics[] = $this->schemaDiagnostic('unexpected-cell-outputs', 'cell', 'outputs', 'absent on non-code cells', $this->jsonValueType($cell['outputs']), $index);
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $notebook
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>
     */
    private function notebookSchemaReview(
        array $notebook,
        int $cellCount,
        int $notebookDiagnosticCount,
        int $cellDiagnosticCount,
        array $diagnostics
    ): array {
        $review = [
            'schema' => 'nbformat-v4-bounded',
            'byteExposurePolicy' => 'metadata-only',
            'checkedCellCount' => $cellCount,
            'diagnosticCount' => count($diagnostics),
            'notebookDiagnosticCount' => $notebookDiagnosticCount,
            'cellDiagnosticCount' => $cellDiagnosticCount,
            'diagnostics' => $diagnostics,
        ];

        if (array_key_exists('nbformat', $notebook) && is_int($notebook['nbformat'])) {
            $review['nbformat'] = $notebook['nbformat'];
        }
        if (array_key_exists('nbformat_minor', $notebook) && is_int($notebook['nbformat_minor'])) {
            $review['nbformatMinor'] = $notebook['nbformat_minor'];
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaDiagnostic(
        string $type,
        string $scope,
        string $field,
        string $expected,
        string $actual,
        ?int $cellIndex = null
    ): array {
        $diagnostic = [
            'type' => $type,
            'scope' => $scope,
            'field' => $field,
            'severity' => 'warning',
            'expected' => $expected,
            'actual' => $actual,
        ];

        if ($cellIndex !== null) {
            $diagnostic['cellIndex'] = $cellIndex;
        }

        return $diagnostic;
    }

    private function jsonValueType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
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
