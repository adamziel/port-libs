<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class IpynbReader
{
    private const MAX_CELLS = 200;
    private const MAX_CELL_SOURCE_BYTES = 1048576;
    private const UNSAFE_CELL_METADATA_KEYS = [
        'collapsed',
        'deletable',
        'editable',
        'hide_input',
        'jupyter',
        'scrolled',
        'slideshow',
        'tags',
        'trusted',
    ];

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
        $rawMarkdownCellDiagnostics = [];

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
        $notebookRichOutputUnsupportedCount = 0;
        $notebookOutputMimeTypes = [];
        $notebookOutputDiagnostics = [];
        $attachmentMedia = [];
        $attachmentMediaDiagnostics = [];
        $attachmentManifestEntries = [];
        $attachmentDiagnostics = [];
        $metadataKeys = $this->metadataKeys($metadata);

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

            $sourcePresent = array_key_exists('source', $cell);
            $sourceValue = $sourcePresent ? $cell['source'] : '';
            $source = $this->normalizeSource($sourceValue, "IPYNB cell {$cellIndex} source");
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} exceeds the bounded native reader source limit");
            }
            $cellSourceDiagnostics = $this->rawMarkdownCellDiagnostics($cell, $cellType, $cellIndex, $sourcePresent, $sourceValue, $source);
            foreach ($cellSourceDiagnostics as $diagnostic) {
                $rawMarkdownCellDiagnostics[] = $diagnostic;
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments, $index);
            $outputSummary = $this->outputSummary($outputs, $index);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['bytePresenceCount'];
            $outputBytePresenceCount += $outputSummary['bytePresenceCount'];
            $outputMimeBundleCount += $outputSummary['mimeBundleCount'];
            $notebookRichOutputUnsupportedCount += $outputSummary['richUnsupportedCount'];
            $notebookOutputMimeTypes = array_merge($notebookOutputMimeTypes, $outputSummary['mimeTypes']);
            $notebookOutputDiagnostics = array_merge($notebookOutputDiagnostics, $outputSummary['unsupportedVerdicts']);
            $attachmentMedia = array_merge($attachmentMedia, $attachmentSummary['media']);
            $attachmentMediaDiagnostics = array_merge($attachmentMediaDiagnostics, $attachmentSummary['diagnostics']);
            $attachmentManifestEntries = array_merge($attachmentManifestEntries, $attachmentSummary['manifestEntries']);
            $attachmentDiagnostics = array_merge($attachmentDiagnostics, $attachmentSummary['manifestDiagnostics']);

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
            if ($outputSummary['mimeTypes'] !== []) {
                $attributes['data-ipynb-output-mime-types'] = implode(' ', $outputSummary['mimeTypes']);
            }
            if ($outputSummary['richUnsupportedCount'] > 0) {
                $attributes['data-ipynb-rich-output-unsupported-count'] = (string) $outputSummary['richUnsupportedCount'];
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
            if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
                $attributes['data-ipynb-execution-count'] = (string) $cell['execution_count'];
            }
            if ($cellTags !== []) {
                $attributes['data-ipynb-cell-tags'] = implode(' ', $cellTags);
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostics'] = implode(' ', $cellDiagnostics);
            }
            if ($attachmentSummary['media'] !== []) {
                $attributes['data-ipynb-attachment-media-count'] = (string) count($attachmentSummary['media']);
            }
            if ($attachmentSummary['diagnostics'] !== []) {
                $attributes['data-ipynb-attachment-diagnostics'] = implode(' ', $attachmentSummary['diagnostics']);
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
                'ipynbAttachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'ipynbAttachmentMedia' => $attachmentSummary['media'],
                'ipynbAttachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'ipynbAttachmentDiagnostics' => $attachmentSummary['manifestDiagnostics'],
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputMimeTypes' => $outputSummary['mimeTypes'],
                'ipynbOutputSummaries' => $outputSummary['outputs'],
                'ipynbOutputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'ipynbOutputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'ipynbOutputExecutionCounts' => $outputSummary['executionCounts'],
                'ipynbOutputErrorNames' => $outputSummary['errorNames'],
                'ipynbOutputStreamNames' => $outputSummary['streamNames'],
                'ipynbOutputUnsupportedVerdicts' => $outputSummary['unsupportedVerdicts'],
                'ipynbRichOutputUnsupportedCount' => $outputSummary['richUnsupportedCount'],
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
            if ($cellSchemaDiagnostics !== []) {
                $cellAttrs['ipynbCellSchemaDiagnosticCount'] = count($cellSchemaDiagnostics);
                $cellAttrs['ipynbCellSchemaDiagnostics'] = $cellSchemaDiagnostics;
            }
            if ($cellSourceDiagnostics !== []) {
                $cellAttrs['ipynbCellSourceDiagnosticCount'] = count($cellSourceDiagnostics);
                $cellAttrs['ipynbCellSourceDiagnostics'] = $cellSourceDiagnostics;
            }

            $blocks[] = new AstNode('div', $cellAttrs, $children);
            $cellSummary = [
                'index' => $cellIndex,
                'type' => $cellType,
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'attachmentMedia' => $attachmentSummary['media'],
                'attachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'attachmentDiagnostics' => $attachmentSummary['manifestDiagnostics'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'outputSummaries' => $outputSummary['outputs'],
                'outputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'outputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'outputExecutionCounts' => $outputSummary['executionCounts'],
                'outputErrorNames' => $outputSummary['errorNames'],
                'outputStreamNames' => $outputSummary['streamNames'],
                'outputUnsupportedVerdicts' => $outputSummary['unsupportedVerdicts'],
                'richOutputUnsupportedCount' => $outputSummary['richUnsupportedCount'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
                'diagnostics' => $cellDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
            ];
            if ($cellSchemaDiagnostics !== []) {
                $cellSummary['schemaDiagnosticCount'] = count($cellSchemaDiagnostics);
                $cellSummary['schemaDiagnostics'] = $cellSchemaDiagnostics;
            }
            if ($cellSourceDiagnostics !== []) {
                $cellSummary['sourceDiagnosticCount'] = count($cellSourceDiagnostics);
                $cellSummary['sourceDiagnostics'] = $cellSourceDiagnostics;
            }
            $cellSummaries[] = $cellSummary;
        }

        $attachmentCollisionGroups = $this->attachmentCollisionGroups($attachmentManifestEntries);
        if ($attachmentCollisionGroups !== []) {
            $attachmentDiagnostics[] = 'ipynb-attachment-safe-name-collision';
        }
        $attachmentDiagnostics = $this->uniqueSortedStrings($attachmentDiagnostics);
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
            'notebookAttachmentMediaCount' => count($attachmentMedia),
            'notebookAttachmentMedia' => $attachmentMedia,
            'notebookAttachmentMediaDiagnostics' => $this->uniqueSortedStrings($attachmentMediaDiagnostics),
            'notebookAttachmentManifest' => $attachmentManifest,
            'notebookAttachmentDiagnostics' => $attachmentDiagnostics,
            'notebookAttachmentCollisionCount' => count($attachmentCollisionGroups),
            'notebookOutputCount' => $outputCount,
            'notebookOutputMimeTypes' => $this->uniqueSortedStrings($notebookOutputMimeTypes),
            'notebookOutputBytePresenceCount' => $outputBytePresenceCount,
            'notebookOutputMimeBundleCount' => $outputMimeBundleCount,
            'notebookRichOutputUnsupportedCount' => $notebookRichOutputUnsupportedCount,
            'notebookOutputDiagnostics' => $notebookOutputDiagnostics,
            'notebookUnsupportedResourceCount' => $unsupportedResourceCount,
            'notebookNbformat' => $notebook['nbformat'] ?? null,
            'notebookNbformatMinor' => $notebook['nbformat_minor'] ?? null,
            'notebookMetadataKeys' => $metadataKeys,
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
            'notebookRawMarkdownCellByteExposurePolicy' => 'metadata-only',
            'notebookRawMarkdownCellDiagnosticCount' => count($rawMarkdownCellDiagnostics),
            'notebookRawMarkdownCellDiagnostics' => $rawMarkdownCellDiagnostics,
            'notebookRawMarkdownCellReview' => $this->rawMarkdownCellReview($markdownCellCount, $rawCellCount, $rawMarkdownCellDiagnostics),
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
     * @return array{
     *     count:int,
     *     names:list<string>,
     *     mimeTypes:list<string>,
     *     media:list<array{
     *         cellIndex:int,
     *         name:string,
     *         mimeTypes:list<string>,
     *         primaryMimeType:string,
     *         mediaPath:string,
     *         byteExposure:string,
     *         extractionState:string,
     *         diagnostics:list<string>
     *     }>,
     *     diagnostics:list<string>,
     *     manifestEntries:list<array<string, mixed>>,
     *     manifestDiagnostics:list<string>
     * }
     */
    private function attachmentSummary(array $attachments, int $cellIndex): array
    {
        $names = [];
        $mimeTypes = [];
        $media = [];
        $diagnostics = [];
        $manifestEntries = [];
        $manifestDiagnostics = [];
        $usedMediaPaths = [];
        $attachmentNames = [];
        foreach ($attachments as $name => $_payload) {
            $attachmentNames[] = (string) $name;
        }
        sort($attachmentNames);

        foreach ($attachmentNames as $name) {
            $payload = $attachments[$name] ?? null;
            if (!is_array($payload)) {
                continue;
            }
            $names[] = (string) $name;
            $payloadMimeTypes = $this->attachmentMimeTypes($payload);
            $mimeTypes = array_merge($mimeTypes, $payloadMimeTypes);
            $entryDiagnostics = $this->attachmentNameDiagnostics($name);
            $manifestDiagnostics = array_merge($manifestDiagnostics, $entryDiagnostics);
            $manifestEntries[] = [
                'cellIndex' => $cellIndex,
                'name' => $name,
                'safeName' => $this->attachmentSafeName($name, count($manifestEntries)),
                'mimeTypeCount' => count($payloadMimeTypes),
                'mimeTypes' => $payloadMimeTypes,
                'payloadExposurePolicy' => 'metadata-only-no-payload',
                'diagnostics' => $entryDiagnostics,
            ];
            $mediaPathPlan = $this->attachmentMediaPath($name, $payloadMimeTypes);
            $mediaPath = 'ipynb-media/' . $mediaPathPlan['safeName'];
            $itemDiagnostics = array_merge(['attachment-bytes-blocked'], $mediaPathPlan['diagnostics']);
            if (isset($usedMediaPaths[$mediaPath])) {
                $mediaPath = $this->disambiguateAttachmentMediaPath($mediaPath, $cellIndex, $name, $payloadMimeTypes);
                $itemDiagnostics[] = 'attachment-media-path-collision';
            }
            $usedMediaPaths[$mediaPath] = true;
            $itemDiagnostics = $this->uniqueSortedStrings($itemDiagnostics);
            $diagnostics = array_merge($diagnostics, $itemDiagnostics);

            $media[] = [
                'cellIndex' => $cellIndex,
                'name' => $name,
                'mimeTypes' => $payloadMimeTypes,
                'primaryMimeType' => $payloadMimeTypes[0] ?? $this->mimeTypeFromPath($name),
                'mediaPath' => $mediaPath,
                'byteExposure' => 'blocked',
                'extractionState' => 'planned-metadata-only',
                'diagnostics' => $itemDiagnostics,
            ];
        }
        sort($names);
        sort($mimeTypes);

        return [
            'count' => count($names),
            'names' => $names,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'media' => $media,
            'diagnostics' => $this->uniqueSortedStrings($diagnostics),
            'manifestEntries' => $manifestEntries,
            'manifestDiagnostics' => $this->uniqueSortedStrings($manifestDiagnostics),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function attachmentMimeTypes(array $payload): array
    {
        $mimeTypes = [];
        foreach ($payload as $mimeType => $data) {
            if (!is_string($mimeType) || $mimeType === '') {
                continue;
            }
            if (!is_scalar($data) && !$this->isStringList($data)) {
                continue;
            }
            $mimeTypes[] = $mimeType;
        }
        sort($mimeTypes);

        return array_values(array_unique($mimeTypes));
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

        return $this->uniqueSortedStrings($diagnostics);
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
     * @return array{count:int, types:list<string>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, unsupportedVerdicts:list<array<string, mixed>>, richUnsupportedCount:int, mimeBundleCount:int, bytePresenceCount:int, executionCounts:list<int>, errorNames:list<string>, streamNames:list<string>, diagnostics:list<string>}
     */
    private function outputSummary(array $outputs, int $cellIndex): array
    {
        $types = [];
        $mimeTypes = [];
        $summaries = [];
        $unsupportedVerdicts = [];
        $mimeBundleCount = 0;
        $bytePresenceCount = 0;
        $executionCounts = [];
        $errorNames = [];
        $streamNames = [];
        $diagnostics = [];
        foreach ($outputs as $index => $output) {
            if (!is_array($output)) {
                $summaries[] = [
                    'index' => $index,
                    'type' => 'unknown',
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
                'type' => $outputType,
                'outputType' => $outputType,
            ];

            $outputMimeTypes = $this->outputMimeTypes($output);
            $outputDiagnostics = [];
            $hasMimeBundle = $outputMimeTypes !== [];
            $hasStreamPayload = $outputType === 'stream' && $this->stringList($output['text'] ?? null) !== [];
            $errorName = is_string($output['ename'] ?? null) && $output['ename'] !== '' ? $output['ename'] : null;
            $hasErrorValue = is_string($output['evalue'] ?? null) && $output['evalue'] !== '';
            $tracebackLines = $this->stringList($output['traceback'] ?? null);
            $hasErrorPayload = $outputType === 'error' && ($errorName !== null || $hasErrorValue || $tracebackLines !== []);
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
            if ($outputType === 'error') {
                $outputDiagnostics[] = 'output-error-metadata-only';
                if ($tracebackLines !== []) {
                    $outputDiagnostics[] = 'output-error-traceback-bytes-blocked';
                }
            }
            foreach ($outputDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }

            if ($outputMimeTypes !== []) {
                $summary['mimeTypes'] = $outputMimeTypes;
                $summary['mimeCount'] = count($outputMimeTypes);
                array_push($mimeTypes, ...$outputMimeTypes);
            }

            if (isset($output['metadata']) && is_array($output['metadata'])) {
                $summary['metadataKeys'] = $this->metadataKeys($output['metadata']);
                $summary['metadataKeyCount'] = count($output['metadata']);
            }
            $summary['bytePresence'] = $hasBytePresence ? 'present' : 'none';
            $summary['byteExposure'] = $hasBytePresence ? 'blocked' : 'none';
            $summary['diagnostics'] = array_values(array_unique($outputDiagnostics));

            if ($outputType === 'stream') {
                $streamName = $output['name'] ?? null;
                if (is_string($streamName) && $streamName !== '') {
                    $summary['streamName'] = $streamName;
                    $streamNames[] = $streamName;
                }
                $summary['textLineCount'] = $this->outputTextLineCount($output['text'] ?? null);
            } elseif ($outputType === 'error') {
                if ($errorName !== null) {
                    $summary['errorName'] = $errorName;
                    $errorNames[] = $errorName;
                }
                $summary['errorValuePresent'] = $hasErrorValue;
                $summary['tracebackLineCount'] = $this->outputTextLineCount($output['traceback'] ?? null);
            }
            if (array_key_exists('execution_count', $output) && (is_int($output['execution_count']) || $output['execution_count'] === null)) {
                $summary['executionCount'] = $output['execution_count'];
                if (is_int($output['execution_count'])) {
                    $executionCounts[] = $output['execution_count'];
                }
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
            'mimeBundleCount' => $mimeBundleCount,
            'bytePresenceCount' => $bytePresenceCount,
            'executionCounts' => array_values(array_unique($executionCounts)),
            'errorNames' => $this->uniqueSortedStrings($errorNames),
            'streamNames' => $this->uniqueSortedStrings($streamNames),
            'diagnostics' => array_values(array_unique($diagnostics)),
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
     * @param array{count:int, names:list<string>, mimeTypes:list<string>, media:list<array<string, mixed>>, diagnostics:list<string>} $attachmentSummary
     * @param array{count:int, types:list<string>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, unsupportedVerdicts:list<array<string, mixed>>, richUnsupportedCount:int, mimeBundleCount:int, bytePresenceCount:int, executionCounts:list<int>, errorNames:list<string>, streamNames:list<string>, diagnostics:list<string>} $outputSummary
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
     * @param array<string, mixed> $cell
     * @return list<array<string, mixed>>
     */
    private function rawMarkdownCellDiagnostics(
        array $cell,
        string $cellType,
        int $index,
        bool $sourcePresent,
        mixed $sourceValue,
        string $source
    ): array {
        if (!in_array($cellType, ['markdown', 'raw'], true)) {
            return [];
        }

        $metadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
        $conversionSupported = $cellType === 'markdown';

        return [[
            'type' => $cellType . '-cell-source-review',
            'scope' => 'cell-source',
            'severity' => 'info',
            'cellIndex' => $index,
            'cellType' => $cellType,
            'sourceShape' => $this->sourceShape($sourcePresent, $sourceValue),
            'sourceBytes' => strlen($source),
            'sourceLineCount' => $this->sourceLineCount($source),
            'byteExposurePolicy' => 'metadata-only',
            'sourcePayloadIncluded' => false,
            'metadataPolicy' => 'keys-only',
            'metadataKeyCount' => count($metadata),
            'unsafeMetadataKeys' => $this->unsafeCellMetadataKeys($metadata),
            'conversionSupported' => $conversionSupported,
            'conversionVerdict' => $conversionSupported
                ? 'parsed-as-native-markdown-blocks'
                : 'unsupported-native-conversion-preserved-as-code-block',
            'externalTooling' => false,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>
     */
    private function rawMarkdownCellReview(int $markdownCellCount, int $rawCellCount, array $diagnostics): array
    {
        return [
            'scope' => 'raw-markdown-cell-source',
            'byteExposurePolicy' => 'metadata-only',
            'checkedCellCount' => $markdownCellCount + $rawCellCount,
            'diagnosticCount' => count($diagnostics),
            'externalTooling' => false,
            'diagnostics' => $diagnostics,
        ];
    }

    private function sourceShape(bool $sourcePresent, mixed $source): string
    {
        if (!$sourcePresent) {
            return 'missing';
        }
        if (is_string($source)) {
            return 'string';
        }
        if (is_array($source)) {
            return array_is_list($source) ? 'string-array' : 'object';
        }

        return $this->jsonValueType($source);
    }

    private function sourceLineCount(string $source): int
    {
        if ($source === '') {
            return 0;
        }

        return substr_count($source, "\n") + 1;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function unsafeCellMetadataKeys(array $metadata): array
    {
        $unsafe = [];
        foreach (array_keys($metadata) as $key) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::UNSAFE_CELL_METADATA_KEYS, true)) {
                $unsafe[] = $normalized;
            }
        }
        $unsafe = array_values(array_unique($unsafe));
        sort($unsafe);

        return $unsafe;
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

    /**
     * @param list<string> $mimeTypes
     * @return array{safeName:string, diagnostics:list<string>}
     */
    private function attachmentMediaPath(string $name, array $mimeTypes): array
    {
        $diagnostics = [];
        $path = $name;
        if ($path === '') {
            $diagnostics[] = 'attachment-empty-name';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            $diagnostics[] = 'attachment-control-byte-name';
        }
        if (str_contains($path, '\\')) {
            $diagnostics[] = 'attachment-backslash-path';
        }

        $normalized = str_replace('\\', '/', $path);
        if ($this->isUri($normalized)) {
            $diagnostics[] = 'attachment-uri-name';
            $uriPath = parse_url($normalized, PHP_URL_PATH);
            $normalized = is_string($uriPath) && $uriPath !== '' ? $uriPath : $normalized;
        }
        if (str_contains($normalized, '?') || str_contains($normalized, '#')) {
            $diagnostics[] = 'attachment-query-or-fragment';
            $normalized = strtok($normalized, '?#') ?: $normalized;
        }
        if (str_starts_with($normalized, '/') || str_starts_with($normalized, '//') || preg_match('/\A[A-Za-z]:\//', $normalized) === 1) {
            $diagnostics[] = 'attachment-absolute-path';
        }
        if (str_contains($normalized, '%')) {
            $diagnostics[] = 'attachment-percent-encoded-path';
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                $diagnostics[] = 'attachment-path-traversal';
                continue;
            }
            $segments[] = $segment;
        }

        $requiresOpaqueName = array_diff($diagnostics, ['attachment-safe-path-remapped']) !== [];
        if ($requiresOpaqueName) {
            return [
                'safeName' => 'attachment-' . substr(sha1($name), 0, 12) . $this->attachmentExtension($name, $mimeTypes[0] ?? ''),
                'diagnostics' => $this->uniqueSortedStrings(array_merge($diagnostics, ['attachment-safe-path-remapped'])),
            ];
        }

        $safeSegments = [];
        foreach ($segments as $segment) {
            $safeSegment = preg_replace('/[^A-Za-z0-9._-]+/', '-', $segment) ?? '';
            $safeSegment = trim($safeSegment, '-');
            if ($safeSegment === '' || $safeSegment === '.' || $safeSegment === '..') {
                $safeSegment = 'attachment';
            }
            if ($safeSegment !== $segment) {
                $diagnostics[] = 'attachment-safe-path-remapped';
            }
            $safeSegments[] = $safeSegment;
        }

        $safeName = implode('/', $safeSegments);
        if ($safeName === '') {
            $safeName = 'attachment-' . substr(sha1($name), 0, 12) . $this->attachmentExtension($name, $mimeTypes[0] ?? '');
            $diagnostics[] = 'attachment-safe-path-remapped';
        }

        return [
            'safeName' => $safeName,
            'diagnostics' => $this->uniqueSortedStrings($diagnostics),
        ];
    }

    /**
     * @param list<string> $mimeTypes
     */
    private function disambiguateAttachmentMediaPath(string $mediaPath, int $cellIndex, string $name, array $mimeTypes): string
    {
        $extension = $this->pathExtension($mediaPath);
        $stem = $extension === '' ? $mediaPath : substr($mediaPath, 0, -strlen($extension));
        $suffix = substr(sha1($cellIndex . "\0" . $name . "\0" . implode("\0", $mimeTypes)), 0, 12);

        return $stem . '-' . $suffix . $extension;
    }

    private function attachmentExtension(string $name, string $mimeType): string
    {
        $extension = $this->pathExtension(strtok(str_replace('\\', '/', $name), '?#') ?: $name);
        if ($extension !== '' && !str_contains($extension, '%')) {
            return $extension;
        }

        return match (strtolower($mimeType)) {
            'image/apng' => '.apng',
            'image/avif' => '.avif',
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/svg+xml' => '.svg',
            'image/webp' => '.webp',
            'text/html' => '.html',
            'text/plain' => '.txt',
            'application/json' => '.json',
            'application/pdf' => '.pdf',
            default => '',
        };
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower($this->pathExtension($path))) {
            '.apng' => 'image/apng',
            '.avif' => 'image/avif',
            '.gif' => 'image/gif',
            '.jpeg', '.jpg', '.jpe' => 'image/jpeg',
            '.png' => 'image/png',
            '.svg', '.svgz' => 'image/svg+xml',
            '.webp' => 'image/webp',
            '.html', '.htm' => 'text/html',
            '.txt', '.text' => 'text/plain',
            '.json' => 'application/json',
            '.pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function pathExtension(string $path): string
    {
        $basename = basename($path);
        $position = strrpos($basename, '.');
        if ($position === false || $position === 0) {
            return '';
        }

        return substr($basename, $position);
    }

    private function isUri(string $source): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1
            && preg_match('/\A[A-Za-z]:\//', $source) !== 1;
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

    private function isStringList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function uniqueSortedStrings(array $strings): array
    {
        $strings = array_values(array_unique(array_filter($strings, static fn (string $string): bool => $string !== '')));
        sort($strings);

        return $strings;
    }

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
