<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$movieAppearance = 'BT /F1 12 Tf 0 0 Td (Movie Poster Noise) Tj ET';
$soundAppearance = 'BT /F1 12 Tf 0 0 Td (Sound Icon Noise) Tj ET';
$soundBytes = "RIFF fake bytes with (Leaked Sound Text) Tj ET";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 15 0 R >> >> /Annots [5 0 R 6 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Movie /Rect [72 540 320 700] /T (Training clip) /Contents (Movie must be reviewed) /Movie 9 0 R /A 10 0 R /AP << /N 11 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Sound /Rect [72 500 180 535] /T (Narration note) /Contents (Audio note) /Name /Speaker /Sound 12 0 R /AP << /N 13 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /F 14 0 R /T (Intro movie title) /Aspect [640 360] /Rotate 90 /Poster true >>\nendobj\n"
    . "10 0 obj\n<< /Start 1.5 /Duration 12 /Rate 1.25 /Volume .75 /ShowControls true /Mode /Once /Synchronous false /FWScale [1 1] /FWPosition [0.5 0.5] >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 15 0 R >> >> /Length " . strlen($movieAppearance) . " >>\nstream\n{$movieAppearance}\nendstream\nendobj\n"
    . "12 0 obj\n<< /R 44100 /C 2 /B 16 /E /Signed /CO /FlateDecode /Length " . strlen($soundBytes) . " >>\nstream\n{$soundBytes}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 15 0 R >> >> /Length " . strlen($soundAppearance) . " >>\nstream\n{$soundAppearance}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /Filespec /F (training.mov) >>\nendobj\n"
    . "15 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$reviewPages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($pdf);
$annotations = [];
foreach ($reviewPages as $page) {
    foreach ($page['annotations'] as $annotation) {
        $annotation['page'] = $page['pnum'] + 1;
        $annotations[] = $annotation;
    }
}

echo '<!-- markerpdf-pdf-sound-movie-annotation-review ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'sound/movie annotations are review metadata only; media dictionaries are parsed without playing payloads',
    'review_annotation_count' => count($annotations),
    'media_payload_text_excluded' => !str_contains($plainText, 'Movie Poster Noise')
        && !str_contains($plainText, 'Sound Icon Noise')
        && !str_contains($plainText, 'Leaked Sound Text'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $movieFile = $annotation['movie']['file_names'][0] ?? '';
    $sampleRate = $annotation['sound']['sample_rate'] ?? '';
    $label = $annotation['movie']['title'] ?? $annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype'];

    echo '<li data-marker-annotation-subtype="' . htmlspecialchars($annotation['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-page="' . htmlspecialchars((string) $annotation['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-movie-file="' . htmlspecialchars((string) $movieFile, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-sound-rate="' . htmlspecialchars((string) $sampleRate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-executes-media="false">'
        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
