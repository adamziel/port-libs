<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\DebugPageImagePlanner;
use PortLibs\MarkerPDF\MarkerSettings;

$settings = static function (bool $debug): MarkerSettings {
    return new MarkerSettings([
        'DEBUG' => $debug,
        'DEBUG_DATA_FOLDER' => '/tmp/markerpdf-debug',
    ]);
};

$page = static function (): array {
    return [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'blocks' => [
            [
                'block_type' => 'Title',
                'bbox' => [72.0, 48.0, 420.0, 84.0],
                'lines' => [
                    ['prelim_text' => 'Migration Brief', 'bbox' => [72.0, 48.0, 420.0, 84.0]],
                ],
            ],
            [
                'block_type' => 'Text',
                'bbox' => [72.0, 112.0, 460.0, 154.0],
                'lines' => [
                    ['prelim_text' => 'Review body text', 'bbox' => [72.0, 112.0, 460.0, 154.0]],
                ],
            ],
        ],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [144.0, 96.0, 840.0, 168.0]],
                ['label' => 'Text', 'bbox' => [144.0, 224.0, 920.0, 308.0]],
            ],
        ],
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['bbox' => [144.0, 96.0, 840.0, 168.0]],
                ['bbox' => [144.0, 224.0, 920.0, 308.0]],
            ],
        ],
    ];
};

$textSizer = static fn (string $label, int $fontSize): array => [strlen($label) * 6, 11];

return [
    'skips debug page image artifacts when upstream DEBUG setting is disabled' => static function (TestRunner $t) use ($settings, $page): void {
        $plan = (new DebugPageImagePlanner($settings(false)))
            ->drawPageDebugImagePlans('/uploads/migration.packet.pdf', [$page()]);

        $t->same('/tmp/markerpdf-debug' . DIRECTORY_SEPARATOR . 'migration.packet', $plan['debug_folder']);
        $t->same([], $plan['artifacts']);
    },
    'plans upstream layout and pdf page debug image artifacts' => static function (TestRunner $t) use ($settings, $page, $textSizer): void {
        $plan = (new DebugPageImagePlanner($settings(true)))
            ->drawPageDebugImagePlans('/uploads/migration.packet.pdf', [$page()], $textSizer);

        $t->same('/tmp/markerpdf-debug' . DIRECTORY_SEPARATOR . 'migration.packet', $plan['debug_folder']);
        $t->same(2, count($plan['artifacts']));

        $layout = $plan['artifacts'][0];
        $pdf = $plan['artifacts'][1];

        $t->same('layout', $layout['type']);
        $t->same('/tmp/markerpdf-debug' . DIRECTORY_SEPARATOR . 'migration.packet' . DIRECTORY_SEPARATOR . 'layout_page_0.png', $layout['path']);
        $t->same([1200, 1600], $layout['image_size']);
        $t->same(16, count($layout['operations']));
        $t->same([
            'type' => 'rectangle',
            'role' => 'label_background',
            'bbox' => [145, 97, 235, 108],
            'fill' => 'white',
        ], $layout['operations'][0]);
        $t->same([
            'type' => 'rectangle',
            'role' => 'bbox',
            'bbox' => [144, 96, 840, 168],
            'outline' => 'blue',
            'width' => 1,
        ], $layout['operations'][4]);
        $t->same('red', $layout['operations'][8]['fill']);
        $t->same([149, 101], $layout['operations'][13]['position']);

        $t->same('pdf', $pdf['type']);
        $t->same('/tmp/markerpdf-debug' . DIRECTORY_SEPARATOR . 'migration.packet' . DIRECTORY_SEPARATOR . 'pdf_page_0.png', $pdf['path']);
        $t->same(14, count($pdf['operations']));
        $t->same('red', $pdf['operations'][6]['fill']);
        $t->same([149, 101], $pdf['operations'][11]['position']);
    },
    'emits WordPress debug page image review metadata' => static function (TestRunner $t) use ($settings, $page, $textSizer): void {
        $plan = (new DebugPageImagePlanner($settings(true)))
            ->drawPageDebugImagePlans('/uploads/import-brief.pdf', [$page()], $textSizer);

        $review = [
            'scenario' => 'wordpress-debug-page-image-plan',
            'artifact_count' => count($plan['artifacts']),
            'layout_overlay_count' => count($plan['artifacts'][0]['operations']),
            'pdf_overlay_count' => count($plan['artifacts'][1]['operations']),
            'paths' => array_column($plan['artifacts'], 'path'),
        ];

        $t->same('wordpress-debug-page-image-plan', $review['scenario']);
        $t->same(2, $review['artifact_count']);
        $t->same(16, $review['layout_overlay_count']);
        $t->same(14, $review['pdf_overlay_count']);
        $t->contains('layout_page_0.png', $review['paths'][0]);
        $t->contains('pdf_page_0.png', $review['paths'][1]);
    },
    'requires page, layout, and text-line geometry for debug image planning' => static function (TestRunner $t) use ($settings): void {
        $planner = new DebugPageImagePlanner($settings(true));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->drawPageDebugImagePlans('/uploads/missing.pdf', [['blocks' => []]])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->drawPageDebugImagePlans('/uploads/missing.pdf', [[
                'bbox' => [0, 0, 100, 100],
                'blocks' => [],
                'text_lines' => ['image_bbox' => [0, 0, 100, 100], 'bboxes' => []],
            ]])
        );
    },
];
