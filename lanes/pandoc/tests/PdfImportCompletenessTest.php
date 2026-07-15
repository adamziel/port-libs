<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

/**
 * Build a current-xref PDF whose optional image stream can push the Catalog,
 * page tree, and trailer beyond the reader's historical 5 MB prefix scan.
 *
 * @param list<string> $pageTexts
 */
$buildPdf = static function (
    array $pageTexts,
    int $imagePaddingBytes = 0,
    bool $includeOrphanPage = false,
    bool $indirectKidsArray = false
): string {
    $objects = [];
    $nextObject = 1;
    if ($imagePaddingBytes > 0) {
        $imageBytes = str_repeat('I', $imagePaddingBytes);
        $objects[$nextObject++] = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8'
            . ' /Filter /DCTDecode /Length ' . strlen($imageBytes) . ">>\nstream\n{$imageBytes}\nendstream";
    }

    $catalogObject = $nextObject++;
    $pagesObject = $nextObject++;
    $pageObjects = [];
    $contentObjects = [];
    foreach ($pageTexts as $_text) {
        $pageObjects[] = $nextObject++;
        $contentObjects[] = $nextObject++;
    }
    $fontObject = $nextObject++;
    $orphanPageObject = $includeOrphanPage ? $nextObject++ : null;
    $kidsArrayObject = $indirectKidsArray ? $nextObject++ : null;

    $objects[$catalogObject] = "<< /Type /Catalog /Pages {$pagesObject} 0 R >>";
    $kids = implode(' ', array_map(static fn (int $object): string => "{$object} 0 R", $pageObjects));
    $kidsValue = $kidsArrayObject === null ? '[' . $kids . ']' : $kidsArrayObject . ' 0 R';
    $objects[$pagesObject] = '<< /Type /Pages /Kids ' . $kidsValue . ' /Count ' . count($pageObjects) . ' >>';
    if ($kidsArrayObject !== null) {
        $objects[$kidsArrayObject] = '[' . $kids . ']';
    }
    $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    foreach ($pageTexts as $index => $pageText) {
        $commands = [];
        $pageLines = explode("\n", $pageText);
        foreach ($pageLines as $lineIndex => $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $commands[] = '(' . $escaped . ') Tj' . ($lineIndex + 1 < count($pageLines) ? ' T*' : '');
        }
        $content = 'BT /F1 12 Tf 14 TL 72 720 Td ' . implode(' ', $commands) . ' ET';
        $pageObject = $pageObjects[$index];
        $contentObject = $contentObjects[$index];
        $objects[$pageObject] = "<< /Type /Page /Parent {$pagesObject} 0 R /MediaBox [0 0 612 792]"
            . " /Resources << /Font << /F1 {$fontObject} 0 R >> >> /Contents {$contentObject} 0 R >>";
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }

    if ($orphanPageObject !== null) {
        $objects[$orphanPageObject] = '<< /Type /Page /MediaBox [0 0 1 1] >>';
    }

    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $body) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $size = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber < $size; $objectNumber++) {
        if (isset($offsets[$objectNumber])) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
        } else {
            $pdf .= "0000000000 00000 f \n";
        }
    }
    $pdf .= "trailer\n<< /Size {$size} /Root {$catalogObject} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
};

