<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pieceInfoAssociatedXmpPacket = static function (array $overrides = []): string {
    $title = $overrides['title'] ?? 'Current Associated XMP Title';
    $description = $overrides['description'] ?? 'Current associated XMP review metadata';
    $createDate = $overrides['create_date'] ?? '2026-06-02T19:07:22-04:00';
    $modifyDate = $overrides['modify_date'] ?? '2026-06-02T20:08:23-04:00';
    $metadataDate = $overrides['metadata_date'] ?? '2026-06-03T01:09:24Z';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Import Editor</rdf:li><rdf:li>Metadata Reviewer</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>source</rdf:li><rdf:li>associated-xmp</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Current Associated Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Current Associated Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($createDate, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:ModifyDate>' . htmlspecialchars($modifyDate, ENT_XML1) . '</xmp:ModifyDate>'
        . '<xmp:MetadataDate>' . htmlspecialchars($metadataDate, ENT_XML1) . '</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pieceInfoAssociatedXmpCurrentBasePdf = static function () use ($pieceInfoAssociatedXmpPacket): array {
    $associatedXmp = $pieceInfoAssociatedXmpPacket();
    $pieceInfoPrivateXmp = $pieceInfoAssociatedXmpPacket([
        'title' => 'Current PieceInfo Private XMP Title',
        'description' => 'PieceInfo private XMP remains review-only',
        'create_date' => '2026-06-02T21:10:25+02:00',
        'modify_date' => '2026-06-02T22:11:26+02:00',
        'metadata_date' => '2026-06-02T23:12:27+02:00',
    ]);
    $staleAssociatedXmp = $pieceInfoAssociatedXmpPacket([
        'title' => 'Stale Associated XMP Title',
        'description' => 'Stale associated XMP must not win',
    ]);
    $stalePieceInfoXmp = $pieceInfoAssociatedXmpPacket([
        'title' => 'Stale PieceInfo Private XMP Title',
        'description' => 'Stale PieceInfo private XMP must not win',
    ]);
    $associatedXmpStream = gzcompress($associatedXmp);
    $pieceInfoPrivateXmpStream = gzcompress($pieceInfoPrivateXmp);
    $staleAssociatedXmpStream = gzcompress($staleAssociatedXmp);
    $stalePieceInfoXmpStream = gzcompress($stalePieceInfoXmp);
    if (
        !is_string($associatedXmpStream)
        || !is_string($pieceInfoPrivateXmpStream)
        || !is_string($staleAssociatedXmpStream)
        || !is_string($stalePieceInfoXmpStream)
    ) {
        throw new RuntimeException('Unable to compress associated XMP current-base fixture streams.');
    }

    $sourcePayload = '<wp-export><post id="xmp-current"/></wp-export>';
    $staleSourcePayload = '<wp-export><post id="stale-xmp"/></wp-export>';
    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $content = 'BT /F1 12 Tf 72 720 Td (Current Associated XMP Body) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Associated XMP Body) Tj ET';
    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /AF [10 0 R] >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($associatedXmpStream) . " >>\nstream\n{$associatedXmpStream}\nendstream");
    $addObject(6, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($pieceInfoPrivateXmpStream) . " >>\nstream\n{$pieceInfoPrivateXmpStream}\nendstream");
    $addObject(10, '<< /Type /Filespec /F (legacy-xmp-source.xml) /UF (xmp-source.xml) /Desc (Current FileSpec XMP source) /AFRelationship /Source /Metadata 5 0 R /PieceInfo << /WPImport << /LastModified (D:20260602190722Z) /Private << /ManifestId (piece-xmp-current) /Metadata 6 0 R >> >> >> /EF << /F 11 0 R >> >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 18; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 17)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 17 ? $xrefOffset : $offsets[$objectNumber], 0);
    }
    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress associated XMP xref stream.');
    }

    $pdf .= "17 0 obj\n"
        . '<< /Type /XRef /Size 18 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /AF [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleAssociatedXmpStream) . " >>\nstream\n{$staleAssociatedXmpStream}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($stalePieceInfoXmpStream) . " >>\nstream\n{$stalePieceInfoXmpStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (stale-xmp-source.xml) /Desc (Stale FileSpec XMP source) /AFRelationship /Source /Metadata 5 0 R /PieceInfo << /WPImport << /LastModified (D:20260602200000Z) /Private << /ManifestId (stale-piece-xmp) /Metadata 6 0 R >> >> >> /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n";

    return [$pdf, $associatedXmp, $pieceInfoPrivateXmp, $sourcePayload, $staleSourcePayload];
};

