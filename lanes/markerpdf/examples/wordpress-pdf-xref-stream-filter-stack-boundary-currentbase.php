<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
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

$xrefRow = static function (int $type, int $offset, int $generation): string {
    if ($offset < 0 || $offset > 0xffff || $generation < 0 || $generation > 0xff) {
        throw new RuntimeException('Focused xref-stream smoke row uses out-of-range fields.');
    }

    return chr($type) . pack('n', $offset) . chr($generation);
};

$buildPdf = static function (string $payload, string $filterOperand = '/FlateDecode'): string {
    $header = "%PDF-1.5\n";
    $xrefOffset = strlen($header);
    $xrefObject = "1 0 obj\n"
        . "<< /Type /XRef /Size 2 /Index [1 1] /W [1 2 1] /Filter {$filterOperand} /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n";

    return $header
        . $xrefObject
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$summaryFor = static function (string $pdf): array {
    $review = (new PdfTextExtractor())->extractXrefStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    return [
        'xref_stream_count' => $review['xref_stream_count'] ?? null,
        'encrypted' => $review['encrypted'] ?? null,
        'filters' => $entry['filters'] ?? null,
        'declared_length' => $entry['declared_length'] ?? null,
        'decoded_entry_count' => $entry['decoded_entry_count'] ?? null,
        'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
        'owner_policy' => $entry['owner_policy'] ?? null,
        'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
    ];
};

$xrefOffset = strlen("%PDF-1.5\n");
$firstMember = gzcompress($xrefRow(1, $xrefOffset, 0));
$tailMember = gzcompress($xrefRow(1, $xrefOffset + 1, 0));
if (!is_string($firstMember) || !is_string($tailMember)) {
    throw new RuntimeException('Unable to build focused Flate xref-stream smoke fixture.');
}

$directConcatPayload = $firstMember . $tailMember;
$stackConcatPayload = $ascii85Encode($firstMember . $tailMember) . '~>';
$validWhitespacePayload = $firstMember . "\n \r\t";

$cases = [
    'direct_flate_concat' => $summaryFor($buildPdf($directConcatPayload)),
    'ascii85_flate_stack_concat' => $summaryFor($buildPdf($stackConcatPayload, '[ /ASCII85Decode /FlateDecode ]')),
    'single_flate_member_with_whitespace' => $summaryFor($buildPdf($validWhitespacePayload)),
];

$evidence = [
    'scenario' => 'wordpress_pdf_xref_stream_filter_stack_boundary_currentbase',
    'source' => 'native-pdf-xref-stream-filter-stack-boundary-currentbase',
    'support_component' => 'pdf-xref-stream-core',
    'cases' => $cases,
    'concat_flate_xref_rejected' => ($cases['direct_flate_concat']['decoded_entry_count'] ?? null) === 0
        && ($cases['direct_flate_concat']['decoded_with_current_operands'] ?? null) === false,
    'stacked_concat_flate_xref_rejected' => ($cases['ascii85_flate_stack_concat']['decoded_entry_count'] ?? null) === 0
        && ($cases['ascii85_flate_stack_concat']['decoded_with_current_operands'] ?? null) === false,
    'single_flate_member_preserved' => ($cases['single_flate_member_with_whitespace']['decoded_entry_count'] ?? null) === 1
        && ($cases['single_flate_member_with_whitespace']['decoded_with_current_operands'] ?? null) === true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$required = [
    $evidence['concat_flate_xref_rejected'],
    $evidence['stacked_concat_flate_xref_rejected'],
    $evidence['single_flate_member_preserved'],
    ($cases['direct_flate_concat']['filters'] ?? null) === ['FlateDecode'],
    ($cases['ascii85_flate_stack_concat']['filters'] ?? null) === ['ASCII85Decode', 'FlateDecode'],
    ($cases['single_flate_member_with_whitespace']['filters'] ?? null) === ['FlateDecode'],
    ($cases['direct_flate_concat']['executes_python_or_models'] ?? null) === false,
    ($cases['direct_flate_concat']['executes_external_pdf_tools'] ?? null) === false,
    ($cases['ascii85_flate_stack_concat']['executes_python_or_models'] ?? null) === false,
    ($cases['ascii85_flate_stack_concat']['executes_external_pdf_tools'] ?? null) === false,
    ($cases['single_flate_member_with_whitespace']['executes_python_or_models'] ?? null) === false,
    ($cases['single_flate_member_with_whitespace']['executes_external_pdf_tools'] ?? null) === false,
];
if (in_array(false, $required, true)) {
    throw new RuntimeException('Expected xref stream filter stack boundary smoke flags to pass: ' . json_encode($evidence, JSON_UNESCAPED_SLASHES));
}

if (in_array('--self-test', $argv, true)) {
    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_xref_stream_filter_stack_boundary\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";
echo "<!-- wp:paragraph -->\n";
echo "<p>XRef stream filter stack boundary preflight passed.</p>\n";
echo "<!-- /wp:paragraph -->\n";
