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
            throw new RuntimeException('Unable to encode page-resource comment-reference CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceCommentReferenceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$directContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedForm Do Q';
$localContent = 'BT /F2 12 Tf 72 680 Td <42> Tj ET q /LocalForm Do Q';
$inheritedForm = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
$localForm = 'BT /F2 12 Tf 12 24 Td <44> Tj ET';
$directCMap = $toUnicodeCMap([
    '41' => 'Comment inherited font text',
    '42' => 'Comment local font text',
    '43' => 'Comment inherited form text',
    '44' => 'Comment local form text',
]);
$directPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 % object/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 20 % local object/generation split by PDF comment\n 0 % local generation/R split by PDF comment\n R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($localContent) . " >>\nstream\n{$localContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentResourceFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($directCMap) . " >>\nstream\n{$directCMap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($inheritedForm) . " >>\nstream\n{$inheritedForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /InheritedForm 9 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($localForm) . " >>\nstream\n{$localForm}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Font << /F2 7 0 R >> /XObject << /LocalForm 11 0 R >> >>\nendobj\n"
    . "%%EOF";

$wrapperContent = 'BT /Fwrap 12 Tf 72 720 Td <41> Tj ET q /WrappedForm Do Q q /StaleForm Do Q';
$wrappedForm = 'BT /Fwrap 12 Tf 12 24 Td <42> Tj ET';
$staleForm = 'BT /Fwrap 12 Tf 12 24 Td <43> Tj ET';
$wrapperCMap = $toUnicodeCMap([
    '41' => 'Comment wrapper inherited font text',
    '42' => 'Comment wrapper inherited form text',
    '43' => 'Comment wrapper stale resource leak',
]);
$wrapperPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 12 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /Resources 30 0 R >> >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($wrapperContent) . " >>\nstream\n{$wrapperContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentWrapperFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($wrapperCMap) . " >>\nstream\n{$wrapperCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($wrappedForm) . " >>\nstream\n{$wrappedForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /Fwrap 5 0 R >> /XObject << /WrappedForm 7 0 R >> >>\nendobj\n"
    . "12 0 obj\n10 % wrapper object/generation split by PDF comment\n 0 % wrapper generation/R split by PDF comment\n R\nendobj\n"
    . "30 0 obj\n<< /Font << /Fwrap 5 0 R >> /XObject << /StaleForm 8 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$directLines = $extractor->extractTextLines($directPdf);
$wrapperLines = $extractor->extractTextLines($wrapperPdf);
$directBoundary = $propertyExtractor->extractPageBoundaryMetadata($directPdf);
$wrapperBoundary = $propertyExtractor->extractPageBoundaryMetadata($wrapperPdf);
$directResources = $directBoundary[0]['resources'] ?? [];
$localResources = $directBoundary[1]['resources'] ?? [];
$wrapperResources = $wrapperBoundary[0]['resources'] ?? [];
$expectedDirectLines = [
    'Comment inherited font text',
    'Comment inherited form text',
    'Comment local font text',
    'Comment local form text',
];
$expectedWrapperLines = [
    'Comment wrapper inherited font text',
    'Comment wrapper inherited form text',
];
$plainText = implode("\n", [...$directLines, ...$wrapperLines]);

if ($directLines !== $expectedDirectLines || $wrapperLines !== $expectedWrapperLines) {
    throw new RuntimeException('Expected comment-delimited page resources to drive WordPress paragraph text.');
}

if (($directResources['resource_object'] ?? null) !== 10
    || ($localResources['resource_object'] ?? null) !== 20
    || ($wrapperResources['resource_object'] ?? null) !== 10
) {
    throw new RuntimeException('Expected comment-delimited page resource references to resolve in page metadata.');
}

echo '<!-- markerpdf-page-resource-comment-reference-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-comment-reference-currentbase',
    'native_boundary' => 'PDF comments are whitespace inside page /Resources indirect references and resource-wrapper objects before inherited lookup',
    'direct_inherited_resource_object' => $directResources['resource_object'] ?? null,
    'local_resource_object' => $localResources['resource_object'] ?? null,
    'wrapper_resolved_resource_object' => $wrapperResources['resource_object'] ?? null,
    'direct_resource_comment_split_resolved' => ($directResources['resource_object'] ?? null) === 10,
    'local_resource_comment_split_resolved' => ($localResources['resource_object'] ?? null) === 20,
    'wrapper_resource_comment_split_resolved' => ($wrapperResources['resource_object'] ?? null) === 10,
    'wrapper_stale_private_resource_excluded' => !str_contains($plainText, 'Comment wrapper stale resource leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ([...$directLines, ...$wrapperLines] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
