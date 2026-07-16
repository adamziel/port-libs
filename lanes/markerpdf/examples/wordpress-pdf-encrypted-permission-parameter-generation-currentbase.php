<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (int $parameterGeneration): array {
    $content = "BT /F1 12 Tf 72 720 Td (WordPress generation {$parameterGeneration} encrypted parameter leak) Tj ET";
    $ownerValidation = str_repeat('W', 32);
    $userValidation = str_repeat('G', 32);
    $hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber][$generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(
        5,
        0,
        '<< /Filter /Standard /V 7 ' . $parameterGeneration . ' R /R 8 ' . $parameterGeneration . ' R'
            . ' /Length 9 ' . $parameterGeneration . ' R'
            . ' /O ' . $hex($ownerValidation)
            . ' /U ' . $hex($userValidation)
            . ' /P -44 /EncryptMetadata true >>'
    );
    $addObject(7, 0, '2');
    $addObject(7, 1, '4');
    $addObject(8, 0, '3');
    $addObject(8, 1, '4');
    $addObject(9, 0, '40');
    $addObject(9, 1, '128');

    $xrefOffset = strlen($pdf);
    $row = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 10\n";
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        if ($objectNumber === 0) {
            $pdf .= $row(0, 65535, 'f');
            continue;
        }
        if (in_array($objectNumber, [7, 8, 9], true)) {
            $pdf .= $row($offsets[$objectNumber][1], 1);
            continue;
        }
        if (!isset($offsets[$objectNumber][0])) {
            $pdf .= $row(0, 65535, 'f');
            continue;
        }
        $pdf .= $row($offsets[$objectNumber][0], 0);
    }
    $pdf .= "trailer\n<< /Size 10 /Root 1 0 R /Encrypt 5 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$summarize = static function (int $parameterGeneration) use ($buildPdf): array {
    [$pdf, $content, $ownerValidation, $userValidation] = $buildPdf($parameterGeneration);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
    $parameterReview = is_array($permission['standard_security_handler_parameter_review'] ?? null)
        ? $permission['standard_security_handler_parameter_review']
        : [];
    $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

    $rowSummary = [];
    foreach ($parameterReview['parameter_declaration_review']['rows'] ?? [] as $row) {
        if (!is_array($row) || !is_string($row['pdf_name'] ?? null)) {
            continue;
        }
        $rowSummary[$row['pdf_name']] = [
            'status' => $row['selected_entry_status'] ?? null,
            'reference_generation' => $row['selected_entry_reference_generation'] ?? null,
            'resolved_generation' => $row['selected_entry_resolved_generation'] ?? null,
            'integer' => $row['selected_integer_value'] ?? null,
        ];
    }

    if ($plainText !== '') {
        throw new RuntimeException('Expected encrypted content to stay blocked.');
    }
    if (
        !is_string($encoded)
        || str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
    ) {
        throw new RuntimeException('Expected encrypted payload and authentication bytes to remain redacted.');
    }

    return [
        'parameter_generation' => $parameterGeneration,
        'plain_text_blocked' => true,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'parameter_status' => $parameterReview['status'] ?? null,
        'parameters_well_formed' => $parameterReview['parameters_well_formed'] ?? null,
        'violations' => $parameterReview['violations'] ?? [],
        'rows' => $rowSummary,
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ];
};

$current = $summarize(1);
$stale = $summarize(0);

if (($current['parameters_well_formed'] ?? null) !== true) {
    throw new RuntimeException('Expected current generation security-handler parameters to be well formed.');
}
if (($stale['parameters_well_formed'] ?? null) !== false) {
    throw new RuntimeException('Expected stale generation security-handler parameters to fail closed.');
}

echo '<!-- markerpdf-encrypted-permission-parameter-generation-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-parameter-generation-currentbase',
    'native_boundary' => 'Standard security-handler /V /R /Length indirect scalar generations are reviewed before permission preflight',
    'current_generation' => $current,
    'stale_generation' => $stale,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF content remains blocked. Standard security-handler parameter generations are reviewed before WordPress import and do not trigger decryption, permission enforcement, Python models, or external PDF tools.</p>\n";
echo "<!-- /wp:paragraph -->\n";
