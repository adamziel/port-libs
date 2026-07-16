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
    private const MAX_PDF_IMAGE_PLACEMENT_CANDIDATES = 384;
    private const MAX_PDF_IMAGE_PLACEMENT_CANDIDATES_PER_PAGE = 64;
    private const MAX_PDF_IMAGE_PLACEMENTS = 96;
    private const MAX_PDF_IMAGE_PLACEMENTS_PER_PAGE = 16;

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
            $placedPdfImages = $this->anchoredPdfImagePlacements(
                $document,
                $this->pdfImagePlacements($document, $bytes, $diagnostics),
                $diagnostics
            );
            if ($placedPdfImages !== []) {
                $pdfImagePlacements = $this->loadPdfImages(
                    $bag,
                    $bytes,
                    $diagnostics,
                    $imageMode,
                    $placedPdfImages,
                    $pdfRasterImages
                );
                if ($pdfImagePlacements !== []) {
                    $document = $this->documentWithPlacedPdfImageBlocks($document, $pdfImagePlacements);
                }
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
     * Accept browser or host supplied web-safe rasters for PDF image XObjects.
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
                || !in_array($mimeType, ['image/png', 'image/avif'], true) || !is_numeric($width) || !is_numeric($height)) {
                continue;
            }
            $width = (int) $width;
            $height = (int) $height;
            if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PDF_RASTER_IMAGE_PIXELS
                || !$this->pdfRasterHasDimensions($contents, $mimeType, $width, $height)) {
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
     * Prefer the compact placement map emitted by PdfReader. A direct media
     * extraction call can still inspect paint locations, but without final
     * text anchors it must not guess where an image belongs.
     *
     * @param list<string> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function pdfImagePlacements(AstNode $document, string $bytes, array &$diagnostics): array
    {
        $metadata = $document->attr('meta', []);
        $placements = is_array($metadata) ? ($metadata['pdfImagePlacements'] ?? null) : null;
        if (is_array($placements)) {
            $normalized = array_values(array_filter($placements, static fn (mixed $placement): bool => is_array($placement)));
            $diagnostics[] = 'extract-media-pdf-placement-metadata:' . count($normalized);

            return $normalized;
        }

        if (!class_exists(\PortLibs\MarkerPDF\PdfTextExtractor::class)) {
            $diagnostics[] = 'extract-media-pdf-placement-unavailable';

            return [];
        }

        try {
            $placements = (new \PortLibs\MarkerPDF\PdfTextExtractor())->extractImagePlacements($bytes);
            $diagnostics[] = 'extract-media-pdf-placement-unanchored-scan:' . count($placements);

            return $placements;
        } catch (\Throwable) {
            $diagnostics[] = 'extract-media-pdf-placement-scan-failed';

            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @param list<string> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function anchoredPdfImagePlacements(AstNode $document, array $placements, array &$diagnostics): array
    {
        $anchored = [];
        $seen = [];
        $candidateCount = 0;
        $candidateCountByPage = [];
        $placementCountByPage = [];
        $candidateLimitReported = false;
        $placementLimitReported = false;
        $candidatePageLimitReported = [];
        $placementPageLimitReported = [];
        foreach ($placements as $placement) {
            if (!$this->pdfImagePlacementIsEligible($placement)) {
                continue;
            }
            if (!is_numeric($placement['object'] ?? null)) {
                continue;
            }
            $page = max(1, (int) ($placement['page'] ?? 1));
            if ($candidateCount >= self::MAX_PDF_IMAGE_PLACEMENT_CANDIDATES) {
                if (!$candidateLimitReported) {
                    $diagnostics[] = 'extract-media-pdf-image-placement-candidate-limit';
                    $candidateLimitReported = true;
                }
                break;
            }
            if (($candidateCountByPage[$page] ?? 0) >= self::MAX_PDF_IMAGE_PLACEMENT_CANDIDATES_PER_PAGE) {
                if (!isset($candidatePageLimitReported[$page])) {
                    $diagnostics[] = 'extract-media-pdf-image-placement-candidate-page-limit:' . $page;
                    $candidatePageLimitReported[$page] = true;
                }
                continue;
            }
            $candidateCount++;
            $candidateCountByPage[$page] = ($candidateCountByPage[$page] ?? 0) + 1;

            $following = $this->uniquePdfImageTextAnchorIndex($document, $placement['followingText'] ?? null);
            $preceding = $this->uniquePdfImageTextAnchorIndex($document, $placement['precedingText'] ?? null);
            if ($following === null && $preceding === null) {
                $diagnostics[] = 'extract-media-pdf-image-unanchored:' . (string) $placement['object'];
                continue;
            }
            if ($following !== null && $preceding !== null && $preceding >= $following) {
                // PDF paint order and the final prose order can disagree.
                // When the two geometry anchors would bracket the image in
                // reverse AST order, choosing one could move it to a wholly
                // unrelated point in the document.
                $diagnostics[] = 'extract-media-pdf-image-anchor-order-conflict:' . (string) $placement['object'];
                continue;
            }

            $placement['anchorIndex'] = $following ?? $preceding;
            $placement['anchorPosition'] = $following !== null ? 'before' : 'after';
            $key = (string) ((int) $placement['object'])
                . ':' . $page
                . ':' . (string) $placement['anchorIndex']
                . ':' . $placement['anchorPosition']
                . ':' . (string) ($placement['paintOrder'] ?? '')
                . ':' . $this->pdfImagePlacementBoundingBoxKey($placement['bbox'] ?? null);
            if (isset($seen[$key])) {
                $diagnostics[] = 'extract-media-pdf-image-placement-duplicate:' . (string) $placement['object'];
                continue;
            }
            if (count($anchored) >= self::MAX_PDF_IMAGE_PLACEMENTS) {
                if (!$placementLimitReported) {
                    $diagnostics[] = 'extract-media-pdf-image-placement-limit';
                    $placementLimitReported = true;
                }
                break;
            }
            if (($placementCountByPage[$page] ?? 0) >= self::MAX_PDF_IMAGE_PLACEMENTS_PER_PAGE) {
                if (!isset($placementPageLimitReported[$page])) {
                    $diagnostics[] = 'extract-media-pdf-image-placement-page-limit:' . $page;
                    $placementPageLimitReported[$page] = true;
                }
                continue;
            }
            $seen[$key] = true;
            $anchored[] = $placement;
            $placementCountByPage[$page] = ($placementCountByPage[$page] ?? 0) + 1;
        }

        return $anchored;
    }

    /**
     * The PDF reader distinguishes a safely anchored clipped image from an
     * image whose geometry is unsafe. Keep backward compatibility with
     * high-confidence metadata emitted before that distinction existed.
     *
     * @param array<string, mixed> $placement
     */
    private function pdfImagePlacementIsEligible(array $placement): bool
    {
        if (array_key_exists('placementEligible', $placement)) {
            return $placement['placementEligible'] === true;
        }

        return ($placement['visible'] ?? false) === true
            && ($placement['confidence'] ?? '') === 'high';
    }

    private function pdfImagePlacementBoundingBoxKey(mixed $bbox): string
    {
        if (!is_array($bbox)) {
            return '';
        }

        $coordinates = [];
        foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
            $value = $bbox[$coordinate] ?? null;
            if (!is_numeric($value)) {
                return '';
            }
            $coordinates[] = rtrim(rtrim(sprintf('%.4F', (float) $value), '0'), '.');
        }

        return implode(',', $coordinates);
    }

    private function uniquePdfImageTextAnchorIndex(AstNode $document, mixed $anchor): ?int
    {
        if (!is_string($anchor)) {
            return null;
        }
        $anchor = $this->normalizedPdfImageAnchorText($anchor);
        if ($anchor === '' || strlen($anchor) < 3) {
            return null;
        }

        $index = null;
        foreach ($document->children as $candidateIndex => $block) {
            if (!in_array($block->type, ['paragraph', 'heading', 'plain'], true)) {
                continue;
            }
            $text = $this->normalizedPdfImageAnchorText((string) $block->attr('text', ''));
            if ($text === '' || !str_contains($text, $anchor)) {
                continue;
            }
            if ($index !== null) {
                return null;
            }
            $index = $candidateIndex;
        }

        return $index;
    }

    private function normalizedPdfImageAnchorText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /**
     * @param list<string> $diagnostics
     * @param list<array<string, mixed>> $requestedPlacements
     * @param array<string,array{contents:string,mimeType:string,width:int,height:int}> $rasterImages
     * @return list<array<string, mixed>>
     */
    private function loadPdfImages(
        MediaBag $bag,
        string $bytes,
        array &$diagnostics,
        string $imageMode,
        array $requestedPlacements,
        array $rasterImages = []
    ): array {
        if (strlen($bytes) > self::MAX_PDF_SCAN_BYTES) {
            $diagnostics[] = 'extract-media-pdf-scan-skipped:too-large';

            return [];
        }

        $placementsByObject = [];
        foreach ($requestedPlacements as $placement) {
            if (!is_numeric($placement['object'] ?? null)) {
                continue;
            }
            $placementsByObject[(string) ((int) $placement['object'])][] = $placement;
        }
        if ($placementsByObject === []) {
            return [];
        }

        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $bytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $assets = [];
        $loaded = 0;
        foreach ($matches as $match) {
            $objectNumber = (string) $match[1];
            $objectKey = (string) ((int) $objectNumber);
            if (!isset($placementsByObject[$objectKey])) {
                continue;
            }
            if ($loaded >= self::MAX_PDF_IMAGES) {
                $diagnostics[] = 'extract-media-pdf-image-limit';
                break;
            }

            $body = (string) $match[3];
            if (!preg_match('#/Subtype\s*/Image\b#', $body)) {
                continue;
            }

            $width = $this->pdfIntegerEntry($body, 'Width');
            $height = $this->pdfIntegerEntry($body, 'Height');
            $imageMask = $this->pdfBooleanEntry($body, 'ImageMask');
            if ($imageMask) {
                $diagnostics[] = 'extract-media-pdf-image-mask-skipped:' . $objectNumber;
                continue;
            }

            $stream = $this->pdfStreamBytes($body);
            if ($stream === null || $stream === '' || strlen($stream) > self::MAX_PDF_IMAGE_BYTES) {
                $diagnostics[] = 'extract-media-pdf-image-skipped:stream';
                continue;
            }

            $filters = $this->pdfFilterNames($body);
            $raster = $rasterImages[$objectNumber] ?? $rasterImages[$objectKey] ?? null;
            if ($raster !== null && ($raster['width'] !== $width || $raster['height'] !== $height)) {
                $diagnostics[] = 'extract-media-pdf-image-raster-skipped:dimensions:' . $objectNumber;
                $raster = null;
            }
            // Selection describes the image painted by the PDF, not the
            // compression ratio of a browser-supplied replacement raster.
            // A lossless PNG can be smaller than its JPX stream (or larger
            // than a JBIG2 stream) without changing whether the source image
            // is an important document asset.
            $importance = $this->pdfImageImportance($width, $height, strlen($stream), false);
            if ($imageMode === 'important' && $importance !== 'important') {
                $diagnostics[] = 'extract-media-pdf-image-unimportant:' . $objectNumber . ':' . $importance;
                continue;
            }

            if ($raster !== null) {
                $mimeType = $raster['mimeType'];
                $extension = $this->pdfRasterExtension($mimeType);
                $imageBytes = $raster['contents'];
                $diagnostics[] = 'extract-media-pdf-image-raster-loaded:' . $objectNumber . ':' . $importance;
            } else {
                $mimeType = $this->pdfEmbeddableMimeType($filters);
                $extension = $mimeType === 'image/jpeg' ? '.jpg' : '.jp2';
                $imageBytes = $stream;
                if ($mimeType === null) {
                    $flateImage = $this->pdfFlateImagePng($body, $filters, $stream, $width, $height, false);
                    if ($flateImage === null) {
                        $diagnostics[] = 'extract-media-pdf-image-skipped:' . ($filters === [] ? 'unfiltered' : implode('+', $filters));
                        continue;
                    }
                    [$mimeType, $extension, $imageBytes] = $flateImage;
                }
            }

            $source = 'pdf/image-' . $objectNumber . $extension;
            $bag->insertMedia($source, $mimeType, $imageBytes);
            // JPEG 2000 is not a browser-safe image format. Keep the source
            // bytes even when an optional rasterizer is unavailable, but do
            // not leave a broken <img> in the converted document. The
            // placement below becomes a download-oriented placeholder until
            // a caller supplies a browser-compatible supplemental raster.
            $webPreviewUnavailable = $raster === null && $mimeType === 'image/jp2';
            $assets[$objectKey] = [
                'source' => $source,
                'object' => $objectNumber,
                'mimeType' => $mimeType,
                'byteLength' => strlen($imageBytes),
                'width' => $width,
                'height' => $height,
                'imageMask' => false,
                'importance' => $importance,
                'webPreviewUnavailable' => $webPreviewUnavailable,
            ];
            if ($webPreviewUnavailable) {
                $diagnostics[] = 'extract-media-pdf-image-placeholder:' . $objectNumber . ':jpeg2000-raster-unavailable';
            }
            $diagnostics[] = 'extract-media-pdf-image-loaded:' . $objectNumber . ':' . $importance;
            $loaded++;
        }

        $resolved = [];
        foreach ($requestedPlacements as $placement) {
            $objectKey = is_numeric($placement['object'] ?? null) ? (string) ((int) $placement['object']) : '';
            if ($objectKey === '' || !isset($assets[$objectKey])) {
                continue;
            }
            $resolved[] = array_replace($placement, $assets[$objectKey]);
        }
        foreach ($placementsByObject as $objectKey => $_placements) {
            if (!isset($assets[$objectKey])) {
                $diagnostics[] = 'extract-media-pdf-image-placement-unavailable:' . $objectKey;
            }
        }

        return $resolved;
    }

    /**
     * @param list<array<string, mixed>> $placements
     */
    private function documentWithPlacedPdfImageBlocks(AstNode $document, array $placements): AstNode
    {
        $before = [];
        $after = [];
        foreach ($placements as $placement) {
            $index = $placement['anchorIndex'] ?? null;
            if (!is_int($index) && !is_numeric($index)) {
                continue;
            }
            $index = (int) $index;
            if (!isset($document->children[$index])) {
                continue;
            }
            $target = ($placement['anchorPosition'] ?? '') === 'after' ? $after : $before;
            $target[$index][] = $placement;
            if (($placement['anchorPosition'] ?? '') === 'after') {
                $after = $target;
            } else {
                $before = $target;
            }
        }

        if ($before === [] && $after === []) {
            return $document;
        }

        $sort = static function (array &$group): void {
            usort($group, static function (array $left, array $right): int {
                $paintOrder = ((int) ($left['paintOrder'] ?? 0)) <=> ((int) ($right['paintOrder'] ?? 0));
                if ($paintOrder !== 0) {
                    return $paintOrder;
                }

                return ((int) ($left['object'] ?? 0)) <=> ((int) ($right['object'] ?? 0));
            });
        };
        foreach ($before as &$group) {
            $sort($group);
        }
        unset($group);
        foreach ($after as &$group) {
            $sort($group);
        }
        unset($group);

        $children = [];
        foreach ($document->children as $index => $block) {
            foreach ($before[$index] ?? [] as $placement) {
                $children[] = $this->placedPdfImageBlock($placement);
            }
            $children[] = $block;
            foreach ($after[$index] ?? [] as $placement) {
                $children[] = $this->placedPdfImageBlock($placement);
            }
        }

        return new AstNode($document->type, $document->attrs, $children);
    }

    /**
     * @param array<string, mixed> $placement
     */
    private function placedPdfImageBlock(array $placement): AstNode
    {
        $attributes = [
            'data-pandoc-pdf-image-object' => (string) $placement['object'],
            'data-pandoc-pdf-image-type' => (string) $placement['mimeType'],
            'data-pandoc-pdf-image-bytes' => (string) $placement['byteLength'],
            'data-pandoc-pdf-image-importance' => (string) $placement['importance'],
            'data-pandoc-pdf-image-placement' => 'inline',
        ];
        if (is_string($placement['id'] ?? null) && $placement['id'] !== '') {
            $attributes['data-pandoc-pdf-visual-id'] = $placement['id'];
        }
        if (isset($placement['width']) && $placement['width'] !== null) {
            $attributes['data-pandoc-pdf-image-width'] = (string) $placement['width'];
        }
        if (isset($placement['height']) && $placement['height'] !== null) {
            $attributes['data-pandoc-pdf-image-height'] = (string) $placement['height'];
        }
        if (isset($placement['page'])) {
            $attributes['data-pandoc-pdf-page'] = (string) $placement['page'];
        }
        if (is_array($placement['bbox'] ?? null)) {
            foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
                if (is_numeric($placement['bbox'][$coordinate] ?? null)) {
                    $attributes['data-pandoc-pdf-image-' . $coordinate] = (string) $placement['bbox'][$coordinate];
                }
            }
        }

        if (($placement['webPreviewUnavailable'] ?? false) === true) {
            $attributes['data-pandoc-pdf-image-rendering'] = 'unavailable';

            return new AstNode('paragraph', [
                'classes' => ['pandoc-pdf-image-block', 'pandoc-pdf-image-placed', 'pandoc-pdf-image-placeholder'],
                'attributes' => $attributes,
            ], [
                new AstNode('span', [
                    'classes' => ['pandoc-pdf-image-placeholder'],
                    'attributes' => array_replace($attributes, ['role' => 'note']),
                ], [
                    new AstNode('text', [
                        'text' => 'PDF image ' . (string) $placement['object']
                            . ' was extracted as JPEG 2000 media, but no JPEG 2000 decoder was available for a preview. ',
                    ]),
                    new AstNode('link', [
                        'url' => (string) $placement['source'],
                        'title' => 'Download original JPEG 2000 image',
                        'attributes' => ['data-pandoc-pdf-image-original' => 'true'],
                    ], [
                        new AstNode('text', ['text' => 'Download original image.']),
                    ]),
                ]),
            ]);
        }

        $imageAttrs = [
            'url' => (string) $placement['source'],
            'title' => 'PDF image ' . (string) $placement['object'],
            'attributes' => $attributes,
        ];
        $displaySize = $this->pdfImageDisplaySize($placement['bbox'] ?? null);
        if ($displaySize !== null) {
            // PDF user-space units are points by default. Retaining the
            // painted bounding-box size keeps an icon from expanding to its
            // source bitmap dimensions when it is inserted into a flowing
            // HTML/WordPress document.
            $imageAttrs['width'] = $displaySize['width'];
            $imageAttrs['height'] = $displaySize['height'];
        }

        return new AstNode('paragraph', ['classes' => ['pandoc-pdf-image-block', 'pandoc-pdf-image-placed']], [
            new AstNode('image', $imageAttrs, [
                new AstNode('text', ['text' => 'PDF image ' . (string) $placement['object']]),
            ]),
        ]);
    }

    /**
     * @return array{width:string,height:string}|null
     */
    private function pdfImageDisplaySize(mixed $bbox): ?array
    {
        if (!is_array($bbox)
            || !is_numeric($bbox['x1'] ?? null) || !is_numeric($bbox['y1'] ?? null)
            || !is_numeric($bbox['x2'] ?? null) || !is_numeric($bbox['y2'] ?? null)) {
            return null;
        }

        $width = abs((float) $bbox['x2'] - (float) $bbox['x1']);
        $height = abs((float) $bbox['y2'] - (float) $bbox['y1']);
        if ($width <= 0.000001 || $height <= 0.000001 || $width > 10000.0 || $height > 10000.0) {
            return null;
        }

        return [
            'width' => $this->pdfPointDimension($width),
            'height' => $this->pdfPointDimension($height),
        ];
    }

    private function pdfPointDimension(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.') . 'pt';
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

    private function pdfRasterHasDimensions(string $bytes, string $mimeType, int $width, int $height): bool
    {
        return match ($mimeType) {
            'image/png' => $this->pngHasDimensions($bytes, $width, $height),
            'image/avif' => $this->avifHasDimensions($bytes, $width, $height),
            default => false,
        };
    }

    private function pdfRasterExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/avif' => '.avif',
            default => '.png',
        };
    }

    /**
     * Verify the minimal AVIF container structure needed to bind a supplied
     * browser/build raster to the PDF Image XObject dimensions. The decoder
     * remains external, but requiring an AVIF brand and an ispe image-spatial
     * extent avoids accepting a mislabeled opaque byte string.
     */
    private function avifHasDimensions(string $bytes, int $width, int $height): bool
    {
        $length = strlen($bytes);
        if ($length < 24) {
            return false;
        }

        $hasAvifBrand = false;
        $offset = 0;
        while ($offset + 8 <= $length) {
            $header = unpack('Nsize', substr($bytes, $offset, 4));
            $boxLength = is_array($header) ? (int) ($header['size'] ?? 0) : 0;
            if ($boxLength === 0) {
                $boxLength = $length - $offset;
            }
            // Extended-length ISO BMFF boxes are not needed for bounded image
            // rasters. Reject them rather than risk an integer-width error.
            if ($boxLength === 1 || $boxLength < 8 || $boxLength > $length - $offset) {
                return false;
            }

            $type = substr($bytes, $offset + 4, 4);
            if ($type === 'ftyp' && $boxLength >= 16) {
                for ($brandOffset = $offset + 8; $brandOffset + 4 <= $offset + $boxLength; $brandOffset += 4) {
                    $brand = substr($bytes, $brandOffset, 4);
                    if ($brand === 'avif' || $brand === 'avis') {
                        $hasAvifBrand = true;
                        break;
                    }
                }
            }
            $offset += $boxLength;
        }
        if (!$hasAvifBrand) {
            return false;
        }

        // `ispe` lives below the meta/iprp/ipco hierarchy. Looking for a
        // bounded complete box is deliberately enough here: dimensions bind
        // this media to an already validated PDF object and the browser still
        // performs the definitive AVIF decode when it displays it.
        $search = 0;
        while (($typeOffset = strpos($bytes, 'ispe', $search)) !== false) {
            $boxOffset = $typeOffset - 4;
            if ($boxOffset >= 0 && $boxOffset + 20 <= $length) {
                $header = unpack('Nsize', substr($bytes, $boxOffset, 4));
                $boxLength = is_array($header) ? (int) ($header['size'] ?? 0) : 0;
                if ($boxLength >= 20 && $boxLength <= $length - $boxOffset) {
                    $dimensions = unpack('Nwidth/Nheight', substr($bytes, $boxOffset + 12, 8));
                    if (is_array($dimensions)
                        && (int) ($dimensions['width'] ?? 0) === $width
                        && (int) ($dimensions['height'] ?? 0) === $height) {
                        return true;
                    }
                }
            }
            $search = $typeOffset + 4;
        }

        return false;
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
