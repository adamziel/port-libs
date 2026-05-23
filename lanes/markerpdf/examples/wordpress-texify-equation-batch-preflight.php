<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\EquationReplacer;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = new MarkerSettings([
    'TEXIFY_BATCH_SIZE' => 2,
    'TEXIFY_MODEL_MAX' => 10,
    'TEXIFY_TOKEN_BUFFER' => 3,
]);

$preflight = (new EquationReplacer(null, $settings))->getLatexBatchedFromSuppliedOutputs(
    ['formula-crop-0.png', 'formula-crop-1.png', 'formula-crop-2.png'],
    [4, 12, 2],
    [
        '$$a+b$$',
        'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu',
        'one two three four',
    ]
);

echo json_encode([
    'scenario' => 'wordpress-texify-equation-batch-preflight',
    'batch_size' => $preflight['batch_size'],
    'batches' => $preflight['batches'],
    'accepted_predictions' => array_values(array_filter(
        $preflight['predictions'],
        static fn (string $prediction): bool => $prediction !== ''
    )),
    'dropped_output_indexes' => $preflight['dropped_output_indexes'],
    'note' => 'Runaway Texify outputs are blanked before WordPress math-block insertion.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