return [
    'uses the current xref page tree beyond five megabytes instead of counting a bounded direct-object prefix' => static function (TestRunner $t) use ($buildPdf): void {
        $pdf = $buildPdf(['Page one', 'Page two', 'Page three'], 5_050_000, true);

        $metadata = (new PdfMetadataExtractor())->extractReaderStructuralMetadata($pdf);

        $t->same(3, $metadata['page_count'] ?? null, 'Only pages reachable from the current Catalog page tree count.');
        $t->same(false, $metadata['page_count_limited'] ?? false, 'A valid tail xref and complete current page tree establish the count exactly.');
    },

    'does not turn image-heavy source bytes into an implicit two-page text limit' => static function (TestRunner $t) use ($buildPdf): void {
        $pdf = $buildPdf(['First page sentinel', 'Second page sentinel', 'Third page must survive'], 5_050_000);

        $document = (new PdfReader())->read($pdf);
        $meta = $document->attr('meta');
        $html = PandocConverter::write($document, 'html');

        $t->contains('Third page must survive', $html);
        $t->same(false, $meta['pdfFastTextOnly'] ?? null);
        $t->same(3, $meta['pdfPageCount'] ?? null);
        $t->same(3, $meta['pdfPagesProcessed'] ?? null);
        $t->same(false, $meta['pdfHasMorePages'] ?? null);
        $t->same(true, $meta['pdfTextComplete'] ?? null);
        $t->same(true, $meta['pdfDocumentComplete'] ?? null);
    },

    'resolves an indirect page-tree Kids array used by browser-generated PDFs' => static function (TestRunner $t) use ($buildPdf): void {
        $pdf = $buildPdf(['Indirect page one', 'Indirect page two', 'Indirect page three'], 0, false, true);

        $inventory = (new \PortLibs\MarkerPDF\PdfTextExtractor([
            'pdfStartPage' => 3,
            'pdfMaxPages' => 1,
        ]))->extractPageInventory($pdf);
        $document = (new PdfReader(['pdfStartPage' => 3, 'pdfMaxPages' => 1]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->same(3, $inventory['totalPages'] ?? null);
        $t->same([3], $inventory['pageNumbers'] ?? null);
        $t->contains('Indirect page three', $html);
        $t->true(!str_contains($html, 'Indirect page one'));
    },

    'imports default text beyond the old whole-document byte ceiling' => static function (TestRunner $t) use ($buildPdf): void {
        $lines = [];
        for ($index = 0; $index < 1_500; $index++) {
            $lines[] = sprintf('Paragraph line %04d carries enough ordinary prose to exercise a document-wide text budget safely.', $index);
        }
        $lines[] = 'LAST PAGE TEXT SENTINEL AFTER ONE HUNDRED TWENTY THOUSAND BYTES';

        $document = (new PdfReader())->read($buildPdf([implode("\n", $lines)]));
        $meta = $document->attr('meta');
        $html = PandocConverter::write($document, 'html');

        $t->contains('LAST PAGE TEXT SENTINEL AFTER ONE HUNDRED TWENTY THOUSAND BYTES', $html);
        $t->same(false, $meta['pdfTextLimited'] ?? null);
        $t->same(true, $meta['pdfTextComplete'] ?? null);
        $t->same(true, $meta['pdfDocumentComplete'] ?? null);
    },

    'an explicit text budget stops at a complete UTF-8 line and reports an incomplete range' => static function (TestRunner $t) use ($buildPdf): void {
        $first = 'First complete line.';
        $second = 'Second café conclusion must not be sliced.';
        $limit = strlen($first) + 1 + strlen('Second caf');

        $document = (new PdfReader(['maxTextBytes' => $limit]))->read($buildPdf([$first . "\n" . $second]));
        $meta = $document->attr('meta');
        $html = PandocConverter::write($document, 'html');

        $t->contains($first, $html);
        $t->true(!str_contains($html, 'Second caf'), 'The reader must not emit a partial word or partial UTF-8 sequence.');
        $t->same(true, mb_check_encoding($html, 'UTF-8'));
        $t->same(true, $meta['pdfTextLimited'] ?? null);
        $t->same(false, $meta['pdfTextComplete'] ?? null);
        $t->same(false, $meta['pdfRangeComplete'] ?? null);
        $t->same(false, $meta['pdfDocumentComplete'] ?? null);
        $t->true(in_array('text-bytes', $meta['pdfLimitReasons'] ?? [], true), 'The exact completeness failure must be machine-readable.');
    },

    'selects resumable page ranges while preserving original page numbers and next-page metadata' => static function (TestRunner $t) use ($buildPdf): void {
        $pdf = $buildPdf(['Page one only', 'Page two only', 'Page three only']);

        $document = (new PdfReader(['pdfStartPage' => 2, 'pdfMaxPages' => 1]))->read($pdf);
        $meta = $document->attr('meta');
        $html = PandocConverter::write($document, 'html');

        $t->true(!str_contains($html, 'Page one only'));
        $t->contains('Page two only', $html);
        $t->true(!str_contains($html, 'Page three only'));
        $t->same(3, $meta['pdfPageCount'] ?? null);
        $t->same(2, $meta['pdfPageStart'] ?? null);
        $t->same(2, $meta['pdfPageEnd'] ?? null);
        $t->same(1, $meta['pdfPagesProcessed'] ?? null);
        $t->same(true, $meta['pdfHasMorePages'] ?? null);
        $t->same(3, $meta['pdfNextPage'] ?? null);
        $t->same(true, $meta['pdfRangeComplete'] ?? null);
        $t->same(false, $meta['pdfDocumentComplete'] ?? null);
        $t->same([2], $meta['pdfProcessedPageNumbers'] ?? null);
    },

    'reports a positioned-text cap instead of silently claiming complete geometry' => static function (TestRunner $t) use ($buildPdf): void {
        $pdf = $buildPdf(["First positioned run\nSecond positioned run"]);

        $meta = (new PdfReader([
            'pdfRepairProseText' => true,
            'pdfMaxPositionedTextRuns' => 1,
        ]))->read($pdf)->attr('meta');

        $t->same(false, $meta['pdfGeometryComplete'] ?? null);
        $t->true(in_array('positioned-text-runs', $meta['pdfLimitReasons'] ?? [], true));
        $t->same(false, $meta['pdfDocumentComplete'] ?? null);
    },
];
