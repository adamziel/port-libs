<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$metadataStreamFilterStackBoundaryCurrentBaseAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$metadataStreamFilterStackBoundaryCurrentBaseRunLength = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length;) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
        $offset += strlen($chunk);
    }

    return $encoded . chr(128);
};

$metadataStreamFilterStackBoundaryCurrentBaseXmp = static function (
    string $title,
    string $description,
    string $keyword,
    string $creatorTool,
    string $producer
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Filter Stack XMP Author</rdf:li><rdf:li>Metadata Import Reviewer</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>' . htmlspecialchars($keyword, ENT_XML1) . '</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>' . htmlspecialchars($producer, ENT_XML1) . '</pdf:Producer>'
        . '<xmp:CreatorTool>' . htmlspecialchars($creatorTool, ENT_XML1) . '</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-07T17:41:57Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T17:42:09Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$metadataStreamFilterStackBoundaryCurrentBasePdf = static function (
    string $metadataPayload,
    string $filterOperand,
    string $bodyText
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter {$filterOperand} /Length " . strlen($metadataPayload) . " >>\nstream\n{$metadataPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Title (Info Fallback Filter Stack Title) /Author (Info Filter Stack Author) /Producer (Info Filter Stack Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";
};

return [
    'promotes catalog XMP metadata through ASCII85 and RunLength filter stacks' => static function (
        TestRunner $t
    ) use (
        $metadataStreamFilterStackBoundaryCurrentBaseAscii85,
        $metadataStreamFilterStackBoundaryCurrentBaseRunLength,
        $metadataStreamFilterStackBoundaryCurrentBaseXmp,
        $metadataStreamFilterStackBoundaryCurrentBasePdf
    ): void {
        $ascii85Xmp = $metadataStreamFilterStackBoundaryCurrentBaseXmp(
            'ASCII85 Stack XMP Title',
            'ASCII85 wrapped Flate metadata should become WordPress document metadata',
            'metadata-ascii85-stack',
            'ASCII85 Stack Creator Tool',
            'ASCII85 Stack Producer'
        );
        $compressedAscii85Xmp = gzcompress($ascii85Xmp);
        if (!is_string($compressedAscii85Xmp)) {
            throw new RuntimeException('Unable to compress ASCII85 metadata stream fixture.');
        }
        $ascii85Payload = $metadataStreamFilterStackBoundaryCurrentBaseAscii85($compressedAscii85Xmp) . '~>';
        $ascii85Pdf = $metadataStreamFilterStackBoundaryCurrentBasePdf(
            $ascii85Payload,
            '[ /ASCII85Decode /FlateDecode ]',
            'Visible ASCII85 metadata stack body'
        );

        $ascii85Metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($ascii85Pdf);
        $ascii85PlainText = (new PdfTextExtractor())->extractPlainText($ascii85Pdf);
        $ascii85EncodedMetadata = json_encode($ascii85Metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $ascii85Metadata['source']);
        $t->same('ASCII85 Stack XMP Title', $ascii85Metadata['title']);
        $t->same('ASCII85 wrapped Flate metadata should become WordPress document metadata', $ascii85Metadata['description']);
        $t->same(['Filter Stack XMP Author', 'Metadata Import Reviewer'], $ascii85Metadata['authors']);
        $t->same(['wordpress', 'metadata-ascii85-stack'], $ascii85Metadata['keywords']);
        $t->same('ASCII85 Stack Creator Tool', $ascii85Metadata['creator_tool']);
        $t->same('ASCII85 Stack Producer', $ascii85Metadata['producer']);
        $t->same('2026-06-07T17:42:09Z', $ascii85Metadata['metadata_date_utc']);
        $t->same('Info Fallback Filter Stack Title', $ascii85Metadata['info']['Title'] ?? null);
        $t->same('Visible ASCII85 metadata stack body', $ascii85PlainText);
        $t->true(is_string($ascii85EncodedMetadata) && !str_contains($ascii85EncodedMetadata, 'Visible ASCII85 metadata stack body'));
        $t->true(!str_contains($ascii85PlainText, 'ASCII85 Stack XMP Title'));

        $runLengthXmp = $metadataStreamFilterStackBoundaryCurrentBaseXmp(
            'RunLength Stack XMP Title',
            'RunLength wrapped Flate metadata should become WordPress document metadata',
            'metadata-runlength-stack',
            'RunLength Stack Creator Tool',
            'RunLength Stack Producer'
        );
        $compressedRunLengthXmp = gzcompress($runLengthXmp);
        if (!is_string($compressedRunLengthXmp)) {
            throw new RuntimeException('Unable to compress RunLength metadata stream fixture.');
        }
        $runLengthPdf = $metadataStreamFilterStackBoundaryCurrentBasePdf(
            $metadataStreamFilterStackBoundaryCurrentBaseRunLength($compressedRunLengthXmp),
            '[ /RunLengthDecode /FlateDecode ]',
            'Visible RunLength metadata stack body'
        );

        $runLengthMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($runLengthPdf);
        $runLengthPlainText = (new PdfTextExtractor())->extractPlainText($runLengthPdf);
        $runLengthEncodedMetadata = json_encode($runLengthMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $runLengthMetadata['source']);
        $t->same('RunLength Stack XMP Title', $runLengthMetadata['title']);
        $t->same('RunLength wrapped Flate metadata should become WordPress document metadata', $runLengthMetadata['description']);
        $t->same(['Filter Stack XMP Author', 'Metadata Import Reviewer'], $runLengthMetadata['authors']);
        $t->same(['wordpress', 'metadata-runlength-stack'], $runLengthMetadata['keywords']);
        $t->same('RunLength Stack Creator Tool', $runLengthMetadata['creator_tool']);
        $t->same('RunLength Stack Producer', $runLengthMetadata['producer']);
        $t->same('2026-06-07T17:41:57Z', $runLengthMetadata['created_at_utc']);
        $t->same('Info Fallback Filter Stack Title', $runLengthMetadata['info']['Title'] ?? null);
        $t->same('Visible RunLength metadata stack body', $runLengthPlainText);
        $t->true(is_string($runLengthEncodedMetadata) && !str_contains($runLengthEncodedMetadata, 'Visible RunLength metadata stack body'));
        $t->true(!str_contains($runLengthPlainText, 'RunLength Stack XMP Title'));
    },
    'rejects catalog XMP metadata filter-stack payload after explicit EOD marker' => static function (
        TestRunner $t
    ) use (
        $metadataStreamFilterStackBoundaryCurrentBaseAscii85,
        $metadataStreamFilterStackBoundaryCurrentBaseXmp,
        $metadataStreamFilterStackBoundaryCurrentBasePdf
    ): void {
        $xmp = $metadataStreamFilterStackBoundaryCurrentBaseXmp(
            'Trailing Payload Stack XMP Title',
            'Payload after ASCII85 EOD must not become WordPress metadata',
            'metadata-stack-eod-boundary',
            'Trailing Payload Stack Creator Tool',
            'Trailing Payload Stack Producer'
        );
        $compressedXmp = gzcompress($xmp);
        if (!is_string($compressedXmp)) {
            throw new RuntimeException('Unable to compress malformed metadata stream fixture.');
        }
        $payload = $metadataStreamFilterStackBoundaryCurrentBaseAscii85($compressedXmp)
            . "~>BT /F1 12 Tf 72 680 Td (Trailing Metadata Payload Leak) Tj ET";
        $pdf = $metadataStreamFilterStackBoundaryCurrentBasePdf(
            $payload,
            '[ /ASCII85Decode /FlateDecode ]',
            'Visible malformed metadata stack body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Info Fallback Filter Stack Title', $metadata['title']);
        $t->same(['Info Filter Stack Author'], $metadata['authors']);
        $t->same('Visible malformed metadata stack body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unreadable_metadata_stream', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($payload), $review['declared_length'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Trailing Payload Stack XMP Title'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Trailing Metadata Payload Leak'));
        $t->true(!str_contains($plainText, 'Trailing Payload Stack XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Metadata Payload Leak'));
    },
];
