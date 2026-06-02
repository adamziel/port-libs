<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Fingerprint Body) Tj ET';
$permanentId = "WP PDF\x00ID-A";
$changingIdHex = '57502d5044462d49442d42';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Fingerprint Review PDF) /Producer (Fixture Writer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R /ID [(WP\\040PDF\\000ID-A) <{$changingIdHex}>] >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

echo '<!-- markerpdf-trailer-id-fingerprint-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF trailer /ID permanent and changing identifiers exposed as document fingerprint review metadata',
    'source' => $metadata['source'],
    'document_fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
    'permanent_id_bytes' => $metadata['trailer_ids']['permanent']['bytes'] ?? null,
    'changing_id_bytes' => $metadata['trailer_ids']['changing']['bytes'] ?? null,
    'changed_since_creation' => $metadata['trailer_ids']['changed_since_creation'] ?? null,
    'fingerprint_matches_permanent_id' => ($metadata['document_fingerprint'] ?? null) === hash('sha256', $permanentId),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-fingerprint ' . htmlspecialchars(json_encode([
    'fingerprint' => $metadata['document_fingerprint'] ?? null,
    'fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
    'permanent_id_hex' => $metadata['trailer_ids']['permanent']['hex'] ?? null,
    'changing_id_hex' => $metadata['trailer_ids']['changing']['hex'] ?? null,
    'changed_since_creation' => $metadata['trailer_ids']['changed_since_creation'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
