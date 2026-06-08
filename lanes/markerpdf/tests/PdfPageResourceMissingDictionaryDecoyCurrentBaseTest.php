<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceMissingDictionaryDecoyPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Page without resource dictionary raw text) Tj ET q /DecoyForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Top-level page XObject decoy leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Font << /F1 5 0 R >> /XObject << /DecoyForm 6 0 R >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'does not promote top-level page Font or XObject decoys when Resources is omitted' => static function (
        TestRunner $t
    ) use ($pageResourceMissingDictionaryDecoyPdf): void {
        $pdf = $pageResourceMissingDictionaryDecoyPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'] ?? [], 'text')),
            $styledPages[0]['blocks'] ?? []
        );
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $expected = ['Page without resource dictionary raw text'];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same('Page without resource dictionary raw text', $plainText);
        $t->same("Page without resource dictionary raw text\n", $extractor->naiveGetText($pdf));
        $t->same([], $boundary);
        $t->same(false, str_contains($plainText, 'Top-level page XObject decoy leak'));
        $t->same(false, str_contains($plainText, 'DecoyForm'));
        $t->same(false, str_contains(json_encode($styledPages, JSON_UNESCAPED_SLASHES) ?: '', 'Top-level page XObject decoy leak'));
    },
];
