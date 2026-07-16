<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataRoleOperandBoundaryXmp = static function (string $title): string {
    return '<?xpacket begin=""?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>outline-role-operand-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$outlineMetadataRoleOperandBoundaryPdf = static function () use ($outlineMetadataRoleOperandBoundaryXmp): array {
    $rootTitle = 'Hidden Root Outline Role Operand XMP';
    $itemTitle = 'Hidden Item Outline Role Operand XMP';
    $rootXmp = $outlineMetadataRoleOperandBoundaryXmp($rootTitle);
    $itemXmp = $outlineMetadataRoleOperandBoundaryXmp($itemTitle);
    $rootStream = gzcompress($rootXmp);
    $itemStream = gzcompress($itemXmp);
    if (!is_string($rootStream) || !is_string($itemStream)) {
        throw new RuntimeException('Unable to compress outline role-operand metadata payloads.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline role operand visible body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Role Operand Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata EmbeddedFile /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type 15 0 R /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
        . "15 0 obj\n/Metadata /EmbeddedFile\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootXmp, $itemXmp, $rootTitle, $itemTitle];
};

return [
    'rejects tailed outline Metadata stream role operands without promoting XMP payloads' => static function (
        TestRunner $t
    ) use ($outlineMetadataRoleOperandBoundaryPdf): void {
        [$pdf, $rootXmp, $itemXmp, $rootTitle, $itemTitle] = $outlineMetadataRoleOperandBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $rootReview = $outline['metadata_stream_review'] ?? [];
        $itemReview = $items[0]['metadata_stream_review'] ?? [];
        $navigationRootReview = $navigation['outline_root_review']['metadata_stream_review'] ?? [];
        $navigationItemReview = $navigation['outline'][0]['metadata_stream_review'] ?? [];
        $rootOperand = $rootReview['role_operands'][0] ?? [];
        $itemOperand = $itemReview['role_operands'][0] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Role Operand Boundary Chapter'], $outline['titles'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(['Role Operand Boundary Chapter'], array_column($toc, 'title'));
        $t->same(['Role Operand Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same('Outline role operand visible body', $plainText);

        $t->same('outline_root_metadata_stream', $rootReview['source'] ?? null);
        $t->same('rejected_tailed_outline_root_metadata_stream_role_operand', $rootReview['status'] ?? null);
        $t->same('single_name_token', $rootReview['role_operand_boundary'] ?? null);
        $t->same(true, $rootReview['role_operand_boundary_rejected'] ?? null);
        $t->same('reject_tailed_outline_metadata_stream_role_operands', $rootReview['role_operand_policy'] ?? null);
        $t->same(['Type'], $rootReview['tailed_role_keys'] ?? null);
        $t->same(1, $rootReview['tailed_role_operand_count'] ?? null);
        $t->same(8, $rootReview['object_number'] ?? null);
        $t->same('Metadata', $rootReview['type'] ?? null);
        $t->same('XML', $rootReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $rootReview['filters'] ?? null);
        $t->same(strlen($rootXmp), $rootReview['bytes'] ?? null);
        $t->same(hash('sha256', $rootXmp), $rootReview['sha256'] ?? null);
        $t->same(['title', 'keywords'], $rootReview['xmp_summary']['field_names'] ?? null);
        $t->same('Type', $rootOperand['key'] ?? null);
        $t->same('direct', $rootOperand['kind'] ?? null);
        $t->same('Metadata', $rootOperand['name'] ?? null);
        $t->same('EmbeddedFile', $rootOperand['trailing_operand_preview'] ?? null);
        $t->same(false, $rootOperand['single_name_token'] ?? null);
        $t->same($rootReview['status'] ?? null, $navigationRootReview['status'] ?? null);

        $t->same('outline_item_metadata_stream', $itemReview['source'] ?? null);
        $t->same('rejected_tailed_outline_item_metadata_stream_role_operand', $itemReview['status'] ?? null);
        $t->same('single_name_token', $itemReview['role_operand_boundary'] ?? null);
        $t->same(true, $itemReview['role_operand_boundary_rejected'] ?? null);
        $t->same('reject_tailed_outline_metadata_stream_role_operands', $itemReview['role_operand_policy'] ?? null);
        $t->same(['Type'], $itemReview['tailed_role_keys'] ?? null);
        $t->same(1, $itemReview['tailed_role_operand_count'] ?? null);
        $t->same(9, $itemReview['object_number'] ?? null);
        $t->same('Metadata', $itemReview['type'] ?? null);
        $t->same('XML', $itemReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $itemReview['filters'] ?? null);
        $t->same(strlen($itemXmp), $itemReview['bytes'] ?? null);
        $t->same(hash('sha256', $itemXmp), $itemReview['sha256'] ?? null);
        $t->same(['title', 'keywords'], $itemReview['xmp_summary']['field_names'] ?? null);
        $t->same('Type', $itemOperand['key'] ?? null);
        $t->same('indirect', $itemOperand['kind'] ?? null);
        $t->same(15, $itemOperand['object_number'] ?? null);
        $t->same('Metadata', $itemOperand['name'] ?? null);
        $t->same('/EmbeddedFile', $itemOperand['trailing_operand_preview'] ?? null);
        $t->same(false, $itemOperand['single_name_token'] ?? null);
        $t->same($itemReview['status'] ?? null, $navigationItemReview['status'] ?? null);

        foreach ([$rootXmp, $itemXmp, $rootTitle, $itemTitle, 'outline-role-operand-boundary'] as $payload) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $payload));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $payload));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(!str_contains($plainText, 'Role Operand Boundary Chapter'));
    },
];
