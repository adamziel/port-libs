<?php

declare(strict_types=1);

use PortLibs\LightningCSS\SelectorSpecificity;

$specificity = static fn (int $ids, int $classes, int $elements): int => ($ids << 20) | ($classes << 10) | $elements;

return [
    'selector specificity maps upstream simple selectors' => static function (TestRunner $t) use ($specificity): void {
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed('e'));
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed('|e'));
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed('svg|circle'));
        $t->same($specificity(0, 0, 0), SelectorSpecificity::packed('*'));
        $t->same($specificity(0, 0, 0), SelectorSpecificity::packed('|*'));
        $t->same($specificity(0, 0, 0), SelectorSpecificity::packed('*|*'));
        $t->same($specificity(0, 2, 0), SelectorSpecificity::packed('.foo:lang(en-US)'));
        $t->same($specificity(1, 0, 0), SelectorSpecificity::packed('#bar'));
        $t->same($specificity(1, 1, 1), SelectorSpecificity::packed('e.foo#bar'));
        $t->same($specificity(1, 1, 1), SelectorSpecificity::packed('e.foo #bar'));
        $t->same($specificity(0, 1, 0), SelectorSpecificity::packed('[Foo]'));
    },
    'selector specificity maps upstream pseudo selectors' => static function (TestRunner $t) use ($specificity): void {
        $t->same($specificity(0, 1, 0), SelectorSpecificity::packed(':empty'), 'upstream selectors/parser.rs::tests::test_empty');
        $t->same($specificity(0, 0, 2), SelectorSpecificity::packed('q::before'), 'upstream selectors/parser.rs::tests::test_pseudo_iter lines 3955-3965');
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed('*|*::before'), 'upstream selectors/parser.rs::tests::test_universal lines 3969-3976');
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed('::before'), 'upstream selectors/parser.rs::tests::test_empty_pseudo_iter lines 3980-3988');
        $t->same($specificity(0, 1, 1), SelectorSpecificity::packed('::before:hover'));
        $t->same($specificity(0, 2, 1), SelectorSpecificity::packed('::before:hover:hover'));
        $t->same($specificity(0, 1, 0), SelectorSpecificity::packed(':not(.cl)'));
        $t->same($specificity(0, 0, 0), SelectorSpecificity::packed(':not(*)'));
        $t->same($specificity(1, 0, 1), SelectorSpecificity::packed('foo:is(.bar, #baz)'));
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed('foo:where(#bar, .baz)'));
        $t->same($specificity(0, 1, 2), SelectorSpecificity::packed('article:has(.wp-block-image img)'));
        $t->same($specificity(1, 1, 1), SelectorSpecificity::packed('li:nth-child(2n of .current, #featured)'));
    },
    'selector specificity maps additional upstream parser specificity rows' => static function (TestRunner $t) use ($specificity): void {
        $t->same($specificity(0, 0, 0), SelectorSpecificity::packed('svg|*'));
        $t->same($specificity(0, 0, 1), SelectorSpecificity::packed(':not(e)'));
        $t->same($specificity(0, 1, 0), SelectorSpecificity::packed('[attr|="foo"]'));
        $t->same($specificity(0, 0, 2), SelectorSpecificity::packed('div ::after'));
        $t->same($specificity(1, 1, 0), SelectorSpecificity::packed('#d1 > .ok'));
        $t->same($specificity(0, 0, 0), SelectorSpecificity::packed(':not(|*)'));
    },
    'selector specificity compares WordPress override selectors' => static function (TestRunner $t): void {
        $t->true(SelectorSpecificity::compare('.wp-block-button .wp-element-button:hover', '.wp-block-button .wp-element-button') > 0);
        $t->true(SelectorSpecificity::compare('#site-header .wp-block-navigation a', '.wp-block-navigation a') > 0);

        $specificity = SelectorSpecificity::calculate('.wp-block-navigation a, #site-header a');
        $t->same(1, $specificity['ids']);
        $t->same(0, $specificity['classes']);
        $t->same(1, $specificity['elements']);
    },
];
