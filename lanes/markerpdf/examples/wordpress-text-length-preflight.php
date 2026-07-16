<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = __DIR__ . '/../fixtures/wordpress-import-content.pdf';
$extractor = new PdfTextExtractor();
$text = $extractor->naiveGetText((string) file_get_contents($fixture));
$textLength = $extractor->getLengthOfText($fixture);
$minimumUsefulCharacters = 20;

echo json_encode([
    'scenario' => 'wordpress-markerpdf-text-length-preflight',
    'purpose' => 'Apply marker.pdf.extract_text get_length_of_text semantics before queuing a heavy PDF import job.',
    'fixture' => basename($fixture),
    'naive_text' => $text,
    'text_length' => $textLength,
    'min_length' => $minimumUsefulCharacters,
    'queue_for_conversion' => $textLength >= $minimumUsefulCharacters,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
