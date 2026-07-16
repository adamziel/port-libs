<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

require dirname(__DIR__) . '/tests/PdfXrefPrevChainDamagedPrevDecoySectionCurrentBaseTest.php';
if (!isset($xrefPrevChainDamagedPrevDecoySectionPdf) || !$xrefPrevChainDamagedPrevDecoySectionPdf instanceof Closure) {
    throw new RuntimeException('Expected damaged Prev decoy-section PDF fixture closure.');
}

$fixture = $xrefPrevChainDamagedPrevDecoySectionPdf();
$pdf = $fixture['pdf'];
$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encoded = json_encode([$metadata, $files, $summary, $lines], JSON_UNESCAPED_SLASHES) ?: '';

$flags = [
    'source' => 'native-pdf-xref-prev-chain-damaged-prev-decoy-section',
    'support_component' => 'pdf-xref-prev-chain',
    'native_boundary' => 'Damaged backward /Prev offsets repair to their declared-offset xref neighborhood before unlinked later xref sections',
    'damaged_prev_points_inside_base_xref' => $fixture['damagedPrevOffset'] > $fixture['baseXrefOffset']
        && $fixture['damagedPrevOffset'] < $fixture['decoyXrefOffset'],
    'unlinked_decoy_section_after_declared_prev' => $fixture['decoyXrefOffset'] < $fixture['currentXrefOffset']
        && str_contains($pdf, 'trailer' . "\n" . '<< /Size 39 /Root 30 0 R /Info 38 0 R >>'),
    'current_text_selected' => $lines === ['Current damaged Prev decoy-section page', 'Declared Prev neighborhood repaired'],
    'current_xmp_selected' => ($metadata['title'] ?? null) === 'Current Damaged Prev Decoy Section XMP Title'
        && ($metadata['description'] ?? null) === 'Damaged Prev repairs to declared-offset neighborhood',
    'current_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Damaged Prev Decoy Section Info Title'
        && ($metadata['producer'] ?? null) === 'Current Damaged Prev Producer',
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'current_attachment_selected' => ($files[0]['filename'] ?? null) === 'current-damaged-prev-decoy-section.xml'
        && ($files[0]['content'] ?? null) === $fixture['currentPayload'],
    'attachment_preflight_selected' => ($summary['filenames'] ?? []) === ['current-damaged-prev-decoy-section.xml']
        && ($summary['total_bytes'] ?? null) === strlen($fixture['currentPayload']),
    'decoy_section_excluded' => !str_contains($plainText, 'Unlinked decoy xref-section page')
        && !str_contains($encoded, 'Decoy Damaged Prev')
        && !str_contains($encoded, 'decoy-damaged-prev'),
    'previous_section_excluded' => !str_contains($plainText, 'Previous damaged Prev decoy-section page')
        && !str_contains($encoded, 'Previous Damaged Prev')
        && !str_contains($encoded, 'previous-damaged-prev'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'native_boundary' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);
if (
    in_array(false, $behaviorFlags, true)
    || $flags['executes_python_or_models'] !== false
    || $flags['executes_external_pdf_tools'] !== false
) {
    throw new RuntimeException('Damaged Prev decoy-section WordPress smoke failed: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

if (in_array('--self-test', $argv, true)) {
    echo json_encode(['self_test_passed' => true] + $flags, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo '<!-- markerpdf-xref-prev-chain-damaged-prev-decoy-section-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF xref import review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

foreach ($files as $file) {
    echo "<!-- wp:list -->\n";
    echo '<ul><li>' . htmlspecialchars((string) $file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ': '
        . htmlspecialchars((string) $file['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li></ul>\n";
    echo "<!-- /wp:list -->\n\n";
}
