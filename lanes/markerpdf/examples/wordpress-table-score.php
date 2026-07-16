<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableScorer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$reference = "| Block | Status |\n| Intro | Published |\n| Media | Draft |";
$hypothesis = "| Block | Status |\n| Intro | Published |\n| Media | Draff |";
$score = (new TableScorer())->scoreTable($hypothesis, $reference);

echo json_encode([
    'scenario' => 'wordpress-pdf-table-import-quality',
    'score' => $score,
    'passes_marker_table_threshold' => $score >= 0.70,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
