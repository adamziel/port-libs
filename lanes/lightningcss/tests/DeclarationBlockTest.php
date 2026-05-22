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
    'declaration block reads upstream background cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'red', 'important' => false], $block->getProperty('background: red', 'background'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty('background: red', 'background-color'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty('background: red url(foo.png)', 'background-color'));
        $t->same(
            ['value' => 'red', 'important' => false],
            $block->getProperty('background: url(foo.png) green, url(bar.png) red', 'background-color')
        );
        $t->same(
            ['value' => 'linear-gradient(red, green), linear-gradient(#fff, #000)', 'important' => false],
            $block->getProperty(
                'background: linear-gradient(red, green) repeat-x, linear-gradient(#fff, #000) repeat-y',
                'background-image'
            )
        );
        $t->same(
            ['value' => 'repeat-x, repeat-y', 'important' => false],
            $block->getProperty(
                'background: linear-gradient(red, green) repeat-x, linear-gradient(#fff, #000) repeat-y',
                'background-repeat'
            )
        );
        $t->same(
            ['value' => '20px 10px', 'important' => false],
            $block->getProperty('background-position-x: 20px; background-position-y: 10px', 'background-position')
        );
        $t->same(
            ['value' => '20px 10px', 'important' => false],
            $block->getProperty('background: linear-gradient(red, green) 20px 10px', 'background-position')
        );
        $t->same(
            ['value' => '20px', 'important' => false],
            $block->getProperty('background: linear-gradient(red, green) 20px 10px', 'background-position-x')
        );
        $t->same(
            ['value' => '10px', 'important' => false],
            $block->getProperty('background: linear-gradient(red, green) 20px 10px', 'background-position-y')
        );
    },
    'declaration block composes upstream background cssom shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'linear-gradient(red, green) 20px 10px / 50px 100px repeat-x', 'important' => false],
            $block->getProperty(
                'background: linear-gradient(red, green); background-position-x: 20px; background-position-y: 10px; background-size: 50px 100px; background-repeat: repeat no-repeat',
                'background'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'background: linear-gradient(red, green); background-position-x: 20px; background-position-y: 10px !important; background-size: 50px 100px; background-repeat: repeat no-repeat',
                'background'
            )
        );
        $t->same(
            [
                'value' => 'linear-gradient(red, green) right 20px top 20px / 50px 50px repeat-x, gray linear-gradient(#fff, #000) 10px 15px no-repeat',
                'important' => false,
            ],
            $block->getProperty(
                'background: linear-gradient(red, green), linear-gradient(#fff, #000) gray; background-position-x: right 20px, 10px; background-position-y: top 20px, 15px; background-size: 50px 50px, auto; background-repeat: repeat no-repeat, no-repeat',
                'background'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'background: linear-gradient(red, green); background-position-x: right 20px, 10px; background-position-y: top 20px, 15px; background-size: 50px 50px, auto; background-repeat: repeat no-repeat, no-repeat',
                'background'
            )
        );
        $t->same(
            ['value' => 'linear-gradient(red, green) 20px 10px / 50px 100px repeat-x', 'important' => false],
            $block->getProperty(
                'background: linear-gradient(red, green); background-position: 20px 10px; background-size: 50px 100px; background-repeat: repeat no-repeat',
                'background'
            )
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
    'declaration block sets upstream background position shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'background: linear-gradient(red, green) 20px 0',
            $block->setProperty('background: linear-gradient(red, green)', 'background-position-x', '20px')
        );
        $t->same(
            'background: linear-gradient(red, green) 20px 10px',
            $block->setProperty('background: linear-gradient(red, green)', 'background-position', '20px 10px')
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
