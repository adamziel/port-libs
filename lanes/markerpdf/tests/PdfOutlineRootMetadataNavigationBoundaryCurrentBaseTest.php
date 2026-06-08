<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootMetadataNavigationPdf = static function (bool $tailed = false): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root metadata navigation boundary body) Tj ET';
    $rootPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Navigation Root Metadata Payload</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $tailPayload = '<x:xmpmeta>Trailing root metadata operand payload must stay hidden</x:xmpmeta>';
    $rootStream = gzcompress($rootPayload);
    $tailStream = gzcompress($tailPayload);
    if (!is_string($rootStream) || !is_string($tailStream)) {
        throw new RuntimeException('Unable to compress outline root metadata navigation payloads.');
    }

    $metadataOperand = $tailed ? '/Metadata 8 0 R 10 0 R ' : '/Metadata 8 0 R ';
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines {$metadataOperand}/First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Metadata Navigation Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($tailStream) . " >>\nstream\n{$tailStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootPayload, $tailPayload];
};

return [
    'carries outline root Metadata stream review into navigation metadata without payload text' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataNavigationPdf): void {
        [$pdf, $rootPayload, $tailPayload] = $outlineRootMetadataNavigationPdf(false);

        $documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $documentRootReview = $documentMetadata['document_outline']['metadata_stream_review'] ?? [];
        $navigationRoot = $navigation['outline_root_review'] ?? [];
        $navigationRootReview = $navigationRoot['metadata_stream_review'] ?? [];

        $t->same('reviewed_outline_root_metadata_stream', $documentRootReview['status'] ?? null);
        $t->same(8, $documentRootReview['object_number'] ?? null);
        $t->same(strlen($rootPayload), $documentRootReview['bytes'] ?? null);
        $t->same(hash('sha256', $rootPayload), $documentRootReview['sha256'] ?? null);

        $t->true(in_array('outline_root_review', $navigation['source'], true));
        $t->same('outline_root_review', $navigationRoot['source'] ?? null);
        $t->same(true, $navigationRoot['review_only'] ?? null);
        $t->same(false, $navigationRoot['payload_included'] ?? null);
        $t->same(false, $navigationRoot['visible_text_source'] ?? null);
        $t->same(5, $navigationRoot['outline_root_object'] ?? null);
        $t->same(6, $navigationRoot['first_item_object'] ?? null);
        $t->same(6, $navigationRoot['last_item_object'] ?? null);
        $t->same(1, $navigationRoot['outline_count'] ?? null);
        $t->same('expanded', $navigationRoot['structure_state'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $navigationRootReview['status'] ?? null);
        $t->same('outline_root_metadata_stream', $navigationRootReview['source'] ?? null);
        $t->same(8, $navigationRootReview['object_number'] ?? null);
        $t->same(0, $navigationRootReview['object_generation'] ?? null);
        $t->same(strlen($rootPayload), $navigationRootReview['bytes'] ?? null);
        $t->same(hash('sha256', $rootPayload), $navigationRootReview['sha256'] ?? null);
        $t->same(false, $navigationRootReview['payload_included'] ?? null);
        $t->same(false, $navigationRootReview['visible_text_source'] ?? null);
        $t->same(false, $navigationRootReview['accepted_as_document_xmp'] ?? null);

        $t->same(['Root Metadata Navigation Chapter'], array_column($toc, 'title'));
        $t->same(['Root Metadata Navigation Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same('Outline root metadata navigation boundary body', $plainText);
        foreach ([$rootPayload, $tailPayload, 'Hidden Navigation Root Metadata Payload'] as $hiddenText) {
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hiddenText));
            $t->true(!str_contains($plainText, $hiddenText));
        }
        $t->true(!str_contains($plainText, 'Root Metadata Navigation Chapter'));
    },
    'propagates malformed outline root Metadata operand review into navigation metadata' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataNavigationPdf): void {
        [$pdf, $rootPayload, $tailPayload] = $outlineRootMetadataNavigationPdf(true);

        $documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $documentRootReview = $documentMetadata['document_outline']['metadata_stream_review'] ?? [];
        $navigationRoot = $navigation['outline_root_review'] ?? [];
        $navigationRootReview = $navigationRoot['metadata_stream_review'] ?? [];

        $t->same('rejected_malformed_outline_root_metadata_operand', $documentRootReview['status'] ?? null);
        $t->same(8, $documentRootReview['object_number'] ?? null);
        $t->same([10], $documentRootReview['trailing_reference_object_numbers'] ?? null);
        $t->true(!array_key_exists('bytes', $documentRootReview));
        $t->true(!array_key_exists('sha256', $documentRootReview));

        $t->true(in_array('outline_root_review', $navigation['source'], true));
        $t->same('outline_root_review', $navigationRoot['source'] ?? null);
        $t->same(5, $navigationRoot['outline_root_object'] ?? null);
        $t->same('rejected_malformed_outline_root_metadata_operand', $navigationRootReview['status'] ?? null);
        $t->same('outline_root_metadata_stream', $navigationRootReview['source'] ?? null);
        $t->same(8, $navigationRootReview['object_number'] ?? null);
        $t->same(0, $navigationRootReview['object_generation'] ?? null);
        $t->same(2, $navigationRootReview['metadata_operand_count'] ?? null);
        $t->same([10], $navigationRootReview['trailing_reference_object_numbers'] ?? null);
        $t->same(['indirect_reference'], $navigationRootReview['trailing_operand_shapes'] ?? null);
        $t->same(false, $navigationRootReview['payload_included'] ?? null);
        $t->same(false, $navigationRootReview['visible_text_source'] ?? null);
        $t->same(false, $navigationRootReview['accepted_as_document_xmp'] ?? null);
        $t->true(!array_key_exists('bytes', $navigationRootReview));
        $t->true(!array_key_exists('sha256', $navigationRootReview));

        $t->same(['Root Metadata Navigation Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same('Outline root metadata navigation boundary body', $plainText);
        foreach ([$rootPayload, $tailPayload, 'Trailing root metadata operand payload must stay hidden'] as $hiddenText) {
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hiddenText));
            $t->true(!str_contains($plainText, $hiddenText));
        }
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'reviewed_outline_root_metadata_stream'));
        $t->true(!str_contains($plainText, 'Root Metadata Navigation Chapter'));
    },
];
