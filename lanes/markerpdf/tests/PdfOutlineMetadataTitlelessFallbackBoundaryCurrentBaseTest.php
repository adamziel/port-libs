<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTitlelessMetadataFallbackBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Titleless outline metadata fallback visible body) Tj ET';
    $metadataPayload = 'BT /F1 12 Tf 72 720 Td (Titleless outline metadata fallback payload should stay hidden) Tj ET';
    $metadataStream = gzcompress($metadataPayload);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress titleless outline metadata fallback payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Parent 5 0 R /Metadata 8 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Safe Titleless Boundary Appendix) /Parent 5 0 R /Prev 6 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $metadataPayload];
};

return [
    'excludes titleless outline item Metadata streams from lightweight fallback text' => static function (
        TestRunner $t
    ) use ($outlineTitlelessMetadataFallbackBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineTitlelessMetadataFallbackBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Safe Titleless Boundary Appendix'], $outline['titles'] ?? []);
        $t->same('Safe Titleless Boundary Appendix', $outline['items'][0]['title'] ?? null);
        $t->same(7, $outline['items'][0]['outline_object'] ?? null);
        $t->same(6, $outline['items'][0]['previous_object'] ?? null);
        $t->true(!isset($outline['items'][0]['metadata_stream_review']));
        $t->same([], $lightweight['pdf_toc'] ?? null);
        $t->same('Titleless outline metadata fallback visible body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $metadataPayload));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $metadataPayload));
        $t->true(!str_contains($plainText, 'Safe Titleless Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Titleless outline metadata fallback payload should stay hidden'));
    },
];
