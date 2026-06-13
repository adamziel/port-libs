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
        $attachmentManifestEntries = [];
        $attachmentDiagnostics = [];

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
            $attachmentSummary = $this->attachmentSummary($attachments, $index);
            $outputSummary = $this->outputSummary($outputs);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            foreach ($attachmentSummary['entries'] as $entry) {
                $attachmentManifestEntries[] = $entry;
            }
            foreach ($attachmentSummary['diagnostics'] as $diagnostic) {
                $attachmentDiagnostics[] = $diagnostic;
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
                'ipynbAttachmentDiagnostics' => $attachmentSummary['diagnostics'],
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
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
                'attachmentDiagnostics' => $attachmentSummary['diagnostics'],
                'outputCount' => $outputSummary['count'],
            ];
        }

        $attachmentCollisionGroups = $this->attachmentCollisionGroups($attachmentManifestEntries);
        if ($attachmentCollisionGroups !== []) {
            $attachmentDiagnostics[] = 'ipynb-attachment-safe-name-collision';
        }
        $attachmentDiagnostics = array_values(array_unique($attachmentDiagnostics));
        $attachmentManifest = [
            'reviewPolicy' => 'metadata-only-no-payload',
            'payloadExposurePolicy' => 'ipynb-attachment-payload-bytes-omitted',
            'attachmentCount' => $attachmentCount,
            'entryCount' => count($attachmentManifestEntries),
            'entries' => $attachmentManifestEntries,
            'diagnosticCount' => count($attachmentDiagnostics),
            'diagnostics' => $attachmentDiagnostics,
            'collisionGroupCount' => count($attachmentCollisionGroups),
            'collisionGroups' => $attachmentCollisionGroups,
        ];

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookAttachmentCount' => $attachmentCount,
            'notebookAttachmentManifest' => $attachmentManifest,
            'notebookAttachmentDiagnostics' => $attachmentDiagnostics,
            'notebookAttachmentCollisionCount' => count($attachmentCollisionGroups),
            'notebookOutputCount' => $outputCount,
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
     * @return array{count:int, names:list<string>, entries:list<array<string, mixed>>, diagnostics:list<string>}
     */
    private function attachmentSummary(array $attachments, int $cellIndex): array
    {
        $names = [];
        $entries = [];
        $diagnostics = [];
        foreach ($attachments as $name => $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $name = (string) $name;
            $names[] = $name;

            $mimeTypes = [];
            foreach ($payload as $mimeType => $_payloadBytes) {
                if (is_string($mimeType) && $mimeType !== '') {
                    $mimeTypes[] = $mimeType;
                }
            }
            sort($mimeTypes);

            $entryDiagnostics = $this->attachmentNameDiagnostics($name);
            foreach ($entryDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }

            $entries[] = [
                'cellIndex' => $cellIndex,
                'name' => $name,
                'safeName' => $this->attachmentSafeName($name, count($entries)),
                'mimeTypeCount' => count($mimeTypes),
                'mimeTypes' => $mimeTypes,
                'payloadExposurePolicy' => 'metadata-only-no-payload',
                'diagnostics' => $entryDiagnostics,
            ];
        }
        sort($names);

        return [
            'count' => count($names),
            'names' => $names,
            'entries' => $entries,
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array{safeName:string, caseFoldKey:string, attachmentCount:int, entries:list<array{cellIndex:int, name:string, safeName:string}>}>
     */
    private function attachmentCollisionGroups(array $entries): array
    {
        $buckets = [];
        foreach ($entries as $entry) {
            $safeName = $entry['safeName'] ?? null;
            if (!is_string($safeName) || $safeName === '') {
                continue;
            }
            $key = strtolower($safeName);
            $buckets[$key][] = [
                'cellIndex' => (int) ($entry['cellIndex'] ?? 0),
                'name' => (string) ($entry['name'] ?? ''),
                'safeName' => $safeName,
            ];
        }

        $groups = [];
        foreach ($buckets as $key => $items) {
            if (count($items) < 2) {
                continue;
            }
            usort($items, static fn (array $left, array $right): int => [$left['cellIndex'], $left['name']] <=> [$right['cellIndex'], $right['name']]);
            $groups[] = [
                'safeName' => $items[0]['safeName'],
                'caseFoldKey' => $key,
                'attachmentCount' => count($items),
                'entries' => $items,
            ];
        }
        usort($groups, static fn (array $left, array $right): int => $left['caseFoldKey'] <=> $right['caseFoldKey']);

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function attachmentNameDiagnostics(string $name): array
    {
        $diagnostics = [];
        if ($name === '') {
            $diagnostics[] = 'ipynb-attachment-empty-name';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            $diagnostics[] = 'ipynb-attachment-control-bytes';
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $name) === 1) {
            $diagnostics[] = 'ipynb-attachment-absolute-path';
        }
        if (str_contains($name, '\\')) {
            $diagnostics[] = 'ipynb-attachment-backslash-path';
        }
        if (str_contains($name, '?') || str_contains($name, '#')) {
            $diagnostics[] = 'ipynb-attachment-query-fragment';
        }

        $segments = preg_split('/[\/\\\\]+/', $name) ?: [];
        if (in_array('..', $segments, true)) {
            $diagnostics[] = 'ipynb-attachment-parent-segment';
        }

        return array_values(array_unique($diagnostics));
    }

    private function attachmentSafeName(string $name, int $ordinal): string
    {
        $path = preg_replace('/[?#].*$/', '', str_replace('\\', '/', $name)) ?? $name;
        $segments = explode('/', $path);
        $base = end($segments);
        if (!is_string($base) || $base === '' || $base === '.' || $base === '..') {
            $base = 'attachment-' . ($ordinal + 1);
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
        $safe = trim($safe, '.-');

        return $safe === '' ? 'attachment-' . ($ordinal + 1) : $safe;
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
