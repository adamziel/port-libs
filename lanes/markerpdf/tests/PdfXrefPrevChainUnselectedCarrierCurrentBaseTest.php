<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainUnselectedCarrierCurrentBasePdf = static function (): string {
    $previousText = 'BT /F1 12 Tf 72 720 Td (Previous unselected carrier page) Tj ET';
    $currentText = 'BT /F1 12 Tf 72 720 Td (Current unselected carrier page) Tj T* (Previous carrier row skipped) Tj ET';
    $leakPayload = '<wp-export><post id="unselected-carrier-leak"/></wp-export>';

    $objectStream = static function (array $members, array &$memberIndexes): array {
        $headerPairs = [];
        $memberIndexes = [];
        $objectData = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $compressed = gzcompress($header . "\n" . $objectData);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress unselected carrier object stream fixture.');
        }

        return [$header, $compressed];
    };

    $previousIndexes = [];
    $replacementIndexes = [];
    [$previousHeader, $previousCompressed] = $objectStream([
        8 => '<< /Names [(previous-carrier-leak.xml) 10 0 R] >>',
    ], $previousIndexes);
    [$replacementHeader, $replacementCompressed] = $objectStream([
        8 => '<< /Names [(unselected-carrier-leak.xml) 10 0 R] >>',
    ], $replacementIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousText) . " >>\nstream\n{$previousText}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($previousHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream");

    $previousRows = ''
        . $xrefStreamRow(1, $offsets['1:0:0'], 0)
        . $xrefStreamRow(1, $offsets['2:0:1'], 0)
        . $xrefStreamRow(1, $offsets['3:0:2'], 0)
        . $xrefStreamRow(1, $offsets['4:0:3'], 0)
        . $xrefStreamRow(1, $offsets['5:0:4'], 0)
        . $xrefStreamRow(2, 6, $previousIndexes[8]);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress previous unselected-carrier xref stream fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5 8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Lang (en-US) /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Contents 9 0 R >>');
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($replacementHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($replacementCompressed) . " >>\nstream\n{$replacementCompressed}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
    $addObject(10, 0, '<< /Type /Filespec /F (unselected-carrier-leak.xml) /Desc (Unselected carrier attachment leak) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($leakPayload) . " >>\nstream\n{$leakPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:1:7'], 1)
        . $xrefStreamRow(1, $offsets['2:1:8'], 1)
        . $xrefStreamRow(1, $offsets['3:1:9'], 1)
        . $xrefStreamRow(1, $offsets['5:0:4'], 0)
        . $xrefStreamRow(1, $offsets['9:0:11'], 0);
    $currentCompressedXref = gzcompress($currentRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress current unselected-carrier xref stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 3 5 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'skips previous type-2 attachment rows whose object-stream carrier was never selected' => static function (
        TestRunner $t
    ) use ($xrefPrevChainUnselectedCarrierCurrentBasePdf): void {
        $pdf = $xrefPrevChainUnselectedCarrierCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current unselected carrier page', 'Previous carrier row skipped'], $extractor->extractTextLines($pdf));
        $t->same("Current unselected carrier page\nPrevious carrier row skipped", $text);
        $t->same(['catalog'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same([], $files);
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true(!str_contains($text, 'Previous unselected carrier page'));
        $t->true(!str_contains($text, 'unselected-carrier-leak'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'unselected-carrier-leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'unselected-carrier-leak'));
        $t->true(!str_contains($text, "\0"));
    },
    'keeps attachment preflight from inheriting previous type-2 rows whose carrier was replaced' => static function (
        TestRunner $t
    ) use ($xrefPrevChainUnselectedCarrierCurrentBasePdf): void {
        $pdf = $xrefPrevChainUnselectedCarrierCurrentBasePdf();
        $attachments = (new PdfAttachmentExtractor())->extractAttachments($pdf);
        $encodedAttachments = json_encode($attachments, JSON_UNESCAPED_SLASHES);

        $t->same([], $attachments);
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true(is_string($encodedAttachments) && !str_contains($encodedAttachments, 'unselected-carrier-leak'));
        $t->true(is_string($encodedAttachments) && !str_contains($encodedAttachments, '<wp-export>'));
        $t->true(!str_contains((new PdfTextExtractor())->extractPlainText($pdf), 'unselected-carrier-leak'));
    },
];
