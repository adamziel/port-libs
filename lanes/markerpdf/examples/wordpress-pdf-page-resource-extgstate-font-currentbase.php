<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode ExtGState font smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressExtGStateFontCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageContent = '/Page#20Text gs BT 72 720 Td <41> Tj ET q /Form#20Resource Do Q';
$formContent = '/Form#20Text gs BT 12 24 Td <42> Tj ET';
$pageCMap = $toUnicodeCMap([
    '41' => 'WordPress inherited ExtGState font text',
    '42' => 'WordPress stale page ExtGState font leak',
]);
$formCMap = $toUnicodeCMap([
    '42' => 'WordPress form-local ExtGState font text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageExtGStateSmokeFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageCMap) . " >>\nstream\n{$pageCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FormExtGStateSmokeFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($formCMap) . " >>\nstream\n{$formCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /Fpage 5 0 R /Fform 5 0 R >> /ExtGState << /Page#20Text 11 0 R >> /XObject << /Form#20Resource 20 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /ExtGState /Font [/Fpage 12] >>\nendobj\n"
    . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 200 80] /Resources << /Font << /Fform 7 0 R >> /ExtGState << /Form#20Text << /Type /ExtGState /Font [/Fform 13] >> >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'WordPress inherited ExtGState font text',
    'WordPress form-local ExtGState font text',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected inherited and form-local ExtGState fonts to render WordPress paragraphs.');
}

if (str_contains($plainText, 'WordPress stale page ExtGState font leak')) {
    throw new RuntimeException('Form-local ExtGState font used stale page font resources.');
}

echo '<!-- markerpdf-page-resource-extgstate-font-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-extgstate-font-currentbase',
    'native_boundary' => 'inherited page and form-local /Resources /ExtGState /Font arrays set text fonts before WordPress paragraph extraction',
    'page_extgstate_font_applied' => $lines[0] === 'WordPress inherited ExtGState font text',
    'form_local_extgstate_font_applied' => $lines[1] === 'WordPress form-local ExtGState font text',
    'stale_page_form_font_excluded' => !str_contains($plainText, 'WordPress stale page ExtGState font leak'),
    'resource_review_reports_extgstate' => in_array('ExtGState', $resources['categories'] ?? [], true),
    'extgstate_names' => $resources['extgstate_names'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
