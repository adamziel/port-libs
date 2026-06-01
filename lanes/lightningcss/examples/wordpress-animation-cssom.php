<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'animation: core-block-fade 240ms ease-out both';
$prefixedDeclarations = '-webkit-animation: core-block-fade 240ms ease-out both';
$block = new DeclarationBlock();

$actual = [
    'animationName' => $block->getProperty($declarations, 'animation-name'),
    'duration' => $block->getProperty($declarations, 'animation-duration'),
    'fillMode' => $block->getProperty($declarations, 'animation-fill-mode'),
    'rewrittenDeclarations' => $block->setProperty($declarations, 'animation-name', 'wp-block-fade-in'),
    'slowerEntrance' => $block->setProperty($declarations, 'animation-duration', '320ms'),
    'multiNameFallback' => $block->setProperty('animation: core-block-fade 240ms', 'animation-name', 'wp-block-fade-in, wp-block-slide-up'),
    'timelineFromShorthand' => $block->getProperty('animation: core-block-fade 240ms ease-out both scroll(nearest block)', 'animation-timeline'),
    'directTimeline' => $block->getProperty('animation-timeline: scroll(root block), view(block auto auto), --wp-scroll', 'animation-timeline'),
    'rewrittenTimeline' => $block->setProperty(
        'animation: core-block-fade 240ms ease-out both scroll(nearest block)',
        'animation-timeline',
        'view(inline auto 20%)'
    ),
    'directTimelineRewrite' => $block->setProperty(
        'animation-timeline: scroll(root block)',
        'animation-timeline',
        'scroll(nearest inline), view(block 10% 10%)'
    ),
    'durationRemoved' => $block->removeProperty($declarations, 'animation-duration'),
    'webkitDuration' => $block->getProperty($prefixedDeclarations, '-webkit-animation-duration'),
    'webkitSlowerEntrance' => $block->setProperty($prefixedDeclarations, '-webkit-animation-duration', '320ms'),
    'webkitDurationRemoved' => $block->removeProperty($prefixedDeclarations, '-webkit-animation-duration'),
];

$expected = [
    'animationName' => ['value' => 'core-block-fade', 'important' => false],
    'duration' => ['value' => '240ms', 'important' => false],
    'fillMode' => ['value' => 'both', 'important' => false],
    'rewrittenDeclarations' => 'animation: 240ms ease-out both wp-block-fade-in',
    'slowerEntrance' => 'animation: 320ms ease-out both core-block-fade',
    'multiNameFallback' => 'animation: 240ms core-block-fade; animation-name: wp-block-fade-in, wp-block-slide-up',
    'timelineFromShorthand' => ['value' => 'scroll()', 'important' => false],
    'directTimeline' => ['value' => 'scroll(root), view(), --wp-scroll', 'important' => false],
    'rewrittenTimeline' => 'animation: 240ms ease-out both core-block-fade view(inline auto 20%)',
    'directTimelineRewrite' => 'animation-timeline: scroll(inline), view(10%)',
    'durationRemoved' => 'animation-name: core-block-fade; animation-timing-function: ease-out; animation-iteration-count: 1; animation-direction: normal; animation-play-state: running; animation-delay: 0s; animation-fill-mode: both; animation-timeline: auto',
    'webkitDuration' => ['value' => '240ms', 'important' => false],
    'webkitSlowerEntrance' => '-webkit-animation: 320ms ease-out both core-block-fade',
    'webkitDurationRemoved' => '-webkit-animation-name: core-block-fade; -webkit-animation-timing-function: ease-out; -webkit-animation-iteration-count: 1; -webkit-animation-direction: normal; -webkit-animation-play-state: running; -webkit-animation-delay: 0s; -webkit-animation-fill-mode: both',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected animation CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
