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
    'declaration block reads upstream cssom important bucket before normal declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'red', 'important' => true],
            $block->getProperty('color: red !important; background: white; color: blue', 'color')
        );
        $t->same(
            ['value' => '1rem', 'important' => true],
            $block->getProperty('margin: 1rem !important; margin-top: 2rem', 'margin-top')
        );
        $t->same(
            ['value' => 'red', 'important' => true],
            $block->getProperty('background: blue; background-color: red !important; background: green', 'background-color')
        );
        $t->same(
            null,
            $block->getProperty(
                'padding-top: 1rem !important; padding-right: 1rem; padding-bottom: 1rem; padding-left: 1rem',
                'padding'
            )
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
    'declaration block reads upstream mask border source cssom longhand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'linear-gradient(red, green)', 'important' => false],
            $block->getProperty('mask-border: linear-gradient(red, green) 25', 'mask-border-source')
        );
        $t->same(
            ['value' => 'linear-gradient(red, green)', 'important' => true],
            $block->getProperty('mask-border: 25 linear-gradient(red, green) / 12px round !important', 'mask-border-source')
        );
        $t->same(
            ['value' => 'url(frame.svg)', 'important' => false],
            $block->getProperty('mask-border-source: none; mask-border: 25; mask-border-source: url(frame.svg)', 'mask-border-source')
        );
    },
    'declaration block reads upstream border cssom shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '1px solid green', 'important' => false],
            $block->getProperty('border: 1px solid red; border-color: green', 'border')
        );
        $t->same(null, $block->getProperty('border: 1px solid red; border-left-color: green', 'border'));
        $t->same(
            ['value' => 'green', 'important' => false],
            $block->getProperty('border: 1px solid red; border-color: green', 'border-color')
        );
        $t->same(
            ['value' => '2px solid var(--wp--preset--color--primary)', 'important' => false],
            $block->getProperty(
                'border: 2px solid var(--wp--preset--color--contrast); border-color: var(--wp--preset--color--primary)',
                'border'
            )
        );
        $t->same(
            ['value' => 'var(--wp--preset--color--primary)', 'important' => false],
            $block->getProperty(
                'border: 2px solid var(--wp--preset--color--contrast); border-color: var(--wp--preset--color--primary)',
                'border-top-color'
            )
        );
        $t->same(
            ['value' => 'solid', 'important' => true],
            $block->getProperty('border: 1px solid red !important', 'border-style')
        );
    },
    'declaration block reads upstream grid area cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'a', 'important' => false],
            $block->getProperty('grid-area: a / b / c / d', 'grid-row-start')
        );
        $t->same(
            ['value' => 'c', 'important' => false],
            $block->getProperty('grid-area: a / b / c / d', 'grid-row-end')
        );
        $t->same(
            ['value' => 'a / c', 'important' => false],
            $block->getProperty('grid-area: a / b / c / d', 'grid-row')
        );
        $t->same(
            ['value' => 'b / d', 'important' => false],
            $block->getProperty('grid-area: a / b / c / d', 'grid-column')
        );
        $t->same(
            ['value' => 'content-start / aside-start / content-end / aside-end', 'important' => true],
            $block->getProperty(
                'grid-area: content-start / aside-start / content-end / aside-end !important',
                'grid-area'
            )
        );
    },
    'declaration block reads upstream grid template cssom shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'auto 1fr / auto 1fr auto', 'important' => false],
            $block->getProperty(
                'grid-template-rows: auto 1fr; grid-template-columns: auto 1fr auto; grid-template-areas: none',
                'grid-template'
            )
        );
        $t->same(
            [
                'value' => '[header-top] "a a a" [header-bottom] [main-top] "b b b" 1fr [main-bottom] / auto 1fr auto',
                'important' => false,
            ],
            $block->getProperty(
                'grid-template-areas: "a a a" "b b b"; grid-template-rows: [header-top] auto [header-bottom main-top] 1fr [main-bottom]; grid-template-columns: auto 1fr auto',
                'grid-template'
            )
        );
        $t->same(
            ['value' => '". a a ." ". b b ." 1fr / 10px 1fr 1fr 10px', 'important' => false],
            $block->getProperty(
                'grid-template-areas: ". a a ." ". b b ."; grid-template-rows: auto 1fr; grid-template-columns: 10px 1fr 1fr 10px',
                'grid-template'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'grid-template-areas: "a a a" "b b b"; grid-template-columns: repeat(3, 1fr); grid-template-rows: auto 1fr',
                'grid-template'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'grid-template-rows: auto 1fr !important; grid-template-columns: auto 1fr auto; grid-template-areas: none',
                'grid-template'
            )
        );
    },
    'declaration block reads upstream grid cssom shorthand only for initial auto placement' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            [
                'value' => '[header-top] "a a a" [header-bottom] [main-top] "b b b" 1fr [main-bottom] / auto 1fr auto',
                'important' => false,
            ],
            $block->getProperty(
                'grid-template-areas: "a a a" "b b b"; grid-template-rows: [header-top] auto [header-bottom main-top] 1fr [main-bottom]; grid-template-columns: auto 1fr auto; grid-auto-flow: row; grid-auto-rows: auto; grid-auto-columns: auto',
                'grid'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'grid-template-areas: "a a a" "b b b"; grid-template-rows: [header-top] auto [header-bottom main-top] 1fr [main-bottom]; grid-template-columns: auto 1fr auto; grid-auto-flow: column; grid-auto-rows: 1fr; grid-auto-columns: 1fr',
                'grid'
            )
        );
    },
    'declaration block reads upstream flex flow cssom properties' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'row wrap', 'important' => false],
            $block->getProperty('flex-direction: row; flex-wrap: wrap', 'flex-flow')
        );
        $t->same(
            ['value' => 'row wrap', 'important' => false],
            $block->getProperty('-webkit-flex-direction: row; -webkit-flex-wrap: wrap', '-webkit-flex-flow')
        );
        $t->same(null, $block->getProperty('flex-direction: row; flex-wrap: wrap', '-webkit-flex-flow'));
        $t->same(null, $block->getProperty('-webkit-flex-direction: row; flex-wrap: wrap', '-webkit-flex-flow'));
        $t->same(null, $block->getProperty('-webkit-flex-direction: row; flex-wrap: wrap', 'flex-flow'));
        $t->same(
            ['value' => 'row', 'important' => false],
            $block->getProperty('-webkit-flex-flow: row', '-webkit-flex-direction')
        );
        $t->same(null, $block->getProperty('-webkit-flex-flow: row', 'flex-direction'));
    },
    'declaration block reads upstream animation name cssom longhand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'foo', 'important' => false],
            $block->getProperty('animation: foo 2s', 'animation-name')
        );
        $t->same(
            ['value' => 'foo, bar', 'important' => true],
            $block->getProperty('animation: foo 2s, bar 150ms !important', 'animation-name')
        );
        $t->same(
            ['value' => 'slide-up', 'important' => false],
            $block->getProperty('animation: fade-in 240ms ease-out; animation-name: slide-up', 'animation-name')
        );
    },
    'declaration block reads upstream transition cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $transition = 'transition: opacity 200ms ease-in 50ms, transform 1s linear';
        $t->same(['value' => 'opacity, transform', 'important' => false], $block->getProperty($transition, 'transition-property'));
        $t->same(['value' => '200ms, 1s', 'important' => false], $block->getProperty($transition, 'transition-duration'));
        $t->same(['value' => '50ms, 0s', 'important' => false], $block->getProperty($transition, 'transition-delay'));
        $t->same(['value' => 'ease-in, linear', 'important' => false], $block->getProperty($transition, 'transition-timing-function'));
        $t->same(['value' => 'opacity 200ms ease-in 50ms, transform 1s linear', 'important' => false], $block->getProperty($transition, 'transition'));
        $t->same(
            ['value' => 'opacity 200ms ease-in 50ms, transform 1s linear', 'important' => true],
            $block->getProperty(
                'transition-property: opacity, transform !important; transition-duration: 200ms, 1s !important; transition-delay: 50ms, 0s !important; transition-timing-function: ease-in, linear !important',
                'transition'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'transition-property: opacity, transform; transition-duration: 200ms; transition-delay: 50ms, 0s; transition-timing-function: ease-in, linear',
                'transition'
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
    'declaration block writes upstream cssom priority buckets' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'background: white; color: green',
            $block->setProperty('color: red !important; background: white; color: blue', 'color', 'green')
        );
        $t->same(
            'background: white; color: green !important',
            $block->setProperty('color: red; background: white; color: blue !important', 'color', 'green', true)
        );
        $t->same(
            'margin-top: 8px; margin: 5px !important',
            $block->setProperty('margin: 5px !important', 'margin-top', '8px')
        );
        $t->same(
            'margin: 5px; margin-top: 8px !important',
            $block->setProperty('margin: 5px', 'margin-top', '8px', true)
        );
        $t->same(
            'flex-flow: row wrap; flex-direction: column !important',
            $block->setProperty('flex-flow: row wrap', 'flex-direction', 'column', true)
        );
    },
    'declaration block sets upstream logical box properties after physical fallbacks' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'margin-inline-start: 5px; margin-top: 10px; margin-inline-start: 8px',
            $block->setProperty('margin-inline-start: 5px; margin-top: 10px', 'margin-inline-start', '8px')
        );
        $t->same(
            'margin-top: 10px; margin-inline-start: 8px',
            $block->setProperty('margin-top: 10px; margin-inline-start: 5px', 'margin-inline-start', '8px')
        );
        $t->same(
            'padding-inline-start: var(--wp--preset--spacing--30); padding-left: 1rem; padding-inline-start: var(--wp--preset--spacing--40)',
            $block->setProperty(
                'padding-inline-start: var(--wp--preset--spacing--30); padding-left: 1rem',
                'padding-inline-start',
                'var(--wp--preset--spacing--40)'
            )
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
    'declaration block sets upstream background cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'background: red url(hero.jpg)',
            $block->setProperty('background: url(hero.jpg) green', 'background-color', 'red')
        );
        $t->same(
            'background: linear-gradient(red, green) left top no-repeat, url(new.png) right bottom repeat-x',
            $block->setProperty(
                'background: url(a.png) left top no-repeat, url(b.png) right bottom repeat-x',
                'background-image',
                'linear-gradient(red, green), url(new.png)'
            )
        );
        $t->same(
            'background: url(a.png) repeat-x, url(b.png) no-repeat',
            $block->setProperty('background: url(a.png), url(b.png)', 'background-repeat', 'repeat no-repeat, no-repeat')
        );
        $t->same(
            'background: url(hero.jpg) 0 0 / cover',
            $block->setProperty('background: url(hero.jpg)', 'background-size', 'cover')
        );
        $t->same(
            'background: url(a.png) 20px 5px, url(b.png) 30px bottom',
            $block->setProperty('background: url(a.png) 20px 10px, url(b.png) 30px 40px', 'background-position-y', '5px, bottom')
        );
        $t->same(
            'background: url(a.png), url(b.png); background-repeat: repeat-x',
            $block->setProperty('background: url(a.png), url(b.png)', 'background-repeat', 'repeat-x')
        );
    },
    'declaration block sets upstream border side longhands without decomposing' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border: 1px solid red; border-right: 1px solid green',
            $block->setProperty('border: 1px solid red', 'border-right', '1px solid green')
        );
        $t->same(
            'border: 1px solid red; border-right-color: green',
            $block->setProperty('border: 1px solid red', 'border-right-color', 'green')
        );
    },
    'declaration block sets upstream flex flow cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'flex-flow: column wrap',
            $block->setProperty('flex-flow: row wrap', 'flex-direction', 'column')
        );
        $t->same(
            '-webkit-flex-flow: column wrap',
            $block->setProperty('-webkit-flex-flow: row wrap', '-webkit-flex-direction', 'column')
        );
        $t->same(
            'flex-flow: wrap; -webkit-flex-direction: column',
            $block->setProperty('flex-flow: row wrap', '-webkit-flex-direction', 'column')
        );
    },
    'declaration block sets upstream animation name cssom longhand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'animation: 2s foo; animation-name: foo, bar',
            $block->setProperty('animation: foo 2s', 'animation-name', 'foo, bar')
        );
        $t->same(
            'animation: 2s bar',
            $block->setProperty('animation: foo 2s', 'animation-name', 'bar')
        );
        $t->same(
            'animation: 240ms ease-out both wp-block-fade-in',
            $block->setProperty('animation: core-block-fade 240ms ease-out both', 'animation-name', 'wp-block-fade-in')
        );
        $t->same(
            'animation: 200ms ease wp-fade, 300ms wp-slide',
            $block->setProperty('animation: fade 200ms ease, slide 300ms', 'animation-name', 'wp-fade, wp-slide')
        );
    },
    'declaration block sets upstream transition cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'transition: opacity 300ms ease-in 50ms',
            $block->setProperty('transition: opacity 200ms ease-in 50ms', 'transition-duration', '300ms')
        );
        $t->same(
            'transition: opacity 200ms linear',
            $block->setProperty('transition: opacity 200ms', 'transition-timing-function', 'linear')
        );
        $t->same(
            'transition: opacity 200ms 100ms',
            $block->setProperty('transition: opacity 200ms', 'transition-delay', '100ms')
        );
        $t->same(
            'transition: color 200ms; transition-property: opacity, transform',
            $block->setProperty('transition: color 200ms', 'transition-property', 'opacity, transform')
        );
        $t->same(
            '-webkit-transition: opacity 300ms',
            $block->setProperty('-webkit-transition: opacity 200ms', '-webkit-transition-duration', '300ms')
        );
        $t->same(
            '-webkit-transition: opacity 200ms; transition-duration: 300ms',
            $block->setProperty('-webkit-transition: opacity 200ms', 'transition-duration', '300ms')
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
    'declaration block removes upstream background cssom shorthand and supported longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'color: red; color: blue',
            $block->removeProperty(
                'color: red; background: url(hero.jpg) green; background-color: blue; background-repeat: no-repeat; color: blue',
                'background'
            )
        );
        $t->same(
            'padding: 1rem',
            $block->removeProperty(
                'background: red !important; background-color: blue; padding: 1rem; background-image: url(foo.png) !important',
                'background'
            )
        );
    },
    'declaration block removes upstream border longhands by splitting shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px; border-top-style: solid; border-right-style: solid; border-bottom-style: solid; border-left-style: solid; border-top-color: red; border-bottom-color: red; border-left-color: red',
            $block->removeProperty('border: 1px solid red', 'border-right-color')
        );
        $t->same(
            'border-right-color: green; border-bottom-color: blue; border-left-color: black',
            $block->removeProperty('border-color: red green blue black', 'border-top-color')
        );
        $t->same(
            'border-top-width: 2px; border-top-style: dotted',
            $block->removeProperty('border-top: 2px dotted blue; border-top-color: green', 'border-top-color')
        );
        $t->same(
            'color: blue; border-top-width: 1px !important; border-right-width: 1px !important; border-bottom-width: 1px !important; border-top-style: solid !important; border-right-style: solid !important; border-bottom-style: solid !important; border-left-style: solid !important; border-top-color: red !important; border-right-color: red !important; border-bottom-color: red !important; border-left-color: red !important',
            $block->removeProperty('border: 1px solid red !important; border-left-width: 4px; color: blue', 'border-left-width')
        );
    },
    'declaration block removes upstream cssom priority buckets' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'background: white',
            $block->removeProperty('color: red !important; background: white; color: blue', 'color')
        );
        $t->same(
            'padding: 1rem; margin-right: 10px !important; margin-bottom: 10px !important; margin-left: 10px !important',
            $block->removeProperty('margin: 10px !important; padding: 1rem; margin-top: 12px', 'margin-top')
        );
        $t->same(
            'flex-wrap: wrap; -webkit-flex-flow: column wrap !important',
            $block->removeProperty('-webkit-flex-flow: column wrap !important; flex-flow: row wrap', 'flex-direction')
        );
    },
    'declaration block removes upstream flex flow cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('flex-wrap: wrap', $block->removeProperty('flex-flow: column wrap', 'flex-direction'));
        $t->same('flex-flow: column wrap', $block->removeProperty('flex-flow: column wrap', '-webkit-flex-direction'));
        $t->same('-webkit-flex-wrap: wrap', $block->removeProperty('-webkit-flex-flow: column wrap', '-webkit-flex-direction'));
    },
    'declaration block removes upstream transition cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'transition-property: opacity; transition-delay: 50ms; transition-timing-function: ease-in',
            $block->removeProperty('transition: opacity 200ms ease-in 50ms', 'transition-duration')
        );
        $t->same(
            'transition-property: opacity; transition-duration: 200ms; transition-delay: 50ms',
            $block->removeProperty('transition: opacity 200ms ease-in 50ms', 'transition-timing-function')
        );
        $t->same(
            'color: red',
            $block->removeProperty('transition: opacity 200ms; transition-duration: 300ms; color: red', 'transition')
        );
        $t->same(
            '-webkit-transition-property: opacity; -webkit-transition-delay: 0s; -webkit-transition-timing-function: ease',
            $block->removeProperty('-webkit-transition: opacity 200ms', '-webkit-transition-duration')
        );
    },
];
