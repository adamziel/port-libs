<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (PDF/A Ready Body) Tj ET';
$profileBytes = "ICC profile bytes for native PDF/A import review\n";
$compressedProfile = gzcompress($profileBytes);
if (!is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress ICC profile fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB IEC61966-2.1) /OutputCondition (sRGB display profile) /RegistryName (http://www.color.org) /Info (PDF/A sRGB) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (($metadata['pdfa']['has_output_intent'] ?? false) !== true) {
    throw new RuntimeException('Expected native PDF/A output intent metadata.');
}

$intent = $metadata['output_intents'][0] ?? [];
$profile = is_array($intent['dest_output_profile'] ?? null) ? $intent['dest_output_profile'] : [];

echo '<!-- markerpdf-pdfa-outputintent-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF catalog /OutputIntents PDF/A metadata plus ICC profile stream review data before WordPress import',
    'source' => $metadata['source'],
    'pdfa' => $metadata['pdfa'],
    'profile_hash_matches' => ($profile['sha256'] ?? null) === hash('sha256', $profileBytes),
    'profile_stream_excluded_from_text' => !str_contains($plainText, 'ICC profile bytes'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>PDF/A output intent: ' . htmlspecialchars($intent['output_condition_identifier'] ?? 'unknown', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:pdfa-output-intent ' . htmlspecialchars(json_encode([
    'subtype' => $intent['subtype'] ?? null,
    'output_condition' => $intent['output_condition'] ?? null,
    'registry_name' => $intent['registry_name'] ?? null,
    'info' => $intent['info'] ?? null,
    'profile' => $profile,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
