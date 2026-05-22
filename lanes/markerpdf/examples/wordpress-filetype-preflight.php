<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\FiletypeDetector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$detector = new FiletypeDetector();
$fixture = __DIR__ . '/../fixtures/wordpress-import-content.pdf';

echo json_encode([
    'scenario' => 'wordpress-pdf-import-filetype-preflight',
    'fixture' => basename($fixture),
    'detected_filetype' => $detector->findFiletype($fixture),
    'accepts_pdf_upload' => $detector->findFiletype($fixture) === 'pdf',
    'rejects_extension_spoofed_zip' => $detector->findFiletypeFromBytes("PK\x03\x04fake docx payload") === 'other',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
