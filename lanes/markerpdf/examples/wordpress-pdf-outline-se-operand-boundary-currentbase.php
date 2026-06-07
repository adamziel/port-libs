<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf /ChapterTitle << /MCID 0 >> BDC 72 720 Td (WordPress outline SE operand boundary visible body) Tj EMC ET';
$selectedPayload = '<wp-export><post id="wordpress-selected-outline-se"/></wp-export>';
$hiddenPayload = '<wp-export><post id="wordpress-hidden-outline-se"/></wp-export>';
$selectedChecksum = strtoupper(hash('md5', $selectedPayload));
$hiddenChecksum = strtoupper(hash('md5', $hiddenPayload));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /Outlines 40 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>\nendobj\n"
    . "41 0 obj\n<< /Title (WordPress malformed SE operand boundary) /Parent 40 0 R /Dest [3 0 R /FitH 720] /SE 60 0 R 61 0 R /A 42 0 R /F 3 >>\nendobj\n"
    . "42 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] /Next 43 0 R >>\nendobj\n"
    . "43 0 obj\n<< /S /URI /URI (https://example.com/wordpress-outline-se-operand-boundary) >>\nendobj\n"
    . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ChapterTitle /H1 /HiddenTitle /H2 >> /ParentTree 55 0 R /K [60 0 R 61 0 R] >>\nendobj\n"
    . "55 0 obj\n<< /Nums [0 [60 0 R 61 0 R]] >>\nendobj\n"
    . "60 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /Pg 3 0 R /T (WordPress selected SE title) /Alt (Selected outline SE summary) /AF [70 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /StructElem /S /HiddenTitle /P 50 0 R /Pg 3 0 R /T (WordPress hidden trailing SE title) /Alt (Hidden trailing outline SE summary) /AF [72 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "70 0 obj\n<< /Type /Filespec /F (wordpress-selected-outline-source.xml) /Desc (Selected outline source payload) /AFRelationship /Source /EF << /F 71 0 R >> >>\nendobj\n"
    . "71 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($selectedPayload) . " /CheckSum <{$selectedChecksum}> >> /Length " . strlen($selectedPayload) . " >>\nstream\n{$selectedPayload}\nendstream\nendobj\n"
    . "72 0 obj\n<< /Type /Filespec /F (wordpress-hidden-outline-source.xml) /Desc (Hidden outline source payload) /AFRelationship /Alternative /EF << /F 73 0 R >> >>\nendobj\n"
    . "73 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($hiddenPayload) . " /CheckSum <{$hiddenChecksum}> >> /Length " . strlen($hiddenPayload) . " >>\nstream\n{$hiddenPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$review = $item['structure_element_boundary_review'] ?? [];
$navigationItem = $navigation['outline'][0] ?? [];
$navigationReview = $navigationItem['structure_element_boundary_review'] ?? [];
$actions = $navigation['outline_action_review_actions'] ?? [];
$ownedReviewJson = json_encode([
    $review,
    $navigationReview,
    array_map(
        static fn (array $action): array => is_array($action['outline_structure_element_boundary_review'] ?? null)
            ? $action['outline_structure_element_boundary_review']
            : [],
        $actions
    ),
], JSON_UNESCAPED_SLASHES);

if (($review['status'] ?? null) !== 'rejected_malformed_outline_item_structure_element_operand') {
    throw new RuntimeException('Expected malformed outline /SE operand boundary review.');
}
if (($review['object_number'] ?? null) !== 60 || ($review['trailing_reference_object_numbers'] ?? null) !== [61]) {
    throw new RuntimeException('Expected selected and trailing outline /SE references to be review metadata.');
}
if (array_key_exists('structure_element', $item) || array_key_exists('structure_element_role', $item)) {
    throw new RuntimeException('Expected malformed outline /SE to suppress structure-element promotion.');
}
if (($navigationReview['status'] ?? null) !== 'rejected_malformed_outline_item_structure_element_operand') {
    throw new RuntimeException('Expected navigation review to carry the malformed outline /SE boundary.');
}
foreach ($actions as $action) {
    if (($action['outline_structure_element_boundary_review']['structure_element_promoted'] ?? null) !== false) {
        throw new RuntimeException('Expected action rows to keep malformed outline /SE review-only.');
    }
    if (array_key_exists('outline_structure_element', $action) || array_key_exists('outline_structure_element_role', $action)) {
        throw new RuntimeException('Expected action rows to omit promoted outline structure metadata.');
    }
}
if (!is_string($ownedReviewJson)
    || str_contains($ownedReviewJson, 'WordPress selected SE title')
    || str_contains($ownedReviewJson, 'WordPress hidden trailing SE title')
    || str_contains($ownedReviewJson, $selectedPayload)
    || str_contains($ownedReviewJson, $hiddenPayload)
) {
    throw new RuntimeException('Expected outline-owned /SE review to omit StructElem titles and payload bytes.');
}
if (str_contains($plainText, 'WordPress malformed SE operand boundary')
    || str_contains($plainText, 'WordPress selected SE title')
    || str_contains($plainText, 'WordPress hidden trailing SE title')
    || str_contains($plainText, 'wordpress-outline-se-operand-boundary')
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected visible WordPress text to exclude outline /SE metadata and payloads.');
}

echo '<!-- markerpdf-outline-se-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-se-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-structure-element-review',
    'native_boundary' => 'outline item /SE with trailing top-level operands is review-only and suppresses structure-element promotion',
    'outline_title' => $item['title'] ?? null,
    'outline_page_number' => $item['page_number'] ?? null,
    'boundary_status' => $review['status'] ?? null,
    'selected_structure_reference' => $review['object_number'] ?? null,
    'trailing_structure_references' => $review['trailing_reference_object_numbers'] ?? [],
    'structure_element_promoted' => array_key_exists('structure_element', $item),
    'navigation_boundary_status' => $navigationReview['status'] ?? null,
    'action_rows_carry_boundary_review' => array_reduce(
        $actions,
        static fn (bool $ok, array $action): bool => $ok
            && (($action['outline_structure_element_boundary_review']['status'] ?? null)
                === 'rejected_malformed_outline_item_structure_element_operand'),
        true
    ),
    'owned_review_payload_omitted' => is_string($ownedReviewJson)
        && !str_contains($ownedReviewJson, $selectedPayload)
        && !str_contains($ownedReviewJson, $hiddenPayload),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress malformed SE operand boundary')
        && !str_contains($plainText, 'WordPress hidden trailing SE title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline SE boundary review\"><ul>\n";
echo '<li data-marker-outline-se-boundary="' . htmlspecialchars((string) ($review['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-se-promoted="false">'
    . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
