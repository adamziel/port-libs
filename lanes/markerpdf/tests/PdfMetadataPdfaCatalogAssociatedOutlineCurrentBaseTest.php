<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfaCatalogAssociatedOutlinePdf = static function (): array {
    $rootXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Catalog AF PDF/A Root Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T21:52:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $fileXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Catalog AF XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Catalog AF XMP is attachment-local</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:MetadataDate>2026-06-02T21:54:00Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';

    $rootXmpStream = gzcompress($rootXmp);
    $fileXmpStream = gzcompress($fileXmp);
    $rootProfile = 'Catalog AF root PDF/A profile bytes';
    $fileProfile = 'Catalog AF attachment-local PDF/A profile bytes';
    $rootProfileStream = gzcompress($rootProfile);
    $fileProfileStream = gzcompress($fileProfile);
    if (!is_string($rootXmpStream) || !is_string($fileXmpStream) || !is_string($rootProfileStream) || !is_string($fileProfileStream)) {
        throw new RuntimeException('Unable to compress catalog associated PDF/A fixture streams.');
    }

    $sourcePayload = '<wp-export><post id="catalog-af-pdfa"/></wp-export>';
    $schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" id="catalog-af-schema"/>';
    $targetPayload = '<wp-page-target outline="catalog-af"/>';
    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $schemaChecksum = strtoupper(hash('md5', $schemaPayload));
    $targetChecksum = strtoupper(hash('md5', $targetPayload));
    $introContent = 'BT /F1 12 Tf 72 720 Td (Catalog AF intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Catalog AF outline target body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R 20 0 R] /Outlines 40 0 R /Names << /Dests 50 0 R >> /OpenAction /CatalogAFTarget >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R /AF [30 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
        . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
        . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Catalog AF Root PDF/A) /Info (Root PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (catalog-source.xml) /Desc (Catalog associated WordPress source) /AFRelationship /Source /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602215200Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Catalog AF Attachment PDF/A) /Info (Attachment-local PDF/A profile) /DestOutputProfile 8 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (catalog-schema.xsd) /Desc (Catalog AF extension schema) /AFRelationship /Schema /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size " . strlen($schemaPayload) . " /CheckSum <{$schemaChecksum}> >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (outline-target-source.xml) /Desc (Outline target page source) /AFRelationship /Supplement /EF << /F 33 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($targetPayload) . " /CheckSum <{$targetChecksum}> >> /Length " . strlen($targetPayload) . " >>\nstream\n{$targetPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Count 1 >>\nendobj\n"
        . "41 0 obj\n<< /Title (Catalog AF Outline Target) /Parent 40 0 R /Dest /CatalogAFTarget >>\nendobj\n"
        . "50 0 obj\n<< /Names [(CatalogAFTarget) [4 0 R /FitH 700]] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootXmp, $fileXmp, $rootProfile, $fileProfile, $sourcePayload, $schemaPayload, $targetPayload];
};

