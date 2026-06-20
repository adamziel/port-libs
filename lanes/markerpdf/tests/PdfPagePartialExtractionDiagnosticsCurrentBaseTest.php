<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pagePartialExtractionDiagnosticsPdf = static function (): string {
    $firstSegment = 'BT /F1 12 Tf 72 720 Td (Readable first segment) Tj ET';
    $secondSegment = 'BT /F1 12 Tf 72 700 Td (Readable second segment) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 8 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [5 0 R 6 0 R /BadOperand 7 0 R null] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents null >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($firstSegment) . " >>\nstream\n{$firstSegment}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Note (Hidden nonstream content operand) >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($secondSegment) . " >>\nstream\n{$secondSegment}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [] >>\nendobj\n"
        . "%%EOF";
};

$pagePartialExtractionIndirectArrayPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect array readable segment) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n[4 0 R (literal scalar operand) [false]]\nendobj\n"
        . "%%EOF";
};

return [
    'reports page contents partial extraction causes while preserving valid stream text' => static function (
        TestRunner $t
    ) use ($pagePartialExtractionDiagnosticsPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pagePartialExtractionDiagnosticsPdf();
        $review = $extractor->extractPagePartialExtractionDiagnostics($pdf);
        $entries = $review['entries'];

        $t->same(['Readable first segment', 'Readable second segment'], $extractor->extractTextLines($pdf));
        $t->same('pdf_page_partial_extraction_diagnostics', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(3, $review['page_count']);
        $t->same(3, $review['partial_page_count']);
        $t->same(5, $review['cause_count']);
        $t->same([
            'page_contents_array_malformed_operand' => 1,
            'page_contents_array_null_operand' => 1,
            'page_contents_empty_array' => 1,
            'page_contents_null' => 1,
            'page_contents_reference_not_stream' => 1,
        ], $review['causes']);
        $t->same([
            'page_contents_reference_not_stream',
            'page_contents_array_malformed_operand',
            'page_contents_array_null_operand',
            'page_contents_null',
            'page_contents_empty_array',
        ], array_column($entries, 'cause'));
        $t->same([1, 1, 1, 2, 3], array_column($entries, 'page_number'));
        $t->same([3, 3, 3, 4, 8], array_column($entries, 'page_object'));
        $t->same([true, true, true, true, true], array_column($entries, 'review_only'));
        $t->same([true, true, true, true, true], array_column($entries, 'text_extraction_partial'));

        $t->same(6, $entries[0]['content_object']);
        $t->same(0, $entries[0]['content_generation']);
        $t->same(1, $entries[0]['content_array_index']);
        $t->same('dictionary', $entries[0]['content_value_type']);
        $t->same('Metadata', $entries[0]['content_type']);
        $t->same(2, $entries[1]['content_array_index']);
        $t->same('name', $entries[1]['content_operand_type']);
        $t->same(4, $entries[2]['content_array_index']);
        $t->same('null', $entries[2]['content_operand_type']);
        $t->same('null', $entries[3]['contents_value_type']);
        $t->same('array', $entries[4]['contents_value_type']);

        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encodedReview) && !str_contains($encodedReview, 'Hidden nonstream content operand'));
    },
    'reports indirect page contents array operand paths without losing readable text' => static function (
        TestRunner $t
    ) use ($pagePartialExtractionIndirectArrayPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pagePartialExtractionIndirectArrayPdf();
        $review = $extractor->extractPagePartialExtractionDiagnostics($pdf);
        $entries = $review['entries'];

        $t->same(['Indirect array readable segment'], $extractor->extractTextLines($pdf));
        $t->same(1, $review['page_count']);
        $t->same(1, $review['partial_page_count']);
        $t->same(2, $review['cause_count']);
        $t->same([
            'page_contents_array_malformed_operand' => 2,
        ], $review['causes']);
        $t->same(['page_contents_array_malformed_operand', 'page_contents_array_malformed_operand'], array_column($entries, 'cause'));
        $t->same([6, 6], array_column($entries, 'content_array_object'));
        $t->same([0, 0], array_column($entries, 'content_array_generation'));
        $t->same(1, $entries[0]['content_array_index']);
        $t->same('literal_string', $entries[0]['content_operand_type']);
        $t->same([2, 0], $entries[1]['content_array_path']);
        $t->same('boolean', $entries[1]['content_operand_type']);
    },
];
