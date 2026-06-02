<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentFileSpecAssociatedAfRelationshipChecksumCurrentBasePdf = static function (): array {
    $sourcePayload = '<wp-export><post id="afrelationship-current"/></wp-export>';
    $reviewPayload = '{"review":"relationship-mismatch"}';
    $orphanPayload = '{"review":"missing-relationship"}';
    $staleSourcePayload = '<wp-export><post id="stale-afrelationship"/></wp-export>';
    $staleReviewPayload = '{"review":"stale"}';

    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $reviewChecksum = str_repeat('0d', 16);
    $orphanChecksum = strtoupper(hash('md5', $orphanPayload));
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current FileSpec Associated Body) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale FileSpec Associated Body) Tj ET';

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /AF [10 0 R 20 0 R 30 0 R] >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(10, '<< /Type /Filespec /F (source.xml) /Desc (Current source export) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602213538Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
    $addObject(20, '<< /Type /Filespec /F (review.json) /Desc (Custom relationship review packet) /AFRelationship /MigrationReview /EF << /F 21 0 R >> >>');
    $addObject(21, '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($reviewPayload) . ' /CheckSum <' . $reviewChecksum . "> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream");
    $addObject(30, '<< /Type /Filespec /F (orphan-review.json) /Desc (Missing relationship review packet) /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($orphanPayload) . ' /CheckSum <' . $orphanChecksum . "> >> /Length " . strlen($orphanPayload) . " >>\nstream\n{$orphanPayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 61; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 60)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 60 ? $xrefOffset : $offsets[$objectNumber], 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress FileSpec AFRelationship checksum xref stream.');
    }

    $pdf .= "60 0 obj\n"
        . '<< /Type /XRef /Size 61 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (stale-source.xml) /Desc (Stale source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (stale-review.json) /Desc (Stale review packet) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Length " . strlen($staleReviewPayload) . " >>\nstream\n{$staleReviewPayload}\nendstream\nendobj\n";

    return [$pdf, $sourcePayload, $reviewPayload, $orphanPayload, $staleSourcePayload, $staleReviewPayload];
};

return [
    'summarizes current xref-selected associated FileSpec AFRelationship and checksum review' => static function (
        TestRunner $t
    ) use ($attachmentFileSpecAssociatedAfRelationshipChecksumCurrentBasePdf): void {
        [$pdf, $sourcePayload, $reviewPayload, $orphanPayload, $staleSourcePayload, $staleReviewPayload] = $attachmentFileSpecAssociatedAfRelationshipChecksumCurrentBasePdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encoded = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(3, count($files));

        $source = $files[0];
        $sourceProvenance = $source['provenance_review'] ?? [];
        $sourcePayloadReview = $sourceProvenance['payload'] ?? [];
        $t->same('catalog_associated_files', $source['source']);
        $t->same(true, $source['associated_file']);
        $t->same(0, $source['associated_file_index']);
        $t->same('source.xml', $source['filename']);
        $t->same('Current source export', $source['description']);
        $t->same('Source', $source['relationship']);
        $t->same('text/xml', $source['mime_type']);
        $t->same(true, $source['checksum_matches']);
        $t->same('associated_file_provenance', $sourceProvenance['source'] ?? null);
        $t->same(true, $sourceProvenance['review_only'] ?? null);
        $t->same(false, $sourceProvenance['payload_included'] ?? null);
        $t->same(true, $sourceProvenance['payload_content_returned'] ?? null);
        $t->same('original_source', $sourceProvenance['relationship_role'] ?? null);
        $t->same('standard_pdf_associated_file_relationship', $sourceProvenance['relationship_status'] ?? null);
        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum'], $sourceProvenance['sources'] ?? []);
        $t->same('source.xml', $sourcePayloadReview['filename'] ?? null);
        $t->same('text/xml', $sourcePayloadReview['mime_type'] ?? null);
        $t->same(strlen($sourcePayload), $sourcePayloadReview['bytes'] ?? null);
        $t->same(strlen($sourcePayload), $sourcePayloadReview['declared_size'] ?? null);
        $t->same(true, $sourcePayloadReview['size_matches_declared'] ?? null);
        $t->same(hash('sha256', $sourcePayload), $sourcePayloadReview['sha256'] ?? null);
        $t->same(hash('md5', $sourcePayload), $sourcePayloadReview['checksum'] ?? null);
        $t->same(hash('md5', $sourcePayload), $sourcePayloadReview['computed_checksum'] ?? null);
        $t->same(true, $sourcePayloadReview['checksum_matches'] ?? null);

        $custom = $files[1];
        $customProvenance = $custom['provenance_review'] ?? [];
        $customPayloadReview = $customProvenance['payload'] ?? [];
        $t->same('review.json', $custom['filename']);
        $t->same('MigrationReview', $custom['relationship']);
        $t->same(false, $custom['checksum_matches']);
        $t->same('unrecognized', $customProvenance['relationship_role'] ?? null);
        $t->same('unrecognized_pdf_associated_file_relationship', $customProvenance['relationship_status'] ?? null);
        $t->same(hash('sha256', $reviewPayload), $customPayloadReview['sha256'] ?? null);
        $t->same(false, $customPayloadReview['checksum_matches'] ?? null);

        $missing = $files[2];
        $missingProvenance = $missing['provenance_review'] ?? [];
        $missingPayloadReview = $missingProvenance['payload'] ?? [];
        $t->same('orphan-review.json', $missing['filename']);
        $t->same(false, array_key_exists('relationship', $missing));
        $t->same('missing_pdf_associated_file_relationship', $missingProvenance['relationship_status'] ?? null);
        $t->same(['embedded_file_payload_hash', 'embedded_file_params_checksum'], $missingProvenance['sources'] ?? []);
        $t->same(hash('sha256', $orphanPayload), $missingPayloadReview['sha256'] ?? null);
        $t->same(true, $missingPayloadReview['checksum_matches'] ?? null);

        $t->same('Current FileSpec Associated Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-source.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleSourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleReviewPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'relationship-mismatch'));
        $t->true(!str_contains($plainText, 'missing-relationship'));
        $t->true(!str_contains($plainText, 'Stale FileSpec Associated Body'));
    },
];