return [
    'summarizes catalog AF PDF/A attachments and keeps outline target page files review-only' => static function (
        TestRunner $t
    ) use ($pdfaCatalogAssociatedOutlinePdf): void {
        [$pdf, $rootXmp, $fileXmp, $rootProfile, $fileProfile, $sourcePayload, $schemaPayload, $targetPayload] = $pdfaCatalogAssociatedOutlinePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $summary = $metadata['pdfa_associated_files'] ?? [];
        $entries = $summary['entries'] ?? [];
        $source = $entries[0] ?? [];
        $schema = $entries[1] ?? [];
        $outline = $navigation['outline'][0] ?? [];
        $targetReview = $outline['target_page_review'] ?? [];

        $t->same(['xmp', 'catalog', 'output_intents'], $metadata['source']);
        $t->same('Catalog AF PDF/A Root Title', $metadata['title']);
        $t->same('2026-06-02T21:52:00Z', $metadata['xmp']['created_at']);
        $t->same(['Catalog AF Root PDF/A'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $rootProfile)], $metadata['pdfa']['profile_sha256']);
        $t->same(false, isset($metadata['pdfa_associated_name_tree']));

        $t->same('pdfa_associated_files', $summary['source'] ?? null);
        $t->same(true, $summary['review_only'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(['Catalog AF Root PDF/A'], $summary['root_output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $rootProfile)], $summary['root_profile_sha256'] ?? []);
        $t->same(2, $summary['count'] ?? null);
        $t->same(['catalog-source.xml', 'catalog-schema.xsd'], $summary['filenames'] ?? []);
        $t->same(['Source', 'Schema'], $summary['relationships'] ?? []);
        $t->same(['original_source', 'schema_definition'], $summary['relationship_roles'] ?? []);
        $t->same(true, $summary['has_attachment_pdfa_output_intent'] ?? null);
        $t->same(['Catalog AF Attachment PDF/A'], $summary['attachment_output_condition_identifiers'] ?? []);

        $t->same('catalog_associated_files', $source['source'] ?? null);
        $t->same(0, $source['associated_file_index'] ?? null);
        $t->same('catalog-source.xml', $source['filename'] ?? null);
        $t->same('Source', $source['relationship'] ?? null);
        $t->same('original_source', $source['relationship_role'] ?? null);
        $t->same(hash('sha256', $sourcePayload), $source['payload']['sha256'] ?? null);
        $t->same(true, $source['payload']['checksum_matches'] ?? null);
        $t->same(6, $source['xmp_metadata']['object_number'] ?? null);
        $t->same(hash('sha256', $fileXmp), $source['xmp_metadata']['sha256'] ?? null);
        $t->same(['title', 'description', 'metadata_date'], $source['xmp_metadata']['xmp_summary']['field_names'] ?? []);
        $t->same(false, $source['xmp_metadata']['xmp_summary']['payload_included'] ?? null);
        $t->same(['Catalog AF Attachment PDF/A'], $source['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $fileProfile)], $source['attachment_pdfa_output_intents']['profile_sha256'] ?? []);

        $t->same('catalog_associated_files', $schema['source'] ?? null);
        $t->same(1, $schema['associated_file_index'] ?? null);
        $t->same('catalog-schema.xsd', $schema['filename'] ?? null);
        $t->same('Schema', $schema['relationship'] ?? null);
        $t->same('schema_definition', $schema['relationship_role'] ?? null);
        $t->same('application/xml', $schema['payload']['mime_type'] ?? null);
        $t->same(hash('sha256', $schemaPayload), $schema['payload']['sha256'] ?? null);
        $t->same(false, array_key_exists('attachment_pdfa_output_intents', $schema));

        $t->same(['outline', 'open_action', 'page_review'], $navigation['source']);
        $t->same('Catalog AF Outline Target', $outline['title'] ?? null);
        $t->same(1, $outline['page'] ?? null);
        $t->same('CatalogAFTarget', $outline['destination'] ?? null);
        $t->same('FitH', $outline['view_mode'] ?? null);
        $t->same('outline-target-source.xml', $targetReview['page_associated_files'][0]['filename'] ?? null);
        $t->same('Supplement', $targetReview['page_associated_files'][0]['relationship'] ?? null);
        $t->same(hash('sha256', $targetPayload), $targetReview['page_associated_files'][0]['content_sha256'] ?? null);
        $t->same(true, $targetReview['page_associated_files'][0]['checksum_matches'] ?? null);

        $t->contains('Catalog AF intro body', $plainText);
        $t->contains('Catalog AF outline target body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $schemaPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $targetPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $fileProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Catalog AF XMP Title'));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'catalog-af-schema'));
        $t->true(!str_contains($plainText, 'Catalog AF Outline Target'));
        $t->true(!str_contains($plainText, 'Catalog AF Attachment PDF/A'));
    },
];
