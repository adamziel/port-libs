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
        $outputBytePresenceCount = 0;
        $outputMimeBundleCount = 0;
        $metadataKeys = $this->metadataKeys($metadata);

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
            $outputSummary = $this->outputSummary($outputs);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['bytePresenceCount'];
            $outputBytePresenceCount += $outputSummary['bytePresenceCount'];
            $outputMimeBundleCount += $outputSummary['mimeBundleCount'];

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
            if ($outputSummary['mimeTypes'] !== []) {
                $attributes['data-ipynb-output-mime-types'] = implode(' ', $outputSummary['mimeTypes']);
            }
            if ($outputSummary['bytePresenceCount'] > 0) {
                $attributes['data-ipynb-output-byte-policy'] = 'metadata-only';
                $attributes['data-ipynb-output-byte-presence-count'] = (string) $outputSummary['bytePresenceCount'];
            }
            if ($outputSummary['executionCounts'] !== []) {
                $attributes['data-ipynb-output-execution-counts'] = implode(' ', array_map(static fn (int $count): string => (string) $count, $outputSummary['executionCounts']));
            }
            if ($outputSummary['errorNames'] !== []) {
                $attributes['data-ipynb-output-error-names'] = implode(' ', $outputSummary['errorNames']);
            }
            if ($outputSummary['streamNames'] !== []) {
                $attributes['data-ipynb-output-stream-names'] = implode(' ', $outputSummary['streamNames']);
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
                'ipynbOutputSummaries' => $outputSummary['outputs'],
                'ipynbOutputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'ipynbOutputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'ipynbOutputExecutionCounts' => $outputSummary['executionCounts'],
                'ipynbOutputErrorNames' => $outputSummary['errorNames'],
                'ipynbOutputStreamNames' => $outputSummary['streamNames'],
                'ipynbUnsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
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
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'outputSummaries' => $outputSummary['outputs'],
                'outputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'outputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
                'diagnostics' => $cellDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
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
            'notebookOutputBytePresenceCount' => $outputBytePresenceCount,
            'notebookOutputMimeBundleCount' => $outputMimeBundleCount,
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
     * @return array{count:int, types:list<string>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, mimeBundleCount:int, bytePresenceCount:int, executionCounts:list<int>, errorNames:list<string>, streamNames:list<string>, diagnostics:list<string>}
     */
    private function outputSummary(array $outputs): array
    {
        $types = [];
        $mimeTypes = [];
        $outputRows = [];
        $mimeBundleCount = 0;
        $bytePresenceCount = 0;
        $executionCounts = [];
        $errorNames = [];
        $streamNames = [];
        $diagnostics = [];

        foreach ($outputs as $output) {
            if (!is_array($output)) {
                continue;
            }
            $type = $output['output_type'] ?? null;
            $type = is_string($type) && $type !== '' ? $type : 'unknown';
            $types[] = $type;

            $dataMimeTypes = [];
            $data = $output['data'] ?? null;
            if (is_array($data)) {
                $dataMimeTypes = $this->mimeTypesFromBundle($data);
            }
            foreach ($dataMimeTypes as $mimeType) {
                $mimeTypes[] = $mimeType;
            }

            $outputDiagnostics = [];
            $hasMimeBundle = $dataMimeTypes !== [];
            $hasStreamPayload = $type === 'stream' && $this->hasStringOrStringList($output['text'] ?? null);
            $errorName = $this->nonEmptyString($output['ename'] ?? null);
            $hasErrorValue = $this->nonEmptyString($output['evalue'] ?? null) !== null;
            $tracebackLines = $this->stringList($output['traceback'] ?? null);
            $hasErrorPayload = $type === 'error' && ($errorName !== null || $hasErrorValue || $tracebackLines !== []);
            $hasBytePresence = $hasMimeBundle || $hasStreamPayload || $hasErrorPayload;

            if ($hasBytePresence) {
                $bytePresenceCount++;
                $outputDiagnostics[] = 'output-bytes-blocked';
            }
            if ($hasMimeBundle) {
                $mimeBundleCount++;
                $outputDiagnostics[] = 'output-mime-bundle-metadata-only';
            }
            if ($hasStreamPayload) {
                $outputDiagnostics[] = 'output-stream-bytes-blocked';
            }
            if ($type === 'error') {
                $outputDiagnostics[] = 'output-error-metadata-only';
                if ($tracebackLines !== []) {
                    $outputDiagnostics[] = 'output-error-traceback-bytes-blocked';
                }
            }
            foreach ($outputDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }

            $row = [
                'index' => count($outputRows),
                'type' => $type,
                'mimeTypes' => $dataMimeTypes,
                'metadataKeys' => isset($output['metadata']) && is_array($output['metadata']) ? $this->metadataKeys($output['metadata']) : [],
                'bytePresence' => $hasBytePresence ? 'present' : 'none',
                'byteExposure' => $hasBytePresence ? 'blocked' : 'none',
                'diagnostics' => array_values(array_unique($outputDiagnostics)),
            ];

            if (array_key_exists('execution_count', $output) && (is_int($output['execution_count']) || $output['execution_count'] === null)) {
                $row['executionCount'] = $output['execution_count'];
                if (is_int($output['execution_count'])) {
                    $executionCounts[] = $output['execution_count'];
                }
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
        sort($mimeTypes);

        return [
            'count' => count($outputs),
            'types' => array_values(array_unique($types)),
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'outputs' => $outputRows,
            'mimeBundleCount' => $mimeBundleCount,
            'bytePresenceCount' => $bytePresenceCount,
            'executionCounts' => array_values(array_unique($executionCounts)),
            'errorNames' => $this->sortedUniqueStrings($errorNames),
            'streamNames' => $this->sortedUniqueStrings($streamNames),
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @param array{count:int, names:list<string>, mimeTypes:list<string>} $attachmentSummary
     * @param array{count:int, types:list<string>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, mimeBundleCount:int, bytePresenceCount:int, executionCounts:list<int>, errorNames:list<string>, streamNames:list<string>, diagnostics:list<string>} $outputSummary
     * @return list<string>
     */
    private function cellDiagnostics(array $attachmentSummary, array $outputSummary): array
    {
        $diagnostics = [];
        if ($attachmentSummary['count'] > 0) {
            $diagnostics[] = 'attachment-bytes-blocked';
        }
        foreach ($outputSummary['diagnostics'] as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        return array_values(array_unique($diagnostics));
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
