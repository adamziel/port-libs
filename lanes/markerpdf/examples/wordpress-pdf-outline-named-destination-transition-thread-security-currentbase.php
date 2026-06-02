<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Intro security navigation page remains visible) Tj ET';
$deckContent = 'BT /F1 12 Tf 72 720 Td (Secure deck target page remains visible) Tj ET';
$signaturePayload = 'OUTLINE_SECURITY_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] /AcroForm 40 0 R /Perms << /DocMDP 43 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 6 /Trans 16 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Secure Deck Outline) /Parent 5 0 R /Dest /SecureDeck >>\nendobj\n"
    . "8 0 obj\n<< /Names [(SecureDeck) 9 0 R (DeckTarget) [4 0 R /FitH 710]] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /DeckTarget /Next [10 0 R 11 0 R 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/outline-security-review) >>\nendobj\n"
    . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outline security script'\\)) >>\nendobj\n"
    . "12 0 obj\n<< /S /Launch /F (outline-helper.exe) /Win << /F (outline-helper.exe) /O (open) >> /NewWindow true >>\nendobj\n"
    . "16 0 obj\n<< /S /Dissolve /D .7 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Security Deck Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 682 280 728] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 682 540 728] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Deck ) /St 7 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($deckContent) . " >>\nstream\n{$deckContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Fields [41 0 R] /SigFlags 3 >>\nendobj\n"
    . "41 0 obj\n<< /FT /Sig /T (approval.outlineSecurity) /V 43 0 R /Kids [42 0 R] >>\nendobj\n"
    . "42 0 obj\n<< /Subtype /Widget /Parent 41 0 R /Rect [72 620 300 664] /P 4 0 R /F 4 >>\nendobj\n"
    . "43 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Outline Security Reviewer) /M (D:20260602213951Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
    . "%%EOF";

$gapStart = strpos($pdf, $signatureContentsToken);
if ($gapStart === false) {
    throw new RuntimeException('Unable to locate signature contents token in focused fixture.');
}

$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
]);

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$actionReview = is_array($preflight['document_action_security_review'] ?? null)
    ? $preflight['document_action_security_review']
    : [];
$outlineSecurity = is_array($actionReview['outline_action_security_review'] ?? null)
    ? $actionReview['outline_action_security_review']
    : [];
$outlineActions = $actionReview['actions'] ?? [];
$encodedPreflight = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSignatureMaterialExposed = is_string($encodedPreflight)
    && (
        str_contains($encodedPreflight, $signaturePayload)
        || str_contains($encodedPreflight, strtoupper(bin2hex($signaturePayload)))
    );

if (($outlineSecurity['outline_action_count'] ?? null) !== 4) {
    throw new RuntimeException('Expected outline actions to be included in the security preflight.');
}
if (($outlineSecurity['unsafe_outline_action_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected JavaScript and Launch outline actions to be blocked.');
}
if (($outlineSecurity['destination_action_target_transition_styles'] ?? []) !== ['Dissolve']) {
    throw new RuntimeException('Expected named destination target transition context.');
}
if (($outlineSecurity['destination_action_target_article_thread_titles'] ?? []) !== ['Security Deck Thread']) {
    throw new RuntimeException('Expected named destination target article-thread context.');
}
if ($rawSignatureMaterialExposed) {
    throw new RuntimeException('Expected signature bytes to stay out of security review JSON.');
}
if (str_contains($plainText, 'SecureDeck') || str_contains($plainText, 'outline-helper.exe') || str_contains($plainText, 'hidden outline security script') || str_contains($plainText, 'Security Deck Thread')) {
    throw new RuntimeException('Expected outline security operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-named-destination-transition-thread-security-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-security-preflight-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline named-destination action chains are security preflight rows with target page transition and article-thread review context, without executing PDF actions',
    'navigation_sources' => $navigation['source'],
    'import_decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'outline_action_count' => $outlineSecurity['outline_action_count'] ?? null,
    'unsafe_outline_action_count' => $outlineSecurity['unsafe_outline_action_count'] ?? null,
    'outline_action_types' => $outlineSecurity['outline_action_types'] ?? [],
    'outline_action_safeties' => $outlineSecurity['outline_action_safety_labels'] ?? [],
    'destination_names' => $outlineSecurity['destination_action_names'] ?? [],
    'target_transition_styles' => $outlineSecurity['destination_action_target_transition_styles'] ?? [],
    'target_article_thread_titles' => $outlineSecurity['destination_action_target_article_thread_titles'] ?? [],
    'raw_signature_material_exposed' => $rawSignatureMaterialExposed,
    'visible_text_excludes_outline_security_operands' => !str_contains($plainText, 'SecureDeck')
        && !str_contains($plainText, 'DeckTarget')
        && !str_contains($plainText, 'outline-security-review')
        && !str_contains($plainText, 'hidden outline security script')
        && !str_contains($plainText, 'outline-helper.exe')
        && !str_contains($plainText, 'Security Deck Thread'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($outlineActions as $row) {
    echo '<li data-marker-outline-action-title="' . htmlspecialchars((string) ($row['outline_title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-type="' . htmlspecialchars((string) ($row['action_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-safety="' . htmlspecialchars((string) ($row['safety'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-target-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-target-transition="' . htmlspecialchars((string) ($row['destination_action_target_page_transition']['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Outline security review: ' . htmlspecialchars((string) ($row['action_type'] ?? 'action'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