return [
    'summarizes current associated FileSpec and PieceInfo XMP without exposing text values' => static function (
        TestRunner $t
    ) use ($pieceInfoAssociatedXmpCurrentBasePdf): void {
        [$pdf, $associatedXmp, $pieceInfoPrivateXmp, $sourcePayload, $staleSourcePayload] = $pieceInfoAssociatedXmpCurrentBasePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $file = ($metadata['associated_files'] ?? [])[0] ?? [];
        $provenance = $file['provenance_review'] ?? [];
        $xmpSummary = $provenance['xmp_metadata']['xmp_summary'] ?? [];
        $pieceInfoXmp = $provenance['piece_info_xmp_metadata'] ?? [];
        $pieceInfoXmpSummary = $pieceInfoXmp['metadata_streams'][0]['xmp_summary'] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('Current Associated XMP Body', $plainText);
        $t->same('xmp-source.xml', $file['filename']);
        $t->same('legacy-xmp-source.xml', $file['platform_filename']);
        $t->same('Current FileSpec XMP source', $file['description']);
        $t->same('piece-xmp-current', $file['piece_info']['WPImport']['private']['ManifestId']);
        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum', 'filespec_metadata_stream', 'filespec_pieceinfo_metadata_stream'], $provenance['sources']);

        $t->same(5, $provenance['xmp_metadata']['object_number']);
        $t->same(hash('sha256', $associatedXmp), $provenance['xmp_metadata']['sha256']);
        $t->same('xmp_packet_review', $xmpSummary['source']);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'modified_at', 'metadata_date', 'authors', 'keywords'], $xmpSummary['field_names']);
        $t->same(2, $xmpSummary['author_count']);
        $t->same(2, $xmpSummary['keyword_count']);
        $t->same('UTF-8', $xmpSummary['packet_encoding']);
        $t->same('2026-06-02T23:07:22Z', $xmpSummary['dates_utc']['created_at']);
        $t->same('2026-06-03T00:08:23Z', $xmpSummary['dates_utc']['modified_at']);
        $t->same('2026-06-03T01:09:24Z', $xmpSummary['dates_utc']['metadata_date']);
        $t->same(false, $xmpSummary['payload_included']);
        $t->same(true, $xmpSummary['text_values_redacted']);

        $t->same('filespec_pieceinfo_metadata_stream', $pieceInfoXmp['source']);
        $t->same(['WPImport'], $pieceInfoXmp['applications']);
        $t->same(1, $pieceInfoXmp['count']);
        $t->same('WPImport', $pieceInfoXmp['metadata_streams'][0]['application']);
        $t->same('D:20260602190722Z', $pieceInfoXmp['metadata_streams'][0]['last_modified']);
        $t->same(6, $pieceInfoXmp['metadata_streams'][0]['object_number']);
        $t->same(hash('sha256', $pieceInfoPrivateXmp), $pieceInfoXmp['metadata_streams'][0]['sha256']);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'modified_at', 'metadata_date', 'authors', 'keywords'], $pieceInfoXmpSummary['field_names']);
        $t->same('2026-06-02T19:10:25Z', $pieceInfoXmpSummary['dates_utc']['created_at']);
        $t->same('2026-06-02T20:11:26Z', $pieceInfoXmpSummary['dates_utc']['modified_at']);
        $t->same('2026-06-02T21:12:27Z', $pieceInfoXmpSummary['dates_utc']['metadata_date']);
        $t->same(false, $pieceInfoXmpSummary['payload_included']);

        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Associated XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current PieceInfo Private XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Associated XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale PieceInfo Private XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleSourcePayload));
        $t->true(!str_contains($plainText, 'Stale Associated XMP Body'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
