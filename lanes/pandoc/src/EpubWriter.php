<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubWriter
{
    private bool $preferMetadataOverOptions = false;

    /**
     * @param array{
     *     writerFormat?: string,
     *     format?: string,
     *     epubVersion?: string,
     *     identifier?: string,
     *     language?: string,
     *     modified?: string,
     *     splitLevel?: int,
     *     pageProgressionDirection?: string,
     *     renditionLayout?: string,
     *     renditionOrientation?: string,
     *     renditionSpread?: string,
     *     renditionFlow?: string,
     *     mediaDuration?: string,
     *     mediaNarrator?: string,
     *     mediaActiveClass?: string,
     *     mediaPlaybackActiveClass?: string,
     *     viewport?: array<string, mixed>|string,
     *     renditionViewport?: array<string, mixed>|string,
     *     epubRenditionViewport?: array<string, mixed>|string,
     *     viewports?: array<mixed>,
     *     chapterPath?: string,
     *     navPath?: string,
     *     packagePath?: string,
     *     coverImage?: string,
     *     resources?: array<string, string>,
     *     resourceMediaTypes?: array<string, string>,
     *     htmlQTags?: bool,
     *     alternateRootfiles?: array<mixed>,
     *     epubAlternateRootfiles?: array<mixed>,
     *     epubAlternateRootfilePackages?: array<mixed>,
     *     containerRootfilePayloads?: array<string, mixed>,
     *     spineItemProperties?: array<mixed>,
     *     spineItemIds?: array<mixed>,
     *     nonLinearSpineItems?: array<mixed>,
     *     mediaOverlays?: array<mixed>
     * } $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('EPUB writer expects a document node');
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('EPUB writing needs PHP ZipArchive, which is unavailable in this runtime.');
        }

        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-write-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary EPUB path.');
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create temporary EPUB package.');
            }

            try {
                $this->writePackage($zip, $document);
            } finally {
                $zip->close();
            }

            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException('Unable to read temporary EPUB package.');
            }

            return $bytes;
        } finally {
            @unlink($path);
        }
    }

    private function writePackage(\ZipArchive $zip, AstNode $document): void
    {
        $packagePath = $this->safePackagePath((string) ($this->options['packagePath'] ?? 'OEBPS/package.opf'));
        $packageDir = $this->dirname($packagePath);
        $chapterPath = $this->safePackagePath((string) ($this->options['chapterPath'] ?? $packageDir . '/text/chapter.xhtml'));
        $meta = $this->metadata($document);
        $includeNav = $this->includeEpub3Nav($meta);
        $navPath = $includeNav ? $this->safePackagePath((string) ($this->options['navPath'] ?? $packageDir . '/nav.xhtml')) : '';
        $ncxId = $this->ncxManifestId($meta);
        $ncxPath = $ncxId === '' ? '' : $this->ncxPath($meta, $packageDir);
        $navHref = $navPath === '' ? '' : $this->relativePath($packageDir, $navPath);
        $resources = $this->resources($meta, $packageDir);
        $ocfSidecarPayloads = $this->ocfSidecarPayloads($meta);
        $document = $this->documentWithHeadingIds($document);
        $containerRootfilePayloads = $this->containerRootfilePayloads($meta, $document);
        $chapters = $this->chapterDocuments($document, $chapterPath, $packageDir);
        $navDir = $navPath === '' ? '' : $this->dirname($navPath);
        $ncxDir = $ncxPath === '' ? '' : $this->dirname($ncxPath);
        $ncxHref = $ncxPath === '' ? '' : $this->relativePath($packageDir, $ncxPath);

        $this->addStoredFile($zip, 'mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', $this->containerXml($packagePath, $meta, $containerRootfilePayloads, array_keys($ocfSidecarPayloads)));
        foreach ($ocfSidecarPayloads as $path => $bytes) {
            $zip->addFromString($path, $bytes);
        }
        $containerRootfilePaths = [];
        foreach ($containerRootfilePayloads as $path => $payload) {
            $containerRootfilePaths[$path] = true;
            $bytes = $payload['payloadBytes'] ?? null;
            if (!is_string($bytes) || $path === $packagePath || $path === 'mimetype' || $path === 'META-INF/container.xml' || isset($ocfSidecarPayloads[$path]) || $this->isOcfSidecarPath($path)) {
                continue;
            }
            $zip->addFromString($path, $bytes);
            $packagePayloads = $payload['packagePayloads'] ?? [];
            if (!is_array($packagePayloads)) {
                continue;
            }
            foreach ($packagePayloads as $payloadPath => $payloadBytes) {
                if (!is_string($payloadPath) || !is_string($payloadBytes)) {
                    continue;
                }
                $payloadPath = $this->safePackagePath($payloadPath);
                if ($payloadPath === '' || $payloadPath === $packagePath || $payloadPath === $path || $payloadPath === 'mimetype' || $payloadPath === 'META-INF/container.xml' || isset($ocfSidecarPayloads[$payloadPath]) || $this->isOcfSidecarPath($payloadPath)) {
                    continue;
                }
                $containerRootfilePaths[$payloadPath] = true;
                $zip->addFromString($payloadPath, $payloadBytes);
            }
        }
        foreach ($chapters as $index => $chapter) {
            $zip->addFromString($chapter['path'], $this->chapterXhtml($chapter['document'], $resources, $packageDir, $this->dirname($chapter['path']), $index));
        }
        $chapterPaths = array_fill_keys(array_map(static fn (array $chapter): string => $chapter['path'], $chapters), true);
        foreach ($resources as $path => $bytes) {
            if (isset($chapterPaths[$path]) || isset($containerRootfilePaths[$path]) || isset($ocfSidecarPayloads[$path]) || $path === $navPath || $path === $ncxPath || $path === $packagePath || $path === 'mimetype' || $path === 'META-INF/container.xml') {
                continue;
            }
            $zip->addFromString($path, $bytes);
        }
        if ($navPath !== '') {
            $zip->addFromString($navPath, $this->navXhtml($document, $chapters, $navDir, $packageDir, $resources, $navPath));
        }
        if ($ncxPath !== '') {
            $zip->addFromString($ncxPath, $this->ncxXml($document, $chapters, $ncxDir, $packageDir, $resources));
        }
        $zip->addFromString($packagePath, $this->packageOpf($document, $chapters, $navHref, $resources, $packageDir, $packagePath, $ncxId, $ncxHref));
    }

    private function writerFormat(): string
    {
        $format = $this->normalizedWriterFormat($this->options['writerFormat'] ?? $this->options['format'] ?? null);
        if ($format !== '') {
            return $format;
        }

        $version = $this->epubMajorVersion($this->options['epubVersion'] ?? null);
        return $version === 2 ? 'epub2' : 'epub3';
    }

    private function normalizedWriterFormat(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $format = strtolower(str_replace('-', '_', trim((string) $value)));
        return match ($format) {
            'epub2' => 'epub2',
            'epub', 'epub3' => 'epub3',
            default => '',
        };
    }

    private function epubMajorVersion(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^([0-9]+)(?:\.[0-9]+)?$/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function isEpub2(array $meta = []): bool
    {
        return $this->writerFormat() === 'epub2';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function includeEpub3Nav(array $meta): bool
    {
        return !$this->isEpub2($meta);
    }

    private function addStoredFile(\ZipArchive $zip, string $path, string $bytes): void
    {
        $zip->addFromString($path, $bytes);
        if (method_exists($zip, 'setCompressionName')) {
            $zip->setCompressionName($path, \ZipArchive::CM_STORE);
        }
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, array<string, mixed>> $rootfilePayloads
     * @param list<string> $ocfSidecarPaths
     */
    private function containerXml(string $packagePath, array $meta = [], array $rootfilePayloads = [], array $ocfSidecarPaths = []): string
    {
        $links = $this->containerLinksXml($meta, $ocfSidecarPaths);
        $version = $this->containerVersion($meta);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="' . $this->esc($version) . '">' . "\n"
            . $this->containerRootfilesXml($packagePath, $meta, $rootfilePayloads)
            . $links
            . '</container>' . "\n";
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function containerVersion(array $meta): string
    {
        return '1.0';
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, array<string, mixed>> $rootfilePayloads
     */
    private function containerRootfilesXml(string $packagePath, array $meta, array $rootfilePayloads): string
    {
        $primary = $this->selectedContainerRootfileMetadata($meta);
        $primary['path'] = $packagePath;
        $primary['mediaType'] = $this->entryString($primary, 'mediaType') ?: 'application/oebps-package+xml';
        $seenIds = [];
        $lines = [$this->containerRootfileXml($primary, [], $seenIds)];

        foreach ($rootfilePayloads as $path => $payload) {
            if ($path === $packagePath || !is_array($payload)) {
                continue;
            }
            $entry = $payload;
            $entry['path'] = $path;
            $entry['mediaType'] = $this->entryString($entry, 'mediaType') ?: 'application/oebps-package+xml';
            $line = $this->containerRootfileXml($entry, $rootfilePayloads, $seenIds);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return '  <rootfiles>' . "\n"
            . implode("\n", array_values(array_unique(array_filter($lines)))) . "\n"
            . '  </rootfiles>' . "\n";
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function selectedContainerRootfileMetadata(array $meta): array
    {
        $rootfiles = $meta['epubContainerRootfiles'] ?? [];
        if (!is_array($rootfiles)) {
            return [];
        }

        foreach ($rootfiles as $rootfile) {
            if (is_array($rootfile) && ($rootfile['selected'] ?? false) === true) {
                return $rootfile;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $rootfile
     */
    /**
     * @param array<string, mixed> $rootfilePayloads
     * @param array<string, bool> $seenIds
     */
    private function containerRootfileXml(array $rootfile, array $rootfilePayloads = [], array &$seenIds = []): string
    {
        $sourcePath = trim((string) ($rootfile['path'] ?? $rootfile['fullPath'] ?? ''));
        if (!$this->validContainerRootfileSourcePath($sourcePath)) {
            return '';
        }
        $path = $this->safePackagePath($sourcePath);

        $mediaType = $this->entryString($rootfile, 'mediaType') ?: $this->entryString($rootfile, 'media-type') ?: 'application/oebps-package+xml';
        if (!$this->validMediaType($mediaType) || !$this->mediaTypeMatches($mediaType, 'application/oebps-package+xml')) {
            $mediaType = 'application/oebps-package+xml';
        }
        if ($rootfilePayloads !== [] && !isset($rootfilePayloads[$path])) {
            return '';
        }
        $sourceId = $this->entryString($rootfile, 'id');
        $id = $this->validXmlId($sourceId) ? $sourceId : '';
        if ($id !== '' && isset($seenIds[$id])) {
            $id = '';
        }
        if ($id !== '') {
            $seenIds[$id] = true;
        }
        $attributes = [
            'full-path' => $path,
            'media-type' => $mediaType,
            'id' => $id,
        ];
        $properties = $this->containerLinkTokenList($rootfile['properties'] ?? []);
        if ($properties !== '') {
            $attributes['properties'] = $properties;
        }

        $xmlAttributes = '';
        foreach ($attributes as $name => $value) {
            if ($value === '') {
                continue;
            }
            $xmlAttributes .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return '    <rootfile' . $xmlAttributes . '/>';
    }

    private function validContainerRootfileSourcePath(string $path): bool
    {
        return $this->containerRootfileFullPathDiagnosticReason($path) === '';
    }

    private function containerRootfileFullPathDiagnosticReason(string $fullPath): string
    {
        $fullPath = trim($fullPath);
        if ($fullPath === '') {
            return 'missing';
        }

        $lowerPath = strtolower($fullPath);
        if (str_starts_with($lowerPath, 'data:')) {
            return 'data-url';
        }
        if (str_starts_with($lowerPath, 'file:')) {
            return 'file-url';
        }
        if ($this->isAbsoluteUrl($fullPath)) {
            return 'absolute-url';
        }
        if (str_contains($fullPath, '\\')) {
            return 'backslash';
        }

        if (str_starts_with($fullPath, '/')) {
            return 'absolute-path';
        }
        if (strpbrk($fullPath, '?#') !== false) {
            return 'url-suffix';
        }
        foreach (explode('/', $fullPath) as $part) {
            if ($part === '..') {
                return 'traversal';
            }
        }
        $encodedDotSegmentReason = $this->encodedDotSegmentPathDiagnosticReason($fullPath);
        if ($encodedDotSegmentReason !== '') {
            return $encodedDotSegmentReason;
        }
        if ($this->safePackagePath($fullPath) === '') {
            return 'empty-normalized-path';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $meta
     */
    /**
     * @param array<string, mixed> $meta
     * @param list<string> $ocfSidecarPaths
     */
    private function containerLinksXml(array $meta, array $ocfSidecarPaths = []): string
    {
        $links = $meta['epubContainerLinks'] ?? $this->options['containerLinks'] ?? [];
        if (!is_array($links) || $links === []) {
            return '';
        }

        $sidecarPaths = array_fill_keys($ocfSidecarPaths, true);
        $lines = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $href = $this->entryString($link, 'href');
            $sourceHref = $href;
            if (!$this->validContainerLinkSourceHref($sourceHref)) {
                continue;
            }
            $href = $this->containerLinkHref($href);
            if ($href === '') {
                continue;
            }
            $zipPath = $this->containerLinkZipPath($href);
            if ($zipPath !== '' && !isset($sidecarPaths[$zipPath])) {
                continue;
            }
            $rel = $this->containerLinkTokenList($link['rel'] ?? '');
            if ($rel === '') {
                continue;
            }
            $sourceId = $this->entryString($link, 'id');
            $id = $this->validXmlId($sourceId) ? $sourceId : '';
            if ($sourceId !== '' && $id === '') {
                continue;
            }
            $sourceRefines = $this->entryString($link, 'refines');
            $refines = $this->validMetadataRefinesValue($sourceRefines);
            if ($sourceRefines !== '' && $refines === '') {
                continue;
            }
            $mediaType = $this->containerLinkMediaType($link, $href);
            if ($mediaType !== '' && !$this->validMediaType($mediaType)) {
                $mediaType = '';
            }
            $hreflang = $this->entryString($link, 'hreflang') ?: $this->entryString($link, 'hrefLang');
            if ($hreflang !== '' && !$this->validXmlLanguageTag($hreflang)) {
                $hreflang = '';
            }
            $attributes = [
                'href' => $href,
                'rel' => $rel,
                'hreflang' => $hreflang,
                'media-type' => $mediaType,
                'id' => $id,
                'refines' => $refines,
            ];
            $language = $this->entryString($link, 'lang') ?: $this->entryString($link, 'xml:lang');
            if ($language !== '' && $this->validXmlLanguageTag($language)) {
                $attributes['xml:lang'] = $language;
            }
            $direction = strtolower($this->entryString($link, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $attributes['dir'] = $direction;
            }
            $properties = $this->containerLinkTokenList($link['properties'] ?? []);
            if ($properties !== '') {
                $attributes['properties'] = $properties;
            }
            $xmlAttributes = '';
            foreach ($attributes as $name => $value) {
                if ($value === '') {
                    continue;
                }
                $xmlAttributes .= ' ' . $name . '="' . $this->esc($value) . '"';
            }
            if ($xmlAttributes !== '') {
                $lines[] = '    <link' . $xmlAttributes . '/>';
            }
        }

        if ($lines === []) {
            return '';
        }

        return '  <links>' . "\n"
            . implode("\n", array_values(array_unique($lines))) . "\n"
            . '  </links>' . "\n";
    }

    private function containerLinkHref(string $href): string
    {
        if ($this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return $href;
        }

        return $this->relativePath('META-INF', $this->safePackagePath($href));
    }

    private function validContainerLinkSourceHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        $lowerHref = strtolower($href);
        if (str_starts_with($lowerHref, 'data:') || str_starts_with($lowerHref, 'file:')) {
            return false;
        }

        return $this->containerLinkHrefPathDiagnosticReason($href) === '';
    }

    private function containerLinkHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function encodedDotSegmentPathDiagnosticReason(string $path): string
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || !str_contains($segment, '%')) {
                continue;
            }
            $decoded = $this->decodeUrlPathPercentEscapes($segment);
            if ($decoded === '.' || $decoded === '..') {
                return 'encoded-dot-segment';
            }
        }

        return '';
    }

    private function decodeUrlPathPercentEscapes(string $path): string
    {
        return (string) preg_replace_callback(
            '/%[0-9A-Fa-f]{2}/',
            static function (array $match): string {
                $byte = hexdec(substr((string) $match[0], 1));
                if (in_array($byte, [0x2f, 0x5c, 0x3f, 0x23], true)) {
                    return (string) $match[0];
                }

                return chr($byte);
            },
            $path
        );
    }

    private function containerLinkZipPath(string $href): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return '';
        }

        [$path] = $this->splitUrlPathSuffix($href);
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        return $this->safePackagePath('META-INF/' . $path);
    }

    /**
     * @param array<string, mixed> $link
     */
    private function containerLinkMediaType(array $link, string $href): string
    {
        $mediaType = $this->entryString($link, 'mediaType') ?: $this->entryString($link, 'media-type');
        if ($mediaType !== '') {
            return $mediaType;
        }
        if ($href === '' || $this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return '';
        }

        [$path] = $this->splitUrlPathSuffix($href);
        $path = $this->safePackagePath(trim($path));
        if ($path === '') {
            return '';
        }

        $inferred = $this->mediaType($path);
        return $inferred === 'application/octet-stream' ? '' : $inferred;
    }

    private function containerLinkTokenList(mixed $tokens): string
    {
        $values = [];
        $source = is_array($tokens) ? $tokens : [$tokens];
        foreach ($source as $entry) {
            if (!is_scalar($entry)) {
                continue;
            }
            foreach (preg_split('/\s+/', trim((string) $entry)) ?: [] as $token) {
                if ($token !== '' && $this->validPropertyValue($token)) {
                    $values[] = $token;
                }
            }
        }

        return implode(' ', array_values(array_unique($values)));
    }

    /**
     * @param array<string, true> $declaredPrefixes
     */
    private function packageLinkTokenList(mixed $tokens, array $declaredPrefixes): string
    {
        $values = [];
        $source = is_array($tokens) ? $tokens : [$tokens];
        foreach ($source as $entry) {
            if (!is_scalar($entry)) {
                continue;
            }
            foreach (preg_split('/\s+/', trim((string) $entry)) ?: [] as $token) {
                if (
                    $token !== ''
                    && $this->validPropertyValue($token)
                    && $this->propertyValuePrefixIsDeclared($token, $declaredPrefixes)
                ) {
                    $values[] = $token;
                }
            }
        }

        return implode(' ', array_values(array_unique($values)));
    }

    /**
     * @param array<string, true> $declaredPrefixes
     */
    private function packageLinkRelationTokenList(mixed $tokens, array $declaredPrefixes, bool $allowRefines, string $refines): string
    {
        $rel = $this->packageLinkTokenList($tokens, $declaredPrefixes);
        if ($rel === '' || !$allowRefines || $this->validMetadataRefinesValue($refines) !== '') {
            return $rel;
        }

        $values = [];
        foreach (preg_split('/\s+/', $rel) ?: [] as $token) {
            if (strtolower($token) !== 'voicing') {
                $values[] = $token;
            }
        }

        return implode(' ', $values);
    }

    private function validPackageLinkHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        $lowerHref = strtolower($href);
        if (str_starts_with($lowerHref, 'data:') || str_starts_with($lowerHref, 'file:')) {
            return false;
        }

        return $this->packageLinkHrefPathDiagnosticReason($href) === ''
            && $this->packageLinkHrefFragmentDiagnosticReason($href) === '';
    }

    private function packageLinkHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '#')) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && str_starts_with($suffix, '?')) {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function packageLinkHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href)) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    private function validMetadataRefinesValue(string $refines): string
    {
        if (!str_starts_with($refines, '#')) {
            return '';
        }

        $target = substr($refines, 1);

        return $target !== '' && $this->validXmlId($target) ? $refines : '';
    }

    private function validPropertyValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || preg_match('/\s/u', $value) === 1) {
            return false;
        }

        if (!str_contains($value, ':')) {
            return $this->validXmlId($value);
        }

        [$prefix, $reference] = explode(':', $value, 2);

        return $prefix !== ''
            && $reference !== ''
            && $this->validXmlId($prefix);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, true>
     */
    private function packagePropertyPrefixNames(array $meta): array
    {
        $prefixes = [];
        foreach (array_keys($this->packageReservedPrefixIris()) as $prefix) {
            $prefixes[$prefix] = true;
        }

        $prefix = $this->packagePrefix($meta);
        $tokens = $prefix === '' ? [] : (preg_split('/\s+/', $prefix) ?: []);
        for ($i = 0, $count = count($tokens); $i < $count; $i += 2) {
            $prefixToken = $tokens[$i] ?? '';
            $iri = $tokens[$i + 1] ?? '';
            if (!str_ends_with($prefixToken, ':')) {
                continue;
            }

            $name = substr($prefixToken, 0, -1);
            if ($name !== '' && $this->validXmlId($name) && $iri !== '' && $this->absoluteIriLike($iri)) {
                $prefixes[$name] = true;
            }
        }

        return $prefixes;
    }

    /**
     * @param array<string, true> $declaredPrefixes
     */
    private function propertyValuePrefixIsDeclared(string $value, array $declaredPrefixes): bool
    {
        if (!str_contains($value, ':')) {
            return true;
        }

        [$prefix] = explode(':', trim($value), 2);

        return isset($declaredPrefixes[$prefix]);
    }

    private function validXmlId(string $value): bool
    {
        return preg_match('/^[\p{L}_][\p{L}\p{N}._-]*$/u', $value) === 1;
    }

    private function validXmlLanguageTag(string $value): bool
    {
        if (preg_match('/[\s_]/', $value) === 1) {
            return false;
        }

        return preg_match('/^(?:[A-Za-z]{2,8}|[xXiI])(?:-[A-Za-z0-9]{1,8})*$/', $value) === 1;
    }

    private function validMediaType(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9!#$&^_.+*-]+\/[A-Za-z0-9!#$&^_.+*-]+(?:\s*;\s*[A-Za-z0-9!#$&^_.+*-]+=(?:"[^"]*"|[A-Za-z0-9!#$&^_.+*-]+))*$/', trim($value)) === 1;
    }

    private function mediaTypeMatches(string $candidate, string $expected): bool
    {
        return strtolower(trim(explode(';', $candidate, 2)[0])) === strtolower($expected);
    }

    private function containerLinkProperties(mixed $properties): string
    {
        if (is_scalar($properties)) {
            $properties = preg_split('/\s+/', trim((string) $properties)) ?: [];
        }
        if (!is_array($properties)) {
            return '';
        }

        return implode(' ', array_values(array_unique(array_filter(
            array_map(static fn (mixed $property): string => trim((string) $property), $properties),
            static fn (string $property): bool => $property !== ''
        ))));
    }

    /**
     * @param array<string, string> $resources
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     */
    private function packageOpf(AstNode $document, array $chapters, string $navHref, array $resources, string $packageDir, string $packagePath, string $ncxId = '', string $ncxHref = ''): string
    {
        $meta = $this->metadata($document);
        $identifier = $this->identifier($meta);
        $identifierId = $this->packageUniqueIdentifierId($meta);
        $title = $this->metaString($meta, 'title', 'Untitled');
        $language = $this->language($meta);
        $modified = $this->modified($meta);
        $resourceMediaTypes = $this->resourceMediaTypes($meta);
        $resourceProperties = $this->resourcePropertiesByPath($meta);
        $resourceAttributes = $this->resourceManifestAttributesByPath($meta);
        $manifestIdOverrides = $this->manifestIdOverridesByPath($meta, $packageDir);
        $standalonePackageLinkResourcePaths = $this->standalonePackageLinkResourcePaths($meta, $packageDir, $resources, $resourceMediaTypes);
        $metadataRefinesResources = $this->metadataRefinesResources($document, $chapters, $navHref, $resources, $resourceMediaTypes, $packageDir, $ncxHref);
        $mediaOverlayByPath = $this->mediaOverlayIdsByContentPath($meta, $packageDir);
        $mediaOverlayByIndex = $this->mediaOverlayIdsBySpineIndex($meta);
        $renditionMetadata = $this->renditionMetadata($meta);
        $mediaMetadata = $this->mediaMetadata($meta);
        $spineManifestProperties = $this->spineManifestProperties($meta);
        $spineManifestAttributes = $this->spineManifestAttributes($meta);
        $spineItemProperties = $this->spineItemProperties($meta);
        $spineItemIds = $this->spineItemIds($meta);
        $seenItemrefIds = [];
        $pageProgressionDirection = $this->pageProgressionDirection($meta);
        $spineId = $this->validManifestId($this->optionOrMetaString($meta, 'spineId', 'epubSpineId', false));
        $packageId = $this->validManifestId($this->optionOrMetaString($meta, 'packageId', 'epubPackageId', false));
        $coverPath = $this->coverImagePath($resources, $meta);
        $coverItemId = '';
        $isEpub2 = $this->isEpub2($meta);
        if ($isEpub2) {
            $pageProgressionDirection = '';
        }

        $manifest = [];
        $manifestItems = [];
        $seenIds = [];
        if ($this->includeEpub3Nav($meta) && $navHref !== '') {
            $manifest[] = '    <item id="nav" href="' . $this->esc($navHref) . '" media-type="application/xhtml+xml" properties="nav"/>';
            $manifestItems['nav'] = [
                'media-type' => 'application/xhtml+xml',
                'properties' => ['nav'],
            ];
            $seenIds['nav'] = true;
        }
        $chapterManifestEntries = [];
        $resourceManifestEntries = [];
        if ($ncxId !== '' && $ncxHref !== '') {
            $manifest[] = '    <item id="' . $this->esc($ncxId) . '" href="' . $this->esc($ncxHref) . '" media-type="application/x-dtbncx+xml"/>';
            $manifestItems[$ncxId] = [
                'media-type' => 'application/x-dtbncx+xml',
                'properties' => [],
            ];
        }
        $spine = [];
        if ($ncxId !== '') {
            $seenIds[$ncxId] = true;
        }
        $seenSpineIds = [];
        foreach ($chapters as $index => $chapter) {
            $seenIds[$chapter['id']] = true;
            $seenSpineIds[$chapter['id']] = true;
            $mediaOverlayId = $mediaOverlayByPath[$chapter['path']] ?? $mediaOverlayByIndex[$index] ?? '';
            $chapterProperties = array_values(array_unique(array_merge(
                $this->chapterManifestProperties($chapter['document'], $meta, $index),
                $spineManifestProperties[$index] ?? []
            )));
            if ($isEpub2) {
                $chapterProperties = [];
            }
            $chapterAttributes = array_replace($spineManifestAttributes[$index] ?? [], $resourceAttributes[$chapter['path']] ?? []);
            if (!$isEpub2 && $mediaOverlayId !== '') {
                $chapterAttributes['media-overlay'] = $mediaOverlayId;
            }
            $chapterManifestEntries[] = [
                'id' => $chapter['id'],
                'href' => $chapter['href'],
                'media-type' => 'application/xhtml+xml',
                'properties' => $chapterProperties,
                'attributes' => $chapterAttributes,
            ];
            $manifestItems[$chapter['id']] = [
                'media-type' => 'application/xhtml+xml',
                'properties' => $chapterProperties,
                'fallback' => $chapterAttributes['fallback'] ?? '',
                'fallback-style' => $chapterAttributes['fallback-style'] ?? '',
                'media-overlay' => $chapterAttributes['media-overlay'] ?? '',
            ];
            $properties = $spineItemProperties[$index] ?? [];
            $itemrefId = $spineItemIds[$index] ?? '';
            if ($itemrefId !== '' && isset($seenItemrefIds[$itemrefId])) {
                $itemrefId = '';
            }
            if ($itemrefId !== '') {
                $seenItemrefIds[$itemrefId] = true;
            }
            $spine[] = '    <itemref'
                . ($itemrefId === '' ? '' : ' id="' . $this->esc($itemrefId) . '"')
                . ' idref="' . $this->esc($chapter['id']) . '"'
                . ($isEpub2 || $properties === [] ? '' : ' properties="' . $this->esc(implode(' ', $properties)) . '"')
                . '/>';
        }
        $resourceManifestIds = [];
        foreach ($resources as $path => $_bytes) {
            if (isset($standalonePackageLinkResourcePaths[$path])) {
                continue;
            }
            $href = $this->relativePath($packageDir, $path);
            $id = $this->manifestIdForResource($path, $seenIds, $manifestIdOverrides);
            $resourceManifestIds[$path] = $id;
            $mediaType = $resourceMediaTypes[$path] ?? $this->mediaType($path);
            $properties = $this->mergeProperties($this->resourceProperties($path, $mediaType, (string) $_bytes), $resourceProperties[$path] ?? []);
            if ($coverPath !== '' && $path === $coverPath) {
                $coverItemId = $id;
                if (!$isEpub2) {
                    $properties = $this->mergeProperties($properties, ['cover-image']);
                }
            }
            if ($isEpub2) {
                $properties = '';
            }
            $attributes = $resourceAttributes[$path] ?? [];
            $propertyTokens = $properties === '' ? [] : (preg_split('/\s+/', $properties) ?: []);
            $resourceManifestEntries[] = [
                'id' => $id,
                'path' => $path,
                'href' => $href,
                'media-type' => $mediaType,
                'properties' => $propertyTokens,
                'propertiesXml' => $properties,
                'attributes' => $attributes,
            ];
            $manifestItems[$id] = [
                'media-type' => $mediaType,
                'properties' => $propertyTokens,
                'fallback' => $attributes['fallback'] ?? '',
                'fallback-style' => $attributes['fallback-style'] ?? '',
                'media-overlay' => $attributes['media-overlay'] ?? '',
            ];
        }
        $manifestAttributesById = $this->sanitizedManifestItemAttributesById($manifestItems, !$isEpub2, !$isEpub2);
        foreach ($chapterManifestEntries as $entry) {
            $chapterProperties = $entry['properties'];
            $chapterAttributes = $manifestAttributesById[$entry['id']] ?? [];
            $manifest[] = '    <item id="' . $this->esc($entry['id']) . '" href="' . $this->esc($entry['href']) . '" media-type="' . $this->esc($entry['media-type']) . '"'
                . ($chapterProperties === [] ? '' : ' properties="' . $this->esc(implode(' ', $chapterProperties)) . '"')
                . $this->manifestItemAttributes($chapterAttributes)
                . '/>';
        }
        $sanitizedResourceAttributes = [];
        foreach ($resourceManifestEntries as $entry) {
            $attributes = $manifestAttributesById[$entry['id']] ?? [];
            $sanitizedResourceAttributes[$entry['path']] = $attributes;
            $manifest[] = '    <item id="' . $this->esc($entry['id']) . '" href="' . $this->esc($entry['href']) . '" media-type="' . $this->esc($entry['media-type']) . '"'
                . ($entry['propertiesXml'] === '' ? '' : ' properties="' . $this->esc($entry['propertiesXml']) . '"')
                . $this->manifestItemAttributes($attributes)
                . '/>';
        }
        array_push($spine, ...$this->nonLinearSpineItemRefs($meta, $packageDir, $resourceManifestIds, $resourceMediaTypes, $sanitizedResourceAttributes, $seenSpineIds, $seenItemrefIds));

        $reservedPackageIds = $seenIds + [$identifierId => true];
        foreach (array_keys($seenItemrefIds) as $itemrefId) {
            $reservedPackageIds[$itemrefId] = true;
        }
        if ($packageId !== '' && isset($reservedPackageIds[$packageId])) {
            $packageId = '';
        }
        if ($packageId !== '') {
            $reservedPackageIds[$packageId] = true;
        }
        if ($spineId !== '' && isset($reservedPackageIds[$spineId])) {
            $spineId = '';
        }
        if ($spineId !== '') {
            $reservedPackageIds[$spineId] = true;
        }

        $dublinCoreMetadata = $this->dublinCoreMetadataElements($meta, $identifier, $identifierId, $title, $language, $reservedPackageIds);
        $metadata = $dublinCoreMetadata['items'];
        $coveredDublinCoreElements = $dublinCoreMetadata['covered'];
        if (!$isEpub2) {
            $metadata[] = '    <meta property="dcterms:modified">' . $this->esc($modified) . '</meta>';
        } elseif ($coverItemId !== '') {
            $metadata[] = '    <meta name="cover" content="' . $this->esc($coverItemId) . '"/>';
        }
        if (!isset($coveredDublinCoreElements['creator'])) {
            foreach ($this->metaList($meta, 'author') as $author) {
                $metadata[] = '    <dc:creator>' . $this->esc($author) . '</dc:creator>';
            }
        }
        if (!isset($coveredDublinCoreElements['contributor'])) {
            foreach ($this->metaList($meta, 'contributor') as $contributor) {
                $metadata[] = '    <dc:contributor>' . $this->esc($contributor) . '</dc:contributor>';
            }
        }
        if (!isset($coveredDublinCoreElements['subject'])) {
            foreach ($this->metaList($meta, 'subject') as $subject) {
                $metadata[] = '    <dc:subject>' . $this->esc($subject) . '</dc:subject>';
            }
        }
        $description = $this->metaString($meta, 'description', '');
        if ($description !== '' && !isset($coveredDublinCoreElements['description'])) {
            $metadata[] = '    <dc:description>' . $this->esc($description) . '</dc:description>';
        }
        foreach ([
            'publisher' => 'publisher',
            'date' => 'date',
            'type' => 'type',
            'format' => 'format',
            'source' => 'source',
            'relation' => 'relation',
            'coverage' => 'coverage',
            'rights' => 'rights',
        ] as $metaKey => $dcElement) {
            if (isset($coveredDublinCoreElements[$dcElement])) {
                continue;
            }
            foreach ($this->metaList($meta, $metaKey) as $value) {
                $metadata[] = '    <dc:' . $dcElement . '>' . $this->esc($value) . '</dc:' . $dcElement . '>';
                if ($dcElement === 'date') {
                    $coveredDublinCoreElements[$dcElement] = true;
                    break;
                }
            }
        }
        if (!$isEpub2) {
            foreach ($renditionMetadata as $property => $value) {
                $metadata[] = '    <meta property="' . $this->esc($property) . '">' . $this->esc($value) . '</meta>';
            }
            foreach ($mediaMetadata as $property => $value) {
                $metadata[] = '    <meta property="' . $this->esc($property) . '">' . $this->esc($value) . '</meta>';
            }
            array_push($metadata, ...$this->spineRenditionMetadataElements($meta, $chapters, $spineItemIds));
            array_push($metadata, ...$this->mediaOverlayMetadataElements($meta));
        }
        $packageElementIds = $this->metadataIdsFromXmlLines(array_merge($metadata, $manifest, $spine));
        if ($packageId !== '') {
            $packageElementIds[$packageId] = true;
        }
        if ($spineId !== '') {
            $packageElementIds[$spineId] = true;
        }

        $metadataProperties = $isEpub2 ? [] : $this->metadataPropertyElements(
            $meta,
            array_merge(['dcterms:modified'], array_keys($renditionMetadata), array_keys($mediaMetadata)),
            array_replace_recursive($this->spineRenditionMetadataSkipMap($meta), $this->mediaOverlayMetadataSkipMap($meta)),
            $metadata,
            $packageDir,
            $packagePath,
            $packageElementIds,
            $metadataRefinesResources['paths'],
            $metadataRefinesResources['xmlPayloads']
        );
        array_push($metadata, ...$metadataProperties);
        foreach ($this->metadataIdsFromXmlLines($metadataProperties) as $id => $_) {
            $packageElementIds[$id] = true;
        }
        $metadataLinks = $isEpub2 ? [] : $this->metadataLinkElements(
            $meta,
            $packageDir,
            $packagePath,
            $packageElementIds,
            $metadataRefinesResources['paths'],
            $metadataRefinesResources['xmlPayloads']
        );
        array_push($metadata, ...$metadataLinks);
        foreach ($this->metadataIdsFromXmlLines($metadataLinks) as $id => $_) {
            $packageElementIds[$id] = true;
        }
        $guideReferences = $this->guideReferences(
            $meta,
            $coverPath,
            $packageDir,
            $metadataRefinesResources['paths'],
            $metadataRefinesResources['xmlPayloads']
        );
        $spineOpen = '  <spine'
            . ($spineId === '' ? '' : ' id="' . $this->esc($spineId) . '"')
            . ($ncxId === '' ? '' : ' toc="' . $this->esc($ncxId) . '"')
            . ($pageProgressionDirection === '' ? '' : ' page-progression-direction="' . $this->esc($pageProgressionDirection) . '"')
            . '>';
        $bindings = $isEpub2 ? '' : $this->bindingsXml($meta, $manifestItems);
        $collections = $isEpub2 ? '' : $this->collectionsXml(
            $meta,
            $packageDir,
            $packagePath,
            $metadataRefinesResources['paths'],
            $metadataRefinesResources['xmlPayloads'],
            $packageElementIds
        );

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<package ' . $this->packageRootAttributes($meta, $identifierId, $packageId) . '>' . "\n"
            . "  <metadata>\n" . implode("\n", $metadata) . "\n  </metadata>\n"
            . "  <manifest>\n" . implode("\n", $manifest) . "\n  </manifest>\n"
            . $spineOpen . "\n" . implode("\n", $spine) . "\n  </spine>\n"
            . ($guideReferences === [] ? '' : "  <guide>\n" . implode("\n", array_map(
                fn (array $reference): string => '    <reference type="' . $this->esc($reference['type']) . '" title="' . $this->esc($reference['title']) . '" href="' . $this->esc($reference['href']) . '"/>',
                $guideReferences
            )) . "\n  </guide>\n")
            . $bindings
            . $collections
            . '</package>' . "\n";
    }

    /**
     * @param array<string, string> $resources
     */
    private function chapterXhtml(AstNode $document, array $resources, string $packageDir, string $chapterDir, int $chapterIndex): string
    {
        $meta = $this->metadata($document);
        $title = $this->chapterHeadTitle($meta, $chapterIndex, $this->metaString($meta, 'title', 'Untitled'));
        $language = $this->chapterLanguage($meta, $chapterIndex);
        $xmlLanguage = $this->chapterXmlLanguage($meta, $chapterIndex, $language);
        $direction = $this->chapterDirection($meta, $chapterIndex);
        $viewport = $this->viewportMeta($meta, $chapterIndex);
        $body = (new HtmlWriter($this->htmlWriterOptions()))->write($document);
        $body = $this->addEpubSpineSemantics($body);
        $body = $this->rewriteRenderedResourceUrls($body, $resources, $packageDir, $chapterDir);
        $body = $this->normalizeXhtmlBodyFragment($body);
        $includeXmlEventsNamespace = str_contains($body, 'ev:');
        $headMetas = $this->chapterHeadMetas($meta, $chapterIndex);
        $headBases = $this->chapterHeadBases($meta, $packageDir, $chapterDir, $chapterIndex);
        $headLinks = $this->chapterHeadLinks($meta, $resources, $packageDir, $chapterDir, $chapterIndex);
        $headStyles = $this->chapterHeadStyles($meta, $resources, $packageDir, $chapterDir, $chapterIndex);
        $headScripts = $this->chapterHeadScripts($meta, $packageDir, $chapterDir, $chapterIndex);
        $bodyAttributes = $this->chapterBodyAttributes($meta, $chapterIndex);
        $rootAttributes = $this->chapterRootAttributes($meta, $chapterIndex, $language, $xmlLanguage, $direction, $includeXmlEventsNamespace);

        return '<html ' . $rootAttributes . '>' . "\n"
            . "<head>\n"
            . '  <title>' . $this->esc($title) . '</title>' . "\n"
            . "  <meta charset=\"utf-8\" />\n"
            . ($viewport === '' ? '' : $viewport . "\n")
            . ($headMetas === '' ? '' : $headMetas . "\n")
            . ($headBases === '' ? '' : $headBases . "\n")
            . ($headLinks === '' ? '' : $headLinks . "\n")
            . ($headStyles === '' ? '' : $headStyles . "\n")
            . ($headScripts === '' ? '' : $headScripts . "\n")
            . "</head>\n"
            . '<body' . $bodyAttributes . ">\n"
            . $body . "\n"
            . "</body>\n"
            . "</html>\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlWriterOptions(): array
    {
        $options = ['writerHTMLMathMethod' => 'mathml'];
        if (array_key_exists('htmlQTags', $this->options)) {
            $options['htmlQTags'] = (bool) $this->options['htmlQTags'];
        }

        return $options;
    }

    /**
     * @param list<array{text: string, level: int, href?: string, type?: string, value?: string}> $entries
     */
    private function navSectionXhtml(string $type, string $id, string $title, array $entries, string $navDir, string $packageDir, array $sectionAttributes = []): string
    {
        if ($entries === []) {
            return '';
        }

        $list = $this->navListXhtml($entries, $navDir, $packageDir, true, 4);
        $heading = $this->navSectionTitle($sectionAttributes, $title);
        $headingTag = 'h' . $this->navSectionHeadingLevel($sectionAttributes, 2);
        $headingAttributes = $this->navSectionHeadingAttributes($sectionAttributes);

        return '  <nav' . $this->navSectionAttributes($sectionAttributes, $type, $id) . '>' . "\n"
            . '    <' . $headingTag . $headingAttributes . '>' . $this->esc($heading) . '</' . $headingTag . '>' . "\n"
            . $list . "\n"
            . "  </nav>\n";
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array{text: string, level: int, href?: string, type?: string, value?: string}>
     */
    private function navigationEntries(array $meta, string $key): array
    {
        $rawEntries = [];
        foreach ($this->navigationEntryKeys($key) as $candidateKey) {
            if (isset($meta[$candidateKey]) && is_array($meta[$candidateKey])) {
                $rawEntries = $meta[$candidateKey];
                break;
            }
        }
        if (!is_array($rawEntries)) {
            return [];
        }

        $entries = [];
        foreach ($rawEntries as $rawEntry) {
            if (!is_array($rawEntry)) {
                continue;
            }
            $text = $this->entryString($rawEntry, 'text');
            if ($text === '' && $key === 'epubPageListEntries') {
                $text = $this->entryString($rawEntry, 'value');
            }
            $href = $this->entryString($rawEntry, 'href');
            if ($text === '') {
                continue;
            }

            $entry = [
                'text' => $text,
                'level' => $this->entryLevel($rawEntry),
            ];
            if ($href !== '') {
                $entry['href'] = $href;
            }
            $type = $this->entryString($rawEntry, 'type');
            if ($type !== '') {
                $entry['type'] = $type;
            }
            $value = $this->entryString($rawEntry, 'value');
            if ($value !== '') {
                $entry['value'] = $value;
            }
            $playOrder = $this->entryPositiveInteger($rawEntry, 'playOrder');
            if ($playOrder !== null) {
                $entry['playOrder'] = $playOrder;
            }
            $id = $this->validManifestId($this->entryString($rawEntry, 'id'));
            if ($id !== '') {
                $entry['id'] = $id;
            }
            foreach (['title', 'role', 'ariaLabel'] as $attribute) {
                $attributeValue = $this->entryString($rawEntry, $attribute);
                if ($attributeValue !== '') {
                    $entry[$attribute] = $attributeValue;
                }
            }
            foreach (['rel', 'hreflang', 'media', 'target'] as $attribute) {
                $attributeValue = $this->entryString($rawEntry, $attribute);
                if ($attributeValue !== '') {
                    $entry[$attribute] = $attributeValue;
                }
            }
            $language = $this->entryString($rawEntry, 'lang') ?: $this->entryString($rawEntry, 'xml:lang') ?: $this->entryString($rawEntry, 'xmlLanguage');
            if ($language !== '' && $this->validLanguageTag($language)) {
                $entry['lang'] = $language;
            }
            $direction = strtolower($this->entryString($rawEntry, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $entry['dir'] = $direction;
            }
            $classes = $this->propertyTokens($rawEntry['classes'] ?? $rawEntry['class'] ?? []);
            if ($classes !== []) {
                $entry['classes'] = $classes;
            }
            if ($this->entryBool($rawEntry, 'hidden')) {
                $entry['hidden'] = true;
            }
            $itemAttributes = $this->navListItemAttributesFromEntry($rawEntry);
            if ($itemAttributes !== []) {
                $entry['itemAttributes'] = $itemAttributes;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function navigationEntryKeys(string $key): array
    {
        return match ($key) {
            'epubTocEntries' => ['epubTocEntries', 'tocEntries'],
            'epubLandmarkEntries' => ['epubLandmarkEntries', 'landmarkEntries'],
            'epubPageListEntries' => ['epubPageListEntries', 'pageListEntries'],
            default => [$key],
        };
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array{type: string, title: string, attributes: array<string, mixed>, entries: list<array<string, mixed>>}>
     */
    private function auxiliaryNavSections(array $meta): array
    {
        $rawSections = $meta['epubAuxiliaryNavSections'] ?? $meta['auxiliaryNavSections'] ?? [];
        if (!is_array($rawSections)) {
            return [];
        }

        $sections = [];
        foreach ($rawSections as $rawSection) {
            if (!is_array($rawSection)) {
                continue;
            }

            $type = strtolower($this->entryString($rawSection, 'type') ?: $this->entryString($rawSection, 'navType'));
            if (!in_array($type, ['loi', 'lot', 'loa', 'lov'], true)) {
                continue;
            }

            $entries = $this->navigationEntries(['entries' => $rawSection['entries'] ?? []], 'entries');
            if ($entries === []) {
                continue;
            }

            $attributes = [];
            if (isset($rawSection['attributes']) && is_array($rawSection['attributes'])) {
                $attributes = $rawSection['attributes'];
            }
            foreach ([
                'id',
                'role',
                'ariaLabel',
                'aria-label',
                'classes',
                'class',
                'lang',
                'xml:lang',
                'xmlLanguage',
                'dir',
                'hidden',
                'epubType',
                'epub:type',
                'headingLevel',
                'heading-level',
                'heading_level',
                'headingAttributes',
                'headingHtmlAttributes',
            ] as $key) {
                if (!array_key_exists($key, $attributes) && array_key_exists($key, $rawSection)) {
                    $attributes[$key] = $rawSection[$key];
                }
            }
            $title = $this->entryString($rawSection, 'title')
                ?: $this->entryString($rawSection, 'heading')
                ?: $this->entryString($rawSection, 'navTitle')
                ?: $this->defaultAuxiliaryNavTitle($type);
            if ($title !== '' && $this->navSectionTitle($attributes, '') === '') {
                $attributes['heading'] = $title;
            }

            $sections[] = [
                'type' => $type,
                'title' => $title,
                'attributes' => $attributes,
                'entries' => $entries,
            ];
        }

        return $sections;
    }

    private function defaultAuxiliaryNavTitle(string $type): string
    {
        return match ($type) {
            'loi' => 'Illustrations',
            'lot' => 'Tables',
            'loa' => 'Audio',
            'lov' => 'Video',
            default => 'Navigation',
        };
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @return list<array{type: string, title: string, href: string}>
     */
    private function guideReferences(array $meta, string $coverPath, string $packageDir, array $resourcePaths = [], array $xmlResourcePayloads = []): array
    {
        $references = [];
        $seen = [];
        $rawReferences = $meta['epubGuideReferences'] ?? [];
        if (is_array($rawReferences)) {
            foreach ($rawReferences as $rawReference) {
                if (!is_array($rawReference)) {
                    continue;
                }
                $type = strtolower($this->entryString($rawReference, 'type'));
                $href = $this->entryString($rawReference, 'href');
                if ($type === '' || !$this->validXmlId($type) || $href === '') {
                    continue;
                }
                $href = $this->guideReferenceHref($href, $packageDir, $resourcePaths, $xmlResourcePayloads);
                if ($href === '') {
                    continue;
                }
                $reference = [
                    'type' => $type,
                    'title' => $this->entryString($rawReference, 'title') ?: ucfirst($type),
                    'href' => $href,
                ];
                $key = $reference['type'] . "\0" . $reference['href'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $references[] = $reference;
            }
        }

        if ($coverPath !== '') {
            $coverReference = [
                'type' => 'cover',
                'title' => 'Cover',
                'href' => $this->relativePath($packageDir, $coverPath),
            ];
            $key = $coverReference['type'] . "\0" . $coverReference['href'];
            if (!isset($seen[$key])) {
                array_unshift($references, $coverReference);
            }
        }

        return $references;
    }

    /**
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function guideReferenceHref(string $href, string $packageDir, array $resourcePaths, array $xmlResourcePayloads): string
    {
        $href = trim($href);
        if ($href === '' || !$this->validGuideReferenceHref($href, $packageDir, $resourcePaths, $xmlResourcePayloads)) {
            return '';
        }

        return $this->packageEntryHref($href, $packageDir);
    }

    /**
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function validGuideReferenceHref(string $href, string $packageDir, array $resourcePaths, array $xmlResourcePayloads): bool
    {
        $lowerHref = strtolower($href);
        if (str_starts_with($lowerHref, 'data:') || str_starts_with($lowerHref, 'file:')) {
            return false;
        }
        if ($this->guideReferenceHrefPathDiagnosticReason($href) !== '' || $this->guideReferenceHrefFragmentDiagnosticReason($href) !== '') {
            return false;
        }
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return true;
        }

        $targetPath = '';
        foreach ($this->packageLinkResourcePathCandidates($href, $packageDir) as $candidate) {
            if (isset($resourcePaths[$candidate])) {
                $targetPath = $candidate;
                break;
            }
        }
        if ($targetPath === '') {
            return false;
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment !== '' && !isset($xmlResourcePayloads[$targetPath])) {
            return false;
        }
        if ($fragment !== '' && !$this->xmlPayloadHasElementIdInWellFormedDocument($xmlResourcePayloads[$targetPath], $fragment)) {
            return false;
        }

        return true;
    }

    private function guideReferenceHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function guideReferenceHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryString(array $entry, string $key): string
    {
        $value = $entry[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryPackagePath(array $entry, string $key, string $packageDir): string
    {
        $path = $this->entryString($entry, $key);
        if ($path === '') {
            return '';
        }

        $path = $this->splitUrlPathSuffix($path)[0];
        if ($path === '') {
            return '';
        }

        $normalized = $this->safePackagePath($path);
        if ($normalized === '') {
            return '';
        }
        if ($key === 'href' || str_ends_with($key, 'Href')) {
            return $this->safePackagePath($packageDir . '/' . $normalized);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryLevel(array $entry): int
    {
        $level = $entry['level'] ?? 1;
        if (!is_int($level) && !is_float($level) && !(is_string($level) && is_numeric($level))) {
            return 1;
        }

        return max(1, min(6, (int) $level));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryPositiveInteger(array $entry, string $key): ?int
    {
        $value = $entry[$key] ?? null;
        if (!is_int($value) && !is_float($value) && !(is_string($value) && preg_match('/^[0-9]+$/', trim($value)) === 1)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryNonNegativeInteger(array $entry, string $key): ?int
    {
        $value = $entry[$key] ?? null;
        if (!is_int($value) && !is_float($value) && !(is_string($value) && preg_match('/^[0-9]+$/', trim($value)) === 1)) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function ncxMetadata(array $meta): array
    {
        $metadata = $meta['epubNcxMetadata'] ?? $meta['ncxMetadata'] ?? [];
        if (!is_array($metadata)) {
            return [];
        }
        if (isset($metadata[0]) && is_array($metadata[0])) {
            return $metadata[0];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $ncxMetadata
     * @return list<array<string, mixed>>
     */
    private function ncxHeadMetadata(array $meta, array $ncxMetadata): array
    {
        $records = $meta['epubNcxHeadMetadata'] ?? $meta['ncxHeadMetadata'] ?? $ncxMetadata['head'] ?? [];
        if (!is_array($records)) {
            return [];
        }

        return array_values(array_filter($records, static fn (mixed $record): bool => is_array($record)));
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $ncxMetadata
     * @return array{text: string, lang?: string}
     */
    private function ncxDocTitleEntry(array $meta, array $ncxMetadata, string $fallbackTitle): array
    {
        $title = ($this->metaStringFromKeys($meta, ['epubNcxDocTitle', 'ncxDocTitle']) ?? '')
            ?: $this->entryString($ncxMetadata, 'docTitle')
            ?: $fallbackTitle;
        $entry = ['text' => $title];
        $language = ($this->metaStringFromKeys($meta, ['epubNcxDocTitleLang', 'ncxDocTitleLang']) ?? '')
            ?: $this->entryString($ncxMetadata, 'docTitleLang');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $entry['lang'] = $language;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $ncxMetadata
     * @return list<array{text: string, lang?: string}>
     */
    private function ncxDocAuthorEntries(array $meta, array $ncxMetadata): array
    {
        $records = $meta['epubNcxDocAuthorRecords'] ?? $meta['ncxDocAuthorRecords'] ?? $ncxMetadata['docAuthorRecords'] ?? [];
        if (is_array($records)) {
            $entries = [];
            foreach ($records as $record) {
                $entry = $this->ncxDocAuthorEntry($record);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
            if ($entries !== []) {
                return $entries;
            }
        }

        $authors = $meta['epubNcxDocAuthors'] ?? $meta['ncxDocAuthors'] ?? $ncxMetadata['docAuthors'] ?? [];
        if (is_scalar($authors)) {
            $authors = [(string) $authors];
        }
        if (!is_array($authors)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $author): ?array => $this->ncxDocAuthorEntry($author), $authors)
        ));
    }

    /**
     * @return array{text: string, lang?: string}|null
     */
    private function ncxDocAuthorEntry(mixed $author): ?array
    {
        if (is_scalar($author)) {
            $text = trim((string) $author);
            return $text === '' ? null : ['text' => $text];
        }
        if (!is_array($author)) {
            return null;
        }

        $text = $this->entryString($author, 'text') ?: $this->entryString($author, 'name') ?: $this->entryString($author, 'author');
        if ($text === '') {
            return null;
        }

        $entry = ['text' => $text];
        $language = $this->entryString($author, 'lang') ?: $this->entryString($author, 'xml:lang') ?: $this->entryString($author, 'xmlLanguage');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $entry['lang'] = $language;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $ncxMetadata
     * @return array{text: string, lang?: string}
     */
    private function ncxPageListLabelEntry(array $meta, array $ncxMetadata): array
    {
        $label = ($this->metaStringFromKeys($meta, ['epubNcxPageListLabel', 'ncxPageListLabel']) ?? '')
            ?: $this->entryString($ncxMetadata, 'pageListLabel')
            ?: 'Pages';
        $entry = ['text' => $label];
        $language = ($this->metaStringFromKeys($meta, ['epubNcxPageListLabelLang', 'ncxPageListLabelLang']) ?? '')
            ?: $this->entryString($ncxMetadata, 'pageListLabelLang');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $entry['lang'] = $language;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array{label: string, entries: list<array<string, mixed>>, id?: string, type?: string, lang?: string}>
     */
    private function ncxNavLists(array $meta): array
    {
        $rawLists = $meta['epubNcxNavLists'] ?? $meta['ncxNavLists'] ?? [];
        if (!is_array($rawLists)) {
            return [];
        }

        $lists = [];
        foreach ($rawLists as $rawList) {
            if (!is_array($rawList)) {
                continue;
            }
            $rawEntries = $rawList['entries'] ?? [];
            if (!is_array($rawEntries)) {
                continue;
            }
            $entries = $this->navigationEntries(['epubTocEntries' => $rawEntries], 'epubTocEntries');
            if ($entries === []) {
                continue;
            }

            $list = [
                'label' => $this->entryString($rawList, 'label')
                    ?: $this->entryString($rawList, 'title')
                    ?: 'Navigation',
                'entries' => $entries,
            ];
            $id = $this->validManifestId($this->entryString($rawList, 'id'));
            if ($id !== '') {
                $list['id'] = $id;
            }
            $type = $this->entryString($rawList, 'type') ?: $this->entryString($rawList, 'class');
            if ($type !== '') {
                $list['type'] = $type;
            }
            $language = $this->entryString($rawList, 'lang') ?: $this->entryString($rawList, 'xml:lang') ?: $this->entryString($rawList, 'xmlLanguage');
            if ($language !== '' && $this->validLanguageTag($language)) {
                $list['lang'] = $language;
            }
            $lists[] = $list;
        }

        return $lists;
    }

    private function navEntryHref(string $href, string $navDir, string $packageDir): string
    {
        return $this->entryHref($href, $navDir, $packageDir);
    }

    private function packageEntryHref(string $href, string $packageDir): string
    {
        return $this->entryHref($href, $packageDir, $packageDir);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function ncxManifestId(array $meta): string
    {
        $id = $this->optionOrMetaString($meta, 'spineTocId', 'epubSpineTocId', false);
        if ($id === '') {
            $id = $this->optionOrMetaString($meta, 'ncxId', 'epubNcxId', false);
        }
        if ($id === '' && !$this->includeNcx($meta)) {
            return '';
        }

        $id = $this->validManifestId($id === '' ? 'toc' : $id);
        if ($id === '' || $id === 'nav') {
            return 'toc';
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function includeNcx(array $meta): bool
    {
        $sources = [$this->optionOrMetaValue($meta, ['includeNcx'], ['epubIncludeNcx'], null, false)];
        foreach ($sources as $value) {
            if (is_bool($value)) {
                return $value;
            }
            if (is_scalar($value)) {
                $normalized = strtolower(trim((string) $value));
                if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                    return true;
                }
                if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                    return false;
                }
            }
        }

        return $this->isEpub2($meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function ncxPath(array $meta, string $packageDir): string
    {
        $candidate = $this->optionOrMetaValue($meta, ['ncxPath'], ['epubNcxPath', 'ncxPath'], null, false);
        foreach ([$candidate] as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $path = $this->safePackagePath((string) $candidate);
            if ($path !== '') {
                return $path;
            }
        }

        return $this->safePackagePath(($packageDir === '' ? '' : $packageDir . '/') . 'toc.ncx');
    }

    private function entryHref(string $href, string $fromDir, string $packageDir): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $href;
        }
        [$path, $suffix] = $this->splitUrlPathSuffix($href);
        if ($path === '') {
            return $href;
        }

        $normalized = $this->safePackagePath($path);
        if ($normalized === '') {
            return $href;
        }
        if (!$this->pathIsWithinBase($normalized, $packageDir)) {
            return $href;
        }

        return $this->relativePath($fromDir, $normalized) . $suffix;
    }

    private function entryBaseHref(string $href, string $fromDir, string $packageDir): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $href;
        }

        [$path] = $this->splitUrlPathSuffix($href);
        $rewritten = $this->entryHref($href, $fromDir, $packageDir);
        if ($path === '' || !str_ends_with($path, '/')) {
            return $rewritten;
        }

        [$rewrittenPath, $suffix] = $this->splitUrlPathSuffix($rewritten);
        if ($rewrittenPath === '' || str_ends_with($rewrittenPath, '/')) {
            return $rewritten;
        }

        return $rewrittenPath . '/' . $suffix;
    }

    private function pathIsWithinBase(string $path, string $baseDir): bool
    {
        $baseDir = $this->safePackagePath($baseDir);
        if ($baseDir === '') {
            return true;
        }

        return $path === $baseDir || str_starts_with($path, $baseDir . '/');
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function renditionMetadata(array $meta): array
    {
        $properties = [];
        foreach ([
            'rendition:layout' => ['option' => 'renditionLayout', 'meta' => 'epubRenditionLayout', 'allowed' => ['reflowable', 'pre-paginated']],
            'rendition:orientation' => ['option' => 'renditionOrientation', 'meta' => 'epubRenditionOrientation', 'allowed' => ['landscape', 'portrait', 'auto']],
            'rendition:spread' => ['option' => 'renditionSpread', 'meta' => 'epubRenditionSpread', 'allowed' => ['none', 'landscape', 'portrait', 'both', 'auto']],
            'rendition:flow' => ['option' => 'renditionFlow', 'meta' => 'epubRenditionFlow', 'allowed' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto']],
        ] as $property => $config) {
            $value = strtolower($this->optionOrMetaString($meta, $config['option'], $config['meta'], false));
            if ($value !== '' && in_array($value, $config['allowed'], true)) {
                $properties[$property] = $value;
            }
        }
        $viewportSources = $this->preferMetadataOverOptions
            ? [
                $meta['epubRenditionViewport'] ?? null,
                $meta['renditionViewport'] ?? null,
            ]
            : [
                $this->options['renditionViewport'] ?? null,
                $this->options['epubRenditionViewport'] ?? null,
                $meta['epubRenditionViewport'] ?? null,
                $meta['renditionViewport'] ?? null,
            ];
        foreach ($viewportSources as $source) {
            $viewport = $this->normalizeViewport($source);
            if ($viewport !== null) {
                $properties['rendition:viewport'] = $viewport['content'];
                break;
            }
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function mediaMetadata(array $meta): array
    {
        $properties = [];
        foreach ($this->mediaMetadataProperties() as $property => $config) {
            $value = $this->optionOrMetaString($meta, $config['option'], $config['meta'], false);
            if ($value !== '') {
                $properties[$property] = $value;
            }
        }
        if (!isset($properties['media:duration'])) {
            $duration = $this->singleMediaOverlayMetadataValue($meta, 'media:duration');
            if ($duration !== '' && $this->validSmilClockValue($duration)) {
                $properties['media:duration'] = $duration;
            }
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function singleMediaOverlayMetadataValue(array $meta, string $property): string
    {
        $source = $this->optionOrMetaValue($meta, ['mediaOverlays'], ['epubMediaOverlays'], [], false);
        if (!is_array($source)) {
            return '';
        }

        $entries = array_values(array_filter($source, static fn (mixed $entry): bool => is_array($entry)));
        if (count($entries) !== 1) {
            return '';
        }

        /** @var array<string, mixed> $entry */
        $entry = $entries[0];
        $target = $this->validManifestId($this->entryString($entry, 'overlayId') ?: $this->entryString($entry, 'mediaOverlay'));
        if ($target === '') {
            return '';
        }

        foreach ($this->mediaOverlayMetadataRecords($entry) as $record) {
            if (($record['property'] ?? '') === $property) {
                return trim((string) ($record['value'] ?? ''));
            }
        }

        return '';
    }

    private function validSmilClockValue(string $value): bool
    {
        $value = strtolower(trim($value));
        if (str_starts_with($value, 'npt=')) {
            $value = trim(substr($value, 4));
        }

        return preg_match('/^\d+:[0-5]\d:[0-5]\d(?:\.\d+)?$/', $value) === 1
            || preg_match('/^\d+:[0-5]\d(?:\.\d+)?$/', $value) === 1
            || preg_match('/^\d+(?:\.\d+)?(?:h|min|s|ms)$/', $value) === 1;
    }

    /**
     * @return array<string, array{option: string, meta: string}>
     */
    private function mediaMetadataProperties(): array
    {
        return [
            'media:duration' => ['option' => 'mediaDuration', 'meta' => 'epubMediaDuration'],
            'media:narrator' => ['option' => 'mediaNarrator', 'meta' => 'epubMediaNarrator'],
            'media:active-class' => ['option' => 'mediaActiveClass', 'meta' => 'epubMediaActiveClass'],
            'media:playback-active-class' => ['option' => 'mediaPlaybackActiveClass', 'meta' => 'epubMediaPlaybackActiveClass'],
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param list<string> $spineItemIds
     * @return list<string>
     */
    private function spineRenditionMetadataElements(array $meta, array $chapters, array $spineItemIds = []): array
    {
        $source = $this->preferMetadataOverOptions && array_key_exists('epubSpineItemRefs', $meta)
            ? $meta['epubSpineItemRefs']
            : ($this->options['spineRenditionMetadata'] ?? $meta['epubSpineItemRefs'] ?? []);
        if (!is_array($source)) {
            return [];
        }

        $items = [];
        $seen = [];
        foreach ($chapters as $index => $chapter) {
            $entry = $source[$index] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            $target = $spineItemIds[$index] ?? '';
            if ($target === '') {
                $target = $chapter['id'];
            }
            foreach ($this->spineRenditionMetadataRecords($entry) as $record) {
                $attrs = [
                    'property' => $record['property'],
                    'refines' => '#' . $target,
                ];
                foreach (['scheme'] as $attribute) {
                    $value = $this->entryString($record, $attribute);
                    if ($value !== '') {
                        $attrs[$attribute] = $value;
                    }
                }
                $direction = strtolower($this->entryString($record, 'dir'));
                if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                    $attrs['dir'] = $direction;
                }
                $language = $this->entryString($record, 'lang') ?: $this->entryString($record, 'xml:lang');
                if ($language !== '') {
                    $attrs['xml:lang'] = $language;
                }

                $xmlAttrs = '';
                foreach ($attrs as $name => $value) {
                    $xmlAttrs .= ' ' . $name . '="' . $this->esc($value) . '"';
                }

                $xml = '    <meta' . $xmlAttrs . '>' . $this->esc($record['value']) . '</meta>';
                if (isset($seen[$xml])) {
                    continue;
                }
                $seen[$xml] = true;
                $items[] = $xml;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function mediaOverlayMetadataElements(array $meta): array
    {
        $source = $this->optionOrMetaValue($meta, ['mediaOverlays'], ['epubMediaOverlays'], [], false);
        if (!is_array($source)) {
            return [];
        }

        $items = [];
        $seen = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $target = $this->validManifestId($this->entryString($entry, 'overlayId') ?: $this->entryString($entry, 'mediaOverlay'));
            if ($target === '') {
                continue;
            }
            foreach ($this->mediaOverlayMetadataRecords($entry) as $record) {
                $attrs = [
                    'property' => $record['property'],
                    'refines' => '#' . $target,
                ];
                foreach (['id', 'scheme'] as $attribute) {
                    $value = $this->entryString($record, $attribute);
                    if ($value !== '') {
                        $attrs[$attribute] = $value;
                    }
                }
                $direction = strtolower($this->entryString($record, 'dir'));
                if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                    $attrs['dir'] = $direction;
                }
                $language = $this->entryString($record, 'lang') ?: $this->entryString($record, 'xml:lang');
                if ($language !== '') {
                    $attrs['xml:lang'] = $language;
                }

                $xmlAttrs = '';
                foreach ($attrs as $name => $value) {
                    $xmlAttrs .= ' ' . $name . '="' . $this->esc($value) . '"';
                }

                $xml = '    <meta' . $xmlAttrs . '>' . $this->esc($record['value']) . '</meta>';
                if (isset($seen[$xml])) {
                    continue;
                }
                $seen[$xml] = true;
                $items[] = $xml;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, array<string, true>>
     */
    private function spineRenditionMetadataSkipMap(array $meta): array
    {
        $source = $meta['epubSpineItemRefs'] ?? [];
        if (!is_array($source)) {
            return [];
        }

        $skip = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $targets = $this->spineRenditionMetadataTargets($entry);
            if ($targets === []) {
                continue;
            }
            foreach ($this->spineRenditionMetadataRecords($entry) as $record) {
                foreach ($targets as $target) {
                    $skip[$target][$record['property']] = true;
                }
            }
        }

        return $skip;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, array<string, true>>
     */
    private function mediaOverlayMetadataSkipMap(array $meta): array
    {
        $source = $meta['epubMediaOverlays'] ?? [];
        if (!is_array($source)) {
            return [];
        }

        $skip = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $target = $this->validManifestId($this->entryString($entry, 'overlayId') ?: $this->entryString($entry, 'mediaOverlay'));
            if ($target === '') {
                continue;
            }
            foreach ($this->mediaOverlayMetadataRecords($entry) as $record) {
                $skip[$target][$record['property']] = true;
            }
        }

        return $skip;
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<array<string, string>>
     */
    private function mediaOverlayMetadataRecords(array $entry): array
    {
        $records = [];
        $seenProperties = [];
        foreach ($this->mediaOverlayMetadataPropertyKeys() as $property => $keys) {
            $value = '';
            foreach ($keys as $key) {
                $value = $this->entryString($entry, $key);
                if ($value !== '') {
                    break;
                }
            }
            if ($value !== '') {
                $records[] = [
                    'property' => $property,
                    'value' => $value,
                ];
                $seenProperties[$property] = true;
            }
        }

        $metadataProperties = $entry['metadataProperties'] ?? [];
        if (!is_array($metadataProperties)) {
            return $records;
        }
        foreach ($metadataProperties as $record) {
            if (!is_array($record)) {
                continue;
            }
            $property = strtolower($this->entryString($record, 'property'));
            if (!isset($this->mediaOverlayMetadataPropertyKeys()[$property]) || isset($seenProperties[$property])) {
                continue;
            }
            $value = $this->entryString($record, 'value') ?: $this->entryString($record, 'content');
            if ($value === '') {
                continue;
            }

            $entryRecord = [
                'property' => $property,
                'value' => $value,
            ];
            foreach (['id', 'scheme', 'dir', 'lang', 'xml:lang'] as $key) {
                $attributeValue = $this->entryString($record, $key);
                if ($attributeValue !== '') {
                    $entryRecord[$key] = $attributeValue;
                }
            }
            $records[] = $entryRecord;
            $seenProperties[$property] = true;
        }

        return $records;
    }

    /**
     * @return array<string, list<string>>
     */
    private function mediaOverlayMetadataPropertyKeys(): array
    {
        return [
            'media:duration' => ['duration', 'mediaDuration', 'epubMediaDuration'],
            'media:narrator' => ['narrator', 'mediaNarrator', 'epubMediaNarrator'],
            'media:active-class' => ['activeClass', 'mediaActiveClass', 'epubMediaActiveClass'],
            'media:playback-active-class' => ['playbackActiveClass', 'mediaPlaybackActiveClass', 'epubMediaPlaybackActiveClass'],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function spineRenditionMetadataTargets(array $entry): array
    {
        $targets = [];
        foreach ([$this->entrySpineItemId($entry), $this->validManifestId(ltrim($this->entryString($entry, 'idref'), '#'))] as $target) {
            if ($target !== '' && !in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<array<string, string>>
     */
    private function spineRenditionMetadataRecords(array $entry): array
    {
        $records = [];
        $seenProperties = [];
        foreach (['renditionViewport', 'epubRenditionViewport', 'viewport', 'epubViewport', 'viewportContent'] as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }
            $viewport = $this->normalizeViewport($entry[$key]);
            if ($viewport === null) {
                continue;
            }
            $records[] = [
                'property' => 'rendition:viewport',
                'value' => $viewport['content'],
            ];
            $seenProperties['rendition:viewport'] = true;
            break;
        }
        foreach ($this->renditionRefinementProperties() as $property => $config) {
            $value = '';
            foreach ($config['keys'] as $key) {
                $value = strtolower($this->entryString($entry, $key));
                if ($value !== '') {
                    break;
                }
            }
            if ($value !== '' && in_array($value, $config['allowed'], true)) {
                $records[] = [
                    'property' => $property,
                    'value' => $value,
                ];
                $seenProperties[$property] = true;
            }
        }

        $metadataProperties = $entry['metadataProperties'] ?? [];
        if (!is_array($metadataProperties)) {
            return $records;
        }
        foreach ($metadataProperties as $record) {
            if (!is_array($record)) {
                continue;
            }
            $property = strtolower($this->entryString($record, 'property'));
            if ($property === 'rendition:viewport') {
                if (isset($seenProperties[$property])) {
                    continue;
                }
                $viewport = $this->normalizeViewport($this->entryString($record, 'value') ?: $this->entryString($record, 'content'));
                if ($viewport === null) {
                    continue;
                }

                $entryRecord = [
                    'property' => $property,
                    'value' => $viewport['content'],
                ];
                foreach (['scheme', 'dir', 'lang', 'xml:lang'] as $key) {
                    $attributeValue = $this->entryString($record, $key);
                    if ($attributeValue !== '') {
                        $entryRecord[$key] = $attributeValue;
                    }
                }
                $records[] = $entryRecord;
                $seenProperties[$property] = true;
                continue;
            }
            $config = $this->renditionRefinementMetadataProperties()[$property] ?? null;
            if ($config === null || isset($seenProperties[$property])) {
                continue;
            }
            $value = strtolower($this->entryString($record, 'value') ?: $this->entryString($record, 'content'));
            if (!in_array($value, $config['allowed'], true)) {
                continue;
            }

            $entryRecord = [
                'property' => $property,
                'value' => $value,
            ];
            foreach (['scheme', 'dir', 'lang', 'xml:lang'] as $key) {
                $attributeValue = $this->entryString($record, $key);
                if ($attributeValue !== '') {
                    $entryRecord[$key] = $attributeValue;
                }
            }
            $records[] = $entryRecord;
            $seenProperties[$property] = true;
        }

        return $records;
    }

    /**
     * @return array<string, array{keys: list<string>, allowed: list<string>}>
     */
    private function renditionRefinementProperties(): array
    {
        return [
            'rendition:layout' => [
                'keys' => ['renditionLayout', 'epubRenditionLayout', 'layout'],
                'allowed' => ['reflowable', 'pre-paginated'],
            ],
            'rendition:orientation' => [
                'keys' => ['renditionOrientation', 'epubRenditionOrientation', 'orientation'],
                'allowed' => ['landscape', 'portrait', 'auto'],
            ],
            'rendition:spread' => [
                'keys' => ['renditionSpread', 'epubRenditionSpread', 'spread'],
                'allowed' => ['none', 'landscape', 'portrait', 'both', 'auto'],
            ],
            'rendition:flow' => [
                'keys' => ['renditionFlow', 'epubRenditionFlow', 'flow'],
                'allowed' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto'],
            ],
        ];
    }

    /**
     * @return array<string, array{keys: list<string>, allowed: list<string>}>
     */
    private function renditionRefinementMetadataProperties(): array
    {
        return $this->renditionRefinementProperties();
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $generatedProperties
     * @param array<string, array<string, true>> $skipRefinedProperties
     * @param list<string> $existingMetadataXml
     * @param array<string, true> $packageElementIds
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @return list<string>
     */
    private function metadataPropertyElements(
        array $meta,
        array $generatedProperties,
        array $skipRefinedProperties = [],
        array $existingMetadataXml = [],
        string $packageDir = '',
        string $packagePath = '',
        array $packageElementIds = [],
        array $resourcePaths = [],
        array $xmlResourcePayloads = []
    ): array
    {
        $source = $this->optionOrMetaValue($meta, ['metadataProperties'], ['epubMetadataProperties', 'epubMetaProperties'], [], false);
        if (!is_array($source)) {
            return [];
        }

        $declaredPrefixes = $this->packagePropertyPrefixNames($meta);
        $generated = array_fill_keys(array_map('strtolower', $generatedProperties), true);
        $items = [];
        $seen = [];
        $seenIds = $this->metadataIdsFromXmlLines($existingMetadataXml);
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $property = $this->entryString($entry, 'property');
            if (
                $property === ''
                || !$this->validPropertyValue($property)
                || !$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)
            ) {
                continue;
            }
            $value = $this->entryString($entry, 'value');
            if ($value === '') {
                $value = $this->entryString($entry, 'content');
            }
            if ($value === '') {
                continue;
            }

            $attrs = ['property' => $property];
            $id = $this->entryString($entry, 'id');
            if ($id !== '' && $this->validXmlId($id) && !isset($seenIds[$id]) && !isset($packageElementIds[$id])) {
                $attrs['id'] = $id;
                $seenIds[$id] = true;
                $packageElementIds[$id] = true;
            }
            $refines = $this->metadataRefinesAttribute(
                $this->entryString($entry, 'refines'),
                $packageDir,
                $packagePath,
                array_replace($packageElementIds, $seenIds),
                $resourcePaths,
                $xmlResourcePayloads
            );
            if ($refines !== '') {
                $attrs['refines'] = $refines;
            }
            $propertyKey = strtolower($property);
            if ($propertyKey === 'dcterms:modified' && $refines === '') {
                continue;
            }
            $scheme = $this->entryString($entry, 'scheme');
            if (
                $scheme !== ''
                && $this->validPropertyValue($scheme)
                && $this->propertyValuePrefixIsDeclared($scheme, $declaredPrefixes)
            ) {
                $attrs['scheme'] = $scheme;
            }
            $direction = strtolower($this->entryString($entry, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $attrs['dir'] = $direction;
            }
            $language = $this->entryString($entry, 'lang') ?: $this->entryString($entry, 'xml:lang');
            if ($language !== '' && $this->validXmlLanguageTag($language)) {
                $attrs['xml:lang'] = $language;
            }
            $refinesTarget = ltrim((string) ($attrs['refines'] ?? ''), '#');
            if ($refinesTarget !== '' && isset($skipRefinedProperties[$refinesTarget][$propertyKey])) {
                continue;
            }
            if (isset($generated[$propertyKey]) && count($attrs) === 1) {
                continue;
            }

            $xmlAttrs = '';
            foreach ($attrs as $name => $attributeValue) {
                $xmlAttrs .= ' ' . $name . '="' . $this->esc($attributeValue) . '"';
            }

            $xml = '    <meta' . $xmlAttrs . '>' . $this->esc($value) . '</meta>';
            if (isset($seen[$xml])) {
                continue;
            }
            $seen[$xml] = true;
            $items[] = $xml;
        }

        return $items;
    }

    /**
     * @param list<string> $xmlLines
     * @return array<string, true>
     */
    private function metadataIdsFromXmlLines(array $xmlLines): array
    {
        $ids = [];
        foreach ($xmlLines as $xmlLine) {
            if (preg_match_all('/\\sid="([^"]+)"/', $xmlLine, $matches) !== false) {
                foreach ($matches[1] ?? [] as $id) {
                    $decoded = html_entity_decode($id, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if ($decoded !== '') {
                        $ids[$decoded] = true;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, true> $packageElementIds
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @return list<string>
     */
    private function metadataLinkElements(
        array $meta,
        string $packageDir,
        string $packagePath = '',
        array $packageElementIds = [],
        array $resourcePaths = [],
        array $xmlResourcePayloads = []
    ): array
    {
        $source = $this->optionOrMetaValue($meta, ['metadataLinks'], ['epubMetadataLinks', 'epubMetaLinks'], [], false);
        if (!is_array($source)) {
            return [];
        }

        $declaredPrefixes = $this->packagePropertyPrefixNames($meta);
        $items = [];
        $seen = [];
        $seenIds = $packageElementIds;
        foreach ($source as $link) {
            if (!is_array($link)) {
                continue;
            }
            $href = $this->entryString($link, 'href');
            if ($href === '' || !$this->validPackageLinkHref($href)) {
                continue;
            }
            if ($this->packageLinkHrefTargetsPackageDocumentFragment($href, $packageDir, $packagePath)) {
                continue;
            }
            if (!$this->packageLinkHrefTargetsAvailableResource($href, $packageDir, $packagePath, $resourcePaths, $xmlResourcePayloads)) {
                continue;
            }
            $rel = $this->packageLinkRelationTokenList($link['rel'] ?? '', $declaredPrefixes, true, $this->entryString($link, 'refines'));
            if ($rel === '') {
                continue;
            }
            $sanitizedLink = $link;
            $sanitizedLink['rel'] = $rel;
            $attrs = [
                'href' => $this->packageEntryHref($href, $packageDir),
                'rel' => $rel,
            ];
            $hreflang = $this->entryString($link, 'hreflang');
            if ($hreflang !== '' && $this->validXmlLanguageTag($hreflang)) {
                $attrs['hreflang'] = $hreflang;
            }
            $mediaType = $this->packageLinkMediaType($sanitizedLink, $href, $packageDir, true);
            if ($mediaType === '' && $this->packageLinkRelRequiresMediaType($sanitizedLink)) {
                continue;
            }
            if ($mediaType !== '') {
                $attrs['media-type'] = $mediaType;
            }
            $refines = $this->packageMetadataLinkRefinesAttribute(
                $sanitizedLink,
                $packageDir,
                $packagePath,
                $packageElementIds,
                $resourcePaths,
                $xmlResourcePayloads
            );
            if ($refines !== '') {
                $attrs['refines'] = $refines;
            }
            $language = $this->entryString($link, 'lang') ?: $this->entryString($link, 'xml:lang');
            if ($language !== '' && $this->validXmlLanguageTag($language)) {
                $attrs['xml:lang'] = $language;
            }
            $direction = strtolower($this->entryString($link, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $attrs['dir'] = $direction;
            }
            $id = $this->validManifestId($this->entryString($link, 'id'));
            if ($id !== '' && !isset($seenIds[$id])) {
                $attrs['id'] = $id;
                $seenIds[$id] = true;
            }
            $properties = $this->packageLinkTokenList($link['properties'] ?? [], $declaredPrefixes);
            if ($properties !== '') {
                $attrs['properties'] = $properties;
            }

            $xmlAttrs = '';
            foreach ($attrs as $name => $value) {
                $xmlAttrs .= ' ' . $name . '="' . $this->esc($value) . '"';
            }
            $xml = '    <link' . $xmlAttrs . '/>';
            if (isset($seen[$xml])) {
                continue;
            }
            $seen[$xml] = true;
            $items[] = $xml;
        }

        return $items;
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param array<string, string> $resources
     * @param array<string, string> $resourceMediaTypes
     * @return array{paths: array<string, true>, xmlPayloads: array<string, string>}
     */
    private function metadataRefinesResources(AstNode $document, array $chapters, string $navHref, array $resources, array $resourceMediaTypes, string $packageDir, string $ncxHref = ''): array
    {
        $paths = [];
        $xmlPayloads = [];

        foreach ($chapters as $index => $chapter) {
            $path = $this->safePackagePath($chapter['path']);
            if ($path === '') {
                continue;
            }
            $paths[$path] = true;
            $xmlPayloads[$path] = $this->chapterXhtml($chapter['document'], $resources, $packageDir, $this->dirname($path), $index);
        }

        foreach ($resources as $path => $bytes) {
            $path = $this->safePackagePath($path);
            if ($path === '') {
                continue;
            }
            $paths[$path] = true;
            $mediaType = $resourceMediaTypes[$path] ?? $this->mediaType($path);
            if ($this->metadataRefinesPayloadIsXmlLike($bytes, $mediaType)) {
                $xmlPayloads[$path] = $bytes;
            }
        }

        $navPath = $this->metadataRefinesZipPath($navHref, $packageDir);
        if ($navPath !== '') {
            $paths[$navPath] = true;
            $xmlPayloads[$navPath] = $this->navXhtml($document, $chapters, $this->dirname($navPath), $packageDir, $resources, $navPath);
        }

        $ncxPath = $this->metadataRefinesZipPath($ncxHref, $packageDir);
        if ($ncxPath !== '') {
            $paths[$ncxPath] = true;
            $xmlPayloads[$ncxPath] = $this->ncxXml($document, $chapters, $this->dirname($ncxPath), $packageDir, $resources);
        }

        return [
            'paths' => $paths,
            'xmlPayloads' => $xmlPayloads,
        ];
    }

    private function metadataRefinesZipPath(string $href, string $packageDir): string
    {
        foreach ($this->packageLinkResourcePathCandidates($href, $packageDir) as $candidate) {
            if ($packageDir === '' || $this->pathIsWithinBase($candidate, $packageDir)) {
                return $candidate;
            }
        }

        return '';
    }

    private function metadataRefinesPayloadIsXmlLike(string $bytes, string $mediaType): bool
    {
        $normalizedMediaType = strtolower(trim(explode(';', $mediaType, 2)[0]));
        if (
            $normalizedMediaType === 'application/xhtml+xml'
            || $normalizedMediaType === 'image/svg+xml'
            || $normalizedMediaType === 'application/xml'
            || $normalizedMediaType === 'text/xml'
            || str_ends_with($normalizedMediaType, '+xml')
        ) {
            return true;
        }

        return str_starts_with(ltrim($bytes), '<');
    }

    /**
     * @param array<string, true> $packageElementIds
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function metadataRefinesAttribute(string $refines, string $packageDir, string $packagePath, array $packageElementIds, array $resourcePaths, array $xmlResourcePayloads): string
    {
        $refines = trim($refines);
        if ($refines === '' || $this->metadataRefinesPathDiagnosticReason($refines) !== '') {
            return '';
        }

        if (str_starts_with($refines, '#')) {
            $target = substr($refines, 1);
            return $target !== '' && $this->validXmlId($target) && isset($packageElementIds[$target]) ? $refines : '';
        }

        $fragment = $this->urlFragmentIdentifier($refines);
        $packagePath = $this->safePackagePath($packagePath);
        foreach ($this->packageLinkResourcePathCandidates($refines, $packageDir) as $candidate) {
            if ($candidate === $packagePath) {
                return $fragment !== '' && $this->validXmlId($fragment) && isset($packageElementIds[$fragment])
                    ? $this->packageEntryHref($refines, $packageDir)
                    : '';
            }
        }

        $targetPath = '';
        foreach ($this->packageLinkResourcePathCandidates($refines, $packageDir) as $candidate) {
            if (isset($resourcePaths[$candidate])) {
                $targetPath = $candidate;
                break;
            }
        }
        if ($targetPath === '') {
            return '';
        }

        if ($fragment !== '' && !isset($xmlResourcePayloads[$targetPath])) {
            return '';
        }
        if ($fragment !== '' && !$this->xmlPayloadHasElementIdInWellFormedDocument($xmlResourcePayloads[$targetPath], $fragment)) {
            return '';
        }

        return $this->packageEntryHref($refines, $packageDir);
    }

    private function metadataRefinesPathDiagnosticReason(string $refines): string
    {
        if (str_starts_with($refines, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($refines)) {
            return 'absolute-url';
        }
        if (str_starts_with($refines, '/')) {
            return 'absolute-path';
        }
        if (str_contains($refines, '\\')) {
            return 'backslash';
        }

        [$path] = $this->splitUrlPathSuffix($refines);
        $encodedDotSegmentReason = $this->encodedDotSegmentPathDiagnosticReason($path);
        if ($encodedDotSegmentReason !== '') {
            return $encodedDotSegmentReason;
        }

        $hasFragment = str_contains($refines, '#');
        $fragment = $this->urlFragmentIdentifier($refines);
        if ($hasFragment && $fragment === '') {
            return 'empty-fragment';
        }
        if (!str_starts_with($refines, '#') && trim($path) === '') {
            return 'empty-path';
        }
        if (!str_starts_with($refines, '#') && $fragment !== '' && preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    private function xmlPayloadHasElementIdInWellFormedDocument(string $xml, string $id): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return false;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && trim($element->getAttribute('id')) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, true> $packageElementIds
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function packageMetadataLinkRefinesAttribute(array $link, string $packageDir, string $packagePath, array $packageElementIds, array $resourcePaths, array $xmlResourcePayloads): string
    {
        foreach (preg_split('/\s+/', strtolower($this->entryString($link, 'rel'))) ?: [] as $rel) {
            if ($rel === 'record') {
                return '';
            }
        }

        return $this->metadataRefinesAttribute(
            $this->entryString($link, 'refines'),
            $packageDir,
            $packagePath,
            $packageElementIds,
            $resourcePaths,
            $xmlResourcePayloads
        );
    }

    /**
     * @param array<string, mixed> $link
     */
    private function packageLinkRefinesAttribute(array $link, bool $allowRefines): string
    {
        if (!$allowRefines) {
            return '';
        }

        $refines = $this->entryString($link, 'refines');
        if ($refines === '') {
            return '';
        }

        foreach (preg_split('/\s+/', strtolower($this->entryString($link, 'rel'))) ?: [] as $rel) {
            if ($rel === 'record') {
                return '';
            }
        }

        return $this->validMetadataRefinesValue($refines);
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $resources
     * @param array<string, string> $resourceMediaTypes
     * @return array<string, true>
     */
    private function standalonePackageLinkResourcePaths(array $meta, string $packageDir, array $resources, array $resourceMediaTypes): array
    {
        $paths = [];
        foreach ($this->packageLinkMetadataEntries($meta) as $link) {
            if (!$this->packageLinkEntryUsesStandaloneResourceRel($link)) {
                continue;
            }
            $href = $this->entryString($link, 'href');
            foreach ($this->packageLinkResourcePathCandidates($href, $packageDir) as $path) {
                if (!array_key_exists($path, $resources)) {
                    continue;
                }
                $mediaType = $this->entryString($link, 'mediaType') ?: $this->entryString($link, 'media-type');
                if ($mediaType === '') {
                    $mediaType = $resourceMediaTypes[$path] ?? $this->mediaType($path);
                }
                if ($this->isEpubContentDocumentMediaType($mediaType)) {
                    continue;
                }
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, mixed>>
     */
    private function packageLinkMetadataEntries(array $meta): array
    {
        $entries = [];
        $metadataLinks = $this->optionOrMetaValue($meta, ['metadataLinks'], ['epubMetadataLinks', 'epubMetaLinks'], [], false);
        if (is_array($metadataLinks)) {
            foreach ($metadataLinks as $link) {
                if (is_array($link)) {
                    $link['_packageLinkContext'] = 'metadata';
                    $entries[] = $link;
                }
            }
        }

        $collections = $this->optionOrMetaValue($meta, ['collections'], ['epubCollections'], [], false);
        if (is_array($collections)) {
            $this->appendCollectionPackageLinkEntries($collections, $entries);
        }

        return $entries;
    }

    /**
     * @param array<mixed> $collections
     * @param list<array<string, mixed>> $entries
     */
    private function appendCollectionPackageLinkEntries(array $collections, array &$entries): void
    {
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }
            $metadata = $collection['metadata'] ?? [];
            if (is_array($metadata)) {
                foreach ($metadata as $link) {
                    if (is_array($link) && $this->entryString($link, 'href') !== '') {
                        $link['_packageLinkContext'] = 'collection-metadata';
                        $entries[] = $link;
                    }
                }
            }
            $links = $collection['links'] ?? [];
            if (is_array($links)) {
                foreach ($links as $link) {
                    if (is_array($link) && $this->entryString($link, 'href') !== '') {
                        $link['_packageLinkContext'] = 'collection-link';
                        $entries[] = $link;
                    }
                }
            }
            $nested = $collection['collections'] ?? [];
            if (is_array($nested)) {
                $this->appendCollectionPackageLinkEntries($nested, $entries);
            }
        }
    }

    /**
     * @param array<string, mixed> $link
     */
    private function packageLinkEntryUsesStandaloneResourceRel(array $link): bool
    {
        $rel = $this->entryString($link, 'rel');
        if ($rel === '') {
            return false;
        }
        foreach (preg_split('/\s+/', strtolower($rel)) ?: [] as $token) {
            if ($token === 'record' || $token === 'voicing') {
                return true;
            }
        }

        return in_array($this->entryString($link, '_packageLinkContext'), ['metadata', 'collection-metadata', 'collection-link'], true);
    }

    /**
     * @param array<string, mixed> $link
     */
    private function packageLinkMediaType(array $link, string $href, string $packageDir, bool $requireLocalMediaType): string
    {
        $mediaType = $this->entryString($link, 'mediaType') ?: $this->entryString($link, 'media-type');
        if ($mediaType !== '' && $this->validMediaType($mediaType)) {
            return $mediaType;
        }
        if (!$requireLocalMediaType && !$this->packageLinkRelRequiresMediaType($link)) {
            return '';
        }
        if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
            return '';
        }

        [$path] = $this->splitUrlPathSuffix($href);
        $path = $this->safePackagePath(trim($path));
        if ($path === '') {
            return '';
        }

        return $this->mediaType($this->safePackagePath($packageDir . '/' . $path));
    }

    /**
     * @param array<string, mixed> $link
     */
    private function packageLinkRelRequiresMediaType(array $link): bool
    {
        foreach (preg_split('/\s+/', strtolower($this->entryString($link, 'rel'))) ?: [] as $token) {
            if ($token === 'record' || $token === 'voicing') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function packageLinkResourcePathCandidates(string $href, string $packageDir): array
    {
        if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
            return [];
        }
        [$path] = $this->splitUrlPathSuffix($href);
        $path = trim($path);
        if ($path === '') {
            return [];
        }
        $normalized = $this->safePackagePath($path);
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized => true];
        if ($packageDir !== '' && !$this->pathIsWithinBase($normalized, $packageDir)) {
            $packageRelative = $this->safePackagePath($packageDir . '/' . $normalized);
            if ($packageRelative !== '') {
                $candidates[$packageRelative] = true;
            }
        }

        return array_keys($candidates);
    }

    /**
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function packageLinkHrefTargetsAvailableResource(string $href, string $packageDir, string $packagePath, array $resourcePaths, array $xmlResourcePayloads = []): bool
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return true;
        }

        $packagePath = $this->safePackagePath($packagePath);
        $fragment = $this->urlFragmentIdentifier($href);
        foreach ($this->packageLinkResourcePathCandidates($href, $packageDir) as $candidate) {
            if (isset($resourcePaths[$candidate]) || ($packagePath !== '' && $candidate === $packagePath)) {
                if ($fragment !== '' && isset($xmlResourcePayloads[$candidate]) && !$this->xmlPayloadHasElementIdInWellFormedDocument($xmlResourcePayloads[$candidate], $fragment)) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    private function packageLinkHrefTargetsPackageDocumentFragment(string $href, string $packageDir, string $packagePath): bool
    {
        $href = trim($href);
        if ($this->urlFragmentIdentifier($href) === '') {
            return false;
        }
        if (str_starts_with($href, '#')) {
            return true;
        }
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return false;
        }

        $packagePath = $this->safePackagePath($packagePath);
        if ($packagePath === '') {
            return false;
        }

        [$hrefPath] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '') {
            return true;
        }

        foreach ($this->packageLinkResourcePathCandidates($href, $packageDir) as $candidate) {
            if ($candidate === $packagePath) {
                return true;
            }
        }

        return false;
    }

    private function isEpubContentDocumentMediaType(string $mediaType): bool
    {
        $mediaType = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return $mediaType === 'application/xhtml+xml' || $mediaType === 'image/svg+xml';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function pageProgressionDirection(array $meta): string
    {
        $direction = strtolower($this->optionOrMetaString($meta, 'pageProgressionDirection', 'epubPageProgressionDirection', false));

        return in_array($direction, ['ltr', 'rtl', 'default'], true) ? $direction : '';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterLanguage(array $meta, int $chapterIndex): string
    {
        $language = $this->chapterIndexedString($meta, $chapterIndex, 'spineLanguages', ['language', 'lang', 'htmlLanguage']);
        if (!$this->validLanguageTag($language)) {
            $rootAttributes = $this->spineRootAttributes($meta, $chapterIndex);
            $language = $this->entryString($rootAttributes, 'lang')
                ?: $this->entryString($rootAttributes, 'xml:lang')
                ?: $this->entryString($rootAttributes, 'xmlLanguage');
        }

        return $this->validLanguageTag($language) ? $language : $this->language($meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterXmlLanguage(array $meta, int $chapterIndex, string $default): string
    {
        $language = $this->chapterIndexedString($meta, $chapterIndex, 'spineLanguages', ['xmlLanguage', 'language', 'lang']);
        if (!$this->validLanguageTag($language)) {
            $rootAttributes = $this->spineRootAttributes($meta, $chapterIndex);
            $language = $this->entryString($rootAttributes, 'xmlLanguage')
                ?: $this->entryString($rootAttributes, 'xml:lang')
                ?: $this->entryString($rootAttributes, 'lang');
        }

        return $this->validLanguageTag($language) ? $language : $default;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterDirection(array $meta, int $chapterIndex): string
    {
        $direction = strtolower($this->chapterIndexedString($meta, $chapterIndex, 'spineDirections', ['direction', 'dir']));
        if ($direction === '') {
            $direction = strtolower($this->optionOrMetaString($meta, 'packageDirection', 'epubPackageDirection', false));
        }
        if ($direction === '') {
            $rootAttributes = $this->spineRootAttributes($meta, $chapterIndex);
            $direction = strtolower($this->entryString($rootAttributes, 'dir'));
        }

        return in_array($direction, ['ltr', 'rtl', 'auto'], true) ? $direction : '';
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $entryKeys
     */
    private function chapterIndexedString(array $meta, int $chapterIndex, string $optionKey, array $entryKeys): string
    {
        if (!$this->preferMetadataOverOptions) {
            $optionValue = $this->indexedEntryString($this->options[$optionKey] ?? null, $chapterIndex, $entryKeys);
            if ($optionValue !== '') {
                return $optionValue;
            }
        }

        $metaValue = $this->indexedEntryString($meta[$optionKey] ?? null, $chapterIndex, $entryKeys);
        if ($metaValue !== '') {
            return $metaValue;
        }

        $spineItems = $meta['epubSpineItemRefs'] ?? null;

        return $this->indexedEntryString($spineItems, $chapterIndex, $entryKeys);
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $optionKeys
     * @param list<string> $entryKeys
     */
    private function chapterIndexedStringFromKeys(array $meta, int $chapterIndex, array $optionKeys, array $entryKeys): string
    {
        if (!$this->preferMetadataOverOptions) {
            foreach ($optionKeys as $optionKey) {
                $optionValue = $this->indexedEntryString($this->options[$optionKey] ?? null, $chapterIndex, $entryKeys);
                if ($optionValue !== '') {
                    return $optionValue;
                }
            }
        }

        foreach ($optionKeys as $optionKey) {
            $metaValue = $this->indexedEntryString($meta[$optionKey] ?? null, $chapterIndex, $entryKeys);
            if ($metaValue !== '') {
                return $metaValue;
            }
        }

        return $this->indexedEntryString($meta['epubSpineItemRefs'] ?? null, $chapterIndex, $entryKeys);
    }

    /**
     * @param list<string> $entryKeys
     */
    private function indexedEntryString(mixed $source, int $chapterIndex, array $entryKeys): string
    {
        if (!is_array($source) || !array_key_exists($chapterIndex, $source)) {
            return '';
        }

        $entry = $source[$chapterIndex];
        if (is_scalar($entry)) {
            return trim((string) $entry);
        }
        if (!is_array($entry)) {
            return '';
        }
        foreach ($entryKeys as $key) {
            $value = $entry[$key] ?? null;
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $sourceKeys
     * @param list<string> $entryKeys
     * @return array<string, mixed>
     */
    private function chapterIndexedMap(array $meta, int $chapterIndex, array $sourceKeys, array $entryKeys): array
    {
        if (!$this->preferMetadataOverOptions) {
            foreach ($sourceKeys as $sourceKey) {
                $value = $this->indexedEntryMap($this->options[$sourceKey] ?? null, $chapterIndex, $entryKeys, true);
                if ($value !== []) {
                    return $value;
                }
            }
        }

        foreach ($sourceKeys as $sourceKey) {
            $value = $this->indexedEntryMap($meta[$sourceKey] ?? null, $chapterIndex, $entryKeys, true);
            if ($value !== []) {
                return $value;
            }
        }

        return $this->indexedEntryMap($meta['epubSpineItemRefs'] ?? null, $chapterIndex, $entryKeys, false);
    }

    /**
     * @param list<string> $entryKeys
     * @return array<string, mixed>
     */
    private function indexedEntryMap(mixed $source, int $chapterIndex, array $entryKeys, bool $allowBareEntry): array
    {
        if (!is_array($source) || !array_key_exists($chapterIndex, $source)) {
            return [];
        }

        $entry = $source[$chapterIndex];
        if (!is_array($entry)) {
            return [];
        }

        foreach ($entryKeys as $key) {
            $value = $entry[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return $allowBareEntry && !array_is_list($entry) ? $entry : [];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $sourceKeys
     * @param list<string> $entryKeys
     * @return list<array<string, mixed>>
     */
    private function chapterIndexedArrayList(array $meta, int $chapterIndex, array $sourceKeys, array $entryKeys): array
    {
        if (!$this->preferMetadataOverOptions) {
            foreach ($sourceKeys as $sourceKey) {
                $value = $this->indexedEntryArrayList($this->options[$sourceKey] ?? null, $chapterIndex, $entryKeys, true);
                if ($value !== []) {
                    return $value;
                }
            }
        }

        foreach ($sourceKeys as $sourceKey) {
            $value = $this->indexedEntryArrayList($meta[$sourceKey] ?? null, $chapterIndex, $entryKeys, true);
            if ($value !== []) {
                return $value;
            }
        }

        return $this->indexedEntryArrayList($meta['epubSpineItemRefs'] ?? null, $chapterIndex, $entryKeys, false);
    }

    /**
     * @param list<string> $entryKeys
     * @return list<array<string, mixed>>
     */
    private function indexedEntryArrayList(mixed $source, int $chapterIndex, array $entryKeys, bool $allowBareEntry): array
    {
        if (!is_array($source) || !array_key_exists($chapterIndex, $source)) {
            return [];
        }

        $entry = $source[$chapterIndex];
        if (!is_array($entry)) {
            return [];
        }

        foreach ($entryKeys as $key) {
            $value = $entry[$key] ?? null;
            if (is_array($value)) {
                return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
            }
        }

        if ($allowBareEntry && array_is_list($entry)) {
            return array_values(array_filter($entry, static fn (mixed $item): bool => is_array($item)));
        }

        return $allowBareEntry ? [$entry] : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function viewportMeta(array $meta, int $chapterIndex): string
    {
        $viewport = $this->viewportForChapter($meta, $chapterIndex);

        return $viewport === null ? '' : '  <meta name="viewport" content="' . $this->esc($viewport['content']) . '" />';
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{width: int, height: int, content: string, properties?: array<string, string>}|null
     */
    private function viewportForChapter(array $meta, int $chapterIndex): ?array
    {
        $sources = [];
        if (!$this->preferMetadataOverOptions) {
            $optionViewports = $this->options['viewports'] ?? null;
            if (is_array($optionViewports)) {
                $sources[] = $optionViewports[$chapterIndex] ?? null;
            }
        }

        $spineItems = $meta['epubSpineItemRefs'] ?? [];
        if (is_array($spineItems) && isset($spineItems[$chapterIndex]) && is_array($spineItems[$chapterIndex])) {
            foreach (['viewport', 'epubViewport', 'viewportContent'] as $key) {
                if (array_key_exists($key, $spineItems[$chapterIndex])) {
                    $sources[] = $spineItems[$chapterIndex][$key];
                }
            }
        }

        $metaViewports = $meta['epubViewports'] ?? null;
        if (is_array($metaViewports)) {
            $sources[] = $metaViewports[$chapterIndex] ?? null;
        }
        $metaViewports = $meta['viewports'] ?? null;
        if (is_array($metaViewports)) {
            $sources[] = $metaViewports[$chapterIndex] ?? null;
        }

        if (!$this->preferMetadataOverOptions) {
            $sources[] = $this->options['viewport'] ?? null;
            $sources[] = [
                'width' => $this->options['viewportWidth'] ?? null,
                'height' => $this->options['viewportHeight'] ?? null,
            ];
        }
        $sources[] = $meta['epubViewport'] ?? null;
        $sources[] = $meta['epubRenditionViewport'] ?? null;
        $sources[] = $meta['renditionViewport'] ?? null;
        $sources[] = [
            'width' => $meta['epubViewportWidth'] ?? null,
            'height' => $meta['epubViewportHeight'] ?? null,
        ];

        foreach ($sources as $source) {
            $viewport = $this->normalizeViewport($source);
            if ($viewport !== null) {
                return $viewport;
            }
        }

        return null;
    }

    /**
     * @return array{width: int, height: int, content: string, properties?: array<string, string>}|null
     */
    private function normalizeViewport(mixed $value): ?array
    {
        if (is_string($value)) {
            return $this->parseViewportContent($value);
        }
        if (!is_array($value)) {
            return null;
        }

        foreach (['content', 'viewport', 'viewportContent'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) {
                $parsed = $this->parseViewportContent((string) $value[$key]);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        $width = $this->positiveViewportInteger($value['width'] ?? $value['viewportWidth'] ?? null);
        $height = $this->positiveViewportInteger($value['height'] ?? $value['viewportHeight'] ?? null);
        if ($width === null || $height === null) {
            return null;
        }

        $properties = $this->viewportProperties($value['properties'] ?? $value['viewportProperties'] ?? []);
        $viewport = [
            'width' => $width,
            'height' => $height,
            'content' => $this->viewportContent($width, $height, $properties),
        ];
        if ($properties !== []) {
            $viewport['properties'] = $properties;
        }

        return $viewport;
    }

    /**
     * @return array{width: int, height: int, content: string, properties?: array<string, string>}|null
     */
    private function parseViewportContent(string $content): ?array
    {
        $content = html_entity_decode(trim($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $width = null;
        $height = null;
        $extraOrder = [];
        $extraParts = [];
        $properties = [];
        foreach (preg_split('/[,;]/', $content) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\s*(width|height)\s*=\s*([0-9]+(?:\.[0-9]+)?)\s*$/i', $part, $match) === 1) {
                $number = $this->positiveViewportInteger($match[2]);
                if ($number === null) {
                    continue;
                }
                if (strtolower($match[1]) === 'width') {
                    $width = $number;
                } else {
                    $height = $number;
                }
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s*=\s*([^,;]+)$/', $part, $match) !== 1) {
                continue;
            }
            $key = strtolower($match[1]);
            if ($key === 'width' || $key === 'height') {
                continue;
            }
            $value = $this->viewportPropertyValue($match[2]);
            if ($value === null) {
                continue;
            }
            if (!isset($properties[$key])) {
                $extraOrder[] = $key;
            }
            $properties[$key] = $value;
            $extraParts[$key] = $key . '=' . $value;
        }
        if ($width === null || $height === null) {
            return null;
        }

        $viewport = [
            'width' => $width,
            'height' => $height,
            'content' => 'width=' . $width . ', height=' . $height,
        ];
        $orderedExtraParts = [];
        foreach ($extraOrder as $key) {
            if (isset($extraParts[$key])) {
                $orderedExtraParts[] = $extraParts[$key];
            }
        }
        if ($orderedExtraParts !== []) {
            $viewport['content'] .= ', ' . implode(', ', $orderedExtraParts);
            $viewport['properties'] = $properties;
        }

        return $viewport;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function viewportContent(int $width, int $height, array $properties = []): string
    {
        $content = 'width=' . $width . ', height=' . $height;
        foreach ($properties as $key => $value) {
            $property = $this->viewportPropertyValue($value);
            $normalizedKey = is_string($key) && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $key) === 1 ? strtolower($key) : '';
            if ($normalizedKey === '' || $normalizedKey === 'width' || $normalizedKey === 'height' || $property === null) {
                continue;
            }
            $content .= ', ' . $normalizedKey . '=' . $property;
        }

        return $content;
    }

    private function viewportPropertyValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || strlen($text) > 80 || strpbrk($text, "<>\"'") !== false) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $text) === 1) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    /**
     * @return array<string, string>
     */
    private function viewportProperties(mixed $properties): array
    {
        if (!is_array($properties)) {
            return [];
        }

        $normalized = [];
        foreach ($properties as $key => $value) {
            if (!is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $key) !== 1) {
                continue;
            }
            $key = strtolower($key);
            if ($key === 'width' || $key === 'height') {
                continue;
            }
            $property = $this->viewportPropertyValue($value);
            if ($property !== null) {
                $normalized[$key] = $property;
            }
        }

        return $normalized;
    }

    private function positiveViewportInteger(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $text) !== 1) {
            return null;
        }

        $number = (int) round((float) $text);

        return $number > 0 ? $number : null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function packageRootAttributes(array $meta, string $identifierId, string $packageId = ''): string
    {
        $attributes = [
            'xmlns' => 'http://www.idpf.org/2007/opf',
            'xmlns:dc' => 'http://purl.org/dc/elements/1.1/',
            'version' => $this->packageVersion($meta),
            'unique-identifier' => $identifierId,
        ];
        if ($packageId !== '') {
            $attributes['id'] = $packageId;
        }
        if ($this->dublinCoreMetadataNeedsOpfNamespace($meta)) {
            $attributes['xmlns:opf'] = 'http://www.idpf.org/2007/opf';
        }

        if (!$this->isEpub2($meta)) {
            $prefix = $this->packagePrefix($meta);
            if ($prefix !== '') {
                $attributes['prefix'] = $prefix;
            }
            $direction = strtolower($this->optionOrMetaString($meta, 'packageDirection', 'epubPackageDirection', false));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $attributes['dir'] = $direction;
            }
            $language = trim($this->optionOrMetaString($meta, 'packageLanguage', 'epubPackageLanguage', false));
            if ($language !== '' && $this->validXmlLanguageTag($language)) {
                $attributes['xml:lang'] = $language;
            }
        }

        $parts = [];
        foreach ($attributes as $name => $value) {
            $parts[] = $name . '="' . $this->esc($value) . '"';
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function packagePrefix(array $meta): string
    {
        $prefix = trim(preg_replace('/\s+/', ' ', $this->optionOrMetaString($meta, 'packagePrefix', 'epubPackagePrefix', false)) ?? '');
        if ($prefix === '') {
            return '';
        }

        $reservedPrefixes = $this->packageReservedPrefixIris();
        $seen = [];
        $pairs = [];
        $tokens = preg_split('/\s+/', $prefix) ?: [];
        for ($i = 0, $count = count($tokens); $i < $count; $i += 2) {
            $prefixToken = $tokens[$i] ?? '';
            $iri = $tokens[$i + 1] ?? '';
            if (!str_ends_with($prefixToken, ':')) {
                continue;
            }

            $name = substr($prefixToken, 0, -1);
            if ($name === '' || $name === '_' || !$this->validXmlId($name) || $iri === '' || !$this->absoluteIriLike($iri)) {
                continue;
            }
            if (isset($seen[$name])) {
                continue;
            }
            if (isset($reservedPrefixes[$name]) && !in_array($iri, $reservedPrefixes[$name], true)) {
                continue;
            }

            $seen[$name] = true;
            $pairs[] = $name . ': ' . $iri;
        }

        return implode(' ', $pairs);
    }

    /**
     * @return array<string, list<string>>
     */
    private function packageReservedPrefixIris(): array
    {
        return [
            'a11y' => ['http://www.idpf.org/epub/vocab/package/a11y/#'],
            'dcterms' => ['http://purl.org/dc/terms/'],
            'marc' => ['http://id.loc.gov/vocabulary/'],
            'media' => ['http://www.idpf.org/epub/vocab/overlays/#'],
            'onix' => ['http://www.editeur.org/ONIX/book/codelists/current.html#'],
            'rendition' => ['http://www.idpf.org/vocab/rendition/#'],
            'schema' => ['http://schema.org/', 'https://schema.org/'],
            'xsd' => ['http://www.w3.org/2001/XMLSchema#'],
        ];
    }

    private function absoluteIriLike(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{items: list<string>, covered: array<string, true>}
     */
    private function dublinCoreMetadataElements(
        array $meta,
        string $identifier,
        string $identifierId,
        string $title,
        string $language,
        array $reservedPackageIds = []
    ): array
    {
        $records = $this->dublinCoreMetadataRecords($meta, $identifierId, $reservedPackageIds);
        if ($records === []) {
            return [
                'items' => [
                    '    <dc:identifier id="' . $this->esc($identifierId) . '">' . $this->esc($identifier) . '</dc:identifier>',
                    '    <dc:title>' . $this->esc($title) . '</dc:title>',
                    '    <dc:language>' . $this->esc($language) . '</dc:language>',
                ],
                'covered' => [
                    'identifier' => true,
                    'title' => true,
                    'language' => true,
                ],
            ];
        }

        $items = [];
        $covered = [];
        $seen = [];
        $hasUniqueIdentifier = false;
        foreach ($records as $record) {
            $element = $record['element'];
            if ($element === 'date' && isset($covered['date'])) {
                continue;
            }
            $covered[$element] = true;
            if ($element === 'identifier' && ($record['id'] ?? '') === $identifierId) {
                $hasUniqueIdentifier = true;
            }
            $xml = $this->dublinCoreMetadataElementXml($record);
            if ($xml === '' || isset($seen[$xml])) {
                continue;
            }
            $seen[$xml] = true;
            $items[] = $xml;
        }

        if (!$hasUniqueIdentifier) {
            array_unshift($items, '    <dc:identifier id="' . $this->esc($identifierId) . '">' . $this->esc($identifier) . '</dc:identifier>');
            $covered['identifier'] = true;
        }
        if (!isset($covered['title'])) {
            $items[] = '    <dc:title>' . $this->esc($title) . '</dc:title>';
            $covered['title'] = true;
        }
        if (!isset($covered['language'])) {
            $items[] = '    <dc:language>' . $this->esc($language) . '</dc:language>';
            $covered['language'] = true;
        }

        return ['items' => $items, 'covered' => $covered];
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, string>>
     */
    private function dublinCoreMetadataRecords(array $meta, string $reservedIdentifierId = '', array $reservedPackageIds = []): array
    {
        $source = $this->preferMetadataOverOptions
            ? ($meta['epubDublinCoreMetadata'] ?? $meta['dublinCoreMetadata'] ?? [])
            : ($this->options['dublinCoreMetadata'] ?? $this->options['epubDublinCoreMetadata'] ?? $meta['epubDublinCoreMetadata'] ?? $meta['dublinCoreMetadata'] ?? []);
        if (!is_array($source)) {
            return [];
        }

        $records = [];
        $seenIds = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $element = $this->dublinCoreElementName($entry);
            $value = $this->entryString($entry, 'value') ?: $this->entryString($entry, 'text') ?: $this->entryString($entry, 'content');
            if ($element === '' || $value === '') {
                continue;
            }
            if ($element === 'language' && !$this->validXmlLanguageTag($value)) {
                continue;
            }
            $record = [
                'element' => $element,
                'value' => $value,
            ];
            $id = $this->validManifestId($this->entryString($entry, 'id'));
            if (
                $id !== ''
                && !isset($seenIds[$id])
                && ($reservedIdentifierId === '' || $id !== $reservedIdentifierId || $element === 'identifier')
                && (!isset($reservedPackageIds[$id]) || ($id === $reservedIdentifierId && $element === 'identifier'))
            ) {
                $record['id'] = $id;
                $seenIds[$id] = true;
            }
            $direction = strtolower($this->entryString($entry, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $record['dir'] = $direction;
            }
            $language = $this->entryString($entry, 'lang') ?: $this->entryString($entry, 'xml:lang');
            if ($language !== '' && $this->validXmlLanguageTag($language)) {
                $record['lang'] = $language;
            }
            foreach (['fileAs', 'role', 'scheme', 'authority', 'term'] as $key) {
                $attributeValue = $this->entryString($entry, $key);
                if ($attributeValue !== '') {
                    $record[$key] = $attributeValue;
                }
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function dublinCoreElementName(array $entry): string
    {
        $element = $this->entryString($entry, 'element') ?: $this->entryString($entry, 'name') ?: $this->entryString($entry, 'dc');
        if (str_starts_with(strtolower($element), 'dc:')) {
            $element = substr($element, 3);
        }
        $element = strtolower($element);

        return in_array($element, $this->dublinCoreElementNames(), true) ? $element : '';
    }

    /**
     * @return list<string>
     */
    private function dublinCoreElementNames(): array
    {
        return ['title', 'creator', 'contributor', 'date', 'language', 'identifier', 'subject', 'description', 'publisher', 'rights', 'source', 'relation', 'coverage', 'type', 'format'];
    }

    /**
     * @param array<string, string> $record
     */
    private function dublinCoreMetadataElementXml(array $record): string
    {
        $element = $record['element'] ?? '';
        $value = $record['value'] ?? '';
        if ($element === '' || $value === '') {
            return '';
        }

        $attrs = [];
        foreach (['id', 'dir'] as $attribute) {
            if (($record[$attribute] ?? '') !== '') {
                $attrs[$attribute] = $record[$attribute];
            }
        }
        if (($record['lang'] ?? '') !== '') {
            $attrs['xml:lang'] = $record['lang'];
        }
        foreach ([
            'opf:file-as' => 'fileAs',
            'opf:role' => 'role',
            'opf:scheme' => 'scheme',
            'opf:authority' => 'authority',
            'opf:term' => 'term',
        ] as $attribute => $key) {
            if (($record[$key] ?? '') !== '') {
                $attrs[$attribute] = $record[$key];
            }
        }

        $xmlAttrs = '';
        foreach ($attrs as $attribute => $attributeValue) {
            $xmlAttrs .= ' ' . $attribute . '="' . $this->esc($attributeValue) . '"';
        }

        return '    <dc:' . $element . $xmlAttrs . '>' . $this->esc($value) . '</dc:' . $element . '>';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function dublinCoreMetadataNeedsOpfNamespace(array $meta): bool
    {
        foreach ($this->dublinCoreMetadataRecords($meta) as $record) {
            foreach (['fileAs', 'role', 'scheme', 'authority', 'term'] as $key) {
                if (($record[$key] ?? '') !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function packageVersion(array $meta): string
    {
        if ($this->isEpub2($meta)) {
            return '2.0';
        }

        // EPUB 3.x specifications keep the OPF package version value at 3.0.
        return '3.0';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function packageUniqueIdentifierId(array $meta): string
    {
        $sources = $this->preferMetadataOverOptions
            ? [$meta['epubPackageUniqueIdentifierId'] ?? null, $meta['packageUniqueIdentifierId'] ?? null]
            : [
                $this->options['packageUniqueIdentifierId'] ?? null,
                $this->options['epubPackageUniqueIdentifierId'] ?? null,
                $meta['epubPackageUniqueIdentifierId'] ?? null,
                $meta['packageUniqueIdentifierId'] ?? null,
            ];
        foreach ($sources as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $id = $this->validManifestId((string) $candidate);
            if ($id !== '') {
                return $id;
            }
        }

        return 'book-id';
    }

    /**
     * @param array<string, mixed> $meta
     */
    /**
     * @param array<string, array{media-type: string, properties: list<string>}> $manifestItems
     */
    private function bindingsXml(array $meta, array $manifestItems): string
    {
        $bindings = $this->optionOrMetaValue($meta, ['bindings'], ['epubBindings'], [], false);
        if (!is_array($bindings)) {
            return '';
        }

        $items = [];
        $seen = [];
        foreach ($bindings as $binding) {
            if (!is_array($binding)) {
                continue;
            }
            $mediaType = trim($this->entryString($binding, 'mediaType') ?: $this->entryString($binding, 'media-type'));
            if ($mediaType === '' || !$this->validMediaType($mediaType) || $this->bindingMediaTypeIsEpubCore($mediaType)) {
                continue;
            }
            $handler = $this->entryString($binding, 'handler');
            if ($handler === '' || !$this->validXmlId($handler) || !isset($manifestItems[$handler])) {
                continue;
            }
            $handlerItem = $manifestItems[$handler];
            if (
                !$this->mediaTypeMatches($handlerItem['media-type'], 'application/xhtml+xml')
                || !in_array('scripted', $handlerItem['properties'], true)
            ) {
                continue;
            }
            $key = $this->normalizedBindingMediaType($mediaType);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = '    <mediaType media-type="' . $this->esc($mediaType) . '" handler="' . $this->esc($handler) . '"/>';
        }
        if ($items === []) {
            return '';
        }

        return "  <bindings>\n" . implode("\n", $items) . "\n  </bindings>\n";
    }

    private function normalizedBindingMediaType(string $mediaType): string
    {
        $value = strtolower(trim($mediaType));
        $value = preg_replace('/\s*;\s*/', ';', $value) ?? $value;
        $value = preg_replace('/\s*=\s*/', '=', $value) ?? $value;

        return $value;
    }

    private function bindingMediaTypeIsEpubCore(string $mediaType): bool
    {
        $normalized = $this->normalizedBindingMediaType($mediaType);
        if ($normalized === 'audio/ogg;codecs=opus') {
            return true;
        }

        $baseType = explode(';', $normalized, 2)[0];

        return in_array($baseType, [
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/svg+xml',
            'image/webp',
            'audio/mpeg',
            'audio/mp4',
            'text/css',
            'font/ttf',
            'application/font-sfnt',
            'font/otf',
            'application/vnd.ms-opentype',
            'font/woff',
            'application/font-woff',
            'font/woff2',
            'application/xhtml+xml',
            'application/javascript',
            'application/ecmascript',
            'text/javascript',
            'application/x-dtbncx+xml',
            'application/smil+xml',
        ], true);
    }

    /**
     * @param array<string, mixed> $meta
     */
    /**
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function collectionsXml(array $meta, string $packageDir, string $packagePath, array $resourcePaths, array $xmlResourcePayloads, array $packageElementIds): string
    {
        $collections = $this->optionOrMetaValue($meta, ['collections'], ['epubCollections'], [], false);
        if (!is_array($collections)) {
            return '';
        }

        $xml = [];
        $declaredPrefixes = $this->packagePropertyPrefixNames($meta);
        $seenPackageIds = $packageElementIds;
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }
            $rendered = $this->collectionXml(
                $collection,
                $packageDir,
                $packagePath,
                $resourcePaths,
                $xmlResourcePayloads,
                2,
                $declaredPrefixes,
                $seenPackageIds
            );
            if ($rendered !== '') {
                $xml[] = $rendered;
            }
        }

        return $xml === [] ? '' : implode('', $xml);
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @param array<string, true> $declaredPrefixes
     * @param array<string, true> $seenPackageIds
     */
    private function collectionXml(
        array $collection,
        string $packageDir,
        string $packagePath,
        array $resourcePaths,
        array $xmlResourcePayloads,
        int $indent,
        array $declaredPrefixes,
        array &$seenPackageIds
    ): string {
        $role = $this->collectionRoleAttribute($this->entryString($collection, 'role'));
        if ($role === '') {
            return '';
        }

        $localSeenPackageIds = $seenPackageIds;
        $attrs = ['role' => $role];
        $id = $this->collectionIdAttribute($collection, $localSeenPackageIds);
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        $direction = strtolower($this->entryString($collection, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attrs['dir'] = $direction;
        }
        $language = $this->entryString($collection, 'lang');
        if ($language !== '' && $this->validXmlLanguageTag($language)) {
            $attrs['xml:lang'] = $language;
        }

        $pad = str_repeat(' ', $indent);
        $refinesSeenPackageIds = $localSeenPackageIds;
        $metadata = $this->collectionMetadataXml(
            $collection['metadata'] ?? [],
            $packageDir,
            $packagePath,
            $resourcePaths,
            $xmlResourcePayloads,
            $indent + 2,
            $declaredPrefixes,
            $this->collectionRefinesTargetIds(
                $collection,
                $packageDir,
                $packagePath,
                $resourcePaths,
                $xmlResourcePayloads,
                $declaredPrefixes,
                $refinesSeenPackageIds,
                $id
            ),
            $localSeenPackageIds
        );
        $children = '';
        $nested = $collection['collections'] ?? [];
        if (is_array($nested)) {
            foreach ($nested as $child) {
                if (is_array($child)) {
                    $children .= $this->collectionXml(
                        $child,
                        $packageDir,
                        $packagePath,
                        $resourcePaths,
                        $xmlResourcePayloads,
                        $indent + 2,
                        $declaredPrefixes,
                        $localSeenPackageIds
                    );
                }
            }
        }
        $links = $this->collectionLinksXml(
            $collection['links'] ?? [],
            $packageDir,
            $packagePath,
            $resourcePaths,
            $xmlResourcePayloads,
            $indent + 2,
            $declaredPrefixes,
            $localSeenPackageIds
        );

        if ($links === '' && $children === '') {
            return '';
        }

        $seenPackageIds = $localSeenPackageIds;

        $attrsXml = '';
        foreach ($attrs as $name => $value) {
            $attrsXml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $pad . '<collection' . $attrsXml . ">\n"
            . $metadata
            . $children
            . $links
            . $pad . "</collection>\n";
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<string, true> $seenPackageIds
     */
    private function collectionIdAttribute(array $collection, array &$seenPackageIds): string
    {
        $id = $this->validManifestId($this->entryString($collection, 'id'));
        if ($id === '' || isset($seenPackageIds[$id])) {
            return '';
        }

        $seenPackageIds[$id] = true;

        return $id;
    }

    private function collectionRoleAttribute(string $role): string
    {
        $tokens = [];
        foreach (preg_split('/\s+/', trim($role)) ?: [] as $token) {
            if (
                $token === ''
                || isset($tokens[$token])
                || !$this->validCollectionRoleToken($token)
                || $this->collectionRoleUsesReservedIdpfHost($token)
            ) {
                continue;
            }
            $tokens[$token] = true;
        }

        return implode(' ', array_keys($tokens));
    }

    private function validCollectionRoleToken(string $role): bool
    {
        return preg_match('/^[\p{L}\p{N}_.:-]+$/u', $role) === 1
            || $this->absoluteIriLike($role);
    }

    private function collectionRoleUsesReservedIdpfHost(string $role): bool
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//', $role) !== 1) {
            return false;
        }

        $host = parse_url($role, PHP_URL_HOST);

        return is_string($host) && str_contains(strtolower($host), 'idpf.org');
    }

    /**
     * @param array<string, true> $declaredPrefixes
     * @param array<string, true> $refinesTargetIds
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @param array<string, true> $seenPackageIds
     */
    private function collectionMetadataXml(mixed $metadata, string $packageDir, string $packagePath, array $resourcePaths, array $xmlResourcePayloads, int $indent, array $declaredPrefixes, array $refinesTargetIds, array &$seenPackageIds): string
    {
        if (!is_array($metadata) || $metadata === []) {
            return '';
        }

        $pad = str_repeat(' ', $indent);
        $itemPad = str_repeat(' ', $indent + 2);
        $items = [];
        $seenIds = [];
        foreach ($metadata as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ($this->entryString($entry, 'href') !== '' || strtolower($this->entryString($entry, 'name')) === 'link') {
                $linkId = $this->validManifestId($this->entryString($entry, 'id'));
                $candidate = $entry;
                if ($linkId !== '' && (isset($seenIds[$linkId]) || isset($seenPackageIds[$linkId]))) {
                    $candidate['id'] = '';
                }
                $refines = $this->validMetadataRefinesValue($this->entryString($candidate, 'refines'));
                if ($refines !== '' && !isset($refinesTargetIds[substr($refines, 1)])) {
                    $candidate['refines'] = '';
                }
                $link = $this->collectionLinkElementXml($candidate, $packageDir, $packagePath, $resourcePaths, $itemPad, $declaredPrefixes, true, true, $xmlResourcePayloads);
                if ($link !== '') {
                    if ($linkId !== '' && !isset($seenIds[$linkId]) && !isset($seenPackageIds[$linkId])) {
                        $seenIds[$linkId] = true;
                        $seenPackageIds[$linkId] = true;
                    }
                    $items[] = $link;
                }
                continue;
            }
            $value = $this->entryString($entry, 'value');
            if ($value === '') {
                continue;
            }
            $property = $this->entryString($entry, 'property') ?: $this->entryString($entry, 'name');
            if (
                $property === ''
                || !$this->validPropertyValue($property)
                || !$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)
            ) {
                continue;
            }
            $attrs = ' property="' . $this->esc($property) . '"';
            $id = $this->validManifestId($this->entryString($entry, 'id'));
            if ($id !== '' && !isset($seenIds[$id]) && !isset($seenPackageIds[$id])) {
                $attrs .= ' id="' . $this->esc($id) . '"';
                $seenIds[$id] = true;
                $seenPackageIds[$id] = true;
            }
            $refines = $this->validMetadataRefinesValue($this->entryString($entry, 'refines'));
            if ($refines !== '' && isset($refinesTargetIds[substr($refines, 1)])) {
                $attrs .= ' refines="' . $this->esc($refines) . '"';
            }
            $scheme = $this->entryString($entry, 'scheme');
            if (
                $scheme !== ''
                && $this->validPropertyValue($scheme)
                && $this->propertyValuePrefixIsDeclared($scheme, $declaredPrefixes)
            ) {
                $attrs .= ' scheme="' . $this->esc($scheme) . '"';
            }
            $direction = strtolower($this->entryString($entry, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $attrs .= ' dir="' . $this->esc($direction) . '"';
            }
            $language = $this->entryString($entry, 'lang') ?: $this->entryString($entry, 'xml:lang');
            if ($language !== '' && $this->validXmlLanguageTag($language)) {
                $attrs .= ' xml:lang="' . $this->esc($language) . '"';
            }
            $items[] = $itemPad . '<meta' . $attrs . '>' . $this->esc($value) . '</meta>';
        }
        if ($items === []) {
            return '';
        }

        return $pad . "<metadata>\n" . implode("\n", $items) . "\n" . $pad . "</metadata>\n";
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @param array<string, true> $declaredPrefixes
     * @param array<string, true> $seenPackageIds
     * @return array<string, true>
     */
    private function collectionRefinesTargetIds(
        array $collection,
        string $packageDir,
        string $packagePath,
        array $resourcePaths,
        array $xmlResourcePayloads,
        array $declaredPrefixes,
        array &$seenPackageIds,
        string $collectionElementId
    ): array {
        $ids = [];

        if ($collectionElementId !== '') {
            $ids[$collectionElementId] = true;
        }

        $metadata = $collection['metadata'] ?? [];
        if (is_array($metadata)) {
            $seenMetadataIds = [];
            foreach ($metadata as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entryId = $this->validManifestId($this->entryString($entry, 'id'));
                if ($entryId === '') {
                    continue;
                }
                if (isset($seenMetadataIds[$entryId]) || isset($seenPackageIds[$entryId])) {
                    continue;
                }
                if ($this->entryString($entry, 'href') !== '' || strtolower($this->entryString($entry, 'name')) === 'link') {
                    if ($this->collectionLinkElementXml($entry, $packageDir, $packagePath, $resourcePaths, '', $declaredPrefixes, true, true, $xmlResourcePayloads) !== '') {
                        $ids[$entryId] = true;
                        $seenMetadataIds[$entryId] = true;
                        $seenPackageIds[$entryId] = true;
                    }
                    continue;
                }

                $value = $this->entryString($entry, 'value');
                if ($value === '') {
                    continue;
                }
                $property = $this->entryString($entry, 'property') ?: $this->entryString($entry, 'name');
                if (
                    $property === ''
                    || !$this->validPropertyValue($property)
                    || !$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)
                ) {
                    continue;
                }

                $ids[$entryId] = true;
                $seenMetadataIds[$entryId] = true;
                $seenPackageIds[$entryId] = true;
            }
        }

        $nested = $collection['collections'] ?? [];
        if (is_array($nested)) {
            foreach ($nested as $child) {
                if (!is_array($child) || $this->collectionRoleAttribute($this->entryString($child, 'role')) === '') {
                    continue;
                }
                $childId = $this->collectionIdAttribute($child, $seenPackageIds);
                foreach ($this->collectionRefinesTargetIds(
                    $child,
                    $packageDir,
                    $packagePath,
                    $resourcePaths,
                    $xmlResourcePayloads,
                    $declaredPrefixes,
                    $seenPackageIds,
                    $childId
                ) as $targetId => $_) {
                    $ids[$targetId] = true;
                }
            }
        }

        $links = $collection['links'] ?? [];
        if (is_array($links)) {
            foreach ($links as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $linkId = $this->validManifestId($this->entryString($link, 'id'));
                if (
                    $linkId !== ''
                    && !isset($seenPackageIds[$linkId])
                    && $this->collectionLinkElementXml($link, $packageDir, $packagePath, $resourcePaths, '', $declaredPrefixes, false, false, $xmlResourcePayloads) !== ''
                ) {
                    $ids[$linkId] = true;
                    $seenPackageIds[$linkId] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @param array<string, true> $declaredPrefixes
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     * @param array<string, true> $seenPackageIds
     */
    private function collectionLinksXml(
        mixed $links,
        string $packageDir,
        string $packagePath,
        array $resourcePaths,
        array $xmlResourcePayloads,
        int $indent,
        array $declaredPrefixes,
        array &$seenPackageIds
    ): string {
        if (!is_array($links) || $links === []) {
            return '';
        }

        $pad = str_repeat(' ', $indent);
        $items = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $linkId = $this->validManifestId($this->entryString($link, 'id'));
            $candidate = $link;
            if ($linkId !== '' && isset($seenPackageIds[$linkId])) {
                $candidate['id'] = '';
            }
            $rendered = $this->collectionLinkElementXml(
                $candidate,
                $packageDir,
                $packagePath,
                $resourcePaths,
                $pad,
                $declaredPrefixes,
                false,
                false,
                $xmlResourcePayloads
            );
            if ($rendered !== '') {
                if ($linkId !== '' && !isset($seenPackageIds[$linkId])) {
                    $seenPackageIds[$linkId] = true;
                }
                $items[] = $rendered;
            }
        }

        return $items === [] ? '' : implode("\n", $items) . "\n";
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, true> $declaredPrefixes
     * @param array<string, true> $resourcePaths
     * @param array<string, string> $xmlResourcePayloads
     */
    private function collectionLinkElementXml(array $link, string $packageDir, string $packagePath, array $resourcePaths, string $pad, array $declaredPrefixes, bool $requireLocalMediaType = false, bool $allowRefines = false, array $xmlResourcePayloads = []): string
    {
        $href = $this->entryString($link, 'href');
        if ($href === '' || !$this->validPackageLinkHref($href)) {
            return '';
        }
        if ($this->packageLinkHrefTargetsPackageDocumentFragment($href, $packageDir, $packagePath)) {
            return '';
        }
        if (!$this->packageLinkHrefTargetsAvailableResource($href, $packageDir, $packagePath, $resourcePaths, $xmlResourcePayloads)) {
            return '';
        }
        $rel = $this->packageLinkRelationTokenList($link['rel'] ?? '', $declaredPrefixes, $allowRefines, $this->entryString($link, 'refines'));
        if ($rel === '') {
            return '';
        }
        $sanitizedLink = $link;
        $sanitizedLink['rel'] = $rel;
        $attrs = [
            'href' => $this->packageEntryHref($href, $packageDir),
            'rel' => $rel,
        ];
        $hreflang = $this->entryString($link, 'hreflang');
        if ($hreflang !== '' && $this->validXmlLanguageTag($hreflang)) {
            $attrs['hreflang'] = $hreflang;
        }
        $mediaType = $this->packageLinkMediaType($sanitizedLink, $href, $packageDir, $requireLocalMediaType);
        if ($mediaType === '' && $this->packageLinkRelRequiresMediaType($sanitizedLink)) {
            return '';
        }
        if ($mediaType !== '') {
            $attrs['media-type'] = $mediaType;
        }
        $refines = $this->packageLinkRefinesAttribute($sanitizedLink, $allowRefines);
        if ($refines !== '') {
            $attrs['refines'] = $refines;
        }
        $language = $this->entryString($link, 'lang') ?: $this->entryString($link, 'xml:lang');
        if ($language !== '' && $this->validXmlLanguageTag($language)) {
            $attrs['xml:lang'] = $language;
        }
        $direction = strtolower($this->entryString($link, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attrs['dir'] = $direction;
        }
        $id = $this->validManifestId($this->entryString($link, 'id'));
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        $properties = $this->packageLinkTokenList($link['properties'] ?? [], $declaredPrefixes);
        if ($properties !== '') {
            $attrs['properties'] = $properties;
        }

        $attrsXml = '';
        foreach ($attrs as $name => $value) {
            $attrsXml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $pad . '<link' . $attrsXml . '/>';
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function mediaOverlayIdsByContentPath(array $meta, string $packageDir): array
    {
        $overlays = [];
        $source = $this->optionOrMetaValue($meta, ['mediaOverlays'], ['epubMediaOverlays'], [], false);
        if (is_array($source)) {
            foreach ($source as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $overlayId = $this->validManifestId($this->entryString($entry, 'overlayId'));
                if ($overlayId === '') {
                    continue;
                }
                foreach (['contentPath', 'contentHref'] as $key) {
                    $path = $this->entryPackagePath($entry, $key, $packageDir);
                    if ($path !== '') {
                        $overlays[$path] = $overlayId;
                    }
                }
            }
        }

        $spineItems = $meta['epubSpineItemRefs'] ?? [];
        if (!is_array($spineItems)) {
            return $overlays;
        }
        foreach ($spineItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $overlayId = $this->validManifestId($this->entryString($entry, 'mediaOverlay'));
            if ($overlayId === '') {
                continue;
            }
            foreach (['path', 'href'] as $key) {
                $path = $this->entryPackagePath($entry, $key, $packageDir);
                if ($path !== '') {
                    $overlays[$path] = $overlayId;
                }
            }
        }

        return $overlays;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function mediaOverlayIdsBySpineIndex(array $meta): array
    {
        $source = $this->optionOrMetaValue($meta, ['mediaOverlays'], ['epubSpineItemRefs', 'epubMediaOverlays'], [], false);
        if (!is_array($source)) {
            return [];
        }

        $ids = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                $ids[] = '';
                continue;
            }
            $ids[] = $this->validManifestId($this->entryString($entry, 'mediaOverlay') ?: $this->entryString($entry, 'overlayId'));
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<list<string>>
     */
    private function spineManifestProperties(array $meta): array
    {
        $source = $this->optionOrMetaValue($meta, ['spineManifestProperties'], ['spineManifestProperties', 'epubSpineManifestProperties'], null, false);
        $fromSpineItemRefs = false;
        if (!is_array($source)) {
            $source = $meta['epubSpineItemRefs'] ?? [];
            $fromSpineItemRefs = true;
        }
        if (!is_array($source)) {
            return [];
        }

        $manifestProperties = [];
        foreach ($source as $entry) {
            if ($fromSpineItemRefs && is_array($entry) && $this->spineEntryIsNonLinear($entry)) {
                continue;
            }
            if ($fromSpineItemRefs && is_array($entry)) {
                $tokens = $this->propertyTokens($entry['manifestProperties'] ?? $entry['itemProperties'] ?? $entry['manifestItemProperties'] ?? []);
            } elseif (is_array($entry) && array_key_exists('properties', $entry)) {
                $tokens = $this->propertyTokens($entry['properties']);
            } else {
                $tokens = $this->propertyTokens($entry);
            }
            $manifestProperties[] = $tokens;
        }

        return $manifestProperties;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, string>>
     */
    private function spineManifestAttributes(array $meta): array
    {
        $source = $this->optionOrMetaValue($meta, ['spineManifestAttributes'], ['spineManifestAttributes', 'epubSpineManifestAttributes'], null, false);
        $fromSpineItemRefs = false;
        if (!is_array($source)) {
            $source = $meta['epubSpineItemRefs'] ?? [];
            $fromSpineItemRefs = true;
        }
        if (!is_array($source)) {
            return [];
        }

        $manifestAttributes = [];
        foreach ($source as $entry) {
            if ($fromSpineItemRefs && is_array($entry) && $this->spineEntryIsNonLinear($entry)) {
                continue;
            }
            if (!is_array($entry)) {
                $manifestAttributes[] = [];
                continue;
            }

            $attributes = [];
            foreach ([
                'fallback' => 'fallback',
                'fallbackStyle' => 'fallback-style',
                'fallback-style' => 'fallback-style',
                'mediaOverlay' => 'media-overlay',
                'media-overlay' => 'media-overlay',
            ] as $key => $attribute) {
                $value = $this->validManifestId($this->entryString($entry, $key));
                if ($value !== '') {
                    $attributes[$attribute] = $value;
                }
            }
            $manifestAttributes[] = $attributes;
        }

        return $manifestAttributes;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<list<string>>
     */
    private function spineItemProperties(array $meta): array
    {
        $source = $this->optionOrMetaValue($meta, ['spineItemProperties'], ['spineItemProperties', 'epubSpineItemProperties'], null, false);
        $fromSpineItemRefs = false;
        if (!is_array($source)) {
            $source = $meta['epubSpineItemRefs'] ?? [];
            $fromSpineItemRefs = true;
        }
        if (!is_array($source)) {
            return [];
        }

        $itemProperties = [];
        foreach ($source as $entry) {
            if ($fromSpineItemRefs && is_array($entry) && $this->spineEntryIsNonLinear($entry)) {
                continue;
            }
            if ($fromSpineItemRefs && is_array($entry)) {
                $tokens = $this->propertyTokens($entry['properties'] ?? []);
                $tokens = array_key_exists('properties', $entry)
                    ? $this->withRenditionSpineProperties($tokens, $entry)
                    : $this->withReadBackOnlyRenditionSpineProperties($tokens, $entry);
            } elseif (is_array($entry) && array_key_exists('properties', $entry)) {
                $tokens = $this->propertyTokens($entry['properties']);
                $tokens = $this->withRenditionSpineProperties($tokens, $entry);
            } elseif (is_array($entry)) {
                $tokens = $this->propertyTokens($entry);
                $tokens = $this->withRenditionSpineProperties($tokens, $entry);
            } else {
                $tokens = $this->propertyTokens($entry);
            }
            $itemProperties[] = array_values(array_unique($tokens));
        }

        return $itemProperties;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function spineItemIds(array $meta): array
    {
        $source = $this->optionOrMetaValue($meta, ['spineItemIds'], ['spineItemIds', 'epubSpineItemIds'], null, false);
        $fromSpineItemRefs = false;
        if (!is_array($source)) {
            $source = $meta['epubSpineItemRefs'] ?? [];
            $fromSpineItemRefs = true;
        }
        if (!is_array($source)) {
            return [];
        }

        $ids = [];
        $seen = [];
        foreach ($source as $entry) {
            if ($fromSpineItemRefs && is_array($entry) && $this->spineEntryIsNonLinear($entry)) {
                continue;
            }
            $id = is_array($entry)
                ? $this->entrySpineItemId($entry)
                : $this->validManifestId(is_scalar($entry) ? (string) $entry : '');
            if ($id !== '' && !isset($seen[$id])) {
                $ids[] = $id;
                $seen[$id] = true;
                continue;
            }

            $ids[] = '';
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entrySpineItemId(array $entry): string
    {
        foreach (['id', 'itemrefId', 'spineItemId'] as $key) {
            $id = $this->validManifestId($this->entryString($entry, $key));
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $resourceManifestIds
     * @param array<string, string> $resourceMediaTypes
     * @param array<string, array<string, string>> $resourceAttributes
     * @param array<string, true> $seenSpineIds
     * @param array<string, true> $seenItemrefIds
     * @return list<string>
     */
    private function nonLinearSpineItemRefs(array $meta, string $packageDir, array $resourceManifestIds, array $resourceMediaTypes, array $resourceAttributes, array &$seenSpineIds, array &$seenItemrefIds = []): array
    {
        $source = null;
        $explicitSource = false;
        $readMetaSource = function () use ($meta, &$source, &$explicitSource): void {
            foreach (['nonLinearSpineItems', 'epubNonLinearSpineItemRefs'] as $key) {
                if (array_key_exists($key, $meta) && is_array($meta[$key])) {
                    $source = $meta[$key];
                    $explicitSource = true;
                    return;
                }
            }
        };
        if ($this->preferMetadataOverOptions) {
            $readMetaSource();
        }
        if (!$explicitSource) {
            $source = $this->options['nonLinearSpineItems'] ?? null;
            $explicitSource = is_array($source);
        }
        if (!$explicitSource && !$this->preferMetadataOverOptions) {
            $readMetaSource();
        }
        if (!$explicitSource) {
            $source = $meta['epubSpineItemRefs'] ?? [];
        }
        if (!is_array($source)) {
            return [];
        }

        $itemrefs = [];
        $isEpub2 = $this->isEpub2($meta);
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (!$explicitSource && !$this->spineEntryIsNonLinear($entry)) {
                continue;
            }

            $path = '';
            foreach (['path', 'href', 'contentPath', 'contentHref'] as $key) {
                $path = $this->entryPackagePath($entry, $key, $packageDir);
                if ($path !== '') {
                    break;
                }
            }
            if ($path === '' || !isset($resourceManifestIds[$path])) {
                continue;
            }

            $mediaType = $this->entryString($entry, 'mediaType') ?: $this->entryString($entry, 'media-type');
            if ($mediaType === '') {
                $mediaType = $resourceMediaTypes[$path] ?? $this->mediaType($path);
            }
            if (
                !$this->readableSpineMediaType($mediaType)
                && !$this->nonLinearSpineResourceHasReadableFallback($entry, $path, $resourceManifestIds, $resourceMediaTypes, $resourceAttributes)
            ) {
                continue;
            }

            $id = $resourceManifestIds[$path];
            if (isset($seenSpineIds[$id])) {
                continue;
            }
            $seenSpineIds[$id] = true;
            $itemrefId = $this->entrySpineItemId($entry);
            if ($itemrefId !== '' && isset($seenItemrefIds[$itemrefId])) {
                $itemrefId = '';
            }
            if ($itemrefId !== '') {
                $seenItemrefIds[$itemrefId] = true;
            }
            $properties = $this->withRenditionSpineProperties($this->propertyTokens($entry['properties'] ?? []), $entry, $explicitSource || array_key_exists('properties', $entry));
            $itemrefs[] = '    <itemref'
                . ($itemrefId === '' ? '' : ' id="' . $this->esc($itemrefId) . '"')
                . ' idref="' . $this->esc($id) . '" linear="no"'
                . ($isEpub2 || $properties === [] ? '' : ' properties="' . $this->esc(implode(' ', $properties)) . '"')
                . '/>';
        }

        return $itemrefs;
    }

    private function readableSpineMediaType(string $mediaType): bool
    {
        $mediaType = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return in_array($mediaType, ['application/xhtml+xml', 'text/html'], true);
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string> $resourceManifestIds
     * @param array<string, string> $resourceMediaTypes
     * @param array<string, array<string, string>> $resourceAttributes
     */
    private function nonLinearSpineResourceHasReadableFallback(array $entry, string $path, array $resourceManifestIds, array $resourceMediaTypes, array $resourceAttributes): bool
    {
        $fallback = $this->validManifestId($this->entryString($entry, 'fallback'));
        if ($fallback === '') {
            $fallback = $this->validManifestId($resourceAttributes[$path]['fallback'] ?? '');
        }
        if ($fallback === '') {
            return false;
        }

        $pathsById = array_flip($resourceManifestIds);
        $seen = [];
        while ($fallback !== '' && !isset($seen[$fallback])) {
            $seen[$fallback] = true;
            $targetPath = $pathsById[$fallback] ?? '';
            if ($targetPath === '') {
                return false;
            }

            $mediaType = $resourceMediaTypes[$targetPath] ?? $this->mediaType($targetPath);
            if ($this->readableSpineMediaType($mediaType)) {
                return true;
            }

            $fallback = $this->validManifestId($resourceAttributes[$targetPath]['fallback'] ?? '');
        }

        return false;
    }

    /**
     * @param list<string> $properties
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function withRenditionSpineProperties(array $properties, array $entry, bool $derive = true): array
    {
        if (!$derive) {
            return $properties;
        }

        foreach ($this->renditionSpinePropertyConfig() as $prefix => $config) {
            $hasProperty = false;
            foreach ($properties as $property) {
                if (str_starts_with(strtolower(trim($property)), $prefix)) {
                    $hasProperty = true;
                    break;
                }
            }
            if ($hasProperty) {
                continue;
            }

            $value = '';
            foreach ($config['keys'] as $key) {
                $value = $this->entryString($entry, $key);
                if ($value !== '') {
                    break;
                }
            }

            $property = $this->renditionSpineProperty($prefix, $value, $config['allowed']);
            if ($property !== '') {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * @param list<string> $properties
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function withRenditionFlowSpineProperty(array $properties, array $entry): array
    {
        return $this->withRenditionSpinePropertyPrefix($properties, $entry, 'rendition:flow-');
    }

    /**
     * @param list<string> $properties
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function withReadBackOnlyRenditionSpineProperties(array $properties, array $entry): array
    {
        foreach (['rendition:page-spread-', 'rendition:flow-'] as $prefix) {
            $properties = $this->withRenditionSpinePropertyPrefix($properties, $entry, $prefix);
        }

        return $properties;
    }

    /**
     * @return array<string, array{keys: list<string>, allowed: list<string>}>
     */
    private function renditionSpinePropertyConfig(): array
    {
        return [
            'rendition:page-spread-' => [
                'keys' => ['pageSpread', 'epubPageSpread', 'spreadPage'],
                'allowed' => ['left', 'right', 'center'],
            ],
            'rendition:layout-' => [
                'keys' => ['renditionLayout', 'epubRenditionLayout', 'layout'],
                'allowed' => ['reflowable', 'pre-paginated'],
            ],
            'rendition:orientation-' => [
                'keys' => ['renditionOrientation', 'epubRenditionOrientation', 'orientation'],
                'allowed' => ['landscape', 'portrait', 'auto'],
            ],
            'rendition:spread-' => [
                'keys' => ['renditionSpread', 'epubRenditionSpread', 'spread'],
                'allowed' => ['none', 'landscape', 'portrait', 'both', 'auto'],
            ],
            'rendition:flow-' => [
                'keys' => ['renditionFlow', 'epubRenditionFlow', 'flow'],
                'allowed' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto'],
            ],
        ];
    }

    /**
     * @param list<string> $allowed
     */
    private function renditionSpineProperty(string $prefix, string $value, array $allowed): string
    {
        $value = strtolower(trim($value));
        if (!in_array($value, $allowed, true)) {
            return '';
        }

        return $prefix . $value;
    }

    /**
     * @param list<string> $properties
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function withRenditionSpinePropertyPrefix(array $properties, array $entry, string $prefix): array
    {
        $config = $this->renditionSpinePropertyConfig()[$prefix] ?? null;
        if ($config === null) {
            return $properties;
        }

        foreach ($properties as $property) {
            if (str_starts_with(strtolower(trim($property)), $prefix)) {
                return $properties;
            }
        }

        $value = '';
        foreach ($config['keys'] as $key) {
            $value = $this->entryString($entry, $key);
            if ($value !== '') {
                break;
            }
        }

        $property = $this->renditionSpineProperty($prefix, $value, $config['allowed']);
        if ($property !== '') {
            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function spineEntryIsNonLinear(array $entry): bool
    {
        if (!array_key_exists('linear', $entry)) {
            return false;
        }

        $linear = $entry['linear'];
        if (is_bool($linear)) {
            return !$linear;
        }
        if (is_int($linear) || is_float($linear)) {
            return (int) $linear === 0;
        }
        if (!is_string($linear)) {
            return false;
        }

        return in_array(strtolower(trim($linear)), ['no', 'false', '0'], true);
    }

    /**
     * @return list<string>
     */
    private function propertyTokens(mixed $value): array
    {
        $tokens = [];
        if (is_string($value)) {
            $tokens = preg_split('/\s+/', trim($value)) ?: [];
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $tokens[] = (string) $item;
                }
            }
        } elseif (is_scalar($value)) {
            $tokens = preg_split('/\s+/', trim((string) $value)) ?: [];
        }

        $tokens = array_map(static fn (string $token): string => trim($token), $tokens);
        $tokens = array_filter($tokens, fn (string $token): bool => $this->isPropertyToken($token));

        return array_values(array_unique($tokens));
    }

    private function isPropertyToken(string $token): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $token) === 1;
    }

    /**
     * @return list<string>
     */
    private function chapterManifestProperties(AstNode $document, array $meta = [], int $chapterIndex = -1): array
    {
        $properties = [];
        if ($this->nodeContainsMathML($document)) {
            $properties[] = 'mathml';
        }
        if ($this->nodeContainsSvg($document)) {
            $properties[] = 'svg';
        }
        if ($this->nodeContainsScriptedContent($document) || $this->chapterHasHeadScripts($meta, $chapterIndex)) {
            $properties[] = 'scripted';
        }
        if ($this->nodeContainsEpubSwitch($document)) {
            $properties[] = 'switch';
        }
        if (
            $this->nodeContainsRemoteResource($document)
            || $this->chapterHeadBasesContainRemoteResource($meta, $chapterIndex)
            || $this->chapterHeadLinksContainRemoteResource($meta, $chapterIndex)
            || $this->chapterHeadStylesContainRemoteResource($meta, $chapterIndex)
            || $this->chapterHeadScriptsContainRemoteResource($meta, $chapterIndex)
        ) {
            $properties[] = 'remote-resources';
        }

        return $properties;
    }

    private function chapterHasHeadScripts(array $meta, int $chapterIndex): bool
    {
        return $this->spineHeadScripts($meta, $chapterIndex) !== [];
    }

    private function chapterHeadScriptsContainRemoteResource(array $meta, int $chapterIndex): bool
    {
        foreach ($this->spineHeadScripts($meta, $chapterIndex) as $script) {
            $src = $this->entryString($script, 'src');
            if ($src !== '' && $this->isRemoteResourceUrl($src)) {
                return true;
            }
        }

        return false;
    }

    private function chapterHeadBasesContainRemoteResource(array $meta, int $chapterIndex): bool
    {
        foreach ($this->spineHeadBases($meta, $chapterIndex) as $base) {
            $href = $this->entryString($base, 'href');
            if ($href !== '' && $this->isRemoteResourceUrl($href)) {
                return true;
            }
        }

        return false;
    }

    private function chapterHeadLinksContainRemoteResource(array $meta, int $chapterIndex): bool
    {
        foreach ($this->spineHeadLinks($meta, $chapterIndex) as $link) {
            $href = $this->entryString($link, 'href');
            if ($href !== '' && $this->isRemoteResourceUrl($href)) {
                return true;
            }
            $imagesrcset = $this->entryString($link, 'imagesrcset') ?: $this->entryString($link, 'imageSrcset');
            if ($imagesrcset !== '' && $this->srcsetContainsRemoteResource($imagesrcset)) {
                return true;
            }
        }

        return false;
    }

    private function chapterHeadStylesContainRemoteResource(array $meta, int $chapterIndex): bool
    {
        foreach ($this->spineHeadStyles($meta, $chapterIndex) as $style) {
            $css = $this->entryString($style, 'css') ?: $this->entryString($style, 'content') ?: $this->entryString($style, 'text');
            if ($css !== '' && $this->cssContainsRemoteResource($css)) {
                return true;
            }
        }

        return false;
    }

    private function nodeContainsMathML(AstNode $node): bool
    {
        if ($node->type === 'math') {
            $mathml = $node->attr('mathml', $node->attr('html', ''));
            if (is_scalar($mathml) && $this->containsMathML((string) $mathml)) {
                return true;
            }

            return trim((string) $node->attr('text', '')) !== '';
        }

        if (in_array($node->type, ['raw_html', 'raw_html_inline'], true)) {
            if ($this->containsMathML((string) $node->attr('html', $node->attr('text', '')))) {
                return true;
            }
        }

        if (in_array($node->type, ['raw_block', 'raw_inline'], true)) {
            $format = strtolower((string) $node->attr('format', ''));
            if ($this->isRawHtmlFormat($format) && $this->containsMathML((string) $node->attr('text', $node->attr('html', '')))) {
                return true;
            }
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsMathML($child)) {
                return true;
            }
        }

        return false;
    }

    private function containsMathML(string $html): bool
    {
        return preg_match('/<math(?:\s|>|\/)/i', $html) === 1;
    }

    private function nodeContainsSvg(AstNode $node): bool
    {
        $html = $this->nodeRawHtml($node);
        if ($html !== '' && preg_match('/<svg(?:\s|>|\/)/i', $html) === 1) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsSvg($child)) {
                return true;
            }
        }

        return false;
    }

    private function nodeContainsScriptedContent(AstNode $node): bool
    {
        $html = $this->nodeRawHtml($node);
        if ($html !== '' && $this->htmlContainsScriptedContent($html)) {
            return true;
        }

        if ($this->nodeIndicatesEpubTrigger($node)) {
            return true;
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes)) {
            foreach ($attributes as $name => $_value) {
                if (is_string($name) && preg_match('/^on[A-Za-z]/', $name) === 1) {
                    return true;
                }
            }
        }
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes)) {
            foreach ($htmlAttributes as $name => $_value) {
                if (is_string($name) && preg_match('/^on[A-Za-z]/', $name) === 1) {
                    return true;
                }
            }
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsScriptedContent($child)) {
                return true;
            }
        }

        return false;
    }

    private function htmlContainsScriptedContent(string $html): bool
    {
        return preg_match('/<script(?:\s|>|\/)/i', $html) === 1
            || preg_match('/<form(?:\s|>|\/)/i', $html) === 1
            || preg_match('/<(?:epub:)?trigger(?:\s|>|\/)/i', $html) === 1
            || preg_match('/\son[A-Za-z][A-Za-z0-9_.:-]*\s*=/i', $html) === 1
            || preg_match('/(?<![\w-])(?:href|src|action|formaction)\s*=\s*(["\'])\s*javascript:/i', $html) === 1;
    }

    private function nodeIndicatesEpubTrigger(AstNode $node): bool
    {
        if ($node->type !== 'span') {
            return false;
        }

        $classes = $node->attr('classes', []);
        if (!is_array($classes) || !in_array('trigger', $classes, true)) {
            return false;
        }

        return $this->attributeSetIndicatesEpubTrigger($node->attr('attributes', []))
            || $this->attributeSetIndicatesEpubTrigger($node->attr('htmlAttributes', []));
    }

    private function attributeSetIndicatesEpubTrigger(mixed $attributes): bool
    {
        if (!is_array($attributes)) {
            return false;
        }

        foreach (['observer', 'ev:observer', 'event', 'ev:event', 'action', 'ref'] as $name) {
            $value = $attributes[$name] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function nodeContainsEpubSwitch(AstNode $node): bool
    {
        $html = $this->nodeRawHtml($node);
        if ($html !== '' && $this->resourceContainsEpubSwitch($html)) {
            return true;
        }

        if ($this->nodeIndicatesEpubSwitch($node)) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsEpubSwitch($child)) {
                return true;
            }
        }

        return false;
    }

    private function nodeIndicatesEpubSwitch(AstNode $node): bool
    {
        if ($node->type !== 'div' || !$this->nodeHasClass($node, 'switch')) {
            return false;
        }

        foreach ($node->children as $child) {
            if ($child->type === 'div' && $this->nodeIndicatesEpubSwitchCase($child)) {
                return true;
            }
        }

        return false;
    }

    private function nodeIndicatesEpubSwitchCase(AstNode $node): bool
    {
        if (!$this->nodeHasClass($node, 'case')) {
            return false;
        }

        return $this->attributeSetIndicatesEpubSwitchCase($node->attr('attributes', []))
            || $this->attributeSetIndicatesEpubSwitchCase($node->attr('htmlAttributes', []));
    }

    private function attributeSetIndicatesEpubSwitchCase(mixed $attributes): bool
    {
        if (!is_array($attributes)) {
            return false;
        }

        foreach (['required-namespace', 'requiredNamespace', 'required-modules', 'requiredModules'] as $name) {
            $value = $attributes[$name] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function nodeHasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attr('classes', []);

        return is_array($classes) && in_array($class, $classes, true);
    }

    private function nodeContainsRemoteResource(AstNode $node): bool
    {
        if ($node->type === 'image') {
            $source = (string) $node->attr('url', $node->attr('src', ''));
            if ($this->isRemoteResourceUrl($source)) {
                return true;
            }
            $htmlAttributes = $node->attr('htmlAttributes', []);
            if (is_array($htmlAttributes) && isset($htmlAttributes['srcset']) && $this->srcsetContainsRemoteResource((string) $htmlAttributes['srcset'])) {
                return true;
            }
        }

        if (
            $this->attributeSetContainsRemoteResource($node->attr('attributes', []))
            || $this->attributeSetContainsRemoteResource($node->attr('htmlAttributes', []))
        ) {
            return true;
        }

        $html = $this->nodeRawHtml($node);
        if ($html !== '' && $this->htmlContainsRemoteResource($html)) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsRemoteResource($child)) {
                return true;
            }
        }

        return false;
    }

    private function attributeSetContainsRemoteResource(mixed $attributes): bool
    {
        if (!is_array($attributes)) {
            return false;
        }

        foreach ($attributes as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $name = strtolower((string) $name);
            $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (
                in_array($name, ['src', 'poster', 'data', 'action', 'formaction', 'background', 'textref'], true)
                && $this->isRemoteResourceUrl($value)
            ) {
                return true;
            }
            if (in_array($name, ['srcset', 'imagesrcset'], true) && $this->srcsetContainsRemoteResource($value)) {
                return true;
            }
            if ($name === 'style' && $this->cssContainsRemoteResource($value)) {
                return true;
            }
        }

        return false;
    }

    private function htmlContainsRemoteResource(string $html): bool
    {
        return $this->resourceContainsRemoteReference($html);
    }

    private function srcsetContainsRemoteResource(string $value): bool
    {
        foreach ($this->srcsetCandidates($value) as $candidate) {
            if ($this->isRemoteResourceUrl($candidate['url'])) {
                return true;
            }
        }

        return false;
    }

    private function cssContainsRemoteResource(string $css): bool
    {
        if (preg_match_all('/url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $url = trim((string) ($match[2] ?? ($match[3] ?? '')));
                if ($url !== '' && $this->isRemoteResourceUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/@import\s+(["\'])([^"\']+)\1/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $url = trim((string) ($match[2] ?? ''));
                if ($url !== '' && $this->isRemoteResourceUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isRemoteResourceUrl(string $url): bool
    {
        $url = strtolower(trim($url));

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function nodeRawHtml(AstNode $node): string
    {
        if (in_array($node->type, ['raw_html', 'raw_html_inline'], true)) {
            return (string) $node->attr('html', $node->attr('text', ''));
        }

        if (in_array($node->type, ['raw_block', 'raw_inline'], true)) {
            $format = strtolower((string) $node->attr('format', ''));
            if ($this->isRawHtmlFormat($format)) {
                return (string) $node->attr('text', $node->attr('html', ''));
            }
        }

        return '';
    }

    private function isRawHtmlFormat(string $format): bool
    {
        return in_array($format, ['html', 'html4', 'html5', 'epub', 'epub2', 'epub3'], true);
    }

    private function addEpubSpineSemantics(string $html): string
    {
        $html = preg_replace_callback(
            '/<a\b(?=[^>]*\brole=(["\'])doc-noteref\1)[^>]*>/i',
            fn (array $match): string => $this->appendMissingTagAttributes($match[0], ['epub:type' => 'noteref']),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<div\b(?=[^>]*\bclass=(["\'])(?=[^"\']*\bfootnotes\b)[^"\']*\1)[^>]*>/i',
            fn (array $match): string => $this->appendMissingTagAttributes($match[0], [
                'epub:type' => 'footnotes',
                'role' => 'doc-endnotes',
            ]),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<li\b(?=[^>]*\bid=(["\'])fn\d+\1)[^>]*>/i',
            fn (array $match): string => $this->appendMissingTagAttributes($match[0], [
                'epub:type' => 'footnote',
                'role' => 'doc-footnote',
            ]),
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/<(span|div)\b(?=[^>]*\bclass=(["\'])(?=[^"\']*\b(?:pagebreak|page-break|doc-pagebreak)\b)[^"\']*\2)[^>]*>/i',
            fn (array $match): string => $this->appendMissingTagAttributes($match[0], [
                'role' => 'doc-pagebreak',
                'epub:type' => 'pagebreak',
            ]),
            $html
        ) ?? $html;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendMissingTagAttributes(string $tag, array $attributes): string
    {
        $insert = '';
        foreach ($attributes as $name => $value) {
            if ($this->tagHasAttribute($tag, $name)) {
                continue;
            }
            $insert .= ' ' . $name . '="' . $this->esc($value) . '"';
        }
        if ($insert === '') {
            return $tag;
        }

        $trimmed = rtrim($tag);
        $selfClosing = str_ends_with($trimmed, '/>');
        $prefix = $selfClosing ? rtrim(substr($trimmed, 0, -2)) : rtrim(substr($trimmed, 0, -1));

        return $prefix . $insert . ($selfClosing ? ' />' : '>');
    }

    private function tagHasAttribute(string $tag, string $name): bool
    {
        return preg_match('/\s' . preg_quote($name, '/') . '\s*=/i', $tag) === 1;
    }

    private function normalizeXhtmlBodyFragment(string $html): string
    {
        $html = $this->escapeRawTextElementContent($html);
        $html = $this->normalizeHtmlNamedEntitiesForXml($html);

        return preg_replace_callback(
            '/<([A-Za-z][A-Za-z0-9:-]*)([^<>]*?)>/s',
            function (array $match): string {
                $tag = $match[1];
                $tail = $this->normalizeBooleanAttributesForXml($match[2]);
                if (!$this->isHtmlVoidElement($tag) || str_ends_with(rtrim($tail), '/')) {
                    return '<' . $tag . $tail . '>';
                }

                return '<' . $tag . rtrim($tail) . ' />';
            },
            $html
        ) ?? $html;
    }

    private function escapeRawTextElementContent(string $html): string
    {
        return preg_replace_callback(
            '/<(script|style)\b([^>]*)>(.*?)<\/\1>/is',
            function (array $match): string {
                $body = $match[3];
                if (!str_contains($body, '<') && !str_contains($body, '&')) {
                    return $match[0];
                }

                return '<' . $match[1] . $match[2] . '>'
                    . $this->escapeXmlTextPreservingEntities($body)
                    . '</' . $match[1] . '>';
            },
            $html
        ) ?? $html;
    }

    private function escapeXmlTextPreservingEntities(string $text): string
    {
        $text = $this->normalizeHtmlNamedEntitiesForXml($text);

        return str_replace('<', '&lt;', $text);
    }

    private function normalizeHtmlNamedEntitiesForXml(string $html): string
    {
        $html = preg_replace_callback(
            '/&([A-Za-z][A-Za-z0-9]+);/',
            static function (array $match): string {
                $name = strtolower($match[1]);
                if (in_array($name, ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
                    return $match[0];
                }

                $decoded = html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($decoded !== $match[0]) {
                    return $decoded;
                }

                return '&amp;' . $match[1] . ';';
            },
            $html
        ) ?? $html;

        return preg_replace('/&(?!#x[0-9A-Fa-f]+;|#[0-9]+;|amp;|lt;|gt;|quot;|apos;)/', '&amp;', $html) ?? $html;
    }

    private function normalizeBooleanAttributesForXml(string $tail): string
    {
        $out = '';
        $length = strlen($tail);
        for ($index = 0; $index < $length;) {
            $char = $tail[$index];
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $start = $index;
                $index++;
                while ($index < $length && $tail[$index] !== $quote) {
                    $index++;
                }
                if ($index < $length) {
                    $index++;
                }
                $out .= substr($tail, $start, $index - $start);
                continue;
            }

            if (preg_match('/[A-Za-z_:]/', $char) !== 1) {
                $out .= $char;
                $index++;
                continue;
            }

            $start = $index;
            $index++;
            while ($index < $length && preg_match('/[A-Za-z0-9_.:-]/', $tail[$index]) === 1) {
                $index++;
            }
            $name = substr($tail, $start, $index - $start);
            $probe = $index;
            while ($probe < $length && ctype_space($tail[$probe])) {
                $probe++;
            }
            if ($probe < $length && $tail[$probe] === '=') {
                $out .= $name;
                continue;
            }
            if ($this->isHtmlBooleanAttribute($name)) {
                $out .= $name . '="' . strtolower($name) . '"';
                continue;
            }

            $out .= $name;
        }

        return $out;
    }

    private function isHtmlVoidElement(string $tag): bool
    {
        return in_array(strtolower($tag), [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
        ], true);
    }

    private function isHtmlBooleanAttribute(string $attribute): bool
    {
        return in_array(strtolower($attribute), [
            'allowfullscreen',
            'async',
            'autofocus',
            'autoplay',
            'checked',
            'controls',
            'default',
            'defer',
            'disabled',
            'hidden',
            'itemscope',
            'loop',
            'multiple',
            'muted',
            'nomodule',
            'open',
            'playsinline',
            'readonly',
            'required',
            'reversed',
            'scoped',
            'selected',
        ], true);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function optionOrMetaString(array $meta, string $optionKey, string $metaKey, bool $allowOptionFallbackWhenPreferring = true): string
    {
        $metaKeys = array_values(array_unique([$metaKey, $optionKey]));
        $optionKeys = array_values(array_unique([$optionKey, $metaKey]));

        if ($this->preferMetadataOverOptions) {
            $metaValue = $this->metaStringFromKeys($meta, $metaKeys);
            if ($metaValue !== null) {
                return $metaValue;
            }
        }
        if ($this->preferMetadataOverOptions && !$allowOptionFallbackWhenPreferring) {
            return '';
        }
        foreach ($optionKeys as $key) {
            $value = $this->options[$key] ?? null;
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return $this->metaStringFromKeys($meta, $metaKeys) ?? '';
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     */
    private function metaStringFromKeys(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $meta)) {
                return $this->metaString($meta, $key, '');
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $optionKeys
     * @param list<string> $metaKeys
     */
    private function optionOrMetaValue(array $meta, array $optionKeys, array $metaKeys, mixed $default = [], bool $allowOptionFallbackWhenPreferring = true): mixed
    {
        $optionKeys = array_values(array_unique(array_merge($optionKeys, $metaKeys)));
        $metaKeys = array_values(array_unique(array_merge($metaKeys, $optionKeys)));
        $orderedSources = $this->preferMetadataOverOptions
            ? (
                $allowOptionFallbackWhenPreferring
                    ? [[$meta, $metaKeys], [$this->options, $optionKeys]]
                    : [[$meta, $metaKeys]]
            )
            : [[$this->options, $optionKeys], [$meta, $metaKeys]];

        foreach ($orderedSources as [$source, $keys]) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== null) {
                    return $source[$key];
                }
            }
        }

        return $default;
    }

    /**
     * @param array<string, string> $resources
     */
    private function rewriteRenderedResourceUrls(string $html, array $resources, string $packageDir, string $chapterDir): string
    {
        if ($resources === []) {
            return $html;
        }

        $html = preg_replace_callback('/(?<![\w-])(src|href|poster|data)=(["\'])([^"\']+)\2/i', function (array $match) use ($resources, $packageDir, $chapterDir): string {
            $url = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->mappedResourceReference($url, $resources, $packageDir, $chapterDir);
            if ($rewritten === null || $rewritten === $url) {
                return $match[0];
            }

            return $match[1] . '=' . $match[2] . $this->esc($rewritten) . $match[2];
        }, $html) ?? $html;

        $html = preg_replace_callback('/(?<![\w-])srcset=(["\'])([^"\']+)\1/i', function (array $match) use ($resources, $packageDir, $chapterDir): string {
            $rewritten = $this->rewriteRenderedSrcsetValue(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $resources, $packageDir, $chapterDir);
            if ($rewritten === $match[2]) {
                return $match[0];
            }

            return 'srcset=' . $match[1] . $this->esc($rewritten) . $match[1];
        }, $html) ?? $html;

        $html = preg_replace_callback('/(?<![\w-])style=(["\'])([^"\']*)\1/i', function (array $match) use ($resources, $packageDir, $chapterDir): string {
            $style = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteRenderedCssResourceReferences($style, $resources, $packageDir, $chapterDir);
            if ($rewritten === $style) {
                return $match[0];
            }

            return 'style=' . $match[1] . $this->esc($rewritten) . $match[1];
        }, $html) ?? $html;

        return preg_replace_callback('/<style\b([^>]*)>(.*?)<\/style>/is', function (array $match) use ($resources, $packageDir, $chapterDir): string {
            $css = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteRenderedCssResourceReferences($css, $resources, $packageDir, $chapterDir);
            if ($rewritten === $css) {
                return $match[0];
            }

            return '<style' . $match[1] . '>' . htmlspecialchars($rewritten, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</style>';
        }, $html) ?? $html;
    }

    /**
     * @param array<string, string> $resources
     */
    private function rewriteRenderedSrcsetValue(string $value, array $resources, string $packageDir, string $chapterDir): string
    {
        $candidates = $this->srcsetCandidates($value);
        if ($candidates === []) {
            return $value;
        }

        $changed = false;
        $rewritten = [];
        foreach ($candidates as $candidate) {
            $url = $candidate['url'];
            $descriptor = $candidate['descriptor'];
            $mapped = $this->mappedResourceReference($url, $resources, $packageDir, $chapterDir);
            if ($mapped !== null && $mapped !== $url) {
                $url = $mapped;
                $changed = true;
            }

            $rewritten[] = $descriptor === '' ? $url : $url . ' ' . $descriptor;
        }

        return $changed ? implode(', ', $rewritten) : $value;
    }

    /**
     * @param array<string, string> $resources
     */
    private function rewriteRenderedCssResourceReferences(string $css, array $resources, string $packageDir, string $fromDir): string
    {
        $css = $this->rewriteRenderedCssResourceUrls($css, $resources, $packageDir, $fromDir);

        return $this->rewriteRenderedCssImportStringResourceUrls($css, $resources, $packageDir, $fromDir);
    }

    /**
     * @param array<string, string> $resources
     */
    private function rewriteRenderedCssResourceUrls(string $css, array $resources, string $packageDir, string $fromDir): string
    {
        if ($resources === []) {
            return $css;
        }

        return preg_replace_callback('/url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)/is', function (array $match) use ($resources, $packageDir, $fromDir): string {
            $quote = $match[1] ?? '';
            $url = trim($match[2] ?? ($match[3] ?? ''));
            if ($url === '') {
                return $match[0];
            }

            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $mapped = $this->mappedResourceReference($url, $resources, $packageDir, $fromDir);
            if ($mapped === null || $mapped === $url) {
                return $match[0];
            }

            return 'url(' . $this->cssUrlLiteral($mapped, $quote) . ')';
        }, $css) ?? $css;
    }

    /**
     * @param array<string, string> $resources
     */
    private function rewriteRenderedCssImportStringResourceUrls(string $css, array $resources, string $packageDir, string $fromDir): string
    {
        if ($resources === []) {
            return $css;
        }

        return preg_replace_callback('/(@import\s+)(["\'])([^"\']+)\2/is', function (array $match) use ($resources, $packageDir, $fromDir): string {
            $url = trim($match[3]);
            if ($url === '') {
                return $match[0];
            }

            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $mapped = $this->mappedResourceReference($url, $resources, $packageDir, $fromDir);
            if ($mapped === null || $mapped === $url) {
                return $match[0];
            }

            return $match[1] . $this->cssUrlLiteral($mapped, $match[2]);
        }, $css) ?? $css;
    }

    private function cssUrlLiteral(string $url, string $preferredQuote = ''): string
    {
        $quote = $preferredQuote;
        if ($quote === '' && preg_match('/[\s()"\']/', $url) === 1) {
            $quote = '"';
        }
        if ($quote === '') {
            return $url;
        }

        return $quote . str_replace(['\\', $quote], ['\\\\', '\\' . $quote], $url) . $quote;
    }

    /**
     * @return list<array{url: string, descriptor: string}>
     */
    private function srcsetCandidates(string $value): array
    {
        $length = strlen($value);
        $index = 0;
        $candidates = [];

        while ($index < $length) {
            while ($index < $length && (ctype_space($value[$index]) || $value[$index] === ',')) {
                $index++;
            }
            if ($index >= $length) {
                break;
            }

            $urlStart = $index;
            $isDataUrl = str_starts_with(strtolower(substr($value, $urlStart, 5)), 'data:');
            while ($index < $length) {
                $char = $value[$index];
                if (ctype_space($char) || ($char === ',' && !$isDataUrl)) {
                    break;
                }
                $index++;
            }
            $url = trim(substr($value, $urlStart, $index - $urlStart));
            if ($url === '') {
                break;
            }

            while ($index < $length && ctype_space($value[$index])) {
                $index++;
            }
            $descriptorStart = $index;
            while ($index < $length && $value[$index] !== ',') {
                $index++;
            }
            $descriptor = trim(substr($value, $descriptorStart, $index - $descriptorStart));

            $candidates[] = [
                'url' => $url,
                'descriptor' => $descriptor,
            ];

            if ($index < $length && $value[$index] === ',') {
                $index++;
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, string> $resources
     */
    private function mappedResourceReference(string $url, array $resources, string $packageDir, string $fromDir): ?string
    {
        if ($url === '' || !$this->isPackageRelativeResourceUrl($url)) {
            return null;
        }

        [$path, $suffix] = $this->splitUrlPathSuffix($url);
        if ($path === '') {
            return null;
        }

        foreach ([
            $this->safePackagePath($path),
            $this->safePackagePath($packageDir . '/' . $path),
            $this->safePackagePath($fromDir . '/' . $path),
        ] as $candidate) {
            if ($candidate !== '' && array_key_exists($candidate, $resources)) {
                return $this->relativePath($fromDir, $candidate) . $suffix;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUrlPathSuffix(string $url): array
    {
        $query = strpos($url, '?');
        $fragment = strpos($url, '#');
        $positions = array_values(array_filter([$query, $fragment], static fn (int|false $position): bool => $position !== false));
        if ($positions === []) {
            return [$url, ''];
        }

        $position = min($positions);

        return [substr($url, 0, $position), substr($url, $position)];
    }

    private function urlFragmentIdentifier(string $url): string
    {
        $position = strpos($url, '#');
        if ($position === false) {
            return '';
        }

        $fragment = substr($url, $position + 1);
        if ($fragment === '') {
            return '';
        }

        return rawurldecode($fragment);
    }

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        return !$this->isAbsoluteUrl($url)
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:');
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterRootAttributes(array $meta, int $chapterIndex, string $language, string $xmlLanguage, string $direction, bool $includeXmlEventsNamespace = false): string
    {
        $attrs = [
            'xmlns' => 'http://www.w3.org/1999/xhtml',
            'xmlns:epub' => 'http://www.idpf.org/2007/ops',
            'lang' => $language,
            'xml:lang' => $xmlLanguage,
        ];
        if ($includeXmlEventsNamespace) {
            $attrs = [
                'xmlns' => 'http://www.w3.org/1999/xhtml',
                'xmlns:epub' => 'http://www.idpf.org/2007/ops',
                'xmlns:ev' => 'http://www.w3.org/2001/xml-events',
                'lang' => $language,
                'xml:lang' => $xmlLanguage,
            ];
        }
        if ($direction !== '') {
            $attrs['dir'] = $direction;
        }

        $rootAttributes = $this->spineRootAttributes($meta, $chapterIndex);
        foreach (['id' => 'id', 'role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($rootAttributes, $key) ?: ($key === 'ariaLabel' ? $this->entryString($rootAttributes, 'aria-label') : '');
            if ($value !== '') {
                $attrs[$attribute] = $value;
            }
        }

        $classes = $this->propertyTokens($rootAttributes['classes'] ?? $rootAttributes['class'] ?? []);
        if ($classes !== []) {
            $attrs['class'] = implode(' ', $classes);
        }
        $prefix = $this->entryString($rootAttributes, 'prefix');
        if ($prefix !== '') {
            $attrs['prefix'] = $prefix;
        }
        if ($this->entryBool($rootAttributes, 'hidden')) {
            $attrs['hidden'] = 'hidden';
        }

        $xml = '';
        foreach ($attrs as $name => $value) {
            $xml .= ($xml === '' ? '' : ' ') . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function spineRootAttributes(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedMap($meta, $chapterIndex, [
            'epubSpineRootAttributes',
            'spineRootAttributes',
        ], [
            'rootAttributes',
            'rootAttrs',
            'htmlAttributes',
            'htmlAttrs',
        ]);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterBodyAttributes(array $meta, int $chapterIndex): string
    {
        $bodyAttributes = $this->spineBodyAttributes($meta, $chapterIndex);
        if ($bodyAttributes === []) {
            return '';
        }

        $attrs = [];
        foreach (['id' => 'id', 'role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($bodyAttributes, $key) ?: ($key === 'ariaLabel' ? $this->entryString($bodyAttributes, 'aria-label') : '');
            if ($value !== '') {
                $attrs[$attribute] = $value;
            }
        }
        $classes = $this->propertyTokens($bodyAttributes['classes'] ?? $bodyAttributes['class'] ?? []);
        if ($classes !== []) {
            $attrs['class'] = implode(' ', $classes);
        }
        $epubType = $this->entryString($bodyAttributes, 'epubType') ?: $this->entryString($bodyAttributes, 'epub:type') ?: $this->entryString($bodyAttributes, 'type');
        if ($epubType !== '') {
            $attrs['epub:type'] = $epubType;
        }
        $language = $this->entryString($bodyAttributes, 'lang') ?: $this->entryString($bodyAttributes, 'xml:lang');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attrs['lang'] = $language;
            $attrs['xml:lang'] = $language;
        }
        $direction = strtolower($this->entryString($bodyAttributes, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attrs['dir'] = $direction;
        }
        if ($this->entryBool($bodyAttributes, 'hidden')) {
            $attrs['hidden'] = 'hidden';
        }

        if ($attrs === []) {
            return '';
        }

        $xml = '';
        foreach ($attrs as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function spineBodyAttributes(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedMap($meta, $chapterIndex, [
            'epubSpineBodyAttributes',
            'spineBodyAttributes',
        ], [
            'bodyAttributes',
            'bodyAttrs',
        ]);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterHeadMetas(array $meta, int $chapterIndex): string
    {
        $metas = [];
        foreach ($this->spineHeadMetas($meta, $chapterIndex) as $headMeta) {
            $metas[] = $this->headMetaXml($headMeta);
        }

        return implode("\n", array_values(array_unique(array_filter($metas, static fn (string $line): bool => $line !== ''))));
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, mixed>>
     */
    private function spineHeadMetas(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedArrayList($meta, $chapterIndex, [
            'epubSpineHeadMetas',
            'spineHeadMetas',
        ], [
            'headMetas',
            'headMetadata',
            'metas',
        ]);
    }

    /**
     * @param array<string, mixed> $headMeta
     */
    private function headMetaXml(array $headMeta): string
    {
        $attrs = [];
        foreach ([
            'id' => 'id',
            'name' => 'name',
            'property' => 'property',
        ] as $key => $attribute) {
            $value = $this->entryString($headMeta, $key);
            if ($value !== '') {
                $attrs[$attribute] = $value;
            }
        }
        if (strtolower($attrs['name'] ?? '') === 'viewport') {
            return '';
        }
        $httpEquiv = $this->entryString($headMeta, 'httpEquiv') ?: $this->entryString($headMeta, 'http-equiv');
        if ($httpEquiv !== '') {
            $attrs['http-equiv'] = $httpEquiv;
        }
        foreach ([
            'content' => 'content',
            'refines' => 'refines',
            'scheme' => 'scheme',
        ] as $key => $attribute) {
            $value = $this->entryString($headMeta, $key);
            if ($value !== '') {
                $attrs[$attribute] = $value;
            }
        }
        $language = $this->entryString($headMeta, 'lang') ?: $this->entryString($headMeta, 'xml:lang');
        if ($language !== '') {
            $attrs['xml:lang'] = $language;
        }
        $direction = strtolower($this->entryString($headMeta, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attrs['dir'] = $direction;
        }
        if (!$this->hasSemanticHeadMetaAttribute($attrs)) {
            return '';
        }

        $xml = '  <meta';
        foreach ($attrs as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml . ' />';
    }

    /**
     * @param array<string, string> $attrs
     */
    private function hasSemanticHeadMetaAttribute(array $attrs): bool
    {
        foreach (['name', 'property', 'http-equiv'] as $name) {
            if (isset($attrs[$name]) && trim($attrs[$name]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterHeadTitle(array $meta, int $chapterIndex, string $fallback): string
    {
        $value = $this->chapterIndexedStringFromKeys($meta, $chapterIndex, [
            'epubSpineHeadTitles',
            'spineHeadTitles',
        ], [
            'headTitle',
            'title',
        ]);

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterHeadBases(array $meta, string $packageDir, string $chapterDir, int $chapterIndex): string
    {
        $bases = [];
        foreach ($this->spineHeadBases($meta, $chapterIndex) as $headBase) {
            $bases[] = $this->headBaseXml($headBase, $packageDir, $chapterDir);
        }

        return implode("\n", array_values(array_unique(array_filter($bases, static fn (string $line): bool => $line !== ''))));
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, mixed>>
     */
    private function spineHeadBases(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedArrayList($meta, $chapterIndex, [
            'epubSpineHeadBases',
            'spineHeadBases',
        ], [
            'headBases',
            'bases',
        ]);
    }

    /**
     * @param array<string, mixed> $headBase
     */
    private function headBaseXml(array $headBase, string $packageDir, string $chapterDir): string
    {
        $href = $this->entryString($headBase, 'href');
        $target = $this->entryString($headBase, 'target');
        if ($href === '' && $target === '') {
            return '';
        }

        $attrs = [];
        $id = $this->entryString($headBase, 'id');
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($target !== '') {
            $attrs['target'] = $target;
        }
        if ($href !== '') {
            $attrs['href'] = $this->entryBaseHref($href, $chapterDir, $packageDir);
        }

        $xml = '  <base';
        foreach ($attrs as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml . ' />';
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $resources
     */
    private function chapterHeadStyles(array $meta, array $resources, string $packageDir, string $chapterDir, int $chapterIndex): string
    {
        $styles = [];
        foreach ($this->spineHeadStyles($meta, $chapterIndex) as $headStyle) {
            $styles[] = $this->headStyleXml($headStyle, $resources, $packageDir, $chapterDir);
        }

        return implode("\n", array_values(array_unique(array_filter($styles, static fn (string $line): bool => $line !== ''))));
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, mixed>>
     */
    private function spineHeadStyles(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedArrayList($meta, $chapterIndex, [
            'epubSpineHeadStyles',
            'spineHeadStyles',
        ], [
            'headStyles',
            'styles',
        ]);
    }

    /**
     * @param array<string, mixed> $headStyle
     */
    /**
     * @param array<string, mixed> $headStyle
     * @param array<string, string> $resources
     */
    private function headStyleXml(array $headStyle, array $resources, string $packageDir, string $chapterDir): string
    {
        $css = $this->entryString($headStyle, 'css') ?: $this->entryString($headStyle, 'content') ?: $this->entryString($headStyle, 'text');
        if ($css === '') {
            return '';
        }
        $css = $this->rewriteRenderedCssResourceReferences($css, $resources, $packageDir, $chapterDir);

        $attrs = [];
        foreach ([
            'id' => 'id',
            'type' => 'type',
            'media' => 'media',
            'title' => 'title',
        ] as $key => $attribute) {
            $value = $this->entryString($headStyle, $key);
            if ($value !== '') {
                $attrs[$attribute] = $value;
            }
        }
        $language = $this->entryString($headStyle, 'lang') ?: $this->entryString($headStyle, 'xml:lang');
        if ($language !== '') {
            $attrs['xml:lang'] = $language;
        }
        $direction = strtolower($this->entryString($headStyle, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attrs['dir'] = $direction;
        }

        $xml = '  <style';
        foreach ($attrs as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml . '>' . $this->esc($css) . '</style>';
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, string> $resourceMediaTypes
     */
    private function chapterHeadLinks(array $meta, array $resources, string $packageDir, string $chapterDir, int $chapterIndex): string
    {
        $links = [];
        $linkedPaths = [];
        foreach ($this->spineHeadLinks($meta, $chapterIndex) as $link) {
            $href = $this->entryString($link, 'href');
            if ($href === '') {
                continue;
            }
            $path = $this->packageEntryPath($href);
            if ($path !== '') {
                $linkedPaths[$path] = true;
            }
            $links[] = $this->headLinkXml($link, $resources, $chapterDir, $packageDir);
        }

        $stylesheetLinks = $this->stylesheetLinks($resources, $chapterDir, $this->resourceMediaTypes($meta), $linkedPaths);
        if ($stylesheetLinks !== '') {
            array_push($links, ...explode("\n", $stylesheetLinks));
        }

        return implode("\n", array_values(array_unique(array_filter($links, static fn (string $line): bool => $line !== ''))));
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, mixed>>
     */
    private function spineHeadLinks(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedArrayList($meta, $chapterIndex, [
            'epubSpineHeadLinks',
            'spineHeadLinks',
        ], [
            'links',
            'headLinks',
        ]);
    }

    /**
     * @param array<string, mixed> $link
     */
    private function headLinkXml(array $link, array $resources, string $chapterDir, string $packageDir): string
    {
        $href = $this->entryString($link, 'href');
        if ($href === '') {
            return '';
        }

        $attrs = [];
        foreach (['id', 'rel', 'type', 'media', 'title'] as $name) {
            $value = $this->entryString($link, $name);
            if ($value !== '') {
                $attrs[$name] = $value;
            }
        }
        $hreflang = $this->entryString($link, 'hreflang') ?: $this->entryString($link, 'hrefLang');
        if ($hreflang !== '') {
            $attrs['hreflang'] = $hreflang;
        }
        $language = $this->entryString($link, 'lang') ?: $this->entryString($link, 'xml:lang');
        if ($language !== '') {
            $attrs['xml:lang'] = $language;
        }
        $direction = strtolower($this->entryString($link, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attrs['dir'] = $direction;
        }
        $properties = $this->propertyTokens($link['properties'] ?? []);
        if ($properties !== []) {
            $attrs['properties'] = implode(' ', $properties);
        }
        foreach ([
            'as' => ['as'],
            'sizes' => ['sizes'],
            'crossorigin' => ['crossorigin', 'crossOrigin'],
            'integrity' => ['integrity'],
            'referrerpolicy' => ['referrerpolicy', 'referrerPolicy'],
            'fetchpriority' => ['fetchpriority', 'fetchPriority'],
            'blocking' => ['blocking'],
            'color' => ['color'],
            'imagesizes' => ['imagesizes', 'imageSizes'],
        ] as $attribute => $keys) {
            foreach ($keys as $key) {
                $value = $this->entryString($link, $key);
                if ($value !== '') {
                    $attrs[$attribute] = $value;
                    break;
                }
            }
        }
        $imagesrcset = $this->entryString($link, 'imagesrcset') ?: $this->entryString($link, 'imageSrcset');
        if ($imagesrcset !== '') {
            $attrs['imagesrcset'] = $this->rewriteRenderedSrcsetValue($imagesrcset, $resources, $packageDir, $chapterDir);
        }
        if ($this->entryBool($link, 'disabled')) {
            $attrs['disabled'] = 'disabled';
        }
        $attrs['href'] = $this->entryHref($href, $chapterDir, $packageDir);

        $xml = '  <link';
        foreach ($attrs as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml . ' />';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function chapterHeadScripts(array $meta, string $packageDir, string $chapterDir, int $chapterIndex): string
    {
        $scripts = [];
        foreach ($this->spineHeadScripts($meta, $chapterIndex) as $headScript) {
            $scripts[] = $this->headScriptXml($headScript, $chapterDir, $packageDir);
        }

        return implode("\n", array_values(array_unique(array_filter($scripts, static fn (string $line): bool => $line !== ''))));
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array<string, mixed>>
     */
    private function spineHeadScripts(array $meta, int $chapterIndex): array
    {
        return $this->chapterIndexedArrayList($meta, $chapterIndex, [
            'epubSpineHeadScripts',
            'spineHeadScripts',
        ], [
            'headScripts',
            'scripts',
        ]);
    }

    /**
     * @param array<string, mixed> $headScript
     */
    private function headScriptXml(array $headScript, string $chapterDir, string $packageDir): string
    {
        $src = $this->entryString($headScript, 'src');
        $script = $this->entryString($headScript, 'script')
            ?: $this->entryString($headScript, 'content')
            ?: $this->entryString($headScript, 'text');
        if ($src === '' && $script === '') {
            return '';
        }

        $attrs = [];
        foreach ([
            'id' => 'id',
            'type' => 'type',
            'charset' => 'charset',
            'crossorigin' => 'crossorigin',
            'integrity' => 'integrity',
            'nonce' => 'nonce',
        ] as $key => $attribute) {
            $value = $this->entryString($headScript, $key);
            if ($value !== '') {
                $attrs[$attribute] = $value;
            }
        }
        $referrerPolicy = $this->entryString($headScript, 'referrerpolicy') ?: $this->entryString($headScript, 'referrerPolicy');
        if ($referrerPolicy !== '') {
            $attrs['referrerpolicy'] = $referrerPolicy;
        }
        foreach (['async', 'defer', 'nomodule'] as $attribute) {
            if ($this->entryBool($headScript, $attribute)) {
                $attrs[$attribute] = $attribute;
            }
        }
        if ($src !== '') {
            $attrs['src'] = $this->entryHref($src, $chapterDir, $packageDir);
        }

        $xml = '  <script';
        foreach ($attrs as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml . '>' . htmlspecialchars($script, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</script>';
    }

    private function packageEntryPath(string $href): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return '';
        }
        [$path] = $this->splitUrlPathSuffix($href);

        return $this->safePackagePath($path);
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, string> $resourceMediaTypes
     * @param array<string, bool> $skipPaths
     */
    private function stylesheetLinks(array $resources, string $chapterDir, array $resourceMediaTypes, array $skipPaths = []): string
    {
        $links = [];
        foreach ($resources as $path => $_bytes) {
            if (isset($skipPaths[$path])) {
                continue;
            }
            $mediaType = $resourceMediaTypes[$path] ?? $this->mediaType($path);
            if ($mediaType !== 'text/css' && !str_ends_with(strtolower($path), '.css')) {
                continue;
            }

            $links[] = '  <link rel="stylesheet" type="text/css" href="' . $this->esc($this->relativePath($chapterDir, $path)) . '" />';
        }

        return implode("\n", array_values(array_unique($links)));
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param array<string, string> $resources
     */
    private function ncxXml(AstNode $document, array $chapters, string $ncxDir, string $packageDir, array $resources = []): string
    {
        $meta = $this->metadata($document);
        $title = $this->metaString($meta, 'title', 'Untitled');
        $ncxMetadata = $this->ncxMetadata($meta);
        $metaTocEntries = $this->navigationEntries($meta, 'epubTocEntries');
        $tocEntries = $metaTocEntries !== [] ? $metaTocEntries : $this->headingEntries($chapters, $ncxDir);
        $rewriteTocHrefs = $metaTocEntries !== [];
        if ($tocEntries === []) {
            $tocEntries[] = $this->fallbackNcxTocEntry($chapters, $ncxDir, $title);
        }

        $toc = $this->normalizedNavEntries($tocEntries, $ncxDir, $packageDir, $rewriteTocHrefs);
        $pageListEntries = $this->navigationEntries($meta, 'epubPageListEntries');
        if ($pageListEntries === []) {
            $pageListEntries = $this->generatedPageListEntries($chapters, $resources, $packageDir);
        }
        $pageList = $this->normalizedNavEntries($pageListEntries, $ncxDir, $packageDir, true);
        $targetResources = $this->ncxTargetResources($chapters, $resources, $packageDir);
        $tocIndex = 0;
        $pageIndex = 0;
        $tocTree = $toc === [] ? [] : $this->navEntryNodes($toc, $tocIndex, $toc[0]['level']);
        $pageTree = $pageList === [] ? [] : $this->navEntryNodes($pageList, $pageIndex, $pageList[0]['level']);
        $tocTree = $this->sanitizeNcxNavigationNodes($tocTree, $ncxDir, $targetResources, false);
        $pageTree = $this->sanitizeNcxNavigationNodes($pageTree, $ncxDir, $targetResources, true);
        $navListTrees = [];
        $navListDepth = 0;
        foreach ($this->ncxNavLists($meta) as $section) {
            $entries = $this->normalizedNavEntries($section['entries'], $ncxDir, $packageDir, true);
            if ($entries === []) {
                continue;
            }
            $sectionIndex = 0;
            $tree = $this->navEntryNodes($entries, $sectionIndex, $entries[0]['level']);
            $tree = $this->sanitizeNcxNavigationNodes($tree, $ncxDir, $targetResources, true);
            $section['entries'] = $entries;
            $section['tree'] = $tree;
            $navListDepth = max($navListDepth, $this->ncxRenderableNavigationNodeDepth($tree));
            $navListTrees[] = $section;
        }
        $tocDepth = $this->ncxRenderableNavigationNodeDepth($tocTree);
        $depth = max(1, $tocDepth === 0 ? 1 : $tocDepth, $this->ncxRenderableNavigationNodeDepth($pageTree), $navListDepth);
        $depth = $this->entryPositiveInteger($meta, 'epubNcxDepth')
            ?? $this->entryPositiveInteger($meta, 'ncxDepth')
            ?? $this->entryPositiveInteger($ncxMetadata, 'depth')
            ?? $depth;
        $uid = ($this->metaStringFromKeys($meta, ['epubNcxUid', 'ncxUid']) ?? '')
            ?: $this->entryString($ncxMetadata, 'uid')
            ?: $this->identifier($meta);
        $totalPageCount = $this->entryNonNegativeInteger($meta, 'epubNcxTotalPageCount')
            ?? $this->entryNonNegativeInteger($meta, 'ncxTotalPageCount')
            ?? $this->entryNonNegativeInteger($ncxMetadata, 'totalPageCount')
            ?? 0;
        $maxPageNumber = $this->entryNonNegativeInteger($meta, 'epubNcxMaxPageNumber')
            ?? $this->entryNonNegativeInteger($meta, 'ncxMaxPageNumber')
            ?? $this->entryNonNegativeInteger($ncxMetadata, 'maxPageNumber')
            ?? 0;
        $docTitle = $this->ncxDocTitleEntry($meta, $ncxMetadata, $title);
        $headLines = [
            '    <meta name="dtb:uid" content="' . $this->esc($uid) . '"/>',
            '    <meta name="dtb:depth" content="' . $depth . '"/>',
            '    <meta name="dtb:totalPageCount" content="' . $totalPageCount . '"/>',
            '    <meta name="dtb:maxPageNumber" content="' . $maxPageNumber . '"/>',
        ];
        foreach ($this->ncxHeadMetadata($meta, $ncxMetadata) as $record) {
            $name = $this->entryString($record, 'name');
            $content = $this->entryString($record, 'content');
            if ($name === '' || $content === '' || in_array(strtolower($name), ['dtb:uid', 'dtb:depth', 'dtb:totalpagecount', 'dtb:maxpagenumber'], true)) {
                continue;
            }
            $attributes = [
                'name' => $name,
                'content' => $content,
            ];
            foreach (['id', 'scheme'] as $attribute) {
                $value = $this->entryString($record, $attribute);
                if ($value !== '') {
                    $attributes[$attribute] = $value;
                }
            }
            $headLines[] = '    <meta' . $this->xmlAttributes($attributes) . '/>';
        }
        $docAuthorXml = '';
        foreach ($this->ncxDocAuthorEntries($meta, $ncxMetadata) as $author) {
            $docAuthorXml .= $this->ncxDocumentTextXml('docAuthor', $author, 2);
        }
        $playOrder = 1;
        $usedIds = [];
        $navMapXml = $this->renderNcxNavPointNodes($tocTree, 4, $playOrder, $usedIds);
        if (trim($navMapXml) === '') {
            $tocTree = [
                [
                    'entry' => $this->fallbackNcxTocEntry($chapters, $ncxDir, $title),
                    'children' => [],
                ],
            ];
            $playOrder = 1;
            $usedIds = [];
            $navMapXml = $this->renderNcxNavPointNodes($tocTree, 4, $playOrder, $usedIds);
        }
        $pageListLabel = $this->ncxPageListLabelEntry($meta, $ncxMetadata);
        $pageTargetXml = $pageTree === [] ? '' : $this->renderNcxPageTargetNodes($pageTree, 4, $playOrder, $usedIds);
        $pageListXml = trim($pageTargetXml) === ''
            ? ''
            : "  <pageList>\n"
                . $this->ncxNavLabelXml($pageListLabel, 4)
                . $pageTargetXml
                . "  </pageList>\n";
        $navListXml = '';
        foreach ($navListTrees as $section) {
            if ($this->firstNcxNavigationHref($section['tree']) === '') {
                continue;
            }
            $attributes = [];
            $id = $this->validManifestId($this->entryString($section, 'id'));
            if ($id !== '') {
                $attributes['id'] = $this->uniqueNcxId($id, 'nav-list', $usedIds);
            }
            $type = $this->entryString($section, 'type');
            if ($type !== '') {
                $attributes['class'] = $type;
            }
            $label = [
                'text' => $this->entryString($section, 'label') ?: 'Navigation',
            ];
            $language = $this->entryString($section, 'lang');
            if ($language !== '' && $this->validLanguageTag($language)) {
                $label['lang'] = $language;
            }
            $navTargetXml = $this->renderNcxNavTargetNodes($section['tree'], 4, $playOrder, $usedIds);
            if (trim($navTargetXml) === '') {
                continue;
            }
            $navListXml .= '  <navList' . $this->xmlAttributes($attributes) . ">\n"
                . $this->ncxNavLabelXml($label, 4)
                . $navTargetXml
                . "  </navList>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">' . "\n"
            . "  <head>\n"
            . implode("\n", $headLines) . "\n"
            . "  </head>\n"
            . $this->ncxDocumentTextXml('docTitle', $docTitle, 2)
            . $docAuthorXml
            . "  <navMap>\n"
            . $navMapXml
            . "  </navMap>\n"
            . $pageListXml
            . $navListXml
            . "</ncx>\n";
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @return array{text: string, href: string, level: int}
     */
    private function fallbackNcxTocEntry(array $chapters, string $ncxDir, string $title): array
    {
        $firstChapter = $chapters[0] ?? null;

        return [
            'text' => $title,
            'href' => is_array($firstChapter) ? $this->relativePath($ncxDir, $firstChapter['path']) : 'text/chapter.xhtml',
            'level' => 1,
        ];
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param array<string, string> $resources
     * @return array{paths: array<string, true>, linearPaths: array<string, true>, xmlPayloads: array<string, string>}
     */
    private function ncxTargetResources(array $chapters, array $resources, string $packageDir): array
    {
        $paths = [];
        $linearPaths = [];
        $xmlPayloads = [];

        foreach ($chapters as $index => $chapter) {
            $path = $this->safePackagePath($chapter['path']);
            if ($path === '') {
                continue;
            }
            $paths[$path] = true;
            $linearPaths[$path] = true;
            $xmlPayloads[$path] = $this->chapterXhtml($chapter['document'], $resources, $packageDir, $this->dirname($path), $index);
        }

        foreach ($resources as $path => $bytes) {
            $path = $this->safePackagePath($path);
            if ($path === '') {
                continue;
            }
            $paths[$path] = true;
            $mediaType = $this->mediaType($path);
            if ($this->metadataRefinesPayloadIsXmlLike($bytes, $mediaType)) {
                $xmlPayloads[$path] = $bytes;
            }
        }

        return [
            'paths' => $paths,
            'linearPaths' => $linearPaths,
            'xmlPayloads' => $xmlPayloads,
        ];
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     * @param array{paths: array<string, true>, linearPaths: array<string, true>, xmlPayloads: array<string, string>} $targetResources
     * @return list<array{entry: array<string, mixed>, children: list<array>}>
     */
    private function sanitizeNcxNavigationNodes(array $nodes, string $ncxDir, array $targetResources, bool $requireLinearTarget): array
    {
        $sanitized = [];
        foreach ($nodes as $node) {
            $entry = is_array($node['entry'] ?? null) ? $node['entry'] : [];
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $children = $this->sanitizeNcxNavigationNodes($children, $ncxDir, $targetResources, $requireLinearTarget);

            if ($this->entryString($entry, 'href') !== '' && !$this->validNcxNavigationEntryHref($entry, $ncxDir, $targetResources, $requireLinearTarget)) {
                unset($entry['href'], $entry['sourceHref']);
            }

            $sanitized[] = [
                'entry' => $entry,
                'children' => $children,
            ];
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array{paths: array<string, true>, linearPaths: array<string, true>, xmlPayloads: array<string, string>} $targetResources
     */
    private function validNcxNavigationEntryHref(array $entry, string $ncxDir, array $targetResources, bool $requireLinearTarget): bool
    {
        $href = $this->entryString($entry, 'href');
        if ($href === '') {
            return false;
        }

        foreach (array_unique([$this->entryString($entry, 'sourceHref') ?: $href, $href]) as $candidate) {
            if ($this->guideReferenceHrefPathDiagnosticReason($candidate) !== '' || $this->guideReferenceHrefFragmentDiagnosticReason($candidate) !== '') {
                return false;
            }
        }

        if (!$this->isPackageRelativeResourceUrl($href)) {
            return true;
        }

        $targetPath = $this->ncxNavigationTargetPath($href, $ncxDir);
        if ($targetPath === '' || !isset($targetResources['paths'][$targetPath])) {
            return false;
        }
        if ($requireLinearTarget && !isset($targetResources['linearPaths'][$targetPath])) {
            return false;
        }

        return true;
    }

    private function ncxNavigationTargetPath(string $href, string $ncxDir): string
    {
        [$path] = $this->splitUrlPathSuffix($href);
        if ($path === '') {
            return '';
        }

        return $this->safePackagePath(($ncxDir === '' ? '' : $ncxDir . '/') . $path);
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     */
    private function navEntryNodeDepth(array $nodes): int
    {
        $depth = 0;
        foreach ($nodes as $node) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $depth = max($depth, 1 + $this->navEntryNodeDepth($children));
        }

        return $depth;
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     */
    private function ncxRenderableNavigationNodeDepth(array $nodes): int
    {
        $depth = 0;
        foreach ($nodes as $node) {
            $entry = is_array($node['entry'] ?? null) ? $node['entry'] : [];
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $href = $this->entryString($entry, 'href') ?: $this->firstNcxNavigationHref($children);
            if ($href === '') {
                continue;
            }

            $depth = max($depth, 1 + $this->ncxRenderableNavigationNodeDepth($children));
        }

        return $depth;
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     * @param array<string, true> $usedIds
     */
    private function renderNcxNavPointNodes(array $nodes, int $indent, int &$playOrder, array &$usedIds): string
    {
        $xml = '';
        foreach ($nodes as $node) {
            $entry = $node['entry'];
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $href = $this->entryString($entry, 'href');
            if ($href === '') {
                $href = $this->firstNcxNavigationHref($children);
            }
            if ($href === '') {
                continue;
            }
            $entryPlayOrder = $this->ncxPlayOrder($entry, $playOrder);
            $id = $this->uniqueNcxId($this->entryString($entry, 'id'), 'navpoint', $usedIds);
            $attributes = [
                'id' => $id,
                'playOrder' => (string) $entryPlayOrder,
            ];
            $type = $this->entryString($entry, 'type');
            if ($type !== '') {
                $attributes['class'] = $type;
            }
            $pad = str_repeat(' ', $indent);
            $childPad = str_repeat(' ', $indent + 2);
            $xml .= $pad . '<navPoint' . $this->xmlAttributes($attributes) . ">\n";
            $xml .= $this->ncxNavLabelXml($entry, $indent + 2);
            $xml .= $childPad . '<content src="' . $this->esc($href) . "\"/>\n";
            if ($children !== []) {
                $xml .= $this->renderNcxNavPointNodes($children, $indent + 2, $playOrder, $usedIds);
            }
            $xml .= $pad . "</navPoint>\n";
        }

        return $xml;
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     */
    private function firstNcxNavigationHref(array $nodes): string
    {
        foreach ($nodes as $node) {
            $entry = is_array($node['entry'] ?? null) ? $node['entry'] : [];
            $href = $this->entryString($entry, 'href');
            if ($href !== '') {
                return $href;
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $href = $this->firstNcxNavigationHref($children);
            if ($href !== '') {
                return $href;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function ncxPlayOrder(array $entry, int &$playOrder): int
    {
        $candidate = $this->entryPositiveInteger($entry, 'playOrder');
        $entryPlayOrder = $candidate !== null && $candidate >= $playOrder ? $candidate : $playOrder;
        $playOrder = $entryPlayOrder + 1;

        return $entryPlayOrder;
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     * @param array<string, true> $usedIds
     */
    private function renderNcxPageTargetNodes(array $nodes, int $indent, int &$playOrder, array &$usedIds): string
    {
        $xml = '';
        foreach ($nodes as $node) {
            $entry = $node['entry'];
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $href = $this->entryString($entry, 'href');
            if ($href === '') {
                $href = $this->firstNcxNavigationHref($children);
            }
            if ($href === '') {
                continue;
            }
            $entryPlayOrder = $this->ncxPlayOrder($entry, $playOrder);
            $attributes = [
                'id' => $this->uniqueNcxId($this->entryString($entry, 'id'), 'page-target', $usedIds),
                'playOrder' => (string) $entryPlayOrder,
            ];
            $type = $this->ncxPageTargetType($entry);
            if ($type !== '') {
                $attributes['type'] = $type;
            }
            $value = $this->entryString($entry, 'value');
            if ($value !== '') {
                $attributes['value'] = $value;
            }
            $pad = str_repeat(' ', $indent);
            $childPad = str_repeat(' ', $indent + 2);
            $xml .= $pad . '<pageTarget' . $this->xmlAttributes($attributes) . ">\n";
            $xml .= $this->ncxNavLabelXml($entry, $indent + 2);
            $xml .= $childPad . '<content src="' . $this->esc($href) . "\"/>\n";
            if ($children !== []) {
                $xml .= $this->renderNcxPageTargetNodes($children, $indent + 2, $playOrder, $usedIds);
            }
            $xml .= $pad . "</pageTarget>\n";
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function ncxPageTargetType(array $entry): string
    {
        $type = $this->entryString($entry, 'type');

        return strtolower($type) === 'pagebreak' ? 'normal' : $type;
    }

    /**
     * @param list<array{entry: array<string, mixed>, children: list<array>}> $nodes
     * @param array<string, true> $usedIds
     */
    private function renderNcxNavTargetNodes(array $nodes, int $indent, int &$playOrder, array &$usedIds): string
    {
        $xml = '';
        foreach ($nodes as $node) {
            $entry = $node['entry'];
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $href = $this->entryString($entry, 'href');
            if ($href === '') {
                $href = $this->firstNcxNavigationHref($children);
            }
            if ($href === '') {
                continue;
            }
            $entryPlayOrder = $this->ncxPlayOrder($entry, $playOrder);
            $attributes = [
                'id' => $this->uniqueNcxId($this->entryString($entry, 'id'), 'nav-target', $usedIds),
                'playOrder' => (string) $entryPlayOrder,
            ];
            $type = $this->entryString($entry, 'type');
            if ($type !== '') {
                $attributes['class'] = $type;
            }
            $value = $this->entryString($entry, 'value');
            if ($value !== '') {
                $attributes['value'] = $value;
            }
            $pad = str_repeat(' ', $indent);
            $childPad = str_repeat(' ', $indent + 2);
            $xml .= $pad . '<navTarget' . $this->xmlAttributes($attributes) . ">\n";
            $xml .= $this->ncxNavLabelXml($entry, $indent + 2);
            $xml .= $childPad . '<content src="' . $this->esc($href) . "\"/>\n";
            if ($children !== []) {
                $xml .= $this->renderNcxNavTargetNodes($children, $indent + 2, $playOrder, $usedIds);
            }
            $xml .= $pad . "</navTarget>\n";
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function ncxNavLabelXml(array $entry, int $indent): string
    {
        $attributes = [];
        $language = $this->entryString($entry, 'lang') ?: $this->entryString($entry, 'xml:lang') ?: $this->entryString($entry, 'xmlLanguage');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attributes['xml:lang'] = $language;
        }

        return str_repeat(' ', $indent)
            . '<navLabel' . $this->xmlAttributes($attributes) . '><text>'
            . $this->esc((string) ($entry['text'] ?? ''))
            . "</text></navLabel>\n";
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function ncxDocumentTextXml(string $element, array $entry, int $indent): string
    {
        $attributes = [];
        $language = $this->entryString($entry, 'lang') ?: $this->entryString($entry, 'xml:lang') ?: $this->entryString($entry, 'xmlLanguage');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attributes['xml:lang'] = $language;
        }

        return str_repeat(' ', $indent)
            . '<' . $element . $this->xmlAttributes($attributes) . '><text>'
            . $this->esc((string) ($entry['text'] ?? ''))
            . '</text></' . $element . ">\n";
    }

    /**
     * @param array<string, true> $usedIds
     */
    private function uniqueNcxId(string $preferred, string $prefix, array &$usedIds): string
    {
        $base = $this->validManifestId($preferred) ?: $prefix . '-' . (count($usedIds) + 1);
        $id = $base;
        $index = 2;
        while (isset($usedIds[$id])) {
            $id = $base . '-' . $index;
            $index++;
        }
        $usedIds[$id] = true;

        return $id;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function xmlAttributes(array $attributes): string
    {
        $xml = '';
        foreach ($attributes as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param array<string, string> $resources
     */
    private function navXhtml(AstNode $document, array $chapters, string $navDir, string $packageDir, array $resources = [], string $navPath = ''): string
    {
        $meta = $this->metadata($document);
        $title = $this->metaString($meta, 'title', 'Untitled');
        $language = $this->language($meta);
        $rootAttributes = $this->navRootAttributesXml($meta, $language);
        $bodyAttributes = $this->navBodyAttributesXml($meta);
        $metaTocEntries = $this->navigationEntries($meta, 'epubTocEntries');
        $entries = $metaTocEntries !== [] ? $metaTocEntries : $this->headingEntries($chapters, $navDir);
        $rewriteTocHrefs = $metaTocEntries !== [];
        $tocAttributes = $this->navSectionMetadata($meta, 'epubTocNavAttributes', 'epubTocNavTitle');
        if ($entries === []) {
            $firstChapter = $chapters[0] ?? null;
            $entries[] = [
                'text' => $title,
                'href' => is_array($firstChapter) ? $this->relativePath($navDir, $firstChapter['path']) : 'text/chapter.xhtml',
                'level' => 1,
            ];
        }
        $tocHeadingTag = 'h' . $this->navSectionHeadingLevel($tocAttributes, 1);
        $tocHeadingAttributes = $this->navSectionHeadingAttributes($tocAttributes);
        $landmarkEntries = $this->navigationEntries($meta, 'epubLandmarkEntries');
        if ($landmarkEntries === []) {
            $landmarkEntries = $this->guideReferenceLandmarkEntries($meta, $chapters, $resources, $packageDir, $navPath);
        }
        $landmarks = $this->navSectionXhtml(
            'landmarks',
            'landmarks',
            'Landmarks',
            $landmarkEntries,
            $navDir,
            $packageDir,
            $this->navSectionMetadata($meta, 'epubLandmarkNavAttributes', 'epubLandmarkNavTitle')
        );
        $pageListEntries = $this->navigationEntries($meta, 'epubPageListEntries');
        if ($pageListEntries === []) {
            $pageListEntries = $this->generatedPageListEntries($chapters, $resources, $packageDir);
        }
        $pageList = $this->navSectionXhtml(
            'page-list',
            'page-list',
            'Page List',
            $pageListEntries,
            $navDir,
            $packageDir,
            $this->navSectionMetadata($meta, 'epubPageListNavAttributes', 'epubPageListNavTitle')
        );
        $auxiliaryNavSections = '';
        foreach ($this->auxiliaryNavSections($meta) as $section) {
            $auxiliaryNavSections .= $this->navSectionXhtml(
                $section['type'],
                $section['type'],
                $section['title'],
                $section['entries'],
                $navDir,
                $packageDir,
                $section['attributes']
            );
        }

        return '<html ' . $rootAttributes . '>' . "\n"
            . "<head>\n"
            . '  <title>' . $this->esc($title) . '</title>' . "\n"
            . "  <meta charset=\"utf-8\" />\n"
            . "</head>\n"
            . '<body' . $bodyAttributes . ">\n"
            . '  <nav' . $this->navSectionAttributes($tocAttributes, 'toc', 'toc') . ">\n"
            . '    <' . $tocHeadingTag . $tocHeadingAttributes . '>' . $this->esc($this->navSectionTitle($tocAttributes, 'Table of Contents')) . '</' . $tocHeadingTag . ">\n"
            . $this->navListXhtml($entries, $navDir, $packageDir, $rewriteTocHrefs, 4) . "\n"
            . "  </nav>\n"
            . $landmarks
            . $pageList
            . $auxiliaryNavSections
            . "</body>\n"
            . "</html>\n";
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param array<string, string> $resources
     * @return list<array{text: string, level: int, href: string, type: string}>
     */
    private function guideReferenceLandmarkEntries(array $meta, array $chapters, array $resources, string $packageDir, string $navPath): array
    {
        $resourcePaths = array_fill_keys(array_keys($resources), true);
        $xmlResourcePayloads = [];
        $linearTargetPaths = [];
        foreach ($chapters as $index => $chapter) {
            $path = $chapter['path'] ?? '';
            $chapterDocument = $chapter['document'] ?? null;
            if (!is_string($path) || $path === '' || !$chapterDocument instanceof AstNode) {
                continue;
            }
            $resourcePaths[$path] = true;
            $linearTargetPaths[$this->safePackagePath($path)] = true;
            $xmlResourcePayloads[$path] = $this->chapterXhtml($chapterDocument, $resources, $packageDir, $this->dirname($path), $index);
        }
        if ($navPath !== '') {
            $resourcePaths[$navPath] = true;
        }

        $entries = [];
        $seen = [];
        foreach ($this->guideReferences($meta, $this->coverImagePath($resources, $meta), $packageDir, $resourcePaths, $xmlResourcePayloads) as $reference) {
            $type = $this->landmarkTypeForGuideReference($reference['type']);
            if ($type === '') {
                continue;
            }
            $href = $this->landmarkHrefForGuideReference($reference['href'], $packageDir);
            if ($href === '') {
                continue;
            }
            if (!$this->guideReferenceLandmarkTargetsLinearPath($href, $linearTargetPaths)) {
                continue;
            }
            $key = $type . "\0" . $href;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entries[] = [
                'text' => $reference['title'] !== '' ? $reference['title'] : ucfirst($type),
                'href' => $href,
                'level' => 1,
                'type' => $type,
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, true> $linearTargetPaths
     */
    private function guideReferenceLandmarkTargetsLinearPath(string $href, array $linearTargetPaths): bool
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return false;
        }

        [$path] = $this->splitUrlPathSuffix($href);
        $path = $this->safePackagePath($path);

        return $path !== '' && isset($linearTargetPaths[$path]);
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @param array<string, string> $resources
     * @return list<array{text: string, level: int, href: string, type: string, value: string}>
     */
    private function generatedPageListEntries(array $chapters, array $resources, string $packageDir): array
    {
        $entries = [];
        $pageNumber = 1;
        $seen = [];
        foreach ($chapters as $index => $chapter) {
            $path = $chapter['path'] ?? '';
            $chapterDocument = $chapter['document'] ?? null;
            if (!is_string($path) || $path === '' || !$chapterDocument instanceof AstNode) {
                continue;
            }

            $chapterXml = $this->chapterXhtml($chapterDocument, $resources, $packageDir, $this->dirname($path), $index);
            foreach ($this->xhtmlPagebreakTargets($chapterXml) as $target) {
                $id = $target['id'];
                $href = $path . '#' . $id;
                if (isset($seen[$href])) {
                    continue;
                }
                $seen[$href] = true;
                $value = $target['value'] !== '' ? $target['value'] : (string) $pageNumber;
                $entries[] = [
                    'text' => $value,
                    'href' => $href,
                    'level' => 1,
                    'type' => 'pagebreak',
                    'value' => $value,
                ];
                $pageNumber++;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{id: string, value: string}>
     */
    private function xhtmlPagebreakTargets(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $targets = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->xhtmlElementHasPagebreakSemantics($element)) {
                continue;
            }
            $id = trim($element->getAttribute('id'));
            if ($id === '') {
                continue;
            }
            $targets[] = [
                'id' => $id,
                'value' => $this->xhtmlPagebreakValue($element),
            ];
        }

        return $targets;
    }

    private function xhtmlElementHasPagebreakSemantics(\DOMElement $element): bool
    {
        $epubTypes = array_map('strtolower', $this->propertyTokens($this->xhtmlEpubTypeAttribute($element)));
        $roles = array_map('strtolower', $this->propertyTokens($element->getAttribute('role')));

        return in_array('pagebreak', $epubTypes, true) || in_array('doc-pagebreak', $roles, true);
    }

    private function xhtmlEpubTypeAttribute(\DOMElement $element): string
    {
        $type = trim($element->getAttribute('epub:type'));
        if ($type !== '') {
            return $type;
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            if ($attribute->localName === 'type' && ($attribute->prefix === 'epub' || $attribute->namespaceURI === 'http://www.idpf.org/2007/ops')) {
                return trim($attribute->value);
            }
        }

        return '';
    }

    private function xhtmlPagebreakValue(\DOMElement $element): string
    {
        foreach (['title', 'aria-label'] as $attribute) {
            $value = $this->normalizedXhtmlText($element->getAttribute($attribute));
            if ($value !== '') {
                return $value;
            }
        }

        return $this->normalizedXhtmlText($element->textContent);
    }

    private function normalizedXhtmlText(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($normalized);
    }

    private function landmarkTypeForGuideReference(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'text') {
            return 'bodymatter';
        }

        return $this->propertyTokens($type)[0] ?? '';
    }

    private function landmarkHrefForGuideReference(string $href, string $packageDir): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $href;
        }

        [$path, $suffix] = $this->splitUrlPathSuffix($href);
        if ($path === '') {
            return '';
        }

        $base = $packageDir === '' ? '' : $packageDir . '/';
        $path = $this->safePackagePath($base . $path);

        return $path === '' ? '' : $path . $suffix;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function navRootAttributesXml(array $meta, string $defaultLanguage): string
    {
        $attributes = $this->navRootAttributes($meta);
        $language = $this->entryString($attributes, 'lang')
            ?: $this->entryString($attributes, 'xml:lang')
            ?: $this->entryString($attributes, 'xmlLanguage')
            ?: $defaultLanguage;
        if (!$this->validLanguageTag($language)) {
            $language = $defaultLanguage;
        }
        $xmlLanguage = $this->entryString($attributes, 'xmlLanguage')
            ?: $this->entryString($attributes, 'xml:lang')
            ?: $this->entryString($attributes, 'lang')
            ?: $language;
        if (!$this->validLanguageTag($xmlLanguage)) {
            $xmlLanguage = $language;
        }

        $xmlAttributes = [
            'xmlns' => 'http://www.w3.org/1999/xhtml',
            'xmlns:epub' => 'http://www.idpf.org/2007/ops',
            'lang' => $language,
            'xml:lang' => $xmlLanguage,
        ];
        foreach (['id' => 'id', 'role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($attributes, $key) ?: ($key === 'ariaLabel' ? $this->entryString($attributes, 'aria-label') : '');
            if ($value !== '') {
                $xmlAttributes[$attribute] = $value;
            }
        }

        $classes = $this->propertyTokens($attributes['classes'] ?? $attributes['class'] ?? []);
        if ($classes !== []) {
            $xmlAttributes['class'] = implode(' ', $classes);
        }
        $prefix = $this->entryString($attributes, 'prefix');
        if ($prefix !== '') {
            $xmlAttributes['prefix'] = $prefix;
        }
        $direction = strtolower($this->entryString($attributes, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $xmlAttributes['dir'] = $direction;
        }
        if ($this->entryBool($attributes, 'hidden')) {
            $xmlAttributes['hidden'] = 'hidden';
        }

        return $this->xmlAttributesWithoutLeadingSpace($xmlAttributes);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function navRootAttributes(array $meta): array
    {
        foreach ([
            'epubNavRootAttributes',
            'epubNavRootAttrs',
            'epubNavHtmlAttributes',
            'epubNavHtmlAttrs',
            'navRootAttributes',
            'navRootAttrs',
            'navHtmlAttributes',
            'navHtmlAttrs',
        ] as $key) {
            if (isset($meta[$key]) && is_array($meta[$key])) {
                return $meta[$key];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function navBodyAttributesXml(array $meta): string
    {
        $attributes = $this->navBodyAttributes($meta);
        if ($attributes === []) {
            return '';
        }

        $xmlAttributes = [];
        foreach (['id' => 'id', 'role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($attributes, $key) ?: ($key === 'ariaLabel' ? $this->entryString($attributes, 'aria-label') : '');
            if ($value !== '') {
                $xmlAttributes[$attribute] = $value;
            }
        }
        $classes = $this->propertyTokens($attributes['classes'] ?? $attributes['class'] ?? []);
        if ($classes !== []) {
            $xmlAttributes['class'] = implode(' ', $classes);
        }
        $epubType = $this->entryString($attributes, 'epubType') ?: $this->entryString($attributes, 'epub:type') ?: $this->entryString($attributes, 'type');
        if ($epubType !== '') {
            $xmlAttributes['epub:type'] = $epubType;
        }
        $language = $this->entryString($attributes, 'lang') ?: $this->entryString($attributes, 'xml:lang') ?: $this->entryString($attributes, 'xmlLanguage');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $xmlAttributes['lang'] = $language;
            $xmlAttributes['xml:lang'] = $language;
        }
        $direction = strtolower($this->entryString($attributes, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $xmlAttributes['dir'] = $direction;
        }
        if ($this->entryBool($attributes, 'hidden')) {
            $xmlAttributes['hidden'] = 'hidden';
        }

        return $this->xmlAttributes($xmlAttributes);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function navBodyAttributes(array $meta): array
    {
        foreach (['epubNavBodyAttributes', 'epubNavBodyAttrs', 'navBodyAttributes', 'navBodyAttrs'] as $key) {
            if (isset($meta[$key]) && is_array($meta[$key])) {
                return $meta[$key];
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function xmlAttributesWithoutLeadingSpace(array $attributes): string
    {
        $xml = $this->xmlAttributes($attributes);

        return $xml === '' ? '' : substr($xml, 1);
    }

    /**
     * @param list<array{text: string, level: int, href?: string, type?: string, value?: string}> $entries
     */
    private function navListXhtml(array $entries, string $navDir, string $packageDir, bool $rewriteHrefs, int $indent): string
    {
        $normalized = $this->normalizedNavEntries($entries, $navDir, $packageDir, $rewriteHrefs);
        if ($normalized === []) {
            return str_repeat(' ', $indent) . '<ol></ol>';
        }

        $index = 0;
        $tree = $this->navEntryNodes($normalized, $index, $normalized[0]['level']);

        return $this->renderNavEntryNodes($tree, $indent);
    }

    /**
     * @param list<array{text: string, level: int, href?: string, type?: string, value?: string}> $entries
     * @return list<array{text: string, level: int, href?: string, type?: string, value?: string}>
     */
    private function normalizedNavEntries(array $entries, string $navDir, string $packageDir, bool $rewriteHrefs): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            $text = trim((string) ($entry['text'] ?? ''));
            $href = trim((string) ($entry['href'] ?? ''));
            if ($text === '') {
                continue;
            }

            $normalizedEntry = [
                'text' => $text,
                'level' => max(1, min(6, (int) ($entry['level'] ?? 1))),
            ];
            if ($href !== '') {
                $normalizedEntry['href'] = $rewriteHrefs ? $this->navEntryHref($href, $navDir, $packageDir) : $href;
                $normalizedEntry['sourceHref'] = $href;
            }
            if (isset($entry['type']) && is_scalar($entry['type']) && trim((string) $entry['type']) !== '') {
                $normalizedEntry['type'] = trim((string) $entry['type']);
            }
            if (isset($entry['value']) && is_scalar($entry['value']) && trim((string) $entry['value']) !== '') {
                $normalizedEntry['value'] = trim((string) $entry['value']);
            }
            $playOrder = $this->entryPositiveInteger($entry, 'playOrder');
            if ($playOrder !== null) {
                $normalizedEntry['playOrder'] = $playOrder;
            }
            $id = $this->validManifestId($this->entryString($entry, 'id'));
            if ($id !== '') {
                $normalizedEntry['id'] = $id;
            }
            foreach (['title', 'role', 'ariaLabel'] as $key) {
                $value = $this->entryString($entry, $key);
                if ($value !== '') {
                    $normalizedEntry[$key] = $value;
                }
            }
            foreach (['rel', 'hreflang', 'media', 'target'] as $key) {
                $value = $this->entryString($entry, $key);
                if ($value !== '') {
                    $normalizedEntry[$key] = $value;
                }
            }
            $language = $this->entryString($entry, 'lang') ?: $this->entryString($entry, 'xml:lang') ?: $this->entryString($entry, 'xmlLanguage');
            if ($language !== '' && $this->validLanguageTag($language)) {
                $normalizedEntry['lang'] = $language;
            }
            $direction = strtolower($this->entryString($entry, 'dir'));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $normalizedEntry['dir'] = $direction;
            }
            $classes = $this->propertyTokens($entry['classes'] ?? $entry['class'] ?? []);
            if ($classes !== []) {
                $normalizedEntry['classes'] = $classes;
            }
            if ($this->entryBool($entry, 'hidden')) {
                $normalizedEntry['hidden'] = true;
            }
            $itemAttributes = $this->navListItemAttributesFromEntry($entry);
            if ($itemAttributes !== []) {
                $normalizedEntry['itemAttributes'] = $itemAttributes;
            }
            $normalized[] = $normalizedEntry;
        }

        return $normalized;
    }

    /**
     * @param list<array{text: string, level: int, href?: string, type?: string, value?: string}> $entries
     * @return list<array{entry: array{text: string, level: int, href?: string, type?: string, value?: string}, children: list<array>}>
     */
    private function navEntryNodes(array $entries, int &$index, int $level): array
    {
        $nodes = [];
        $count = count($entries);
        while ($index < $count) {
            $entry = $entries[$index];
            $entryLevel = $entry['level'];
            if ($entryLevel < $level) {
                break;
            }
            if ($entryLevel > $level) {
                if ($nodes === []) {
                    $level = $entryLevel;
                } else {
                    $lastIndex = array_key_last($nodes);
                    $nodes[$lastIndex]['children'] = array_merge(
                        $nodes[$lastIndex]['children'],
                        $this->navEntryNodes($entries, $index, $entryLevel)
                    );
                    continue;
                }
            }

            $index++;
            $node = [
                'entry' => $entry,
                'children' => [],
            ];
            while ($index < $count && $entries[$index]['level'] > $entryLevel) {
                $node['children'] = array_merge(
                    $node['children'],
                    $this->navEntryNodes($entries, $index, $entries[$index]['level'])
                );
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param list<array{entry: array{text: string, level: int, href?: string, type?: string, value?: string}, children: list<array>}> $nodes
     */
    private function renderNavEntryNodes(array $nodes, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        $itemPad = str_repeat(' ', $indent + 2);
        $html = $pad . "<ol>\n";
        foreach ($nodes as $node) {
            $entry = $node['entry'];
            $href = trim((string) ($entry['href'] ?? ''));
            $attributes = $this->navLabelAttributes($entry, $href !== '');
            $itemAttributes = $this->navListItemAttributes($entry);
            $label = $href === ''
                ? '<span' . $attributes . '>' . $this->esc($entry['text']) . '</span>'
                : '<a' . $attributes . ' href="' . $this->esc($href) . '">' . $this->esc($entry['text']) . '</a>';
            $html .= $itemPad . '<li' . $itemAttributes . '>' . $label;
            if ($node['children'] !== []) {
                $html .= "\n" . $this->renderNavEntryNodes($node['children'], $indent + 4) . "\n" . $itemPad . '</li>';
            } else {
                $html .= '</li>';
            }
            $html .= "\n";
        }

        return $html . $pad . '</ol>';
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function navLabelAttributes(array $entry, bool $anchor = false): string
    {
        $attributes = [];
        if (isset($entry['type'])) {
            $attributes['epub:type'] = (string) $entry['type'];
        }
        foreach (['id' => 'id', 'role' => 'role', 'ariaLabel' => 'aria-label', 'title' => 'title'] as $key => $attribute) {
            $value = trim((string) ($entry[$key] ?? ''));
            if ($value !== '') {
                $attributes[$attribute] = $value;
            }
        }
        if (isset($entry['classes']) && is_array($entry['classes']) && $entry['classes'] !== []) {
            $attributes['class'] = implode(' ', $entry['classes']);
        }
        $language = trim((string) ($entry['lang'] ?? ''));
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
            $attributes['xml:lang'] = $language;
        }
        $direction = strtolower(trim((string) ($entry['dir'] ?? '')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if (($entry['hidden'] ?? false) === true) {
            $attributes['hidden'] = 'hidden';
        }
        if ($anchor) {
            foreach (['rel' => 'rel', 'hreflang' => 'hreflang', 'media' => 'media', 'target' => 'target'] as $key => $attribute) {
                $value = trim((string) ($entry[$key] ?? ''));
                if ($value !== '') {
                    $attributes[$attribute] = $value;
                }
            }
        }

        $xml = '';
        foreach ($attributes as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function navListItemAttributesFromEntry(array $entry): array
    {
        $attributes = $entry['itemAttributes'] ?? $entry['listItemAttributes'] ?? [];
        if (!is_array($attributes)) {
            return [];
        }

        return $this->normalizedNavListItemAttributes($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedNavListItemAttributes(array $attributes): array
    {
        $normalized = [];
        $typeTokens = $this->propertyTokens(
            $attributes['epubType'] ?? $attributes['epub:type'] ?? $attributes['type'] ?? []
        );
        if ($typeTokens !== []) {
            $normalized['type'] = implode(' ', $typeTokens);
        }

        $id = $this->validManifestId($this->entryString($attributes, 'id'));
        if ($id !== '') {
            $normalized['id'] = $id;
        }

        foreach (['role' => 'role', 'ariaLabel' => 'aria-label', 'title' => 'title'] as $key => $attribute) {
            $value = $this->entryString($attributes, $key);
            if ($value === '' && $key === 'ariaLabel') {
                $value = $this->entryString($attributes, 'aria-label');
            }
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        $classes = $this->propertyTokens($attributes['classes'] ?? $attributes['class'] ?? []);
        if ($classes !== []) {
            $normalized['classes'] = $classes;
        }

        $language = $this->entryString($attributes, 'lang') ?: $this->entryString($attributes, 'xml:lang') ?: $this->entryString($attributes, 'xmlLanguage');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $normalized['lang'] = $language;
        }

        $direction = strtolower($this->entryString($attributes, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $normalized['dir'] = $direction;
        }

        if ($this->entryBool($attributes, 'hidden')) {
            $normalized['hidden'] = true;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function navListItemAttributes(array $entry): string
    {
        $attributes = $this->navListItemAttributesFromEntry($entry);
        if ($attributes === []) {
            return '';
        }

        $xmlAttributes = [];
        if (isset($attributes['type'])) {
            $xmlAttributes['epub:type'] = (string) $attributes['type'];
        }
        foreach (['id' => 'id', 'role' => 'role', 'ariaLabel' => 'aria-label', 'title' => 'title'] as $key => $attribute) {
            $value = trim((string) ($attributes[$key] ?? ''));
            if ($value !== '') {
                $xmlAttributes[$attribute] = $value;
            }
        }
        if (isset($attributes['classes']) && is_array($attributes['classes']) && $attributes['classes'] !== []) {
            $xmlAttributes['class'] = implode(' ', $attributes['classes']);
        }
        $language = trim((string) ($attributes['lang'] ?? ''));
        if ($language !== '' && $this->validLanguageTag($language)) {
            $xmlAttributes['lang'] = $language;
            $xmlAttributes['xml:lang'] = $language;
        }
        $direction = strtolower(trim((string) ($attributes['dir'] ?? '')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $xmlAttributes['dir'] = $direction;
        }
        if (($attributes['hidden'] ?? false) === true) {
            $xmlAttributes['hidden'] = 'hidden';
        }

        $xml = '';
        foreach ($xmlAttributes as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function navSectionMetadata(array $meta, string $key, string $titleKey): array
    {
        $attributes = [];
        foreach ($this->navSectionAttributeKeys($key) as $candidateKey) {
            if (isset($meta[$candidateKey]) && is_array($meta[$candidateKey])) {
                $attributes = $meta[$candidateKey];
                break;
            }
        }

        $title = '';
        foreach ($this->navSectionTitleKeys($titleKey) as $candidateKey) {
            $title = $this->metaString($meta, $candidateKey, '');
            if ($title !== '') {
                break;
            }
        }
        if ($title !== '' && $this->navSectionTitle($attributes, '') === '') {
            $attributes['heading'] = $title;
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function navSectionAttributeKeys(string $key): array
    {
        return match ($key) {
            'epubTocNavAttributes' => ['epubTocNavAttributes', 'tocNavAttributes'],
            'epubLandmarkNavAttributes' => ['epubLandmarkNavAttributes', 'landmarkNavAttributes'],
            'epubPageListNavAttributes' => ['epubPageListNavAttributes', 'pageListNavAttributes'],
            default => [$key],
        };
    }

    /**
     * @return list<string>
     */
    private function navSectionTitleKeys(string $key): array
    {
        return match ($key) {
            'epubTocNavTitle' => ['epubTocNavTitle', 'tocNavTitle'],
            'epubLandmarkNavTitle' => ['epubLandmarkNavTitle', 'landmarkNavTitle'],
            'epubPageListNavTitle' => ['epubPageListNavTitle', 'pageListNavTitle'],
            default => [$key],
        };
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function navSectionTitle(array $attributes, string $default): string
    {
        $title = $this->entryString($attributes, 'heading')
            ?: $this->entryString($attributes, 'label')
            ?: $this->entryString($attributes, 'navTitle');

        return $title !== '' ? $title : $default;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function navSectionHeadingLevel(array $attributes, int $default): int
    {
        $level = $this->entryPositiveInteger($attributes, 'headingLevel')
            ?? $this->entryPositiveInteger($attributes, 'heading-level')
            ?? $this->entryPositiveInteger($attributes, 'heading_level')
            ?? $default;

        return max(1, min(6, $level));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function navSectionHeadingAttributes(array $attributes): string
    {
        $headingAttributes = $attributes['headingAttributes'] ?? $attributes['headingHtmlAttributes'] ?? [];
        if (!is_array($headingAttributes)) {
            return '';
        }

        $normalized = $this->normalizedNavListItemAttributes($headingAttributes);
        if ($normalized === []) {
            return '';
        }

        $xmlAttributes = [];
        if (isset($normalized['type'])) {
            $xmlAttributes['epub:type'] = (string) $normalized['type'];
        }
        foreach (['id' => 'id', 'role' => 'role', 'ariaLabel' => 'aria-label', 'title' => 'title'] as $key => $attribute) {
            $value = trim((string) ($normalized[$key] ?? ''));
            if ($value !== '') {
                $xmlAttributes[$attribute] = $value;
            }
        }
        if (isset($normalized['classes']) && is_array($normalized['classes']) && $normalized['classes'] !== []) {
            $xmlAttributes['class'] = implode(' ', $normalized['classes']);
        }
        $language = trim((string) ($normalized['lang'] ?? ''));
        if ($language !== '' && $this->validLanguageTag($language)) {
            $xmlAttributes['lang'] = $language;
            $xmlAttributes['xml:lang'] = $language;
        }
        $direction = strtolower(trim((string) ($normalized['dir'] ?? '')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $xmlAttributes['dir'] = $direction;
        }
        if (($normalized['hidden'] ?? false) === true) {
            $xmlAttributes['hidden'] = 'hidden';
        }

        $xml = '';
        foreach ($xmlAttributes as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function navSectionAttributes(array $attributes, string $requiredType, string $defaultId): string
    {
        $xmlAttributes = [];
        $typeTokens = $this->propertyTokens(
            $this->entryString($attributes, 'epubType')
                ?: $this->entryString($attributes, 'epub:type')
                ?: $this->entryString($attributes, 'type')
        );
        $hasRequiredType = false;
        foreach ($typeTokens as $token) {
            if (strcasecmp($token, $requiredType) === 0) {
                $hasRequiredType = true;
                break;
            }
        }
        if (!$hasRequiredType) {
            array_unshift($typeTokens, $requiredType);
        }
        $xmlAttributes['epub:type'] = implode(' ', array_values(array_unique($typeTokens)));

        $id = $this->validManifestId($this->entryString($attributes, 'id')) ?: $defaultId;
        if ($id !== '') {
            $xmlAttributes['id'] = $id;
        }

        foreach (['role' => 'role', 'ariaLabel' => 'aria-label', 'title' => 'title'] as $key => $attribute) {
            $value = $this->entryString($attributes, $key);
            if ($value === '' && $key === 'ariaLabel') {
                $value = $this->entryString($attributes, 'aria-label');
            }
            if ($value !== '') {
                $xmlAttributes[$attribute] = $value;
            }
        }

        $classes = $this->propertyTokens($attributes['classes'] ?? $attributes['class'] ?? []);
        if ($classes !== []) {
            $xmlAttributes['class'] = implode(' ', $classes);
        }

        $language = $this->entryString($attributes, 'lang') ?: $this->entryString($attributes, 'xml:lang') ?: $this->entryString($attributes, 'xmlLanguage');
        if ($language !== '' && $this->validLanguageTag($language)) {
            $xmlAttributes['lang'] = $language;
            $xmlAttributes['xml:lang'] = $language;
        }

        $direction = strtolower($this->entryString($attributes, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $xmlAttributes['dir'] = $direction;
        }

        if ($this->entryBool($attributes, 'hidden')) {
            $xmlAttributes['hidden'] = 'hidden';
        }

        $xml = '';
        foreach ($xmlAttributes as $name => $value) {
            $xml .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryBool(array $entry, string $key): bool
    {
        $value = $entry[$key] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    /**
     * @return list<array{id: string, path: string, href: string, document: AstNode, title: string}>
     */
    private function chapterDocuments(AstNode $document, string $chapterPath, string $packageDir): array
    {
        return $this->chapterDocumentsForSplitLevel($document, $chapterPath, $packageDir, $this->splitLevel());
    }

    /**
     * @return list<array{id: string, path: string, href: string, document: AstNode, title: string}>
     */
    private function chapterDocumentsForSplitLevel(AstNode $document, string $chapterPath, string $packageDir, int $splitLevel): array
    {
        if ($splitLevel <= 0) {
            $chapterIds = $this->linearSpineManifestIds($document, 1);
            return [[
                'id' => $chapterIds[0],
                'path' => $chapterPath,
                'href' => $this->relativePath($packageDir, $chapterPath),
                'document' => $document,
                'title' => $this->chapterTitle($document->children, $document),
            ]];
        }

        $groups = [];
        $current = [];
        foreach ($document->children as $node) {
            if ($node->type === 'heading' && (int) $node->attr('level', 1) <= $splitLevel && $current !== []) {
                $groups[] = $current;
                $current = [];
            }
            $current[] = $node;
        }
        if ($current !== []) {
            $groups[] = $current;
        }
        if ($groups === []) {
            $groups[] = [];
        }

        $chapters = [];
        $chapterIds = $this->linearSpineManifestIds($document, count($groups));
        foreach ($groups as $index => $children) {
            $path = $this->indexedChapterPath($chapterPath, $index + 1);
            $chapterDocument = new AstNode('document', $document->attrs, $children);
            $chapters[] = [
                'id' => $chapterIds[$index],
                'path' => $path,
                'href' => $this->relativePath($packageDir, $path),
                'document' => $chapterDocument,
                'title' => $this->chapterTitle($children, $document),
            ];
        }

        return $chapters;
    }

    /**
     * @return list<string>
     */
    private function linearSpineManifestIds(AstNode $document, int $count): array
    {
        $meta = $this->metadata($document);
        $source = $this->optionOrMetaValue($meta, ['spineManifestIds'], ['spineManifestIds', 'epubSpineManifestIds'], null, false);
        $fromSpineItemRefs = false;
        if (!is_array($source)) {
            $source = $meta['epubSpineItemRefs'] ?? [];
            $fromSpineItemRefs = true;
        }
        if (!is_array($source)) {
            $source = [];
        }
        if ($fromSpineItemRefs) {
            $source = array_values(array_filter(
                $source,
                fn (mixed $entry): bool => is_array($entry) && !$this->spineEntryIsNonLinear($entry)
            ));
        }

        $ids = [];
        $seen = ['nav' => true];
        for ($index = 0; $index < $count; $index++) {
            $entry = $source[$index] ?? null;
            $preferred = '';
            if (is_array($entry)) {
                $preferred = $this->validManifestId(ltrim($this->entryString($entry, 'idref'), '#'));
            } elseif (is_scalar($entry)) {
                $preferred = $this->validManifestId(ltrim(trim((string) $entry), '#'));
            }

            $fallback = 'chapter-' . ($index + 1);
            $id = $preferred !== '' && !isset($seen[$preferred])
                ? $preferred
                : $this->uniqueManifestId($fallback, $seen);
            $seen[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param array<string, true> $seen
     */
    private function uniqueManifestId(string $base, array $seen): string
    {
        $id = $this->validManifestId($base) ?: 'chapter';
        if (!isset($seen[$id])) {
            return $id;
        }

        $index = 2;
        while (isset($seen[$id . '-' . $index])) {
            $index++;
        }

        return $id . '-' . $index;
    }

    private function splitLevel(): int
    {
        $level = $this->options['splitLevel'] ?? 1;

        return $this->normalizedSplitLevel($level, 1);
    }

    private function alternateRootfileSplitLevel(array $entry): int
    {
        foreach (['splitLevel', 'split-level', 'epubSplitLevel'] as $key) {
            if (array_key_exists($key, $entry)) {
                return $this->normalizedSplitLevel($entry[$key], $this->splitLevel());
            }
        }

        return $this->splitLevel();
    }

    private function normalizedSplitLevel(mixed $level, int $default): int
    {
        if (!is_int($level) && !is_float($level) && !(is_string($level) && is_numeric($level))) {
            return $default;
        }

        return max(0, min(6, (int) $level));
    }

    /**
     * @param list<AstNode> $children
     */
    private function chapterTitle(array $children, AstNode $document): string
    {
        foreach ($children as $node) {
            if ($node->type !== 'heading') {
                continue;
            }
            $text = trim((string) $node->attr('text', $this->plainInlineText($node->children)));
            if ($text !== '') {
                return $text;
            }
        }

        return $this->metaString($this->metadata($document), 'title', 'Untitled');
    }

    private function indexedChapterPath(string $chapterPath, int $index): string
    {
        if ($index <= 1) {
            return $chapterPath;
        }

        $dir = $this->dirname($chapterPath);
        $filename = basename($chapterPath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));
        $path = $stem . '-' . $index . ($extension === '' ? '' : '.' . $extension);

        return $dir === '' ? $path : $dir . '/' . $path;
    }

    private function documentWithHeadingIds(AstNode $document): AstNode
    {
        $seen = [];
        $children = [];
        foreach ($document->children as $node) {
            if ($node->type !== 'heading') {
                $children[] = $node;
                continue;
            }

            $attrs = $node->attrs;
            $id = trim((string) ($attrs['id'] ?? ''));
            if ($id === '') {
                $text = trim((string) $node->attr('text', $this->plainInlineText($node->children)));
                $id = $this->slug($text);
            }
            if ($id === '') {
                $id = 'heading';
            }
            $base = $id;
            $index = 2;
            while (isset($seen[$id])) {
                $id = $base . '-' . $index;
                $index++;
            }
            $seen[$id] = true;
            $attrs['id'] = $id;
            $children[] = new AstNode($node->type, $attrs, $node->children);
        }

        return new AstNode($document->type, $document->attrs, $children);
    }

    /**
     * @param list<array{id: string, path: string, href: string, document: AstNode, title: string}> $chapters
     * @return list<array{text: string, href: string, level: int}>
     */
    private function headingEntries(array $chapters, string $navDir): array
    {
        $entries = [];
        foreach ($chapters as $chapter) {
            foreach ($chapter['document']->children as $node) {
                if ($node->type !== 'heading') {
                    continue;
                }

                $text = trim((string) $node->attr('text', $this->plainInlineText($node->children)));
                $id = trim((string) $node->attr('id', ''));
                if ($text === '' || $id === '') {
                    continue;
                }
                $level = (int) $node->attr('level', 1);
                $entries[] = [
                    'text' => $text,
                    'href' => $this->relativePath($navDir, $chapter['path']) . '#' . $id,
                    'level' => max(1, min(6, $level)),
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(AstNode $document): array
    {
        $meta = $document->attr('meta', []);

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function identifier(array $meta): string
    {
        $identifier = $this->preferMetadataOverOptions
            ? $this->metaString($meta, 'identifier', '')
            : (string) ($this->options['identifier'] ?? $this->metaString($meta, 'identifier', ''));
        if ($identifier !== '') {
            return $identifier;
        }

        $title = $this->metaString($meta, 'title', 'untitled');

        return 'urn:uuid:' . sha1($title . "\0" . $this->plainDocumentSeed($meta));
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function language(array $meta): string
    {
        $language = $this->preferMetadataOverOptions
            ? $this->metaString($meta, 'lang', $this->metaString($meta, 'language', 'en'))
            : (string) ($this->options['language'] ?? $this->metaString($meta, 'lang', $this->metaString($meta, 'language', 'en')));

        return $this->validLanguageTag($language) ? $language : 'en';
    }

    private function validLanguageTag(string $language): bool
    {
        return preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $language) === 1;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function modified(array $meta): string
    {
        $modified = $this->optionOrMetaString($meta, 'modified', 'epubModified', false);
        if ($modified === '') {
            $modified = gmdate('Y-m-d\TH:i:s\Z');
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $modified) === 1
            ? $modified
            : gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function metaString(array $meta, string $key, string $default): string
    {
        $value = $meta[$key] ?? null;
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        if (is_array($value) && isset($value['type'])) {
            return trim($this->metaValueText($value));
        }
        if (is_array($value) && $this->arrayIsAstNodes($value)) {
            return trim($this->plainInlineText($value));
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function metaList(array $meta, string $key): array
    {
        $value = $meta[$key] ?? null;
        if ($value === null && $key === 'author') {
            $value = $meta['authors'] ?? null;
        }
        if ($value === null) {
            return [];
        }
        if (is_scalar($value)) {
            $text = trim((string) $value);
            return $text === '' ? [] : [$text];
        }
        if (is_array($value) && isset($value['type'])) {
            $text = trim($this->metaValueText($value));
            return $text === '' ? [] : [$text];
        }
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if ($item instanceof AstNode) {
                    $items[] = $this->plainInlineText([$item]);
                    continue;
                }
                if (is_array($item) && $this->arrayIsAstNodes($item)) {
                    $items[] = $this->plainInlineText($item);
                    continue;
                }
                if (is_scalar($item)) {
                    $items[] = (string) $item;
                }
            }

            return array_values(array_filter(array_map('trim', $items), static fn (string $item): bool => $item !== ''));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function metaValueText(array $value): string
    {
        $payload = $value['value'] ?? null;
        if (is_array($payload) && $this->arrayIsAstNodes($payload)) {
            return $this->plainInlineText($payload);
        }
        if (is_array($payload)) {
            $parts = [];
            foreach ($payload as $item) {
                if ($item instanceof AstNode) {
                    $parts[] = $this->plainInlineText([$item]);
                } elseif (is_scalar($item)) {
                    $parts[] = (string) $item;
                }
            }

            return implode(' ', $parts);
        }
        if (is_scalar($payload)) {
            return (string) $payload;
        }

        return '';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'image' => (string) $node->attr('alt', $this->plainInlineText($node->children)),
                'citation' => (string) $node->attr('text', $this->plainInlineText($node->children)),
                'math' => (string) $node->attr('text', ''),
                'raw_html_inline' => '',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function plainDocumentSeed(array $meta): string
    {
        $seed = [];
        foreach (['author', 'date', 'description'] as $key) {
            if (isset($meta[$key]) && is_scalar($meta[$key])) {
                $seed[] = (string) $meta[$key];
            }
        }

        return implode("\0", $seed);
    }

    /**
     * @param array<mixed> $items
     */
    private function arrayIsAstNodes(array $items): bool
    {
        foreach ($items as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return $items !== [];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function resources(array $meta, string $packageDir = ''): array
    {
        $resources = $this->metadataResources($meta);
        $optionResources = $this->options['resources'] ?? null;
        if (is_array($optionResources)) {
            foreach ($optionResources as $path => $bytes) {
                if (!is_string($path) || !is_string($bytes)) {
                    continue;
                }
                $path = $this->safePackagePath($path);
                if ($path === '' || $this->isOcfSidecarPath($path)) {
                    continue;
                }
                $resources[$path] = $bytes;
            }
        }

        if (!$this->isEpub2($meta)) {
            foreach ($this->generatedMediaOverlayResources($meta, $packageDir, $resources) as $path => $bytes) {
                $resources[$path] = $bytes;
            }
        }

        return $this->rewriteCssResourcePayloads($resources, $packageDir, $this->resourceMediaTypes($meta));
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, string> $resourceMediaTypes
     * @return array<string, string>
     */
    private function rewriteCssResourcePayloads(array $resources, string $packageDir, array $resourceMediaTypes): array
    {
        foreach ($resources as $path => $bytes) {
            $mediaType = $resourceMediaTypes[$path] ?? $this->mediaType($path);
            if (!$this->resourceLooksLikeCss($path, $mediaType)) {
                continue;
            }

            $resources[$path] = $this->rewriteRenderedCssResourceReferences($bytes, $resources, $packageDir, $this->dirname($path));
        }

        return $resources;
    }

    private function resourceLooksLikeCss(string $path, string $mediaType): bool
    {
        $mediaType = strtolower($mediaType);
        $path = strtolower($path);

        return str_contains($mediaType, 'css') || str_ends_with($path, '.css');
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $existingResources
     * @return array<string, string>
     */
    private function generatedMediaOverlayResources(array $meta, string $packageDir, array $existingResources): array
    {
        $source = $this->optionOrMetaValue($meta, ['mediaOverlays'], ['epubMediaOverlays'], [], false);
        if (!is_array($source)) {
            return [];
        }

        $resources = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $pairs = $entry['pairs'] ?? [];
            if (!is_array($pairs) || $pairs === []) {
                continue;
            }
            $path = $this->mediaOverlayPath($entry, $packageDir);
            if ($path === '' || isset($existingResources[$path]) || isset($resources[$path])) {
                continue;
            }
            $smil = $this->mediaOverlaySmilXml($entry, $pairs, $path, $packageDir);
            if ($smil !== '') {
                $resources[$path] = $smil;
            }
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function mediaOverlayPath(array $entry, string $packageDir): string
    {
        $path = $this->entryPackagePath($entry, 'overlayPath', '');
        if ($path === '') {
            $path = $this->entryPackagePath($entry, 'overlayHref', $packageDir);
        }
        if ($path !== '') {
            return $path;
        }

        $overlayId = $this->validManifestId($this->entryString($entry, 'overlayId') ?: $this->entryString($entry, 'mediaOverlay'));
        if ($overlayId === '') {
            return '';
        }

        return $this->safePackagePath(($packageDir === '' ? '' : $packageDir . '/') . 'overlays/' . $overlayId . '.smil');
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<int|string, mixed> $pairs
     */
    private function mediaOverlaySmilXml(array $entry, array $pairs, string $overlayPath, string $packageDir): string
    {
        $overlayDir = $this->dirname($overlayPath);
        $sequenceXml = $this->mediaOverlaySequencesXml($entry, $pairs, $overlayDir, $packageDir);
        if ($sequenceXml['xml'] === '') {
            return '';
        }

        $bodyAttributes = $this->mediaOverlayBodyAttributes($entry);
        $pairAttributes = $this->mediaOverlayPairNamespaceAttributes($pairs);
        $rootAttributes = $this->mediaOverlayRootAttributes($entry, $bodyAttributes, array_replace($sequenceXml['attributes'], $pairAttributes));

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<smil' . $this->xmlAttributes($rootAttributes) . ">\n"
            . "  <body" . $this->xmlAttributes($bodyAttributes) . ">\n"
            . $sequenceXml['xml']
            . "  </body>\n"
            . "</smil>\n";
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<int|string, mixed> $pairs
     * @return array{xml: string, attributes: array<string, string>}
     */
    private function mediaOverlaySequencesXml(array $entry, array $pairs, string $overlayDir, string $packageDir): array
    {
        $sequences = $entry['sequences'] ?? [];
        $sequenceXml = '';
        $namespaceAttributes = [];
        $usedPairs = [];

        if (is_array($sequences) && $sequences !== []) {
            $sequenceCount = count(array_filter($sequences, 'is_array'));
            foreach ($sequences as $sequence) {
                if (!is_array($sequence)) {
                    continue;
                }
                $sequencePairs = $this->mediaOverlayPairsForSequence($sequence, $pairs, $usedPairs, $sequenceCount === 1);
                $parXml = $this->mediaOverlayPairsXml($sequencePairs, $overlayDir, $packageDir);
                if ($parXml === '') {
                    continue;
                }
                $attributes = $this->mediaOverlaySequenceAttributes($sequence, $overlayDir, $packageDir, true);
                $namespaceAttributes = array_replace($namespaceAttributes, $attributes);
                $sequenceXml .= "    <seq" . $this->xmlAttributes($attributes) . ">\n"
                    . $parXml
                    . "    </seq>\n";
            }
        }

        $remainingPairs = [];
        foreach ($pairs as $index => $pair) {
            if (!is_array($pair) || isset($usedPairs[(string) $index])) {
                continue;
            }
            $remainingPairs[] = $pair;
        }

        if ($remainingPairs !== []) {
            $attributes = $sequenceXml === ''
                ? $this->mediaOverlaySequenceAttributes($entry, $overlayDir, $packageDir)
                : [];
            $namespaceAttributes = array_replace($namespaceAttributes, $attributes);
            $sequenceXml .= "    <seq" . $this->xmlAttributes($attributes) . ">\n"
                . $this->mediaOverlayPairsXml($remainingPairs, $overlayDir, $packageDir)
                . "    </seq>\n";
        }

        return [
            'xml' => $sequenceXml,
            'attributes' => $namespaceAttributes,
        ];
    }

    /**
     * @param array<string, mixed> $sequence
     * @param array<int|string, mixed> $pairs
     * @param array<string, true> $usedPairs
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayPairsForSequence(array $sequence, array $pairs, array &$usedPairs, bool $useAllWhenUnspecified): array
    {
        $explicitPairs = $sequence['pairs'] ?? null;
        if (is_array($explicitPairs)) {
            $selected = [];
            foreach ($explicitPairs as $pair) {
                if (is_array($pair)) {
                    $selected[] = $pair;
                    $this->markMediaOverlayPairUsed($pair, $pairs, $usedPairs);
                }
            }

            return $selected;
        }

        $parIds = $this->propertyTokens($sequence['parIds'] ?? $sequence['par-ids'] ?? []);
        if ($parIds !== []) {
            $wanted = array_fill_keys($parIds, true);
            $selected = [];
            foreach ($pairs as $index => $pair) {
                if (!is_array($pair) || isset($usedPairs[(string) $index])) {
                    continue;
                }
                $id = $this->validManifestId($this->entryString($pair, 'id'));
                if ($id === '' || !isset($wanted[$id])) {
                    continue;
                }
                $usedPairs[(string) $index] = true;
                $selected[] = $pair;
            }

            return $selected;
        }

        if (!$useAllWhenUnspecified) {
            return [];
        }

        $selected = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $selected[] = $pair;
        }

        foreach (array_keys($pairs) as $index) {
            $usedPairs[(string) $index] = true;
        }

        return $selected;
    }

    /**
     * @param array<string, mixed> $selectedPair
     * @param array<int|string, mixed> $pairs
     * @param array<string, true> $usedPairs
     */
    private function markMediaOverlayPairUsed(array $selectedPair, array $pairs, array &$usedPairs): void
    {
        $selectedId = $this->validManifestId($this->entryString($selectedPair, 'id'));
        if ($selectedId === '') {
            return;
        }

        foreach ($pairs as $index => $pair) {
            if (!is_array($pair) || isset($usedPairs[(string) $index])) {
                continue;
            }
            if ($this->validManifestId($this->entryString($pair, 'id')) === $selectedId) {
                $usedPairs[(string) $index] = true;
                return;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $pairs
     */
    private function mediaOverlayPairsXml(array $pairs, string $overlayDir, string $packageDir): string
    {
        $parXml = '';
        foreach ($pairs as $pair) {
            $text = $this->entryString($pair, 'text');
            $audio = $this->entryString($pair, 'audio');
            if ($text === '' && $audio === '') {
                continue;
            }

            $attributes = [];
            $id = $this->validManifestId($this->entryString($pair, 'id'));
            if ($id !== '') {
                $attributes['id'] = $id;
            }

            $parXml .= "      <par" . $this->xmlAttributes($attributes) . ">\n";
            if ($text !== '') {
                $textAttributes = $this->mediaOverlayPairChildAttributes($pair, 'text', [
                    'src' => $this->mediaOverlaySmilHref($text, $overlayDir, $packageDir),
                ]);
                $parXml .= '        <text' . $this->xmlAttributes($textAttributes) . "/>\n";
            }
            if ($audio !== '') {
                $audioAttributes = $this->mediaOverlayPairChildAttributes($pair, 'audio', [
                    'src' => $this->mediaOverlaySmilHref($audio, $overlayDir, $packageDir),
                ]);
                foreach (['clipBegin', 'clipEnd'] as $key) {
                    $value = $this->entryString($pair, $key);
                    if ($value !== '') {
                        $audioAttributes[$key] = $value;
                    }
                }
                $parXml .= '        <audio' . $this->xmlAttributes($audioAttributes) . "/>\n";
            }
            $parXml .= "      </par>\n";
        }

        return $parXml;
    }

    /**
     * @param array<string, mixed> $pair
     * @param array<string, string> $defaults
     * @return array<string, string>
     */
    private function mediaOverlayPairChildAttributes(array $pair, string $kind, array $defaults): array
    {
        $attributes = $defaults;
        $source = $this->mediaOverlayAttributeSource($pair, [
            $kind . 'Attributes',
            $kind . 'Attrs',
            'smil' . ucfirst($kind) . 'Attributes',
        ]);

        $id = $this->validManifestId($this->entryString($source, 'id'));
        if ($id === '') {
            $id = $this->validManifestId($this->entryFirstString($pair, [$kind . 'Id', $kind . 'ElementId']));
        }
        if ($id !== '') {
            $attributes['id'] = $id;
        }

        foreach (['role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($source, $key) ?: ($key === 'ariaLabel' ? $this->entryString($source, 'aria-label') : '');
            if ($value !== '') {
                $attributes[$attribute] = $value;
            }
        }

        $classes = $this->propertyTokens($source['classes'] ?? $source['class'] ?? []);
        if ($classes !== []) {
            $attributes['class'] = implode(' ', $classes);
        }

        foreach (['epubType', 'epub:type', 'type'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $tokens = $this->propertyTokens($source[$key]);
            if ($tokens !== []) {
                $attributes['epub:type'] = implode(' ', $tokens);
                break;
            }
        }

        $language = $this->entryFirstString($source, ['lang', 'xml:lang', 'xmlLanguage', 'language']);
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attributes['xml:lang'] = $language;
        }

        $direction = strtolower($this->entryString($source, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($this->entryBool($source, 'hidden')) {
            $attributes['hidden'] = 'hidden';
        }

        return $attributes;
    }

    /**
     * @param array<int|string, mixed> $pairs
     * @return array<string, string>
     */
    private function mediaOverlayPairNamespaceAttributes(array $pairs): array
    {
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            foreach (['text', 'audio'] as $kind) {
                $attributes = $this->mediaOverlayPairChildAttributes($pair, $kind, []);
                if ($this->xmlAttributesNeedEpubNamespace($attributes)) {
                    return ['epub:type' => ''];
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string> $bodyAttributes
     * @param array<string, string> $seqAttributes
     * @return array<string, string>
     */
    private function mediaOverlayRootAttributes(array $entry, array $bodyAttributes, array $seqAttributes): array
    {
        $source = $this->mediaOverlayAttributeSource($entry, ['rootAttributes', 'rootAttrs', 'smilRootAttributes', 'smilAttributes']);
        $payload = [];

        $id = $this->validManifestId($this->entryString($source, 'id'));
        if ($id !== '') {
            $payload['id'] = $id;
        }

        foreach (['role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($source, $key) ?: ($key === 'ariaLabel' ? $this->entryString($source, 'aria-label') : '');
            if ($value !== '') {
                $payload[$attribute] = $value;
            }
        }

        $classes = $this->propertyTokens($source['classes'] ?? $source['class'] ?? []);
        if ($classes !== []) {
            $payload['class'] = implode(' ', $classes);
        }

        $prefix = $this->entryString($source, 'prefix');
        if ($prefix !== '') {
            $payload['prefix'] = $prefix;
        }

        foreach (['epubType', 'epub:type', 'type'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $tokens = $this->propertyTokens($source[$key]);
            if ($tokens !== []) {
                $payload['epub:type'] = implode(' ', $tokens);
                break;
            }
        }

        $language = $this->entryFirstString($source, ['lang', 'xml:lang', 'xmlLanguage', 'language'])
            ?: $this->entryFirstString($entry, ['lang', 'xml:lang', 'xmlLanguage', 'language']);
        if ($language !== '' && $this->validLanguageTag($language)) {
            $payload['xml:lang'] = $language;
        }

        $direction = strtolower($this->entryString($source, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $payload['dir'] = $direction;
        }
        if ($this->entryBool($source, 'hidden')) {
            $payload['hidden'] = 'hidden';
        }

        $version = $this->entryString($source, 'version');
        if ($version === '') {
            $version = '3.0';
        }

        $attributes = [
            'xmlns' => 'http://www.w3.org/ns/SMIL',
        ];
        if (
            $this->xmlAttributesNeedEpubNamespace($payload)
            || $this->xmlAttributesNeedEpubNamespace($bodyAttributes)
            || $this->xmlAttributesNeedEpubNamespace($seqAttributes)
        ) {
            $attributes['xmlns:epub'] = 'http://www.idpf.org/2007/ops';
        }
        $attributes['version'] = $version;

        return array_merge($attributes, $payload);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    private function mediaOverlayBodyAttributes(array $entry): array
    {
        $source = $this->mediaOverlayAttributeSource($entry, ['bodyAttributes', 'bodyAttrs', 'smilBodyAttributes']);
        $attributes = [];

        $id = $this->validManifestId($this->entryString($source, 'id'));
        if ($id !== '') {
            $attributes['id'] = $id;
        }

        foreach (['role' => 'role', 'title' => 'title', 'ariaLabel' => 'aria-label'] as $key => $attribute) {
            $value = $this->entryString($source, $key) ?: ($key === 'ariaLabel' ? $this->entryString($source, 'aria-label') : '');
            if ($value !== '') {
                $attributes[$attribute] = $value;
            }
        }

        $classes = $this->propertyTokens($source['classes'] ?? $source['class'] ?? []);
        if ($classes !== []) {
            $attributes['class'] = implode(' ', $classes);
        }

        foreach (['epubType', 'epub:type', 'type'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $tokens = $this->propertyTokens($source[$key]);
            if ($tokens !== []) {
                $attributes['epub:type'] = implode(' ', $tokens);
                break;
            }
        }

        $language = $this->entryFirstString($source, ['lang', 'xml:lang', 'xmlLanguage', 'language']);
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attributes['xml:lang'] = $language;
        }

        $direction = strtolower($this->entryString($source, 'dir'));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($this->entryBool($source, 'hidden')) {
            $attributes['hidden'] = 'hidden';
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $entry
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function mediaOverlayAttributeSource(array $entry, array $keys): array
    {
        foreach ($keys as $key) {
            $source = $entry[$key] ?? null;
            if (is_array($source)) {
                return $source;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    private function mediaOverlaySequenceAttributes(array $entry, string $overlayDir, string $packageDir, bool $entryIsSequence = false): array
    {
        $source = $entry;
        $sourceIsSequence = $entryIsSequence;
        $sequences = $entry['sequences'] ?? [];
        if (!$entryIsSequence && is_array($sequences)) {
            foreach ($sequences as $sequence) {
                if (is_array($sequence)) {
                    $source = $sequence;
                    $sourceIsSequence = true;
                    break;
                }
            }
        }

        $attributes = [];
        foreach ($sourceIsSequence ? ['id', 'sequenceId', 'seqId'] : ['sequenceId', 'seqId'] as $key) {
            $id = $this->validManifestId($this->entryString($source, $key));
            if ($id !== '') {
                $attributes['id'] = $id;
                break;
            }
        }

        $textref = $this->entryFirstString($source, ['textref', 'textRef', 'epubTextref', 'epubTextRef', 'epub:textref']);
        if ($textref === '') {
            foreach (['contentPath', 'contentHref'] as $key) {
                $path = $this->entryPackagePath($entry, $key, $packageDir);
                if ($path !== '') {
                    $textref = $path;
                    break;
                }
            }
        }
        if ($textref !== '') {
            $attributes['epub:textref'] = $this->mediaOverlaySmilHref($textref, $overlayDir, $packageDir);
        }

        foreach (['type', 'epubType', 'epub:type'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $tokens = $this->propertyTokens($source[$key]);
            if ($tokens !== []) {
                $attributes['epub:type'] = implode(' ', $tokens);
                break;
            }
        }

        $language = $this->entryFirstString($source, ['lang', 'xml:lang', 'xmlLanguage', 'language']);
        if ($language !== '' && $this->validLanguageTag($language)) {
            $attributes['xml:lang'] = $language;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $entry
     * @param list<string> $keys
     */
    private function entryFirstString(array $entry, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->entryString($entry, $key);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $attributes
     */
    private function xmlAttributesNeedEpubNamespace(array $attributes): bool
    {
        foreach (array_keys($attributes) as $name) {
            if (str_starts_with((string) $name, 'epub:')) {
                return true;
            }
        }

        return false;
    }

    private function mediaOverlaySmilHref(string $href, string $overlayDir, string $packageDir): string
    {
        return $this->entryHref($href, $overlayDir, $packageDir);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function resourceMediaTypes(array $meta): array
    {
        $normalized = $this->preferMetadataOverOptions ? [] : $this->metadataResourceMediaTypes($meta);
        foreach ([$meta['resourceMediaTypes'] ?? [], $meta['epubResourceMediaTypes'] ?? []] as $mediaTypes) {
            if (!is_array($mediaTypes)) {
                continue;
            }
            foreach ($mediaTypes as $path => $mediaType) {
                if (!is_string($path) || !is_scalar($mediaType)) {
                    continue;
                }
                $mediaType = trim((string) $mediaType);
                $path = $this->safePackagePath($path);
                if ($path !== '' && $mediaType !== '') {
                    $normalized[$path] = $mediaType;
                }
            }
        }
        if ($this->preferMetadataOverOptions) {
            foreach ($this->metadataResourceMediaTypes($meta) as $path => $mediaType) {
                $normalized[$path] = $mediaType;
            }

            return $normalized;
        }

        foreach ($this->optionResourceMediaTypes() as $path => $mediaType) {
            $normalized[$path] = $mediaType;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function optionResourceMediaTypes(): array
    {
        $mediaTypes = $this->options['resourceMediaTypes'] ?? [];
        if (!is_array($mediaTypes)) {
            return [];
        }

        $normalized = [];
        foreach ($mediaTypes as $path => $mediaType) {
            if (!is_string($path) || !is_scalar($mediaType)) {
                continue;
            }
            $mediaType = trim((string) $mediaType);
            if ($mediaType === '') {
                continue;
            }
            $normalized[$this->safePackagePath($path)] = $mediaType;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function metadataResources(array $meta): array
    {
        $resources = [];
        foreach ([$meta['resources'] ?? [], $meta['epubResources'] ?? []] as $source) {
            if (!is_array($source)) {
                continue;
            }
            foreach ($source as $path => $bytes) {
                if (!is_string($path) || !is_string($bytes)) {
                    continue;
                }
                $path = $this->safePackagePath($path);
                if ($path === '' || $this->isOcfSidecarPath($path)) {
                    continue;
                }
                $resources[$path] = $bytes;
            }
        }

        $payloads = $meta['epubResourcePayloads'] ?? [];
        if (!is_array($payloads)) {
            return $resources;
        }

        foreach ($payloads as $path => $payload) {
            if (!is_string($path) || !is_array($payload)) {
                continue;
            }
            $data = $payload['data'] ?? null;
            $encoding = strtolower((string) ($payload['encoding'] ?? ''));
            if (!is_string($data) || $encoding !== 'base64') {
                continue;
            }
            $bytes = base64_decode($data, true);
            if (!is_string($bytes)) {
                continue;
            }
            $path = $this->safePackagePath($path);
            if ($path === '' || $this->isOcfSidecarPath($path)) {
                continue;
            }
            $resources[$path] = $bytes;
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function ocfSidecarPayloads(array $meta): array
    {
        $payloads = $meta['epubOcfSidecarPayloads'] ?? $this->options['ocfSidecars'] ?? [];
        if (!is_array($payloads)) {
            return [];
        }

        $sidecars = [];
        foreach ($payloads as $path => $payload) {
            if (!is_string($path) || !is_array($payload)) {
                continue;
            }
            $path = $this->safePackagePath($path);
            if (!$this->isOcfSidecarPayloadPath($path)) {
                continue;
            }
            $data = $payload['data'] ?? null;
            $encoding = strtolower((string) ($payload['encoding'] ?? ''));
            if (!is_string($data) || $encoding !== 'base64') {
                continue;
            }
            $bytes = base64_decode($data, true);
            if (!is_string($bytes)) {
                continue;
            }
            $sidecars[$path] = $bytes;
        }

        return $sidecars;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, array<string, mixed>>
     */
    private function containerRootfilePayloads(array $meta, AstNode $document): array
    {
        $payloads = $meta['epubContainerRootfilePayloads'] ?? $this->options['containerRootfilePayloads'] ?? [];
        if (!is_array($payloads)) {
            $payloads = [];
        }

        $rootfiles = [];
        foreach ($payloads as $path => $payload) {
            $payload = is_string($payload) ? ['payloadBytes' => $payload] : $payload;
            if (!is_array($payload)) {
                continue;
            }
            $sourcePath = is_string($path) ? $path : (string) ($payload['path'] ?? $payload['fullPath'] ?? '');
            if (!$this->validContainerRootfileSourcePath($sourcePath)) {
                continue;
            }
            $path = $this->safePackagePath($sourcePath);
            if ($path === '' || $path === 'mimetype' || $path === 'META-INF/container.xml' || $this->isOcfSidecarPath($path)) {
                continue;
            }

            $mediaType = $this->entryString($payload, 'mediaType') ?: $this->entryString($payload, 'media-type') ?: 'application/oebps-package+xml';
            if (!$this->containerRootfileLooksLikeOpf($path, $mediaType)) {
                continue;
            }

            $bytes = $this->containerRootfilePayloadBytes($payload);
            if (!is_string($bytes)) {
                continue;
            }

            $entry = array_replace($this->containerRootfileMetadataForPath($meta, $path), $payload);
            $entry['path'] = $path;
            $entry['mediaType'] = $mediaType;
            $entry['payloadBytes'] = $bytes;
            $rootfiles[$path] = $entry;
        }

        foreach ($this->generatedAlternateRootfilePayloads($meta, $document) as $path => $payload) {
            if (!isset($rootfiles[$path])) {
                $rootfiles[$path] = $payload;
            }
        }

        return $rootfiles;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, array<string, mixed>>
     */
    private function generatedAlternateRootfilePayloads(array $meta, AstNode $document): array
    {
        $source = $meta['epubAlternateRootfiles']
            ?? $meta['alternateRootfiles']
            ?? $this->options['epubAlternateRootfiles']
            ?? $this->options['alternateRootfiles']
            ?? [];
        if (!is_array($source) || $source === []) {
            $source = $meta['epubAlternateRootfilePackages']
                ?? $this->options['epubAlternateRootfilePackages']
                ?? [];
        }
        if (!is_array($source)) {
            return [];
        }

        $rootfiles = [];
        foreach ($source as $path => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sourcePath = is_string($path) ? $path : $this->entryFirstString($entry, ['path', 'fullPath', 'full-path', 'rootfile']);
            if (!$this->validContainerRootfileSourcePath($sourcePath)) {
                continue;
            }
            $path = $this->safePackagePath($sourcePath);
            if ($path === '' || $path === 'mimetype' || $path === 'META-INF/container.xml' || $this->isOcfSidecarPath($path)) {
                continue;
            }

            $mediaType = $this->entryFirstString($entry, ['mediaType', 'media-type']) ?: 'application/oebps-package+xml';
            if (!$this->containerRootfileLooksLikeOpf($path, $mediaType)) {
                continue;
            }

            $payload = $this->generatedAlternateRootfilePayload($entry, $path, $document);
            if ($payload === null) {
                continue;
            }

            $metadata = array_replace($this->containerRootfileMetadataForPath($meta, $path), $entry);
            $metadata['path'] = $path;
            $metadata['mediaType'] = $mediaType;
            $metadata['payloadBytes'] = $payload['payloadBytes'];
            $metadata['packagePayloads'] = $payload['packagePayloads'];
            $rootfiles[$path] = $metadata;
        }

        return $rootfiles;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{payloadBytes: string, packagePayloads: array<string, string>}|null
     */
    private function generatedAlternateRootfilePayload(array $entry, string $packagePath, AstNode $primaryDocument): ?array
    {
        return $this->withMetadataOptionPrecedence(
            fn (): ?array => $this->generatedAlternateRootfilePayloadWithMetadataPrecedence($entry, $packagePath, $primaryDocument)
        );
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{payloadBytes: string, packagePayloads: array<string, string>}|null
     */
    private function generatedAlternateRootfilePayloadWithMetadataPrecedence(array $entry, string $packagePath, AstNode $primaryDocument): ?array
    {
        $document = $this->alternateRootfileDocument($entry, $primaryDocument);
        if ($document === null) {
            return null;
        }

        $document = $this->documentWithHeadingIds($document);
        $documentMeta = $this->metadata($document);
        $packageDir = $this->dirname($packagePath);
        $chapterPath = $this->entryFirstString($entry, ['chapterPath', 'chapter-path', 'spinePath', 'spine-path']);
        $chapterPath = $chapterPath === '' ? $packageDir . '/text/chapter.xhtml' : $chapterPath;
        $chapterPath = $this->safePackagePath($chapterPath);
        $includeNav = $this->includeEpub3Nav($documentMeta);
        $navPath = '';
        if ($includeNav) {
            $navPath = $this->entryFirstString($entry, ['navPath', 'nav-path']);
            $navPath = $navPath === '' ? $packageDir . '/nav.xhtml' : $navPath;
            $navPath = $this->safePackagePath($navPath);
        }
        if ($chapterPath === '' || $chapterPath === $packagePath || ($includeNav && ($navPath === '' || $navPath === $packagePath))) {
            return null;
        }

        $resources = $this->alternateRootfileResources($entry, $packageDir, $documentMeta);
        if (!$this->isEpub2($documentMeta)) {
            foreach ($this->generatedMediaOverlayResources($documentMeta, $packageDir, $resources) as $path => $bytes) {
                $resources[$path] = $bytes;
            }
        }
        $chapters = $this->chapterDocumentsForSplitLevel($document, $chapterPath, $packageDir, $this->alternateRootfileSplitLevel($entry));
        $navDir = $navPath === '' ? '' : $this->dirname($navPath);
        $navHref = $navPath === '' ? '' : $this->relativePath($packageDir, $navPath);
        $ncxId = $this->ncxManifestId($documentMeta);
        $ncxPath = $ncxId === '' ? '' : $this->safePackagePath($this->ncxPath($documentMeta, $packageDir));
        $chapterPaths = array_fill_keys(array_map(static fn (array $chapter): string => $chapter['path'], $chapters), true);
        if ($ncxPath === '' || $ncxPath === $packagePath || ($navPath !== '' && $ncxPath === $navPath) || isset($chapterPaths[$ncxPath]) || isset($resources[$ncxPath])) {
            $ncxId = '';
            $ncxPath = '';
        }
        $ncxDir = $ncxPath === '' ? '' : $this->dirname($ncxPath);
        $ncxHref = $ncxPath === '' ? '' : $this->relativePath($packageDir, $ncxPath);
        $payloads = [];
        foreach ($chapters as $index => $chapter) {
            $payloads[$chapter['path']] = $this->chapterXhtml($chapter['document'], $resources, $packageDir, $this->dirname($chapter['path']), $index);
        }
        foreach ($resources as $path => $bytes) {
            if ($path !== $packagePath && ($navPath === '' || $path !== $navPath) && $path !== $ncxPath && !isset($payloads[$path])) {
                $payloads[$path] = $bytes;
            }
        }
        if ($navPath !== '') {
            $payloads[$navPath] = $this->navXhtml($document, $chapters, $navDir, $packageDir, $resources, $navPath);
        }
        if ($ncxPath !== '') {
            $payloads[$ncxPath] = $this->ncxXml($document, $chapters, $ncxDir, $packageDir, $resources);
        }

        return [
            'payloadBytes' => $this->packageOpf($document, $chapters, $navHref, $resources, $packageDir, $packagePath, $ncxId, $ncxHref),
            'packagePayloads' => $payloads,
        ];
    }

    private function withMetadataOptionPrecedence(callable $callback): mixed
    {
        $previous = $this->preferMetadataOverOptions;
        $this->preferMetadataOverOptions = true;
        try {
            return $callback();
        } finally {
            $this->preferMetadataOverOptions = $previous;
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function alternateRootfileDocument(array $entry, AstNode $primaryDocument): ?AstNode
    {
        $document = $entry['document'] ?? $entry['ast'] ?? null;
        if ($document instanceof AstNode) {
            if ($document->type === 'document') {
                return new AstNode('document', ['meta' => $this->alternateRootfileMeta($entry, $this->metadata($document))], $document->children);
            }

            return new AstNode('document', ['meta' => $this->alternateRootfileMeta($entry, [])], [$document]);
        }

        foreach (['children', 'bodyBlocks', 'blocks'] as $key) {
            $children = $entry[$key] ?? null;
            if (is_array($children) && $this->arrayIsAstNodes($children)) {
                return new AstNode('document', ['meta' => $this->alternateRootfileMeta($entry, [])], array_values($children));
            }
            $children = $this->astNodesFromSerializedList($children);
            if ($children !== []) {
                return new AstNode('document', ['meta' => $this->alternateRootfileMeta($entry, [])], $children);
            }
        }

        $bodyAst = $this->astNodeFromSerializedData($entry['epubBodyAst'] ?? null);
        if ($bodyAst instanceof AstNode) {
            if ($bodyAst->type === 'document') {
                return new AstNode(
                    'document',
                    ['meta' => $this->alternateRootfileMeta($entry, $this->metadata($bodyAst))],
                    $bodyAst->children
                );
            }

            return new AstNode('document', ['meta' => $this->alternateRootfileMeta($entry, [])], [$bodyAst]);
        }

        $bodyBlocks = $this->astNodesFromSerializedList($entry['epubBodyBlocks'] ?? null);
        if ($bodyBlocks !== []) {
            return new AstNode('document', ['meta' => $this->alternateRootfileMeta($entry, [])], $bodyBlocks);
        }

        if ($this->entryBool($entry, 'usePrimaryDocument')) {
            return new AstNode(
                'document',
                ['meta' => $this->alternateRootfileMeta($entry, $this->alternateRootfilePrimaryBodyBaseMeta($this->metadata($primaryDocument)))],
                $primaryDocument->children
            );
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function astNodesFromSerializedList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $nodes = [];
        foreach ($items as $item) {
            $node = $this->astNodeFromSerializedData($item);
            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function astNodeFromSerializedData(mixed $data): ?AstNode
    {
        if (!is_array($data)) {
            return null;
        }

        $type = $data['type'] ?? null;
        if (!is_scalar($type) || trim((string) $type) === '') {
            return null;
        }

        $attrs = $data['attrs'] ?? [];
        if (!is_array($attrs)) {
            $attrs = [];
        }

        return new AstNode(
            (string) $type,
            $attrs,
            $this->astNodesFromSerializedList($data['children'] ?? [])
        );
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function alternateRootfilePrimaryBodyBaseMeta(array $meta): array
    {
        foreach ([
            'epubAlternateRootfiles',
            'epubDublinCoreMetadata',
            'dublinCoreMetadata',
            'epubMetadataProperties',
            'epubMetaProperties',
            'metadataProperties',
            'metaProperties',
            'epubMetadataLinks',
            'epubMetaLinks',
            'metadataLinks',
            'metaLinks',
            'epubBindings',
            'bindings',
            'epubCollections',
            'collections',
            'epubGuideReferences',
            'guideReferences',
            'epubCoverImage',
            'coverImage',
            'epubPackageId',
            'packageId',
            'epubPackageUniqueIdentifierId',
            'packageUniqueIdentifierId',
            'epubPackagePrefix',
            'packagePrefix',
            'epubPackageVersion',
            'packageVersion',
            'epubPackageDirection',
            'packageDirection',
            'epubPackageLanguage',
            'packageLanguage',
            'epubModified',
            'modified',
            'packageModified',
            'epubPageProgressionDirection',
            'pageProgressionDirection',
            'epubSpineId',
            'spineId',
            'epubSpineTocId',
            'spineTocId',
            'epubSpineItemRefs',
            'spineItemRefs',
            'epubNonLinearSpineItemRefs',
            'nonLinearSpineItems',
            'epubSpineManifestIds',
            'spineManifestIds',
            'epubSpineItemIds',
            'spineItemIds',
            'epubSpineItemProperties',
            'spineItemProperties',
            'spineDirections',
            'spineLanguages',
            'epubSpineRootAttributes',
            'spineRootAttributes',
            'epubSpineBodyAttributes',
            'spineBodyAttributes',
            'epubSpineHeadTitles',
            'spineHeadTitles',
            'epubSpineHeadMetas',
            'spineHeadMetas',
            'epubSpineHeadBases',
            'spineHeadBases',
            'epubSpineHeadLinks',
            'spineHeadLinks',
            'epubSpineHeadStyles',
            'spineHeadStyles',
            'epubSpineHeadScripts',
            'spineHeadScripts',
            'epubViewport',
            'epubViewports',
            'viewports',
            'epubSpineManifestProperties',
            'spineManifestProperties',
            'epubSpineManifestAttributes',
            'spineManifestAttributes',
            'epubManifestResources',
            'manifestResources',
            'epubResources',
            'resources',
            'epubResourceMediaTypes',
            'resourceMediaTypes',
            'epubResourcePayloads',
            'resourcePayloads',
            'epubMediaDuration',
            'mediaDuration',
            'epubMediaNarrator',
            'mediaNarrator',
            'epubMediaActiveClass',
            'mediaActiveClass',
            'epubMediaPlaybackActiveClass',
            'mediaPlaybackActiveClass',
            'epubMediaOverlays',
            'mediaOverlays',
            'epubIncludeNcx',
            'includeNcx',
            'epubNcxPath',
            'ncxPath',
            'epubNcxId',
            'ncxId',
            'epubNcxUid',
            'ncxUid',
            'epubNcxDepth',
            'ncxDepth',
            'epubNcxTotalPageCount',
            'ncxTotalPageCount',
            'epubNcxMaxPageNumber',
            'ncxMaxPageNumber',
            'epubNcxMetadata',
            'ncxMetadata',
            'epubNcxHeadMetadata',
            'ncxHeadMetadata',
            'epubNcxDocTitle',
            'ncxDocTitle',
            'epubNcxDocTitleLang',
            'ncxDocTitleLang',
            'epubNcxDocAuthors',
            'ncxDocAuthors',
            'epubNcxDocAuthorRecords',
            'ncxDocAuthorRecords',
            'epubNcxPageListLabel',
            'ncxPageListLabel',
            'epubNcxPageListLabelLang',
            'ncxPageListLabelLang',
            'epubNcxNavLists',
            'ncxNavLists',
            'epubTocEntries',
            'tocEntries',
            'epubLandmarkEntries',
            'landmarkEntries',
            'epubPageListEntries',
            'pageListEntries',
            'epubAuxiliaryNavSections',
            'auxiliaryNavSections',
            'epubRenditionLayout',
            'renditionLayout',
            'epubRenditionOrientation',
            'renditionOrientation',
            'epubRenditionSpread',
            'renditionSpread',
            'epubRenditionFlow',
            'renditionFlow',
            'epubRenditionViewport',
            'renditionViewport',
            'viewport',
            'epubNavRootAttributes',
            'navRootAttributes',
            'epubNavBodyAttributes',
            'navBodyAttributes',
            'epubTocNavAttributes',
            'tocNavAttributes',
            'epubLandmarkNavAttributes',
            'landmarkNavAttributes',
            'epubPageListNavAttributes',
            'pageListNavAttributes',
            'epubTocNavTitle',
            'tocNavTitle',
            'epubLandmarkNavTitle',
            'landmarkNavTitle',
            'epubPageListNavTitle',
            'pageListNavTitle',
        ] as $key) {
            unset($meta[$key]);
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $baseMeta
     * @return array<string, mixed>
     */
    private function alternateRootfileMeta(array $entry, array $baseMeta): array
    {
        $meta = $baseMeta;
        foreach ([
            'manifestResources' => 'epubManifestResources',
            'resourcePayloads' => 'epubResourcePayloads',
            'nonLinearSpineItems' => 'epubNonLinearSpineItemRefs',
            'metadataProperties' => 'epubMetadataProperties',
            'metaProperties' => 'epubMetaProperties',
            'metadataLinks' => 'epubMetadataLinks',
            'metaLinks' => 'epubMetaLinks',
            'bindings' => 'epubBindings',
            'collections' => 'epubCollections',
            'guideReferences' => 'epubGuideReferences',
            'coverImage' => 'epubCoverImage',
            'dublinCoreMetadata' => 'epubDublinCoreMetadata',
            'packageId' => 'epubPackageId',
            'packageUniqueIdentifierId' => 'epubPackageUniqueIdentifierId',
            'packagePrefix' => 'epubPackagePrefix',
            'packageVersion' => 'epubPackageVersion',
            'packageDirection' => 'epubPackageDirection',
            'packageLanguage' => 'epubPackageLanguage',
            'modified' => 'epubModified',
            'packageModified' => 'epubModified',
            'pageProgressionDirection' => 'epubPageProgressionDirection',
            'spineId' => 'epubSpineId',
            'spineTocId' => 'epubSpineTocId',
            'spineRootAttributes' => 'epubSpineRootAttributes',
            'spineBodyAttributes' => 'epubSpineBodyAttributes',
            'spineHeadTitles' => 'epubSpineHeadTitles',
            'spineHeadMetas' => 'epubSpineHeadMetas',
            'spineHeadBases' => 'epubSpineHeadBases',
            'spineHeadLinks' => 'epubSpineHeadLinks',
            'spineHeadStyles' => 'epubSpineHeadStyles',
            'spineHeadScripts' => 'epubSpineHeadScripts',
            'spineManifestProperties' => 'epubSpineManifestProperties',
            'spineManifestAttributes' => 'epubSpineManifestAttributes',
            'mediaDuration' => 'epubMediaDuration',
            'mediaNarrator' => 'epubMediaNarrator',
            'mediaActiveClass' => 'epubMediaActiveClass',
            'mediaPlaybackActiveClass' => 'epubMediaPlaybackActiveClass',
            'mediaOverlays' => 'epubMediaOverlays',
            'includeNcx' => 'epubIncludeNcx',
            'ncxPath' => 'epubNcxPath',
            'ncxId' => 'epubNcxId',
            'ncxUid' => 'epubNcxUid',
            'ncxDepth' => 'epubNcxDepth',
            'ncxTotalPageCount' => 'epubNcxTotalPageCount',
            'ncxMaxPageNumber' => 'epubNcxMaxPageNumber',
            'ncxMetadata' => 'epubNcxMetadata',
            'ncxHeadMetadata' => 'epubNcxHeadMetadata',
            'ncxDocTitle' => 'epubNcxDocTitle',
            'ncxDocTitleLang' => 'epubNcxDocTitleLang',
            'ncxDocAuthors' => 'epubNcxDocAuthors',
            'ncxDocAuthorRecords' => 'epubNcxDocAuthorRecords',
            'ncxPageListLabel' => 'epubNcxPageListLabel',
            'ncxPageListLabelLang' => 'epubNcxPageListLabelLang',
            'ncxNavLists' => 'epubNcxNavLists',
            'tocEntries' => 'epubTocEntries',
            'landmarkEntries' => 'epubLandmarkEntries',
            'pageListEntries' => 'epubPageListEntries',
            'auxiliaryNavSections' => 'epubAuxiliaryNavSections',
            'renditionLayout' => 'epubRenditionLayout',
            'renditionOrientation' => 'epubRenditionOrientation',
            'renditionSpread' => 'epubRenditionSpread',
            'renditionFlow' => 'epubRenditionFlow',
            'renditionViewport' => 'epubRenditionViewport',
            'viewport' => 'epubViewport',
            'viewports' => 'epubViewports',
            'navRootAttributes' => 'epubNavRootAttributes',
            'navBodyAttributes' => 'epubNavBodyAttributes',
            'tocNavAttributes' => 'epubTocNavAttributes',
            'landmarkNavAttributes' => 'epubLandmarkNavAttributes',
            'pageListNavAttributes' => 'epubPageListNavAttributes',
            'tocNavTitle' => 'epubTocNavTitle',
            'landmarkNavTitle' => 'epubLandmarkNavTitle',
            'pageListNavTitle' => 'epubPageListNavTitle',
        ] as $sourceKey => $targetKey) {
            if (!array_key_exists($targetKey, $entry) && array_key_exists($sourceKey, $entry)) {
                $meta[$targetKey] = $entry[$sourceKey];
            }
        }
        if (!array_key_exists('epubSpineItemRefs', $entry) && array_key_exists('spineItemRefs', $entry) && is_array($entry['spineItemRefs'])) {
            $meta['epubSpineItemRefs'] = array_map(static function (mixed $item): mixed {
                if (is_array($item) && !array_key_exists('properties', $item)) {
                    $item['properties'] = [];
                }

                return $item;
            }, $entry['spineItemRefs']);
        }
        foreach ([
            'identifier',
            'title',
            'lang',
            'language',
            'author',
            'creator',
            'description',
            'subject',
            'publisher',
            'date',
            'rights',
            'epubSpineItemRefs',
            'nonLinearSpineItems',
            'epubNonLinearSpineItemRefs',
            'spineManifestIds',
            'epubSpineManifestIds',
            'spineItemIds',
            'epubSpineItemIds',
            'spineItemProperties',
            'epubSpineItemProperties',
            'spineDirections',
            'spineLanguages',
            'spineRootAttributes',
            'epubSpineRootAttributes',
            'spineBodyAttributes',
            'epubSpineBodyAttributes',
            'spineHeadTitles',
            'epubSpineHeadTitles',
            'spineHeadMetas',
            'epubSpineHeadMetas',
            'spineHeadBases',
            'epubSpineHeadBases',
            'spineHeadLinks',
            'epubSpineHeadLinks',
            'spineHeadStyles',
            'epubSpineHeadStyles',
            'spineHeadScripts',
            'epubSpineHeadScripts',
            'viewports',
            'epubViewports',
            'viewport',
            'epubViewport',
            'epubSpineManifestProperties',
            'epubSpineManifestAttributes',
            'epubMediaDuration',
            'epubMediaNarrator',
            'epubMediaActiveClass',
            'epubMediaPlaybackActiveClass',
            'epubMediaOverlays',
            'epubManifestResources',
            'resourceMediaTypes',
            'epubResourceMediaTypes',
            'epubResourcePayloads',
            'epubMetadataProperties',
            'epubMetaProperties',
            'epubMetadataLinks',
            'epubMetaLinks',
            'epubBindings',
            'epubCollections',
            'epubGuideReferences',
            'epubCoverImage',
            'epubDublinCoreMetadata',
            'epubPackageId',
            'epubPackageUniqueIdentifierId',
            'epubPackagePrefix',
            'epubPackageVersion',
            'epubPackageDirection',
            'epubPackageLanguage',
            'modified',
            'epubModified',
            'epubPageProgressionDirection',
            'epubSpineId',
            'epubSpineTocId',
            'epubIncludeNcx',
            'epubNcxPath',
            'epubNcxId',
            'epubNcxUid',
            'epubNcxDepth',
            'epubNcxTotalPageCount',
            'epubNcxMaxPageNumber',
            'epubNcxMetadata',
            'epubNcxHeadMetadata',
            'epubNcxDocTitle',
            'epubNcxDocTitleLang',
            'epubNcxDocAuthors',
            'epubNcxDocAuthorRecords',
            'epubNcxPageListLabel',
            'epubNcxPageListLabelLang',
            'epubNcxNavLists',
            'epubTocEntries',
            'epubLandmarkEntries',
            'epubPageListEntries',
            'epubAuxiliaryNavSections',
            'epubNavRootAttributes',
            'epubNavBodyAttributes',
            'epubTocNavAttributes',
            'epubLandmarkNavAttributes',
            'epubPageListNavAttributes',
            'epubTocNavTitle',
            'epubLandmarkNavTitle',
            'epubPageListNavTitle',
            'renditionLayout',
            'renditionOrientation',
            'renditionSpread',
            'renditionFlow',
            'renditionViewport',
            'viewport',
        ] as $key) {
            if (array_key_exists($key, $entry)) {
                $meta[$key] = $entry[$key];
            }
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    private function alternateRootfileResources(array $entry, string $packageDir, array $meta = []): array
    {
        $resources = [];
        foreach ([$entry['resources'] ?? [], $entry['epubResources'] ?? []] as $source) {
            if (!is_array($source)) {
                continue;
            }
            foreach ($source as $path => $bytes) {
                if (!is_string($path) || !is_string($bytes)) {
                    continue;
                }
                $path = $this->safePackagePath($path);
                if ($path !== '' && !$this->isOcfSidecarPath($path)) {
                    $resources[$path] = $bytes;
                }
            }
        }

        $payloads = $entry['resourcePayloads'] ?? $entry['epubResourcePayloads'] ?? [];
        if (is_array($payloads)) {
            foreach ($payloads as $path => $payload) {
                if (!is_array($payload)) {
                    continue;
                }
                $path = is_string($path) ? $path : $this->entryFirstString($payload, ['path', 'href']);
                $path = $this->safePackagePath($path);
                $data = $payload['data'] ?? null;
                $encoding = strtolower((string) ($payload['encoding'] ?? ''));
                if ($path === '' || !is_string($data) || $encoding !== 'base64') {
                    continue;
                }
                $bytes = base64_decode($data, true);
                if (is_string($bytes) && !$this->isOcfSidecarPath($path)) {
                    $resources[$path] = $bytes;
                }
            }
        }

        return $this->rewriteCssResourcePayloads($resources, $packageDir, $this->resourceMediaTypes($meta));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function containerRootfilePayloadBytes(array $payload): ?string
    {
        $bytes = $payload['payloadBytes'] ?? null;
        if (is_string($bytes)) {
            return $bytes;
        }

        foreach (['xml', 'contents', 'content'] as $key) {
            $contents = $payload[$key] ?? null;
            if (is_string($contents)) {
                return $contents;
            }
        }

        $data = $payload['data'] ?? null;
        $encoding = strtolower((string) ($payload['encoding'] ?? ''));
        if (!is_string($data) || $encoding !== 'base64') {
            return null;
        }

        $decoded = base64_decode($data, true);
        return is_string($decoded) ? $decoded : null;
    }

    private function containerRootfileLooksLikeOpf(string $path, string $mediaType): bool
    {
        $baseMediaType = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return $baseMediaType === 'application/oebps-package+xml' || str_ends_with(strtolower($path), '.opf');
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function containerRootfileMetadataForPath(array $meta, string $path): array
    {
        $rootfiles = $meta['epubContainerRootfiles'] ?? [];
        if (!is_array($rootfiles)) {
            return [];
        }

        foreach ($rootfiles as $rootfile) {
            if (!is_array($rootfile)) {
                continue;
            }
            $candidate = $this->safePackagePath((string) ($rootfile['path'] ?? $rootfile['fullPath'] ?? ''));
            if ($candidate === $path) {
                return $rootfile;
            }
        }

        return [];
    }

    private function isOcfSidecarPath(string $path): bool
    {
        return in_array($path, [
            'META-INF/encryption.xml',
            'META-INF/metadata.xml',
            'META-INF/rights.xml',
            'META-INF/signatures.xml',
        ], true);
    }

    private function isOcfSidecarPayloadPath(string $path): bool
    {
        return $this->isOcfSidecarPath($path)
            || (str_starts_with($path, 'META-INF/') && $path !== 'META-INF/container.xml');
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function metadataResourceMediaTypes(array $meta): array
    {
        $mediaTypes = [];
        foreach ([$meta['epubManifestResources'] ?? [], $meta['epubResourcePayloads'] ?? []] as $resources) {
            if (!is_array($resources)) {
                continue;
            }
            foreach ($resources as $path => $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $mediaType = trim((string) ($resource['mediaType'] ?? ''));
                $resourcePath = is_string($path) ? $path : (string) ($resource['path'] ?? '');
                $resourcePath = $this->safePackagePath($resourcePath);
                if ($resourcePath === '' || $mediaType === '') {
                    continue;
                }
                $mediaTypes[$resourcePath] = $mediaType;
            }
        }

        return $mediaTypes;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, list<string>>
     */
    private function resourcePropertiesByPath(array $meta): array
    {
        $properties = [];
        foreach ([$meta['epubManifestResources'] ?? [], $meta['epubResourcePayloads'] ?? []] as $resourceList) {
            if (!is_array($resourceList)) {
                continue;
            }
            foreach ($resourceList as $path => $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $resourcePath = is_string($path) ? $path : (string) ($resource['path'] ?? '');
                $resourcePath = $this->safePackagePath($resourcePath);
                if ($resourcePath === '') {
                    continue;
                }
                $tokens = $resource['properties'] ?? [];
                if (!is_array($tokens)) {
                    continue;
                }
                $properties[$resourcePath] = array_values(array_unique(array_filter(
                    array_map(static fn (mixed $token): string => trim((string) $token), $tokens),
                    static fn (string $token): bool => $token !== ''
                )));
            }
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, array<string, string>>
     */
    private function resourceManifestAttributesByPath(array $meta): array
    {
        $attributes = [];
        foreach ([$meta['epubManifestResources'] ?? [], $meta['epubResourcePayloads'] ?? []] as $resourceList) {
            if (!is_array($resourceList)) {
                continue;
            }
            foreach ($resourceList as $path => $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $resourcePath = is_string($path) ? $path : (string) ($resource['path'] ?? '');
                $resourcePath = $this->safePackagePath($resourcePath);
                if ($resourcePath === '') {
                    continue;
                }
                foreach ([
                    'fallback' => 'fallback',
                    'fallbackStyle' => 'fallback-style',
                    'fallback-style' => 'fallback-style',
                    'mediaOverlay' => 'media-overlay',
                    'media-overlay' => 'media-overlay',
                ] as $key => $attribute) {
                    $value = $this->validManifestId($this->entryString($resource, $key));
                    if ($value !== '') {
                        $attributes[$resourcePath][$attribute] = $value;
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function manifestItemAttributes(array $attributes): string
    {
        $xml = '';
        foreach (['fallback', 'fallback-style', 'media-overlay'] as $attribute) {
            $value = $attributes[$attribute] ?? '';
            if ($value !== '') {
                $xml .= ' ' . $attribute . '="' . $this->esc($value) . '"';
            }
        }

        return $xml;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestItems
     * @return array<string, array<string, string>>
     */
    private function sanitizedManifestItemAttributesById(array $manifestItems, bool $includeMediaOverlay = true, bool $includeFallbackStyle = true): array
    {
        $attributesById = [];
        foreach ($manifestItems as $id => $item) {
            if (!is_array($item)) {
                continue;
            }

            $attributes = [];
            $fallback = $this->validManifestId((string) ($item['fallback'] ?? ''));
            if (
                $fallback !== ''
                && isset($manifestItems[$fallback])
                && !$this->manifestFallbackWouldCycle($id, $fallback, $manifestItems)
                && $this->manifestFallbackHasRequiredCoreMediaTarget($id, $fallback, $manifestItems)
            ) {
                $attributes['fallback'] = $fallback;
            }

            if ($includeFallbackStyle) {
                $fallbackStyle = $this->validManifestId((string) ($item['fallback-style'] ?? ''));
                if (
                    $fallbackStyle !== ''
                    && isset($manifestItems[$fallbackStyle])
                    && is_array($manifestItems[$fallbackStyle])
                    && $this->mediaTypeMatches((string) ($manifestItems[$fallbackStyle]['media-type'] ?? ''), 'text/css')
                ) {
                    $attributes['fallback-style'] = $fallbackStyle;
                }
            }

            if ($includeMediaOverlay) {
                $mediaOverlay = $this->validManifestId((string) ($item['media-overlay'] ?? ''));
                if (
                    $mediaOverlay !== ''
                    && isset($manifestItems[$mediaOverlay])
                    && is_array($manifestItems[$mediaOverlay])
                    && $this->isEpubContentDocumentMediaType((string) ($item['media-type'] ?? ''))
                    && $this->mediaTypeMatches((string) ($manifestItems[$mediaOverlay]['media-type'] ?? ''), 'application/smil+xml')
                ) {
                    $attributes['media-overlay'] = $mediaOverlay;
                }
            }

            if ($attributes !== []) {
                $attributesById[$id] = $attributes;
            }
        }

        return $attributesById;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestItems
     */
    private function manifestFallbackWouldCycle(string $id, string $fallback, array $manifestItems): bool
    {
        $seen = [$id => true];
        $current = $fallback;
        while ($current !== '') {
            if (isset($seen[$current])) {
                return true;
            }
            if (!isset($manifestItems[$current]) || !is_array($manifestItems[$current])) {
                return false;
            }

            $seen[$current] = true;
            $current = $this->validManifestId((string) ($manifestItems[$current]['fallback'] ?? ''));
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestItems
     */
    private function manifestFallbackHasRequiredCoreMediaTarget(string $id, string $fallback, array $manifestItems): bool
    {
        $item = $manifestItems[$id] ?? null;
        if (!is_array($item)) {
            return false;
        }

        $mediaType = (string) ($item['media-type'] ?? '');
        if (!$this->validMediaType($mediaType) || $this->isEpubCoreMediaType($mediaType)) {
            return true;
        }

        return $this->manifestFallbackChainReachesCoreMediaType($fallback, $manifestItems);
    }

    /**
     * @param array<string, array<string, mixed>> $manifestItems
     */
    private function manifestFallbackChainReachesCoreMediaType(string $fallback, array $manifestItems): bool
    {
        $seen = [];
        $current = $fallback;
        while ($current !== '') {
            if (isset($seen[$current]) || !isset($manifestItems[$current]) || !is_array($manifestItems[$current])) {
                return false;
            }

            $seen[$current] = true;
            $item = $manifestItems[$current];
            $mediaType = (string) ($item['media-type'] ?? '');
            if (!$this->validMediaType($mediaType)) {
                return false;
            }
            if ($this->isEpubCoreMediaType($mediaType)) {
                return true;
            }

            $current = $this->validManifestId((string) ($item['fallback'] ?? ''));
        }

        return false;
    }

    private function isEpubCoreMediaType(string $mediaType): bool
    {
        return $this->bindingMediaTypeIsEpubCore($mediaType);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function manifestIdOverridesByPath(array $meta, string $packageDir = ''): array
    {
        $overrides = [];
        foreach ([$meta['epubManifestResources'] ?? [], $meta['epubResourcePayloads'] ?? []] as $resourceList) {
            if (!is_array($resourceList)) {
                continue;
            }
            foreach ($resourceList as $path => $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $id = $this->validManifestId($this->entryString($resource, 'id'));
                $resourcePath = is_string($path) ? $path : (string) ($resource['path'] ?? '');
                $resourcePath = $this->safePackagePath($resourcePath);
                if ($id !== '' && $resourcePath !== '') {
                    $overrides[$resourcePath] = $id;
                }
            }
        }

        $mediaOverlays = $this->optionOrMetaValue($meta, ['mediaOverlays'], ['epubMediaOverlays'], [], false);
        if (!is_array($mediaOverlays)) {
            return $overrides;
        }
        foreach ($mediaOverlays as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = $this->validManifestId($this->entryString($entry, 'overlayId'));
            $path = $this->entryPackagePath($entry, 'overlayPath', '');
            if ($path === '') {
                $path = $this->entryPackagePath($entry, 'overlayHref', $packageDir);
            }
            if ($id !== '' && $path !== '') {
                $overrides[$path] = $id;
            }
        }

        return $overrides;
    }

    private function mediaType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'aac' => 'audio/aac',
            'css' => 'text/css',
            'flac' => 'audio/flac',
            'gif' => 'image/gif',
            'htm', 'html', 'xhtml' => 'application/xhtml+xml',
            'jpeg', 'jpg' => 'image/jpeg',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'm4a' => 'audio/mp4',
            'mp3', 'mpga' => 'audio/mpeg',
            'mp4', 'm4v' => 'video/mp4',
            'ncx' => 'application/x-dtbncx+xml',
            'oga', 'ogg', 'opus' => 'audio/ogg',
            'ogv' => 'video/ogg',
            'otf' => 'font/otf',
            'png' => 'image/png',
            'smil' => 'application/smil+xml',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            'vtt' => 'text/vtt',
            'wav' => 'audio/wav',
            'webm' => 'video/webm',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
        };
    }

    private function resourceProperties(string $path, string $mediaType, string $bytes = ''): string
    {
        $properties = [];
        $isXhtml = $this->mediaTypeMatches($mediaType, 'application/xhtml+xml');
        $isSvg = $this->mediaTypeMatches($mediaType, 'image/svg+xml') || str_ends_with(strtolower($path), '.svg');
        $isCss = $this->mediaTypeMatches($mediaType, 'text/css');
        $isSmil = $this->mediaTypeMatches($mediaType, 'application/smil+xml');

        if (($isXhtml || $isSvg) && $bytes !== '' && $this->resourceContainsMathMl($bytes)) {
            $properties[] = 'mathml';
        }
        if ($isSvg || ($isXhtml && $bytes !== '' && $this->resourceContainsInlineSvg($bytes))) {
            $properties[] = 'svg';
        }
        if (($isXhtml || $isSvg) && $bytes !== '' && $this->resourceContainsScriptedContent($bytes)) {
            $properties[] = 'scripted';
        }
        if ($isXhtml && $bytes !== '' && $this->resourceContainsEpubSwitch($bytes)) {
            $properties[] = 'switch';
        }
        if (($isXhtml || $isSvg || $isCss || $isSmil) && $bytes !== '' && $this->resourceContainsRemoteReference($bytes)) {
            $properties[] = 'remote-resources';
        }

        return implode(' ', array_values(array_unique($properties)));
    }

    private function resourceContainsMathMl(string $bytes): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?math(?:\s|>|\/)/i', $bytes) === 1;
    }

    private function resourceContainsInlineSvg(string $bytes): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?svg(?:\s|>|\/)/i', $bytes) === 1;
    }

    private function resourceContainsScriptedContent(string $bytes): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?script(?:\s|>|\/)/i', $bytes) === 1
            || preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?form(?:\s|>|\/)/i', $bytes) === 1
            || preg_match('/\son[A-Za-z][A-Za-z0-9_.:-]*\s*=/i', $bytes) === 1
            || preg_match('/(?<![\w-])(?:href|src|action|formaction)\s*=\s*(["\'])\s*javascript:/i', $bytes) === 1;
    }

    private function resourceContainsEpubSwitch(string $bytes): bool
    {
        return stripos($bytes, 'switch') !== false
            && preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?switch(?:\s|>|\/)/i', $bytes) === 1;
    }

    private function resourceContainsRemoteReference(string $bytes): bool
    {
        if ($this->resourceContainsRemoteBaseRelativeResourceReference($bytes)) {
            return true;
        }

        if (preg_match_all('/(?<![\w-])(?:src|poster|data|action|formaction|background|textref)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?<![\w-])(?:srcset|imagesrcset)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->srcsetContainsRemoteResource(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?<![\w-])style\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->cssContainsRemoteResource(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return $this->resourceContainsRemoteSvgHrefReference($bytes)
            || $this->resourceContainsRemoteHtmlLinkHrefReference($bytes)
            || $this->cssContainsRemoteResource($bytes);
    }

    private function resourceContainsRemoteBaseRelativeResourceReference(string $bytes): bool
    {
        if (!$this->resourceContainsRemoteHtmlBaseHrefReference($bytes)) {
            return false;
        }

        if (preg_match_all('/(?<![\w-])(?:src|poster|data|action|formaction|background|textref)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?<![\w-])(?:srcset|imagesrcset)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                foreach ($this->srcsetCandidates(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $candidate) {
                    if ($this->isPackageRelativeResourceUrl($candidate['url'])) {
                        return true;
                    }
                }
            }
        }

        if (preg_match_all('/(?<![\w-])style\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->cssContainsPackageRelativeResource(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return $this->resourceContainsPackageRelativeSvgHrefReference($bytes)
            || $this->resourceContainsPackageRelativeHtmlLinkHrefReference($bytes)
            || $this->cssContainsPackageRelativeResource($bytes);
    }

    private function resourceContainsRemoteHtmlBaseHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?base\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsRemoteHtmlLinkHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?link\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsPackageRelativeHtmlLinkHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?link\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsRemoteSvgHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?(?:altGlyph|clipPath|color-profile|cursor|feImage|filter|font-face-uri|glyphRef|image|linearGradient|marker|mask|mpath|pattern|radialGradient|script|textPath|tref|use)\b[^>]*\b(?:href|xlink:href)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsPackageRelativeSvgHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?(?:altGlyph|clipPath|color-profile|cursor|feImage|filter|font-face-uri|glyphRef|image|linearGradient|marker|mask|mpath|pattern|radialGradient|script|textPath|tref|use)\b[^>]*\b(?:href|xlink:href)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function cssContainsPackageRelativeResource(string $css): bool
    {
        if (preg_match_all('/url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $url = trim((string) ($match[2] ?? ($match[3] ?? '')));
                if ($url !== '' && $this->isPackageRelativeResourceUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/@import\s+(["\'])([^"\']+)\1/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $url = trim((string) ($match[2] ?? ''));
                if ($url !== '' && $this->isPackageRelativeResourceUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $additional
     */
    private function mergeProperties(string $properties, array $additional): string
    {
        $tokens = preg_split('/\s+/', trim($properties)) ?: [];
        foreach ($additional as $token) {
            if ($token !== '' && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return implode(' ', array_values(array_filter($tokens, static fn (string $token): bool => $token !== '')));
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, mixed> $meta
     */
    private function coverImagePath(array $resources, array $meta): string
    {
        $candidates = $this->preferMetadataOverOptions
            ? [
                $meta['epubCoverImage'] ?? null,
                $meta['coverImage'] ?? null,
            ]
            : [
                $this->options['coverImage'] ?? null,
                $meta['epubCoverImage'] ?? null,
                $meta['coverImage'] ?? null,
            ];

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $path = $this->safePackagePath((string) $candidate);
            if ($path !== '' && isset($resources[$path])) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @param array<string, true> $seen
     */
    private function manifestIdForResource(string $path, array &$seen, array $overrides): string
    {
        $id = $this->validManifestId($overrides[$path] ?? '');
        if ($id !== '' && !isset($seen[$id])) {
            $seen[$id] = true;

            return $id;
        }

        return $this->manifestId($path, $seen);
    }

    /**
     * @param array<string, true> $seen
     */
    private function manifestId(string $path, array &$seen): string
    {
        $base = preg_replace('/[^A-Za-z0-9_.-]+/', '-', pathinfo($path, PATHINFO_FILENAME)) ?? 'resource';
        $base = trim($base, '.-');
        if ($base === '') {
            $base = 'resource';
        }
        if (!preg_match('/^[A-Za-z_]/', $base)) {
            $base = 'r-' . $base;
        }

        $id = $base;
        $index = 2;
        while (isset($seen[$id])) {
            $id = $base . '-' . $index;
            $index++;
        }
        $seen[$id] = true;

        return $id;
    }

    private function validManifestId(string $id): string
    {
        $id = trim($id);

        return $id !== '' && $this->validXmlId($id) ? $id : '';
    }

    private function safePackagePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function dirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));
        return $dir === '.' ? '' : $dir;
    }

    private function relativePath(string $fromDir, string $toPath): string
    {
        $from = $fromDir === '' ? [] : explode('/', $this->safePackagePath($fromDir));
        $to = explode('/', $this->safePackagePath($toPath));
        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)) . implode('/', $to);
    }

    private function slug(string $text): string
    {
        $slug = strtolower(preg_replace('/[^\pL\pN]+/u', '-', $text) ?? $text);

        return trim($slug, '-');
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
