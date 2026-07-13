<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocMediaExtractor
{
    private const MAX_PACKAGE_MEDIA_BYTES = 33554432;
    private const MAX_PDF_SCAN_BYTES = 67108864;
    private const MAX_PDF_IMAGE_BYTES = 33554432;
    private const MAX_PDF_RASTER_IMAGE_BYTES = 16777216;
    private const MAX_PDF_RASTER_IMAGE_PIXELS = 48000000;
    private const MAX_PDF_IMAGES = 96;

    /**
     * @param array<string, mixed> $options
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, extractionPathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, linkedMimeGroup?:string, linkedMimeGroupSize?:int}>,
     *     diagnostics:list<string>
     * }
     */
    public function extract(AstNode $document, string $bytes, string $format, array $options, ?EpubArchive $epubArchive = null): array
    {
        $destination = (string) ($options['destination'] ?? $options['extractMedia'] ?? $options['extract-media'] ?? 'media');
        $sourcePath = isset($options['sourcePath']) && is_string($options['sourcePath']) ? $options['sourcePath'] : null;
        $imageMode = $this->normalizeImageMode($options['imageMode'] ?? $options['image-mode'] ?? $options['pdfImageMode'] ?? $options['pdf-image-mode'] ?? 'all');
        $pdfRasterImages = $this->normalizePdfRasterImages($options['pdfRasterImages'] ?? $options['pdf-raster-images'] ?? []);
        $format = PandocConverter::canonicalInputFormat($format);
        $bag = new MediaBag();
        $diagnostics = ['extract-media-image-mode:' . $imageMode];

        if ($imageMode === 'none') {
            $document = $this->documentWithoutImages($document);
        }

        $imageSources = $this->imageSources($document);
        if ($imageSources !== []) {
            $this->loadDataUriImages($bag, $imageSources, $diagnostics);
            $this->loadPackageImages($bag, $imageSources, $bytes, $format, $diagnostics, $epubArchive);
            if ($sourcePath !== null) {
                $this->loadLocalImages($bag, $imageSources, $sourcePath, $diagnostics);
            }
        }

        $pdfImagePlacements = [];
        if ($format === 'pdf' && $imageMode !== 'none') {
            $pdfImagePlacements = $this->loadPdfImages($bag, $bytes, $diagnostics, $imageMode, $pdfRasterImages);
            if ($pdfImagePlacements !== []) {
                $document = $this->documentWithPdfImageBlocks($document, $pdfImagePlacements);
            }
        }

        $extracted = $bag->extractMedia($document, $destination);

        return [
            'document' => $extracted['document'],
            'entries' => $extracted['entries'],
            'diagnostics' => array_values(array_unique(array_merge($diagnostics, $extracted['diagnostics']))),
        ];
    }

    /**
     * Use a native file-backed EPUB archive when available so package media
     * never requires retaining the whole source ZIP as a PHP string.
     *
     * @param array<string, mixed> $options
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, extractionPathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, linkedMimeGroup?:string, linkedMimeGroupSize?:int}>,
     *     diagnostics:list<string>
     * }
     */
    public function extractFile(AstNode $document, string $path, string $format, array $options): array
    {
        if (PandocConverter::canonicalInputFormat($format) === 'epub') {
            try {
                $epubArchive = EpubArchiveFactory::fromFile($path);
            } catch (\Throwable) {
                // Preserve the byte-backed fallback for ZIP variants the
                // native extension cannot open.
                $epubArchive = null;
            }
            if ($epubArchive !== null) {
                return $this->extract($document, '', $format, $options, $epubArchive);
            }
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->extract($document, $bytes, $format, $options);
    }

    private function normalizeImageMode(mixed $mode): string
    {
        if (is_bool($mode)) {
            return $mode ? 'all' : 'none';
        }

        $mode = strtolower(str_replace(['_', ' '], '-', trim((string) $mode)));

        return match ($mode) {
            'none', 'no', 'off', 'false', '0', 'no-images', 'without-images' => 'none',
            'important', 'auto', 'selected', 'significant' => 'important',
            default => 'all',
        };
    }

    /**
     * Accept browser or host supplied PNG rasters for PDF image XObjects.
     * A decoder remains outside this class, but the PDF object and intrinsic
     * dimensions are checked again before a raster can become document media.
     *
     * @return array<string,array{contents:string,mimeType:string,width:int,height:int}>
     */
    private function normalizePdfRasterImages(mixed $images): array
    {
        if (!is_array($images)) {
            return [];
        }

        $normalized = [];
        foreach ($images as $key => $image) {
            if (!is_array($image)) {
                continue;
            }
            $object = (string) ($image['object'] ?? $key);
            if (preg_match('/^\d+$/', $object) !== 1) {
                continue;
            }
            $contents = $image['contents'] ?? $image['bytes'] ?? '';
            $mimeType = strtolower(trim((string) ($image['mimeType'] ?? $image['mime'] ?? '')));
            $width = $image['width'] ?? null;
            $height = $image['height'] ?? null;
            if (!is_string($contents) || $contents === '' || strlen($contents) > self::MAX_PDF_RASTER_IMAGE_BYTES
                || $mimeType !== 'image/png' || !is_numeric($width) || !is_numeric($height)) {
                continue;
            }
            $width = (int) $width;
            $height = (int) $height;
            if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PDF_RASTER_IMAGE_PIXELS
                || !$this->pngHasDimensions($contents, $width, $height)) {
                continue;
            }

            $normalized[$object] = [
                'contents' => $contents,
                'mimeType' => $mimeType,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $normalized;
    }

    private function documentWithoutImages(AstNode $document): AstNode
    {
        return $this->filterImages($document) ?? new AstNode('document', $document->attrs, []);
    }

    private function filterImages(AstNode $node): ?AstNode
    {
        if ($node->type === 'image') {
            return null;
        }

        $children = [];
        $removedChild = false;
        foreach ($node->children as $child) {
            $filtered = $this->filterImages($child);
            if ($filtered !== null) {
                $children[] = $filtered;
            } else {
                $removedChild = true;
            }
        }

        if ($removedChild && $children === [] && in_array($node->type, ['paragraph', 'plain', 'list_item'], true) && trim((string) $node->attr('text', '')) === '') {
            return null;
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    /**
     * @return list<string>
     */
    private function imageSources(AstNode $document): array
    {
        $sources = [];
        $this->walk($document, static function (AstNode $node) use (&$sources): void {
            if ($node->type !== 'image') {
                return;
            }
            $source = (string) $node->attr('url', $node->attr('src', ''));
            if ($source !== '') {
                $sources[$source] = true;
            }
        });

        return array_keys($sources);
    }

    private function walk(AstNode $node, callable $callback): void
    {
        $callback($node);
        foreach ($node->children as $child) {
            $this->walk($child, $callback);
        }
    }

    /**
     * @param list<string> $sources
     * @param list<string> $diagnostics
     */
    private function loadDataUriImages(MediaBag $bag, array $sources, array &$diagnostics): void
    {
        foreach ($sources as $source) {
            if (!str_starts_with($source, 'data:') || $bag->has($source)) {
                continue;
            }
            try {
                $bag->insertDataUri($source);
                $diagnostics[] = 'extract-media-data-uri-loaded';
            } catch (\InvalidArgumentException) {
                $diagnostics[] = 'extract-media-data-uri-invalid';
            }
        }
    }

    /**
     * @param list<string> $sources
     * @param list<string> $diagnostics
     */
    private function loadPackageImages(
        MediaBag $bag,
        array $sources,
        string $bytes,
        string $format,
        array &$diagnostics,
        ?EpubArchive $epubArchive = null
    ): void
    {
        if (!in_array($format, ['docx', 'epub', 'odt', 'odp', 'ods', 'pptx', 'xlsx'], true)) {
            return;
        }

        try {
            $zip = $format === 'epub' && $epubArchive !== null
                ? $epubArchive
                : ($format === 'epub'
                    ? EpubArchiveFactory::fromString($bytes)
                    : ZipPackage::fromString($bytes));
        } catch (\Throwable) {
            $diagnostics[] = 'extract-media-package-unreadable';

            return;
        }

        $names = $zip->names();
        foreach ($sources as $source) {
            if ($source === '' || str_starts_with($source, 'data:') || $bag->has($source)) {
                continue;
            }
            $entryName = $this->resolvePackageEntry($source, $format, $names);
            if ($entryName === null) {
                continue;
            }

            try {
                $contents = $zip->readBounded($entryName, self::MAX_PACKAGE_MEDIA_BYTES);
            } catch (\Throwable) {
                $diagnostics[] = 'extract-media-package-read-failed:' . $this->diagnosticToken($source);
                continue;
            }

            $bag->insertMedia($source, $this->mimeTypeFromPath($entryName), $contents);
            $diagnostics[] = 'extract-media-package-loaded:' . $this->diagnosticToken($source);
        }
    }

    /**
     * @param list<string> $names
     */
    private function resolvePackageEntry(string $source, string $format, array $names): ?string
    {
        $source = ltrim(str_replace('\\', '/', rawurldecode($source)), '/');
        if ($source === '' || str_contains($source, "\0")) {
            return null;
        }

        $nameSet = array_fill_keys($names, true);
        $candidates = [$source];
        if ($format === 'docx' && str_starts_with($source, 'media/')) {
            $candidates[] = 'word/' . $source;
        }
        if (in_array($format, ['odt', 'odp', 'ods'], true) && !str_starts_with($source, 'Pictures/')) {
            $candidates[] = 'Pictures/' . basename($source);
        }
        foreach ($candidates as $candidate) {
            if (isset($nameSet[$candidate])) {
                return $candidate;
            }
        }

        $suffixMatches = [];
        foreach ($names as $name) {
            if ($name === $source || str_ends_with($name, '/' . $source)) {
                $suffixMatches[] = $name;
            }
        }
        if (count($suffixMatches) === 1) {
            return $suffixMatches[0];
        }

        $basename = basename($source);
        if ($basename === $source || $basename === '') {
            return null;
        }
        $basenameMatches = [];
        foreach ($names as $name) {
            if (basename($name) === $basename && $this->looksLikeImagePath($name)) {
                $basenameMatches[] = $name;
            }
        }

        return count($basenameMatches) === 1 ? $basenameMatches[0] : null;
    }

    /**
     * @param list<string> $sources
     * @param list<string> $diagnostics
     */
    private function loadLocalImages(MediaBag $bag, array $sources, string $sourcePath, array &$diagnostics): void
    {
        $baseDir = is_dir($sourcePath) ? $sourcePath : dirname($sourcePath);
        foreach ($sources as $source) {
            if ($source === '' || $bag->has($source) || str_starts_with($source, 'data:') || $this->isUri($source)) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', rawurldecode($source)), '/');
            if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
                continue;
            }
            $path = $baseDir . '/' . $relative;
            if (!is_file($path) || !$this->looksLikeImagePath($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                continue;
            }
            $bag->insertMedia($source, $this->mimeTypeFromPath($path), $contents);
            $diagnostics[] = 'extract-media-local-loaded:' . $this->diagnosticToken($source);
        }
    }

    /**
     * @param list<string> $diagnostics
     * @param array<string,array{contents:string,mimeType:string,width:int,height:int}> $rasterImages
     * @return list<array{source:string, page:int|null, object:string, mimeType:string, byteLength:int, width:int|null, height:int|null, imageMask:bool, importance:string}>
     */
    private function loadPdfImages(MediaBag $bag, string $bytes, array &$diagnostics, string $imageMode, array $rasterImages = []): array
    {
        if (strlen($bytes) > self::MAX_PDF_SCAN_BYTES) {
            $diagnostics[] = 'extract-media-pdf-scan-skipped:too-large';

            return [];
        }

        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $bytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $placements = [];
        $loaded = 0;
        foreach ($matches as $match) {
            if ($loaded >= self::MAX_PDF_IMAGES) {
                $diagnostics[] = 'extract-media-pdf-image-limit';
                break;
            }
            $objectNumber = (string) $match[1];
            $body = (string) $match[3];
            if (!preg_match('#/Subtype\s*/Image\b#', $body)) {
                continue;
            }

            $width = $this->pdfIntegerEntry($body, 'Width');
            $height = $this->pdfIntegerEntry($body, 'Height');
            $imageMask = $this->pdfBooleanEntry($body, 'ImageMask');

            $stream = $this->pdfStreamBytes($body);
            if ($stream === null || $stream === '' || strlen($stream) > self::MAX_PDF_IMAGE_BYTES) {
                $diagnostics[] = 'extract-media-pdf-image-skipped:stream';
                continue;
            }

            $filters = $this->pdfFilterNames($body);
            $raster = $rasterImages[$objectNumber] ?? $rasterImages[(string) (int) $objectNumber] ?? null;
            if ($raster !== null && ($raster['width'] !== $width || $raster['height'] !== $height)) {
                $diagnostics[] = 'extract-media-pdf-image-raster-skipped:dimensions:' . $objectNumber;
                $raster = null;
            }
            $importance = $this->pdfImageImportance($width, $height, $raster === null ? strlen($stream) : strlen($raster['contents']), $imageMask);
            if ($imageMode === 'important' && $importance !== 'important') {
                $diagnostics[] = 'extract-media-pdf-image-unimportant:' . $objectNumber . ':' . $importance;
                continue;
            }

            if ($raster !== null) {
                $mimeType = $raster['mimeType'];
                $extension = '.png';
                $imageBytes = $raster['contents'];
                $diagnostics[] = 'extract-media-pdf-image-raster-loaded:' . $objectNumber . ':' . $importance;
            } else {
                $mimeType = $this->pdfEmbeddableMimeType($filters);
                $extension = $mimeType === 'image/jpeg' ? '.jpg' : '.jp2';
                $imageBytes = $stream;
                if ($mimeType === null) {
                    $flateImage = $this->pdfFlateImagePng($body, $filters, $stream, $width, $height, $imageMask);
                    if ($flateImage === null) {
                        $diagnostics[] = 'extract-media-pdf-image-skipped:' . ($filters === [] ? 'unfiltered' : implode('+', $filters));
                        continue;
                    }
                    [$mimeType, $extension, $imageBytes] = $flateImage;
                }
            }

            $source = 'pdf/image-' . $objectNumber . $extension;
            $bag->insertMedia($source, $mimeType, $imageBytes);
            $placements[] = [
                'source' => $source,
                'page' => null,
                'object' => $objectNumber,
                'mimeType' => $mimeType,
                'byteLength' => strlen($imageBytes),
                'width' => $width,
                'height' => $height,
                'imageMask' => $imageMask,
                'importance' => $importance,
            ];
            $diagnostics[] = 'extract-media-pdf-image-loaded:' . $objectNumber . ':' . $importance;
            $loaded++;
        }

        return $placements;
    }

    /**
     * @param list<array{source:string, page:int|null, object:string, mimeType:string, byteLength:int, width:int|null, height:int|null, imageMask:bool, importance:string}> $placements
     */
    private function documentWithPdfImageBlocks(AstNode $document, array $placements): AstNode
    {
        $imageBlocks = [];
        foreach ($placements as $placement) {
            $attributes = [
                'data-pandoc-pdf-image-object' => $placement['object'],
                'data-pandoc-pdf-image-type' => $placement['mimeType'],
                'data-pandoc-pdf-image-bytes' => (string) $placement['byteLength'],
                'data-pandoc-pdf-image-importance' => $placement['importance'],
            ];
            if ($placement['width'] !== null) {
                $attributes['data-pandoc-pdf-image-width'] = (string) $placement['width'];
            }
            if ($placement['height'] !== null) {
                $attributes['data-pandoc-pdf-image-height'] = (string) $placement['height'];
            }
            if ($placement['page'] !== null) {
                $attributes['data-pandoc-pdf-page'] = (string) $placement['page'];
            }
            $imageBlocks[] = new AstNode('paragraph', ['classes' => ['pandoc-pdf-image-block']], [
                new AstNode('image', [
                    'url' => $placement['source'],
                    'title' => 'PDF image ' . $placement['object'],
                    'attributes' => $attributes,
                ], [
                    new AstNode('text', ['text' => 'PDF image ' . $placement['object']]),
                ]),
            ]);
        }

        if ($imageBlocks === []) {
            return $document;
        }

        $section = new AstNode('div', [
            'classes' => ['pandoc-pdf-extracted-images'],
            'attributes' => [
                'data-pandoc-pdf-image-placement' => 'separate-section',
            ],
        ], array_merge([
            new AstNode('heading', [
                'level' => 2,
                'id' => 'extracted-pdf-images',
                'classes' => ['pandoc-pdf-extracted-images-heading'],
                'text' => 'Extracted PDF images',
            ], [
                new AstNode('text', ['text' => 'Extracted PDF images']),
            ]),
            new AstNode('paragraph', [
                'classes' => ['pandoc-pdf-extracted-images-note'],
                'text' => 'These images were extracted from PDF image streams and are shown separately because exact PDF image placement is not yet reconstructed.',
            ], [
                new AstNode('text', ['text' => 'These images were extracted from PDF image streams and are shown separately because exact PDF image placement is not yet reconstructed.']),
            ]),
        ], $imageBlocks));

        return new AstNode($document->type, $document->attrs, array_merge([$section], $document->children));
    }

    private function pdfIntegerEntry(string $objectBody, string $name): ?int
    {
        if (preg_match('#/' . preg_quote($name, '#') . '\s+(\d+)\b#', $objectBody, $match) !== 1) {
            return null;
        }

        return max(0, (int) $match[1]);
    }

    private function pdfBooleanEntry(string $objectBody, string $name): bool
    {
        return preg_match('#/' . preg_quote($name, '#') . '\s+true\b#i', $objectBody) === 1;
    }

    private function pdfImageImportance(?int $width, ?int $height, int $byteLength, bool $imageMask): string
    {
        if ($imageMask) {
            return 'mask';
        }
        if ($width !== null && $height !== null && ($width < 16 || $height < 16)) {
            return 'tiny';
        }
        if ($byteLength >= 8192) {
            return 'important';
        }
        if ($width !== null && $height !== null && ($width * $height >= 10000 || ($width >= 96 && $height >= 96))) {
            return 'important';
        }

        return 'small';
    }

    /**
     * @return list<string>
     */
    private function pdfFilterNames(string $objectBody): array
    {
        if (!preg_match('#/Filter\s*(\[[^\]]*\]|/[A-Za-z0-9]+)#s', $objectBody, $match)) {
            return [];
        }
        preg_match_all('#/([A-Za-z0-9]+)#', (string) $match[1], $names);

        return $names[1] ?? [];
    }

    /**
     * @param list<string> $filters
     */
    private function pdfEmbeddableMimeType(array $filters): ?string
    {
        if ($filters === ['DCTDecode'] || $filters === ['DCT']) {
            return 'image/jpeg';
        }
        if ($filters === ['JPXDecode']) {
            return 'image/jp2';
        }

        return null;
    }

    private function pdfStreamBytes(string $objectBody): ?string
    {
        $streamOffset = strpos($objectBody, 'stream');
        if ($streamOffset === false) {
            return null;
        }
        $start = $streamOffset + strlen('stream');
        if (substr($objectBody, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (isset($objectBody[$start]) && ($objectBody[$start] === "\n" || $objectBody[$start] === "\r")) {
            $start++;
        }

        $end = strrpos($objectBody, 'endstream');
        if ($end === false || $end < $start) {
            return null;
        }
        $stream = substr($objectBody, $start, $end - $start);

        return preg_replace("/(?:\r\n|\n|\r)\z/", '', $stream) ?? $stream;
    }

    /**
     * @param list<string> $filters
     * @return array{0:string, 1:string, 2:string}|null
     */
    private function pdfFlateImagePng(string $objectBody, array $filters, string $stream, ?int $width, ?int $height, bool $imageMask): ?array
    {
        if ($filters !== ['FlateDecode'] && $filters !== ['Fl']) {
            return null;
        }
        if ($width === null || $height === null || $width <= 0 || $height <= 0 || $width * $height > 8000000) {
            return null;
        }

        $bitsPerComponent = $this->pdfIntegerEntry($objectBody, 'BitsPerComponent') ?? ($imageMask ? 1 : 8);
        $decoded = $this->pdfFlateDecode($stream);
        if ($decoded === null || $decoded === '') {
            return null;
        }

        $pixelCount = $width * $height;
        if ($bitsPerComponent === 8) {
            $decodedLength = strlen($decoded);
            if ($decodedLength === $pixelCount) {
                return ['image/png', '.png', $this->pngEncodeGrayscale8($width, $height, $decoded)];
            }
            if ($decodedLength === $pixelCount * 3) {
                return ['image/png', '.png', $this->pngEncodeRgb8($width, $height, $decoded)];
            }
            if ($decodedLength === $pixelCount * 4) {
                return ['image/png', '.png', $this->pngEncodeRgba8($width, $height, $decoded)];
            }
        }

        if ($bitsPerComponent === 1) {
            $rowBytes = (int) ceil($width / 8);
            if (strlen($decoded) !== $rowBytes * $height) {
                return null;
            }

            return ['image/png', '.png', $this->pngEncodeGrayscale8(
                $width,
                $height,
                $this->unpackOneBitImageRows($decoded, $width, $height, $rowBytes)
            )];
        }

        return null;
    }

    private function pdfFlateDecode(string $stream): ?string
    {
        $decoded = @gzuncompress($stream);
        if (is_string($decoded)) {
            return $decoded;
        }

        $decoded = @gzdecode($stream);
        if (is_string($decoded)) {
            return $decoded;
        }

        if (strlen($stream) > 2) {
            $decoded = @gzinflate(substr($stream, 2));
            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function unpackOneBitImageRows(string $bytes, int $width, int $height, int $rowBytes): string
    {
        $pixels = '';
        for ($y = 0; $y < $height; $y++) {
            $rowOffset = $y * $rowBytes;
            for ($x = 0; $x < $width; $x++) {
                $byte = ord($bytes[$rowOffset + intdiv($x, 8)]);
                $bit = ($byte >> (7 - ($x % 8))) & 1;
                $pixels .= $bit === 1 ? "\xff" : "\x00";
            }
        }

        return $pixels;
    }

    private function pngHasDimensions(string $bytes, int $width, int $height): bool
    {
        if (strlen($bytes) < 24 || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n" || substr($bytes, 12, 4) !== 'IHDR') {
            return false;
        }
        $dimensions = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

        return is_array($dimensions)
            && (int) ($dimensions['width'] ?? 0) === $width
            && (int) ($dimensions['height'] ?? 0) === $height;
    }

    private function pngEncodeGrayscale8(int $width, int $height, string $pixels): string
    {
        return $this->pngEncode($width, $height, 0, $this->pngScanlines($pixels, $height, $width));
    }

    private function pngEncodeRgb8(int $width, int $height, string $pixels): string
    {
        return $this->pngEncode($width, $height, 2, $this->pngScanlines($pixels, $height, $width * 3));
    }

    private function pngEncodeRgba8(int $width, int $height, string $pixels): string
    {
        return $this->pngEncode($width, $height, 6, $this->pngScanlines($pixels, $height, $width * 4));
    }

    private function pngScanlines(string $pixels, int $height, int $rowLength): string
    {
        $scanlines = '';
        for ($y = 0; $y < $height; $y++) {
            $scanlines .= "\x00" . substr($pixels, $y * $rowLength, $rowLength);
        }

        return $scanlines;
    }

    private function pngEncode(int $width, int $height, int $colorType, string $scanlines): string
    {
        return "\x89PNG\r\n\x1a\n"
            . $this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, $colorType, 0, 0, 0))
            . $this->pngChunk('IDAT', gzcompress($scanlines))
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function isUri(string $source): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1;
    }

    private function looksLikeImagePath(string $path): bool
    {
        return str_starts_with($this->mimeTypeFromPath($path), 'image/');
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'apng' => 'image/apng',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'jpeg', 'jpg', 'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'svg', 'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'tif', 'tiff' => 'image/tiff',
            'jp2', 'jpx' => 'image/jp2',
            default => 'application/octet-stream',
        };
    }

    private function diagnosticToken(string $source): string
    {
        $source = preg_replace('/[^A-Za-z0-9._-]+/', '-', $source) ?? $source;

        return substr($source, 0, 96);
    }
}
