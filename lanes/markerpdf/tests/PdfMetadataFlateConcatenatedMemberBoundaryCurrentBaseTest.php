<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$metadataFlateConcatenatedMemberBoundaryCurrentBaseXmp = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>Flate Boundary XMP Author</rdf:li><rdf:li>Metadata Boundary Reviewer</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>' . htmlspecialchars($keyword, ENT_XML1) . '</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>' . htmlspecialchars($producer, ENT_XML1) . '</pdf:Producer>'
        . '<xmp:CreatorTool>' . htmlspecialchars($creatorTool, ENT_XML1) . '</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T14:49:51Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T14:49:52Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$metadataFlateConcatenatedMemberBoundaryCurrentBasePdf = static function (
    string $metadataPayload,
    string $bodyText,
    string $infoTitle,
    string $infoAuthor
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataPayload) . " >>\nstream\n{$metadataPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Title (" . $infoTitle . ") /Author (" . $infoAuthor . ") /Producer (Info Flate Boundary Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";
};

return [
    'rejects catalog XMP metadata when Flate stream has a concatenated member tail' => static function (
        TestRunner $t
    ) use (
        $metadataFlateConcatenatedMemberBoundaryCurrentBaseXmp,
        $metadataFlateConcatenatedMemberBoundaryCurrentBasePdf
    ): void {
        $firstMemberXmp = $metadataFlateConcatenatedMemberBoundaryCurrentBaseXmp(
            'First Flate Member XMP Title',
            'The first Flate member must not become trusted WordPress metadata when another member follows it',
            'metadata-flate-first-member',
            'First Flate Member Creator Tool',
            'First Flate Member Producer'
        );
        $secondMemberXmp = $metadataFlateConcatenatedMemberBoundaryCurrentBaseXmp(
            'Second Flate Member XMP Decoy',
            'A second compressed member is a non-whitespace tail for the metadata stream',
            'metadata-flate-second-member',
            'Second Flate Member Creator Tool',
            'Second Flate Member Producer'
        );
        $firstCompressed = gzcompress($firstMemberXmp);
        $secondCompressed = gzcompress($secondMemberXmp);
        if (!is_string($firstCompressed) || !is_string($secondCompressed)) {
            throw new RuntimeException('Unable to compress focused metadata stream fixtures.');
        }

        $payload = $firstCompressed . $secondCompressed;
        $pdf = $metadataFlateConcatenatedMemberBoundaryCurrentBasePdf(
            $payload,
            'Visible concatenated Flate metadata body',
            'Info Fallback Concatenated Flate Title',
            'Info Concatenated Flate Author'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Info Fallback Concatenated Flate Title', $metadata['title']);
        $t->same(['Info Concatenated Flate Author'], $metadata['authors']);
        $t->same('Visible concatenated Flate metadata body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unreadable_metadata_stream', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($payload), $review['declared_length'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'First Flate Member XMP Title'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Second Flate Member XMP Decoy'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'metadata-flate-first-member'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'metadata-flate-second-member'));
        $t->true(!str_contains($plainText, 'First Flate Member XMP Title'));
        $t->true(!str_contains($plainText, 'Second Flate Member XMP Decoy'));
    },
    'promotes catalog XMP metadata when Flate stream ends before PDF whitespace only' => static function (
        TestRunner $t
    ) use (
        $metadataFlateConcatenatedMemberBoundaryCurrentBaseXmp,
        $metadataFlateConcatenatedMemberBoundaryCurrentBasePdf
    ): void {
        $xmp = $metadataFlateConcatenatedMemberBoundaryCurrentBaseXmp(
            'Whitespace Bounded Flate XMP Title',
            'Whitespace after the complete Flate member is a safe metadata boundary',
            'metadata-flate-whitespace-boundary',
            'Whitespace Bounded Flate Creator Tool',
            'Whitespace Bounded Flate Producer'
        );
        $compressed = gzcompress($xmp);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress whitespace-bounded metadata stream fixture.');
        }

        $payload = $compressed . " \t";
        $pdf = $metadataFlateConcatenatedMemberBoundaryCurrentBasePdf(
            $payload,
            'Visible whitespace bounded Flate metadata body',
            'Info Whitespace Flate Title',
            'Info Whitespace Flate Author'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Whitespace Bounded Flate XMP Title', $metadata['title']);
        $t->same('Whitespace after the complete Flate member is a safe metadata boundary', $metadata['description']);
        $t->same(['Flate Boundary XMP Author', 'Metadata Boundary Reviewer'], $metadata['authors']);
        $t->same(['wordpress', 'metadata-flate-whitespace-boundary'], $metadata['keywords']);
        $t->same('Whitespace Bounded Flate Creator Tool', $metadata['creator_tool']);
        $t->same('Whitespace Bounded Flate Producer', $metadata['producer']);
        $t->same('2026-06-08T14:49:52Z', $metadata['metadata_date_utc']);
        $t->same('Info Whitespace Flate Title', $metadata['info']['Title'] ?? null);
        $t->same('Visible whitespace bounded Flate metadata body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Visible whitespace bounded Flate metadata body'));
        $t->true(!str_contains($plainText, 'Whitespace Bounded Flate XMP Title'));
    },
];
