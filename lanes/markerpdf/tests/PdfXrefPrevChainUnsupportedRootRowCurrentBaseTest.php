<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainUnsupportedRootRowCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale unsupported Root Prev page) Tj ET';
    $staleXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Unsupported Root XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Unsupported Root Info Title) /Author (Stale Unsupported Root Author) /Producer (Stale Unsupported Root Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-unsupported-root.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-unsupported-root.xml) /Desc (Stale unsupported Root attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $stalePayload = '<wp-export><post id="stale-unsupported-root"/></wp-export>';
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Unsupported Root Info Title) /Author (Current Unsupported Root Author) /Producer (Current Unsupported Root Producer) >>');

    $currentRows = $xrefStreamRow(9, 0, 0)
        . $xrefStreamRow(1, $currentInfoOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress unsupported-Root-row xref Prev chain fixture rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1 6 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'treats unsupported current xref-stream Root row as catalog-clearing owner before Prev replay' => static function (
        TestRunner $t
    ) use ($xrefPrevChainUnsupportedRootRowCurrentBasePdf): void {
        $pdf = $xrefPrevChainUnsupportedRootRowCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same([], $files);
        $t->same(0, $attachmentSummary['attachment_count']);
        $t->same(0, $attachmentSummary['total_bytes']);
        $t->same([], $attachmentSummary['filenames']);
        $t->same([], $attachmentSummary['attachments']);
        $t->same(['info'], $metadata['source']);
        $t->same('Current Unsupported Root Info Title', $metadata['title']);
        $t->same('Current Unsupported Root Info Title', $metadata['info']['Title']);
        $t->same(['Current Unsupported Root Author'], $metadata['authors']);
        $t->same('Current Unsupported Root Producer', $metadata['producer']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(str_contains($pdf, '/Root 1 0 R'));
        $t->true(str_contains($pdf, '/Index [1 1 6 1]'));
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Unsupported Root'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-unsupported-root.xml'));
        $t->true(is_string($encodedAttachmentSummary) && !str_contains($encodedAttachmentSummary, 'stale-unsupported-root.xml'));
        $t->true(is_string($encodedAttachmentSummary) && !str_contains($encodedAttachmentSummary, 'Stale unsupported Root attachment'));
        $t->true(!isset($metadata['catalog']));
        $t->true(!isset($metadata['language']));
        $t->true(!isset($metadata['embedded_files']));
    },
];
