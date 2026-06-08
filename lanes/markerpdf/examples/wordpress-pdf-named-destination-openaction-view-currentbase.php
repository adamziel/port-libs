<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introPageContent = 'BT /F1 12 Tf 72 720 Td (Open action intro page stays visible) Tj ET';
$asciiTargetContent = 'BT /F1 12 Tf 72 720 Td (ASCII collision target body stays visible) Tj ET';
$utf16TargetContent = 'BT /F1 12 Tf 72 720 Td (UTF16 collision open action target stays visible) Tj ET';
$utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /OpenAction {$utf16Collision} >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Collision) {$utf16Collision}] /Names [(Collision) [4 0 R /FitH 700] {$utf16Collision} [5 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introPageContent) . " >>\nstream\n{$introPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($asciiTargetContent) . " >>\nstream\n{$asciiTargetContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($utf16TargetContent) . " >>\nstream\n{$utf16TargetContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$namedDestinationExtractor = new PdfNamedDestinationExtractor();
$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();

$destinations = $namedDestinationExtractor->extractNamedDestinations($pdf);
$openActionRows = $outlineExtractor->getOpenActionReviewActions($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (array_column($destinations, 'name') !== ['Collision', 'Collision']
    || array_column($destinations, 'page') !== [1, 2]
    || array_column($destinations, 'fit') !== ['FitH', 'XYZ']
) {
    throw new RuntimeException('Expected decoded-collision named destinations to preserve distinct pages and views.');
}
if (count($openActionRows) !== 1
    || ($openActionRows[0]['action_type'] ?? null) !== 'GoTo'
    || ($openActionRows[0]['safety'] ?? null) !== 'local-destination'
    || ($openActionRows[0]['page'] ?? null) !== 2
    || ($openActionRows[0]['view_mode'] ?? null) !== 'XYZ'
    || ($openActionRows[0]['view_parameters'] ?? null) !== ['left' => 72.0, 'top' => 640.0, 'zoom' => null]
    || ($openActionRows[0]['executes_on_import'] ?? true) !== false
) {
    throw new RuntimeException('Expected catalog OpenAction review to preserve the named destination XYZ view without executing it.');
}
if (($navigation['open_action_destination']['view_parameters'] ?? null) !== ($openActionRows[0]['view_parameters'] ?? null)) {
    throw new RuntimeException('Expected OpenAction review view parameters to match catalog page-view metadata.');
}
foreach (['Collision', 'OpenAction', 'FitH', 'XYZ'] as $reviewOnly) {
    if (str_contains($plainText, $reviewOnly)) {
        throw new RuntimeException('Expected named-destination OpenAction operands to stay out of visible WordPress text.');
    }
}

$summary = [
    'support_component' => 'native-pdf-openaction-named-destination-review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'catalog OpenAction raw-byte named destinations preserve destination view operands as review metadata only',
    'decoded_collision_count' => count($destinations),
    'destination_name_bytes_hex' => array_column($destinations, 'name_bytes_hex'),
    'open_action_page' => $openActionRows[0]['page'],
    'open_action_destination' => $openActionRows[0]['destination'],
    'open_action_view_mode' => $openActionRows[0]['view_mode'],
    'open_action_view_parameters' => $openActionRows[0]['view_parameters'],
    'open_action_review_view_matches_catalog' => ($navigation['open_action_destination']['view_parameters'] ?? null) === $openActionRows[0]['view_parameters'],
    'visible_text_excludes_destination_labels' => !str_contains($plainText, 'Collision') && !str_contains($plainText, 'XYZ'),
];

echo '<!-- markerpdf-pdf-named-destination-openaction-view-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach (array_filter(array_map('trim', explode("\n", $plainText))) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- wp:list {"className":"markerpdf-openaction-review"} -->' . "\n<ul class=\"markerpdf-openaction-review\">\n";
echo '<li data-marker-openaction-page="' . htmlspecialchars((string) $openActionRows[0]['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-openaction-view="' . htmlspecialchars((string) $openActionRows[0]['view_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-destination-collision-count="' . htmlspecialchars((string) count($destinations), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">OpenAction review target: '
    . htmlspecialchars((string) $openActionRows[0]['destination'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
