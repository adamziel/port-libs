<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamCompressedOperandCascadePdf = static function (): array {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Compressed operand cascade page) Tj ET';
    $payload = '<wp-export><post id="object-stream-operand-cascade"/></wp-export>';
    $checksum = md5($payload);

    $objectStream = static function (array $members): array {
        $headerPairs = [];
        $indexes = [];
        $data = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($data);
            $indexes[$objectNumber] = count($indexes);
            $data .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $plain = $header . "\n" . $data;
        $compressed = gzcompress($plain);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress object-stream operand cascade fixture.');
        }

        return [
            'count' => count($members),
            'first' => strlen($header) + 1,
            'indexes' => $indexes,
            'content' => $compressed,
        ];
    };

    $carrierMembers = [
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-CA) /PageMode /UseOutlines /ViewerPreferences << /DisplayDocTitle true >> /Names << /EmbeddedFiles 8 0 R >> /AF [10 0 R] >>',
        6 => '<< /Title (Compressed Operand Cascade Title) /Author (Cascade Author) /Producer (Cascade Producer) >>',
        8 => '<< /Names [(operand-cascade.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (operand-cascade.xml) /Desc (Compressed operand cascade attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ];
    $carrier = $objectStream($carrierMembers);
    $helper = $objectStream([
        90 => (string) $carrier['count'],
        91 => (string) $carrier['first'],
    ]);

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . pack('n', $fieldThree);

    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($payload) . ' /CheckSum <' . $checksum . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
    $addObject(20, '<< /Type /ObjStm /N 90 0 R /First 91 0 R /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");
    $addObject(21, '<< /Type /ObjStm /N ' . $helper['count'] . ' /First ' . $helper['first'] . ' /Filter /FlateDecode /Length ' . strlen($helper['content']) . " >>\nstream\n{$helper['content']}\nendstream");

    $xrefRows = '';
    for ($objectNumber = 0; $objectNumber <= 91; $objectNumber++) {
        if ($objectNumber === 0) {
            $xrefRows .= $row(0, 0, 65535);
            continue;
        }

        if (isset($carrier['indexes'][$objectNumber])) {
            $xrefRows .= $row(2, 20, $carrier['indexes'][$objectNumber]);
            continue;
        }

        if (isset($helper['indexes'][$objectNumber])) {
            $xrefRows .= $row(2, 21, $helper['indexes'][$objectNumber]);
            continue;
        }

        $xrefRows .= isset($offsets[$objectNumber])
            ? $row(1, $offsets[$objectNumber], 0)
            : $row(0, 0, 0);
    }

    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress object-stream operand cascade xref rows.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 92 /Root 1 0 R /Info 6 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'expands compressed object-stream N and First helper members before metadata and attachment selection' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamCompressedOperandCascadePdf): void {
        [$pdf, $payload, $checksum] = $xrefObjectStreamCompressedOperandCascadePdf();

        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES);

        $t->same(['Compressed operand cascade page'], $textExtractor->extractTextLines($pdf));
        $t->same('Compressed operand cascade page', $textExtractor->extractPlainText($pdf));
        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same('Compressed Operand Cascade Title', $metadata['title']);
        $t->same(['Cascade Author'], $metadata['authors']);
        $t->same('Cascade Producer', $metadata['producer']);
        $t->same('en-CA', $metadata['language']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same(['display_doc_title' => true], $metadata['viewer_preferences']);
        $t->same(1, count($embedded));
        $t->same('operand-cascade.xml', $embedded[0]['filename'] ?? null);
        $t->same('Compressed operand cascade attachment', $embedded[0]['description'] ?? null);
        $t->same('Source', $embedded[0]['relationship'] ?? null);
        $t->same('text/xml', $embedded[0]['mime_type'] ?? null);
        $t->same(10, $embedded[0]['file_spec_object'] ?? null);
        $t->same(11, $embedded[0]['embedded_file_object'] ?? null);
        $t->same($payload, $embedded[0]['content'] ?? null);
        $t->same(hash('sha256', $payload), $embedded[0]['content_sha256'] ?? null);
        $t->same(6, $review['compressed_entry_count']);
        $t->same(4, $entries[1]['object_stream_member_count'] ?? null);
        $t->same(20, $entries[1]['object_stream'] ?? null);
        $t->same('explicit_member_index', $entries[1]['selection_policy'] ?? null);
        $t->same(2, $entries[90]['object_stream_member_count'] ?? null);
        $t->same(21, $entries[90]['object_stream'] ?? null);
        $t->true(is_string($metadataJson) && !str_contains($metadataJson, 'object-stream-operand-cascade'));
        $t->true(is_string($embeddedJson) && str_contains($embeddedJson, 'operand-cascade.xml'));
        $t->true(!str_contains($textExtractor->extractPlainText($pdf), $payload));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
