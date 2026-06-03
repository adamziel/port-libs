<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStructureElementMetadataPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf /ChapterTitle << /MCID 0 >> BDC 72 720 Td (Visible tagged outline section) Tj EMC ET';
    $payload = '<wp-export><post id="outline-se-structure"/></wp-export>';
    $checksum = strtoupper(hash('md5', $payload));

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /Outlines 40 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>\nendobj\n"
        . "41 0 obj\n<< /Title (Review-only outline structure link) /Parent 40 0 R /Dest [3 0 R /FitH 720] /SE 60 0 R /F 3 >>\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ChapterTitle /H1 >> /ParentTree 55 0 R /K [60 0 R] >>\nendobj\n"
        . "55 0 obj\n<< /Nums [0 [60 0 R]] >>\nendobj\n"
        . "60 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /Pg 3 0 R /Lang (en-GB) /T (Outline Structure Title) /ID (outline-structure-1) /Alt (Accessible Outline Summary) /C [/section /review] /R 2 /AF [70 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
        . "70 0 obj\n<< /Type /Filespec /F (outline-section-source.xml) /Desc (Outline structure source payload) /AFRelationship /Source /EF << /F 71 0 R >> >>\nendobj\n"
        . "71 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'maps outline item SE structure elements into document outline metadata' => static function (
        TestRunner $t
    ) use ($outlineStructureElementMetadataPdf): void {
        [$pdf, $payload, $checksum] = $outlineStructureElementMetadataPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $structure = $item['structure_element'] ?? [];
        $files = $structure['associated_files'] ?? [];
        $source = $files[0] ?? [];
        $provenance = $source['provenance_review'] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['structure_element_count'] ?? null);
        $t->same(true, $outline['structure_element_review_only'] ?? null);
        $t->same(false, $outline['structure_element_payload_included'] ?? null);
        $t->same([60], $outline['structure_element_objects'] ?? []);
        $t->same(['H1'], $outline['structure_element_roles'] ?? []);
        $t->same(['ChapterTitle'], $outline['structure_element_raw_roles'] ?? []);
        $t->same([0], $outline['structure_element_mcids'] ?? []);
        $t->same(1, $outline['structure_element_associated_file_count'] ?? null);

        $t->same('Review-only outline structure link', $item['title'] ?? null);
        $t->same(41, $item['outline_object'] ?? null);
        $t->same(0, $item['page'] ?? null);
        $t->same(3, $item['page_object'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same(60, $item['structure_element_object'] ?? null);
        $t->same('ChapterTitle', $item['structure_element_raw_role'] ?? null);
        $t->same('H1', $item['structure_element_role'] ?? null);
        $t->same(0, $item['structure_element_page'] ?? null);
        $t->same(1, $item['structure_element_page_number'] ?? null);
        $t->same([0], $item['structure_element_mcids'] ?? []);
        $t->same(1, $item['structure_element_associated_file_count'] ?? null);

        $t->same('outline_item_structure_element', $structure['source'] ?? null);
        $t->same(true, $structure['review_only'] ?? null);
        $t->same(false, $structure['visible_text_source'] ?? null);
        $t->same(false, $structure['payload_included'] ?? null);
        $t->same(60, $structure['object'] ?? null);
        $t->same('Outline Structure Title', $structure['title'] ?? null);
        $t->same('outline-structure-1', $structure['id'] ?? null);
        $t->same('Accessible Outline Summary', $structure['alternate_text'] ?? null);
        $t->same(['section', 'review'], $structure['classes'] ?? []);
        $t->same(2, $structure['revision'] ?? null);
        $t->same('en-GB', $structure['language'] ?? null);
        $t->same(false, $structure['language_inherited'] ?? null);
        $t->same(0, $structure['marked_content'][0]['mcid'] ?? null);
        $t->same(3, $structure['marked_content'][0]['page_object'] ?? null);
        $t->same(0, $structure['marked_content'][0]['page'] ?? null);

        $t->same('structure_element_associated_files', $source['source'] ?? null);
        $t->same('outline-section-source.xml', $source['filename'] ?? null);
        $t->same('Source', $source['relationship'] ?? null);
        $t->same('text/xml', $source['mime_type'] ?? null);
        $t->same(70, $source['file_spec_object'] ?? null);
        $t->same(71, $source['embedded_file_object'] ?? null);
        $t->same(hash('sha256', $payload), $source['content_sha256'] ?? null);
        $t->same(strtolower($checksum), $source['checksum'] ?? null);
        $t->same(true, $source['checksum_matches'] ?? null);
        $t->same('original_source', $provenance['relationship_role'] ?? null);
        $t->same(false, $provenance['payload_included'] ?? null);
        $t->same(hash('sha256', $payload), $provenance['payload']['sha256'] ?? null);
        $t->true(!array_key_exists('content', $source));
    },
    'keeps outline SE metadata and associated payload out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineStructureElementMetadataPdf): void {
        [$pdf, $payload] = $outlineStructureElementMetadataPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same('Visible tagged outline section', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(!str_contains($plainText, 'Review-only outline structure link'));
        $t->true(!str_contains($plainText, 'Outline Structure Title'));
        $t->true(!str_contains($plainText, 'Accessible Outline Summary'));
        $t->true(!str_contains($plainText, 'outline-section-source.xml'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
