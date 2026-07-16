<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Remote dict Duplicate dest Safe URI) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Contents (Remote dict review) /A << /S /GoToR /F (remote-dict.pdf) /D 20 0 R /NewWindow true >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [180 700 300 718] /Contents (Duplicate remote dest review) /A << /S /GoToR /F (duplicate-remote.pdf) /D 21 0 R /NewWindow false >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 390 718] /Contents (Safe URI review) /A << /S /URI /URI (https://example.com/remote-dictionary-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /D [2 /FitH 720] >>\nendobj\n"
    . "21 0 obj\n<< /D [3 /FitH 640] /D (Stale Remote Target) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 390.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'spans' => [
                ['text' => 'Remote dict', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Duplicate dest', 'bbox' => [180.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [310.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedPromotedRows = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$annotationSafeties = array_map(
    static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-annotation-remote-gotor-dictionary-boundary',
    'native_boundary' => 'Remote GoToR destination dictionaries with duplicate /D or /S keys fail closed before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safeties' => $annotationSafeties,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'remote_dict_file' => $links[0]['links'][0]['file'] ?? null,
    'remote_dict_destination_page' => $links[0]['links'][0]['destination_page'] ?? null,
    'duplicate_destination_promoted' => str_contains($encodedPromotedRows, 'duplicate-remote.pdf')
        || str_contains($encodedPromotedRows, 'Stale Remote Target'),
    'safe_uri_promoted' => str_contains($wordpressText, 'https://example.com/remote-dictionary-boundary'),
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => $visibleText === 'Remote dict Duplicate dest Safe URI',
    'annotation_payload_text_visible' => str_contains($visibleText, 'Duplicate remote dest review')
        || str_contains($visibleText, 'Stale Remote Target'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    if (($summary['promoted_link_objects'] ?? []) !== [7, 9]) {
        throw new RuntimeException('Expected only the valid remote GoToR and safe URI annotations to be promoted.');
    }
    if (($summary['annotation_action_safeties'] ?? []) !== ['remote-document-review', 'unsupported-action-review', 'review-uri']) {
        throw new RuntimeException('Expected duplicate destination dictionary to remain unsupported review metadata.');
    }
    if (($summary['remote_dict_file'] ?? null) !== 'remote-dict.pdf' || ($summary['remote_dict_destination_page'] ?? null) !== 2) {
        throw new RuntimeException('Expected valid remote destination dictionary metadata.');
    }
    if (($summary['duplicate_destination_promoted'] ?? true) !== false) {
        throw new RuntimeException('Duplicate remote destination dictionary leaked into promoted WordPress metadata.');
    }
    if (($summary['wordpress_markdown'] ?? '') !== 'Remote dict Duplicate dest [Safe URI](https://example.com/remote-dictionary-boundary)') {
        throw new RuntimeException('Expected safe URI promotion while duplicate remote destination stays plain text.');
    }
    if (($summary['visible_text_imported'] ?? false) !== true || ($summary['annotation_payload_text_visible'] ?? true) !== false) {
        throw new RuntimeException('Expected visible text isolation for annotation/action operands.');
    }
}

echo '<!-- markerpdf-pdf-link-annotation-remote-gotor-dictionary-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
