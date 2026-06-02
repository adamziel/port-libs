<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpOutputIntentNameTreePacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-02T21:02:00-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-02T21:05:30-04:00</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

return [
    'keeps name-tree XMP and OutputIntent dictionaries review-only on current xref catalog' => static function (
        TestRunner $t
    ) use ($xmpOutputIntentNameTreePacket): void {
        $documentXmp = $xmpOutputIntentNameTreePacket(
            'Current Document XMP NameTree Boundary Title',
            'Document-level XMP should remain authoritative'
        );
        $nameTreeXmp = $xmpOutputIntentNameTreePacket(
            'Hidden NameTree XMP Title',
            'Name-tree XMP packet is review-only'
        );
        $staleNameTreeXmp = $xmpOutputIntentNameTreePacket(
            'Stale Hidden NameTree XMP Title',
            'Stale name-tree XMP packet must not be selected'
        );

        $documentXmpStream = gzcompress($documentXmp);
        $nameTreeXmpStream = gzcompress($nameTreeXmp);
        $staleNameTreeXmpStream = gzcompress($staleNameTreeXmp);
        $rootProfile = 'Current document root ICC profile bytes';
        $nameTreeProfile = 'Current name-tree ICC profile bytes should stay review metadata';
        $staleProfile = 'Stale name-tree ICC profile bytes should not be selected';
        $rootProfileStream = gzcompress($rootProfile);
        $nameTreeProfileStream = gzcompress($nameTreeProfile);
        $staleProfileStream = gzcompress($staleProfile);
        if (
            !is_string($documentXmpStream)
            || !is_string($nameTreeXmpStream)
            || !is_string($staleNameTreeXmpStream)
            || !is_string($rootProfileStream)
            || !is_string($nameTreeProfileStream)
            || !is_string($staleProfileStream)
        ) {
            throw new RuntimeException('Unable to compress name-tree XMP OutputIntent fixture streams.');
        }

        $script = "app.alert('name-tree metadata review only')";
        $staleScript = "app.alert('stale name-tree metadata')";
        $content = 'BT /F1 12 Tf 72 720 Td (Current XMP OutputIntent NameTree Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale XMP OutputIntent NameTree Body) Tj ET';
        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 14 0 R /OutputIntents [9 0 R] /Names << /JavaScript 20 0 R /IDS 30 0 R /Dests 70 0 R >> >>');
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
        $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
        $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($nameTreeXmpStream) . " >>\nstream\n{$nameTreeXmpStream}\nendstream");
        $addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
        $addObject(8, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($nameTreeProfileStream) . " >>\nstream\n{$nameTreeProfileStream}\nendstream");
        $addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current Document Root sRGB) /Info (Root document PDF/A profile) /DestOutputProfile 7 0 R >>');
        $addObject(13, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (NameTree Review sRGB) /Info (Name-tree attachment-local PDF/A profile) /DestOutputProfile 8 0 R >>');
        $addObject(14, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($documentXmpStream) . " >>\nstream\n{$documentXmpStream}\nendstream");
        $addObject(20, 0, '<< /Kids [21 0 R 22 0 R 20 0 R] >>');
        $addObject(21, 0, '<< /Limits [(metadata) (metadata-z)] /Names [(metadata-action) 40 0 R (z-stale-action) 41 0 R] >>');
        $addObject(22, 0, '<< /Limits [(metadata-close) (metadata-close)] /Names [(metadata-close) 42 0 R] >>');
        $addObject(30, 0, '<< /Limits [(review-id) (review-id)] /Names [(review-id) << /Type /DeveloperExtension /Metadata 5 0 R /OutputIntents [13 0 R] >> (zz-stale-id) 41 0 R] >>');
        $addObject(40, 0, "<< /S /JavaScript /JS ({$script}) /Metadata 5 0 R /OutputIntents [13 0 R] >>");
        $addObject(41, 0, "<< /S /JavaScript /JS ({$staleScript}) /Metadata 50 0 R /OutputIntents [53 0 R] >>");
        $addObject(42, 0, '<< /S /JavaScript /JS <' . strtoupper(bin2hex("\xfe\xff\0a\0p\0p\0.\0a\0l\0e\0r\0t\0(\0'\0m\0e\0t\0a\0d\0a\0t\0a\0-\0c\0l\0o\0s\0e\0'\0)")) . '> /Metadata 5 0 R /OutputIntents [13 0 R] >>');
        $addObject(50, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleNameTreeXmpStream) . " >>\nstream\n{$staleNameTreeXmpStream}\nendstream");
        $addObject(52, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($staleProfileStream) . " >>\nstream\n{$staleProfileStream}\nendstream");
        $addObject(53, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale NameTree Review sRGB) /Info (Stale name-tree attachment-local PDF/A profile) /DestOutputProfile 52 0 R >>');
        $addObject(60, 0, '<< /Title (Current Info Fallback Title) /Author (Current Info Author) /Producer (Current Info Producer) >>');
        $addObject(70, 0, '<< /Names [(Review Start) [3 0 R /FitH 700]] >>');

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
            throw new RuntimeException('Unable to compress name-tree XMP OutputIntent xref stream.');
        }

        $pdf .= "90 0 obj\n"
            . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 50 0 R /OutputIntents [53 0 R] /Names << /JavaScript 80 0 R /IDS 81 0 R >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
            . "40 0 obj\n<< /S /JavaScript /JS ({$staleScript}) /Metadata 50 0 R /OutputIntents [53 0 R] >>\nendobj\n"
            . "60 0 obj\n<< /Title (Stale Info Fallback Title) /Author (Stale Info Author) >>\nendobj\n"
            . "80 0 obj\n<< /Names [(stale-metadata-action) 40 0 R] >>\nendobj\n"
            . "81 0 obj\n<< /Names [(stale-review-id) 41 0 R] >>\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $nameTrees = $metadata['document_name_trees'] ?? [];
        $javaScriptEntry = $nameTrees['trees']['JavaScript']['entries'][0] ?? [];
        $idsEntry = $nameTrees['trees']['IDS']['entries'][0] ?? [];

        $t->same(['xmp', 'info', 'catalog', 'output_intents'], $metadata['source']);
        $t->same('Current Document XMP NameTree Boundary Title', $metadata['title']);
        $t->same('Current Info Author', $metadata['info']['Author']);
        $t->same(['Current Document Root sRGB'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $rootProfile)], $metadata['pdfa']['profile_sha256']);
        $t->same('Current XMP OutputIntent NameTree Body', $plainText);
        $t->same(['JavaScript', 'IDS'], $nameTrees['tree_names'] ?? []);
        $t->same(['metadata-action', 'metadata-close'], $nameTrees['trees']['JavaScript']['names'] ?? []);
        $t->same(['review-id'], $nameTrees['trees']['IDS']['names'] ?? []);

        $t->same('JavaScript', $javaScriptEntry['action_type'] ?? null);
        $t->same(false, $javaScriptEntry['executes_action'] ?? null);
        $t->same(5, $javaScriptEntry['metadata_review']['object_number'] ?? null);
        $t->same(hash('sha256', $nameTreeXmp), $javaScriptEntry['metadata_review']['sha256'] ?? null);
        $t->same(['title', 'description', 'created_at', 'metadata_date'], $javaScriptEntry['metadata_review']['xmp_summary']['field_names'] ?? []);
        $t->same(false, $javaScriptEntry['metadata_review']['xmp_summary']['payload_included'] ?? null);
        $t->same('2026-06-03T01:02:00Z', $javaScriptEntry['metadata_review']['xmp_summary']['dates_utc']['created_at'] ?? null);
        $t->same(1, $javaScriptEntry['output_intents_review']['count'] ?? null);
        $t->same(true, $javaScriptEntry['output_intents_review']['has_pdfa_output_intent'] ?? null);
        $t->same(['NameTree Review sRGB'], $javaScriptEntry['output_intents_review']['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $nameTreeProfile)], $javaScriptEntry['output_intents_review']['profile_sha256'] ?? []);

        $t->same('DeveloperExtension', $idsEntry['type'] ?? null);
        $t->same(5, $idsEntry['metadata_review']['object_number'] ?? null);
        $t->same(['NameTree Review sRGB'], $idsEntry['output_intents_review']['output_condition_identifiers'] ?? []);

        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden NameTree XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Name-tree XMP packet is review-only'));
        $t->true(is_string($encoded) && !str_contains($encoded, $nameTreeProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, $script));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Hidden NameTree XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale NameTree Review sRGB'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-metadata-action'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Info Fallback Title'));
        $t->true(!str_contains($plainText, 'Hidden NameTree XMP Title'));
        $t->true(!str_contains($plainText, 'NameTree Review sRGB'));
        $t->true(!str_contains($plainText, 'Stale XMP OutputIntent NameTree Body'));
    },
];
