<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

return [
    'empty image placements do not load the placement anchorer' => static function (
        TestRunner $t
    ): void {
        $helperClass = 'PortLibs\\Pandoc\\PdfImagePlacementAnchorer';
        $t->same(false, class_exists($helperClass, false));

        $reader = new PdfReader();
        $anchor = (function (): array {
            return $this->imagePlacementsWithTextAnchors([], [], []);
        })->bindTo($reader, PdfReader::class);

        $t->same([], $anchor());
        $t->same(false, class_exists($helperClass, false));
    },
];
