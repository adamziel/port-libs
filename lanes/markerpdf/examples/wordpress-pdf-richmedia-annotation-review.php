<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$screenAppearance = 'BT /F1 12 Tf 0 0 Td (Embedded Video Noise) Tj ET';
$richMediaAppearance = 'BT /F1 12 Tf 0 0 Td (Rich Media Noise) Tj ET';
$widgetAppearance = 'BT /F1 12 Tf 0 0 Td (Printable Widget Review) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 17 0 R >> >> /Annots [5 0 R 6 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 600 260 700] /T (Training video) /Contents (MP4 launch annotation) /A 12 0 R /AA << /PV 13 0 R >> /AP << /N 9 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 500 300 590] /Alt (Rich media package) /RichMediaContent 15 0 R /A << /S /URI /URI (https://cdn.example.com/asset.mp4) >> /AP << /N 10 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 460 260 490] /AP << /N 11 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($richMediaAppearance) . " >>\nstream\n{$richMediaAppearance}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($widgetAppearance) . " >>\nstream\n{$widgetAppearance}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /Rendition /R << /C 16 0 R /OP 0 >> >>\nendobj\n"
    . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\(\\'do not run\\'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /RichMediaContent << /Assets << /Names [(intro-video.mp4) 16 0 R] >> >> >>\nendobj\n"
    . "16 0 obj\n<< /Type /Filespec /F (intro-video.mp4) >>\nendobj\n"
    . "17 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$lines = $textExtractor->extractTextLines($pdf);
$reviewPages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($pdf);
$annotations = [];
foreach ($reviewPages as $page) {
    foreach ($page['annotations'] as $annotation) {
        $annotations[] = ['page' => $page['pnum'] + 1, ...$annotation];
    }
}

echo '<!-- markerpdf-pdf-richmedia-annotation-review ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'screen/rich-media annotations are review metadata only; their appearances are excluded from paragraph extraction',
    'review_annotation_count' => count($annotations),
    'media_appearance_text_excluded' => !str_contains($plainText, 'Embedded Video Noise') && !str_contains($plainText, 'Rich Media Noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $label = $annotation['title'] ?? $annotation['alternate_text'] ?? $annotation['contents'] ?? $annotation['subtype'];
    $actions = $annotation['action_types'] === [] ? 'none' : implode(',', $annotation['action_types']);
    $files = $annotation['file_names'] === [] ? 'none' : implode(',', $annotation['file_names']);
    echo '<li data-marker-annotation-subtype="' . htmlspecialchars($annotation['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-page="' . htmlspecialchars((string) $annotation['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-actions="' . htmlspecialchars($actions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-files="' . htmlspecialchars($files, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
