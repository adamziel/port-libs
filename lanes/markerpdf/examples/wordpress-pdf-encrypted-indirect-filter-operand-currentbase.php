<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildPdf = static function (string $filterObjectBody, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} WordPress indirect filter encrypted leak) Tj ET";
    $ownerValidation = str_repeat(substr($label, 0, 1), 32);
    $userValidation = str_repeat(substr($label, -1), 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter 9 0 R /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "9 0 obj\n{$filterObjectBody}\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

[$literalPdf, $literalContent, $literalOwner, $literalUser] = $buildPdf('(Standard)', 'LITERAL');
[$arrayPdf, $arrayContent, $arrayOwner, $arrayUser] = $buildPdf('[/Standard]', 'ARRAY');

$literalMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($literalPdf);
$literalReport = (new PdfSecurityPreflight())->analyze($literalPdf);
$arrayMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($arrayPdf);
$arrayReport = (new PdfSecurityPreflight())->analyze($arrayPdf);
$extractor = new PdfTextExtractor();

$literalPermission = is_array($literalReport['permission_preflight'] ?? null)
    ? $literalReport['permission_preflight']
    : [];
$arrayPermission = is_array($arrayReport['permission_preflight'] ?? null)
    ? $arrayReport['permission_preflight']
    : [];
$arrayDeclaration = is_array($arrayPermission['security_handler_declaration_review'] ?? null)
    ? $arrayPermission['security_handler_declaration_review']
    : [];

$encoded = json_encode([$literalMetadata, $literalReport, $arrayMetadata, $arrayReport], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded) && (
    str_contains($encoded, $literalContent)
    || str_contains($encoded, $arrayContent)
    || str_contains($encoded, $literalOwner)
    || str_contains($encoded, $literalUser)
    || str_contains($encoded, $arrayOwner)
    || str_contains($encoded, $arrayUser)
    || str_contains($encoded, strtoupper(bin2hex($literalOwner)))
    || str_contains($encoded, strtoupper(bin2hex($arrayUser)))
);

if ($extractor->extractPlainText($literalPdf) !== '' || $extractor->extractPlainText($arrayPdf) !== '') {
    throw new RuntimeException('Expected encrypted indirect Filter fixtures to keep text blocked.');
}

if (($literalMetadata['encryption']['filter'] ?? null) !== 'Standard') {
    throw new RuntimeException('Expected indirect literal Filter to resolve to Standard metadata for malformed-parameter review.');
}

if (($literalPermission['source'] ?? null) !== 'standard_security_handler_malformed_parameters') {
    throw new RuntimeException('Expected indirect literal Filter to fail closed as malformed Standard parameters.');
}

if (($arrayMetadata['encryption']['filter'] ?? null) !== null || ($arrayDeclaration['fail_closed'] ?? null) !== true) {
    throw new RuntimeException('Expected indirect composite Filter to fail closed without exposing a numeric handler name.');
}

if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted credential bytes and page text to stay out of preflight metadata.');
}

echo '<!-- markerpdf-encrypted-indirect-filter-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-indirect-filter-operand-currentbase',
    'native_boundary' => 'indirect security-handler Filter operands are resolved as single scalars or fail closed before WordPress text import',
    'literal_text_blocked' => $extractor->extractPlainText($literalPdf) === '',
    'literal_filter' => $literalMetadata['encryption']['filter'] ?? null,
    'literal_permission_source' => $literalPermission['source'] ?? null,
    'literal_permission_policy' => $literalPermission['policy'] ?? null,
    'literal_parameter_status' => $literalPermission['standard_security_handler_parameter_status'] ?? null,
    'literal_parameter_violations' => $literalPermission['standard_security_handler_parameter_violations'] ?? [],
    'array_text_blocked' => $extractor->extractPlainText($arrayPdf) === '',
    'array_filter' => $arrayMetadata['encryption']['filter'] ?? null,
    'array_permission_source' => $arrayPermission['source'] ?? null,
    'array_permission_policy' => $arrayPermission['policy'] ?? null,
    'array_security_handler_declaration_status' => $arrayDeclaration['status'] ?? null,
    'array_security_handler_filter_names' => $arrayDeclaration['filter_names'] ?? [],
    'raw_material_exposed' => $rawMaterialExposed,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF text remains blocked while indirect security-handler Filter operands are classified before WordPress import trusts any permission bits.</p>\n";
echo "<!-- /wp:paragraph -->\n";
