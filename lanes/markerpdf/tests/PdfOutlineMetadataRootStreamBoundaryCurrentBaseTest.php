<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootStreamBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root stream boundary visible body) Tj ET';
    $rootPayload = 'BT /F1 12 Tf 72 720 Td (Outline root stream payload must stay hidden) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Length " . strlen($rootPayload) . " >>\nstream\n{$rootPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Rejected Stream Root Outline) /Parent 5 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('stream root outline action leak'\\)) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootPayload];
};

return [
    'rejects stream-carried outline roots before document metadata promotion' => static function (
        TestRunner $t
    ) use ($outlineRootStreamBoundaryPdf): void {
        [$pdf, $rootPayload] = $outlineRootStreamBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->true(!array_key_exists('document_outline', $metadata));
        $t->true(!array_key_exists('document_outline', $metadata['catalog'] ?? []));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Stream Root Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stream root outline action leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, $rootPayload));
    },
    'keeps stream-carried outline roots out of TOC navigation lightweight metadata and visible text' => static function (
        TestRunner $t
    ) use ($outlineRootStreamBoundaryPdf): void {
        [$pdf, $rootPayload] = $outlineRootStreamBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same([], $toc);
        $t->same([], $navigation['outline']);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same([], $lightweight['pdf_toc']);
        $t->same(1, $lightweight['pages']);
        $t->same('Outline root stream boundary visible body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Rejected Stream Root Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stream root outline action leak'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Rejected Stream Root Outline'));
        $t->true(!str_contains($plainText, 'Rejected Stream Root Outline'));
        $t->true(!str_contains($plainText, 'stream root outline action leak'));
        $t->true(!str_contains($plainText, $rootPayload));
    },
];
