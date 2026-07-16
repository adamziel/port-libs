<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainEncryptOmittedRowsXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-06T20:35:43Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainEncryptOmittedRowsPdf = static function () use ($xrefPrevChainEncryptOmittedRowsXmp): string {
    $staleText = 'BT /F1 12 Tf 72 720 Td (Stale omitted Encrypt row text leak) Tj ET';
    $currentText = 'BT /F1 12 Tf 72 720 Td (Current omitted Encrypt row text blocked) Tj ET';
    $currentXmp = gzcompress($xrefPrevChainEncryptOmittedRowsXmp(
        'Current Omitted Encrypt XMP Title',
        'Current same-generation Encrypt dictionary selected'
    ));
    if (!is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress omitted-Encrypt xref Prev chain XMP stream.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Omitted Encrypt Info Title) /Author (Stale Encrypt Author) >>');
    $staleEncryptOffset = $addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 7\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . "30 1\n"
        . $xrefTableRow($staleEncryptOffset)
        . "trailer\n<< /Size 40 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Omitted Encrypt Info Title) /Author (Current Encrypt Author) /Producer (Current Encrypt Producer) >>');
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <FEEDFACE> /U <BEEFBEEF> /P -64 /EncryptMetadata false >>');

    $currentRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentInfoOffset, 0)
        . $xrefStreamRow(1, $currentMetadataOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress omitted-Encrypt current xref-stream rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /Prev ' . $previousXrefOffset . ' /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs latest trailer Encrypt row before inheriting stale Prev encryption metadata' => static function (
        TestRunner $t
    ) use ($xrefPrevChainEncryptOmittedRowsPdf): void {
        $pdf = $xrefPrevChainEncryptOmittedRowsPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);
        $extractor = new PdfTextExtractor();
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedPreflight = json_encode($preflight, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(['encryption', 'xmp'], $metadata['source']);
        $t->same('Current Omitted Encrypt XMP Title', $metadata['title']);
        $t->same('Current same-generation Encrypt dictionary selected', $metadata['description']);
        $t->same([], $metadata['info'] ?? []);
        $t->true(!isset($metadata['authors']));
        $t->true(!isset($metadata['producer']));
        $t->same('xref_stream_trailer_encrypt', $metadata['encryption']['source']);
        $t->same(false, $metadata['encryption']['encrypt_metadata']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $metadata['encryption']['metadata_source_policy']['xmp_stream_policy']);
        $t->same('FFFFFFC0', $metadata['encryption']['standard_permissions']['hex']);
        $t->same(true, $preflight['encrypted']);
        $t->same('blocked_without_decryption', $preflight['text_extraction_policy']);
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same([], $extractor->extractTextLines($pdf));
        $t->true(str_contains($pdf, '/Encrypt 30 0 R'));
        $t->true(!str_contains($encodedMetadata, 'Stale Omitted Encrypt'));
        $t->true(!str_contains($encodedMetadata, 'DEADBEEF'));
        $t->true(!str_contains($encodedPreflight, 'DEADBEEF'));
        $t->true(!str_contains($encodedMetadata, 'Stale Encrypt Author'));
    },
];
