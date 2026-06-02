<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpAssociatedSchemaPacket = static function (string $title, bool $includeSchema): string {
    $schema = $includeSchema
        ? '<pdfaExtension:schemas>'
            . '<rdf:Bag>'
            . '<rdf:li rdf:parseType="Resource">'
            . '<pdfaSchema:schema>WordPress Import Extension Schema</pdfaSchema:schema>'
            . '<pdfaSchema:namespaceURI>https://example.org/ns/wp-import/1.0/</pdfaSchema:namespaceURI>'
            . '<pdfaSchema:prefix>wpimport</pdfaSchema:prefix>'
            . '<pdfaSchema:property>'
            . '<rdf:Seq>'
            . '<rdf:li rdf:parseType="Resource">'
            . '<pdfaProperty:name>wpPostId</pdfaProperty:name>'
            . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
            . '<pdfaProperty:category>external</pdfaProperty:category>'
            . '<pdfaProperty:description>Original WordPress post identifier</pdfaProperty:description>'
            . '</rdf:li>'
            . '<rdf:li rdf:parseType="Resource">'
            . '<pdfaProperty:name>wpSourceChecksum</pdfaProperty:name>'
            . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
            . '<pdfaProperty:category>external</pdfaProperty:category>'
            . '<pdfaProperty:description>Checksum for the source export attachment</pdfaProperty:description>'
            . '</rdf:li>'
            . '</rdf:Seq>'
            . '</pdfaSchema:property>'
            . '</rdf:li>'
            . '</rdf:Bag>'
            . '</pdfaExtension:schemas>'
        : '';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"'
        . ' xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"'
        . ' xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T22:28:00-04:00</xmp:CreateDate>'
        . $schema
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

return [
    'correlates root XMP extension schema declarations with current EmbeddedFiles name-tree schema attachments' => static function (
        TestRunner $t
    ) use ($xmpAssociatedSchemaPacket): void {
        $rootXmp = $xmpAssociatedSchemaPacket('Current XMP Associated Schema Title', true);
        $fileXmp = $xmpAssociatedSchemaPacket('Hidden NameTree Schema FileSpec XMP Title', false);
        $staleRootXmp = $xmpAssociatedSchemaPacket('Stale XMP Associated Schema Title', true);
        $rootXmpStream = gzcompress($rootXmp);
        $fileXmpStream = gzcompress($fileXmp);
        $staleRootXmpStream = gzcompress($staleRootXmp);
        $rootProfile = 'Current XMP schema root ICC bytes';
        $fileProfile = 'Name-tree schema attachment ICC bytes should stay review-only';
        $staleProfile = 'Stale XMP schema ICC bytes should not be selected';
        $rootProfileStream = gzcompress($rootProfile);
        $fileProfileStream = gzcompress($fileProfile);
        $staleProfileStream = gzcompress($staleProfile);
        if (
            !is_string($rootXmpStream)
            || !is_string($fileXmpStream)
            || !is_string($staleRootXmpStream)
            || !is_string($rootProfileStream)
            || !is_string($fileProfileStream)
            || !is_string($staleProfileStream)
        ) {
            throw new RuntimeException('Unable to compress XMP associated schema fixture streams.');
        }

        $sourcePayload = '<wp-export><post id="xmp-associated-schema"/></wp-export>';
        $schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" targetNamespace="https://example.org/ns/wp-import/1.0/"/>';
        $staleSchemaPayload = '<xsd:schema id="stale-associated-schema"/>';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
        $schemaChecksum = strtoupper(hash('md5', $schemaPayload));
        $content = 'BT /F1 12 Tf 72 720 Td (Current XMP Associated Schema Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale XMP Associated Schema Body) Tj ET';

        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /Names << /EmbeddedFiles 20 0 R >> >>');
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
        $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
        $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream");
        $addObject(6, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream");
        $addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
        $addObject(8, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream");
        $addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current XMP Schema Root PDF/A) /Info (Current root PDF/A profile) /DestOutputProfile 7 0 R >>');
        $addObject(10, 0, '<< /Type /Filespec /F (source.xml) /Desc (Current XMP schema source export) /AFRelationship /Source /EF << /F 11 0 R >> >>');
        $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
        $addObject(13, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Schema Attachment PDF/A) /Info (Attachment-local schema PDF/A profile) /DestOutputProfile 8 0 R >>');
        $addObject(14, 0, '<< /Type /Filespec /F (schema.xsd) /Desc (XMP extension schema attachment) /AFRelationship /Schema /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 15 0 R >> >>');
        $addObject(15, 0, '<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size ' . strlen($schemaPayload) . ' /CheckSum <' . $schemaChecksum . "> >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream");
        $addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
        $addObject(21, 0, '<< /Limits [(source.xml) (source.xml)] /Names [(source.xml) 10 0 R (zz-stale-schema.xsd) 50 0 R] >>');
        $addObject(22, 0, '<< /Limits [(schema.xsd) (schema.xsd)] /Names [(schema.xsd) 14 0 R] >>');
        $addObject(50, 0, '<< /Type /Filespec /F (zz-stale-schema.xsd) /Desc (Stale out-of-limits schema) /AFRelationship /Schema /EF << /F 51 0 R >> >>');
        $addObject(51, 0, '<< /Type /EmbeddedFile /Subtype /application#2Fxml /Length ' . strlen($staleSchemaPayload) . " >>\nstream\n{$staleSchemaPayload}\nendstream");
        $addObject(60, 0, '<< /Title (Current Info Associated Schema Title) /Producer (Current Info Producer) >>');

        $xrefOffset = strlen($pdf);
        $rows = '';
        for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
            if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
                $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
                continue;
            }

            $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
        }

        $compressedXref = gzcompress($rows);
        if (!is_string($compressedXref)) {
            throw new RuntimeException('Unable to compress XMP associated schema xref stream.');
        }

        $pdf .= "90 0 obj\n"
            . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 80 0 R /OutputIntents [83 0 R] /Names << /EmbeddedFiles 70 0 R >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
            . "70 0 obj\n<< /Names [(stale-schema.xsd) 50 0 R] >>\nendobj\n"
            . "80 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleRootXmpStream) . " >>\nstream\n{$staleRootXmpStream}\nendstream\nendobj\n"
            . "82 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($staleProfileStream) . " >>\nstream\n{$staleProfileStream}\nendstream\nendobj\n"
            . "83 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale XMP Schema PDF/A) /DestOutputProfile 82 0 R >>\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $schemas = $metadata['pdfa_extension_schemas'] ?? [];
        $schemaSummary = $metadata['pdfa_xmp_associated_schema'] ?? [];
        $schemaFiles = $schemaSummary['associated_schema_files'] ?? [];

        $t->same(['xmp', 'info', 'catalog', 'output_intents'], $metadata['source']);
        $t->same('Current XMP Associated Schema Title', $metadata['title']);
        $t->same('2026-06-03T02:28:00Z', $metadata['created_at_utc']);
        $t->same('Current XMP Associated Schema Body', $plainText);
        $t->same(['Current XMP Schema Root PDF/A'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $rootProfile)], $metadata['pdfa']['profile_sha256']);

        $t->same(1, count($schemas));
        $t->same('WordPress Import Extension Schema', $schemas[0]['schema'] ?? null);
        $t->same('https://example.org/ns/wp-import/1.0/', $schemas[0]['namespace_uri'] ?? null);
        $t->same('wpimport', $schemas[0]['prefix'] ?? null);
        $t->same(['wpPostId', 'wpSourceChecksum'], $schemas[0]['property_names'] ?? []);
        $t->same('Text', $schemas[0]['properties'][0]['value_type'] ?? null);
        $t->same('external', $schemas[0]['properties'][0]['category'] ?? null);

        $t->same('pdfa_xmp_associated_schema', $schemaSummary['source'] ?? null);
        $t->same(true, $schemaSummary['review_only'] ?? null);
        $t->same(false, $schemaSummary['payload_included'] ?? null);
        $t->same(1, $schemaSummary['xmp_schema_count'] ?? null);
        $t->same(1, $schemaSummary['associated_schema_file_count'] ?? null);
        $t->same(['WordPress Import Extension Schema'], $schemaSummary['xmp_schema_names'] ?? []);
        $t->same(['https://example.org/ns/wp-import/1.0/'], $schemaSummary['xmp_schema_namespaces'] ?? []);
        $t->same(['wpimport'], $schemaSummary['xmp_schema_prefixes'] ?? []);
        $t->same(['wpPostId', 'wpSourceChecksum'], $schemaSummary['xmp_schema_property_names'] ?? []);
        $t->same(['schema.xsd'], $schemaSummary['associated_schema_file_names'] ?? []);
        $t->same(['catalog_names_embedded_files'], $schemaSummary['associated_schema_file_sources'] ?? []);

        $t->same(1, count($schemaFiles));
        $t->same('catalog_names_embedded_files', $schemaFiles[0]['source'] ?? null);
        $t->same('schema.xsd', $schemaFiles[0]['name_tree_name'] ?? null);
        $t->same('Schema', $schemaFiles[0]['relationship'] ?? null);
        $t->same('schema_definition', $schemaFiles[0]['relationship_role'] ?? null);
        $t->same(hash('sha256', $schemaPayload), $schemaFiles[0]['payload']['sha256'] ?? null);
        $t->same(true, $schemaFiles[0]['payload']['checksum_matches'] ?? null);
        $t->same(['Schema Attachment PDF/A'], $schemaFiles[0]['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? []);
        $t->same(6, $schemaFiles[0]['xmp_metadata']['object_number'] ?? null);
        $t->same(['title', 'created_at'], $schemaFiles[0]['xmp_metadata']['xmp_summary']['field_names'] ?? []);
        $t->same(false, $schemaFiles[0]['xmp_metadata']['xmp_summary']['payload_included'] ?? null);

        $t->same(['source.xml', 'schema.xsd'], $metadata['pdfa_associated_name_tree']['names'] ?? []);
        $t->same(['Source', 'Schema'], $metadata['pdfa_associated_name_tree']['relationships'] ?? []);
        $t->same(true, $metadata['pdfa_associated_name_tree']['has_attachment_pdfa_output_intent'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $schemaPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $fileProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden NameTree Schema FileSpec XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'zz-stale-schema.xsd'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-associated-schema'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale XMP Associated Schema Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale XMP Schema PDF/A'));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'schema.xsd'));
        $t->true(!str_contains($plainText, 'Stale XMP Associated Schema Body'));
    },
];
