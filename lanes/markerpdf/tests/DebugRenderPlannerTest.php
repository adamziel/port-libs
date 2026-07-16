<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\DebugRenderPlanner;

return [
    'plans upstream debug bbox rectangles with integer coordinates and shared color' => static function (TestRunner $t): void {
        $plan = (new DebugRenderPlanner())->renderOnImagePlan([
            [10.9, 20.1, 110.8, 80.6],
            [-3.7, 4.5, 12.9, 16.2],
        ]);

        $t->same(10, $plan['label_font_size']);
        $t->same([
            [
                'type' => 'rectangle',
                'role' => 'bbox',
                'bbox' => [10, 20, 110, 80],
                'outline' => 'red',
                'width' => 1,
            ],
            [
                'type' => 'rectangle',
                'role' => 'bbox',
                'bbox' => [-3, 4, 12, 16],
                'outline' => 'red',
                'width' => 1,
            ],
        ], $plan['operations']);
    },
    'uses per-box colors and label placement from marker debug render' => static function (TestRunner $t): void {
        $plan = (new DebugRenderPlanner())->renderOnImagePlan(
            [[72, 48, 420, 84], [72, 112, 460, 154]],
            ['Title', 'Text'],
            2,
            12,
            ['blue', 'green'],
            true,
            static fn (string $label, int $fontSize): array => [$label === 'Title' ? 31 : 24, $fontSize + 3]
        );

        $t->same([
            [
                'type' => 'rectangle',
                'role' => 'bbox',
                'bbox' => [72, 48, 420, 84],
                'outline' => 'blue',
                'width' => 1,
            ],
            [
                'type' => 'rectangle',
                'role' => 'label_background',
                'bbox' => [74, 50, 105, 65],
                'fill' => 'white',
            ],
            [
                'type' => 'text',
                'role' => 'label',
                'position' => [74, 50],
                'text' => 'Title',
                'fill' => 'blue',
                'font_size' => 12,
            ],
            [
                'type' => 'rectangle',
                'role' => 'bbox',
                'bbox' => [72, 112, 460, 154],
                'outline' => 'green',
                'width' => 1,
            ],
            [
                'type' => 'rectangle',
                'role' => 'label_background',
                'bbox' => [74, 114, 98, 129],
                'fill' => 'white',
            ],
            [
                'type' => 'text',
                'role' => 'label',
                'position' => [74, 114],
                'text' => 'Text',
                'fill' => 'green',
                'font_size' => 12,
            ],
        ], $plan['operations']);
    },
    'can plan label overlays without drawing source bboxes' => static function (TestRunner $t): void {
        $plan = (new DebugRenderPlanner())->renderOnImagePlan(
            [[10, 20, 40, 50]],
            ['Hidden bbox'],
            1,
            10,
            'purple',
            false,
            static fn (string $label, int $fontSize): array => [60, 11]
        );

        $t->same([
            [
                'type' => 'rectangle',
                'role' => 'label_background',
                'bbox' => [11, 21, 71, 32],
                'fill' => 'white',
            ],
            [
                'type' => 'text',
                'role' => 'label',
                'position' => [11, 21],
                'text' => 'Hidden bbox',
                'fill' => 'purple',
                'font_size' => 10,
            ],
        ], $plan['operations']);
    },
    'skips zero-size label draws like upstream pil textbbox guard' => static function (TestRunner $t): void {
        $plan = (new DebugRenderPlanner())->renderOnImagePlan(
            [[1, 2, 3, 4], [5, 6, 7, 8]],
            ['skip width', 'skip height'],
            textSizer: static fn (string $label, int $fontSize): array => $label === 'skip width' ? [0, 9] : [9, 0]
        );

        $t->same([
            [
                'type' => 'rectangle',
                'role' => 'bbox',
                'bbox' => [1, 2, 3, 4],
                'outline' => 'red',
                'width' => 1,
            ],
            [
                'type' => 'rectangle',
                'role' => 'bbox',
                'bbox' => [5, 6, 7, 8],
                'outline' => 'red',
                'width' => 1,
            ],
        ], $plan['operations']);
    },
    'does not index colors when upstream would draw no operations' => static function (TestRunner $t): void {
        $planner = new DebugRenderPlanner();

        $t->same([], $planner->renderOnImagePlan([[1, 2, 3, 4]], color: [], drawBbox: false)['operations']);
        $t->same(
            [],
            $planner->renderOnImagePlan(
                [[1, 2, 3, 4]],
                ['skipped'],
                color: [],
                drawBbox: false,
                textSizer: static fn (string $label, int $fontSize): array => [0, 10]
            )['operations']
        );
    },
    'reports the same missing-list boundaries as upstream index access' => static function (TestRunner $t): void {
        $planner = new DebugRenderPlanner();

        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->renderOnImagePlan([[0, 0, 1, 1]], [], textSizer: static fn (string $label, int $fontSize): array => [1, 1]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->renderOnImagePlan([[0, 0, 1, 1]], color: []));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->renderOnImagePlan([[0, 0, 1]]));
    },
    'emits WordPress debug overlay review metadata' => static function (TestRunner $t): void {
        $plan = (new DebugRenderPlanner())->renderOnImagePlan(
            [[72, 48, 420, 84], [72, 112, 460, 154]],
            ['Title', 'Text'],
            color: ['#2271b1', '#00a32a'],
            textSizer: static fn (string $label, int $fontSize): array => [strlen($label) * 6, 11]
        );
        $review = [
            'scenario' => 'wordpress-debug-render-plan',
            'bbox_count' => count(array_filter($plan['operations'], static fn (array $op): bool => ($op['role'] ?? '') === 'bbox')),
            'label_count' => count(array_filter($plan['operations'], static fn (array $op): bool => ($op['role'] ?? '') === 'label')),
            'first_label_position' => $plan['operations'][2]['position'],
        ];

        $t->same('wordpress-debug-render-plan', $review['scenario']);
        $t->same(2, $review['bbox_count']);
        $t->same(2, $review['label_count']);
        $t->same([73, 49], $review['first_label_position']);
    },
];
