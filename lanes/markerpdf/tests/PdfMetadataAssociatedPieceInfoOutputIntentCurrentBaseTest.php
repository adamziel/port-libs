<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$associatedPieceInfoOutputIntentCurrentBasePdf = static function (): array {
    $rootProfile = 'Associated PieceInfo root PDF/A profile bytes';
    $pieceProfile = 'Associated PieceInfo attachment PDF/A profile bytes';
    $sourcePayload = '<wp-export><post id="associated-pieceinfo-outputintent"/></wp-export>';
    $privatePayload = 'BT /F1 12 Tf 72 720 Td (Associated PieceInfo Private OutputIntent Leak) Tj ET';
    $staleProfile = 'Stale Associated PieceInfo attachment PDF/A profile bytes';
    $staleSourcePayload = '<wp-export><post id="stale-associated-pieceinfo-outputintent"/></wp-export>';
    $stalePrivatePayload = 'BT /F1 12 Tf 72 720 Td (Stale Associated PieceInfo Private Leak) Tj ET';

    $rootProfileStream = gzcompress($rootProfile);
    $pieceProfileStream = gzcompress($pieceProfile);
    $privateStream = gzcompress($privatePayload);
    $staleProfileStream = gzcompress($staleProfile);
    $stalePrivateStream = gzcompress($stalePrivatePayload);
    if (
        !is_string($rootProfileStream)
        || !is_string($pieceProfileStream)
        || !is_string($privateStream)
        || !is_string($staleProfileStream)
        || !is_string($stalePrivateStream)
    ) {
        throw new RuntimeException('Unable to compress associated PieceInfo OutputIntent fixture streams.');
    }

    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $privateChecksum = strtoupper(hash('md5', $privatePayload));
    $content = 'BT /F1 12 Tf 72 720 Td (Current Associated PieceInfo OutputIntent Body) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Associated PieceInfo OutputIntent Body) Tj ET';

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /OutputIntents [9 0 R] /AF [10 0 R] >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(7, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
    $addObject(8, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($pieceProfileStream) . " >>\nstream\n{$pieceProfileStream}\nendstream");
    $addObject(9, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated PieceInfo Root PDF/A) /Info (Root associated PieceInfo PDF/A) /DestOutputProfile 7 0 R >>');
    $addObject(10, '<< /Type /Filespec /F (legacy-associated-piece.xml) /UF (associated-piece.xml) /Desc (Associated PieceInfo WordPress source) /AFRelationship /Source /PieceInfo << /WPImport << /LastModified (D:20260602224600Z) /Private << /ManifestId (associated-piece-outputintent-current) /OutputIntents [13 0 R << /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (Associated PieceInfo Proof Intent) >>] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602224610Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
    $addObject(13, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated PieceInfo Attachment PDF/A) /Info (PieceInfo private attachment PDF/A) /DestOutputProfile 8 0 R >>');
    $addObject(16, '<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Params << /Size ' . strlen($privatePayload) . ' /CheckSum <' . $privateChecksum . "> >> /Length " . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 31; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 30)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 30 ? $xrefOffset : $offsets[$objectNumber], 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress associated PieceInfo OutputIntent xref stream.');
    }

    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /OutputIntents [9 0 R] /AF [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($staleProfileStream) . " >>\nstream\n{$staleProfileStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (stale-associated-piece.xml) /Desc (Stale associated PieceInfo source) /AFRelationship /Source /PieceInfo << /WPImport << /LastModified (D:20260602230000Z) /Private << /ManifestId (stale-associated-piece-outputintent) /OutputIntents [13 0 R] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale Associated PieceInfo Attachment PDF/A) /Info (Stale piece intent) /DestOutputProfile 8 0 R >>\nendobj\n"
        . "16 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Length " . strlen($stalePrivateStream) . " >>\nstream\n{$stalePrivateStream}\nendstream\nendobj\n";

    return [$pdf, $rootProfile, $pieceProfile, $sourcePayload, $privatePayload, $staleProfile, $staleSourcePayload, $stalePrivatePayload];
};

return [
    'summarizes catalog AF PieceInfo private OutputIntents in PDF/A associated-file metadata' => static function (
        TestRunner $t
    ) use ($associatedPieceInfoOutputIntentCurrentBasePdf): void {
        [$pdf, $rootProfile, $pieceProfile, $sourcePayload, $privatePayload, $staleProfile, $staleSourcePayload, $stalePrivatePayload] = $associatedPieceInfoOutputIntentCurrentBasePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $associatedFile = ($metadata['associated_files'] ?? [])[0] ?? [];
        $provenance = $associatedFile['provenance_review'] ?? [];
        $summary = $metadata['pdfa_associated_files'] ?? [];
        $entry = $summary['entries'][0] ?? [];
        $pieceOutputIntents = $entry['piece_info_pdfa_output_intents'] ?? [];
        $piecePrivateStreams = $entry['piece_info_private_streams'] ?? [];

        $t->same(['catalog', 'output_intents'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('Current Associated PieceInfo OutputIntent Body', $plainText);
        $t->same(['Associated PieceInfo Root PDF/A'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $rootProfile)], $metadata['pdfa']['profile_sha256']);

        $t->same('associated-piece.xml', $associatedFile['filename'] ?? null);
        $t->same('legacy-associated-piece.xml', $associatedFile['platform_filename'] ?? null);
        $t->same('Source', $associatedFile['relationship'] ?? null);
        $t->same(true, $associatedFile['checksum_matches'] ?? null);
        $t->same('associated-piece-outputintent-current', $associatedFile['piece_info']['WPImport']['private']['ManifestId'] ?? null);
        $t->same('Associated PieceInfo Attachment PDF/A', $associatedFile['piece_info']['WPImport']['private']['OutputIntents'][0]['OutputConditionIdentifier'] ?? null);
        $t->same(16, $associatedFile['piece_info']['WPImport']['private_streams'][0]['object'] ?? null);
        $t->same(hash('sha256', $privatePayload), $associatedFile['piece_info']['WPImport']['private_streams'][0]['content_sha256'] ?? null);

        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum', 'filespec_pieceinfo_private_streams', 'filespec_pieceinfo_output_intents'], $provenance['sources'] ?? []);
        $t->same(['Associated PieceInfo Attachment PDF/A'], $provenance['piece_info_pdfa_output_intents']['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $pieceProfile)], $provenance['piece_info_pdfa_output_intents']['profile_sha256'] ?? []);

        $t->same('pdfa_associated_files', $summary['source'] ?? null);
        $t->same(true, $summary['has_attachment_pdfa_output_intent'] ?? null);
        $t->same(['Associated PieceInfo Attachment PDF/A'], $summary['attachment_output_condition_identifiers'] ?? []);
        $t->same('associated-piece.xml', $entry['filename'] ?? null);
        $t->same(['Associated PieceInfo Attachment PDF/A'], $pieceOutputIntents['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $pieceProfile)], $pieceOutputIntents['profile_sha256'] ?? []);
        $t->same('WPImport', $pieceOutputIntents['entries'][0]['application'] ?? null);
        $t->same('D:20260602224600Z', $pieceOutputIntents['entries'][0]['last_modified'] ?? null);
        $t->same('Associated PieceInfo Proof Intent', $pieceOutputIntents['entries'][0]['output_intents']['intents'][1]['output_condition_identifier'] ?? null);
        $t->same(16, $piecePrivateStreams['streams'][0]['object'] ?? null);
        $t->same(true, $piecePrivateStreams['streams'][0]['checksum_matches'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $privatePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $pieceProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleSourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePrivatePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Associated PieceInfo Private OutputIntent Leak'));
        $t->true(!str_contains($plainText, 'Stale Associated PieceInfo OutputIntent Body'));
        $t->true(!str_contains($plainText, 'Stale Associated PieceInfo Attachment PDF/A'));
    },
];
