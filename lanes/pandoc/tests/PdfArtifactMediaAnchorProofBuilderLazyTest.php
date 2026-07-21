<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

return [
    'empty clipped display anchor state does not load its proof builder' => static function (
        TestRunner $t
    ): void {
        $helperClass = 'PortLibs\\Pandoc\\PdfClippedDisplayMediaAnchorProofBuilder';
        $t->same(false, class_exists($helperClass, false));

        $reader = new PdfReader();
        $build = (function (): array {
            return $this->pdfClippedDisplayArtifactMediaAnchorProofs(
                [],
                [],
                [],
                [],
                [['kind' => 'noncandidate-placement']]
            );
        })->bindTo($reader, PdfReader::class);

        $t->same(
            ['proofs' => [], 'truncatedCount' => 0],
            $build()
        );
        $t->same(false, class_exists($helperClass, false));
    },
];
