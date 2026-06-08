<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Remote ok Duplicate UF Duplicate F Safe URI) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Remote ok review) /A << /S /GoToR /F 20 0 R /D (Remote Appendix) /NewWindow true >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Duplicate UF review) /A << /S /GoToR /F 21 0 R /D (Duplicate UF Target) /NewWindow true >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Duplicate F review) /A << /S /GoToR /F 22 0 R /D [2 /FitH 720] /NewWindow false >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 450 718] /Contents (Safe URI review) /A << /S /URI /URI (https://example.com/filespec-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (fallback-current.pdf) /UF (remote-current.pdf) >>\nendobj\n"
    . "21 0 obj\n<< /Type /Filespec /F (fallback-duplicate-uf.pdf) /UF (safe-duplicate-uf.pdf) /UF (evil-duplicate-uf.pdf) >>\nendobj\n"
    . "22 0 obj\n<< /Type /Filespec /F (safe-duplicate-f.pdf) /F (evil-duplicate-f.pdf) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 450.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'spans' => [
                ['text' => 'Remote ok', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Duplicate UF', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Duplicate F', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [370.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($wordpressText !== 'Remote ok Duplicate UF Duplicate F [Safe URI](https://example.com/filespec-boundary)') {
    throw new RuntimeException('Expected duplicate Filespec rows to stay out of WordPress Markdown links.');
}
if (str_contains($encodedReview, 'evil-duplicate-uf.pdf') || str_contains($encodedReview, 'evil-duplicate-f.pdf')) {
    throw new RuntimeException('Duplicate Filespec file targets leaked into annotation/link review metadata.');
}
if (!str_contains($encodedReview, 'remote-current.pdf') || !str_contains($encodedReview, 'filespec-boundary')) {
    throw new RuntimeException('Expected clean remote FileSpec and safe URI rows to remain reviewable.');
}
if (str_contains($visibleText, 'remote-current.pdf') || str_contains($visibleText, 'filespec-boundary')) {
    throw new RuntimeException('Link action metadata leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-link-filespec-duplicate-key-boundary',
    'native_boundary' => 'Remote GoToR Link action FileSpec dictionaries with duplicate file-name keys fail closed before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safety' => array_map(
        static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
        $annotations[0]['annotations'] ?? []
    ),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_remote_files' => array_values(array_filter(array_column($links[0]['links'] ?? [], 'file'))),
    'duplicate_filespec_targets_promoted' => str_contains($encodedReview, 'evil-duplicate-uf.pdf')
        || str_contains($encodedReview, 'evil-duplicate-f.pdf'),
    'safe_uri_promoted' => str_contains($wordpressText, 'https://example.com/filespec-boundary'),
    'visible_text_imported' => $visibleText === 'Remote ok Duplicate UF Duplicate F Safe URI',
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-filespec-duplicate-key-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
