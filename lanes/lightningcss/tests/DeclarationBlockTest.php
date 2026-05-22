<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

return [
    'declaration block get returns the last property and priority' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'red', 'important' => false], $block->getProperty('color: green; color: red', 'color'));
        $t->same(['value' => 'red', 'important' => true], $block->getProperty('color: red !important', 'color'));
        $t->same(null, $block->getProperty('margin-top: 5px', 'color'));
        $t->same(['value' => '5px', 'important' => false], $block->getProperty('margin: 5px 6px 7px 8px', 'margin-top'));
        $t->same(
            ['value' => '5px 6px', 'important' => false],
            $block->getProperty('margin-top: 5px; margin-bottom: 5px; margin-left: 6px; margin-right: 6px', 'margin')
        );
        $t->same(
            null,
            $block->getProperty('margin-top: 5px; margin-bottom: 5px; margin-left: 5px !important; margin-right: 5px', 'margin')
        );
        $t->same(
            ['value' => '1rem 2rem 3rem 4rem', 'important' => true],
            $block->getProperty('padding: 1rem 2rem 3rem 4rem !important', 'padding')
        );
    },
    'declaration block set replaces direct properties and serializes priority' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('color: green', $block->setProperty('color: red', 'color', 'green'));
        $t->same('color: green !important', $block->setProperty('color: red !important', 'color', 'green', true));
        $t->same('color: red; background-color: blue', $block->setProperty('color: red', 'background-color', 'blue'));
        $t->same('margin: 8px 5px 5px', $block->setProperty('margin: 5px', 'margin-top', '8px'));
        $t->same('padding: 1rem 2rem 1rem 4rem', $block->setProperty('padding: 1rem 2rem', 'padding-left', '4rem'));
        $t->same(
            'margin: 5px; margin-inline-start: 8px; margin-left: 10px',
            $block->setProperty('margin: 5px; margin-inline-start: 8px', 'margin-left', '10px')
        );
    },
    'declaration block remove drops direct properties and preserves neighbors' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('', $block->removeProperty('margin-top: 10px', 'margin-top'));
        $t->same('margin-left: 5px', $block->removeProperty('margin-top: 10px !important; margin-left: 5px', 'margin-top'));
        $t->same('color: red', $block->removeProperty('margin-top: 10px; color: red; margin-top: 12px', 'margin-top'));
        $t->same(
            'margin-right: 10px; margin-bottom: 10px; margin-left: 10px',
            $block->removeProperty('margin: 10px', 'margin-top')
        );
        $t->same('', $block->removeProperty('margin: 10px', 'margin'));
        $t->same('', $block->removeProperty('margin-top: 10px; margin-right: 10px; margin-bottom: 10px; margin-left: 10px', 'margin'));
        $t->same(
            'padding-top: 1rem !important; padding-right: 2rem !important; padding-bottom: 3rem !important',
            $block->removeProperty('padding: 1rem 2rem 3rem 4rem !important', 'padding-left')
        );
    },
];
