<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline item type boundary cover body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline item type boundary appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Item Type Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Title (Stale Import Annot Spoof Outline) /Parent 5 0 R /Prev 6 0 R /Next 8 0 R /Dest /SpoofTarget /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Untrusted Import Tail After Typed Spoof) /Parent 5 0 R /Prev 7 0 R /Dest /AppendixTarget /A 13 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (typed-annot-spoof-outline.pdf) /D (spoof-target) /NewWindow true >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToR /F (typed-spoof-tail.pdf) /D (tail-target) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (SpoofTarget) [4 0 R /XYZ 12 34 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['Import Item Type Boundary Chapter']
    || array_column($toc, 'title') !== ['Import Item Type Boundary Chapter']
    || array_column($navigation['outline'] ?? [], 'title') !== ['Import Item Type Boundary Chapter']
) {
    throw new RuntimeException('Expected typed non-outline object to stop outline metadata traversal.');
}
if (($outline['item_count'] ?? null) !== 1 || ($outline['resolved_destination_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected only the valid outline item to resolve.');
}
if ($remoteActions !== []) {
    throw new RuntimeException('Expected remote actions on typed spoof rows to stay excluded.');
}
if (($lightweightMetadata['pdf_toc'] ?? []) !== [[
    'title' => 'Import Item Type Boundary Chapter',
    'level' => 1,
    'page' => 0,
]]) {
    throw new RuntimeException('Expected lightweight pdf_toc metadata to apply the typed item boundary.');
}
foreach ([
    'Stale Import Annot Spoof Outline',
    'Untrusted Import Tail After Typed Spoof',
    'typed-annot-spoof-outline.pdf',
    'typed-spoof-tail.pdf',
] as $forbidden) {
    if ((is_string($metadataEncoded) && str_contains($metadataEncoded, $forbidden))
        || (is_string($navigationEncoded) && str_contains($navigationEncoded, $forbidden))
        || (is_string($lightweightEncoded) && str_contains($lightweightEncoded, $forbidden))
        || str_contains($plainText, $forbidden)
    ) {
        throw new RuntimeException('Typed spoof outline metadata leaked into WordPress review output.');
    }
}
if (str_contains($plainText, 'Import Item Type Boundary Chapter')) {
    throw new RuntimeException('Expected outline titles to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-item-type-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-item-type-boundary-currentbase',
    'support_component' => 'native-pdf-outline-typed-item-boundary-review',
    'native_boundary' => 'typed non-outline objects linked into an outline sibling chain stop traversal before annotation/action spoof rows reach WordPress navigation review',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'lightweight_toc_titles' => array_column($lightweightMetadata['pdf_toc'] ?? [], 'title'),
    'remote_action_titles' => array_column($remoteActions, 'title'),
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'typed_spoof_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale Import Annot Spoof Outline'),
    'tail_after_typed_spoof_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Untrusted Import Tail After Typed Spoof'),
    'stale_remote_actions_excluded' => is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'typed-annot-spoof-outline.pdf')
        && !str_contains($navigationEncoded, 'typed-spoof-tail.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Item Type Boundary Chapter')
        && !str_contains($plainText, 'Stale Import Annot Spoof Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF typed outline item boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-next="' . htmlspecialchars((string) ($item['next_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
