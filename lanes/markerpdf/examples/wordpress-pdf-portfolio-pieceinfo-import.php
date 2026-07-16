<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$exportXml = '<wp-export><post id="42"/></wp-export>';
$notes = 'Portfolio review notes';
$privateReview = 'BT /F1 12 Tf 72 720 Td (PieceInfo Private Leak) Tj ET';
$compressedPrivateReview = gzcompress($privateReview);
if (!is_string($compressedPrivateReview)) {
    throw new RuntimeException('Unable to compress PieceInfo private stream fixture.');
}
$pageContent = 'BT /F1 12 Tf 72 720 Td (Portfolio Cover) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /PieceInfo << /WPPortfolio << /LastModified (D:20260602043000Z) /Private << /Workflow (WordPress migration review) /Batch 7 /Kind /Portfolio >> >> >> /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /D /D (wp-export.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 /V true /E false >> /Modified << /Subtype /D /N (Modified) /O 2 >> /Size << /Subtype /Size /N (Size) /O 3 >> >> /Sort << /S [/Subject /Modified] /A [false true] >> >>\nendobj\n"
    . "6 0 obj\n<< /Kids [7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(review-notes.txt) (wp-export.xml)] /Names [(wp-export.xml) 10 0 R (review-notes.txt) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (wp-export.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /PieceInfo 31 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($exportXml) . " >> /Length " . strlen($exportXml) . " >>\nstream\n{$exportXml}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (review-notes.txt) /Desc (Portfolio review notes) /CI << /Subject (Editorial Notes) /Size " . strlen($notes) . " >> /PieceInfo 32 0 R /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Length " . strlen($notes) . " >>\nstream\n{$notes}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Subject (WordPress Export) /Modified (D:20260602043100Z) /Size " . strlen($exportXml) . " /Review << /Type /CollectionSubitem /D (Approved) /P (Status: ) >> >>\nendobj\n"
    . "31 0 obj\n<< /WPImporter << /LastModified (D:20260602043200Z) /Private << /ManifestId (wp-42) /Preserve true /Priority 2 >> >> >>\nendobj\n"
    . "32 0 obj\n<< /WPReview << /LastModified (D:20260602084600Z) /Private 33 0 R >> >>\nendobj\n"
    . "33 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length " . strlen($compressedPrivateReview) . " >>\nstream\n{$compressedPrivateReview}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

if (count($attachments) !== 2) {
    throw new RuntimeException('Expected two portfolio attachments.');
}

$first = $attachments[0];
if (($first['portfolio']['default_document'] ?? null) !== 'wp-export.xml') {
    throw new RuntimeException('Expected portfolio default document metadata.');
}
if (($first['piece_info']['WPImporter']['private']['ManifestId'] ?? null) !== 'wp-42') {
    throw new RuntimeException('Expected Filespec PieceInfo metadata.');
}
$second = $attachments[1] ?? null;
if (!is_array($second) || ($second['piece_info']['WPReview']['private_stream']['object'] ?? null) !== 33) {
    throw new RuntimeException('Expected Filespec PieceInfo private stream metadata.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-portfolio-pieceinfo-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-portfolio-pieceinfo-name-tree-parser',
    'native_boundary' => 'catalog /Collection plus EmbeddedFiles name-tree /CI and /PieceInfo review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'portfolio_view' => $first['portfolio']['view'] ?? null,
    'default_document' => $first['portfolio']['default_document'] ?? null,
    'schema_fields' => array_keys($first['portfolio']['schema'] ?? []),
    'sort_keys' => $first['portfolio']['sort']['keys'] ?? [],
    'portfolio_item_subjects' => array_map(
        static fn (array $attachment): ?string => $attachment['portfolio_item']['Subject'] ?? null,
        $attachments
    ),
    'piece_info_apps' => array_keys($first['piece_info'] ?? []),
    'private_stream_piece_info_objects' => array_values(array_filter(array_map(
        static fn (array $attachment): ?int => $attachment['piece_info']['WPReview']['private_stream']['object'] ?? null,
        $attachments
    ))),
    'catalog_piece_info_apps' => array_keys($first['catalog_piece_info'] ?? []),
    'excluded_attachment_payload_text' => !str_contains($plainText, 'wp-export') && !str_contains($plainText, 'Portfolio review notes'),
    'excluded_pieceinfo_private_stream_text' => !str_contains($plainText, 'PieceInfo Private Leak'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n\n";
    echo '<!-- markerpdf:portfolio-attachment ' . $htmlJson([
        'name' => $attachment['name'],
        'filename' => $attachment['filename'],
        'description' => $attachment['description'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'mime_type' => $attachment['mime_type'] ?? null,
        'portfolio_item' => $attachment['portfolio_item'] ?? [],
        'piece_info' => $attachment['piece_info'] ?? [],
        'catalog_piece_info' => $attachment['catalog_piece_info'] ?? [],
    ]) . " -->\n\n";
}
