<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MediaBag
{
    /** @var array<string, array{source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, path:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string, sha1:string, byteLength:int}> */
    private array $itemsByCanonicalSource = [];

    /**
     * @return array{contents:string, mimeType:string}
     */
    public static function decodeDataUri(string $uri): array
    {
        if (!preg_match('/\Adata:([^,]*),(.*)\z/s', $uri, $matches)) {
            throw new \InvalidArgumentException('Invalid media bag data URI');
        }

        $metadata = $matches[1];
        $payload = $matches[2];
        $parts = $metadata === '' ? [] : explode(';', $metadata);
        $mimeType = $parts[0] ?? '';
        if ($mimeType === '') {
            $mimeType = 'text/plain';
        }

        $base64 = in_array('base64', array_map('strtolower', $parts), true);
        if ($base64) {
            $payload = explode(';', $payload, 2)[0];
            $decoded = base64_decode(preg_replace('/\s+/', '', $payload) ?? $payload, true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Invalid media bag base64 data URI');
            }

            return ['contents' => $decoded, 'mimeType' => strtolower($mimeType)];
        }

        return ['contents' => rawurldecode($payload), 'mimeType' => strtolower($mimeType)];
    }

    public function insertDataUri(string $uri): void
    {
        $data = self::decodeDataUri($uri);
        $this->insertMedia($uri, $data['mimeType'], $data['contents']);
    }

    public function insertMedia(string $source, ?string $mimeType, string $contents): void
    {
        if ($source === '') {
            throw new \InvalidArgumentException('Media bag source must not be empty');
        }

        $canonicalSource = self::canonicalizeSource($source);
        $decodedSource = rawurldecode($canonicalSource);
        $sourcePath = self::sourcePathForProvenance($source);
        $normalizedSourcePath = self::normalizedSourcePathForProvenance($source, $sourcePath);
        $inferredMimeType = self::mimeTypeFromPath(self::uriPathOrSource($source, $decodedSource));
        $declaredMimeType = $mimeType === null || trim($mimeType) === ''
            ? null
            : strtolower(trim($mimeType));
        $normalizedMimeType = $declaredMimeType ?? $inferredMimeType;
        $mimeTypeSource = $declaredMimeType === null ? 'inferred-path' : 'declared';
        $hashPath = sha1($contents) . self::extensionFor($normalizedMimeType, self::uriPathOrSource($source, $decodedSource));
        $path = str_starts_with($source, 'data:')
            ? $hashPath
            : (self::isSafeRelativeMediaPath($decodedSource) ? $decodedSource : $hashPath);

        $this->itemsByCanonicalSource[$canonicalSource] = [
            'source' => $source,
            'canonicalSource' => $canonicalSource,
            'sourcePath' => $sourcePath,
            'normalizedSourcePath' => $normalizedSourcePath,
            'path' => $path,
            'mimeType' => $normalizedMimeType,
            'mimeTypeSource' => $mimeTypeSource,
            'inferredMimeType' => $inferredMimeType,
            'declaredMimeType' => $declaredMimeType,
            'contents' => $contents,
            'sha1' => sha1($contents),
            'byteLength' => strlen($contents),
        ];
    }

    public function deleteMedia(string $source): void
    {
        unset($this->itemsByCanonicalSource[self::canonicalizeSource($source)]);
    }

    public function has(string $source): bool
    {
        return $this->lookup($source) !== null;
    }

    /**
     * @return array{source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, path:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string, sha1:string, byteLength:int}|null
     */
    public function lookup(string $source): ?array
    {
        return $this->itemsByCanonicalSource[self::canonicalizeSource($source)] ?? null;
    }

    /**
     * @return list<array{path:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string}>
     */
    public function directory(): array
    {
        $items = [];
        foreach ($this->itemsByCanonicalSource as $item) {
            $items[] = [
                'path' => $item['path'],
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $item['source'],
                'canonicalSource' => $item['canonicalSource'],
                'sourcePath' => $item['sourcePath'],
                'normalizedSourcePath' => $item['normalizedSourcePath'],
                'mimeTypeSource' => $item['mimeTypeSource'],
                'inferredMimeType' => $item['inferredMimeType'],
                'declaredMimeType' => $item['declaredMimeType'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $items;
    }

    /**
     * @return list<array{path:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string}>
     */
    public function mediaItems(): array
    {
        $items = [];
        foreach ($this->itemsByCanonicalSource as $item) {
            $items[] = [
                'path' => $item['path'],
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $item['source'],
                'canonicalSource' => $item['canonicalSource'],
                'sourcePath' => $item['sourcePath'],
                'normalizedSourcePath' => $item['normalizedSourcePath'],
                'mimeTypeSource' => $item['mimeTypeSource'],
                'inferredMimeType' => $item['inferredMimeType'],
                'declaredMimeType' => $item['declaredMimeType'],
                'contents' => $item['contents'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $items;
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @return array{document:AstNode, diagnostics:list<string>}
     */
    public function fillDocument(AstNode $document, array $resources): array
    {
        $diagnostics = [];
        $loadedLinkedMimeCounts = [];
        $resourcesByCanonicalSource = self::canonicalResourceMap($resources);
        $document = $this->mapResourceNodes($document, function (AstNode $image) use ($resources, $resourcesByCanonicalSource, &$diagnostics): AstNode {
            $source = (string) $image->attr('url', '');
            if ($source === '' || $this->lookupMediaSource($source) !== null) {
                return $image;
            }

            if (str_starts_with($source, 'data:')) {
                try {
                    $this->insertDataUri($source);
                } catch (\InvalidArgumentException) {
                    $diagnostics[] = 'media-resource-invalid:data-uri';

                    return $this->placeholderFor($image);
                }

                $diagnostics[] = 'media-resource-loaded:data-uri';

                return $image;
            }

            $resource = self::lookupResource($source, $resources, $resourcesByCanonicalSource);
            if ($resource !== null) {
                $contents = is_array($resource)
                    ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
                    : (string) $resource;
                $mimeType = is_array($resource) ? ($resource['mimeType'] ?? null) : null;
                $this->insertMedia($source, is_string($mimeType) ? $mimeType : null, $contents);
                $diagnostics[] = 'media-resource-loaded:' . self::diagnosticSource($source);

                return $image;
            }

            $diagnostics[] = 'media-resource-missing:' . self::diagnosticSource($source);

            return $this->placeholderFor($image);
        }, function (AstNode $link) use ($resources, $resourcesByCanonicalSource, &$diagnostics, &$loadedLinkedMimeCounts): AstNode {
            $source = (string) $link->attr('url', '');
            if ($source === '' || $this->lookupMediaSource($source) !== null) {
                return $link;
            }

            if (str_starts_with($source, 'data:')) {
                try {
                    $this->insertDataUri($source);
                } catch (\InvalidArgumentException) {
                    $diagnostics[] = 'media-resource-link-invalid:data-uri';

                    return $link;
                }

                $diagnostics[] = 'media-resource-link-loaded:data-uri';

                return $link;
            }

            $resource = self::lookupResource($source, $resources, $resourcesByCanonicalSource);
            if ($resource !== null) {
                $contents = is_array($resource)
                    ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
                    : (string) $resource;
                $mimeType = is_array($resource) ? ($resource['mimeType'] ?? null) : null;
                $this->insertMedia($source, is_string($mimeType) ? $mimeType : null, $contents);
                $diagnostics[] = 'media-resource-link-loaded:' . self::diagnosticSource($source);
                $item = $this->lookupMediaSource($source);
                if ($item !== null) {
                    $loadedLinkedMimeCounts[$item['mimeType']] = ($loadedLinkedMimeCounts[$item['mimeType']] ?? 0) + 1;
                    array_push($diagnostics, ...self::linkedResourceLoadDiagnostics($source, $item));
                }

                return $link;
            }

            return $link;
        });
        foreach ($loadedLinkedMimeCounts as $mimeType => $count) {
            if ($count > 1) {
                $diagnostics[] = 'media-resource-link-mime-duplicate:' . $mimeType . ':' . $count;
            }
        }

        return ['document' => $document, 'diagnostics' => $diagnostics];
    }

    /**
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string}>,
     *     diagnostics:list<string>
     * }
     */
    public function extractMedia(AstNode $document, string $destination): array
    {
        $destination = self::normalizeExtractionDestination($destination);
        $entries = [];
        $diagnostics = [];
        $extractionPlan = $this->plannedExtractionPlan();
        $plannedPaths = $extractionPlan['paths'];
        $collisionKinds = $extractionPlan['collisionKinds'];
        foreach ($this->itemsForExtraction() as $item) {
            $mediaPath = $plannedPaths[$item['canonicalSource']] ?? $item['path'];
            $entries[] = [
                'path' => $destination . '/' . $mediaPath,
                'mediaPath' => $mediaPath,
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $item['source'],
                'canonicalSource' => $item['canonicalSource'],
                'sourcePath' => $item['sourcePath'],
                'normalizedSourcePath' => $item['normalizedSourcePath'],
                'mimeTypeSource' => $item['mimeTypeSource'],
                'inferredMimeType' => $item['inferredMimeType'],
                'declaredMimeType' => $item['declaredMimeType'],
                'contents' => $item['contents'],
            ];
            if ($mediaPath !== $item['path']) {
                foreach ($collisionKinds[$item['canonicalSource']] ?? ['path'] as $collisionKind) {
                    $diagnostics[] = match ($collisionKind) {
                        'casefold' => 'media-resource-casefold-path-collision:' . self::diagnosticSource($item['source']),
                        default => 'media-resource-path-collision:' . self::diagnosticSource($item['source']),
                    };
                }
            }
        }

        usort($entries, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        $document = $this->mapResourceNodes($document, function (AstNode $image) use ($destination, $plannedPaths, &$diagnostics): AstNode {
            $source = (string) $image->attr('url', '');
            $item = $this->lookupMediaSource($source);
            if ($item === null) {
                return $image;
            }

            $attrs = $image->attrs;
            $mediaPath = $plannedPaths[$item['canonicalSource']] ?? $item['path'];
            $mappedUrl = $destination . '/' . $mediaPath;
            $attrs['url'] = $mappedUrl;
            $attrs['attributes'] = $this->mediaProvenanceAttributes($attrs, $item, $mediaPath, $mappedUrl);
            $diagnostics[] = 'media-resource-mapped:' . self::diagnosticSource($source);

            return new AstNode($image->type, $attrs, $image->children);
        }, function (AstNode $link) use ($destination, $plannedPaths, &$diagnostics): AstNode {
            $source = (string) $link->attr('url', '');
            $item = $this->lookupMediaSource($source);
            if ($item === null) {
                return $link;
            }

            $attrs = $link->attrs;
            $mediaPath = $plannedPaths[$item['canonicalSource']] ?? $item['path'];
            $mappedUrl = $destination . '/' . $mediaPath;
            $attrs['url'] = $mappedUrl;
            $attrs['attributes'] = $this->mediaProvenanceAttributes($attrs, $item, $mediaPath, $mappedUrl);
            $diagnostics[] = 'media-resource-link-mapped:' . self::diagnosticSource($source);

            return new AstNode($link->type, $attrs, $link->children);
        });

        return [
            'document' => $document,
            'entries' => $entries,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, path:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string, sha1:string, byteLength:int} $item
     * @return array<string, string>
     */
    private function mediaProvenanceAttributes(array $attrs, array $item, string $mediaPath, string $mappedUrl): array
    {
        $attributes = [];
        $existingAttributes = $attrs['attributes'] ?? [];
        if (is_array($existingAttributes)) {
            foreach ($existingAttributes as $name => $value) {
                if (!is_string($name)) {
                    continue;
                }

                $value = (string) $value;
                if ($value === '') {
                    continue;
                }

                $attributes[$name] = $value;
            }
        }

        return array_replace($attributes, [
            'data-pandoc-media-source' => $item['source'],
            'data-pandoc-media-canonical-source' => $item['canonicalSource'],
            'data-pandoc-media-source-path' => $item['sourcePath'],
            'data-pandoc-media-normalized-source-path' => $item['normalizedSourcePath'],
            'data-pandoc-media-path' => $mediaPath,
            'data-pandoc-media-target' => $mappedUrl,
            'data-pandoc-media-type' => $item['mimeType'],
            'data-pandoc-media-type-source' => $item['mimeTypeSource'],
            'data-pandoc-media-inferred-type' => $item['inferredMimeType'],
            'data-pandoc-media-bytes' => (string) $item['byteLength'],
            'data-pandoc-media-sha1' => $item['sha1'],
        ]);
    }

    /**
     * @return list<array{source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, path:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string, sha1:string, byteLength:int}>
     */
    private function itemsForExtraction(): array
    {
        $items = array_values($this->itemsByCanonicalSource);
        usort($items, static function (array $a, array $b): int {
            $path = $a['path'] <=> $b['path'];
            if ($path !== 0) {
                return $path;
            }

            $aLiteral = $a['canonicalSource'] === $a['path'];
            $bLiteral = $b['canonicalSource'] === $b['path'];
            if ($aLiteral !== $bLiteral) {
                return $aLiteral ? -1 : 1;
            }

            return $a['canonicalSource'] <=> $b['canonicalSource'];
        });

        return $items;
    }

    /**
     * @return array{paths:array<string, string>, collisionKinds:array<string, list<string>>}
     */
    private function plannedExtractionPlan(): array
    {
        $paths = [];
        $collisionKinds = [];
        $used = [];
        $usedCaseFolded = [];
        foreach ($this->itemsForExtraction() as $item) {
            $path = $item['path'];
            $itemCollisionKinds = [];
            if (isset($used[$path]) && $used[$path] !== $item['sha1']) {
                $path = self::disambiguateMediaPath($path, $item['sha1'], $item['canonicalSource'], $used);
                $itemCollisionKinds[] = 'path';
            }
            $caseFoldedPath = self::caseFoldPath($path);
            if (isset($usedCaseFolded[$caseFoldedPath]) && $usedCaseFolded[$caseFoldedPath] !== $path) {
                $path = self::disambiguateCaseFoldedMediaPath($path, $item['sha1'], $item['canonicalSource'], $used, $usedCaseFolded);
                $itemCollisionKinds[] = 'casefold';
            }

            $paths[$item['canonicalSource']] = $path;
            if ($itemCollisionKinds !== []) {
                $collisionKinds[$item['canonicalSource']] = $itemCollisionKinds;
            }
            $used[$path] = $item['sha1'];
            $usedCaseFolded[self::caseFoldPath($path)] = $path;
        }

        return ['paths' => $paths, 'collisionKinds' => $collisionKinds];
    }

    private static function canonicalizeSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return $source;
        }

        $pathSource = str_replace('\\', '/', $source);
        if (self::isWindowsDrivePath($pathSource)) {
            return self::normalizePath($pathSource);
        }

        if (self::isUri($source)) {
            return $source;
        }

        return self::normalizePath($pathSource);
    }

    private static function normalizePath(string $path): string
    {
        $prefix = '';
        if (preg_match('/\A([A-Za-z]:)(\/?)(.*)\z/', $path, $matches)) {
            $prefix = $matches[1] . '/';
            $path = $matches[3];
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        if ($prefix !== '') {
            return $prefix . $normalized;
        }

        return $normalized === '' ? '.' : $normalized;
    }

    private static function isUri(string $source): bool
    {
        return !self::isWindowsDrivePath(str_replace('\\', '/', $source))
            && preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1;
    }

    private static function isWindowsDrivePath(string $source): bool
    {
        return preg_match('/\A[A-Za-z]:\//', $source) === 1;
    }

    private static function uriPathOrSource(string $source, string $decodedSource): string
    {
        if (!self::isUri($source)) {
            return $decodedSource;
        }

        $path = parse_url($source, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rawurldecode($path) : $decodedSource;
    }

    private static function sourcePathForProvenance(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri';
        }

        return self::pathOnlyRelativeSource($source);
    }

    private static function normalizedSourcePathForProvenance(string $source, string $sourcePath): string
    {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri';
        }

        return rawurldecode($sourcePath);
    }

    private static function isSafeRelativeMediaPath(string $path): bool
    {
        return $path !== ''
            && $path !== '.'
            && !str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && preg_match('/\A[A-Za-z]:[\/\\\\]/', $path) !== 1
            && !str_contains($path, '..')
            && !str_contains($path, '%')
            && !str_contains($path, '?')
            && !str_contains($path, '#')
            && !self::isUri($path);
    }

    private static function mimeTypeFromPath(string $path): string
    {
        if (str_ends_with(strtolower($path), '.gz')) {
            $path = substr($path, 0, -3);
        }

        return match (strtolower(self::pathExtension($path))) {
            '.apng' => 'image/apng',
            '.avif' => 'image/avif',
            '.gif' => 'image/gif',
            '.jpeg', '.jpg', '.jpe' => 'image/jpeg',
            '.png' => 'image/png',
            '.svg', '.svgz' => 'image/svg+xml',
            '.webp' => 'image/webp',
            '.bmp' => 'image/bmp',
            '.ico' => 'image/x-icon',
            '.tif', '.tiff' => 'image/tiff',
            '.css' => 'text/css',
            '.js', '.mjs' => 'text/javascript',
            '.json', '.map', '.webmanifest' => 'application/json',
            '.html', '.htm' => 'text/html',
            '.xhtml' => 'application/xhtml+xml',
            '.xml' => 'application/xml',
            '.mp3' => 'audio/mpeg',
            '.m4a' => 'audio/mp4',
            '.ogg', '.oga' => 'audio/ogg',
            '.wav' => 'audio/wav',
            '.flac' => 'audio/flac',
            '.mp4', '.m4v' => 'video/mp4',
            '.webm' => 'video/webm',
            '.ogv' => 'video/ogg',
            '.woff' => 'font/woff',
            '.woff2' => 'font/woff2',
            '.ttf' => 'font/ttf',
            '.otf' => 'font/otf',
            '.pdf' => 'application/pdf',
            '.epub' => 'application/epub+zip',
            '.txt', '.text' => 'text/plain',
            '.md', '.markdown' => 'text/markdown',
            '.csv' => 'text/csv',
            '.tsv' => 'text/tab-separated-values',
            default => 'application/octet-stream',
        };
    }

    private static function extensionFor(string $mimeType, string $path): string
    {
        $mimeExtension = match (strtolower($mimeType)) {
            'image/apng' => '.apng',
            'image/avif' => '.avif',
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/svg+xml' => '.svg',
            'image/webp' => '.webp',
            'image/bmp' => '.bmp',
            'image/x-icon', 'image/vnd.microsoft.icon' => '.ico',
            'image/tiff' => '.tiff',
            'text/css' => '.css',
            'text/javascript', 'application/javascript', 'application/ecmascript' => '.js',
            'application/json' => '.json',
            'text/html' => '.html',
            'application/xhtml+xml' => '.xhtml',
            'application/xml', 'text/xml' => '.xml',
            'audio/mpeg' => '.mp3',
            'audio/mp4' => '.m4a',
            'audio/ogg' => '.ogg',
            'audio/wav', 'audio/wave', 'audio/x-wav' => '.wav',
            'audio/flac' => '.flac',
            'video/mp4' => '.mp4',
            'video/webm' => '.webm',
            'video/ogg' => '.ogv',
            'font/woff', 'application/font-woff' => '.woff',
            'font/woff2' => '.woff2',
            'font/ttf', 'application/font-sfnt' => '.ttf',
            'font/otf', 'application/vnd.ms-opentype' => '.otf',
            'application/pdf' => '.pdf',
            'application/epub+zip' => '.epub',
            'text/plain' => '.txt',
            'text/markdown' => '.md',
            'text/csv' => '.csv',
            'text/tab-separated-values' => '.tsv',
            default => '',
        };
        if ($mimeExtension !== '') {
            return $mimeExtension;
        }

        $extension = self::pathExtension($path);

        return str_contains($extension, '%') ? '' : $extension;
    }

    /**
     * @param array<string, string> $usedPaths
     */
    private static function disambiguateMediaPath(string $path, string $sha1, string $canonicalSource, array $usedPaths): string
    {
        $extension = self::pathExtension($path);
        $stem = $extension === '' ? $path : substr($path, 0, -strlen($extension));
        $seed = $canonicalSource . "\0" . $sha1;

        do {
            $suffix = substr(sha1($seed), 0, 12);
            $candidate = $stem . '-' . $suffix . $extension;
            $seed = $candidate . "\0" . $seed;
        } while (isset($usedPaths[$candidate]) && $usedPaths[$candidate] !== $sha1);

        return $candidate;
    }

    /**
     * @param array<string, string> $usedPaths
     * @param array<string, string> $usedCaseFoldedPaths
     */
    private static function disambiguateCaseFoldedMediaPath(
        string $path,
        string $sha1,
        string $canonicalSource,
        array $usedPaths,
        array $usedCaseFoldedPaths
    ): string {
        $extension = self::pathExtension($path);
        $stem = $extension === '' ? $path : substr($path, 0, -strlen($extension));
        $seed = $canonicalSource . "\0" . $sha1;

        do {
            $suffix = substr(sha1($seed), 0, 12);
            $candidate = $stem . '-' . $suffix . $extension;
            $seed = $candidate . "\0" . $seed;
        } while (
            (isset($usedPaths[$candidate]) && $usedPaths[$candidate] !== $sha1)
            || isset($usedCaseFoldedPaths[self::caseFoldPath($candidate)])
        );

        return $candidate;
    }

    private static function caseFoldPath(string $path): string
    {
        return strtolower($path);
    }

    private static function pathExtension(string $path): string
    {
        $path = strtok($path, "?#");
        $basename = basename($path === false ? '' : $path);
        $position = strrpos($basename, '.');
        if ($position === false || $position === 0) {
            return '';
        }

        return substr($basename, $position);
    }

    private static function normalizeExtractionDestination(string $destination): string
    {
        $destination = rtrim(preg_replace('#/+#', '/', trim(str_replace('\\', '/', $destination))) ?? '', '/');
        if ($destination === '' || !self::isSafeRelativeMediaPath($destination)) {
            throw new \InvalidArgumentException('Media extraction destination must be a safe relative path');
        }

        return self::normalizePath($destination);
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @return array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>
     */
    private static function canonicalResourceMap(array $resources): array
    {
        $canonical = [];
        foreach ($resources as $source => $resource) {
            if (!is_string($source)) {
                continue;
            }

            $canonical[self::canonicalizeSource($source)] = $resource;
        }

        return $canonical;
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resourcesByCanonicalSource
     * @return string|array{contents?:string, data?:string, mimeType?:string|null}|null
     */
    private static function lookupResource(string $source, array $resources, array $resourcesByCanonicalSource): string|array|null
    {
        foreach (self::resourceLookupKeys($source) as $key) {
            if (array_key_exists($key, $resources)) {
                return $resources[$key];
            }

            $canonicalKey = self::canonicalizeSource($key);
            if (array_key_exists($canonicalKey, $resources)) {
                return $resources[$canonicalKey];
            }
            if (array_key_exists($canonicalKey, $resourcesByCanonicalSource)) {
                return $resourcesByCanonicalSource[$canonicalKey];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function resourceLookupKeys(string $source): array
    {
        $keys = [$source, self::canonicalizeSource($source)];
        $pathOnlySource = self::pathOnlyRelativeSource($source);
        if ($pathOnlySource !== $source) {
            $keys[] = $pathOnlySource;
            $keys[] = self::canonicalizeSource($pathOnlySource);
        }
        $decodedPathOnlySource = self::decodedRelativeSourceKey($pathOnlySource);
        if ($decodedPathOnlySource !== null) {
            $keys[] = $decodedPathOnlySource;
            $keys[] = self::canonicalizeSource($decodedPathOnlySource);
        }

        return array_values(array_unique($keys));
    }

    private static function pathOnlyRelativeSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return $source;
        }

        $queryPosition = strpos($source, '?');
        $fragmentPosition = strpos($source, '#');
        $positions = array_filter(
            [$queryPosition, $fragmentPosition],
            static fn (int|false $position): bool => $position !== false
        );
        if ($positions === []) {
            return $source;
        }

        return substr($source, 0, min($positions));
    }

    /**
     * @return array{source:string, canonicalSource:string, path:string, mimeType:string, contents:string, sha1:string, byteLength:int}|null
     */
    private function lookupMediaSource(string $source): ?array
    {
        foreach (self::resourceLookupKeys($source) as $key) {
            $item = $this->lookup($key);
            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    private static function decodedRelativeSourceKey(string $source): ?string
    {
        if (str_starts_with($source, 'data:') || self::isUri($source) || !str_contains($source, '%')) {
            return null;
        }

        $decoded = rawurldecode($source);
        if (
            $decoded === $source
            || str_contains($decoded, "\0")
            || !self::isSafeRelativeMediaPath($decoded)
        ) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array{source:string, canonicalSource:string, sourcePath:string, normalizedSourcePath:string, path:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, declaredMimeType:?string, contents:string, sha1:string, byteLength:int} $item
     * @return list<string>
     */
    private static function linkedResourceLoadDiagnostics(string $source, array $item): array
    {
        if (str_starts_with($source, 'data:')) {
            return [];
        }

        $diagnostics = [];
        if ($item['sourcePath'] !== $item['normalizedSourcePath']) {
            $diagnostics[] = 'media-resource-link-source-normalized:'
                . self::diagnosticSource($source)
                . ':'
                . $item['normalizedSourcePath'];
        }

        if (
            $item['declaredMimeType'] !== null
            && $item['inferredMimeType'] !== 'application/octet-stream'
            && $item['declaredMimeType'] !== $item['inferredMimeType']
        ) {
            $diagnostics[] = 'media-resource-link-mime-disagreement:'
                . self::diagnosticSource($source)
                . ':extension='
                . $item['inferredMimeType']
                . ':content-type='
                . $item['declaredMimeType'];
        }

        return $diagnostics;
    }

    private function placeholderFor(AstNode $image): AstNode
    {
        $attrs = $image->attrs;
        $attributes = $attrs['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }

        $attrs['classes'] = array_values(array_unique(array_merge(['image', 'placeholder'], $classes)));
        $attrs['attributes'] = array_merge([
            'original-image-src' => (string) $image->attr('url', ''),
            'original-image-title' => (string) $image->attr('title', ''),
        ], $attributes);

        return new AstNode('span', $attrs, $image->children);
    }

    /**
     * @param callable(AstNode): AstNode $mapImage
     * @param callable(AstNode): AstNode $mapLink
     */
    private function mapResourceNodes(AstNode $node, callable $mapImage, callable $mapLink): AstNode
    {
        $children = array_map(fn (AstNode $child): AstNode => $this->mapResourceNodes($child, $mapImage, $mapLink), $node->children);
        $mapped = new AstNode($node->type, $node->attrs, $children);

        return match ($mapped->type) {
            'image' => $mapImage($mapped),
            'link' => $mapLink($mapped),
            default => $mapped,
        };
    }

    private static function diagnosticSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri';
        }

        return $source;
    }
}
