<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Typed docs Untyped docs Filespec decoy Typed highlight XObject decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 11 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Typed link review) /A << /S /URI /URI (https://example.com/typed-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Link /Rect [160 700 250 718] /Contents (Type omitted link review) /A << /S /URI /URI (https://example.com/type-omitted-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Filespec /Subtype /Link /Rect [260 700 365 718] /Contents (Filespec decoy link review) /A << /S /URI /URI (https://example.com/filespec-decoy-link) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /XObject /Subtype /Highlight /Rect [490 700 590 718] /QuadPoints [490 718 590 718 490 700 590 700] /Contents (XObject highlight decoy review) /T (Decoy QA) /C [1 0 0] >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [375 700 480 718] /QuadPoints [375 718 480 718 375 700 480 700] /Contents (Typed highlight review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 590.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 590.0, 718.0],
            'spans' => [
                ['text' => 'Typed docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Untyped docs', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Filespec decoy', 'bbox' => [260.0, 700.0, 365.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Typed highlight', 'bbox' => [375.0, 700.0, 480.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' XObject decoy', 'bbox' => [490.0, 700.0, 590.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$linkPages = $linkExtractor->extractPageLinks($pdf);
$markupPages = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotationPages, $linkPages, $markupPages, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-annotation-link-explicit-type-boundary',
    'native_boundary' => 'Page annotation collection accepts /Type /Annot or omitted /Type dictionaries, but rejects explicit non-/Annot dictionaries before WordPress link and markup promotion.',
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_subtypes' => array_column($annotationPages[0]['annotations'] ?? [], 'subtype'),
    'promoted_link_objects' => array_column($linkPages[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_uris' => array_column($linkPages[0]['links'] ?? [], 'uri'),
    'markup_annotation_objects' => array_column($markupPages[0]['markups'] ?? [], 'annotation_object'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'type_omitted_link_promoted' => in_array('https://example.com/type-omitted-link', array_column($linkPages[0]['links'] ?? [], 'uri'), true),
    'filespec_link_excluded' => !str_contains($encodedReview, 'filespec-decoy-link'),
    'xobject_markup_excluded' => !str_contains($encodedReview, 'XObject highlight decoy review'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Typed link review')
        || str_contains($plainText, 'Filespec decoy link review')
        || str_contains($plainText, 'XObject highlight decoy review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['annotation_objects'] ?? []) !== [7, 8, 11]) {
    throw new RuntimeException('Expected only annotation-typed or type-omitted page annotations to remain in review metadata.');
}
if (($summary['promoted_link_objects'] ?? []) !== [7, 8]) {
    throw new RuntimeException('Expected only safe annotation link objects to promote into WordPress spans.');
}
if (($summary['markup_annotation_objects'] ?? []) !== [11]) {
    throw new RuntimeException('Expected only the true text-markup annotation to remain in review metadata.');
}
if (($summary['filespec_link_excluded'] ?? false) !== true || ($summary['xobject_markup_excluded'] ?? false) !== true) {
    throw new RuntimeException('Explicit non-/Annot dictionaries must stay out of WordPress annotation imports.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation payload text must not be imported as visible WordPress text.');
}

echo '<!-- markerpdf:pdf-annotation-link-explicit-type-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
