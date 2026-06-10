<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MediaBag
{
    /** @var array<string, array{source:string, canonicalSource:string, path:string, mimeType:string, contents:string, sha1:string, byteLength:int}> */
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
        $normalizedMimeType = $mimeType === null || trim($mimeType) === ''
            ? self::mimeTypeFromPath($decodedSource)
            : strtolower(trim($mimeType));
        $hashPath = sha1($contents) . self::extensionFor($normalizedMimeType, self::uriPathOrSource($source, $decodedSource));
        $path = str_starts_with($source, 'data:')
            ? $hashPath
            : (self::isSafeRelativeMediaPath($decodedSource) ? $decodedSource : $hashPath);

        $this->itemsByCanonicalSource[$canonicalSource] = [
            'source' => $source,
            'canonicalSource' => $canonicalSource,
            'path' => $path,
            'mimeType' => $normalizedMimeType,
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
     * @return array{source:string, canonicalSource:string, path:string, mimeType:string, contents:string, sha1:string, byteLength:int}|null
     */
    public function lookup(string $source): ?array
    {
        return $this->itemsByCanonicalSource[self::canonicalizeSource($source)] ?? null;
    }

    /**
     * @return list<array{path:string, mimeType:string, byteLength:int, sha1:string, source:string}>
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
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $items;
    }

    /**
     * @return list<array{path:string, mimeType:string, byteLength:int, sha1:string, source:string, contents:string}>
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
        $resourcesByCanonicalSource = self::canonicalResourceMap($resources);
        $document = $this->mapImages($document, function (AstNode $image) use ($resources, $resourcesByCanonicalSource, &$diagnostics): AstNode {
            $source = (string) $image->attr('url', '');
            if ($source === '' || $this->lookupImageSource($source) !== null) {
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
        });

        return ['document' => $document, 'diagnostics' => $diagnostics];
    }

    /**
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, contents:string}>,
     *     diagnostics:list<string>
     * }
     */
    public function extractMedia(AstNode $document, string $destination): array
    {
        $destination = self::normalizeExtractionDestination($destination);
        $entries = [];
        $diagnostics = [];
        $plannedPaths = $this->plannedExtractionPaths();
        foreach ($this->itemsForExtraction() as $item) {
            $mediaPath = $plannedPaths[$item['canonicalSource']] ?? $item['path'];
            $entries[] = [
                'path' => $destination . '/' . $mediaPath,
                'mediaPath' => $mediaPath,
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $item['source'],
                'contents' => $item['contents'],
            ];
            if ($mediaPath !== $item['path']) {
                $diagnostics[] = 'media-resource-path-collision:' . self::diagnosticSource($item['source']);
            }
        }

        usort($entries, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        $document = $this->mapImages($document, function (AstNode $image) use ($destination, $plannedPaths, &$diagnostics): AstNode {
            $source = (string) $image->attr('url', '');
            $item = $this->lookupImageSource($source);
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
        });

        return [
            'document' => $document,
            'entries' => $entries,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{source:string, canonicalSource:string, path:string, mimeType:string, contents:string, sha1:string, byteLength:int} $item
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
            'data-pandoc-media-path' => $mediaPath,
            'data-pandoc-media-target' => $mappedUrl,
            'data-pandoc-media-type' => $item['mimeType'],
            'data-pandoc-media-bytes' => (string) $item['byteLength'],
            'data-pandoc-media-sha1' => $item['sha1'],
        ]);
    }

    /**
     * @return list<array{source:string, canonicalSource:string, path:string, mimeType:string, contents:string, sha1:string, byteLength:int}>
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
     * @return array<string, string>
     */
    private function plannedExtractionPaths(): array
    {
        $paths = [];
        $used = [];
        foreach ($this->itemsForExtraction() as $item) {
            $path = $item['path'];
            if (isset($used[$path]) && $used[$path] !== $item['sha1']) {
                $path = self::disambiguateMediaPath($path, $item['sha1'], $item['canonicalSource'], $used);
            }

            $paths[$item['canonicalSource']] = $path;
            $used[$path] = $item['sha1'];
        }

        return $paths;
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
            '.pdf' => 'application/pdf',
            '.txt', '.text' => 'text/plain',
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
            'application/pdf' => '.pdf',
            'text/plain' => '.txt',
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
        $destination = trim(str_replace('\\', '/', $destination));
        if ($destination === '') {
            throw new \InvalidArgumentException('Media extraction destination must not be empty');
        }

        return rtrim($destination, '/');
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
    private function lookupImageSource(string $source): ?array
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
     */
    private function mapImages(AstNode $node, callable $mapImage): AstNode
    {
        $children = array_map(fn (AstNode $child): AstNode => $this->mapImages($child, $mapImage), $node->children);
        $mapped = new AstNode($node->type, $node->attrs, $children);

        return $mapped->type === 'image' ? $mapImage($mapped) : $mapped;
    }

    private static function diagnosticSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri';
        }

        return $source;
    }
}
