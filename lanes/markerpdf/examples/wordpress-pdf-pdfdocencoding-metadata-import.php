<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (PDFDocEncoding Metadata Body) Tj ET';
$title = 'WordPress' . chr(0x80) . ' PDF ' . chr(0x93) . chr(0x94) . ' Import ' . chr(0xa0);
$author = chr(0x95) . 'ukasz Editor; Data' . chr(0x92) . 'Team';
$subject = strtoupper(bin2hex('Review ' . chr(0x8d) . 'quotes' . chr(0x8e) . ' ' . chr(0x8a) . ' minus'));
$keywords = 'wp' . chr(0x8b) . 'percent, caf' . chr(0xe9) . '; ' . chr(0x9b) . 'odz';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title ({$title}) /Author ({$author}) /Subject <{$subject}> /Keywords ({$keywords}) /Creator (Native\\201Metadata) /Producer (Fixture\\205Writer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

echo '<!-- markerpdf-pdfdocencoding-metadata-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDFDocEncoding trailer /Info text strings decoded before WordPress metadata review',
    'source' => $metadata['source'],
    'decoded_pdfdocencoding_title' => ($metadata['title'] ?? null) === 'WordPress• PDF ﬁﬂ Import €',
    'decoded_pdfdocencoding_authors' => $metadata['authors'] ?? [],
    'decoded_pdfdocencoding_keywords' => $metadata['keywords'] ?? [],
    'metadata_not_visible_text' => !str_contains($plainText, 'WordPress'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . htmlspecialchars(json_encode([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
