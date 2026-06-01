<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$queryCard = 'container: wp-query-card / inline-size; color: var(--wp--preset--color--contrast)';
$escapedQueryCard = 'container: wp-\71 uery-card is-\77 ide / inline-size';

$actual = [
    'containerName' => $block->getProperty($queryCard, 'container-name'),
    'containerType' => $block->getProperty($queryCard, 'container-type'),
    'escapedContainer' => $block->getProperty($escapedQueryCard, 'container'),
    'editorContainerType' => $block->setProperty($queryCard, 'container-type', 'size'),
    'editorContainerName' => $block->setProperty($queryCard, 'container-name', 'wp-query-card is-wide'),
    'editorEscapedContainerName' => $block->setProperty($queryCard, 'container-name', 'wp-\71 uery-card is-\77 ide'),
    'dropContainerName' => $block->removeProperty($queryCard, 'container-name'),
    'dropEscapedContainerType' => $block->removeProperty($escapedQueryCard, 'container-type'),
    'resetContainer' => $block->removeProperty($queryCard, 'container'),
];

$expected = [
    'containerName' => [
        'value' => 'wp-query-card',
        'important' => false,
    ],
    'containerType' => [
        'value' => 'inline-size',
        'important' => false,
    ],
    'escapedContainer' => [
        'value' => 'wp-query-card is-wide / inline-size',
        'important' => false,
    ],
    'editorContainerType' => 'container: wp-query-card / size; color: var(--wp--preset--color--contrast)',
    'editorContainerName' => 'container: wp-query-card is-wide / inline-size; color: var(--wp--preset--color--contrast)',
    'editorEscapedContainerName' => 'container: wp-query-card is-wide / inline-size; color: var(--wp--preset--color--contrast)',
    'dropContainerName' => 'container-type: inline-size; color: var(--wp--preset--color--contrast)',
    'dropEscapedContainerType' => 'container-name: wp-query-card is-wide',
    'resetContainer' => 'color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected container CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
