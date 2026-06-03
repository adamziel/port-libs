<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class CorePdfConverter
{
    private OcrLanguage $languages;
    private PdfSecurityPreflight $securityPreflight;

    public function __construct(?OcrLanguage $languages = null, ?PdfSecurityPreflight $securityPreflight = null)
    {
        $this->languages = $languages ?? new OcrLanguage();
        $this->securityPreflight = $securityPreflight ?? new PdfSecurityPreflight();
    }

    /**
     * Native supplied-boundary slice for marker.convert::convert_single_pdf.
     *
     * The caller supplies the page dictionaries that upstream would get from
     * pdftext/pypdfium and the downstream conversion pipeline callback that
     * would normally run OCR/layout/order/table/equation/finalization models.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>> $toc
     * @param callable(list<array<string, mixed>>, array<string, mixed>): mixed $pipeline
     * @param list<string>|null $langs
     * @param array<string, mixed>|null $metadata
     * @return array{text: string, images: array<string, mixed>, metadata: array<string, mixed>, context: array<string, mixed>}
     */
    public function convertWithSuppliedPages(
        string $filename,
        array $pages,
        array $toc,
        callable $pipeline,
        ?int $maxPages = null,
        ?int $startPage = null,
        ?array $metadata = null,
        ?array $langs = null,
        int|float $batchMultiplier = 1,
        bool $ocrAllPages = false,
        ?int $documentPageCount = null,
        ?MarkerSettings $settings = null
    ): array {
        $settings ??= new MarkerSettings();
        $metadata ??= [];

        if (array_key_exists('languages', $metadata)) {
            $langs = $this->languageList($metadata['languages']);
        } else {
            $langs = $this->languageList($langs);
        }

        $ocrEngine = (string) ($settings->get('OCR_ENGINE') ?? 'surya');
        $langs = $this->languages->normalizeAndValidate(
            $langs,
            $ocrEngine,
            (string) $settings->get('DEFAULT_LANG')
        );

        $filetype = (new FiletypeDetector($settings))->findFiletype($filename);
        $outMetadata = [
            'languages' => $langs,
            'filetype' => $filetype,
        ];
        $pdfSecurity = $filetype === 'pdf' ? $this->pdfSecurityForFile($filename) : null;
        if ($pdfSecurity !== null) {
            $outMetadata['pdf_security'] = $pdfSecurity;
        }
        $context = [
            'filename' => $filename,
            'max_pages' => $maxPages,
            'start_page' => $startPage,
            'langs' => $langs,
            'ocr_all_pages' => $ocrAllPages || (bool) $settings->get('OCR_ALL_PAGES'),
            'batch_multiplier' => (float) $batchMultiplier,
            'filetype' => $filetype,
            'stage' => 'preflight',
        ];
        if ($pdfSecurity !== null) {
            $context['pdf_security'] = $pdfSecurity;
        }

        if ($filetype === 'other') {
            $context['stage'] = 'unsupported-filetype';

            return [
                'text' => '',
                'images' => [],
                'metadata' => $outMetadata,
                'context' => $context,
            ];
        }

        if ($pdfSecurity !== null && $pdfSecurity['encrypted'] === true) {
            $context['stage'] = 'encrypted-pdf-preflight';

            return [
                'text' => '',
                'images' => [],
                'metadata' => $outMetadata,
                'context' => $context,
            ];
        }

        $outMetadata['pdf_toc'] = $toc;
        $outMetadata['pages'] = count($pages);
        $lowresImagePlan = $this->lowresImagePlan(
            count($pages),
            $documentPageCount,
            $startPage,
            (int) $settings->get('SURYA_DETECTOR_DPI')
        );
        $context = array_replace($context, [
            'pdf_toc' => $toc,
            'page_count' => count($pages),
            'document_page_count' => $documentPageCount,
            'trimmed_document_page_count' => $this->trimmedDocumentPageCount($documentPageCount, $startPage),
            'lowres_image_plan' => $lowresImagePlan,
            'lowres_image_count' => count($lowresImagePlan),
            'stage' => 'supplied-pages',
        ]);

        $conversion = $this->normalizeConversion($pipeline(array_values($pages), $context));

        return [
            'text' => $conversion['text'],
            'images' => $conversion['images'],
            'metadata' => array_replace_recursive($outMetadata, $conversion['metadata']),
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pdfSecurityForFile(string $filename): array
    {
        $bytes = @file_get_contents($filename);
        if (!is_string($bytes)) {
            throw new InvalidArgumentException('Unable to read PDF security preflight source: ' . $filename);
        }

        $security = $this->securityPreflight->analyze($bytes);
        $encrypted = ($security['encrypted'] ?? false) === true;
        $contentExtractionAllowed = ($security['content_extraction_allowed'] ?? false) === true;

        $security['permission_allows_text_extraction'] = $contentExtractionAllowed;
        $security['should_queue_models'] = !$encrypted;

        return $security;
    }

    /**
     * @return list<array{doc_page_index: int, dpi: int}>
     */
    private function lowresImagePlan(int $pageCount, ?int $documentPageCount, ?int $startPage, int $dpi): array
    {
        $availableDocumentPages = $this->trimmedDocumentPageCount($documentPageCount, $startPage);
        $imageCount = min($pageCount, $availableDocumentPages ?? $pageCount);
        $plan = [];
        for ($index = 0; $index < $imageCount; $index++) {
            $plan[] = [
                'doc_page_index' => $index,
                'dpi' => $dpi,
            ];
        }

        return $plan;
    }

    private function trimmedDocumentPageCount(?int $documentPageCount, ?int $startPage): ?int
    {
        if ($documentPageCount === null) {
            return null;
        }
        if ($startPage === null || $startPage <= 0) {
            return max(0, $documentPageCount);
        }

        return max(0, $documentPageCount - $startPage);
    }

    /**
     * @return list<string>|null
     */
    private function languageList(mixed $langs): ?array
    {
        if ($langs === null) {
            return null;
        }
        if (!is_array($langs)) {
            throw new InvalidArgumentException('convert_single_pdf languages must be a list when supplied.');
        }

        return array_map(static fn (mixed $lang): string => (string) $lang, array_values($langs));
    }

    /**
     * @return array{text: string, images: array<string, mixed>, metadata: array<string, mixed>}
     */
    private function normalizeConversion(mixed $conversion): array
    {
        if (is_string($conversion)) {
            return ['text' => $conversion, 'images' => [], 'metadata' => []];
        }
        if (!is_array($conversion)) {
            throw new InvalidArgumentException('Core PDF conversion pipeline must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? '';
        $images = $conversion['images'] ?? $conversion[1] ?? [];
        $metadata = $conversion['metadata'] ?? $conversion['out_metadata'] ?? $conversion[2] ?? [];

        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Core PDF conversion pipeline images and metadata must be arrays.');
        }

        return [
            'text' => (string) $text,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }
}
