<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'preserves name-tree FileSpec PieceInfo and OutputIntent review on current xref catalog' => static function (TestRunner $t): void {
        $rootProfile = 'Current name-tree root ICC bytes';
        $fileProfile = 'Current name-tree FileSpec ICC bytes';
        $sourcePayload = '<wp-export><post id="192222"/></wp-export>';
        $previewPayload = 'preview-bytes';
        $privatePayload = 'BT /F1 12 Tf 72 720 Td (NameTree PieceInfo Private Leak) Tj ET';
        $staleSourcePayload = '<wp-export><post id="stale-nametree-pieceinfo"/></wp-export>';
        $stalePrivatePayload = 'BT /F1 12 Tf 72 720 Td (Stale NameTree PieceInfo Private Leak) Tj ET';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));

        $rootProfileStream = gzcompress($rootProfile);
        $fileProfileStream = gzcompress($fileProfile);
        $privateStream = gzcompress($privatePayload);
        $stalePrivateStream = gzcompress($stalePrivatePayload);
        if (
            !is_string($rootProfileStream)
            || !is_string($fileProfileStream)
            || !is_string($privateStream)
            || !is_string($stalePrivateStream)
        ) {
            throw new RuntimeException('Unable to compress name-tree PieceInfo fixture streams.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (Current NameTree PieceInfo Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale NameTree PieceInfo Body) Tj ET';
        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /OutputIntents [9 0 R] /Names << /EmbeddedFiles 20 0 R >> >>');
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
        $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
        $addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
        $addObject(8, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream");
        $addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree Root sRGB) /Info (Current name-tree root PDF/A) /DestOutputProfile 7 0 R >>');
        $addObject(10, 0, '<< /Type /Filespec /F (legacy-migrate-source.xml) /UF (migrate-source.xml) /Desc (Current name-tree WordPress export) /AFRelationship /Source /Lang (en-US) /OutputIntents [13 0 R] /PieceInfo << /WPNameTree << /LastModified (D:20260602192222Z) /Private << /ManifestId (nt-piece-192222) /OutputIntents [13 0 R] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>');
        $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602192300Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
        $addObject(13, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree FileSpec sRGB) /Info (Current attachment-local PDF/A) /DestOutputProfile 8 0 R >>');
        $addObject(16, 0, '<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Length ' . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");
        $addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
        $addObject(21, 0, '<< /Limits [(a) (mzz)] /Names [(migrate-source.xml) 10 0 R (z-out-of-limits.xml) 50 0 R] >>');
        $addObject(22, 0, '<< /Limits [(n) (z)] /Names [(review-preview.pdf) 42 0 R] >>');
        $addObject(42, 0, '<< /Type /Filespec /F (review-preview.pdf) /Desc (Current name-tree preview) /AFRelationship /Alternative /EF << /F 43 0 R >> >>');
        $addObject(43, 0, '<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length ' . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream");
        $addObject(50, 0, '<< /Type /Filespec /F (z-out-of-limits.xml) /Desc (Stale out-of-limits source) /AFRelationship /Source /EF << /F 51 0 R >> >>');
        $addObject(51, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream");
        $addObject(60, 0, '<< /Title (Current NameTree PieceInfo Title) /Author (Current NameTree Author) /Producer (Current NameTree Producer) >>');

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
            throw new RuntimeException('Unable to compress name-tree PieceInfo xref stream.');
        }

        $pdf .= "90 0 obj\n"
            . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /OutputIntents [9 0 R] /Names << /EmbeddedFiles 70 0 R >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (stale-piece-source.xml) /Desc (Stale NameTree PieceInfo source) /AFRelationship /Source /OutputIntents [13 0 R] /PieceInfo << /WPNameTree << /LastModified (D:20260602200000Z) /Private << /ManifestId (stale-nt-piece) /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale NameTree FileSpec sRGB) /Info (Stale attachment-local PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
            . "16 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Length " . strlen($stalePrivateStream) . " >>\nstream\n{$stalePrivateStream}\nendstream\nendobj\n"
            . "70 0 obj\n<< /Names [(stale-detached.xml) 10 0 R] >>\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $embeddedFiles = $metadata['embedded_files'] ?? [];
        $source = $embeddedFiles[0] ?? [];
        $preview = $embeddedFiles[1] ?? [];
        $pieceInfo = $source['piece_info']['WPNameTree'] ?? [];
        $provenance = $source['provenance_review'] ?? [];

        $t->same(['info', 'catalog', 'output_intents'], $metadata['source']);
        $t->same('Current NameTree PieceInfo Title', $metadata['title']);
        $t->same('en-US', $metadata['language']);
        $t->same(['Current NameTree Root sRGB'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $rootProfile)], $metadata['pdfa']['profile_sha256']);
        $t->same('Current NameTree PieceInfo Body', $plainText);
        $t->same(2, count($embeddedFiles));
        $t->same('migrate-source.xml', $source['name_tree_name']);
        $t->same('migrate-source.xml', $source['filename']);
        $t->same('migrate-source.xml', $source['name']);
        $t->same('Current name-tree WordPress export', $source['description']);
        $t->same('Source', $source['relationship']);
        $t->same('text/xml', $source['mime_type']);
        $t->same(true, $source['checksum_matches']);
        $t->same(hash('sha256', $sourcePayload), $source['content_sha256']);
        $t->same('Current NameTree FileSpec sRGB', $source['output_intents_review'][0]['OutputConditionIdentifier']);
        $t->same('D:20260602192222Z', $pieceInfo['last_modified'] ?? null);
        $t->same('nt-piece-192222', $pieceInfo['private']['ManifestId'] ?? null);
        $t->same('Current NameTree FileSpec sRGB', $pieceInfo['private']['OutputIntents'][0]['OutputConditionIdentifier'] ?? null);
        $t->same('Metadata', $pieceInfo['private']['PrivateStream']['Type'] ?? null);
        $t->same(['Current NameTree FileSpec sRGB'], $provenance['pdfa_output_intents']['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $fileProfile)], $provenance['pdfa_output_intents']['profile_sha256'] ?? []);
        $t->same('review-preview.pdf', $preview['name_tree_name']);
        $t->same('Alternative', $preview['relationship']);
        $t->true(!array_key_exists('content', $source));
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $privatePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'z-out-of-limits.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-detached.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale NameTree'));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleSourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePrivatePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'NameTree PieceInfo Private Leak'));
        $t->true(!str_contains($plainText, 'Stale NameTree PieceInfo Body'));
    },
];
