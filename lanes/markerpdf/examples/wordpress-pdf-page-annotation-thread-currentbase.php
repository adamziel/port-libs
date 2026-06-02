<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageStream = 'BT /F1 12 Tf 72 720 Td (Visible page text) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n{$pageStream}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 676 280 724] /Contents (Root note review only) /T (Editor) /NM /root-note >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [92 636 300 668] /Contents (Accepted reply review only) /T (Reviewer A) /NM /reply-accepted /IRT 6 0 R /RT /R /State /Accepted /StateModel /Review /Popup 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [92 596 300 628] /Contents (Grouped reply review only) /T (Reviewer B) /NM /reply-group /IRT 6 0 R /RT /Group /State /Marked /StateModel /Marked >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [92 556 300 588] /Contents (Detached reply review only) /T (Reviewer C) /NM /reply-detached /IRT 90 0 R /RT /R /State /Rejected /StateModel /Review >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [320 620 500 690] /Parent 7 0 R /Open true /Contents (Reply popup review only) >>\nendobj\n"
    . "90 0 obj\n<< /Type /Annot /Subtype /Text /Rect [20 20 40 40] /Contents (Detached stale root must not promote) /T (Detached root) >>\nendobj\n"
    . "%%EOF";

$page = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0] ?? ['annotations' => []];
$annotations = $page['annotations'] ?? [];
$threads = $page['annotation_threads'] ?? [];
$detachedReplies = $page['detached_annotation_thread_replies'] ?? [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (count($annotations) !== 4 || count($threads) !== 1 || count($detachedReplies) !== 1) {
    throw new RuntimeException('Expected one page annotation reply thread plus one detached reply review row.');
}
if (($threads[0]['reply_annotation_objects'] ?? []) !== [7, 8]) {
    throw new RuntimeException('Expected current-page annotation replies to stay linked to root annotation 6.');
}
if (($detachedReplies[0]['in_reply_to_object'] ?? null) !== 90 || ($detachedReplies[0]['current_page_thread'] ?? true) !== false) {
    throw new RuntimeException('Expected detached /IRT targets to remain review-only references.');
}
if (
    !str_contains($plainText, 'Visible page text')
    || str_contains($plainText, 'Root note review only')
    || str_contains($plainText, 'Accepted reply review only')
    || str_contains($plainText, 'Grouped reply review only')
    || str_contains($plainText, 'Detached reply review only')
    || str_contains($plainText, 'Reply popup review only')
    || str_contains($plainText, 'Detached stale root must not promote')
) {
    throw new RuntimeException('Expected annotation thread payloads to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-annotation-thread-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-annotation-parser',
    'native_boundary' => 'page /Annots reply-thread /IRT /RT /State /StateModel review metadata before WordPress import',
    'review_annotation_count' => count($annotations),
    'thread_root_objects' => array_column($threads, 'root_annotation_object'),
    'reply_annotation_objects' => $threads[0]['reply_annotation_objects'] ?? [],
    'reply_type_labels' => $threads[0]['reply_type_labels'] ?? [],
    'states' => $threads[0]['states'] ?? [],
    'state_models' => $threads[0]['state_models'] ?? [],
    'detached_reply_objects' => array_column($detachedReplies, 'annotation_object'),
    'detached_reply_targets' => array_column($detachedReplies, 'in_reply_to_object'),
    'visible_text_excludes_annotation_payloads' => str_contains($plainText, 'Visible page text')
        && !str_contains($plainText, 'Root note review only')
        && !str_contains($plainText, 'Accepted reply review only')
        && !str_contains($plainText, 'Grouped reply review only')
        && !str_contains($plainText, 'Detached reply review only')
        && !str_contains($plainText, 'Reply popup review only')
        && !str_contains($plainText, 'Detached stale root must not promote'),
    'executes_pdf_actions' => false,
    'renders_annotations' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $thread = $annotation['reply_thread'] ?? [];
    $attrs = [
        'data-marker-annotation-object' => (string) ($annotation['annotation_object'] ?? ''),
        'data-marker-annotation-subtype' => (string) ($annotation['subtype'] ?? ''),
        'data-marker-review-only' => 'true',
        'data-marker-current-page-thread' => (($thread['current_page_thread'] ?? false) === true) ? 'true' : 'false',
    ];
    if (isset($thread['root_annotation_object'])) {
        $attrs['data-marker-thread-root'] = (string) $thread['root_annotation_object'];
    }
    if (isset($thread['in_reply_to_object'])) {
        $attrs['data-marker-reply-to'] = (string) $thread['in_reply_to_object'];
    }
    if (isset($thread['state'])) {
        $attrs['data-marker-state'] = (string) $thread['state'];
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars((string) ($annotation['name'] ?? $annotation['subtype'] ?? 'annotation'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
