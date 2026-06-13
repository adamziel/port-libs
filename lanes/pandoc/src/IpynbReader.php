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

        foreach ($cells as $index => $cell) {
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$index} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $source = $this->normalizeSource($cell['source'] ?? '', "IPYNB cell {$index} source");
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$index} exceeds the bounded native reader source limit");
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments);
            $outputSummary = $this->outputSummary($outputs, $index);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];

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
            if ($outputSummary['mimeTypes'] !== []) {
                $attributes['data-ipynb-output-mime-types'] = implode(' ', $outputSummary['mimeTypes']);
            }
            if ($outputSummary['richUnsupportedCount'] > 0) {
                $attributes['data-ipynb-rich-output-unsupported-count'] = (string) $outputSummary['richUnsupportedCount'];
            }
            if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
                $attributes['data-ipynb-execution-count'] = (string) $cell['execution_count'];
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
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputMimeTypes' => $outputSummary['mimeTypes'],
                'ipynbOutputSummaries' => $outputSummary['outputs'],
                'ipynbOutputUnsupportedVerdicts' => $outputSummary['unsupportedVerdicts'],
                'ipynbRichOutputUnsupportedCount' => $outputSummary['richUnsupportedCount'],
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
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'richOutputUnsupportedCount' => $outputSummary['richUnsupportedCount'],
            ];
        }

        $notebookOutputMimeTypes = [];
        $notebookOutputDiagnostics = [];
        $notebookRichOutputUnsupportedCount = 0;
        foreach ($blocks as $block) {
            $mimeTypes = $block->attr('ipynbOutputMimeTypes', []);
            if (is_array($mimeTypes)) {
                foreach ($mimeTypes as $mimeType) {
                    if (is_string($mimeType) && $mimeType !== '') {
                        $notebookOutputMimeTypes[] = $mimeType;
                    }
                }
            }

            $unsupportedVerdicts = $block->attr('ipynbOutputUnsupportedVerdicts', []);
            if (is_array($unsupportedVerdicts)) {
                foreach ($unsupportedVerdicts as $verdict) {
                    if (is_array($verdict)) {
                        $notebookOutputDiagnostics[] = $verdict;
                    }
                }
            }

            $notebookRichOutputUnsupportedCount += (int) $block->attr('ipynbRichOutputUnsupportedCount', 0);
        }
        $notebookOutputMimeTypes = array_values(array_unique($notebookOutputMimeTypes));
        sort($notebookOutputMimeTypes);

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookAttachmentCount' => $attachmentCount,
            'notebookOutputCount' => $outputCount,
            'notebookOutputMimeTypes' => $notebookOutputMimeTypes,
            'notebookRichOutputUnsupportedCount' => $notebookRichOutputUnsupportedCount,
            'notebookOutputDiagnostics' => $notebookOutputDiagnostics,
            'notebookNbformat' => $notebook['nbformat'] ?? null,
            'notebookNbformatMinor' => $notebook['nbformat_minor'] ?? null,
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
     * @return array{count:int, types:list<string>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, unsupportedVerdicts:list<array<string, mixed>>, richUnsupportedCount:int}
     */
    private function outputSummary(array $outputs, int $cellIndex): array
    {
        $types = [];
        $mimeTypes = [];
        $summaries = [];
        $unsupportedVerdicts = [];
        foreach ($outputs as $index => $output) {
            if (!is_array($output)) {
                $summaries[] = [
                    'index' => $index,
                    'outputType' => 'unknown',
                    'diagnostics' => ['ipynb-output-not-object'],
                ];
                continue;
            }
            $type = $output['output_type'] ?? null;
            $outputType = is_string($type) && $type !== '' ? $type : 'unknown';
            if ($outputType !== 'unknown') {
                $types[] = $outputType;
            }

            $summary = [
                'index' => $index,
                'outputType' => $outputType,
            ];

            $outputMimeTypes = $this->outputMimeTypes($output);
            if ($outputMimeTypes !== []) {
                $summary['mimeTypes'] = $outputMimeTypes;
                $summary['mimeCount'] = count($outputMimeTypes);
                array_push($mimeTypes, ...$outputMimeTypes);
            }

            if (isset($output['metadata']) && is_array($output['metadata'])) {
                $summary['metadataKeyCount'] = count($output['metadata']);
            }

            if ($outputType === 'stream') {
                $streamName = $output['name'] ?? null;
                if (is_string($streamName) && $streamName !== '') {
                    $summary['streamName'] = $streamName;
                }
                $summary['textLineCount'] = $this->outputTextLineCount($output['text'] ?? null);
            } elseif ($outputType === 'error') {
                $errorName = $output['ename'] ?? null;
                if (is_string($errorName) && $errorName !== '') {
                    $summary['errorName'] = $errorName;
                }
                $summary['tracebackLineCount'] = $this->outputTextLineCount($output['traceback'] ?? null);
            }

            if ($this->isRichOutputType($outputType) && $outputMimeTypes !== []) {
                $verdict = [
                    'code' => 'ipynb-rich-output-unsupported',
                    'cellIndex' => $cellIndex,
                    'outputIndex' => $index,
                    'outputType' => $outputType,
                    'mimeTypes' => $outputMimeTypes,
                    'mimeCount' => count($outputMimeTypes),
                    'reason' => 'rich-output-rendering-not-implemented',
                    'payloadPolicy' => 'metadata-only-no-payload-bytes',
                ];
                $summary['unsupportedVerdict'] = $verdict['code'];
                $unsupportedVerdicts[] = $verdict;
            }

            $summaries[] = $summary;
        }
        $types = array_values(array_unique($types));
        $mimeTypes = array_values(array_unique($mimeTypes));
        sort($mimeTypes);

        return [
            'count' => count($outputs),
            'types' => $types,
            'mimeTypes' => $mimeTypes,
            'outputs' => $summaries,
            'unsupportedVerdicts' => $unsupportedVerdicts,
            'richUnsupportedCount' => count($unsupportedVerdicts),
        ];
    }

    /**
     * @param array<string, mixed> $output
     * @return list<string>
     */
    private function outputMimeTypes(array $output): array
    {
        $data = $output['data'] ?? null;
        if (!is_array($data)) {
            return [];
        }

        $mimeTypes = [];
        foreach ($data as $mimeType => $_payload) {
            $mimeType = $this->normalizeMimeType((string) $mimeType);
            if ($mimeType !== '') {
                $mimeTypes[] = $mimeType;
            }
        }
        $mimeTypes = array_values(array_unique($mimeTypes));
        sort($mimeTypes);

        return $mimeTypes;
    }

    private function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        if (preg_match('/^[a-z0-9][a-z0-9.+_-]*\/[a-z0-9][a-z0-9.+_-]*(?:\s*;\s*[a-z0-9_.-]+=(?:"[^"]*"|[^;\s]+))*$/', $mimeType) !== 1) {
            return '';
        }

        return $mimeType;
    }

    private function outputTextLineCount(mixed $text): int
    {
        if (is_string($text)) {
            return $text === '' ? 0 : substr_count($text, "\n") + 1;
        }

        if (!is_array($text)) {
            return 0;
        }

        $count = 0;
        foreach ($text as $line) {
            if (is_string($line)) {
                $count++;
            }
        }

        return $count;
    }

    private function isRichOutputType(string $outputType): bool
    {
        return in_array($outputType, ['display_data', 'execute_result'], true);
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
