<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$lightweightOutlineBoundaryPdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Lightweight outline boundary cover body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Lightweight outline boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Lightweight Boundary Current Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Stale Lightweight Boundary Remote Review) /Parent 5 0 R /Prev 99 0 R /Dest [4 0 R /Fit] /A 12 0 R /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Untrusted Lightweight Tail After Bad Prev) /Parent 5 0 R /Prev 7 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (stale-lightweight-outline.pdf) /D (stale-lightweight-target) /NewWindow true >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Lightweight Boundary Info Title) /Author (Current Lightweight Metadata Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n"
        . "%%EOF";
};

return [
    'bounds lightweight pdf_toc traversal by explicit outline Prev backlinks' => static function (
        TestRunner $t
    ) use ($lightweightOutlineBoundaryPdf): void {
        $pdf = $lightweightOutlineBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(2, $lightweight['pages']);
        $t->same('Lightweight Boundary Info Title', $lightweight['document_info']['title'] ?? null);
        $t->same([
            [
                'title' => 'Lightweight Boundary Current Chapter',
                'level' => 1,
                'page' => 0,
            ],
        ], $lightweight['pdf_toc']);
        $t->same(['Lightweight Boundary Current Chapter'], $metadata['document_outline']['titles'] ?? []);
        $t->same(1, $metadata['document_outline']['item_count'] ?? null);
        $t->same("Lightweight outline boundary cover body\nLightweight outline boundary appendix body", $plainText);
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Stale Lightweight Boundary Remote Review'));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Untrusted Lightweight Tail After Bad Prev'));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'stale-lightweight-outline.pdf'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Lightweight Boundary Remote Review'));
        $t->true(!str_contains($plainText, 'Lightweight Boundary Current Chapter'));
        $t->true(!str_contains($plainText, 'Stale Lightweight Boundary Remote Review'));
        $t->true(!str_contains($plainText, 'Untrusted Lightweight Tail After Bad Prev'));
    },
];
