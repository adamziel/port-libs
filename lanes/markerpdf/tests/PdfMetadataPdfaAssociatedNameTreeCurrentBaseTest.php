<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfaAssociatedNameTreeXmpPacket = static function (): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">NameTree Associated XMP Hidden Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T20:40:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

return [
    'summarizes PDF/A associated EmbeddedFiles name-tree rows on current xref catalog' => static function (
        TestRunner $t
    ) use ($pdfaAssociatedNameTreeXmpPacket): void {
        $rootProfile = 'Current PDF/A name-tree root ICC bytes';
        $fileProfile = 'Current PDF/A name-tree associated ICC bytes';
        $sourcePayload = '<wp-export><post id="pdfa-nametree-current"/></wp-export>';
        $schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"/>';
        $staleSourcePayload = '<wp-export><post id="stale-pdfa-nametree"/></wp-export>';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
        $xmpPacket = $pdfaAssociatedNameTreeXmpPacket();

        $rootProfileStream = gzcompress($rootProfile);
        $fileProfileStream = gzcompress($fileProfile);
        $xmpStream = gzcompress($xmpPacket);
        if (!is_string($rootProfileStream) || !is_string($fileProfileStream) || !is_string($xmpStream)) {
            throw new RuntimeException('Unable to compress PDF/A associated name-tree fixture streams.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (Current PDF/A NameTree Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale PDF/A NameTree Body) Tj ET';
        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] /Names << /EmbeddedFiles 20 0 R >> >>');
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
        $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
        $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($xmpStream) . " >>\nstream\n{$xmpStream}\nendstream");
        $addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
        $addObject(8, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream");
        $addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree Root PDF/A) /Info (Current root PDF/A profile) /DestOutputProfile 7 0 R >>');
        $addObject(10, 0, '<< /Type /Filespec /F (legacy-source.xml) /UF (migrate-source.xml) /Desc (Current PDF/A source export) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>');
        $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602204000Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
        $addObject(13, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree Attachment PDF/A) /Info (Attachment-local PDF/A profile) /DestOutputProfile 8 0 R >>');
        $addObject(14, 0, '<< /Type /Filespec /F (schema.xsd) /Desc (PDF/A extension schema) /AFRelationship /Schema /EF << /F 15 0 R >> >>');
        $addObject(15, 0, '<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size ' . strlen($schemaPayload) . " >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream");
        $addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
        $addObject(21, 0, '<< /Limits [(a) (mzz)] /Names [(migrate-source.xml) 10 0 R (z-out-of-limits.xml) 50 0 R] >>');
        $addObject(22, 0, '<< /Limits [(n) (z)] /Names [(schema.xsd) 14 0 R] >>');
        $addObject(50, 0, '<< /Type /Filespec /F (z-out-of-limits.xml) /Desc (Stale out-of-limits source) /AFRelationship /Source /EF << /F 51 0 R >> >>');
        $addObject(51, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream");
        $addObject(60, 0, '<< /Title (Current PDF/A NameTree Title) /Producer (Current PDF/A NameTree Producer) >>');

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
            throw new RuntimeException('Unable to compress PDF/A associated name-tree xref stream.');
        }

        $pdf .= "90 0 obj\n"
            . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] /Names << /EmbeddedFiles 70 0 R >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (stale-source.xml) /Desc (Stale PDF/A source export) /AFRelationship /Source /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale NameTree Attachment PDF/A) /Info (Stale attachment-local PDF/A profile) /DestOutputProfile 8 0 R >>\nendobj\n"
            . "60 0 obj\n<< /Title (Stale PDF/A NameTree Title) /Producer (Stale Producer) >>\nendobj\n"
            . "70 0 obj\n<< /Names [(stale-detached.xml) 10 0 R] >>\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $summary = $metadata['pdfa_associated_name_tree'] ?? [];
        $entries = $summary['entries'] ?? [];
        $source = $entries[0] ?? [];
        $schema = $entries[1] ?? [];

        $t->same(['info', 'catalog', 'output_intents'], $metadata['source']);
        $t->same('Current PDF/A NameTree Title', $metadata['title']);
        $t->same('Current PDF/A NameTree Body', $plainText);
        $t->same(['Current NameTree Root PDF/A'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $rootProfile)], $metadata['pdfa']['profile_sha256']);

        $t->same('pdfa_associated_name_tree', $summary['source'] ?? null);
        $t->same(true, $summary['review_only'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(['Current NameTree Root PDF/A'], $summary['root_output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $rootProfile)], $summary['root_profile_sha256'] ?? []);
        $t->same(2, $summary['count'] ?? null);
        $t->same(['migrate-source.xml', 'schema.xsd'], $summary['names'] ?? []);
        $t->same(['Source', 'Schema'], $summary['relationships'] ?? []);
        $t->same(['original_source', 'schema_definition'], $summary['relationship_roles'] ?? []);
        $t->same(true, $summary['has_attachment_pdfa_output_intent'] ?? null);

        $t->same('migrate-source.xml', $source['name_tree_name'] ?? null);
        $t->same('migrate-source.xml', $source['filename'] ?? null);
        $t->same('Source', $source['relationship'] ?? null);
        $t->same('original_source', $source['relationship_role'] ?? null);
        $t->same(true, $source['payload']['size_matches_declared'] ?? null);
        $t->same(hash('sha256', $sourcePayload), $source['payload']['sha256'] ?? null);
        $t->same(5, $source['xmp_metadata']['object_number'] ?? null);
        $t->same(hash('sha256', $xmpPacket), $source['xmp_metadata']['sha256'] ?? null);
        $t->same(['Current NameTree Attachment PDF/A'], $source['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $fileProfile)], $source['attachment_pdfa_output_intents']['profile_sha256'] ?? []);

        $t->same('schema.xsd', $schema['name_tree_name'] ?? null);
        $t->same('Schema', $schema['relationship'] ?? null);
        $t->same('schema_definition', $schema['relationship_role'] ?? null);
        $t->same('application/xml', $schema['payload']['mime_type'] ?? null);
        $t->same(false, array_key_exists('attachment_pdfa_output_intents', $schema));

        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $schemaPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $fileProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NameTree Associated XMP Hidden Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'z-out-of-limits.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-detached.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale PDF/A'));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Stale PDF/A NameTree Body'));
    },
];
