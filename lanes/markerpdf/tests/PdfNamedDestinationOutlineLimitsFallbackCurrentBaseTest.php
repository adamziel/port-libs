<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationOutlineLimitsFallbackCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Outline limit fallback start page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Outline limit fallback review page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> /Outlines 50 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current Start) (Review Summary)] /Kids [21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(zz-stale) (zz-stale)] /Names [(Current Start) [3 0 R /FitH 700] (Review Summary) 22 0 R (zz-stale) [4 0 R /FitH 111]] >>\nendobj\n"
        . "22 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Current Start Outline) /Parent 50 0 R /Dest (Current Start) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Review Summary Outline) /Parent 50 0 R /Dest (Review Summary) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Stale Decoy Outline) /Parent 50 0 R /Dest (zz-stale) /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'falls back to inherited destination limits before outline destination view metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationOutlineLimitsFallbackCurrentBasePdf): void {
        $pdf = $namedDestinationOutlineLimitsFallbackCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Current Start', 'Review Summary', 'LegacyOnly'], array_column($destinations, 'name'));
        $t->same(['Current Start', 'Review Summary', 'LegacyOnly'], $metadata['document_destinations']['names'] ?? null);
        $t->same(['Current Start Outline', 'Review Summary Outline'], array_column($toc, 'title'));
        $t->same(['Current Start', 'Review Summary'], array_column($toc, 'destination'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same([[700.0], [72.0, 640.0, null]], array_column($toc, 'view_position'));
        $t->same([['top' => 700.0], ['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($toc, 'view_parameters'));

        $encoded = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Stale Decoy Outline'));
        $t->same(false, str_contains($encoded, 'zz-stale'));
        $t->same(false, str_contains($encoded, '111'));
    },
    'keeps malformed outline destination limit decoys out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationOutlineLimitsFallbackCurrentBasePdf): void {
        $pdf = $namedDestinationOutlineLimitsFallbackCurrentBasePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->contains('Outline limit fallback start page', $plainText);
        $t->contains('Outline limit fallback review page', $plainText);
        foreach ([
            'Current Start Outline',
            'Review Summary Outline',
            'Stale Decoy Outline',
            'Current Start',
            'Review Summary',
            'zz-stale',
        ] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }

        $encoded = json_encode($toc, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Stale Decoy Outline'));
        $t->same(false, str_contains($encoded, 'zz-stale'));
    },
];
