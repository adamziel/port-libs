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
    'declaration block preserves upstream custom property case in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = '--Block-Accent: red; --block-accent: blue; color: var(--Block-Accent)';

        $t->same(
            [
                '--Block-Accent' => 'red',
                '--block-accent' => 'blue',
                'color' => 'var(--Block-Accent)',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'red', 'important' => false], $block->getProperty($declarations, '--Block-Accent'));
        $t->same(['value' => 'blue', 'important' => false], $block->getProperty($declarations, '--block-accent'));
        $t->same(null, $block->getProperty($declarations, '--BLOCK-ACCENT'));
        $t->same(
            ['value' => 'red', 'important' => true],
            $block->getProperty('--Block-Accent: red !important; --block-accent: blue', '--Block-Accent')
        );
        $t->same(
            '--Block-Accent: green; --block-accent: blue; color: var(--Block-Accent)',
            $block->setProperty($declarations, '--Block-Accent', 'green')
        );
        $t->same(
            '--block-accent: blue; --Block-Accent: green',
            $block->setProperty('--Block-Accent: red !important; --block-accent: blue', '--Block-Accent', 'green')
        );
        $t->same(
            '--Block-Accent: red; --block-accent: green; color: var(--Block-Accent)',
            $block->setProperty($declarations, '--block-accent', 'green')
        );
        $t->same(
            '--block-accent: blue; --Block-Accent: green !important',
            $block->setProperty('--Block-Accent: red; --block-accent: blue', '--Block-Accent', 'green', true)
        );
        $t->same(
            '--block-accent: blue; color: var(--Block-Accent)',
            $block->removeProperty($declarations, '--Block-Accent')
        );
        $t->same($declarations, $block->removeProperty($declarations, '--BLOCK-ACCENT'));
    },
    'declaration block normalizes upstream alpha percentages in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'opacity: 50% !important; fill-opacity: 100%; stroke-opacity: 0.2500; --opacity: 50%';

        $t->same(
            [
                'opacity' => '.5 !important',
                'fill-opacity' => '1',
                'stroke-opacity' => '.25',
                '--opacity' => '50%',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => '.5', 'important' => true], $block->getProperty($declarations, 'opacity'));
        $t->same(['value' => '1', 'important' => false], $block->getProperty($declarations, 'fill-opacity'));
        $t->same(['value' => '.25', 'important' => false], $block->getProperty($declarations, 'stroke-opacity'));
        $t->same(['value' => '50%', 'important' => false], $block->getProperty($declarations, '--opacity'));
        $t->same(
            'fill-opacity: 1; stroke-opacity: .25; --opacity: 50%; opacity: .25',
            $block->setProperty($declarations, 'opacity', '25%')
        );
        $t->same(
            'fill-opacity: .5; stroke-opacity: .25; --opacity: 50%; opacity: .5 !important',
            $block->setProperty($declarations, 'fill-opacity', '0.500')
        );
        $t->same(
            'fill-opacity: 1; --opacity: 50%; opacity: .5 !important; stroke-opacity: 1 !important',
            $block->setProperty($declarations, 'stroke-opacity', '100%', true)
        );
        $t->same(
            'fill-opacity: 1; stroke-opacity: .25; opacity: .5 !important',
            $block->removeProperty($declarations, '--opacity')
        );
    },
    'declaration block canonicalizes upstream accent color cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'accent-color: Yellow; color: blue; --Accent-Color: Yellow';

        $t->same(
            [
                'accent-color' => '#ff0',
                'color' => '#00f',
                '--Accent-Color' => 'Yellow',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => '#ff0', 'important' => false], $block->getProperty($declarations, 'accent-color'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('accent-color: AUTO', 'accent-color'));
        $t->same(['value' => 'Yellow', 'important' => false], $block->getProperty($declarations, '--Accent-Color'));
        $t->same(
            'accent-color: auto; color: #00f; --Accent-Color: Yellow',
            $block->setProperty($declarations, 'accent-color', 'AUTO')
        );
        $t->same(
            'color: #00f; --Accent-Color: Yellow; accent-color: #0f0 !important',
            $block->setProperty($declarations, 'accent-color', 'Lime', true)
        );
        $t->same(
            'color: #00f; --Accent-Color: Yellow',
            $block->removeProperty($declarations, 'accent-color')
        );
    },
    'declaration block canonicalizes upstream direct color cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'color: #FF0000; background-color: RGB(255 255 0 / 100%); border-top-color: rgba(255,0,0,.4); border-inline-end-color: CURRENTCOLOR; outline-color: Yellow !important; text-decoration-color: Lime; -webkit-text-decoration-color: BLUE; text-emphasis-color: rgba(255, 0, 0, .4); -webkit-text-emphasis-color: transparent; caret-color: AUTO; --wp--preset--color--Brand: Yellow';

        $t->same(
            [
                'color' => 'red',
                'background-color' => '#ff0',
                'border-top-color' => '#f006',
                'border-inline-end-color' => 'currentColor',
                'outline-color' => '#ff0 !important',
                'text-decoration-color' => '#0f0',
                '-webkit-text-decoration-color' => '#00f',
                'text-emphasis-color' => '#f006',
                '-webkit-text-emphasis-color' => '#0000',
                'caret-color' => 'auto',
                '--wp--preset--color--Brand' => 'Yellow',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'red', 'important' => false], $block->getProperty($declarations, 'color'));
        $t->same(['value' => '#ff0', 'important' => false], $block->getProperty($declarations, 'background-color'));
        $t->same(['value' => '#f006', 'important' => false], $block->getProperty($declarations, 'border-top-color'));
        $t->same(['value' => 'currentColor', 'important' => false], $block->getProperty($declarations, 'border-inline-end-color'));
        $t->same(['value' => '#ff0', 'important' => true], $block->getProperty($declarations, 'outline-color'));
        $t->same(['value' => '#0f0', 'important' => false], $block->getProperty($declarations, 'text-decoration-color'));
        $t->same(['value' => '#00f', 'important' => false], $block->getProperty($declarations, '-webkit-text-decoration-color'));
        $t->same(['value' => '#f006', 'important' => false], $block->getProperty($declarations, 'text-emphasis-color'));
        $t->same(['value' => '#0000', 'important' => false], $block->getProperty($declarations, '-webkit-text-emphasis-color'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty($declarations, 'caret-color'));
        $t->same(['value' => 'Yellow', 'important' => false], $block->getProperty($declarations, '--wp--preset--color--Brand'));
        $t->same(
            'background-color: #ff0; border-top-color: #f006; border-inline-end-color: currentColor; text-decoration-color: #0f0; -webkit-text-decoration-color: #00f; text-emphasis-color: #f006; -webkit-text-emphasis-color: #0000; caret-color: auto; --wp--preset--color--Brand: Yellow; outline-color: #ff0 !important; color: #00f !important',
            $block->setProperty($declarations, 'color', 'BLUE', true)
        );
        $t->same(
            'color: red; border-top-color: #f006; border-inline-end-color: currentColor; text-decoration-color: #0f0; -webkit-text-decoration-color: #00f; text-emphasis-color: #f006; -webkit-text-emphasis-color: #0000; caret-color: auto; --wp--preset--color--Brand: Yellow; outline-color: #ff0 !important; background-color: currentColor !important',
            $block->setProperty($declarations, 'background-color', 'CURRENTCOLOR', true)
        );
        $t->same(
            'color: red; background-color: #ff0; border-top-color: #f006; border-inline-end-color: currentColor; text-decoration-color: #0f0; -webkit-text-decoration-color: #00f; text-emphasis-color: #f006; -webkit-text-emphasis-color: #0000; caret-color: auto; --wp--preset--color--Brand: Yellow; border-top-color: red; outline-color: #ff0 !important',
            $block->setProperty($declarations, 'border-top-color', '#ff0000')
        );
        $t->same(
            'color: red; background-color: #ff0; border-top-color: #f006; border-inline-end-color: currentColor; text-decoration-color: #0f0; -webkit-text-decoration-color: #00f; text-emphasis-color: #f006; -webkit-text-emphasis-color: #0000; caret-color: currentColor; --wp--preset--color--Brand: Yellow; outline-color: #ff0 !important',
            $block->setProperty($declarations, 'caret-color', 'CURRENTCOLOR')
        );
        $t->same(
            'color: red; background-color: #ff0; border-top-color: #f006; border-inline-end-color: currentColor; text-decoration-color: #0f0; -webkit-text-decoration-color: #00f; -webkit-text-emphasis-color: #0000; caret-color: auto; --wp--preset--color--Brand: Yellow; outline-color: #ff0 !important',
            $block->removeProperty($declarations, 'text-emphasis-color')
        );
    },
    'declaration block decodes escaped css property names in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'c\\6f lor: red !important; background-color: white; --Block\\2D Accent: blue';

        $t->same(
            [
                'color' => 'red !important',
                'background-color' => '#fff',
                '--Block-Accent' => 'blue',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'red', 'important' => true], $block->getProperty($declarations, 'color'));
        $t->same(['value' => 'red', 'important' => true], $block->getProperty($declarations, 'c\\6f lor'));
        $t->same(['value' => 'blue', 'important' => false], $block->getProperty($declarations, '--Block-Accent'));
        $t->same(['value' => 'blue', 'important' => false], $block->getProperty($declarations, '--Block\\2D Accent'));
        $t->same(3, $block->length($declarations));
        $t->same('background-color', $block->item($declarations, 0));
        $t->same('--Block-Accent', $block->item($declarations, 1));
        $t->same('color', $block->item($declarations, 2));
        $t->same(
            'background-color: #fff; --Block-Accent: blue; color: green',
            $block->setProperty($declarations, 'color', 'green')
        );
        $t->same(
            'background-color: #fff; --Block-Accent: green; color: red !important',
            $block->setProperty($declarations, '--Block\\2D Accent', 'green')
        );
        $t->same(
            'background-color: #fff; --Block-Accent: blue',
            $block->removeProperty($declarations, 'color')
        );
        $t->same(
            'background-color: #fff; color: red !important',
            $block->removeProperty($declarations, '--Block-Accent')
        );
    },
    'declaration block canonicalizes upstream all css-wide keywords in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            [
                'all' => 'initial',
                'color' => 'red',
            ],
            $block->parse('ALL: INITIAL; color: red')
        );
        $t->same(
            ['value' => 'inherit', 'important' => true],
            $block->getProperty('a\\6c l: InHeRiT !important; color: red', 'all')
        );
        $t->same(
            ['value' => 'var(--wp--custom--reset)', 'important' => false],
            $block->getProperty('all: var(--wp--custom--reset)', 'all')
        );
        $t->same(
            'color: red; all: revert-layer',
            $block->setProperty('all: initial !important; color: red', 'all', 'REVERT-LAYER')
        );
        $t->same(
            'all: unset; color: red',
            $block->setProperty('all: inherit; color: red', 'all', 'UNSET')
        );
        $t->same(
            'all: var(--wp--custom--reset); color: red',
            $block->setProperty('all: inherit; color: red', 'all', 'var(--wp--custom--reset)')
        );
        $t->same(
            'color: red; all: revert !important',
            $block->setProperty('all: initial; color: red', 'ALL', 'ReVeRt', true)
        );
        $t->same(
            'color: red',
            $block->removeProperty('all: initial; color: red; all: revert !important', 'all')
        );
    },
    'declaration block canonicalizes upstream css-wide keywords for normal cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            [
                'color' => 'inherit',
                'margin' => 'revert-layer',
                'border-spacing' => 'revert',
                '--Block-Reset' => 'InHeRiT',
            ],
            $block->parse('color: InHeRiT; margin: REVERT-LAYER; border-spacing: ReVeRt; --Block-Reset: InHeRiT')
        );
        $t->same(['value' => 'inherit', 'important' => false], $block->getProperty('color: InHeRiT', 'color'));
        $t->same(['value' => 'revert-layer', 'important' => true], $block->getProperty('margin: REVERT-LAYER !important', 'margin-top'));
        $t->same(['value' => 'revert', 'important' => false], $block->getProperty('border-spacing: ReVeRt', 'border-spacing'));
        $t->same(['value' => 'InHeRiT', 'important' => false], $block->getProperty('--Block-Reset: InHeRiT', '--Block-Reset'));
        $t->same(
            'background: white; color: revert-layer',
            $block->setProperty('color: red !important; background: white', 'color', 'ReVeRt-LaYeR')
        );
        $t->same(
            'color: red; margin: revert !important',
            $block->setProperty('margin: 1rem; color: red', 'margin', 'ReVeRt', true)
        );
        $t->same(
            'color: red; border-spacing: unset',
            $block->setProperty('border-spacing: 0px 0px !important; color: red', 'border-spacing', 'UNSET')
        );
        $t->same(
            '--Block-Reset: InHeRiT; color: red',
            $block->setProperty('--Block-Reset: red; color: red', '--Block-Reset', 'InHeRiT')
        );
        $t->same(
            'color: red',
            $block->removeProperty('color: red; margin: ReVeRt-LaYeR !important', 'margin')
        );
    },
    'declaration block canonicalizes upstream direct enum cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'color-scheme: Dark Light Only; print-color-adjust: Exact; -webkit-print-color-adjust: Economy; view-transition-name: AUTO; view-transition-class: None; view-transition-group: NEAREST; --Block-State: AUTO';
        $customTransitions = 'view-transition-name: c\61 rd-enter; view-transition-class: nav\2d menu thumb; view-transition-group: c\61 rd-group; --View-Transition-Class: nav\2d menu';

        $t->same(
            [
                'color-scheme' => 'light dark only',
                'print-color-adjust' => 'exact',
                '-webkit-print-color-adjust' => 'economy',
                'view-transition-name' => 'auto',
                'view-transition-class' => 'none',
                'view-transition-group' => 'nearest',
                '--Block-State' => 'AUTO',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'light dark only', 'important' => false], $block->getProperty($declarations, 'color-scheme'));
        $t->same(['value' => 'exact', 'important' => false], $block->getProperty($declarations, 'print-color-adjust'));
        $t->same(['value' => 'economy', 'important' => false], $block->getProperty($declarations, '-webkit-print-color-adjust'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty($declarations, 'view-transition-name'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($declarations, 'view-transition-class'));
        $t->same(['value' => 'nearest', 'important' => false], $block->getProperty($declarations, 'view-transition-group'));
        $t->same(
            [
                'view-transition-name' => 'card-enter',
                'view-transition-class' => 'nav-menu thumb',
                'view-transition-group' => 'card-group',
                '--View-Transition-Class' => 'nav\2d menu',
            ],
            $block->parse($customTransitions)
        );
        $t->same(['value' => 'card-enter', 'important' => false], $block->getProperty($customTransitions, 'view-transition-name'));
        $t->same(['value' => 'nav-menu thumb', 'important' => false], $block->getProperty($customTransitions, 'view-transition-class'));
        $t->same(['value' => 'card-group', 'important' => false], $block->getProperty($customTransitions, 'view-transition-group'));
        $t->same(
            'color-scheme: dark only; print-color-adjust: exact; -webkit-print-color-adjust: economy; view-transition-name: auto; view-transition-class: none; view-transition-group: nearest; --Block-State: AUTO',
            $block->setProperty($declarations, 'color-scheme', 'ONLY DARK')
        );
        $t->same(
            'color-scheme: light dark only; print-color-adjust: economy; -webkit-print-color-adjust: economy; view-transition-name: auto; view-transition-class: none; view-transition-group: nearest; --Block-State: AUTO',
            $block->setProperty($declarations, 'print-color-adjust', 'Economy')
        );
        $t->same(
            'color-scheme: light dark only; print-color-adjust: exact; -webkit-print-color-adjust: exact; view-transition-name: auto; view-transition-class: none; view-transition-group: nearest; --Block-State: AUTO',
            $block->setProperty($declarations, '-webkit-print-color-adjust', 'Exact')
        );
        $t->same(
            'color-scheme: light dark only; print-color-adjust: exact; -webkit-print-color-adjust: economy; view-transition-name: card-enter; view-transition-class: none; view-transition-group: nearest; --Block-State: AUTO',
            $block->setProperty($declarations, 'view-transition-name', 'card-enter')
        );
        $t->same(
            'color: red; view-transition-class: card-enter thumb',
            $block->setProperty('color: red', 'view-transition-class', 'c\61 rd-enter thumb')
        );
        $t->same(
            'view-transition-name: card-enter; view-transition-class: nav-menu thumb; view-transition-group: hero-group; --View-Transition-Class: nav\2d menu',
            $block->setProperty($customTransitions, 'view-transition-group', 'h\65 ro-group')
        );
        $t->same(
            'color-scheme: light dark only; print-color-adjust: exact; -webkit-print-color-adjust: economy; view-transition-name: auto; view-transition-class: none; view-transition-group: contain; --Block-State: AUTO',
            $block->setProperty($declarations, 'view-transition-group', 'CONTAIN')
        );
        $t->same(
            'color: red; color-scheme: light dark only',
            $block->setProperty('color: red', 'color-scheme', 'dark light only')
        );
        $t->same(
            'color: red; -moz-print-color-adjust: exact',
            $block->setProperty('color: red', '-moz-print-color-adjust', 'EXACT')
        );
        $t->same(
            'print-color-adjust: exact; -webkit-print-color-adjust: economy; view-transition-name: auto; view-transition-class: none; view-transition-group: nearest; --Block-State: AUTO',
            $block->removeProperty($declarations, 'color-scheme')
        );
    },
    'declaration block canonicalizes upstream display layout cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'display: Inline Flow-Root; visibility: Collapse; position: Sticky; box-sizing: Border-Box; text-overflow: Ellipsis; vertical-align: SUPER; transform-style: Preserve-3D; transform-box: Fill-Box; backface-visibility: Hidden; mix-blend-mode: Multiply; perspective: 0px; z-index: AUTO; --Display: Inline Flow-Root';

        $t->same(
            [
                'display' => 'inline-block',
                'visibility' => 'collapse',
                'position' => 'sticky',
                'box-sizing' => 'border-box',
                'text-overflow' => 'ellipsis',
                'vertical-align' => 'super',
                'transform-style' => 'preserve-3d',
                'transform-box' => 'fill-box',
                'backface-visibility' => 'hidden',
                'mix-blend-mode' => 'multiply',
                'perspective' => '0',
                'z-index' => 'auto',
                '--Display' => 'Inline Flow-Root',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'inline-block', 'important' => false], $block->getProperty($declarations, 'display'));
        $t->same(['value' => 'collapse', 'important' => false], $block->getProperty($declarations, 'visibility'));
        $t->same(['value' => 'super', 'important' => false], $block->getProperty($declarations, 'vertical-align'));
        $t->same(['value' => '0', 'important' => false], $block->getProperty($declarations, 'perspective'));
        $t->same(['value' => 'Inline Flow-Root', 'important' => false], $block->getProperty($declarations, '--Display'));
        $t->same(['value' => 'block', 'important' => false], $block->getProperty('display: block flow', 'display'));
        $t->same(['value' => 'inline', 'important' => false], $block->getProperty('display: inline flow', 'display'));
        $t->same(['value' => 'flow-root list-item', 'important' => false], $block->getProperty('display: flow-root list-item', 'display'));
        $t->same(['value' => 'ruby', 'important' => false], $block->getProperty('display: ruby', 'display'));
        $t->same(['value' => 'inline-grid', 'important' => false], $block->getProperty('display: inline grid', 'display'));
        $t->same(['value' => 'inline-block', 'important' => false], $block->getProperty('display: Inline-Block', 'display'));
        $t->same(
            'display: inline-flex; visibility: collapse; position: sticky; box-sizing: border-box; text-overflow: ellipsis; vertical-align: super; transform-style: preserve-3d; transform-box: fill-box; backface-visibility: hidden; mix-blend-mode: multiply; perspective: 0; z-index: auto; --Display: Inline Flow-Root',
            $block->setProperty($declarations, 'display', 'inline flex')
        );
        $t->same(
            'display: inline-block; visibility: collapse; position: sticky; box-sizing: border-box; text-overflow: ellipsis; vertical-align: 12px; transform-style: preserve-3d; transform-box: fill-box; backface-visibility: hidden; mix-blend-mode: multiply; perspective: 0; z-index: auto; --Display: Inline Flow-Root',
            $block->setProperty($declarations, 'vertical-align', '12PX')
        );
        $t->same(
            'display: inline-block; visibility: collapse; position: sticky; box-sizing: border-box; text-overflow: ellipsis; vertical-align: super; transform-style: preserve-3d; transform-box: fill-box; backface-visibility: hidden; mix-blend-mode: multiply; perspective: .5px; z-index: auto; --Display: Inline Flow-Root',
            $block->setProperty($declarations, 'perspective', '.5000PX')
        );
        $t->same(
            'display: inline-block; position: sticky; box-sizing: border-box; text-overflow: ellipsis; vertical-align: super; transform-style: preserve-3d; transform-box: fill-box; backface-visibility: hidden; mix-blend-mode: multiply; perspective: 0; z-index: auto; --Display: Inline Flow-Root; visibility: hidden !important',
            $block->setProperty($declarations, 'visibility', 'Hidden', true)
        );
        $t->same(
            'visibility: collapse; position: sticky; box-sizing: border-box; text-overflow: ellipsis; vertical-align: super; transform-style: preserve-3d; transform-box: fill-box; backface-visibility: hidden; mix-blend-mode: multiply; perspective: 0; z-index: auto; --Display: Inline Flow-Root',
            $block->removeProperty($declarations, 'display')
        );
    },
    'declaration block canonicalizes upstream ui direct enum cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'resize: Horizontal; user-select: Text; -webkit-user-select: NONE; appearance: SearchField; -moz-appearance: Menulist-Button; -ms-appearance: Button-Bevel; --Editor-Appearance: SearchField';

        $t->same(
            [
                'resize' => 'horizontal',
                'user-select' => 'text',
                '-webkit-user-select' => 'none',
                'appearance' => 'searchfield',
                '-moz-appearance' => 'menulist-button',
                '-ms-appearance' => 'Button-Bevel',
                '--Editor-Appearance' => 'SearchField',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'horizontal', 'important' => false], $block->getProperty($declarations, 'resize'));
        $t->same(['value' => 'text', 'important' => false], $block->getProperty($declarations, 'user-select'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($declarations, '-webkit-user-select'));
        $t->same(['value' => 'searchfield', 'important' => false], $block->getProperty($declarations, 'appearance'));
        $t->same(['value' => 'menulist-button', 'important' => false], $block->getProperty($declarations, '-moz-appearance'));
        $t->same(['value' => 'Button-Bevel', 'important' => false], $block->getProperty($declarations, '-ms-appearance'));
        $t->same(['value' => 'SearchField', 'important' => false], $block->getProperty($declarations, '--Editor-Appearance'));
        $t->same(
            'resize: block; user-select: text; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; -ms-appearance: Button-Bevel; --Editor-Appearance: SearchField',
            $block->setProperty($declarations, 'resize', 'Block')
        );
        $t->same(
            'resize: horizontal; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; -ms-appearance: Button-Bevel; --Editor-Appearance: SearchField; user-select: all !important',
            $block->setProperty($declarations, 'user-select', 'ALL', true)
        );
        $t->same(
            'resize: horizontal; user-select: text; -webkit-user-select: contain; appearance: searchfield; -moz-appearance: menulist-button; -ms-appearance: Button-Bevel; --Editor-Appearance: SearchField',
            $block->setProperty($declarations, '-webkit-user-select', 'Contain')
        );
        $t->same(
            'resize: horizontal; user-select: text; -webkit-user-select: none; appearance: textarea; -moz-appearance: menulist-button; -ms-appearance: Button-Bevel; --Editor-Appearance: SearchField',
            $block->setProperty($declarations, 'appearance', 'TextArea')
        );
        $t->same(
            'appearance: Button-Bevel',
            $block->setProperty('appearance: SearchField', 'appearance', 'Button-Bevel')
        );
        $t->same(
            'resize: horizontal; user-select: text; -webkit-user-select: none; -moz-appearance: menulist-button; -ms-appearance: Button-Bevel; --Editor-Appearance: SearchField',
            $block->removeProperty($declarations, 'appearance')
        );
    },
    'declaration block canonicalizes upstream layout and effects direct cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'visibility: Hidden; box-sizing: Border-Box; position: -WebKit-Sticky; text-overflow: Ellipsis; mix-blend-mode: Plus-Lighter; z-index: +0010; aspect-ratio: 16.0 / 9.00; --Editor-State: Hidden';

        $t->same(
            [
                'visibility' => 'hidden',
                'box-sizing' => 'border-box',
                'position' => '-webkit-sticky',
                'text-overflow' => 'ellipsis',
                'mix-blend-mode' => 'plus-lighter',
                'z-index' => '10',
                'aspect-ratio' => '16 / 9',
                '--Editor-State' => 'Hidden',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'hidden', 'important' => false], $block->getProperty($declarations, 'visibility'));
        $t->same(['value' => 'border-box', 'important' => false], $block->getProperty($declarations, 'box-sizing'));
        $t->same(['value' => '-webkit-sticky', 'important' => false], $block->getProperty($declarations, 'position'));
        $t->same(['value' => 'ellipsis', 'important' => false], $block->getProperty($declarations, 'text-overflow'));
        $t->same(['value' => 'plus-lighter', 'important' => false], $block->getProperty($declarations, 'mix-blend-mode'));
        $t->same(['value' => '10', 'important' => false], $block->getProperty($declarations, 'z-index'));
        $t->same(['value' => '16 / 9', 'important' => false], $block->getProperty($declarations, 'aspect-ratio'));
        $t->same(['value' => 'Hidden', 'important' => false], $block->getProperty($declarations, '--Editor-State'));
        $t->same(
            'visibility: hidden; box-sizing: border-box; position: sticky; text-overflow: ellipsis; mix-blend-mode: plus-lighter; z-index: 10; aspect-ratio: 16 / 9; --Editor-State: Hidden',
            $block->setProperty($declarations, 'position', 'Sticky')
        );
        $t->same(
            'visibility: hidden; box-sizing: border-box; position: -webkit-sticky; text-overflow: ellipsis; mix-blend-mode: plus-lighter; aspect-ratio: 16 / 9; --Editor-State: Hidden; z-index: -5 !important',
            $block->setProperty($declarations, 'z-index', '-0005', true)
        );
        $t->same(
            'visibility: hidden; box-sizing: border-box; position: -webkit-sticky; text-overflow: ellipsis; mix-blend-mode: plus-lighter; z-index: 10; aspect-ratio: auto 9; --Editor-State: Hidden',
            $block->setProperty($declarations, 'aspect-ratio', '9.0 / 1.0 auto')
        );
        $t->same(
            'visibility: hidden; box-sizing: border-box; position: -webkit-sticky; text-overflow: ellipsis; z-index: 10; aspect-ratio: 16 / 9; --Editor-State: Hidden; mix-blend-mode: color-dodge !important',
            $block->setProperty($declarations, 'mix-blend-mode', 'Color-Dodge', true)
        );
        $t->same(
            'visibility: hidden; box-sizing: border-box; position: -webkit-sticky; mix-blend-mode: plus-lighter; z-index: 10; aspect-ratio: 16 / 9; --Editor-State: Hidden',
            $block->removeProperty($declarations, 'text-overflow')
        );
    },
    'declaration block canonicalizes upstream cursor cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'cursor: URL("drag.cur") 4.0 12.00, Grab !important; --Block-Cursor: URL("drag.cur") 4.0 12.00, Grab; color: red';

        $t->same(
            [
                'cursor' => 'url(drag.cur) 4 12, grab !important',
                '--Block-Cursor' => 'URL("drag.cur") 4.0 12.00, Grab',
                'color' => 'red',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'url(drag.cur) 4 12, grab', 'important' => true], $block->getProperty($declarations, 'cursor'));
        $t->same(['value' => 'URL("drag.cur") 4.0 12.00, Grab', 'important' => false], $block->getProperty($declarations, '--Block-Cursor'));
        $t->same(
            '--Block-Cursor: URL("drag.cur") 4.0 12.00, Grab; color: red; cursor: url(hand.cur), ew-resize',
            $block->setProperty($declarations, 'cursor', 'url("hand.cur"), EW-RESIZE')
        );
        $t->same(
            '--Block-Cursor: URL("drag.cur") 4.0 12.00, Grab; color: red; cursor: url(hand.cur) 2 4, zoom-in !important',
            $block->setProperty($declarations, 'cursor', 'url("hand.cur") 2.0 4.00, Zoom-In', true)
        );
        $t->same(
            '--Block-Cursor: URL("drag.cur") 4.0 12.00, Grab; color: red',
            $block->removeProperty($declarations, 'cursor')
        );
    },
    'declaration block canonicalizes upstream text and writing direct cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'text-transform: UpperCase full-size-kana full-width; white-space: Pre-Wrap; word-break: Break-All; line-break: Anywhere; hyphens: Manual; -webkit-hyphens: AUTO; overflow-wrap: Break-Word; word-wrap: Anywhere; text-align: Match-Parent; text-align-last: Justify; -moz-text-align-last: CENTER; text-justify: Inter-Character; direction: RTL; unicode-bidi: Isolate-Override; box-decoration-break: Clone; -webkit-box-decoration-break: Slice; text-size-adjust: 100.0%; -webkit-text-size-adjust: NONE; marker-side: Match-Parent; --Text-Transform: UpperCase';

        $t->same(
            [
                'text-transform' => 'uppercase full-width full-size-kana',
                'white-space' => 'pre-wrap',
                'word-break' => 'break-all',
                'line-break' => 'anywhere',
                'hyphens' => 'manual',
                '-webkit-hyphens' => 'auto',
                'overflow-wrap' => 'break-word',
                'word-wrap' => 'anywhere',
                'text-align' => 'match-parent',
                'text-align-last' => 'justify',
                '-moz-text-align-last' => 'center',
                'text-justify' => 'inter-character',
                'direction' => 'rtl',
                'unicode-bidi' => 'isolate-override',
                'box-decoration-break' => 'clone',
                '-webkit-box-decoration-break' => 'slice',
                'text-size-adjust' => '100%',
                '-webkit-text-size-adjust' => 'none',
                'marker-side' => 'match-parent',
                '--Text-Transform' => 'UpperCase',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'uppercase full-width full-size-kana', 'important' => false], $block->getProperty($declarations, 'text-transform'));
        $t->same(['value' => 'full-width full-size-kana', 'important' => false], $block->getProperty('text-transform: full-size-kana full-width', 'text-transform'));
        $t->same(['value' => 'pre-wrap', 'important' => false], $block->getProperty($declarations, 'white-space'));
        $t->same(['value' => 'break-all', 'important' => false], $block->getProperty($declarations, 'word-break'));
        $t->same(['value' => 'anywhere', 'important' => false], $block->getProperty($declarations, 'line-break'));
        $t->same(['value' => 'manual', 'important' => false], $block->getProperty($declarations, 'hyphens'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty($declarations, '-webkit-hyphens'));
        $t->same(['value' => 'break-word', 'important' => false], $block->getProperty($declarations, 'overflow-wrap'));
        $t->same(['value' => 'anywhere', 'important' => false], $block->getProperty($declarations, 'word-wrap'));
        $t->same(['value' => 'match-parent', 'important' => false], $block->getProperty($declarations, 'text-align'));
        $t->same(['value' => 'justify', 'important' => false], $block->getProperty($declarations, 'text-align-last'));
        $t->same(['value' => 'center', 'important' => false], $block->getProperty($declarations, '-moz-text-align-last'));
        $t->same(['value' => 'inter-character', 'important' => false], $block->getProperty($declarations, 'text-justify'));
        $t->same(['value' => 'rtl', 'important' => false], $block->getProperty($declarations, 'direction'));
        $t->same(['value' => 'isolate-override', 'important' => false], $block->getProperty($declarations, 'unicode-bidi'));
        $t->same(['value' => 'clone', 'important' => false], $block->getProperty($declarations, 'box-decoration-break'));
        $t->same(['value' => 'slice', 'important' => false], $block->getProperty($declarations, '-webkit-box-decoration-break'));
        $t->same(['value' => '100%', 'important' => false], $block->getProperty($declarations, 'text-size-adjust'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($declarations, '-webkit-text-size-adjust'));
        $t->same(['value' => 'match-parent', 'important' => false], $block->getProperty($declarations, 'marker-side'));
        $t->same(['value' => 'UpperCase', 'important' => false], $block->getProperty($declarations, '--Text-Transform'));
        $t->same(
            'text-transform: lowercase full-size-kana; color: red',
            $block->setProperty('text-transform: UpperCase full-width; color: red', 'text-transform', 'LowerCase full-size-kana')
        );
        $t->same(
            'white-space: break-spaces; text-align: match-parent !important',
            $block->setProperty('text-align: Center; white-space: Break-Spaces', 'text-align', 'MATCH-PARENT', true)
        );
        $t->same(
            'text-size-adjust: 100%; color: red; -webkit-text-size-adjust: none',
            $block->setProperty('text-size-adjust: 100.0%; color: red', '-webkit-text-size-adjust', 'None')
        );
        $t->same(
            'box-decoration-break: slice; -webkit-box-decoration-break: slice',
            $block->setProperty('box-decoration-break: Clone; -webkit-box-decoration-break: Slice', 'box-decoration-break', 'slice')
        );
        $t->same(
            'unicode-bidi: isolate; marker-side: match-self',
            $block->removeProperty('direction: RTL; unicode-bidi: Isolate; marker-side: Match-Self', 'direction')
        );
    },
    'declaration block canonicalizes upstream text spacing and tab size cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'tab-size: +004; -moz-tab-size: 4PX; -o-tab-size: 0px; word-spacing: NORMAL; letter-spacing: 0.500EM; text-indent: each-line hanging 3.00em; --Text-Indent: each-line hanging 3.00em';

        $t->same(
            [
                'tab-size' => '4',
                '-moz-tab-size' => '4px',
                '-o-tab-size' => '0',
                'word-spacing' => 'normal',
                'letter-spacing' => '.5em',
                'text-indent' => '3em hanging each-line',
                '--Text-Indent' => 'each-line hanging 3.00em',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => '4', 'important' => false], $block->getProperty($declarations, 'tab-size'));
        $t->same(['value' => '4px', 'important' => false], $block->getProperty($declarations, '-moz-tab-size'));
        $t->same(['value' => '0', 'important' => false], $block->getProperty($declarations, '-o-tab-size'));
        $t->same(['value' => 'normal', 'important' => false], $block->getProperty($declarations, 'word-spacing'));
        $t->same(['value' => '.5em', 'important' => false], $block->getProperty($declarations, 'letter-spacing'));
        $t->same(['value' => '3em hanging each-line', 'important' => false], $block->getProperty($declarations, 'text-indent'));
        $t->same(['value' => 'each-line hanging 3.00em', 'important' => false], $block->getProperty($declarations, '--Text-Indent'));
        $t->same(
            'tab-size: 2; color: red',
            $block->setProperty('tab-size: +004; color: red', 'tab-size', '+002')
        );
        $t->same(
            'tab-size: 4; color: red; -moz-tab-size: 8px !important',
            $block->setProperty('tab-size: +004; -moz-tab-size: 4PX; color: red', '-moz-tab-size', '8PX', true)
        );
        $t->same(
            'color: red; word-spacing: 3px !important',
            $block->setProperty('word-spacing: 0px; color: red', 'word-spacing', '+3.00PX', true)
        );
        $t->same(
            'letter-spacing: normal; color: red',
            $block->setProperty('letter-spacing: 0.500EM; color: red', 'letter-spacing', 'Normal')
        );
        $t->same(
            'text-indent: 2.5em hanging each-line; color: red',
            $block->setProperty('text-indent: each-line hanging 3.00em; color: red', 'text-indent', 'hanging 2.500em each-line')
        );
        $t->same(
            'tab-size: 4; -moz-tab-size: 4px; -o-tab-size: 0; word-spacing: normal; letter-spacing: .5em; --Text-Indent: each-line hanging 3.00em',
            $block->removeProperty($declarations, 'text-indent')
        );
    },
    'declaration block canonicalizes upstream border spacing cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            [
                'border-spacing' => '0',
                'color' => 'red',
            ],
            $block->parse('border-spacing: 0px 0px; color: red')
        );
        $t->same(
            ['value' => '12px 0', 'important' => true],
            $block->getProperty('border-spacing: 12px 0px !important; color: red', 'border-spacing')
        );
        $t->same(
            ['value' => '-20px', 'important' => false],
            $block->getProperty('border-spacing: -20px -20px', 'border-spacing')
        );
        $t->same(
            'border-spacing: 4px; color: red',
            $block->setProperty('border-spacing: 0px 0px; color: red', 'border-spacing', '4px 4px')
        );
        $t->same(
            'color: red; border-spacing: 0 12px',
            $block->setProperty('color: red', 'border-spacing', '0px 12px')
        );
        $t->same(
            'color: red; border-spacing: 8px !important',
            $block->setProperty('border-spacing: 12px 0px !important; color: red', 'border-spacing', '8px 8px', true)
        );
        $t->same(
            'color: red',
            $block->removeProperty('border-spacing: 0px 0px; color: red', 'border-spacing')
        );
    },
    'declaration block canonicalizes upstream shadow cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            [
                'box-shadow' => '12px 12px #0006',
                'text-shadow' => '1px 1px #ff0',
            ],
            $block->parse('box-shadow: 12px 12px 0px 0px rgba(0,0,0,0.4); text-shadow: 1px 1px 0 yellow')
        );
        $t->same(
            ['value' => 'inset 0 0 12px 4px #0006', 'important' => true],
            $block->getProperty('box-shadow: 0px 0px 12px 4px rgba(0,0,0,0.4) inset !important', 'box-shadow')
        );
        $t->same(
            ['value' => '1px 1px #ff0, 2px 3px red', 'important' => false],
            $block->getProperty('text-shadow: 1px 1px yellow, 2px 3px red', 'text-shadow')
        );
        $t->same(
            ['value' => '12px 12px', 'important' => false],
            $block->getProperty('box-shadow: 12px 12px currentColor', 'box-shadow')
        );
        $t->same(
            'color: red; box-shadow: inset 0 0 12px 4px #0006',
            $block->setProperty('color: red', 'box-shadow', '0px 0px 12px 4px rgba(0,0,0,0.4) inset')
        );
        $t->same(
            'box-shadow: 12px 12px #0006; text-shadow: 1px 1px #ff0 !important',
            $block->setProperty('box-shadow: 12px 12px rgba(0,0,0,0.4); text-shadow: 2px 2px blue', 'text-shadow', '1px 1px 0 yellow', true)
        );
        $t->same(
            '-webkit-box-shadow: 12px 12px #0006',
            $block->setProperty('-webkit-box-shadow: 12px 12px rgba(0,0,0,0.4)', '-webkit-box-shadow', '12px 12px 0px rgba(0,0,0,0.4)')
        );
        $t->same(
            'text-shadow: 1px 1px #ff0',
            $block->removeProperty('box-shadow: 12px 12px rgba(0,0,0,0.4); text-shadow: 1px 1px yellow', 'box-shadow')
        );
    },
    'declaration block canonicalizes upstream filter cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'filter: URL("filters.svg#filter-id") Blur(0px) Brightness(100%) drop-shadow(16px 16px 20px yellow) !important; backdrop-filter: Blur(0px); -webkit-filter: hue-rotate(0); --Filter: Blur(0px)';

        $t->same(
            [
                'filter' => 'url(filters.svg#filter-id) blur()brightness() drop-shadow(16px 16px 20px #ff0) !important',
                'backdrop-filter' => 'blur()',
                '-webkit-filter' => 'hue-rotate()',
                '--Filter' => 'Blur(0px)',
            ],
            $block->parse($declarations)
        );
        $t->same(
            ['value' => 'url(filters.svg#filter-id) blur()brightness() drop-shadow(16px 16px 20px #ff0)', 'important' => true],
            $block->getProperty($declarations, 'filter')
        );
        $t->same(['value' => 'blur()', 'important' => false], $block->getProperty($declarations, 'backdrop-filter'));
        $t->same(['value' => 'hue-rotate()', 'important' => false], $block->getProperty($declarations, '-webkit-filter'));
        $t->same(['value' => 'Blur(0px)', 'important' => false], $block->getProperty($declarations, '--Filter'));
        $t->same(
            'backdrop-filter: blur()brightness(10%); -webkit-filter: hue-rotate(); --Filter: Blur(0px); filter: url(filters.svg#filter-id) blur()brightness() drop-shadow(16px 16px 20px #ff0) !important',
            $block->setProperty($declarations, 'backdrop-filter', 'Blur(0px) Brightness(10%)')
        );
        $t->same(
            'backdrop-filter: blur(); -webkit-filter: hue-rotate(); --Filter: Blur(0px); filter: contrast(175%)brightness(3%)',
            $block->setProperty($declarations, 'filter', 'contrast(175%) brightness(3%)')
        );
        $t->same(
            'backdrop-filter: blur(); --Filter: Blur(0px); filter: url(filters.svg#filter-id) blur()brightness() drop-shadow(16px 16px 20px #ff0) !important',
            $block->removeProperty($declarations, '-webkit-filter')
        );
    },
    'declaration block canonicalizes upstream svg paint and rendering cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'fill: url("#wp-gradient") currentColor; stroke: rgba(255,0,0,.4); stroke-dasharray: 0px, 2px 4px; stroke-linecap: ROUND; stroke-linejoin: Miter; text-rendering: geometricPrecision; shape-rendering: crispEdges; color-interpolation: sRGB; color-interpolation-filters: linearRGB; fill-rule: EVENODD; clip-rule: NONZERO; marker-start: url("#start"); marker-end: NONE; stroke-width: 2.500px; stroke-dashoffset: 0px; stroke-miterlimit: 4.000; --Icon-Render: geometricPrecision';

        $t->same(
            [
                'fill' => 'url(#wp-gradient) currentColor',
                'stroke' => '#f006',
                'stroke-dasharray' => '0 2 4',
                'stroke-linecap' => 'round',
                'stroke-linejoin' => 'miter',
                'text-rendering' => 'geometricprecision',
                'shape-rendering' => 'crispedges',
                'color-interpolation' => 'srgb',
                'color-interpolation-filters' => 'linearrgb',
                'fill-rule' => 'evenodd',
                'clip-rule' => 'nonzero',
                'marker-start' => 'url(#start)',
                'marker-end' => 'none',
                'stroke-width' => '2.5px',
                'stroke-dashoffset' => '0',
                'stroke-miterlimit' => '4',
                '--Icon-Render' => 'geometricPrecision',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'url(#wp-gradient) currentColor', 'important' => false], $block->getProperty($declarations, 'fill'));
        $t->same(['value' => '#f006', 'important' => false], $block->getProperty($declarations, 'stroke'));
        $t->same(['value' => '0 2 4', 'important' => false], $block->getProperty($declarations, 'stroke-dasharray'));
        $t->same(['value' => 'geometricprecision', 'important' => false], $block->getProperty($declarations, 'text-rendering'));
        $t->same(['value' => 'srgb', 'important' => false], $block->getProperty($declarations, 'color-interpolation'));
        $t->same(['value' => 'url(#start)', 'important' => false], $block->getProperty($declarations, 'marker-start'));
        $t->same(['value' => 'geometricPrecision', 'important' => false], $block->getProperty($declarations, '--Icon-Render'));
        $t->same(
            'fill: url(#editor-gradient) #ff0; stroke: #f006; color: red',
            $block->setProperty('fill: url("#wp-gradient") currentColor; stroke: rgba(255,0,0,.4); color: red', 'fill', 'url("#editor-gradient") yellow')
        );
        $t->same(
            'stroke-dasharray: .5 25% 4; color: red',
            $block->setProperty('stroke-dasharray: 1px 2px; color: red', 'stroke-dasharray', '0.500px 25% 4')
        );
        $t->same(
            'shape-rendering: crispedges; text-rendering: optimizelegibility !important',
            $block->setProperty('text-rendering: geometricPrecision; shape-rendering: crispEdges', 'text-rendering', 'optimizeLegibility', true)
        );
        $t->same(
            'marker-start: url(#editor-marker); marker-end: none',
            $block->setProperty('marker-start: url("#start"); marker-end: NONE', 'marker-start', 'url("#editor-marker")')
        );
        $t->same(
            'stroke: #f006; stroke-dasharray: 0 2 4',
            $block->removeProperty('fill: url("#wp-gradient") currentColor; stroke: rgba(255,0,0,.4); stroke-dasharray: 0px, 2px 4px', 'fill')
        );
    },
    'declaration block canonicalizes upstream svg color and image rendering cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'color-rendering: optimizeSpeed; image-rendering: optimizeQuality !important; color: red; --Raster-Mode: optimizeSpeed';

        $t->same(
            [
                'color-rendering' => 'optimizespeed',
                'image-rendering' => 'optimizequality !important',
                'color' => 'red',
                '--Raster-Mode' => 'optimizeSpeed',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'optimizespeed', 'important' => false], $block->getProperty($declarations, 'color-rendering'));
        $t->same(['value' => 'optimizequality', 'important' => true], $block->getProperty($declarations, 'image-rendering'));
        $t->same(['value' => 'optimizeSpeed', 'important' => false], $block->getProperty($declarations, '--Raster-Mode'));
        $t->same(
            'color-rendering: optimizequality; color: red; --Raster-Mode: optimizeSpeed; image-rendering: optimizequality !important',
            $block->setProperty($declarations, 'color-rendering', 'optimizeQuality')
        );
        $t->same(
            'color-rendering: optimizespeed; color: red; --Raster-Mode: optimizeSpeed; image-rendering: optimizespeed !important',
            $block->setProperty($declarations, 'image-rendering', 'optimizeSpeed', true)
        );
        $t->same(
            'color: red; --Raster-Mode: optimizeSpeed; image-rendering: optimizequality !important',
            $block->removeProperty($declarations, 'color-rendering')
        );
    },
    'declaration block canonicalizes upstream svg stroke linejoin miter clip cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'stroke-linejoin: Miter-Clip !important; stroke-linecap: ROUND; color: red; --Line-Join: Miter-Clip';

        $t->same(
            [
                'stroke-linejoin' => 'miter-clip !important',
                'stroke-linecap' => 'round',
                'color' => 'red',
                '--Line-Join' => 'Miter-Clip',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'miter-clip', 'important' => true], $block->getProperty($declarations, 'stroke-linejoin'));
        $t->same(['value' => 'round', 'important' => false], $block->getProperty($declarations, 'stroke-linecap'));
        $t->same(['value' => 'Miter-Clip', 'important' => false], $block->getProperty($declarations, '--Line-Join'));
        $t->same(
            'stroke-linejoin: miter-clip; color: red',
            $block->setProperty('stroke-linejoin: Miter; color: red', 'stroke-linejoin', 'Miter-Clip')
        );
        $t->same(
            'color: red; stroke-linejoin: miter-clip !important',
            $block->setProperty('stroke-linejoin: Miter; color: red', 'stroke-linejoin', 'Miter-Clip', true)
        );
        $t->same(
            'stroke-linecap: round; color: red; --Line-Join: Miter-Clip',
            $block->removeProperty($declarations, 'stroke-linejoin')
        );
    },
    'declaration block canonicalizes upstream clip path cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'clip-path: padding-box circle(50px at 0 100px) !important; -webkit-clip-path: url("clip.svg#star"); --Clip-Path: padding-box circle(50px at 0 100px)';

        $t->same(
            [
                'clip-path' => 'circle(50px at 0 100px) padding-box !important',
                '-webkit-clip-path' => 'url(clip.svg#star)',
                '--Clip-Path' => 'padding-box circle(50px at 0 100px)',
            ],
            $block->parse($declarations)
        );
        $t->same(
            ['value' => 'circle(50px at 0 100px) padding-box', 'important' => true],
            $block->getProperty($declarations, 'clip-path')
        );
        $t->same(['value' => 'url(clip.svg#star)', 'important' => false], $block->getProperty($declarations, '-webkit-clip-path'));
        $t->same(
            ['value' => 'padding-box circle(50px at 0 100px)', 'important' => false],
            $block->getProperty($declarations, '--Clip-Path')
        );
        $t->same(
            ['value' => 'inset(100px 50px round 5px)', 'important' => false],
            $block->getProperty('clip-path: inset(100px 50px round 5px 5px 5px 5px)', 'clip-path')
        );
        $t->same(
            ['value' => 'circle()', 'important' => false],
            $block->getProperty('clip-path: circle(closest-side at 50% 50%)', 'clip-path')
        );
        $t->same(
            ['value' => 'ellipse(at 10% 20%)', 'important' => false],
            $block->getProperty('clip-path: ellipse(closest-side closest-side at 10% 20%)', 'clip-path')
        );
        $t->same(
            ['value' => 'polygon(50% 0%,100% 50%,50% 100%,0% 50%)', 'important' => false],
            $block->getProperty('clip-path: polygon(nonzero, 50% 0%, 100% 50%, 50% 100%, 0% 50%)', 'clip-path')
        );
        $t->same(
            ['value' => 'polygon(evenodd,50% 0%,100% 50%)', 'important' => false],
            $block->getProperty('clip-path: polygon(evenodd, 50% 0%, 100% 50%)', 'clip-path')
        );
        $t->same(['value' => 'margin-box', 'important' => false], $block->getProperty('clip-path: margin-box', 'clip-path'));
        $t->same(
            '-webkit-clip-path: url(clip.svg#star); --Clip-Path: padding-box circle(50px at 0 100px); clip-path: circle()',
            $block->setProperty($declarations, 'clip-path', 'circle(closest-side at 50% 50%)')
        );
        $t->same(
            '-webkit-clip-path: circle(50px at 0 100px) padding-box; --Clip-Path: padding-box circle(50px at 0 100px); clip-path: circle(50px at 0 100px) padding-box !important',
            $block->setProperty($declarations, '-webkit-clip-path', 'padding-box circle(50px at 0 100px)')
        );
        $t->same(
            '-webkit-clip-path: url(clip.svg#star); --Clip-Path: padding-box circle(50px at 0 100px); clip-path: polygon(50% 0%,100% 50%) !important',
            $block->setProperty($declarations, 'clip-path', 'polygon(nonzero, 50% 0%, 100% 50%)', true)
        );
        $t->same(
            '--Clip-Path: padding-box circle(50px at 0 100px); clip-path: circle(50px at 0 100px) padding-box !important',
            $block->removeProperty($declarations, '-webkit-clip-path')
        );
    },
    'declaration block canonicalizes upstream transform cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'transform: translateX(10px) scale3d(100%, 100%, 100%); translate: 0px 12px 0px; rotate: 10deg 0 0 -1; scale: 100% 105% 1; color: red';

        $t->same(
            [
                'transform' => 'translate(10px)scale(1)',
                'translate' => '0 12px',
                'rotate' => '-10deg',
                'scale' => '1 1.05',
                'color' => 'red',
            ],
            $block->parse($declarations)
        );
        $t->same(
            ['value' => 'translate(10px)scale(1)', 'important' => false],
            $block->getProperty($declarations, 'transform')
        );
        $t->same(['value' => '0 12px', 'important' => false], $block->getProperty($declarations, 'translate'));
        $t->same(['value' => '-10deg', 'important' => false], $block->getProperty($declarations, 'rotate'));
        $t->same(['value' => '1 1.05', 'important' => false], $block->getProperty($declarations, 'scale'));
        $t->same(
            'transform: translate(2px)scaleY(2); color: red',
            $block->setProperty('transform: rotateZ(20deg); color: red', 'transform', 'translate3d(2px, 0px, 0px) scale(100%, 200%)')
        );
        $t->same(
            'color: red; -ms-transform: translate(2px)rotate(20deg) !important',
            $block->setProperty('color: red; -ms-transform: translateX(1px)', '-ms-transform', 'translate3d(2px, 0px, 0px) rotateZ(20deg)', true)
        );
        $t->same(
            'color: red; rotate: x 20deg',
            $block->setProperty('color: red; rotate: 10deg', 'rotate', '1 0 0 20deg')
        );
        $t->same(
            'color: red; scale: 2',
            $block->setProperty('color: red; scale: 100% 200% 1', 'scale', '200% 200% 100%')
        );
        $t->same(
            'translate: 12px; rotate: 0deg; scale: 1.05; color: red',
            $block->removeProperty('transform: translate(10px); translate: 12px 0; rotate: 0; scale: 105%; color: red', 'transform')
        );
    },
    'declaration block canonicalizes upstream transform origin cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'transform-origin: LEFT top; -webkit-transform-origin: right bottom !important; -moz-transform-origin: center 0px; --Transform-Origin: LEFT top';

        $t->same(
            [
                'transform-origin' => '0 0',
                '-webkit-transform-origin' => '100% 100% !important',
                '-moz-transform-origin' => '50% 0',
                '--Transform-Origin' => 'LEFT top',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => '0 0', 'important' => false], $block->getProperty($declarations, 'transform-origin'));
        $t->same(
            ['value' => '100% 100%', 'important' => true],
            $block->getProperty($declarations, '-webkit-transform-origin')
        );
        $t->same(['value' => '50% 0', 'important' => false], $block->getProperty($declarations, '-moz-transform-origin'));
        $t->same(['value' => 'LEFT top', 'important' => false], $block->getProperty($declarations, '--Transform-Origin'));
        $t->same(
            'transform-origin: 50% 100%; -moz-transform-origin: 50% 0; --Transform-Origin: LEFT top; -webkit-transform-origin: 100% 100% !important',
            $block->setProperty($declarations, 'transform-origin', 'bottom')
        );
        $t->same(
            'transform-origin: 0 0; -moz-transform-origin: 50% 0; --Transform-Origin: LEFT top; -webkit-transform-origin: 0 50% !important',
            $block->setProperty($declarations, '-webkit-transform-origin', 'left', true)
        );
        $t->same(
            'transform-origin: 0 0; --Transform-Origin: LEFT top; -webkit-transform-origin: 100% 100% !important',
            $block->removeProperty($declarations, '-moz-transform-origin')
        );
    },
    'declaration block canonicalizes upstream perspective origin cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'perspective-origin: LEFT top; -webkit-perspective-origin: right bottom !important; -moz-perspective-origin: center 0px; --Perspective-Origin: LEFT top';

        $t->same(
            [
                'perspective-origin' => '0 0',
                '-webkit-perspective-origin' => '100% 100% !important',
                '-moz-perspective-origin' => '50% 0',
                '--Perspective-Origin' => 'LEFT top',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => '0 0', 'important' => false], $block->getProperty($declarations, 'perspective-origin'));
        $t->same(
            ['value' => '100% 100%', 'important' => true],
            $block->getProperty($declarations, '-webkit-perspective-origin')
        );
        $t->same(['value' => '50% 0', 'important' => false], $block->getProperty($declarations, '-moz-perspective-origin'));
        $t->same(['value' => 'LEFT top', 'important' => false], $block->getProperty($declarations, '--Perspective-Origin'));
        $t->same(
            'perspective-origin: 50% 100%; -moz-perspective-origin: 50% 0; --Perspective-Origin: LEFT top; -webkit-perspective-origin: 100% 100% !important',
            $block->setProperty($declarations, 'perspective-origin', 'bottom')
        );
        $t->same(
            'perspective-origin: 0 0; -moz-perspective-origin: 50% 0; --Perspective-Origin: LEFT top; -webkit-perspective-origin: 0 50% !important',
            $block->setProperty($declarations, '-webkit-perspective-origin', 'left', true)
        );
        $t->same(
            'perspective-origin: 0 0; --Perspective-Origin: LEFT top; -webkit-perspective-origin: 100% 100% !important',
            $block->removeProperty($declarations, '-moz-perspective-origin')
        );
    },
    'declaration block enumerates upstream cssom length and item order' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'color: red !important; background: white; --Block-Accent: blue; margin: 1rem !important; color: green';

        $t->same(5, $block->length($declarations));
        $t->same('background', $block->item($declarations, 0));
        $t->same('--Block-Accent', $block->item($declarations, 1));
        $t->same('color', $block->item($declarations, 2));
        $t->same('color', $block->item($declarations, 3));
        $t->same('margin', $block->item($declarations, 4));
        $t->same(null, $block->item($declarations, 5));
        $t->same(0, $block->length(''));
        $t->throws(InvalidArgumentException::class, static fn () => $block->item($declarations, -1));
    },
    'declaration block maps cssom declaration source ranges' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $backgroundValue = 'url("/theme/a;b.css") !important';
        $customProperty = '--wp--preset--color--contrast';
        $customValue = 'var(--wp--preset--color--contrast)';
        $source = "  color: red;\n  background: {$backgroundValue};\n  {$customProperty}: {$customValue};";
        $customKeyEnd = 3 + strlen($customProperty);
        $customValueStart = $customKeyEnd + 2;

        $t->same(
            [
                'key' => ['start' => ['line' => 1, 'column' => 3], 'end' => ['line' => 1, 'column' => 8]],
                'value' => ['start' => ['line' => 1, 'column' => 10], 'end' => ['line' => 1, 'column' => 13]],
            ],
            $block->propertyLocation($source, 0)
        );
        $t->same(
            [
                'key' => ['start' => ['line' => 2, 'column' => 3], 'end' => ['line' => 2, 'column' => 13]],
                'value' => ['start' => ['line' => 2, 'column' => 15], 'end' => ['line' => 2, 'column' => 15 + strlen($backgroundValue)]],
            ],
            $block->propertyLocation($source, 1)
        );
        $t->same(
            [
                'key' => ['start' => ['line' => 3, 'column' => 3], 'end' => ['line' => 3, 'column' => $customKeyEnd]],
                'value' => ['start' => ['line' => 3, 'column' => $customValueStart], 'end' => ['line' => 3, 'column' => $customValueStart + strlen($customValue)]],
            ],
            $block->propertyLocation($source, 2)
        );
        $t->same(null, $block->propertyLocation($source, 3));
        $t->throws(InvalidArgumentException::class, static fn () => $block->propertyLocation($source, -1));
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
    'declaration block parses upstream comment-separated important flags in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'red', 'important' => true],
            $block->getProperty('color: red !/* theme override */ important', 'color')
        );
        $t->same(
            ['value' => 'red', 'important' => true],
            $block->getProperty('color: red ! /* theme override */ IMPORTANT /* trailing */', 'color')
        );
        $t->same(
            ['value' => 'var(--wp--preset--color--accent)', 'important' => true],
            $block->getProperty(
                'color: var(--wp--preset--color--accent) /* keep */ ! /* core */ important; color: var(--wp--preset--color--contrast)',
                'color'
            )
        );
        $t->same(
            ['value' => 'red', 'important' => true],
            $block->getProperty('--Block-Accent: red !/* custom token */ important; --block-accent: blue', '--Block-Accent')
        );
        $t->same(
            'background: white; color: green',
            $block->setProperty('color: red !/* core */ important; background: white; color: blue', 'color', 'green')
        );
        $t->same(
            'background: white; color: green !important',
            $block->setProperty('color: red !/* core */ important; background: white; color: blue', 'color', 'green', true)
        );
        $t->same(
            'padding: 1rem; margin-right: 10px !important; margin-bottom: 10px !important; margin-left: 10px !important',
            $block->removeProperty('margin: 10px ! /* core */ important; padding: 1rem; margin-top: 12px', 'margin-top')
        );
        $t->same(
            ['value' => 'red !importantish', 'important' => false],
            $block->getProperty('color: red !importantish', 'color')
        );
    },
    'declaration block ignores css comments while tokenizing cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = '/* header ; : */ color /* key : */: red /* value ; : */ ! /* priority ; */ important; background: white; margin: 5px /* side ; */ 6px; --Block-Accent: var(--wp--preset--color--accent) /* custom ; : */';

        $t->same(['value' => 'red', 'important' => true], $block->getProperty($declarations, 'color'));
        $t->same(['value' => '5px 6px', 'important' => false], $block->getProperty($declarations, 'margin'));
        $t->same(['value' => '6px', 'important' => false], $block->getProperty($declarations, 'margin-left'));
        $t->same(
            ['value' => 'var(--wp--preset--color--accent)', 'important' => false],
            $block->getProperty($declarations, '--Block-Accent')
        );
        $t->same(
            'background: white; margin: 5px 6px; --Block-Accent: var(--wp--preset--color--accent); color: #00f',
            $block->setProperty($declarations, 'color', 'blue')
        );
        $t->same(
            'background: white; margin: 8px 6px 5px; --Block-Accent: var(--wp--preset--color--accent); color: red !important',
            $block->setProperty($declarations, 'margin-top', '8px /* write separator ; */')
        );
        $t->same(
            'background: white; margin-right: 6px; margin-bottom: 5px; margin-left: 6px; --Block-Accent: var(--wp--preset--color--accent); color: red !important',
            $block->removeProperty($declarations, 'margin-top')
        );
        $t->same(
            'background: white; margin: 5px 6px; --Block-Accent: var(--wp--preset--color--accent)',
            $block->removeProperty($declarations, 'color')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $block->getProperty('color: red /* unterminated', 'color')
        );
    },
    'declaration block preserves custom-property simple blocks while tokenizing cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = '--theme-rule: { color: red; background: url("/a;b.css") }; color: blue; --theme-list: [--a: 1; --b: 2] !important';

        $t->same(
            [
                '--theme-rule' => '{ color: red; background: url("/a;b.css") }',
                'color' => '#00f',
                '--theme-list' => '[--a: 1; --b: 2] !important',
            ],
            $block->parse($declarations)
        );
        $t->same(
            ['value' => '{ color: red; background: url("/a;b.css") }', 'important' => false],
            $block->getProperty($declarations, '--theme-rule')
        );
        $t->same(
            ['value' => '[--a: 1; --b: 2]', 'important' => true],
            $block->getProperty($declarations, '--theme-list')
        );
        $t->same(
            ['value' => '{ color: red !important; }', 'important' => false],
            $block->getProperty('--theme-rule: { color: red !important; }', '--theme-rule')
        );
        $t->same(
            '--theme-rule: { color: green; --nested: "a;b" }; color: #00f; --theme-list: [--a: 1; --b: 2] !important',
            $block->setProperty($declarations, '--theme-rule', '{ color: green; --nested: "a;b" }')
        );
        $t->same(
            'color: #00f; --theme-list: [--a: 1; --b: 2] !important',
            $block->removeProperty($declarations, '--theme-rule')
        );
        $t->same(
            [
                'key' => ['start' => ['line' => 4, 'column' => 3], 'end' => ['line' => 4, 'column' => 15]],
                'value' => ['start' => ['line' => 4, 'column' => 17], 'end' => ['line' => 4, 'column' => 60]],
            ],
            $block->propertyLocation($declarations, 0, 4, 3)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $block->parse('color: { color: red; }; background: blue')
        );
    },
    'declaration block rejects non custom top level blocks during cssom writes' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $block->setProperty('color: red', 'color', '{ color: blue; }')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $block->setProperty('background: red', 'background', 'url(hero.jpg) { color: blue; }')
        );
        $t->same(
            '--theme-rule: { color: blue; background: url("/a;b.css") }; color: red',
            $block->setProperty(
                '--theme-rule: { color: red; background: url("/a;b.css") }; color: red',
                '--theme-rule',
                '{ color: blue; background: url("/a;b.css") }'
            )
        );
        $t->same(
            'color: var(--theme-rule, { color: blue; })',
            $block->setProperty('color: red', 'color', 'var(--theme-rule, { color: blue; })')
        );
    },
    'declaration block reads upstream background cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'red', 'important' => false], $block->getProperty('background: red', 'background'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty('background: red', 'background-color'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty('background: red', 'background-image'));
        $t->same(['value' => '0 0', 'important' => false], $block->getProperty('background: red', 'background-position'));
        $t->same(['value' => 'repeat', 'important' => false], $block->getProperty('background: red', 'background-repeat'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('background: red', 'background-size'));
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
    'declaration block reads upstream background attachment origin and clip cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $background = 'background: url(hero.jpg) fixed border-box content-box, linear-gradient(red, green) local padding-box';

        $t->same(['value' => 'fixed, local', 'important' => false], $block->getProperty($background, 'background-attachment'));
        $t->same(['value' => 'border-box, padding-box', 'important' => false], $block->getProperty($background, 'background-origin'));
        $t->same(['value' => 'content-box, padding-box', 'important' => false], $block->getProperty($background, 'background-clip'));
        $t->same(['value' => 'scroll', 'important' => false], $block->getProperty('background: url(hero.jpg)', 'background-attachment'));
        $t->same(['value' => 'padding-box', 'important' => false], $block->getProperty('background: url(hero.jpg)', 'background-origin'));
        $t->same(['value' => 'border-box', 'important' => false], $block->getProperty('background: url(hero.jpg)', 'background-clip'));
        $t->same(['value' => 'url(hero.jpg) text', 'important' => false], $block->getProperty('background: url(hero.jpg) text', 'background'));
        $t->same(['value' => 'text', 'important' => false], $block->getProperty('background: url(hero.jpg) text', 'background-clip'));
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
    'declaration block reads upstream mask border cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $mask = 'mask-border: url("frame.svg") 10 40 10 40 / 12px / 2 round round luminance';

        $t->same(['value' => 'url(frame.svg)', 'important' => false], $block->getProperty($mask, 'mask-border-source'));
        $t->same(['value' => '10 40', 'important' => false], $block->getProperty($mask, 'mask-border-slice'));
        $t->same(['value' => '12px', 'important' => false], $block->getProperty($mask, 'mask-border-width'));
        $t->same(['value' => '2', 'important' => false], $block->getProperty($mask, 'mask-border-outset'));
        $t->same(['value' => 'round', 'important' => false], $block->getProperty($mask, 'mask-border-repeat'));
        $t->same(['value' => 'luminance', 'important' => false], $block->getProperty($mask, 'mask-border-mode'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty('mask-border: 25', 'mask-border-source'));
        $t->same(
            ['value' => 'url(frame.svg) 10 40 / 12px / 2 round luminance', 'important' => false],
            $block->getProperty(
                'mask-border-source: url("frame.svg"); mask-border-slice: 10 40 10 40; mask-border-width: 12px; mask-border-outset: 2; mask-border-repeat: round round; mask-border-mode: luminance',
                'mask-border'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'mask-border-source: url(frame.svg); mask-border-slice: 10; mask-border-width: 12px !important; mask-border-outset: 2; mask-border-repeat: round; mask-border-mode: luminance',
                'mask-border'
            )
        );
    },
    'declaration block reads writes removes upstream webkit mask box image cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $webkitMaskBox = '-webkit-mask-box-image: url("frame.svg") 10 40 10 40 / 12px / 2 round round !important';

        $t->same(['value' => 'url(frame.svg)', 'important' => true], $block->getProperty($webkitMaskBox, '-webkit-mask-box-image-source'));
        $t->same(['value' => '10 40', 'important' => true], $block->getProperty($webkitMaskBox, '-webkit-mask-box-image-slice'));
        $t->same(['value' => '12px', 'important' => true], $block->getProperty($webkitMaskBox, '-webkit-mask-box-image-width'));
        $t->same(['value' => '2', 'important' => true], $block->getProperty($webkitMaskBox, '-webkit-mask-box-image-outset'));
        $t->same(['value' => 'round', 'important' => true], $block->getProperty($webkitMaskBox, '-webkit-mask-box-image-repeat'));
        $t->same(['value' => 'url(frame.svg) 10 40 / 12px / 2 round', 'important' => true], $block->getProperty($webkitMaskBox, '-webkit-mask-box-image'));
        $t->same(null, $block->getProperty($webkitMaskBox, 'mask-border-source'));
        $t->same(
            ['value' => 'url(frame.svg) 10 40 / 12px / 2 round', 'important' => false],
            $block->getProperty(
                '-webkit-mask-box-image-source: url("frame.svg"); -webkit-mask-box-image-slice: 10 40 10 40; -webkit-mask-box-image-width: 12px; -webkit-mask-box-image-outset: 2; -webkit-mask-box-image-repeat: round round',
                '-webkit-mask-box-image'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                '-webkit-mask-box-image-source: url(frame.svg); -webkit-mask-box-image-slice: 10; -webkit-mask-box-image-width: 12px !important; -webkit-mask-box-image-outset: 2; -webkit-mask-box-image-repeat: round',
                '-webkit-mask-box-image'
            )
        );
        $t->same(
            '-webkit-mask-box-image: url(new-frame.svg) 25 / 12px round',
            $block->setProperty('-webkit-mask-box-image: url(frame.svg) 25 / 12px round', '-webkit-mask-box-image-source', 'url("new-frame.svg")')
        );
        $t->same(
            '-webkit-mask-box-image: url(frame.svg) 25 / 12px space round',
            $block->setProperty('-webkit-mask-box-image: url(frame.svg) 25 / 12px round', '-webkit-mask-box-image-repeat', 'space round')
        );
        $t->same(
            '-webkit-mask-box-image: url(frame.svg) 25 / 12px round; mask-border-source: url(new-frame.svg)',
            $block->setProperty('-webkit-mask-box-image: url(frame.svg) 25 / 12px round', 'mask-border-source', 'url(new-frame.svg)')
        );
        $t->same(
            '-webkit-mask-box-image-slice: 25; -webkit-mask-box-image-width: 12px; -webkit-mask-box-image-outset: 0; -webkit-mask-box-image-repeat: round',
            $block->removeProperty('-webkit-mask-box-image: url(frame.svg) 25 / 12px round', '-webkit-mask-box-image-source')
        );
        $t->same(
            'color: red',
            $block->removeProperty('-webkit-mask-box-image: url(frame.svg) 25 / 12px round; -webkit-mask-box-image-repeat: space; color: red', '-webkit-mask-box-image')
        );
        $t->same(
            'color: red; -webkit-mask-box-image-slice: 25 !important; -webkit-mask-box-image-width: 12px !important; -webkit-mask-box-image-outset: 0 !important; -webkit-mask-box-image-repeat: round !important',
            $block->removeProperty('-webkit-mask-box-image: url(frame.svg) 25 / 12px round !important; color: red; -webkit-mask-box-image-source: url(other.svg)', '-webkit-mask-box-image-source')
        );
    },
    'declaration block reads upstream mask cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $mask = 'mask: url("mask.svg") 25% 75% / cover no-repeat content-box padding-box subtract luminance';

        $t->same(['value' => 'url(mask.svg)', 'important' => false], $block->getProperty($mask, 'mask-image'));
        $t->same(['value' => '25% 75%', 'important' => false], $block->getProperty($mask, 'mask-position'));
        $t->same(['value' => '25%', 'important' => false], $block->getProperty($mask, 'mask-position-x'));
        $t->same(['value' => '75%', 'important' => false], $block->getProperty($mask, 'mask-position-y'));
        $t->same(['value' => 'cover', 'important' => false], $block->getProperty($mask, 'mask-size'));
        $t->same(['value' => 'no-repeat', 'important' => false], $block->getProperty($mask, 'mask-repeat'));
        $t->same(['value' => 'content-box', 'important' => false], $block->getProperty($mask, 'mask-origin'));
        $t->same(['value' => 'padding-box', 'important' => false], $block->getProperty($mask, 'mask-clip'));
        $t->same(['value' => 'subtract', 'important' => false], $block->getProperty($mask, 'mask-composite'));
        $t->same(['value' => 'luminance', 'important' => false], $block->getProperty($mask, 'mask-mode'));
        $t->same(
            ['value' => 'url(mask.svg) 25% 75% / cover no-repeat content-box padding-box subtract luminance', 'important' => false],
            $block->getProperty($mask, 'mask')
        );
        $t->same(
            ['value' => 'url(/wp-content/themes/acme/assets/fade.svg)', 'important' => false],
            $block->getProperty('mask: url("/wp-content/themes/acme/assets/fade.svg") 50% 50% / cover no-repeat content-box padding-box luminance', 'mask-image')
        );
        $t->same(['value' => '0 0', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-position'));
        $t->same(['value' => '0', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-position-x'));
        $t->same(['value' => '0', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-position-y'));
        $t->same(['value' => 'auto', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-size'));
        $t->same(['value' => 'repeat', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-repeat'));
        $t->same(['value' => 'border-box', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-origin'));
        $t->same(['value' => 'border-box', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-clip'));
        $t->same(['value' => 'add', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-composite'));
        $t->same(['value' => 'match-source', 'important' => true], $block->getProperty('mask: url(mask.svg) !important', 'mask-mode'));
        $t->same(
            ['value' => 'url(mask.svg) 50% 50% / cover no-repeat content-box padding-box subtract luminance', 'important' => false],
            $block->getProperty(
                'mask-image: url("mask.svg"); mask-position: 50% 50%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: subtract; mask-mode: luminance',
                'mask'
            )
        );
        $t->same(
            ['value' => 'left 10px bottom 20px', 'important' => false],
            $block->getProperty('mask-position-x: left 10px; mask-position-y: bottom 20px', 'mask-position')
        );
        $t->same(
            null,
            $block->getProperty('mask-position-x: left; mask-position-y: bottom !important', 'mask-position')
        );
        $webkitMask = '-webkit-mask: url("mask.svg") 10px 20px / contain no-repeat content-box padding-box';
        $t->same(['value' => 'url(mask.svg)', 'important' => false], $block->getProperty($webkitMask, '-webkit-mask-image'));
        $t->same(['value' => '10px 20px', 'important' => false], $block->getProperty($webkitMask, '-webkit-mask-position'));
        $t->same(['value' => 'contain', 'important' => false], $block->getProperty($webkitMask, '-webkit-mask-size'));
        $t->same(['value' => 'no-repeat', 'important' => false], $block->getProperty($webkitMask, '-webkit-mask-repeat'));
        $t->same(['value' => 'content-box', 'important' => false], $block->getProperty($webkitMask, '-webkit-mask-origin'));
        $t->same(['value' => 'padding-box', 'important' => false], $block->getProperty($webkitMask, '-webkit-mask-clip'));
        $t->same(
            ['value' => 'url(mask.svg) 10px 20px / contain no-repeat content-box padding-box', 'important' => false],
            $block->getProperty($webkitMask, '-webkit-mask')
        );
        $t->same(null, $block->getProperty($webkitMask, 'mask-image'));
        $t->same(
            ['value' => 'url(mask.svg) 10px 20px / contain no-repeat content-box padding-box', 'important' => true],
            $block->getProperty(
                '-webkit-mask-image: url(mask.svg) !important; -webkit-mask-position: 10px 20px !important; -webkit-mask-size: contain !important; -webkit-mask-repeat: no-repeat !important; -webkit-mask-origin: content-box !important; -webkit-mask-clip: padding-box !important',
                '-webkit-mask'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                '-webkit-mask-image: url(mask.svg); -webkit-mask-position: 10px 20px; -webkit-mask-size: contain !important; -webkit-mask-repeat: no-repeat; -webkit-mask-origin: content-box; -webkit-mask-clip: padding-box',
                '-webkit-mask'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'mask-image: url(mask.svg); mask-position: 50% 50%; mask-size: cover !important; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: subtract; mask-mode: luminance',
                'mask'
            )
        );
    },
    'declaration block canonicalizes upstream standalone mask compositing cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = '-webkit-mask-composite: SOURCE-OUT, Xor; -webkit-mask-source-type: LUMINANCE, Auto; mask-composite: SUBTRACT, Exclude; mask-mode: LUMINANCE, Match-Source; --Mask-Composite: SOURCE-OUT';

        $t->same(
            [
                '-webkit-mask-composite' => 'source-out, xor',
                '-webkit-mask-source-type' => 'luminance, auto',
                'mask-composite' => 'subtract, exclude',
                'mask-mode' => 'luminance, match-source',
                '--Mask-Composite' => 'SOURCE-OUT',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'source-out, xor', 'important' => false], $block->getProperty($declarations, '-webkit-mask-composite'));
        $t->same(['value' => 'luminance, auto', 'important' => false], $block->getProperty($declarations, '-webkit-mask-source-type'));
        $t->same(['value' => 'subtract, exclude', 'important' => false], $block->getProperty($declarations, 'mask-composite'));
        $t->same(['value' => 'luminance, match-source', 'important' => false], $block->getProperty($declarations, 'mask-mode'));
        $t->same(['value' => 'SOURCE-OUT', 'important' => false], $block->getProperty($declarations, '--Mask-Composite'));
        $t->same(
            '-webkit-mask-source-type: luminance, auto; mask-composite: subtract, exclude; mask-mode: luminance, match-source; --Mask-Composite: SOURCE-OUT; -webkit-mask-composite: source-in, destination-out !important',
            $block->setProperty($declarations, '-webkit-mask-composite', 'Source-In, Destination-Out', true)
        );
        $t->same(
            '-webkit-mask-composite: source-out, xor; -webkit-mask-source-type: alpha; mask-composite: subtract, exclude; mask-mode: luminance, match-source; --Mask-Composite: SOURCE-OUT',
            $block->setProperty($declarations, '-webkit-mask-source-type', 'Alpha')
        );
        $t->same(
            '-webkit-mask-composite: source-out, xor; -webkit-mask-source-type: luminance, auto; mask-composite: subtract, exclude; mask-mode: alpha, match-source; --Mask-Composite: SOURCE-OUT',
            $block->setProperty($declarations, 'mask-mode', 'Alpha, Match-Source')
        );
        $t->same(
            '-webkit-mask-composite: source-out, xor; mask-composite: subtract, exclude; mask-mode: luminance, match-source; --Mask-Composite: SOURCE-OUT',
            $block->removeProperty($declarations, '-webkit-mask-source-type')
        );
        $t->same(
            '-webkit-mask-composite: source-out, xor; -webkit-mask-source-type: luminance, auto; mask-mode: luminance, match-source; --Mask-Composite: SOURCE-OUT',
            $block->removeProperty($declarations, 'mask-composite')
        );
    },
    'declaration block canonicalizes upstream mask type cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'mask-type: LUMINANCE; color: red; --Mask-Type: LUMINANCE';

        $t->same(
            [
                'mask-type' => 'luminance',
                'color' => 'red',
                '--Mask-Type' => 'LUMINANCE',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'luminance', 'important' => false], $block->getProperty($declarations, 'mask-type'));
        $t->same(['value' => 'alpha', 'important' => false], $block->getProperty('mask-type: ALPHA', 'mask-type'));
        $t->same(['value' => 'LUMINANCE', 'important' => false], $block->getProperty($declarations, '--Mask-Type'));
        $t->same(
            'color: red; --Mask-Type: LUMINANCE; mask-type: alpha !important',
            $block->setProperty($declarations, 'mask-type', 'Alpha', true)
        );
        $t->same(
            'color: red; --Mask-Type: LUMINANCE',
            $block->removeProperty($declarations, 'mask-type')
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
    'declaration block reads upstream logical border cssom shorthands and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $blockBorder = 'border-block: 2px solid var(--wp--preset--color--contrast)';

        $t->same(
            ['value' => 'var(--wp--preset--color--contrast)', 'important' => false],
            $block->getProperty($blockBorder, 'border-block-start-color')
        );
        $t->same(['value' => '2px', 'important' => false], $block->getProperty($blockBorder, 'border-block-end-width'));
        $t->same(['value' => 'solid', 'important' => false], $block->getProperty($blockBorder, 'border-block-start-style'));
        $t->same(
            ['value' => '2px solid var(--wp--preset--color--contrast)', 'important' => false],
            $block->getProperty($blockBorder, 'border-block')
        );
        $t->same(
            ['value' => '2px 4px', 'important' => false],
            $block->getProperty('border-block-start-width: 2px; border-block-end-width: 4px', 'border-block-width')
        );
        $t->same(
            ['value' => 'red green', 'important' => false],
            $block->getProperty('border-inline-start-color: red; border-inline-end-color: green', 'border-inline-color')
        );
        $t->same(
            ['value' => '1px dashed #00f', 'important' => true],
            $block->getProperty(
                'border-inline-start-width: 1px !important; border-inline-end-width: 1px !important; border-inline-start-style: dashed !important; border-inline-end-style: dashed !important; border-inline-start-color: blue !important; border-inline-end-color: blue !important',
                'border-inline'
            )
        );
        $t->same(
            null,
            $block->getProperty('border-block-start-width: 1px !important; border-block-end-width: 1px', 'border-block-width')
        );
        $t->same(null, $block->getProperty('border: 1px solid red', 'border-block-start-color'));
    },
    'declaration block reads upstream border image cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $borderImage = 'border-image: url("frame.svg") 10 40 10 40 fill / 12px / 2 round round';

        $t->same(['value' => 'url(frame.svg)', 'important' => false], $block->getProperty($borderImage, 'border-image-source'));
        $t->same(['value' => '10 40 fill', 'important' => false], $block->getProperty($borderImage, 'border-image-slice'));
        $t->same(['value' => '12px', 'important' => false], $block->getProperty($borderImage, 'border-image-width'));
        $t->same(['value' => '2', 'important' => false], $block->getProperty($borderImage, 'border-image-outset'));
        $t->same(['value' => 'round', 'important' => false], $block->getProperty($borderImage, 'border-image-repeat'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty('border-image: 25', 'border-image-source'));
        $t->same(
            ['value' => 'url(frame.svg) 10 40 fill / 10px round', 'important' => false],
            $block->getProperty(
                'border-image-source: url("frame.svg"); border-image-slice: 10 40 10 40 fill; border-image-width: 10px; border-image-outset: 0; border-image-repeat: round round',
                'border-image'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'border-image-source: url(frame.svg); border-image-slice: 10; border-image-width: 12px !important; border-image-outset: 2; border-image-repeat: round',
                'border-image'
            )
        );

        $legacyBorderImage = '-webkit-border-image: url("frame.svg") 25 / 12px round !important';
        $t->same(['value' => 'url(frame.svg)', 'important' => true], $block->getProperty($legacyBorderImage, 'border-image-source'));
        $t->same(['value' => '25', 'important' => true], $block->getProperty($legacyBorderImage, 'border-image-slice'));
        $t->same(['value' => '12px', 'important' => true], $block->getProperty($legacyBorderImage, 'border-image-width'));
        $t->same(['value' => '0', 'important' => true], $block->getProperty($legacyBorderImage, 'border-image-outset'));
        $t->same(['value' => 'round', 'important' => true], $block->getProperty($legacyBorderImage, 'border-image-repeat'));
        $t->same(['value' => 'url(frame.svg) 25 / 12px round', 'important' => true], $block->getProperty($legacyBorderImage, '-webkit-border-image'));
        $t->same(null, $block->getProperty($legacyBorderImage, 'border-image'));
        $t->same(null, $block->getProperty('border-image: url(frame.svg) 25 / 12px round', '-webkit-border-image'));
        $t->same(
            ['value' => 'url(frame.svg) 25 / 12px round', 'important' => false],
            $block->getProperty(
                'border-image-source: url(frame.svg); border-image-slice: 25; border-image-width: 12px; border-image-outset: 0; border-image-repeat: round',
                '-moz-border-image'
            )
        );
    },
    'declaration block reads upstream border radius cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $radius = 'border-radius: 10px 20px 30px 40px / 1px 2px 3px 4px';

        $t->same(['value' => '10px 1px', 'important' => false], $block->getProperty($radius, 'border-top-left-radius'));
        $t->same(['value' => '20px 2px', 'important' => false], $block->getProperty($radius, 'border-top-right-radius'));
        $t->same(['value' => '30px 3px', 'important' => false], $block->getProperty($radius, 'border-bottom-right-radius'));
        $t->same(['value' => '40px 4px', 'important' => false], $block->getProperty($radius, 'border-bottom-left-radius'));
        $t->same(['value' => '10px 20px 30px 40px / 1px 2px 3px 4px', 'important' => false], $block->getProperty($radius, 'border-radius'));
        $t->same(
            ['value' => '10px 20px / 1px 2px', 'important' => false],
            $block->getProperty(
                'border-top-left-radius: 10px 1px; border-top-right-radius: 20px 2px; border-bottom-right-radius: 10px 1px; border-bottom-left-radius: 20px 2px',
                'border-radius'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'border-top-left-radius: 10px; border-top-right-radius: 10px !important; border-bottom-right-radius: 10px; border-bottom-left-radius: 10px',
                'border-radius'
            )
        );
        $t->same(['value' => '8px', 'important' => true], $block->getProperty('-webkit-border-radius: 4px 8px !important', '-webkit-border-top-right-radius'));
        $t->same(null, $block->getProperty('-webkit-border-radius: 4px 8px', 'border-top-right-radius'));
    },
    'declaration block reads upstream outline cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => '2px', 'important' => false], $block->getProperty('outline: solid 2px red', 'outline-width'));
        $t->same(['value' => 'solid', 'important' => false], $block->getProperty('outline: solid 2px red', 'outline-style'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty('outline: solid 2px red', 'outline-color'));
        $t->same(['value' => '2px solid red', 'important' => false], $block->getProperty('outline: solid 2px red', 'outline'));
        $t->same(
            ['value' => 'auto var(--wp--preset--color--accent)', 'important' => true],
            $block->getProperty('outline: auto var(--wp--preset--color--accent) !important', 'outline')
        );
        $t->same(
            ['value' => '3px dashed', 'important' => false],
            $block->getProperty('outline-width: 3px; outline-style: dashed; outline-color: currentColor', 'outline')
        );
        $t->same(
            null,
            $block->getProperty('outline-width: 3px; outline-style: dashed !important; outline-color: red', 'outline')
        );
    },
    'declaration block reads upstream grid area cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => 'content', 'important' => false],
            $block->getProperty('grid-area: content', 'grid-area')
        );
        $t->same(
            ['value' => 'content', 'important' => false],
            $block->getProperty('grid-area: content', 'grid-row')
        );
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
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('grid-template: auto / 1fr', 'grid-template-rows'));
        $t->same(['value' => '1fr', 'important' => false], $block->getProperty('grid-template: auto / 1fr', 'grid-template-columns'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty('grid-template: auto / 1fr', 'grid-template-areas'));
        $t->same(
            ['value' => 'minmax(0, 1fr) 18rem', 'important' => true],
            $block->getProperty('grid-template: auto / minmax(0, 1fr) 18rem !important', 'grid-template-columns')
        );
        $t->same(['value' => 'none', 'important' => false], $block->getProperty('grid-template: none', 'grid-template'));
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
    'declaration block reads upstream grid auto flow cssom shorthand components' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $rowGrid = 'grid: auto-flow dense minmax(10px, 1fr) / [content-start] 1fr [content-end]';
        $columnGrid = 'grid: [sidebar-start] 12rem [sidebar-end] / dense auto-flow 8rem';

        $t->same(['value' => 'auto-flow dense minmax(10px, 1fr) / [content-start] 1fr [content-end]', 'important' => false], $block->getProperty($rowGrid, 'grid'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($rowGrid, 'grid-template-rows'));
        $t->same(['value' => '[content-start] 1fr [content-end]', 'important' => false], $block->getProperty($rowGrid, 'grid-template-columns'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($rowGrid, 'grid-template-areas'));
        $t->same(['value' => 'row dense', 'important' => false], $block->getProperty($rowGrid, 'grid-auto-flow'));
        $t->same(['value' => 'minmax(10px, 1fr)', 'important' => false], $block->getProperty($rowGrid, 'grid-auto-rows'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty($rowGrid, 'grid-auto-columns'));

        $t->same(['value' => '[sidebar-start] 12rem [sidebar-end] / auto-flow dense 8rem', 'important' => false], $block->getProperty($columnGrid, 'grid'));
        $t->same(['value' => '[sidebar-start] 12rem [sidebar-end]', 'important' => false], $block->getProperty($columnGrid, 'grid-template-rows'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($columnGrid, 'grid-template-columns'));
        $t->same(['value' => 'column dense', 'important' => false], $block->getProperty($columnGrid, 'grid-auto-flow'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty($columnGrid, 'grid-auto-rows'));
        $t->same(['value' => '8rem', 'important' => false], $block->getProperty($columnGrid, 'grid-auto-columns'));
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
        $t->same(
            ['value' => 'row wrap', 'important' => false],
            $block->getProperty('-ms-flex-direction: row; -ms-flex-wrap: wrap', '-ms-flex-flow')
        );
        $t->same(null, $block->getProperty('flex-direction: row; flex-wrap: wrap', '-webkit-flex-flow'));
        $t->same(null, $block->getProperty('-ms-flex-direction: row; flex-wrap: wrap', '-ms-flex-flow'));
        $t->same(null, $block->getProperty('-webkit-flex-direction: row; flex-wrap: wrap', '-webkit-flex-flow'));
        $t->same(null, $block->getProperty('-webkit-flex-direction: row; flex-wrap: wrap', 'flex-flow'));
        $t->same(
            ['value' => 'row', 'important' => false],
            $block->getProperty('-webkit-flex-flow: row', '-webkit-flex-direction')
        );
        $t->same(
            ['value' => 'row', 'important' => false],
            $block->getProperty('-ms-flex-flow: row', '-ms-flex-direction')
        );
        $t->same(null, $block->getProperty('-webkit-flex-flow: row', 'flex-direction'));
        $t->same(null, $block->getProperty('-ms-flex-flow: row', '-webkit-flex-direction'));
    },
    'declaration block reads upstream flex cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'none', 'important' => false], $block->getProperty('flex: none', 'flex'));
        $t->same(['value' => '0', 'important' => false], $block->getProperty('flex: none', 'flex-grow'));
        $t->same(['value' => '0', 'important' => false], $block->getProperty('flex: none', 'flex-shrink'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('flex: none', 'flex-basis'));
        $t->same(['value' => 'auto', 'important' => true], $block->getProperty('flex: auto !important', 'flex'));
        $t->same(
            ['value' => '1', 'important' => false],
            $block->getProperty('flex-grow: 1; flex-shrink: 1; flex-basis: 0%', 'flex')
        );
        $t->same(
            ['value' => '1 1 0', 'important' => false],
            $block->getProperty('flex-grow: 1; flex-shrink: 1; flex-basis: 0px', 'flex')
        );
        $t->same(
            ['value' => '1 0 auto', 'important' => false],
            $block->getProperty('flex-grow: 1; flex-shrink: 0; flex-basis: auto', 'flex')
        );
        $t->same(
            ['value' => '2 10px', 'important' => false],
            $block->getProperty('flex-grow: 2; flex-shrink: 1; flex-basis: 10px', 'flex')
        );
        $t->same(
            null,
            $block->getProperty('flex-grow: 1; flex-shrink: 1 !important; flex-basis: 0%', 'flex')
        );
        $t->same(['value' => '10px', 'important' => false], $block->getProperty('-webkit-flex: 2 10px', '-webkit-flex-basis'));
        $t->same(null, $block->getProperty('-webkit-flex: 2 10px', 'flex-basis'));
    },
    'declaration block canonicalizes upstream direct flex cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'flex-flow: Row Wrap; flex: +1.00 .500 0PX; flex-grow: +2.00; flex-shrink: .2500; flex-basis: 0PX; order: +001; -webkit-flex-flow: Column NoWrap; -webkit-flex: +2.0 1.00 10PX; -webkit-order: -0003; --Flex-Flow: Row Wrap';

        $t->same(
            [
                'flex-flow' => 'row wrap',
                'flex' => '1 .5 0',
                'flex-grow' => '2',
                'flex-shrink' => '.25',
                'flex-basis' => '0',
                'order' => '1',
                '-webkit-flex-flow' => 'column nowrap',
                '-webkit-flex' => '2 10px',
                '-webkit-order' => '-3',
                '--Flex-Flow' => 'Row Wrap',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'row wrap', 'important' => false], $block->getProperty($declarations, 'flex-flow'));
        $t->same(['value' => '2 .25 0', 'important' => false], $block->getProperty($declarations, 'flex'));
        $t->same(['value' => '2', 'important' => false], $block->getProperty($declarations, 'flex-grow'));
        $t->same(['value' => '0', 'important' => false], $block->getProperty($declarations, 'flex-basis'));
        $t->same(['value' => '1', 'important' => false], $block->getProperty($declarations, 'order'));
        $t->same(['value' => 'column nowrap', 'important' => false], $block->getProperty($declarations, '-webkit-flex-flow'));
        $t->same(['value' => '2 10px', 'important' => false], $block->getProperty($declarations, '-webkit-flex'));
        $t->same(['value' => 'Row Wrap', 'important' => false], $block->getProperty($declarations, '--Flex-Flow'));
        $t->same(
            'flex-flow: column wrap; color: red',
            $block->setProperty('flex-flow: Row Wrap; color: red', 'flex-direction', 'Column')
        );
        $t->same(
            'flex: 2 10px; color: red',
            $block->setProperty('flex: +1.00 .500 0PX; color: red', 'flex', '+2.00 1.00 10PX')
        );
        $t->same(
            'flex-grow: .5; color: red',
            $block->setProperty('flex-grow: +1.00; color: red', 'flex-grow', '.500')
        );
        $t->same(
            'color: red; order: -5 !important',
            $block->setProperty('order: +001; color: red', 'order', '-0005', true)
        );
        $t->same(
            'flex-flow: row wrap; flex: 1 .5 0; flex-grow: 2; flex-shrink: .25; flex-basis: 0; -webkit-flex-flow: column nowrap; -webkit-flex: 2 10px; -webkit-order: -3; --Flex-Flow: Row Wrap',
            $block->removeProperty($declarations, 'order')
        );
    },
    'declaration block canonicalizes upstream legacy flex cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $legacy = '-webkit-box-orient: Vertical; -moz-box-direction: Reverse; -webkit-box-align: Stretch; -moz-box-pack: Justify; -webkit-box-lines: Multiple; -webkit-box-flex: .500; -moz-box-flex: +1.00; -webkit-box-ordinal-group: +003; -webkit-box-flex-group: +004; -ms-flex-pack: Distribute; -ms-flex-align: Baseline; -ms-flex-item-align: Auto; -ms-flex-line-pack: Stretch; -ms-flex-positive: +2.00; -ms-flex-negative: .2500; -ms-flex-preferred-size: 0PX; -ms-flex-order: -0002; --Legacy-Flex: Vertical';

        $t->same(
            [
                '-webkit-box-orient' => 'vertical',
                '-moz-box-direction' => 'reverse',
                '-webkit-box-align' => 'stretch',
                '-moz-box-pack' => 'justify',
                '-webkit-box-lines' => 'multiple',
                '-webkit-box-flex' => '.5',
                '-moz-box-flex' => '1',
                '-webkit-box-ordinal-group' => '3',
                '-webkit-box-flex-group' => '4',
                '-ms-flex-pack' => 'distribute',
                '-ms-flex-align' => 'baseline',
                '-ms-flex-item-align' => 'auto',
                '-ms-flex-line-pack' => 'stretch',
                '-ms-flex-positive' => '2',
                '-ms-flex-negative' => '.25',
                '-ms-flex-preferred-size' => '0',
                '-ms-flex-order' => '-2',
                '--Legacy-Flex' => 'Vertical',
            ],
            $block->parse($legacy)
        );
        $t->same(['value' => 'vertical', 'important' => false], $block->getProperty($legacy, '-webkit-box-orient'));
        $t->same(['value' => 'stretch', 'important' => false], $block->getProperty($legacy, '-webkit-box-align'));
        $t->same(['value' => '.5', 'important' => false], $block->getProperty($legacy, '-webkit-box-flex'));
        $t->same(['value' => '3', 'important' => false], $block->getProperty($legacy, '-webkit-box-ordinal-group'));
        $t->same(['value' => '4', 'important' => false], $block->getProperty($legacy, '-webkit-box-flex-group'));
        $t->same(['value' => 'distribute', 'important' => false], $block->getProperty($legacy, '-ms-flex-pack'));
        $t->same(['value' => '0', 'important' => false], $block->getProperty($legacy, '-ms-flex-preferred-size'));
        $t->same(['value' => 'Vertical', 'important' => false], $block->getProperty($legacy, '--Legacy-Flex'));
        $t->same(
            '-webkit-box-orient: horizontal; color: red',
            $block->setProperty('-webkit-box-orient: Vertical; color: red', '-webkit-box-orient', 'Horizontal')
        );
        $t->same(
            '-ms-flex-preferred-size: 12px; color: red',
            $block->setProperty('-ms-flex-preferred-size: 0PX; color: red', '-ms-flex-preferred-size', '12PX')
        );
        $t->same(
            '-webkit-box-flex-group: 6; color: red',
            $block->setProperty('-webkit-box-flex-group: +004; color: red', '-webkit-box-flex-group', '+006')
        );
        $t->same(
            'color: red; -ms-flex-order: 4 !important',
            $block->setProperty('-ms-flex-order: -0002; color: red', '-ms-flex-order', '+004', true)
        );
        $t->same(
            '-webkit-box-flex: .5; color: red',
            $block->removeProperty('-webkit-box-flex: .500; -webkit-box-flex-group: +004; color: red', '-webkit-box-flex-group')
        );
        $t->same(
            '-webkit-box-orient: vertical; -moz-box-direction: reverse; -webkit-box-align: stretch; -webkit-box-lines: multiple; -webkit-box-flex: .5; -moz-box-flex: 1; -webkit-box-ordinal-group: 3; -webkit-box-flex-group: 4; -ms-flex-pack: distribute; -ms-flex-align: baseline; -ms-flex-item-align: auto; -ms-flex-line-pack: stretch; -ms-flex-positive: 2; -ms-flex-negative: .25; -ms-flex-preferred-size: 0; -ms-flex-order: -2; --Legacy-Flex: Vertical',
            $block->removeProperty($legacy, '-moz-box-pack')
        );
    },
    'declaration block reads upstream place alignment cssom shorthands and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'center space-between', 'important' => false], $block->getProperty('place-content: center space-between', 'place-content'));
        $t->same(['value' => 'center', 'important' => false], $block->getProperty('place-content: center space-between', 'align-content'));
        $t->same(['value' => 'space-between', 'important' => false], $block->getProperty('place-content: center space-between', 'justify-content'));
        $t->same(['value' => 'baseline start', 'important' => false], $block->getProperty('place-content: first baseline', 'place-content'));
        $t->same(['value' => 'baseline', 'important' => false], $block->getProperty('place-content: first baseline', 'align-content'));
        $t->same(['value' => 'start', 'important' => false], $block->getProperty('place-content: first baseline', 'justify-content'));
        $t->same(
            ['value' => 'safe center unsafe right', 'important' => true],
            $block->getProperty('align-content: safe center !important; justify-content: unsafe right !important', 'place-content')
        );
        $t->same(null, $block->getProperty('align-content: center !important; justify-content: end', 'place-content'));
        $t->same(['value' => 'auto end', 'important' => false], $block->getProperty('place-self: auto end', 'place-self'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('place-self: auto end', 'align-self'));
        $t->same(['value' => 'end', 'important' => false], $block->getProperty('place-self: auto end', 'justify-self'));
        $t->same(['value' => 'normal', 'important' => false], $block->getProperty('place-items: normal stretch', 'place-items'));
        $t->same(['value' => 'normal', 'important' => false], $block->getProperty('place-items: normal stretch', 'align-items'));
        $t->same(['value' => 'stretch', 'important' => false], $block->getProperty('place-items: normal stretch', 'justify-items'));
        $t->same(['value' => 'stretch stretch', 'important' => false], $block->getProperty('place-items: stretch', 'place-items'));
        $t->same(['value' => 'stretch stretch', 'important' => false], $block->getProperty('place-self: stretch', 'place-self'));
        $t->same(
            ['value' => 'last baseline legacy left', 'important' => false],
            $block->getProperty('align-items: last baseline; justify-items: left legacy', 'place-items')
        );
    },
    'declaration block reads upstream gap cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => '1rem 2rem', 'important' => false], $block->getProperty('gap: 1rem 2rem', 'gap'));
        $t->same(['value' => '1rem', 'important' => false], $block->getProperty('gap: 1rem 2rem', 'row-gap'));
        $t->same(['value' => '2rem', 'important' => false], $block->getProperty('gap: 1rem 2rem', 'column-gap'));
        $t->same(
            ['value' => 'var(--wp--style--block-gap)', 'important' => false],
            $block->getProperty(
                'row-gap: var(--wp--style--block-gap); column-gap: var(--wp--style--block-gap)',
                'gap'
            )
        );
        $t->same(
            ['value' => '1rem 2rem', 'important' => true],
            $block->getProperty('row-gap: 1rem !important; column-gap: 2rem !important', 'gap')
        );
        $t->same(null, $block->getProperty('row-gap: 1rem !important; column-gap: 2rem', 'gap'));
        $t->same(
            ['value' => '1rem', 'important' => true],
            $block->getProperty('gap: 1rem !important; row-gap: 2rem', 'row-gap')
        );
    },
    'declaration block reads upstream multi-column cssom shorthands and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => '2 16rem', 'important' => false], $block->getProperty('columns: 2 16rem', 'columns'));
        $t->same(['value' => '16rem', 'important' => false], $block->getProperty('columns: 2 16rem', 'column-width'));
        $t->same(['value' => '2', 'important' => false], $block->getProperty('columns: 2 16rem', 'column-count'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('columns: 16rem', 'column-count'));
        $t->same(['value' => '16rem', 'important' => false], $block->getProperty('columns: auto 16rem', 'columns'));
        $t->same(['value' => '3', 'important' => false], $block->getProperty('columns: auto 3', 'columns'));
        $t->same(
            ['value' => '3 18rem', 'important' => false],
            $block->getProperty('column-width: 18rem; column-count: 3', 'columns')
        );
        $t->same(null, $block->getProperty('column-width: 18rem !important; column-count: 3', 'columns'));
        $t->same(['value' => '2 16rem', 'important' => true], $block->getProperty('-webkit-columns: 2 16rem !important', '-webkit-columns'));
        $t->same(null, $block->getProperty('-webkit-columns: 2 16rem', 'columns'));

        $t->same(['value' => '1px solid #ddd', 'important' => false], $block->getProperty('column-rule: 1px solid #ddd', 'column-rule'));
        $t->same(['value' => '1px', 'important' => false], $block->getProperty('column-rule: 1px solid #ddd', 'column-rule-width'));
        $t->same(['value' => 'solid', 'important' => false], $block->getProperty('column-rule: 1px solid #ddd', 'column-rule-style'));
        $t->same(['value' => '#ddd', 'important' => false], $block->getProperty('column-rule: 1px solid #ddd', 'column-rule-color'));
        $t->same(
            ['value' => '2px dashed var(--wp--preset--color--contrast)', 'important' => false],
            $block->getProperty(
                'column-rule-width: 2px; column-rule-style: dashed; column-rule-color: var(--wp--preset--color--contrast)',
                'column-rule'
            )
        );
        $t->same(
            null,
            $block->getProperty('column-rule-width: 2px; column-rule-style: dashed !important; column-rule-color: red', 'column-rule')
        );
        $t->same(
            ['value' => '2px dotted #aaa', 'important' => true],
            $block->getProperty('-moz-column-rule: dotted 2px #aaa !important', '-moz-column-rule')
        );
        $t->same(null, $block->getProperty('-moz-column-rule: dotted 2px #aaa', 'column-rule'));
    },
    'declaration block reads upstream overflow cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(['value' => 'hidden auto', 'important' => false], $block->getProperty('overflow: hidden auto', 'overflow'));
        $t->same(['value' => 'hidden', 'important' => false], $block->getProperty('overflow: hidden auto', 'overflow-x'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('overflow: hidden auto', 'overflow-y'));
        $t->same(
            ['value' => 'clip', 'important' => false],
            $block->getProperty('overflow-x: clip; overflow-y: clip', 'overflow')
        );
        $t->same(
            ['value' => 'scroll auto', 'important' => true],
            $block->getProperty('overflow-x: scroll !important; overflow-y: auto !important', 'overflow')
        );
        $t->same(null, $block->getProperty('overflow-x: hidden; overflow-y: auto !important', 'overflow'));
        $t->same(
            ['value' => 'hidden', 'important' => true],
            $block->getProperty('overflow: hidden !important; overflow-x: visible', 'overflow-x')
        );
    },
    'declaration block reads upstream scroll snap cssom rect shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '1rem 2rem 3rem 4rem', 'important' => false],
            $block->getProperty(
                'scroll-margin-top: 1rem; scroll-margin-right: 2rem; scroll-margin-bottom: 3rem; scroll-margin-left: 4rem',
                'scroll-margin'
            )
        );
        $t->same(
            ['value' => '2rem', 'important' => false],
            $block->getProperty('scroll-margin: 1rem 2rem 3rem 4rem', 'scroll-margin-right')
        );
        $t->same(
            ['value' => 'var(--wp--style--block-gap)', 'important' => true],
            $block->getProperty('scroll-padding: var(--wp--style--block-gap) !important', 'scroll-padding-bottom')
        );
        $t->same(
            null,
            $block->getProperty(
                'scroll-padding-top: 1rem; scroll-padding-right: 1rem !important; scroll-padding-bottom: 1rem; scroll-padding-left: 1rem',
                'scroll-padding'
            )
        );
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
    'declaration block reads upstream animation cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $motion = 'animation: core-block-fade 240ms ease-out 80ms 2 reverse both paused scroll(block)';
        $t->same(['value' => 'core-block-fade', 'important' => false], $block->getProperty($motion, 'animation-name'));
        $t->same(['value' => '240ms', 'important' => false], $block->getProperty($motion, 'animation-duration'));
        $t->same(['value' => 'ease-out', 'important' => false], $block->getProperty($motion, 'animation-timing-function'));
        $t->same(['value' => '80ms', 'important' => false], $block->getProperty($motion, 'animation-delay'));
        $t->same(['value' => '2', 'important' => false], $block->getProperty($motion, 'animation-iteration-count'));
        $t->same(['value' => 'reverse', 'important' => false], $block->getProperty($motion, 'animation-direction'));
        $t->same(['value' => 'both', 'important' => false], $block->getProperty($motion, 'animation-fill-mode'));
        $t->same(['value' => 'paused', 'important' => false], $block->getProperty($motion, 'animation-play-state'));
        $t->same(['value' => 'scroll()', 'important' => false], $block->getProperty($motion, 'animation-timeline'));
        $t->same(
            ['value' => 'scroll(root), view(), --wp-scroll', 'important' => false],
            $block->getProperty('animation-timeline: scroll(root block), view(block auto auto), --wp-scroll', 'animation-timeline')
        );
        $t->same(
            ['value' => 'view(inline auto 20%), scroll(self y)', 'important' => false],
            $block->getProperty('animation-timeline: view(auto 20% inline), scroll(y self)', 'animation-timeline')
        );
        $t->same(
            ['value' => 'ease, linear', 'important' => false],
            $block->getProperty('animation: fade 200ms, slide 300ms linear 50ms', 'animation-timing-function')
        );
        $t->same(
            ['value' => '400ms', 'important' => true],
            $block->getProperty('animation: fade 200ms; animation-duration: 400ms !important', 'animation-duration')
        );
    },
    'declaration block canonicalizes upstream animation composition cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'animation-composition: ADD, Accumulate, replace !important; animation-name: wp-cover; --Animation-Composition: ADD';

        $t->same(
            [
                'animation-composition' => 'add, accumulate, replace !important',
                'animation-name' => 'wp-cover',
                '--Animation-Composition' => 'ADD',
            ],
            $block->parse($declarations)
        );
        $t->same(
            ['value' => 'add, accumulate, replace', 'important' => true],
            $block->getProperty($declarations, 'animation-composition')
        );
        $t->same(
            ['value' => 'replace, var(--wp--custom--composition)', 'important' => false],
            $block->getProperty('animation-composition: Replace, var(--wp--custom--composition)', 'animation-composition')
        );
        $t->same(
            ['value' => 'ADD', 'important' => false],
            $block->getProperty($declarations, '--Animation-Composition')
        );
        $t->same(
            'animation-name: wp-cover; --Animation-Composition: ADD; animation-composition: accumulate, add',
            $block->setProperty($declarations, 'animation-composition', 'Accumulate, ADD')
        );
        $t->same(
            'color: red; animation-composition: add, accumulate !important',
            $block->setProperty('animation-composition: replace; color: red', 'animation-composition', 'ADD, Accumulate', true)
        );
        $t->same(
            'color: red; animation-composition: replace',
            $block->setProperty('color: red', 'animation-composition', 'Replace')
        );
        $t->same(
            'animation-name: wp-cover; --Animation-Composition: ADD',
            $block->removeProperty($declarations, 'animation-composition')
        );
    },
    'declaration block reads upstream prefixed animation cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $webkit = '-webkit-animation: fade 200ms ease-in 50ms both paused';
        $t->same(['value' => 'fade', 'important' => false], $block->getProperty($webkit, '-webkit-animation-name'));
        $t->same(['value' => '200ms', 'important' => false], $block->getProperty($webkit, '-webkit-animation-duration'));
        $t->same(['value' => 'ease-in', 'important' => false], $block->getProperty($webkit, '-webkit-animation-timing-function'));
        $t->same(['value' => '50ms', 'important' => false], $block->getProperty($webkit, '-webkit-animation-delay'));
        $t->same(['value' => 'both', 'important' => false], $block->getProperty($webkit, '-webkit-animation-fill-mode'));
        $t->same(['value' => 'paused', 'important' => false], $block->getProperty($webkit, '-webkit-animation-play-state'));
        $t->same(['value' => '200ms ease-in 50ms both paused fade', 'important' => false], $block->getProperty($webkit, '-webkit-animation'));
        $t->same(null, $block->getProperty($webkit, 'animation-duration'));
        $t->same(null, $block->getProperty($webkit, '-webkit-animation-timeline'));

        $t->same(
            ['value' => '400ms', 'important' => true],
            $block->getProperty('-moz-animation: fade 200ms; -moz-animation-duration: 400ms !important', '-moz-animation-duration')
        );
        $t->same(
            ['value' => '300ms linear 75ms 2 reverse both slide', 'important' => false],
            $block->getProperty(
                '-o-animation-name: slide; -o-animation-duration: 300ms; -o-animation-timing-function: linear; -o-animation-iteration-count: 2; -o-animation-direction: reverse; -o-animation-play-state: running; -o-animation-delay: 75ms; -o-animation-fill-mode: both',
                '-o-animation'
            )
        );
    },
    'declaration block reads upstream animation range cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $range = 'animation-range: entry 10% exit 90%';
        $t->same(['value' => 'entry 10% exit 90%', 'important' => false], $block->getProperty($range, 'animation-range'));
        $t->same(['value' => 'entry 10%', 'important' => false], $block->getProperty($range, 'animation-range-start'));
        $t->same(['value' => 'exit 90%', 'important' => false], $block->getProperty($range, 'animation-range-end'));
        $t->same(['value' => 'entry', 'important' => false], $block->getProperty('animation-range: entry', 'animation-range-end'));
        $t->same(
            ['value' => '10% 90%', 'important' => false],
            $block->getProperty('animation-range-start: 10%; animation-range-end: 90%', 'animation-range')
        );
        $t->same(
            ['value' => 'entry 10%, contain', 'important' => true],
            $block->getProperty('animation-range: entry 10% exit 90%, contain !important', 'animation-range-start')
        );
        $t->same(
            null,
            $block->getProperty('animation-range-start: entry !important; animation-range-end: exit 90%', 'animation-range')
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
        $t->same(
            ['value' => '200ms', 'important' => false],
            $block->getProperty('-ms-transition: opacity 200ms ease-in 50ms', '-ms-transition-duration')
        );
        $t->same(
            ['value' => 'opacity 200ms ease-in 50ms', 'important' => true],
            $block->getProperty(
                '-ms-transition-property: opacity !important; -ms-transition-duration: 200ms !important; -ms-transition-delay: 50ms !important; -ms-transition-timing-function: ease-in !important',
                '-ms-transition'
            )
        );
    },
    'declaration block canonicalizes upstream transition property identifiers in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $longhands = 'transition-property: COLOR, c\\6f lor, --Block-Opacity; transition-duration: 200MS, 0MS; transition-timing-function: Ease-In, Linear; transition-delay: 50MS, 0MS';
        $shorthand = 'transition: COLOR 200MS Ease-In 50MS, --Block-Opacity 0MS ease';

        $t->same(
            [
                'transition-property' => 'color, color, --Block-Opacity',
                'transition-duration' => '200ms, 0s',
                'transition-timing-function' => 'ease-in, linear',
                'transition-delay' => '50ms, 0s',
            ],
            $block->parse($longhands)
        );
        $t->same(['value' => 'color, color, --Block-Opacity', 'important' => false], $block->getProperty($longhands, 'transition-property'));
        $t->same(['value' => '200ms, 0s', 'important' => false], $block->getProperty($longhands, 'transition-duration'));
        $t->same(['value' => 'ease-in, linear', 'important' => false], $block->getProperty($longhands, 'transition-timing-function'));
        $t->same(['value' => '50ms, 0s', 'important' => false], $block->getProperty($longhands, 'transition-delay'));
        $t->same(null, $block->getProperty($longhands, 'transition'));
        $t->same(['value' => 'color 200ms ease-in 50ms, --Block-Opacity', 'important' => false], $block->getProperty($shorthand, 'transition'));
        $t->same(['value' => 'color, --Block-Opacity', 'important' => false], $block->getProperty($shorthand, 'transition-property'));
        $t->same(['value' => '200ms, 0s', 'important' => false], $block->getProperty($shorthand, 'transition-duration'));
        $t->same(['value' => 'ease-in, ease', 'important' => false], $block->getProperty($shorthand, 'transition-timing-function'));
        $t->same(['value' => '50ms, 0s', 'important' => false], $block->getProperty($shorthand, 'transition-delay'));
        $t->same(
            'transition: background-color 200ms ease-in 50ms',
            $block->setProperty('transition: COLOR 200MS Ease-In 50MS', 'transition-property', 'Background-Color')
        );
        $t->same(
            'transition: opacity 200ms ease-out; color: red',
            $block->setProperty('transition: opacity 200MS Ease-In; color: red', 'transition-timing-function', 'Ease-Out')
        );
        $t->same('transition-property: color', $block->setProperty('transition-property: COLOR', 'transition-property', 'c\\6f lor'));
        $t->same('transition-property: --Block-Opacity', $block->setProperty('transition-property: opacity', 'transition-property', '--Block-Opacity'));
    },
    'declaration block reads upstream list style cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $list = 'list-style: inside url(marker.svg) square';
        $t->same(['value' => 'inside url(marker.svg) square', 'important' => false], $block->getProperty($list, 'list-style'));
        $t->same(['value' => 'square', 'important' => false], $block->getProperty($list, 'list-style-type'));
        $t->same(['value' => 'url(marker.svg)', 'important' => false], $block->getProperty($list, 'list-style-image'));
        $t->same(['value' => 'inside', 'important' => false], $block->getProperty($list, 'list-style-position'));
        $t->same(
            ['value' => 'url(marker.svg)', 'important' => false],
            $block->getProperty(
                'list-style-type: disc; list-style-image: url(marker.svg); list-style-position: outside',
                'list-style'
            )
        );
        $t->same(['value' => 'none', 'important' => true], $block->getProperty('list-style: none !important', 'list-style-type'));
    },
    'declaration block reads upstream text decoration cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $decoration = 'text-decoration: underline wavy red 2px';
        $t->same(['value' => 'underline 2px wavy red', 'important' => false], $block->getProperty($decoration, 'text-decoration'));
        $t->same(['value' => 'underline', 'important' => false], $block->getProperty($decoration, 'text-decoration-line'));
        $t->same(['value' => '2px', 'important' => false], $block->getProperty($decoration, 'text-decoration-thickness'));
        $t->same(['value' => 'wavy', 'important' => false], $block->getProperty($decoration, 'text-decoration-style'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty($decoration, 'text-decoration-color'));
        $t->same(
            ['value' => 'underline overline from-font dashed var(--wp--preset--color--accent)', 'important' => true],
            $block->getProperty(
                'text-decoration-line: overline underline !important; text-decoration-thickness: from-font !important; text-decoration-style: dashed !important; text-decoration-color: var(--wp--preset--color--accent) !important',
                'text-decoration'
            )
        );
        $t->same(
            ['value' => 'none', 'important' => false],
            $block->getProperty('text-decoration: wavy red', 'text-decoration')
        );
        $t->same(
            null,
            $block->getProperty(
                'text-decoration-line: underline; text-decoration-thickness: 2px !important; text-decoration-style: wavy; text-decoration-color: red',
                'text-decoration'
            )
        );
    },
    'declaration block reads upstream prefixed text decoration cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $decoration = '-webkit-text-decoration: underline wavy red 2px';
        $t->same(['value' => 'underline 2px wavy red', 'important' => false], $block->getProperty($decoration, '-webkit-text-decoration'));
        $t->same(['value' => 'underline', 'important' => false], $block->getProperty($decoration, '-webkit-text-decoration-line'));
        $t->same(['value' => 'wavy', 'important' => false], $block->getProperty($decoration, '-webkit-text-decoration-style'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty($decoration, '-webkit-text-decoration-color'));
        $t->same(null, $block->getProperty($decoration, 'text-decoration-thickness'));
        $t->same(
            ['value' => 'underline overline from-font dashed var(--wp--preset--color--accent)', 'important' => true],
            $block->getProperty(
                '-webkit-text-decoration-line: overline underline !important; text-decoration-thickness: from-font !important; -webkit-text-decoration-style: dashed !important; -webkit-text-decoration-color: var(--wp--preset--color--accent) !important',
                '-webkit-text-decoration'
            )
        );
        $t->same(
            ['value' => 'blue', 'important' => false],
            $block->getProperty('-moz-text-decoration: line-through dotted blue', '-moz-text-decoration-color')
        );
        $t->same(null, $block->getProperty('-webkit-text-decoration: underline red', 'text-decoration-color'));
        $t->same(null, $block->getProperty('text-decoration: underline red', '-webkit-text-decoration-color'));
    },
    'declaration block reads upstream text emphasis cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $emphasis = 'text-emphasis: dot var(--wp--preset--color--accent)';
        $t->same(['value' => 'dot var(--wp--preset--color--accent)', 'important' => false], $block->getProperty($emphasis, 'text-emphasis'));
        $t->same(['value' => 'dot', 'important' => false], $block->getProperty($emphasis, 'text-emphasis-style'));
        $t->same(['value' => 'var(--wp--preset--color--accent)', 'important' => false], $block->getProperty($emphasis, 'text-emphasis-color'));
        $t->same(
            ['value' => 'open sesame #123456', 'important' => true],
            $block->getProperty(
                'text-emphasis-style: sesame open !important; text-emphasis-color: #123456 !important',
                'text-emphasis'
            )
        );
        $t->same(['value' => 'open dot', 'important' => false], $block->getProperty('text-emphasis: dot open red', 'text-emphasis-style'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty('text-emphasis: dot open red', 'text-emphasis-color'));
        $t->same(
            null,
            $block->getProperty('text-emphasis-style: dot; text-emphasis-color: red !important', 'text-emphasis')
        );
        $t->same(['value' => 'open circle blue', 'important' => false], $block->getProperty('-webkit-text-emphasis: open circle blue', '-webkit-text-emphasis'));
        $t->same(null, $block->getProperty('-webkit-text-emphasis: open circle blue', 'text-emphasis'));
        $t->same(['value' => 'over left', 'important' => false], $block->getProperty('text-emphasis-position: over left', 'text-emphasis-position'));
    },
    'declaration block canonicalizes upstream text decoration skip ink and emphasis position cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'text-decoration-skip-ink: ALL; -webkit-text-decoration-skip-ink: None; text-emphasis-position: OVER RIGHT; -webkit-text-emphasis-position: left UNDER; --Skip-Ink: ALL';

        $t->same(
            [
                'text-decoration-skip-ink' => 'all',
                '-webkit-text-decoration-skip-ink' => 'none',
                'text-emphasis-position' => 'over',
                '-webkit-text-emphasis-position' => 'under left',
                '--Skip-Ink' => 'ALL',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => 'all', 'important' => false], $block->getProperty($declarations, 'text-decoration-skip-ink'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($declarations, '-webkit-text-decoration-skip-ink'));
        $t->same(['value' => 'over', 'important' => false], $block->getProperty($declarations, 'text-emphasis-position'));
        $t->same(['value' => 'under left', 'important' => false], $block->getProperty($declarations, '-webkit-text-emphasis-position'));
        $t->same(['value' => 'ALL', 'important' => false], $block->getProperty($declarations, '--Skip-Ink'));
        $t->same(['value' => 'over', 'important' => false], $block->getProperty('text-emphasis-position: RIGHT over', 'text-emphasis-position'));
        $t->same(['value' => 'under left', 'important' => false], $block->getProperty('text-emphasis-position: LEFT under', 'text-emphasis-position'));
        $t->same(['value' => 'auto', 'important' => true], $block->getProperty('text-decoration-skip-ink: Auto !important', 'text-decoration-skip-ink'));
        $t->same(
            'text-decoration-skip-ink: auto; -webkit-text-decoration-skip-ink: none; text-emphasis-position: over; -webkit-text-emphasis-position: under left; --Skip-Ink: ALL',
            $block->setProperty($declarations, 'text-decoration-skip-ink', 'Auto')
        );
        $t->same(
            'text-decoration-skip-ink: all; text-emphasis-position: over; -webkit-text-emphasis-position: under left; --Skip-Ink: ALL; -webkit-text-decoration-skip-ink: all !important',
            $block->setProperty($declarations, '-webkit-text-decoration-skip-ink', 'ALL', true)
        );
        $t->same(
            'text-decoration-skip-ink: all; -webkit-text-decoration-skip-ink: none; text-emphasis-position: over left; -webkit-text-emphasis-position: under left; --Skip-Ink: ALL',
            $block->setProperty($declarations, 'text-emphasis-position', 'left over')
        );
        $t->same(
            'text-decoration-skip-ink: all; -webkit-text-decoration-skip-ink: none; text-emphasis-position: over; --Skip-Ink: ALL; -webkit-text-emphasis-position: under !important',
            $block->setProperty($declarations, '-webkit-text-emphasis-position', 'RIGHT UNDER', true)
        );
        $t->same(
            '-webkit-text-decoration-skip-ink: none; text-emphasis-position: over; -webkit-text-emphasis-position: under left; --Skip-Ink: ALL',
            $block->removeProperty($declarations, 'text-decoration-skip-ink')
        );
        $t->same(
            'text-decoration-skip-ink: all; -webkit-text-decoration-skip-ink: none; text-emphasis-position: over; --Skip-Ink: ALL',
            $block->removeProperty($declarations, '-webkit-text-emphasis-position')
        );
    },
    'declaration block reads upstream caret cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $caret = 'caret: red block';
        $t->same(['value' => 'red block', 'important' => false], $block->getProperty($caret, 'caret'));
        $t->same(['value' => 'red', 'important' => false], $block->getProperty($caret, 'caret-color'));
        $t->same(['value' => 'block', 'important' => false], $block->getProperty($caret, 'caret-shape'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('caret: block', 'caret-color'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('caret: red', 'caret-shape'));
        $t->same(['value' => 'auto', 'important' => false], $block->getProperty('caret: auto auto', 'caret'));
        $t->same(['value' => 'yellow block', 'important' => false], $block->getProperty('caret: block yellow', 'caret'));
        $t->same(
            ['value' => 'red underscore', 'important' => true],
            $block->getProperty('caret-color: red !important; caret-shape: underscore !important', 'caret')
        );
        $t->same(
            null,
            $block->getProperty('caret-color: red; caret-shape: block !important', 'caret')
        );
        $t->same(['value' => 'bar', 'important' => true], $block->getProperty('caret: auto bar !important; caret-color: red', 'caret-shape'));
    },
    'declaration block reads upstream font cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $font = 'font: italic small-caps 600 condensed 16px/1.5 "Inter", sans-serif';
        $t->same(['value' => 'italic small-caps 600 condensed 16px/1.5 Inter, sans-serif', 'important' => false], $block->getProperty($font, 'font'));
        $t->same(['value' => 'Inter, sans-serif', 'important' => false], $block->getProperty($font, 'font-family'));
        $t->same(['value' => '16px', 'important' => false], $block->getProperty($font, 'font-size'));
        $t->same(['value' => 'italic', 'important' => false], $block->getProperty($font, 'font-style'));
        $t->same(['value' => '600', 'important' => false], $block->getProperty($font, 'font-weight'));
        $t->same(['value' => 'condensed', 'important' => false], $block->getProperty($font, 'font-stretch'));
        $t->same(['value' => '1.5', 'important' => false], $block->getProperty($font, 'line-height'));
        $t->same(['value' => 'small-caps', 'important' => false], $block->getProperty($font, 'font-variant-caps'));
        $oblique = 'font-style: Oblique 14deg; font: oblique +014.000deg 600 16px Inter; --Font-Style: Oblique 14deg';
        $t->same(
            [
                'font-style' => 'oblique',
                'font' => 'oblique 600 16px Inter',
                '--Font-Style' => 'Oblique 14deg',
            ],
            $block->parse($oblique)
        );
        $t->same(['value' => 'oblique', 'important' => false], $block->getProperty($oblique, 'font-style'));
        $t->same(['value' => 'oblique 600 16px Inter', 'important' => false], $block->getProperty($oblique, 'font'));
        $t->same(['value' => 'oblique 40deg', 'important' => false], $block->getProperty('font-style: OBLIQUE 40.000deg', 'font-style'));
        $t->same(['value' => 'oblique .25turn', 'important' => false], $block->getProperty('font-style: oblique +0.2500TURN', 'font-style'));
        $t->same(
            ['value' => '700 clamp(1.25rem, 2vw, 2rem)/1.2 Inter var, system-ui', 'important' => true],
            $block->getProperty(
                'font-family: "Inter var", system-ui !important; font-size: clamp(1.25rem, 2vw, 2rem) !important; font-style: normal !important; font-weight: 700 !important; font-stretch: normal !important; line-height: 1.2 !important; font-variant-caps: normal !important',
                'font'
            )
        );
        $t->same(
            null,
            $block->getProperty(
                'font-family: Inter; font-size: 16px; font-style: italic; font-weight: 600 !important; font-stretch: normal; line-height: 1.5; font-variant-caps: small-caps',
                'font'
            )
        );
    },
    'declaration block canonicalizes upstream direct font longhand cssom declarations' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $direct = 'font-family: "Inter var", system-ui; font-size: +016.00PX; line-height: +001.500; font-weight: +0700; font-stretch: 125.0%; font-variant-caps: All-Small-Caps; --Font-Size: +016.00PX';

        $t->same(
            [
                'font-family' => 'Inter var, system-ui',
                'font-size' => '16px',
                'line-height' => '1.5',
                'font-weight' => '700',
                'font-stretch' => '125%',
                'font-variant-caps' => 'all-small-caps',
                '--Font-Size' => '+016.00PX',
            ],
            $block->parse($direct)
        );
        $t->same(['value' => 'Inter var, system-ui', 'important' => false], $block->getProperty($direct, 'font-family'));
        $t->same(['value' => '16px', 'important' => false], $block->getProperty($direct, 'font-size'));
        $t->same(['value' => '1.5', 'important' => false], $block->getProperty($direct, 'line-height'));
        $t->same(['value' => '700', 'important' => false], $block->getProperty($direct, 'font-weight'));
        $t->same(['value' => '125%', 'important' => false], $block->getProperty($direct, 'font-stretch'));
        $t->same(['value' => 'all-small-caps', 'important' => false], $block->getProperty($direct, 'font-variant-caps'));
        $t->same(
            'color: red; font-size: 18px',
            $block->setProperty('color: red', 'font-size', '+018.00PX')
        );
        $t->same(
            'color: red; font-family: "Source Serif 4", serif',
            $block->setProperty('color: red', 'font-family', '"Source Serif 4", serif')
        );
        $t->same(
            'color: red; font-weight: 700',
            $block->setProperty('color: red', 'font-weight', '+0700')
        );
        $t->same(
            'color: red; font-stretch: 87.5%',
            $block->setProperty('color: red', 'font-stretch', '+087.500%')
        );
        $t->same(
            'color: red; line-height: 1.25',
            $block->setProperty('color: red', 'line-height', '+001.250')
        );
        $t->same(
            'color: red; font-variant-caps: petite-caps',
            $block->setProperty('color: red', 'font-variant-caps', 'Petite-Caps')
        );
    },
    'declaration block canonicalizes upstream font palette cssom dashed idents' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'font-palette: --\\43 ooler; --Font-Palette: --\\43 ooler; color: red';

        $t->same(
            [
                'font-palette' => '--Cooler',
                '--Font-Palette' => '--\\43 ooler',
                'color' => 'red',
            ],
            $block->parse($declarations)
        );
        $t->same(['value' => '--Cooler', 'important' => false], $block->getProperty($declarations, 'font-palette'));
        $t->same(['value' => '--Cooler', 'important' => true], $block->getProperty('font-palette: --\\43 ooler !important', 'font-palette'));
        $t->same(['value' => '--wp\\ Palette', 'important' => false], $block->getProperty('font-palette: --wp\\ Palette', 'font-palette'));
        $t->same(
            'font-palette: --Editor; --Font-Palette: --\\43 ooler; color: red',
            $block->setProperty($declarations, 'font-palette', '--Editor')
        );
        $t->same(
            '--Font-Palette: --\\43 ooler; color: red; font-palette: --Cooler !important',
            $block->setProperty($declarations, 'font-palette', '--\\43 ooler', true)
        );
        $t->same(
            '--Font-Palette: --\\43 ooler; color: red',
            $block->removeProperty($declarations, 'font-palette')
        );
    },
    'declaration block reads upstream container cssom shorthand and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $container = 'container: wp-query-card / inline-size';
        $t->same(['value' => 'wp-query-card / inline-size', 'important' => false], $block->getProperty($container, 'container'));
        $t->same(['value' => 'wp-query-card', 'important' => false], $block->getProperty($container, 'container-name'));
        $t->same(['value' => 'inline-size', 'important' => false], $block->getProperty($container, 'container-type'));
        $t->same(
            ['value' => 'wp-query-card / size', 'important' => true],
            $block->getProperty('container-name: wp-query-card !important; container-type: size !important', 'container')
        );
        $t->same(
            ['value' => 'wp-query-card', 'important' => false],
            $block->getProperty('container-name: wp-query-card; container-type: normal', 'container')
        );
        $t->same(
            null,
            $block->getProperty('container-name: wp-query-card !important; container-type: inline-size', 'container')
        );
        $t->same(
            ['value' => 'size', 'important' => true],
            $block->getProperty('container: wp-query-card / inline-size; container-type: size !important', 'container-type')
        );
    },
    'declaration block set replaces direct properties and serializes priority' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('color: green', $block->setProperty('color: red', 'color', 'green'));
        $t->same('color: green !important', $block->setProperty('color: red !important', 'color', 'green', true));
        $t->same('color: red; background-color: #00f', $block->setProperty('color: red', 'background-color', 'blue'));
        $t->same('margin: 8px 5px 5px', $block->setProperty('margin: 5px', 'margin-top', '8px'));
        $t->same('padding: 1rem 2rem 1rem 4rem', $block->setProperty('padding: 1rem 2rem', 'padding-left', '4rem'));
        $t->same(
            'margin: 5px; margin-inline-start: 8px; margin-left: 10px',
            $block->setProperty('margin: 5px; margin-inline-start: 8px', 'margin-left', '10px')
        );
    },
    'declaration block parses upstream cssom set values before top level delimiters' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'color: green',
            $block->setProperty('color: red', 'color', 'green; background: blue')
        );
        $t->same(
            'color: green',
            $block->setProperty('color: red', 'color', 'green !important')
        );
        $t->same(
            'color: green !important',
            $block->setProperty('color: red', 'color', 'green !important', true)
        );
        $t->same(
            'background: url("hero;wide.jpg"); color: green',
            $block->setProperty('background: url("hero;wide.jpg")', 'color', 'green; --wp--bad: nope')
        );
        $t->same(
            '--Block-Accent: blue',
            $block->setProperty('--Block-Accent: red', '--Block-Accent', 'blue; color: red !important')
        );
        $t->same(
            '--Theme-Rule: { color: red; background: url("/wp-content/uploads/a;b.svg") }',
            $block->setProperty('--Theme-Rule: old', '--Theme-Rule', '{ color: red; background: url("/wp-content/uploads/a;b.svg") }; color: blue')
        );
        $t->same(
            '--Escaped: token\\; still\\! kept',
            $block->setProperty('--Escaped: old', '--Escaped', 'token\\; still\\! kept; color: red')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $block->setProperty('color: red', 'color', '!important')
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
    'declaration block rejects mixed priority cssom shorthand reads' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $mixedMargin = 'margin: 1px; margin-top: 2px !important; margin-right: 2px !important; margin-bottom: 2px !important; margin-left: 2px !important';
        $mixedLogicalMargin = 'margin-inline: 1px 2px; margin-inline-start: 3px !important; margin-inline-end: 4px !important';
        $mixedFlexFlow = 'flex-flow: row wrap; flex-direction: column !important; flex-wrap: nowrap !important';
        $mixedFlex = 'flex: 1 0 auto; flex-grow: 2 !important; flex-shrink: 2 !important; flex-basis: 10px !important';
        $writtenMargin = $block->setProperty('margin: 1px', 'margin-top', '2px', true);
        $writtenFlexFlow = $block->setProperty('flex-flow: row wrap', 'flex-direction', 'column', true);

        $t->same(null, $block->getProperty($mixedMargin, 'margin'));
        $t->same(['value' => '2px', 'important' => true], $block->getProperty($mixedMargin, 'margin-top'));
        $t->same(null, $block->getProperty($mixedLogicalMargin, 'margin-inline'));
        $t->same(['value' => '3px', 'important' => true], $block->getProperty($mixedLogicalMargin, 'margin-inline-start'));
        $t->same(null, $block->getProperty($mixedFlexFlow, 'flex-flow'));
        $t->same(['value' => 'column', 'important' => true], $block->getProperty($mixedFlexFlow, 'flex-direction'));
        $t->same(null, $block->getProperty($mixedFlex, 'flex'));
        $t->same(['value' => '2', 'important' => true], $block->getProperty($mixedFlex, 'flex-grow'));
        $t->same('margin: 1px; margin-top: 2px !important', $writtenMargin);
        $t->same(null, $block->getProperty($writtenMargin, 'margin'));
        $t->same('flex-flow: row wrap; flex-direction: column !important', $writtenFlexFlow);
        $t->same(null, $block->getProperty($writtenFlexFlow, 'flex-flow'));
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
    'declaration block reads and writes upstream logical size cssom properties after physical fallbacks' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '5px', 'important' => false],
            $block->getProperty('block-size: 5px; width: 10px', 'block-size')
        );
        $t->same(
            'block-size: 5px; width: 10px; block-size: 8px',
            $block->setProperty('block-size: 5px; width: 10px', 'block-size', '8px')
        );
        $t->same(
            'width: 10px; block-size: 8px',
            $block->setProperty('width: 10px; block-size: 5px', 'block-size', '8px')
        );
        $t->same(
            'inline-size: 50%; height: auto; inline-size: 75%',
            $block->setProperty('inline-size: 50%; height: auto', 'inline-size', '75%')
        );
        $t->same(
            'min-inline-size: 20rem; min-width: 10rem; min-inline-size: 24rem',
            $block->setProperty('min-inline-size: 20rem; min-width: 10rem', 'min-inline-size', '24rem')
        );
        $t->same(
            'max-height: 80vh; max-block-size: 90vh; max-height: 70vh',
            $block->setProperty('max-height: 80vh; max-block-size: 90vh', 'max-height', '70vh')
        );
        $t->same(
            'width: 10px; block-size: 8px !important',
            $block->setProperty('block-size: 5px; width: 10px', 'block-size', '8px', true)
        );
    },
    'declaration block canonicalizes upstream size cssom read write values' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $declarations = 'width: MIN-CONTENT; height: -WEBKIT-FILL-AVAILABLE; block-size: FIT-CONTENT(100.0%); inline-size: +000.500rem; min-width: -MOZ-AVAILABLE; min-inline-size: CONTAIN; max-width: NONE; max-block-size: fit-content(12.00PX); --Width: MIN-CONTENT';

        $t->same(['value' => 'min-content', 'important' => false], $block->getProperty($declarations, 'width'));
        $t->same(['value' => '-webkit-fill-available', 'important' => false], $block->getProperty($declarations, 'height'));
        $t->same(['value' => 'fit-content(100%)', 'important' => false], $block->getProperty($declarations, 'block-size'));
        $t->same(['value' => '.5rem', 'important' => false], $block->getProperty($declarations, 'inline-size'));
        $t->same(['value' => '-moz-available', 'important' => false], $block->getProperty($declarations, 'min-width'));
        $t->same(['value' => 'contain', 'important' => false], $block->getProperty($declarations, 'min-inline-size'));
        $t->same(['value' => 'none', 'important' => false], $block->getProperty($declarations, 'max-width'));
        $t->same(['value' => 'fit-content(12px)', 'important' => false], $block->getProperty($declarations, 'max-block-size'));
        $t->same(['value' => 'MIN-CONTENT', 'important' => false], $block->getProperty($declarations, '--Width'));
        $t->same(
            'width: fit-content(100%); color: red',
            $block->setProperty('width: MIN-CONTENT; color: red', 'width', 'FIT-CONTENT(100.0%)')
        );
        $t->same(
            'width: min-content; color: red; max-inline-size: -webkit-fill-available !important',
            $block->setProperty('width: MIN-CONTENT; max-inline-size: NONE; color: red', 'max-inline-size', '-WEBKIT-FILL-AVAILABLE', true)
        );
        $t->same(
            'height: -webkit-fill-available; min-width: -moz-available; max-width: none',
            $block->removeProperty('height: -WEBKIT-FILL-AVAILABLE; min-width: -MOZ-AVAILABLE; max-width: NONE; width: MIN-CONTENT', 'width')
        );
    },
    'declaration block reads upstream logical axis cssom shorthands and longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '1rem', 'important' => false],
            $block->getProperty('margin-inline: 1rem 2rem', 'margin-inline-start')
        );
        $t->same(
            ['value' => '2rem', 'important' => false],
            $block->getProperty('margin-inline: 1rem 2rem', 'margin-inline-end')
        );
        $t->same(
            ['value' => '1rem 2rem', 'important' => false],
            $block->getProperty('margin-inline-start: 1rem; margin-inline-end: 2rem', 'margin-inline')
        );
        $t->same(
            ['value' => 'var(--wp--preset--spacing--40)', 'important' => true],
            $block->getProperty('padding-block: var(--wp--preset--spacing--40) !important', 'padding-block-end')
        );
        $t->same(
            ['value' => '8px 16px', 'important' => false],
            $block->getProperty('scroll-padding-inline-start: 8px; scroll-padding-inline-end: 16px', 'scroll-padding-inline')
        );
        $t->same(
            ['value' => '2px 4px', 'important' => false],
            $block->getProperty('inset-block-start: 2px; inset-block-end: 4px', 'inset-block')
        );
        $t->same(
            null,
            $block->getProperty('margin-inline-start: 1rem; margin-inline-end: 2rem !important', 'margin-inline')
        );
        $t->same(null, $block->getProperty('margin-inline: 1rem 2rem', 'margin-left'));
    },
    'declaration block canonicalizes upstream box spacing length values in cssom read write' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '0 .5rem', 'important' => false],
            $block->getProperty('margin: 0px 0.500rem', 'margin')
        );
        $t->same(
            ['value' => '0', 'important' => false],
            $block->getProperty('padding-inline: +0em -0px', 'padding-inline')
        );
        $t->same(
            ['value' => '.25%', 'important' => false],
            $block->getProperty('scroll-margin-top: 0.250%', 'scroll-margin-top')
        );
        $t->same(
            ['value' => '5px 0', 'important' => false],
            $block->getProperty('inset: 5 0px', 'inset')
        );
        $t->same('margin: 0 5px 5px', $block->setProperty('margin: 5px', 'margin-top', '0px'));
        $t->same(
            'scroll-padding-inline: .5rem 2rem',
            $block->setProperty('scroll-padding-inline: 0px 2rem', 'scroll-padding-inline-start', '0.500rem')
        );
        $t->same(
            'inset-block: 0 1.25rem',
            $block->setProperty('inset-block: 0px 1.250rem', 'inset-block-start', '-0px')
        );
        $t->same(
            'padding-inline-end: 2rem',
            $block->removeProperty('padding-inline: 0px 2rem', 'padding-inline-start')
        );
    },
    'declaration block sets upstream logical axis cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'margin-inline: 3rem 2rem',
            $block->setProperty('margin-inline: 1rem 2rem', 'margin-inline-start', '3rem')
        );
        $t->same(
            'padding-block: 1rem 3rem',
            $block->setProperty('padding-block: 1rem 2rem', 'padding-block-end', '3rem')
        );
        $t->same(
            'scroll-padding-inline: 8px 24px',
            $block->setProperty('scroll-padding-inline: 8px 16px', 'scroll-padding-inline-end', '24px')
        );
        $t->same(
            'inset-block: 2px',
            $block->setProperty('inset-block: 2px 4px', 'inset-block-end', '2px')
        );
        $t->same(
            'margin-inline: 1rem 2rem; margin-left: 4rem; margin-inline-start: 3rem',
            $block->setProperty('margin-inline: 1rem 2rem; margin-left: 4rem', 'margin-inline-start', '3rem')
        );
        $t->same(
            'padding-inline-start: 3rem; padding-inline: 1rem 2rem !important',
            $block->setProperty('padding-inline: 1rem 2rem !important', 'padding-inline-start', '3rem')
        );
    },
    'declaration block sets upstream shorthands after opposite logical group fallbacks' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'margin: 1rem; margin-inline-start: 2rem; margin: 3rem',
            $block->setProperty('margin: 1rem; margin-inline-start: 2rem', 'margin', '3rem')
        );
        $t->same(
            'margin-inline: 1rem 2rem; margin-left: 4rem; margin-inline: 3rem',
            $block->setProperty('margin-inline: 1rem 2rem; margin-left: 4rem', 'margin-inline', '3rem')
        );
        $t->same(
            'scroll-padding: 8px; scroll-padding-block-start: 12px; scroll-padding: 16px',
            $block->setProperty('scroll-padding: 8px; scroll-padding-block-start: 12px', 'scroll-padding', '16px')
        );
        $t->same(
            'border-color: red; border-inline-start-color: #00f; border-color: green',
            $block->setProperty('border-color: red; border-inline-start-color: blue', 'border-color', 'green')
        );
        $t->same(
            'border-block-color: red blue; border-left-color: #000; border-block-color: green',
            $block->setProperty('border-block-color: red blue; border-left-color: black', 'border-block-color', 'green')
        );
        $t->same(
            'border-radius: 8px; border-start-start-radius: 12px; border-radius: 16px',
            $block->setProperty('border-radius: 8px; border-start-start-radius: 12px', 'border-radius', '16px')
        );
    },
    'declaration block reads and writes upstream inset cssom rect shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '0 1px', 'important' => false],
            $block->getProperty('top: 0; right: 1px; bottom: 0; left: 1px', 'inset')
        );
        $t->same(
            ['value' => '2px', 'important' => false],
            $block->getProperty('inset: 2px 4px 6px 8px', 'top')
        );
        $t->same(
            ['value' => '4px', 'important' => false],
            $block->getProperty('inset: 2px 4px 6px 8px', 'right')
        );
        $t->same(
            null,
            $block->getProperty('top: 0; right: 1px; bottom: 0 !important; left: 1px', 'inset')
        );
        $t->same(
            ['value' => '0', 'important' => true],
            $block->getProperty('inset: 0 !important; top: 2px', 'top')
        );
        $t->same('inset: 2px 0 0', $block->setProperty('inset: 0', 'top', '2px'));
        $t->same(
            'inset: 1px 2px 1px 3px',
            $block->setProperty('inset: 1px 2px', 'left', '3px')
        );
        $t->same(
            'inset: 4px; inset-inline-start: 2px; top: 8px',
            $block->setProperty('inset: 4px; inset-inline-start: 2px', 'top', '8px')
        );
        $t->same(
            'top: 10px; inset-inline-start: 4px',
            $block->setProperty('top: 10px; inset-inline-start: 2px', 'inset-inline-start', '4px')
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
        $t->same(
            'background-position: left 10px',
            $block->setProperty('background-position: 20px 10px', 'background-position-x', 'left')
        );
        $t->same(
            'background-position: 20px bottom',
            $block->setProperty('background-position: 20px 10px', 'background-position-y', 'bottom')
        );
        $t->same(
            'background-position: 30px 10px, right top',
            $block->setProperty('background-position: 20px 10px, left top', 'background-position-x', '30px, right')
        );
        $t->same(
            'color: red; background-position: 20px bottom !important',
            $block->setProperty('background-position: 20px 10px !important; color: red', 'background-position-y', 'bottom', true)
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
    'declaration block sets upstream background attachment origin and clip cssom longhands in shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'background: url(hero.jpg) fixed',
            $block->setProperty('background: url(hero.jpg)', 'background-attachment', 'fixed')
        );
        $t->same(
            'background: url(hero.jpg) content-box border-box',
            $block->setProperty('background: url(hero.jpg)', 'background-origin', 'content-box')
        );
        $t->same(
            'background: url(hero.jpg) padding-box content-box',
            $block->setProperty('background: url(hero.jpg)', 'background-clip', 'content-box')
        );
        $t->same(
            'background: url(a.png) fixed, url(b.png) local',
            $block->setProperty('background: url(a.png), url(b.png)', 'background-attachment', 'fixed, local')
        );
        $t->same(
            'background: url(a.png), url(b.png); background-clip: text',
            $block->setProperty('background: url(a.png), url(b.png)', 'background-clip', 'text')
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
    'declaration block sets upstream physical border component cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            ['value' => '1px solid red', 'important' => false],
            $block->getProperty('border-top: 1px solid red', 'border-top')
        );
        $t->same(
            ['value' => 'green', 'important' => true],
            $block->getProperty('border-color: red green !important', 'border-left-color')
        );
        $t->same(
            'border-color: #00f green red',
            $block->setProperty('border-color: red green', 'border-top-color', 'blue')
        );
        $t->same(
            'border-width: 1px 2px 1px 4px',
            $block->setProperty('border-width: 1px 2px', 'border-left-width', '4px')
        );
        $t->same(
            'border-style: solid dashed solid double',
            $block->setProperty('border-style: solid dashed dotted double', 'border-bottom-style', 'solid')
        );
        $t->same(
            'border-top: 2px solid red',
            $block->setProperty('border-top: 1px solid red', 'border-top-width', '2px')
        );
        $t->same(
            'border-right: 1px dotted blue',
            $block->setProperty('border-right: 1px solid blue', 'border-right-style', 'dotted')
        );
        $t->same(
            'border-top: 1px solid red; border-color: green #00f green green',
            $block->setProperty('border-top: 1px solid red; border-color: green', 'border-right-color', 'blue')
        );
        $t->same(
            'border-color: red green; border-inline-start-color: orange; border-top-color: #00f',
            $block->setProperty('border-color: red green; border-inline-start-color: orange', 'border-top-color', 'blue')
        );
        $t->same(
            'border-top-color: #00f; border-color: red green !important',
            $block->setProperty('border-color: red green !important', 'border-top-color', 'blue')
        );
    },
    'declaration block sets upstream logical border cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-block-color: #00f green',
            $block->setProperty('border-block-color: red green', 'border-block-start-color', 'blue')
        );
        $t->same(
            'border-inline-width: 2px',
            $block->setProperty('border-inline-width: 1px 2px', 'border-inline-start-width', '2px')
        );
        $t->same(
            'border-inline-start: 1px dashed red',
            $block->setProperty('border-inline-start: 1px solid red', 'border-inline-start-style', 'dashed')
        );
        $t->same(
            'border-block: 1px solid red; border-block-start-color: #00f',
            $block->setProperty('border-block: 1px solid red', 'border-block-start-color', 'blue')
        );
        $t->same(
            'border-block-start-color: #00f; border-block-color: red green !important',
            $block->setProperty('border-block-color: red green !important', 'border-block-start-color', 'blue')
        );
        $t->same(
            'border-block-color: green; border-top-color: red; border-block-start-color: #00f',
            $block->setProperty('border-block-color: green; border-top-color: red', 'border-block-start-color', 'blue')
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
            '-ms-flex-flow: column wrap',
            $block->setProperty('-ms-flex-flow: row wrap', '-ms-flex-direction', 'column')
        );
        $t->same(
            'flex-flow: wrap; -webkit-flex-direction: column',
            $block->setProperty('flex-flow: row wrap', '-webkit-flex-direction', 'column')
        );
        $t->same(
            'flex-flow: wrap; -ms-flex-direction: column',
            $block->setProperty('flex-flow: row wrap', '-ms-flex-direction', 'column')
        );
    },
    'declaration block sets upstream flex cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('flex: 1 0', $block->setProperty('flex: 0 0', 'flex-grow', '1'));
        $t->same('flex: 1 1 0', $block->setProperty('flex: 1 1 0', 'flex-basis', '0px'));
        $t->same('flex: 1', $block->setProperty('flex: 1 1 0', 'flex-basis', '0%'));
        $t->same('flex: 1 0 auto', $block->setProperty('flex: auto', 'flex-shrink', '0'));
        $t->same('-webkit-flex: 2 auto', $block->setProperty('-webkit-flex: auto', '-webkit-flex-grow', '2'));
        $t->same(
            'flex-basis: auto; flex: 1 !important',
            $block->setProperty('flex: 1 !important', 'flex-basis', 'auto')
        );
        $t->same(
            'flex: 0 0; flex-grow: var(--grow)',
            $block->setProperty('flex: 0 0', 'flex-grow', 'var(--grow)')
        );
    },
    'declaration block sets upstream place alignment cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'place-content: start space-between',
            $block->setProperty('place-content: center space-between', 'align-content', 'start')
        );
        $t->same(
            'place-content: center',
            $block->setProperty('place-content: center space-between', 'justify-content', 'center')
        );
        $t->same(
            'place-content: baseline end',
            $block->setProperty('place-content: first baseline', 'justify-content', 'end')
        );
        $t->same(
            'place-self: auto end',
            $block->setProperty('place-self: auto start', 'justify-self', 'end')
        );
        $t->same(
            'place-items: stretch center',
            $block->setProperty('place-items: flex-end center', 'align-items', 'stretch')
        );
        $t->same(
            'align-content: start; place-content: center space-between !important',
            $block->setProperty('place-content: center space-between !important', 'align-content', 'start')
        );
        $t->same(
            'place-items: baseline legacy right',
            $block->setProperty('place-items: baseline legacy left', 'justify-items', 'right legacy')
        );
    },
    'declaration block sets upstream gap cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'gap: 3rem 2rem',
            $block->setProperty('gap: 1rem 2rem', 'row-gap', '3rem')
        );
        $t->same(
            'gap: 1rem',
            $block->setProperty('gap: 1rem 2rem', 'column-gap', '1rem')
        );
        $t->same(
            'gap: 1rem 2rem; row-gap: 3rem',
            $block->setProperty('gap: 1rem 2rem; row-gap: 1rem', 'row-gap', '3rem')
        );
        $t->same(
            'row-gap: 3rem; gap: 1rem !important',
            $block->setProperty('gap: 1rem !important', 'row-gap', '3rem')
        );
    },
    'declaration block sets upstream multi-column cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('columns: 2 20rem', $block->setProperty('columns: 2 16rem', 'column-width', '20rem'));
        $t->same('columns: 3 16rem', $block->setProperty('columns: 2 16rem', 'column-count', '3'));
        $t->same('-webkit-columns: 2 20rem', $block->setProperty('-webkit-columns: 2 16rem', '-webkit-column-width', '20rem'));
        $t->same('columns: 2 16rem; -webkit-column-width: 20rem', $block->setProperty('columns: 2 16rem', '-webkit-column-width', '20rem'));

        $t->same(
            'column-rule: 1px solid var(--wp--preset--color--accent)',
            $block->setProperty('column-rule: 1px solid #ddd', 'column-rule-color', 'var(--wp--preset--color--accent)')
        );
        $t->same('column-rule: 1px dashed #ddd', $block->setProperty('column-rule: 1px solid #ddd', 'column-rule-style', 'dashed'));
        $t->same(
            '-moz-column-rule: 2px dotted #aaa',
            $block->setProperty('-moz-column-rule: dotted 1px #aaa', '-moz-column-rule-width', '2px')
        );
        $t->same(
            'column-rule-width: 2px; column-rule: 1px solid #ddd !important',
            $block->setProperty('column-rule: 1px solid #ddd !important', 'column-rule-width', '2px')
        );
    },
    'declaration block sets upstream overflow cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'overflow: scroll auto',
            $block->setProperty('overflow: hidden auto', 'overflow-x', 'scroll')
        );
        $t->same(
            'overflow: hidden',
            $block->setProperty('overflow: hidden auto', 'overflow-y', 'hidden')
        );
        $t->same(
            'overflow: hidden auto; overflow-y: scroll',
            $block->setProperty('overflow: hidden auto; overflow-y: clip', 'overflow-y', 'scroll')
        );
        $t->same(
            'overflow-x: scroll; overflow: hidden !important',
            $block->setProperty('overflow: hidden !important', 'overflow-x', 'scroll')
        );
    },
    'declaration block sets upstream text emphasis cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('text-emphasis: dot #00f', $block->setProperty('text-emphasis: dot red', 'text-emphasis-color', 'blue'));
        $t->same('text-emphasis: open dot red', $block->setProperty('text-emphasis: dot red', 'text-emphasis-style', 'open dot'));
        $t->same('text-emphasis: none', $block->setProperty('text-emphasis: dot red', 'text-emphasis-style', 'none'));
        $t->same('text-emphasis: dot', $block->setProperty('text-emphasis: dot red', 'text-emphasis-color', 'currentColor'));
        $t->same(
            'text-emphasis-style: open dot; text-emphasis: dot red !important',
            $block->setProperty('text-emphasis: dot red !important', 'text-emphasis-style', 'open dot')
        );
        $t->same('-webkit-text-emphasis: dot #00f', $block->setProperty('-webkit-text-emphasis: dot red', '-webkit-text-emphasis-color', 'blue'));
        $t->same(
            'text-emphasis-style: open circle; text-emphasis-color: red',
            $block->setProperty('text-emphasis-style: dot; text-emphasis-color: red', 'text-emphasis-style', 'open circle')
        );
    },
    'declaration block sets upstream caret cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('caret: #00f block', $block->setProperty('caret: red block', 'caret-color', 'blue'));
        $t->same('caret: red underscore', $block->setProperty('caret: red block', 'caret-shape', 'underscore'));
        $t->same('caret: block', $block->setProperty('caret: red block', 'caret-color', 'auto'));
        $t->same('caret: red', $block->setProperty('caret: red block', 'caret-shape', 'auto'));
        $t->same('caret-color: red; caret-shape: bar', $block->setProperty('caret-color: red; caret-shape: block', 'caret-shape', 'bar'));
        $t->same(
            'caret-shape: underscore; caret: red block !important',
            $block->setProperty('caret: red block !important', 'caret-shape', 'underscore')
        );
    },
    'declaration block sets upstream scroll snap cssom rect longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'scroll-margin: 2rem 1rem 1rem',
            $block->setProperty('scroll-margin: 1rem', 'scroll-margin-top', '2rem')
        );
        $t->same(
            'scroll-padding: 1rem 2rem 1rem 3rem',
            $block->setProperty('scroll-padding: 1rem 2rem', 'scroll-padding-left', '3rem')
        );
        $t->same(
            'scroll-padding: 1rem; scroll-padding-inline-start: 2rem; scroll-padding-left: 3rem',
            $block->setProperty('scroll-padding: 1rem; scroll-padding-inline-start: 2rem', 'scroll-padding-left', '3rem')
        );
        $t->same(
            'scroll-margin-top: 2rem; scroll-margin: 1rem !important',
            $block->setProperty('scroll-margin: 1rem !important', 'scroll-margin-top', '2rem')
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
    'declaration block sets upstream animation cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'animation: 320ms ease-out 80ms both paused core-block-fade',
            $block->setProperty(
                'animation: core-block-fade 240ms ease-out 80ms both paused',
                'animation-duration',
                '320ms'
            )
        );
        $t->same(
            'animation: 200ms 50ms fade, 300ms linear 100ms slide',
            $block->setProperty(
                'animation: fade 200ms, slide 300ms linear',
                'animation-delay',
                '50ms, 100ms'
            )
        );
        $t->same(
            'animation: 200ms fade, 300ms slide; animation-delay: 50ms',
            $block->setProperty('animation: fade 200ms, slide 300ms', 'animation-delay', '50ms')
        );
        $t->same(
            'animation: 240ms wp-block-fade scroll(root)',
            $block->setProperty('animation: wp-block-fade 240ms scroll(block)', 'animation-timeline', 'scroll(root block)')
        );
        $t->same(
            'animation: 240ms ease-out both core-block-fade view(inline auto 20%)',
            $block->setProperty(
                'animation: core-block-fade 240ms ease-out both scroll(nearest block)',
                'animation-timeline',
                'view(inline auto 20%)'
            )
        );
        $t->same(
            'animation-timeline: scroll(inline), view(10%)',
            $block->setProperty('animation-timeline: scroll(root block)', 'animation-timeline', 'scroll(nearest inline), view(block 10% 10%)')
        );
        $t->same(
            'animation-duration: 320ms; animation: wp-block-fade 240ms !important',
            $block->setProperty('animation: wp-block-fade 240ms !important', 'animation-duration', '320ms')
        );
    },
    'declaration block sets upstream prefixed animation cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            '-webkit-animation: 200ms ease-in 50ms fade',
            $block->setProperty('-webkit-animation: fade 200ms ease-in', '-webkit-animation-delay', '50ms')
        );
        $t->same(
            '-o-animation: 200ms slide',
            $block->setProperty('-o-animation: fade 200ms', '-o-animation-name', 'slide')
        );
        $t->same(
            '-moz-animation: 200ms fade, 300ms slide; -moz-animation-duration: 250ms',
            $block->setProperty('-moz-animation: fade 200ms, slide 300ms', '-moz-animation-duration', '250ms')
        );
        $t->same(
            '-webkit-animation-duration: 300ms; -webkit-animation: fade 200ms !important',
            $block->setProperty('-webkit-animation: fade 200ms !important', '-webkit-animation-duration', '300ms')
        );
    },
    'declaration block sets upstream animation range cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'animation-range: entry exit 90%',
            $block->setProperty('animation-range: entry', 'animation-range-end', 'exit 90%')
        );
        $t->same(
            'animation-range: cover 20% exit 90%',
            $block->setProperty('animation-range: entry 10% exit 90%', 'animation-range-start', 'cover 20%')
        );
        $t->same(
            'animation-range: entry',
            $block->setProperty('animation-range: entry exit 90%', 'animation-range-end', 'normal')
        );
        $t->same(
            'animation-range: entry 10% exit 90%, contain 20%',
            $block->setProperty('animation-range: entry exit 90%, contain', 'animation-range-start', 'entry 10%, contain 20%')
        );
        $t->same(
            'animation-range: entry, cover; animation-range-end: exit 90%',
            $block->setProperty('animation-range: entry, cover', 'animation-range-end', 'exit 90%')
        );
        $t->same(
            'animation-range-end: exit 90%; animation-range: entry !important',
            $block->setProperty('animation-range: entry !important', 'animation-range-end', 'exit 90%')
        );
    },
    'declaration block sets upstream grid placement cssom longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'grid-area: masthead-start / content-start / header-end / content-end',
            $block->setProperty(
                'grid-area: header-start / content-start / header-end / content-end',
                'grid-row-start',
                'masthead-start'
            )
        );
        $t->same(
            'grid-area: header-start / content-start / header-end / aside-end',
            $block->setProperty(
                'grid-area: header-start / content-start / header-end / content-end',
                'grid-column-end',
                'aside-end'
            )
        );
        $t->same(
            'grid-row: hero-start / span 2',
            $block->setProperty('grid-row: hero-start / hero-end', 'grid-row-end', 'span 2')
        );
        $t->same(
            'grid-column: 2 / 3',
            $block->setProperty('grid-column: 1 / 3', 'grid-column-start', '2')
        );
        $t->same(
            'grid-row: hero-start / hero-end; grid-column-start: content-start',
            $block->setProperty('grid-row: hero-start / hero-end', 'grid-column-start', 'content-start')
        );
        $t->same(
            'grid-row-start: masthead-start; grid-area: header-start / content-start / header-end / content-end !important',
            $block->setProperty(
                'grid-area: header-start / content-start / header-end / content-end !important',
                'grid-row-start',
                'masthead-start'
            )
        );
    },
    'declaration block sets upstream grid template cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'grid-template: auto / 2fr',
            $block->setProperty('grid-template: auto / 1fr', 'grid-template-columns', '2fr')
        );
        $t->same(
            'grid-template: minmax(0, 1fr) auto / 1fr',
            $block->setProperty('grid-template: auto / 1fr', 'grid-template-rows', 'minmax(0, 1fr) auto')
        );
        $t->same(
            'grid-template: auto / 1fr',
            $block->setProperty('grid-template: auto / 1fr', 'grid-template-areas', 'none')
        );
        $t->same(
            'grid-template: auto / 1fr; grid-template-columns: 2fr',
            $block->setProperty('grid-template: auto / 1fr; grid-template-columns: 3fr', 'grid-template-columns', '2fr')
        );
        $t->same(
            'grid-template-columns: 2fr; grid-template: auto / 1fr !important',
            $block->setProperty('grid-template: auto / 1fr !important', 'grid-template-columns', '2fr')
        );
    },
    'declaration block sets upstream grid auto flow cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'grid: auto-flow dense 12px / minmax(0, 2fr)',
            $block->setProperty('grid: auto-flow dense 12px / 1fr', 'grid-template-columns', 'minmax(0, 2fr)')
        );
        $t->same(
            'grid: auto-flow dense 16px / 1fr',
            $block->setProperty('grid: auto-flow dense 12px / 1fr', 'grid-auto-rows', '16px')
        );
        $t->same(
            'grid: [sidebar] auto / auto-flow dense 8rem',
            $block->setProperty('grid: [sidebar] auto / auto-flow 8rem', 'grid-auto-flow', 'column dense')
        );
        $t->same(
            'grid: [sidebar] auto / auto-flow dense 10rem',
            $block->setProperty('grid: [sidebar] auto / dense auto-flow 8rem', 'grid-auto-columns', '10rem')
        );
        $t->same(
            'grid-auto-flow: row dense; grid: auto-flow / 1fr !important',
            $block->setProperty('grid: auto-flow / 1fr !important', 'grid-auto-flow', 'dense')
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
        $t->same(
            '-ms-transition: opacity 300ms ease-in 50ms',
            $block->setProperty('-ms-transition: opacity 200ms ease-in 50ms', '-ms-transition-duration', '300ms')
        );
        $t->same(
            '-ms-transition: opacity 200ms ease-in 75ms',
            $block->setProperty('-ms-transition: opacity 200ms ease-in 50ms', '-ms-transition-delay', '75ms')
        );
        $t->same(
            '-ms-transition: opacity 200ms; -moz-transition-duration: 300ms',
            $block->setProperty('-ms-transition: opacity 200ms', '-moz-transition-duration', '300ms')
        );
    },
    'declaration block sets upstream list style cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'list-style: inside decimal',
            $block->setProperty('list-style: inside', 'list-style-type', 'decimal')
        );
        $t->same(
            'list-style: square',
            $block->setProperty('list-style: url(marker.svg) square', 'list-style-image', 'none')
        );
        $t->same(
            'list-style: url(new-marker.svg) square',
            $block->setProperty('list-style: url(marker.svg) square', 'list-style-image', 'url(new-marker.svg)')
        );
        $t->same(
            'list-style: square; list-style-position: inside !important',
            $block->setProperty('list-style: square', 'list-style-position', 'inside', true)
        );
    },
    'declaration block sets upstream text decoration cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'text-decoration: underline 2px wavy #00f',
            $block->setProperty('text-decoration: underline wavy red 2px', 'text-decoration-color', 'blue')
        );
        $t->same(
            'text-decoration: underline overline wavy red',
            $block->setProperty('text-decoration: underline wavy red', 'text-decoration-line', 'overline underline')
        );
        $t->same(
            'text-decoration: underline from-font wavy red',
            $block->setProperty('text-decoration: underline wavy red', 'text-decoration-thickness', 'from-font')
        );
        $t->same(
            'text-decoration: underline red',
            $block->setProperty('text-decoration: underline wavy red', 'text-decoration-style', 'solid')
        );
        $t->same(
            'text-decoration-color: #00f; text-decoration: underline wavy red !important',
            $block->setProperty('text-decoration: underline wavy red !important', 'text-decoration-color', 'blue')
        );
    },
    'declaration block sets upstream prefixed text decoration cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            '-webkit-text-decoration: underline 2px wavy #00f',
            $block->setProperty('-webkit-text-decoration: underline wavy red 2px', '-webkit-text-decoration-color', 'blue')
        );
        $t->same(
            '-webkit-text-decoration: underline overline wavy red',
            $block->setProperty('-webkit-text-decoration: underline wavy red', '-webkit-text-decoration-line', 'overline underline')
        );
        $t->same(
            '-moz-text-decoration: line-through red',
            $block->setProperty('-moz-text-decoration: line-through dotted red', '-moz-text-decoration-style', 'solid')
        );
        $t->same(
            'text-decoration: underline red; -webkit-text-decoration-color: #00f',
            $block->setProperty('text-decoration: underline red', '-webkit-text-decoration-color', 'blue')
        );
        $t->same(
            '-webkit-text-decoration: underline wavy red; text-decoration-thickness: from-font',
            $block->setProperty('-webkit-text-decoration: underline wavy red', 'text-decoration-thickness', 'from-font')
        );
    },
    'declaration block sets upstream font cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'font: italic 700 16px/1.5 Inter, sans-serif',
            $block->setProperty('font: italic 600 16px/1.5 "Inter", sans-serif', 'font-weight', '700')
        );
        $t->same(
            'font: italic 600 16px Inter, sans-serif',
            $block->setProperty('font: italic 600 16px/1.5 Inter, sans-serif', 'line-height', 'normal')
        );
        $t->same(
            'font: italic 600 16px/1.5 Inter var, system-ui',
            $block->setProperty('font: italic 600 16px/1.5 Inter, sans-serif', 'font-family', '"Inter var", system-ui')
        );
        $t->same(
            'font: italic 600 expanded 16px/1.5 Inter, sans-serif',
            $block->setProperty('font: italic 600 16px/1.5 Inter, sans-serif', 'font-stretch', 'expanded')
        );
        $t->same(
            'font: oblique 40deg 600 16px/1.5 Inter, sans-serif',
            $block->setProperty('font: italic 600 16px/1.5 Inter, sans-serif', 'font-style', 'Oblique 40.000deg')
        );
        $t->same(
            'font: oblique 600 16px Inter; color: red',
            $block->setProperty('font: italic 600 16px Inter; color: red', 'font-style', 'oblique +014.000deg')
        );
        $t->same(
            'color: red; font: oblique 600 16px Inter',
            $block->setProperty('color: red', 'font', 'oblique +014.000deg 600 16px Inter')
        );
        $t->same(
            'font-family: Inter; font: italic 600 16px Inter !important',
            $block->setProperty('font: italic 600 16px Inter !important', 'font-family', 'Inter')
        );
    },
    'declaration block sets upstream container cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'container: wp-query-card / size; color: red',
            $block->setProperty('container: wp-query-card / inline-size; color: red', 'container-type', 'size')
        );
        $t->same(
            'container: wp-query-card',
            $block->setProperty('container: wp-query-card / inline-size', 'container-type', 'normal')
        );
        $t->same(
            'container: wp-query-card is-wide / inline-size',
            $block->setProperty('container: wp-query-card / inline-size', 'container-name', 'wp-query-card is-wide')
        );
        $t->same(
            'container-name: wp-query-card; container-type: size',
            $block->setProperty('container-name: wp-query-card; container-type: inline-size', 'container-type', 'size')
        );
        $t->same(
            'container-type: size; container: wp-query-card / inline-size !important',
            $block->setProperty('container: wp-query-card / inline-size !important', 'container-type', 'size')
        );
    },
    'declaration block sets upstream mask border cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'mask-border: url(new-frame.svg) 25 / 12px / 2 round luminance',
            $block->setProperty('mask-border: url(old-frame.svg) 25 / 12px / 2 round luminance', 'mask-border-source', 'url("new-frame.svg")')
        );
        $t->same(
            'mask-border: url(frame.svg) 20 40 fill / 12px round',
            $block->setProperty('mask-border: url(frame.svg) 10 / 12px round', 'mask-border-slice', '20 40 20 40 fill')
        );
        $t->same(
            'mask-border: url(frame.svg) 25 luminance',
            $block->setProperty('mask-border: url(frame.svg) 25', 'mask-border-mode', 'luminance')
        );
        $t->same(
            'mask-border-source: url(new-frame.svg); mask-border: url(old-frame.svg) 25 !important',
            $block->setProperty('mask-border: url(old-frame.svg) 25 !important', 'mask-border-source', 'url(new-frame.svg)')
        );
    },
    'declaration block sets upstream mask cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $mask = 'mask: url(mask.svg) 25% 75% / cover no-repeat content-box padding-box subtract luminance';

        $t->same(
            'mask: url(new-mask.svg) 25% 75% / cover no-repeat content-box padding-box subtract luminance',
            $block->setProperty($mask, 'mask-image', 'url("new-mask.svg")')
        );
        $t->same(
            'mask: url(mask.svg) 25% 75% no-repeat content-box padding-box subtract luminance',
            $block->setProperty($mask, 'mask-size', 'auto')
        );
        $t->same(
            'mask: url(mask.svg) border-box padding-box',
            $block->setProperty('mask: url(mask.svg)', 'mask-clip', 'padding-box')
        );
        $t->same(
            'mask: url(mask.svg) 10px 75% / cover no-repeat content-box padding-box subtract luminance',
            $block->setProperty($mask, 'mask-position-x', '10px')
        );
        $t->same(
            'mask: url(mask.svg) 25% bottom / cover no-repeat content-box padding-box subtract luminance',
            $block->setProperty($mask, 'mask-position-y', 'bottom')
        );
        $t->same(
            'mask-position: left 10px',
            $block->setProperty('mask-position: 20px 10px', 'mask-position-x', 'left')
        );
        $t->same(
            'mask: url(a.svg) 15px 20px / cover, url(b.svg) right 50% / contain',
            $block->setProperty(
                'mask: url(a.svg) 10px 20px / cover, url(b.svg) 50% 50% / contain',
                'mask-position-x',
                '15px, right'
            )
        );
        $t->same(
            'mask: url(a.svg), url(b.svg); mask-repeat: no-repeat',
            $block->setProperty('mask: url(a.svg), url(b.svg)', 'mask-repeat', 'no-repeat')
        );
        $t->same(
            'mask-image: url(new-mask.svg); mask: url(mask.svg) !important',
            $block->setProperty('mask: url(mask.svg) !important', 'mask-image', 'url(new-mask.svg)')
        );
        $t->same(
            'mask: url(a.svg) 10px 20px / 50% 25% no-repeat, url(b.svg) 50% 50% / cover repeat-x',
            $block->setProperty(
                'mask: url(a.svg) 10px 20px / 50% 25% no-repeat, url(b.svg) 50% 50% / contain repeat-x',
                'mask-size',
                '50% 25%, cover'
            )
        );
        $webkitMask = '-webkit-mask: url(mask.svg) 10px 20px / contain no-repeat content-box padding-box';
        $t->same(
            '-webkit-mask: url(new-mask.svg) 10px 20px / contain no-repeat content-box padding-box',
            $block->setProperty($webkitMask, '-webkit-mask-image', 'url("new-mask.svg")')
        );
        $t->same(
            '-webkit-mask: url(mask.svg) 10px 20px / contain repeat-x content-box padding-box',
            $block->setProperty($webkitMask, '-webkit-mask-repeat', 'repeat-x')
        );
        $t->same(
            '-webkit-mask: url(a.svg), url(b.svg); -webkit-mask-size: cover',
            $block->setProperty('-webkit-mask: url(a.svg), url(b.svg)', '-webkit-mask-size', 'cover')
        );
        $t->same(
            '-webkit-mask-image: url(new-mask.svg); -webkit-mask: url(mask.svg) !important',
            $block->setProperty('-webkit-mask: url(mask.svg) !important', '-webkit-mask-image', 'url(new-mask.svg)')
        );
        $t->same(
            '-webkit-mask: url(mask.svg); mask-repeat: no-repeat',
            $block->setProperty('-webkit-mask: url(mask.svg)', 'mask-repeat', 'no-repeat')
        );
    },
    'declaration block sets upstream border image cssom longhands in existing shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-image: url(new-frame.svg) 25 / 12px / 2 round',
            $block->setProperty('border-image: url(old-frame.svg) 25 / 12px / 2 round', 'border-image-source', 'url("new-frame.svg")')
        );
        $t->same(
            'border-image: url(frame.svg) 20 40 fill / 12px round',
            $block->setProperty('border-image: url(frame.svg) 10 / 12px round', 'border-image-slice', '20 40 20 40 fill')
        );
        $t->same(
            'border-image: url(frame.svg) 25 / 12px space round',
            $block->setProperty('border-image: url(frame.svg) 25 / 12px round', 'border-image-repeat', 'space round')
        );
        $t->same(
            '-webkit-border-image: url(new-frame.svg) 25 / 12px round',
            $block->setProperty('-webkit-border-image: url(frame.svg) 25 / 12px round', 'border-image-source', 'url("new-frame.svg")')
        );
        $t->same(
            '-moz-border-image: url(frame.svg) 25 / 12px space round !important',
            $block->setProperty('-moz-border-image: url(frame.svg) 25 / 12px round !important', 'border-image-repeat', 'space round', true)
        );
        $t->same(
            'border-image-source: url(new-frame.svg); border-image: url(old-frame.svg) 25 !important',
            $block->setProperty('border-image: url(old-frame.svg) 25 !important', 'border-image-source', 'url(new-frame.svg)')
        );
    },
    'declaration block sets upstream outline cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'outline: 2px solid #00f',
            $block->setProperty('outline: 2px solid red', 'outline-color', 'blue')
        );
        $t->same(
            'outline: 2px auto var(--wp--preset--color--accent)',
            $block->setProperty('outline: 2px solid var(--wp--preset--color--accent)', 'outline-style', 'auto')
        );
        $t->same(
            'outline: 4px',
            $block->setProperty('outline: none', 'outline-width', '4px')
        );
        $t->same(
            'outline-color: green',
            $block->setProperty('outline-color: red', 'outline-color', 'green')
        );
        $t->same(
            'outline-color: #00f; outline: 2px solid red !important',
            $block->setProperty('outline: 2px solid red !important', 'outline-color', 'blue')
        );
    },
    'declaration block sets upstream border radius cssom longhands in existing shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-radius: 20px 10px 10px / 30px 10px 10px',
            $block->setProperty('border-radius: 10px', 'border-top-left-radius', '20px 30px')
        );
        $t->same(
            'border-radius: 8px 16px 8px 20px / 4px 12px 4px 20px',
            $block->setProperty('border-radius: 8px 16px / 4px 12px', 'border-bottom-left-radius', '20px')
        );
        $t->same(
            '-webkit-border-radius: 8px 12px 8px 8px',
            $block->setProperty('-webkit-border-radius: 8px', '-webkit-border-top-right-radius', '12px')
        );
        $t->same(
            'border-top-left-radius: 12px; border-radius: 8px !important',
            $block->setProperty('border-radius: 8px !important', 'border-top-left-radius', '12px')
        );
        $t->same(
            'border-radius: 8px; border-start-start-radius: 12px; border-top-left-radius: 16px',
            $block->setProperty('border-radius: 8px; border-start-start-radius: 12px', 'border-top-left-radius', '16px')
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
    'declaration block removes upstream inset cssom rect shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'right: 2px; bottom: 1px; left: 2px',
            $block->removeProperty('inset: 1px 2px', 'top')
        );
        $t->same(
            'top: 1px; bottom: 3px; left: 4px',
            $block->removeProperty('inset: 1px 2px 3px 4px', 'right')
        );
        $t->same(
            'inset-inline-start: 4px',
            $block->removeProperty('top: 0; right: 1px; bottom: 0; left: 1px; inset-inline-start: 4px', 'inset')
        );
        $t->same(
            'color: red; right: 1px !important; bottom: 0 !important; left: 1px !important',
            $block->removeProperty('inset: 0 1px !important; top: 2px; color: red', 'top')
        );
    },
    'declaration block removes upstream background cssom shorthand and supported longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'color: red; color: #00f',
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
        $t->same(
            'padding: 1rem',
            $block->removeProperty(
                'background: url(hero.jpg) fixed content-box text; background-attachment: local; background-origin: border-box; background-clip: text; padding: 1rem',
                'background'
            )
        );
    },
    'declaration block removes upstream background position cssom longhands by splitting shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'background-position-y: 10px',
            $block->removeProperty('background-position: 20px 10px', 'background-position-x')
        );
        $t->same(
            'background-position-x: 20px',
            $block->removeProperty('background-position: 20px 10px', 'background-position-y')
        );
        $t->same(
            'color: red',
            $block->removeProperty('background-position: 20px 10px; background-position-x: 30px; color: red', 'background-position')
        );
        $t->same(
            'color: red; background-position-y: 10px !important',
            $block->removeProperty('background-position: 20px 10px !important; color: red; background-position-x: 30px', 'background-position-x')
        );
    },
    'declaration block removes upstream background shorthand-derived longhands by splitting shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $fullBackground = 'background: red url(hero.jpg) 20px 10px no-repeat fixed border-box content-box; color: blue';

        $t->same(
            'background-image: url(hero.jpg); background-position-x: 20px; background-position-y: 10px; background-repeat: no-repeat; background-size: auto; background-attachment: fixed; background-origin: border-box; background-clip: content-box; color: #00f',
            $block->removeProperty($fullBackground, 'background-color')
        );
        $t->same(
            'background-color: red; background-position-x: 20px; background-position-y: 10px; background-repeat: no-repeat; background-size: auto; background-attachment: fixed; background-origin: border-box; background-clip: content-box; color: #00f',
            $block->removeProperty($fullBackground, 'background-image')
        );
        $t->same(
            'background-color: red; background-image: url(hero.jpg); background-position-y: 10px; background-repeat: no-repeat; background-size: auto; background-attachment: fixed; background-origin: border-box; background-clip: content-box; color: #00f',
            $block->removeProperty($fullBackground, 'background-position-x')
        );
        $t->same(
            'background-color: #0000; background-image: url(hero.jpg); background-position-x: 20px; background-position-y: 10px; background-repeat: repeat; background-attachment: scroll; background-origin: padding-box; background-clip: border-box; color: #00f',
            $block->removeProperty('background: url(hero.jpg) 20px 10px / cover; color: blue', 'background-size')
        );
    },
    'declaration block removes upstream border longhands by splitting shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px; border-top-style: solid; border-right-style: solid; border-bottom-style: solid; border-left-style: solid; border-top-color: red; border-bottom-color: red; border-left-color: red',
            $block->removeProperty('border: 1px solid red', 'border-right-color')
        );
        $t->same(
            'border-right-color: green; border-bottom-color: #00f; border-left-color: #000',
            $block->removeProperty('border-color: red green blue black', 'border-top-color')
        );
        $t->same(
            'border-top-width: 2px; border-top-style: dotted',
            $block->removeProperty('border-top: 2px dotted blue; border-top-color: green', 'border-top-color')
        );
        $t->same(
            'color: #00f; border-top-width: 1px !important; border-right-width: 1px !important; border-bottom-width: 1px !important; border-top-style: solid !important; border-right-style: solid !important; border-bottom-style: solid !important; border-left-style: solid !important; border-top-color: red !important; border-right-color: red !important; border-bottom-color: red !important; border-left-color: red !important',
            $block->removeProperty('border: 1px solid red !important; border-left-width: 4px; color: blue', 'border-left-width')
        );
    },
    'declaration block removes upstream logical border longhands by splitting shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-inline-start-width: 1px; border-inline-end-width: 1px; border-inline-start-style: solid; border-inline-end-style: solid; border-inline-end-color: red',
            $block->removeProperty('border-inline: 1px solid red', 'border-inline-start-color')
        );
        $t->same(
            'border-block-start-width: 2px',
            $block->removeProperty('border-block-width: 2px 4px', 'border-block-end-width')
        );
        $t->same(
            'border-block-start-width: 2px; border-block-start-color: red',
            $block->removeProperty('border-block-start: 2px solid red', 'border-block-start-style')
        );
        $t->same(
            'color: red',
            $block->removeProperty(
                'border-block-color: red green; border-block-start-color: blue; border-block-end-color: orange; color: red',
                'border-block-color'
            )
        );
        $t->same(
            'color: red',
            $block->removeProperty('border-block: 1px solid red; border-block-start-color: blue; color: red', 'border-block')
        );
        $t->same(
            'color: red; border-inline-start-width: 1px !important; border-inline-end-width: 1px !important; border-inline-start-style: solid !important; border-inline-end-style: solid !important; border-inline-end-color: red !important',
            $block->removeProperty('border-inline: 1px solid red !important; color: red; border-inline-start-color: blue', 'border-inline-start-color')
        );
    },
    'declaration block removes upstream shorthand groups and included longhands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'color: green',
            $block->removeProperty(
                'border: 1px solid red; border-top-color: blue; color: green; border-left-width: 4px',
                'border'
            )
        );
        $t->same(
            'color: green',
            $block->removeProperty(
                'border: 1px solid red !important; border-top-color: blue !important; color: green; border-left-width: 4px !important',
                'border'
            )
        );
        $t->same(
            'border-left-style: dotted; color: green',
            $block->removeProperty(
                'border-color: red green blue black; border-top-color: orange; border-left-style: dotted; color: green',
                'border-color'
            )
        );
        $t->same(
            'border-right-width: 3px',
            $block->removeProperty(
                'border-top: 2px dotted blue; border-top-color: green; border-right-width: 3px',
                'border-top'
            )
        );
        $t->same(
            'color: red',
            $block->removeProperty('flex-flow: column wrap; flex-direction: row; flex-wrap: nowrap; color: red', 'flex-flow')
        );
        $t->same(
            'flex-direction: row; color: red',
            $block->removeProperty(
                '-webkit-flex-flow: column wrap; flex-direction: row; -webkit-flex-wrap: nowrap; -webkit-flex-direction: column; color: red',
                '-webkit-flex-flow'
            )
        );
        $t->same(
            'color: #00f',
            $block->removeProperty(
                'grid-area: header / main / footer / aside; grid-row-start: promo; grid-column-end: rail; color: blue',
                'grid-area'
            )
        );
        $t->same(
            'grid-auto-flow: row dense; color: #00f',
            $block->removeProperty(
                'grid-template: auto 1fr / auto; grid-template-rows: min-content; grid-template-columns: 1fr; grid-auto-flow: dense; color: blue',
                'grid-template'
            )
        );
        $t->same(
            'color: #00f',
            $block->removeProperty(
                'grid: auto 1fr / auto; grid-template-rows: min-content; grid-template-columns: 1fr; grid-auto-flow: dense; grid-auto-rows: 12px; color: blue',
                'grid'
            )
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
        $t->same('-ms-flex-wrap: wrap', $block->removeProperty('-ms-flex-flow: column wrap', '-ms-flex-direction'));
        $t->same('-ms-flex-flow: column wrap', $block->removeProperty('-ms-flex-flow: column wrap', '-webkit-flex-direction'));
    },
    'declaration block removes upstream flex cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('flex-shrink: 0; flex-basis: 10px', $block->removeProperty('flex: 2 0 10px', 'flex-grow'));
        $t->same('flex-grow: 1; flex-shrink: 1', $block->removeProperty('flex: auto', 'flex-basis'));
        $t->same('color: red', $block->removeProperty('flex: 1; flex-grow: 2; flex-basis: auto; color: red', 'flex'));
        $t->same(
            '-webkit-flex-grow: 2; -webkit-flex-shrink: 1',
            $block->removeProperty('-webkit-flex: 2 10px', '-webkit-flex-basis')
        );
        $t->same(
            'color: red; flex-shrink: 0 !important; flex-basis: 10px !important',
            $block->removeProperty('flex: 2 0 10px !important; flex-grow: 1; color: red', 'flex-grow')
        );
    },
    'declaration block removes upstream place alignment cssom longhands and shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'justify-content: space-between',
            $block->removeProperty('place-content: center space-between', 'align-content')
        );
        $t->same(
            'align-content: baseline',
            $block->removeProperty('place-content: first baseline', 'justify-content')
        );
        $t->same(
            'align-self: auto',
            $block->removeProperty('place-self: auto end', 'justify-self')
        );
        $t->same(
            'align-items: normal',
            $block->removeProperty('place-items: normal stretch', 'justify-items')
        );
        $t->same(
            'color: red',
            $block->removeProperty('align-items: center; place-items: end center; justify-items: legacy left; color: red', 'place-items')
        );
        $t->same(
            'color: red; justify-items: stretch !important',
            $block->removeProperty('place-items: center stretch !important; align-items: end; color: red', 'align-items')
        );
    },
    'declaration block removes upstream gap cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'column-gap: 2rem',
            $block->removeProperty('gap: 1rem 2rem', 'row-gap')
        );
        $t->same(
            'row-gap: 1rem',
            $block->removeProperty('gap: 1rem 2rem', 'column-gap')
        );
        $t->same(
            'color: red',
            $block->removeProperty('gap: 1rem 2rem; row-gap: 3rem; color: red', 'gap')
        );
        $t->same(
            'column-gap: 1rem !important',
            $block->removeProperty('gap: 1rem !important; row-gap: 3rem', 'row-gap')
        );
    },
    'declaration block removes upstream multi-column cssom longhands and shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('column-count: 2; color: red', $block->removeProperty('columns: 2 16rem; color: red', 'column-width'));
        $t->same('column-width: 16rem', $block->removeProperty('columns: 2 16rem', 'column-count'));
        $t->same('color: red', $block->removeProperty('columns: 2 16rem; column-count: 3; color: red', 'columns'));
        $t->same('-moz-column-width: 16rem', $block->removeProperty('-moz-columns: 2 16rem', '-moz-column-count'));

        $t->same(
            'column-rule-width: 1px; column-rule-style: solid; color: red',
            $block->removeProperty('column-rule: 1px solid #ddd; color: red', 'column-rule-color')
        );
        $t->same(
            'column-rule-style: solid; column-rule-color: #ddd',
            $block->removeProperty('column-rule: 1px solid #ddd', 'column-rule-width')
        );
        $t->same('color: red', $block->removeProperty('column-rule: 1px solid #ddd; column-rule-color: red; color: red', 'column-rule'));
        $t->same(
            '-webkit-column-rule-width: 1px; -webkit-column-rule-color: #ddd',
            $block->removeProperty('-webkit-column-rule: 1px solid #ddd', '-webkit-column-rule-style')
        );
    },
    'declaration block removes upstream overflow cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'overflow-y: auto',
            $block->removeProperty('overflow: hidden auto', 'overflow-x')
        );
        $t->same(
            'overflow-x: hidden',
            $block->removeProperty('overflow: hidden auto', 'overflow-y')
        );
        $t->same(
            'color: red',
            $block->removeProperty('overflow: hidden auto; overflow-x: scroll; color: red', 'overflow')
        );
        $t->same(
            'color: red; overflow-y: hidden !important',
            $block->removeProperty('overflow: hidden !important; overflow-x: visible; color: red', 'overflow-x')
        );
    },
    'declaration block removes upstream scroll snap cssom rect longhands and shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'scroll-margin-right: 2rem; scroll-margin-bottom: 3rem; scroll-margin-left: 4rem',
            $block->removeProperty('scroll-margin: 1rem 2rem 3rem 4rem', 'scroll-margin-top')
        );
        $t->same(
            'scroll-padding-top: 1rem; scroll-padding-bottom: 3rem; scroll-padding-left: 4rem',
            $block->removeProperty('scroll-padding: 1rem 2rem 3rem 4rem', 'scroll-padding-right')
        );
        $t->same(
            'color: red',
            $block->removeProperty(
                'scroll-margin: 1rem; scroll-margin-top: 2rem; scroll-margin-left: 3rem; color: red',
                'scroll-margin'
            )
        );
        $t->same(
            'color: red; scroll-padding-right: 1rem !important; scroll-padding-bottom: 1rem !important; scroll-padding-left: 1rem !important',
            $block->removeProperty('scroll-padding: 1rem !important; scroll-padding-top: 2rem; color: red', 'scroll-padding-top')
        );
    },
    'declaration block removes upstream logical axis cssom longhands and shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'margin-inline-end: 2rem',
            $block->removeProperty('margin-inline: 1rem 2rem', 'margin-inline-start')
        );
        $t->same(
            'padding-block-start: 1rem',
            $block->removeProperty('padding-block: 1rem', 'padding-block-end')
        );
        $t->same(
            'margin: 1rem; color: red',
            $block->removeProperty('margin: 1rem; margin-inline-start: 2rem; margin-inline-end: 3rem; color: red', 'margin-inline')
        );
        $t->same(
            'scroll-padding-inline-start: 8px',
            $block->removeProperty('scroll-padding-inline: 8px 16px', 'scroll-padding-inline-end')
        );
        $t->same(
            'color: red; inset-block-end: 2px !important',
            $block->removeProperty('inset-block: 2px !important; inset-block-start: 4px; color: red', 'inset-block-start')
        );
    },
    'declaration block removes upstream animation cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'animation-name: fade; animation-timing-function: ease-out; animation-iteration-count: 1; animation-direction: normal; animation-play-state: paused; animation-delay: 80ms; animation-fill-mode: both; animation-timeline: auto; color: red',
            $block->removeProperty('animation: fade 200ms ease-out 80ms both paused; color: red', 'animation-duration')
        );
        $t->same(
            'color: red',
            $block->removeProperty('animation: fade 200ms; animation-duration: 300ms; color: red', 'animation')
        );
        $t->same(
            'animation-name: fade, slide; animation-duration: 200ms, 300ms; animation-timing-function: ease, linear; animation-iteration-count: 1, 1; animation-direction: normal, normal; animation-play-state: running, running; animation-delay: 0s, 50ms; animation-timeline: auto, auto',
            $block->removeProperty('animation: fade 200ms both, slide 300ms linear 50ms forwards', 'animation-fill-mode')
        );
    },
    'declaration block removes upstream prefixed animation cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            '-webkit-animation-name: fade; -webkit-animation-timing-function: ease-in; -webkit-animation-iteration-count: 1; -webkit-animation-direction: normal; -webkit-animation-play-state: paused; -webkit-animation-delay: 50ms; -webkit-animation-fill-mode: both; color: red',
            $block->removeProperty(
                '-webkit-animation: fade 200ms ease-in 50ms both paused; color: red',
                '-webkit-animation-duration'
            )
        );
        $t->same(
            'animation: slide 1s; color: red',
            $block->removeProperty(
                '-webkit-animation: fade 200ms; -webkit-animation-duration: 300ms; animation: slide 1s; color: red',
                '-webkit-animation'
            )
        );
        $t->same(
            '-webkit-animation: fade 200ms; color: red',
            $block->removeProperty('-webkit-animation: fade 200ms; animation: slide 1s; color: red', 'animation')
        );
        $t->same(
            'color: red; -moz-animation-name: fade !important; -moz-animation-duration: 200ms !important; -moz-animation-timing-function: ease !important; -moz-animation-iteration-count: 1 !important; -moz-animation-direction: normal !important; -moz-animation-play-state: running !important; -moz-animation-fill-mode: none !important',
            $block->removeProperty(
                '-moz-animation: fade 200ms !important; color: red; -moz-animation-delay: 50ms',
                '-moz-animation-delay'
            )
        );
    },
    'declaration block removes upstream animation range cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'animation-range-start: entry 10%',
            $block->removeProperty('animation-range: entry 10% exit 90%', 'animation-range-end')
        );
        $t->same(
            'animation-range-end: exit 90%',
            $block->removeProperty('animation-range: entry 10% exit 90%', 'animation-range-start')
        );
        $t->same(
            'animation-range-end: exit 90%, contain',
            $block->removeProperty('animation-range: entry 10% exit 90%, contain', 'animation-range-start')
        );
        $t->same(
            'color: red',
            $block->removeProperty(
                'animation-range: entry exit 90%; animation-range-start: entry; animation-range-end: cover; color: red',
                'animation-range'
            )
        );
        $t->same(
            'color: red; animation-range-end: exit 90% !important',
            $block->removeProperty('animation-range: entry exit 90% !important; color: red; animation-range-start: entry', 'animation-range-start')
        );
    },
    'declaration block removes upstream grid placement cssom longhands and shorthands' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'grid-column-start: content-start; grid-row-end: header-end; grid-column-end: content-end',
            $block->removeProperty(
                'grid-area: header-start / content-start / header-end / content-end',
                'grid-row-start'
            )
        );
        $t->same(
            'color: red; grid-row-start: header-start !important; grid-column-start: content-start !important; grid-row-end: header-end !important',
            $block->removeProperty(
                'grid-area: header-start / content-start / header-end / content-end !important; color: red',
                'grid-column-end'
            )
        );
        $t->same(
            'grid-row-start: hero-start',
            $block->removeProperty('grid-row: hero-start / hero-end', 'grid-row-end')
        );
        $t->same(
            'grid-column-end: 3',
            $block->removeProperty('grid-column: 2 / 3', 'grid-column-start')
        );
        $t->same(
            'color: red',
            $block->removeProperty(
                'grid-row-start: header-start; grid-column-start: content-start; grid-row-end: header-end; grid-column-end: content-end; color: red',
                'grid-area'
            )
        );
        $t->same(
            'grid-column: content-start / content-end',
            $block->removeProperty(
                'grid-row: header-start / header-end; grid-row-start: masthead-start; grid-column: content-start / content-end',
                'grid-row'
            )
        );
    },
    'declaration block removes upstream grid template cssom longhands by splitting shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'grid-template-columns: 1fr; grid-template-areas: none',
            $block->removeProperty('grid-template: auto / 1fr', 'grid-template-rows')
        );
        $t->same(
            'grid-template-rows: auto; grid-template-areas: none',
            $block->removeProperty('grid-template: auto / 1fr', 'grid-template-columns')
        );
        $t->same(
            'grid-template-rows: auto; grid-template-columns: 1fr',
            $block->removeProperty('grid-template: auto / 1fr', 'grid-template-areas')
        );
        $t->same(
            'grid-template-columns: none; grid-template-areas: none',
            $block->removeProperty('grid-template: none', 'grid-template-rows')
        );
        $t->same(
            'color: red; grid-template-columns: 1fr !important; grid-template-areas: none !important',
            $block->removeProperty('grid-template: auto / 1fr !important; color: red; grid-template-rows: minmax(0, 1fr)', 'grid-template-rows')
        );
    },
    'declaration block removes upstream grid auto flow cssom longhands by splitting shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'grid-template-rows: none; grid-template-columns: 1fr; grid-template-areas: none; grid-auto-rows: 12px; grid-auto-columns: auto',
            $block->removeProperty('grid: auto-flow dense 12px / 1fr', 'grid-auto-flow')
        );
        $t->same(
            'grid-template-rows: none; grid-template-areas: none; grid-auto-rows: 12px; grid-auto-columns: auto; grid-auto-flow: row dense',
            $block->removeProperty('grid: auto-flow dense 12px / 1fr', 'grid-template-columns')
        );
        $t->same(
            'grid-template-rows: [sidebar] auto; grid-template-columns: none; grid-template-areas: none; grid-auto-rows: auto; grid-auto-flow: column dense',
            $block->removeProperty('grid: [sidebar] auto / dense auto-flow 8rem', 'grid-auto-columns')
        );
        $t->same(
            'color: red; grid-template-rows: none !important; grid-template-columns: 1fr !important; grid-template-areas: none !important; grid-auto-columns: auto !important; grid-auto-flow: row dense !important',
            $block->removeProperty('grid: auto-flow dense 12px / 1fr !important; color: red; grid-auto-rows: 16px', 'grid-auto-rows')
        );
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
        $t->same(
            '-ms-transition-property: opacity; -ms-transition-delay: 50ms; -ms-transition-timing-function: ease-in',
            $block->removeProperty('-ms-transition: opacity 200ms ease-in 50ms', '-ms-transition-duration')
        );
        $t->same(
            'color: red',
            $block->removeProperty('-ms-transition: opacity 200ms; -ms-transition-duration: 300ms; color: red', '-ms-transition')
        );
    },
    'declaration block removes upstream list style cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'list-style-position: inside; list-style-type: square',
            $block->removeProperty('list-style: inside url(marker.svg) square', 'list-style-image')
        );
        $t->same(
            'list-style-image: url(marker.svg); list-style-type: square',
            $block->removeProperty('list-style: inside url(marker.svg) square', 'list-style-position')
        );
        $t->same(
            'color: red',
            $block->removeProperty(
                'color: red; list-style: inside square; list-style-image: url(marker.svg); list-style-position: outside',
                'list-style'
            )
        );
        $t->same(
            'color: red; list-style-position: inside !important; list-style-type: square !important',
            $block->removeProperty('list-style: inside url(marker.svg) square !important; color: red', 'list-style-image')
        );
    },
    'declaration block removes upstream text decoration cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'text-decoration-line: underline; text-decoration-thickness: 2px; text-decoration-style: wavy',
            $block->removeProperty('text-decoration: underline wavy red 2px', 'text-decoration-color')
        );
        $t->same(
            'text-decoration-line: underline; text-decoration-thickness: auto; text-decoration-color: red',
            $block->removeProperty('text-decoration: underline solid red', 'text-decoration-style')
        );
        $t->same(
            'color: red',
            $block->removeProperty('text-decoration: underline wavy red; text-decoration-color: blue; color: red', 'text-decoration')
        );
        $t->same(
            'color: red; text-decoration-thickness: 2px !important; text-decoration-style: wavy !important; text-decoration-color: red !important',
            $block->removeProperty('text-decoration: underline wavy red 2px !important; color: red; text-decoration-line: overline', 'text-decoration-line')
        );
    },
    'declaration block removes upstream prefixed text decoration cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            '-webkit-text-decoration-line: underline; -webkit-text-decoration-style: wavy',
            $block->removeProperty('-webkit-text-decoration: underline wavy red 2px', '-webkit-text-decoration-color')
        );
        $t->same(
            '-moz-text-decoration-line: line-through; -moz-text-decoration-color: #00f',
            $block->removeProperty('-moz-text-decoration: line-through dotted blue', '-moz-text-decoration-style')
        );
        $t->same(
            'color: red',
            $block->removeProperty('-webkit-text-decoration: underline wavy red; -webkit-text-decoration-color: blue; color: red', '-webkit-text-decoration')
        );
        $t->same(
            'color: red',
            $block->removeProperty('-webkit-text-decoration: underline wavy red; text-decoration-thickness: 3px; color: red', '-webkit-text-decoration')
        );
        $t->same(
            'color: red; -webkit-text-decoration-line: underline !important; -webkit-text-decoration-style: wavy !important',
            $block->removeProperty('-webkit-text-decoration: underline wavy red !important; color: red; -webkit-text-decoration-color: blue', '-webkit-text-decoration-color')
        );
        $t->same(
            '-webkit-text-decoration-line: underline; -webkit-text-decoration-style: wavy; -webkit-text-decoration-color: red',
            $block->removeProperty('-webkit-text-decoration: underline wavy red 2px', 'text-decoration-thickness')
        );
    },
    'declaration block removes upstream text emphasis cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('text-emphasis-color: red', $block->removeProperty('text-emphasis: open dot red', 'text-emphasis-style'));
        $t->same('text-emphasis-style: open dot', $block->removeProperty('text-emphasis: open dot red', 'text-emphasis-color'));
        $t->same(
            'text-emphasis-position: over left; color: green',
            $block->removeProperty(
                'text-emphasis: dot red; text-emphasis-style: open circle; text-emphasis-color: blue; text-emphasis-position: over left; color: green',
                'text-emphasis'
            )
        );
        $t->same(
            '-webkit-text-emphasis-color: red; text-emphasis: open dot blue',
            $block->removeProperty('-webkit-text-emphasis: open dot red; text-emphasis: open dot blue', '-webkit-text-emphasis-style')
        );
        $t->same(
            '-webkit-text-emphasis: open dot red',
            $block->removeProperty('-webkit-text-emphasis: open dot red', 'text-emphasis-style')
        );
        $t->same(
            'color: green; text-emphasis-style: dot !important',
            $block->removeProperty('text-emphasis: dot red !important; text-emphasis-color: blue; color: green', 'text-emphasis-color')
        );
    },
    'declaration block removes upstream caret cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same('caret-shape: block', $block->removeProperty('caret: red block', 'caret-color'));
        $t->same('caret-color: red', $block->removeProperty('caret: red block', 'caret-shape'));
        $t->same('caret-color: auto', $block->removeProperty('caret: block', 'caret-shape'));
        $t->same(
            'color: green',
            $block->removeProperty('caret: red block; caret-color: blue; color: green', 'caret')
        );
        $t->same(
            'color: green; caret-shape: block !important',
            $block->removeProperty('caret: red block !important; caret-color: blue; color: green', 'caret-color')
        );
    },
    'declaration block removes upstream font cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'font-family: Inter, sans-serif; font-style: italic; font-weight: 600; font-stretch: condensed; line-height: 1.5; font-variant-caps: small-caps',
            $block->removeProperty('font: italic small-caps 600 condensed 16px/1.5 Inter, sans-serif', 'font-size')
        );
        $t->same(
            'font-family: Inter, sans-serif; font-size: 16px; font-style: italic; font-weight: 600; font-stretch: normal; font-variant-caps: normal',
            $block->removeProperty('font: italic 600 16px/1.5 Inter, sans-serif', 'line-height')
        );
        $t->same(
            'color: red',
            $block->removeProperty(
                'font: italic 600 16px/1.5 Inter, sans-serif; font-size: 18px; color: red',
                'font'
            )
        );
        $t->same(
            'color: red; font-size: 16px !important; font-style: italic !important; font-weight: 600 !important; font-stretch: normal !important; line-height: 1.5 !important; font-variant-caps: normal !important',
            $block->removeProperty('font: italic 600 16px/1.5 Inter !important; color: red; font-family: system-ui', 'font-family')
        );
    },
    'declaration block removes upstream container cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'container-type: inline-size; color: red',
            $block->removeProperty('container: wp-query-card / inline-size; color: red', 'container-name')
        );
        $t->same(
            'container-name: wp-query-card; color: red',
            $block->removeProperty('container: wp-query-card / inline-size; color: red', 'container-type')
        );
        $t->same(
            'container-type: normal; color: red',
            $block->removeProperty('container: wp-query-card; color: red', 'container-name')
        );
        $t->same(
            'color: red',
            $block->removeProperty('container: wp-query-card / size; container-name: stale; color: red', 'container')
        );
        $t->same(
            'color: red; container-type: size !important',
            $block->removeProperty('container: wp-query-card / size !important; color: red; container-name: stale', 'container-name')
        );
    },
    'declaration block removes upstream mask border cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'mask-border-slice: 25; mask-border-width: 12px; mask-border-outset: 2; mask-border-repeat: round; mask-border-mode: luminance',
            $block->removeProperty('mask-border: url(frame.svg) 25 / 12px / 2 round luminance', 'mask-border-source')
        );
        $t->same(
            'mask-border-source: url(frame.svg); mask-border-slice: 25; mask-border-width: 12px; mask-border-outset: 0; mask-border-repeat: round',
            $block->removeProperty('mask-border: url(frame.svg) 25 / 12px round', 'mask-border-mode')
        );
        $t->same(
            'color: red',
            $block->removeProperty('mask-border: url(frame.svg) 25; mask-border-mode: luminance; color: red', 'mask-border')
        );
        $t->same(
            'color: red; mask-border-slice: 25 !important; mask-border-width: 1 !important; mask-border-outset: 0 !important; mask-border-repeat: stretch !important; mask-border-mode: alpha !important',
            $block->removeProperty('mask-border: url(frame.svg) 25 !important; color: red; mask-border-source: url(new-frame.svg)', 'mask-border-source')
        );
    },
    'declaration block removes upstream mask cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();
        $mask = 'mask: url(mask.svg) 25% 75% / cover no-repeat content-box padding-box subtract luminance';

        $t->same(
            'mask-position: 25% 75%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: subtract; mask-mode: luminance',
            $block->removeProperty($mask, 'mask-image')
        );
        $t->same(
            'mask-image: url(mask.svg); mask-position: 25% 75%; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: subtract; mask-mode: luminance',
            $block->removeProperty($mask, 'mask-size')
        );
        $t->same(
            'mask-image: url(mask.svg); mask-position-y: 75%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: subtract; mask-mode: luminance',
            $block->removeProperty($mask, 'mask-position-x')
        );
        $t->same(
            'mask-position-x: 20px',
            $block->removeProperty('mask-position: 20px 10px', 'mask-position-y')
        );
        $t->same(
            'color: red',
            $block->removeProperty('mask: url(mask.svg); mask-image: url(other.svg); color: red; mask-mode: luminance', 'mask')
        );
        $t->same(
            'color: red; mask-position: 0 0 !important; mask-size: auto !important; mask-repeat: repeat !important; mask-origin: border-box !important; mask-clip: border-box !important; mask-composite: add !important; mask-mode: match-source !important',
            $block->removeProperty('mask: url(mask.svg) !important; color: red; mask-image: url(other.svg)', 'mask-image')
        );
        $webkitMask = '-webkit-mask: url(mask.svg) 10px 20px / contain no-repeat content-box padding-box';
        $t->same(
            '-webkit-mask-position: 10px 20px; -webkit-mask-size: contain; -webkit-mask-repeat: no-repeat; -webkit-mask-origin: content-box; -webkit-mask-clip: padding-box',
            $block->removeProperty($webkitMask, '-webkit-mask-image')
        );
        $t->same(
            'color: red',
            $block->removeProperty('-webkit-mask: url(mask.svg); -webkit-mask-image: url(other.svg); color: red', '-webkit-mask')
        );
        $t->same(
            '-webkit-mask: url(mask.svg)',
            $block->removeProperty('-webkit-mask: url(mask.svg)', 'mask-image')
        );
    },
    'declaration block removes upstream border image cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-image-slice: 25; border-image-width: 12px; border-image-outset: 2; border-image-repeat: round',
            $block->removeProperty('border-image: url(frame.svg) 25 / 12px / 2 round', 'border-image-source')
        );
        $t->same(
            'border-image-source: url(frame.svg); border-image-slice: 25; border-image-width: 12px; border-image-outset: 0',
            $block->removeProperty('border-image: url(frame.svg) 25 / 12px round', 'border-image-repeat')
        );
        $t->same(
            'color: red',
            $block->removeProperty('border-image: url(frame.svg) 25; border-image-repeat: round; color: red', 'border-image')
        );
        $t->same(
            'color: red; border-image-slice: 25 !important; border-image-width: 1 !important; border-image-outset: 0 !important; border-image-repeat: stretch !important',
            $block->removeProperty('border-image: url(frame.svg) 25 !important; color: red; border-image-source: url(new-frame.svg)', 'border-image-source')
        );
        $t->same(
            'border-image-slice: 25; border-image-width: 12px; border-image-outset: 2; border-image-repeat: round',
            $block->removeProperty('-webkit-border-image: url(frame.svg) 25 / 12px / 2 round', 'border-image-source')
        );
        $t->same(
            'color: red',
            $block->removeProperty('-o-border-image: url(frame.svg) 25 / 12px round; color: red', '-o-border-image')
        );
        $t->same(
            'color: red; border-image-slice: 25 !important; border-image-width: 1 !important; border-image-outset: 0 !important; border-image-repeat: stretch !important',
            $block->removeProperty('-moz-border-image: url(frame.svg) 25 !important; color: red; border-image-source: url(new-frame.svg)', 'border-image-source')
        );
    },
    'declaration block removes upstream border radius cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'border-top-right-radius: 20px 2px; border-bottom-right-radius: 30px 3px; border-bottom-left-radius: 40px 4px',
            $block->removeProperty('border-radius: 10px 20px 30px 40px / 1px 2px 3px 4px', 'border-top-left-radius')
        );
        $t->same(
            'border-start-start-radius: 12px; color: red',
            $block->removeProperty('border-radius: 8px; border-top-left-radius: 10px; border-start-start-radius: 12px; color: red', 'border-radius')
        );
        $t->same(
            '-webkit-border-top-left-radius: 8px; -webkit-border-bottom-right-radius: 8px; -webkit-border-bottom-left-radius: 8px',
            $block->removeProperty('-webkit-border-radius: 8px 12px 8px 8px', '-webkit-border-top-right-radius')
        );
        $t->same(
            'color: red; border-top-right-radius: 8px !important; border-bottom-right-radius: 8px !important; border-bottom-left-radius: 8px !important',
            $block->removeProperty('border-radius: 8px !important; color: red; border-top-left-radius: 12px', 'border-top-left-radius')
        );
    },
    'declaration block removes upstream outline cssom longhands and shorthand' => static function (TestRunner $t): void {
        $block = new DeclarationBlock();

        $t->same(
            'outline-width: 2px; outline-style: solid',
            $block->removeProperty('outline: 2px solid red', 'outline-color')
        );
        $t->same(
            'outline-width: medium; outline-color: var(--wp--preset--color--accent)',
            $block->removeProperty('outline: auto var(--wp--preset--color--accent)', 'outline-style')
        );
        $t->same(
            'outline-offset: 2px',
            $block->removeProperty('outline: 2px solid red; outline-width: 4px; outline-color: blue; outline-offset: 2px', 'outline')
        );
        $t->same(
            'color: red; outline-width: 2px !important; outline-style: solid !important',
            $block->removeProperty('outline: 2px solid red !important; color: red; outline-color: blue', 'outline-color')
        );
    },
];
