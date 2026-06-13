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
        $attachmentMedia = [];
        $attachmentMediaDiagnostics = [];
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
            $attachmentSummary = $this->attachmentSummary($attachments, $index);
            $outputSummary = $this->outputSummary($outputs);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['count'];
            $attachmentMedia = array_merge($attachmentMedia, $attachmentSummary['media']);
            $attachmentMediaDiagnostics = array_merge($attachmentMediaDiagnostics, $attachmentSummary['diagnostics']);

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
            if ($attachmentSummary['media'] !== []) {
                $attributes['data-ipynb-attachment-media-count'] = (string) count($attachmentSummary['media']);
            }
            if ($attachmentSummary['diagnostics'] !== []) {
                $attributes['data-ipynb-attachment-diagnostics'] = implode(' ', $attachmentSummary['diagnostics']);
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
                'ipynbAttachmentMedia' => $attachmentSummary['media'],
                'ipynbAttachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
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
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'attachmentMedia' => $attachmentSummary['media'],
                'attachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
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
            'notebookAttachmentMediaCount' => count($attachmentMedia),
            'notebookAttachmentMedia' => $attachmentMedia,
            'notebookAttachmentMediaDiagnostics' => $this->uniqueSortedStrings($attachmentMediaDiagnostics),
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
     *     diagnostics:list<string>
     * }
     */
    private function attachmentSummary(array $attachments, int $cellIndex): array
    {
        $names = [];
        $mimeTypes = [];
        $media = [];
        $diagnostics = [];
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
     * @param array{count:int, names:list<string>, mimeTypes:list<string>, media:list<array<string, mixed>>, diagnostics:list<string>} $attachmentSummary
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
