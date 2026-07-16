<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamCarrierType2Xmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-05T20:06:39Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefObjectStreamCarrierType2ObjectStream = static function (array $members): array {
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress object-stream carrier type-2 fixture.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'count' => count($members),
        'content' => $compressed,
    ];
};

$xrefObjectStreamCarrierType2MetadataAttachmentPdf = static function () use (
    $xrefObjectStreamCarrierType2Xmp,
    $xrefObjectStreamCarrierType2ObjectStream
): string {
    $currentText = 'BT /F1 12 Tf 72 720 Td (Current type-2 carrier page) Tj T* (Metadata and attachments stay compressed) Tj ET';
    $currentPayload = '<wp-export><post id="current-type2-carrier"/></wp-export>';
    $currentXmp = gzcompress($xrefObjectStreamCarrierType2Xmp(
        'Current Type2 Carrier XMP Title',
        'Object stream carrier remains direct before WordPress import'
    ));
    if (!is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress object-stream carrier type-2 XMP fixture.');
    }

    $carrier = $xrefObjectStreamCarrierType2ObjectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> /AF [10 0 R] >>',
        6 => '<< /Title (Current Type2 Carrier Info Title) /Author (Current Type2 Carrier Author) /Producer (Current Type2 Carrier Producer) >>',
        8 => '<< /Names [(current-type2-carrier.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (current-type2-carrier.xml) /Desc (Current type-2 carrier attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ]);
    $decoyCarrier = $xrefObjectStreamCarrierType2ObjectStream([
        20 => '<< /Type /ObjStm /Note (malformed compressed carrier decoy) >>',
    ]);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . md5($currentPayload) . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(20, 0, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");
    $addObject(21, 0, '<< /Type /ObjStm /N ' . $decoyCarrier['count'] . ' /First ' . $decoyCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($decoyCarrier['content']) . " >>\nstream\n{$decoyCarrier['content']}\nendstream");

    $xrefRows = ''
        . $row(2, 20, $carrier['indexes'][1])
        . $row(1, $offsets['2:0'])
        . $row(1, $offsets['3:0'])
        . $row(1, $offsets['4:0'])
        . $row(1, $offsets['5:0'])
        . $row(2, 20, $carrier['indexes'][6])
        . $row(1, $offsets['7:0'])
        . $row(2, 20, $carrier['indexes'][8])
        . $row(0, 0, 0)
        . $row(2, 20, $carrier['indexes'][10])
        . $row(1, $offsets['11:0'])
        . $row(2, 21, $decoyCarrier['indexes'][20])
        . $row(1, $offsets['21:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress object-stream carrier type-2 xref rows.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Index [1 11 20 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps direct object-stream carriers when malformed type two rows target the carrier itself' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamCarrierType2MetadataAttachmentPdf): void {
        $pdf = $xrefObjectStreamCarrierType2MetadataAttachmentPdf();
        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(['Current type-2 carrier page', 'Metadata and attachments stay compressed'], $textExtractor->extractTextLines($pdf));
        $t->same("Current type-2 carrier page\nMetadata and attachments stay compressed", $plainText);
        $t->same("Current type-2 carrier page\nMetadata and attachments stay compressed\n", $textExtractor->naiveGetText($pdf));
        $t->same(1, $textExtractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $textExtractor->extractPageLabels($pdf));

        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Type2 Carrier XMP Title', $metadata['title']);
        $t->same('Object stream carrier remains direct before WordPress import', $metadata['description']);
        $t->same('Current Type2 Carrier Info Title', $metadata['info']['Title']);
        $t->same(['Current Type2 Carrier Author'], $metadata['authors']);
        $t->same('Current Type2 Carrier Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-05T20:06:39Z', $metadata['created_at_utc']);

        $t->same(1, count($files));
        $t->same('current-type2-carrier.xml', $files[0]['filename']);
        $t->same('Current type-2 carrier attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(10, $files[0]['file_spec_object']);
        $t->same(11, $files[0]['embedded_file_object']);
        $t->same('<wp-export><post id="current-type2-carrier"/></wp-export>', $files[0]['content']);
        $t->same(hash('sha256', '<wp-export><post id="current-type2-carrier"/></wp-export>'), $files[0]['content_sha256']);

        $t->same(1, $summary['attachment_count']);
        $t->same(['current-type2-carrier.xml'], $summary['filenames']);
        $t->same('current-type2-carrier.xml', $summary['attachments'][0]['filename']);
        $t->same(10, $summary['attachments'][0]['file_spec_object_id']);
        $t->same(11, $summary['attachments'][0]['stream_object_id']);
        $t->same(true, $summary['attachments'][0]['associated_file']);
        $t->same('catalog_af', $summary['attachments'][0]['associated_file_source']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'malformed compressed carrier decoy'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'malformed compressed carrier decoy'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'malformed compressed carrier decoy'));
        $t->true(!str_contains($plainText, 'malformed compressed carrier decoy'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
