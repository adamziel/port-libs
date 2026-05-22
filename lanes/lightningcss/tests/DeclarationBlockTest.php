<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

return [
    'declaration block get returns the last property and priority' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'red', 'important' => false], $block->getProperty('color: green; color: red', 'color'));
        $t->same(['value' => 'red', 'important' => true], $block->getProperty('color: red !important', 'color'));
        $t->same(null, $block->getProperty('margin-top: 5px', 'color'));
    },
    'declaration block set replaces direct properties and serializes priority' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('color: green', $block->setProperty('color: red', 'color', 'green'));
        $t->same('color: green !important', $block->setProperty('color: red !important', 'color', 'green', true));
        $t->same('color: red; background-color: blue', $block->setProperty('color: red', 'background-color', 'blue'));
    },
    'declaration block remove drops direct properties and preserves neighbors' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('', $block->removeProperty('margin-top: 10px', 'margin-top'));
        $t->same('margin-left: 5px', $block->removeProperty('margin-top: 10px !important; margin-left: 5px', 'margin-top'));
        $t->same('color: red', $block->removeProperty('margin-top: 10px; color: red; margin-top: 12px', 'margin-top'));
    },
];
