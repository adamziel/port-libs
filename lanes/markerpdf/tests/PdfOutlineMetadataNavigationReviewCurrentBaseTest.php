<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataNavigationReviewPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata navigation review visible body) Tj ET';
    $metadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Outline Navigation Metadata Payload</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $metadataStream = gzcompress($metadataPayload);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress outline navigation metadata stream payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Outline Metadata Navigation Review) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 8 0 R /A 9 0 R /C [0 .2 .4] /F 3 >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] /Next 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/outline-metadata-navigation-review) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $metadataPayload];
};

return [
    'carries outline Metadata stream review into navigation and action rows without payload text' => static function (
        TestRunner $t
    ) use ($outlineMetadataNavigationReviewPdf): void {
        [$pdf, $metadataPayload] = $outlineMetadataNavigationReviewPdf();

        $documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfOutlineExtractor();
        $navigation = $extractor->getNavigationReviewMetadata($pdf);
        $directRows = $extractor->getOutlineStructureDestinationPageContext($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $documentReview = $documentMetadata['document_outline']['items'][0]['metadata_stream_review'] ?? [];
        $outline = $navigation['outline'][0] ?? [];
        $directRow = $directRows[0] ?? [];
        $actions = $navigation['outline_action_review_actions'] ?? [];
        $outlineReview = $outline['metadata_stream_review'] ?? [];
        $directReview = $directRow['metadata_stream_review'] ?? [];

        $t->same('reviewed_outline_item_metadata_stream', $documentReview['status'] ?? null);
        $t->same(8, $documentReview['object_number'] ?? null);
        $t->same(strlen($metadataPayload), $documentReview['bytes'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $documentReview['sha256'] ?? null);

        $t->true(in_array('outline', $navigation['source'], true));
        $t->true(in_array('outline_actions', $navigation['source'], true));
        $t->same('Outline Metadata Navigation Review', $outline['title'] ?? null);
        $t->same(6, $outline['outline_object'] ?? null);
        $t->same('#003366', $outline['text_color_hex'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $outlineReview['status'] ?? null);
        $t->same(8, $outlineReview['object_number'] ?? null);
        $t->same(strlen($metadataPayload), $outlineReview['bytes'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $outlineReview['sha256'] ?? null);
        $t->same(false, $outlineReview['payload_included'] ?? null);
        $t->same(false, $outlineReview['visible_text_source'] ?? null);
        $t->same(false, $outlineReview['accepted_as_document_xmp'] ?? null);

        $t->same(1, count($directRows));
        $t->same(6, $directRow['outline_object'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $directReview['status'] ?? null);
        $t->same(8, $directReview['object_number'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $directReview['sha256'] ?? null);

        $t->same(['GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri'], array_column($actions, 'safety'));
        foreach ($actions as $action) {
            $actionReview = $action['outline_metadata_stream_review'] ?? [];
            $t->same(6, $action['outline_object'] ?? null);
            $t->same('reviewed_outline_item_metadata_stream', $actionReview['status'] ?? null);
            $t->same(8, $actionReview['object_number'] ?? null);
            $t->same(false, $actionReview['payload_included'] ?? null);
            $t->same(false, $actionReview['visible_text_source'] ?? null);
        }

        $t->same('https://example.com/outline-metadata-navigation-review', $actions[1]['uri'] ?? null);
        $t->same('Outline metadata navigation review visible body', $plainText);
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $metadataPayload));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Outline Navigation Metadata Payload'));
        $t->true(!str_contains($plainText, 'Outline Metadata Navigation Review'));
        $t->true(!str_contains($plainText, 'Outline Navigation Metadata Payload'));
        $t->true(!str_contains($plainText, 'outline-metadata-navigation-review'));
    },
    'keeps outline Metadata review payload hashes review-only in WordPress text extraction' => static function (
        TestRunner $t
    ) use ($outlineMetadataNavigationReviewPdf): void {
        [$pdf, $metadataPayload] = $outlineMetadataNavigationReviewPdf();

        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same('Outline metadata navigation review visible body', $plainText);
        $t->true(is_string($encoded) && str_contains($encoded, hash('sha256', $metadataPayload)));
        $t->true(is_string($encoded) && !str_contains($encoded, $metadataPayload));
        $t->true(!str_contains($plainText, hash('sha256', $metadataPayload)));
        $t->true(!str_contains($plainText, 'Outline Metadata Navigation Review'));
        $t->true(!str_contains($plainText, 'Outline Navigation Metadata Payload'));
        $t->true(!str_contains($plainText, 'outline-metadata-navigation-review'));
    },
];
