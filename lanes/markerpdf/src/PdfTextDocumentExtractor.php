<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfTextDocumentExtractor
{
    private PdfTextBlockConverter $converter;

    public function __construct(?PdfTextBlockConverter $converter = null)
    {
        $this->converter = $converter ?? new PdfTextBlockConverter();
    }

    /**
     * Native supplied-data boundary for marker.pdf.extract_text::get_text_blocks.
     *
     * Upstream gets the dictionary pages from pdftext over page_range and then
     * enumerates that sliced result, so span IDs restart at 0 even when page["page"]
     * remains the original document page number.
     *
     * @param list<array<string, mixed>> $pdftextPages
     * @param list<array<string, mixed>> $toc
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     toc: list<array<string, mixed>>,
     *     metadata: array{pdf_toc: list<array<string, mixed>>, pages: int, start_page: int, max_pages: int},
     *     page_range: list<int>
     * }
     */
    public function getTextBlocks(
        array $pdftextPages,
        ?int $maxPages = null,
        ?int $startPage = null,
        array $toc = []
    ): array {
        $totalPages = count($pdftextPages);
        $startPage ??= 0;

        if ($startPage < 0 || ($totalPages > 0 && $startPage >= $totalPages) || ($totalPages === 0 && $startPage !== 0)) {
            throw new InvalidArgumentException('start_page must be within supplied pdftext pages.');
        }

        if ($maxPages !== null && $maxPages < 0) {
            throw new InvalidArgumentException('max_pages must be zero or greater.');
        }

        $pageCount = $totalPages - $startPage;
        if ($maxPages !== null && $maxPages > 0) {
            $pageCount = min($maxPages, $pageCount);
        }

        $selectedPages = array_slice($pdftextPages, $startPage, $pageCount);
        $pages = [];
        foreach (array_values($selectedPages) as $relativeIndex => $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Supplied pdftext page entries must be arrays.');
            }
            $pages[] = $this->converter->pdftextFormatToPage($page, $relativeIndex);
        }

        $pageRange = $pageCount > 0 ? range($startPage, $startPage + $pageCount - 1) : [];

        return [
            'pages' => $pages,
            'toc' => array_values($toc),
            'metadata' => [
                'pdf_toc' => array_values($toc),
                'pages' => count($pages),
                'start_page' => $startPage,
                'max_pages' => $pageCount,
            ],
            'page_range' => $pageRange,
        ];
    }
}
