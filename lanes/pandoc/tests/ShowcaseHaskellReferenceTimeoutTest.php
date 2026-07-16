<?php

declare(strict_types=1);

use PortLibs\Pandoc\ShowcaseHaskellReferenceTimeout;

return [
    'scales the Haskell reference timeout from expanded office package size' => static function (TestRunner $t): void {
        $schema = dirname(__DIR__, 3) . '/pandoc-showcase/samples/odt-oasis-opendocument-schema-OpenDocument-v1.3-os-part3-schema.odt';

        $t->true(is_file($schema), 'Expected the large ODT schema sample.');
        $t->true(class_exists(ZipArchive::class), 'ZIP support is required for office package reference calibration.');
        $t->true(
            ShowcaseHaskellReferenceTimeout::secondsFor($schema) >= 570,
            'A 10 MiB expanded office package needs more than the fixed five-minute reference budget.'
        );
        $t->true(
            ShowcaseHaskellReferenceTimeout::secondsFor($schema) <= 900,
            'External reference generation should remain bounded even for expanded packages.'
        );
    },
    'keeps small non-package reference conversions fast' => static function (TestRunner $t): void {
        $t->same(35, ShowcaseHaskellReferenceTimeout::secondsFor(__FILE__));
    },
];
