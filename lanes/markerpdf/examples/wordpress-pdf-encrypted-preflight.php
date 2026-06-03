<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CorePdfConverter;
use PortLibs\MarkerPDF\PdfSecurityPreflight;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /P -20 /EncryptMetadata false /O <00> /U <01> >>\nendobj\n"
    . "trailer << /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF\n";
$path = sys_get_temp_dir() . '/markerpdf-encrypted-preflight-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, $pdf);

$pipelineCalls = 0;
try {
    $security = (new PdfSecurityPreflight())->analyze($pdf);
    $conversion = (new CorePdfConverter())->convertWithSuppliedPages(
        $path,
        [['pnum' => 0, 'blocks' => []]],
        [],
        static function () use (&$pipelineCalls): array {
            $pipelineCalls++;

            return ['text' => 'should not run', 'images' => [], 'metadata' => []];
        }
    );
} finally {
    @unlink($path);
}

echo json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permissions-preflight',
    'encrypted' => $security['encrypted'],
    'security_stage' => $security['text_extraction_policy'],
    'conversion_stage' => $conversion['context']['stage'],
    'permission_allows_text_extraction' => $security['content_extraction_allowed'],
    'permissions' => $security['permission_preflight'],
    'requires_decryption' => $security['permission_preflight']['requires_password_for_content_extraction'],
    'should_queue_models' => !$security['encrypted'],
    'pipeline_calls' => $pipelineCalls,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'blocker' => $security['permission_preflight']['content_extraction_boundary'] ?? $security['import_decision'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
