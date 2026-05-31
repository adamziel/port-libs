<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\MediaQueryParser;

return [
    'media query parser maps upstream range syntax normalization' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('(min-width: 240px)'));
        $t->same('(width<240px)', $parser->minifyList('(width < 240px)'));
        $t->same('(width<=240px)', $parser->minifyList('(width <= 240px)'));
        $t->same('(width>240px)', $parser->minifyList('(240px < width)'));
        $t->same('(width>=240px)', $parser->minifyList('(240px <= width)'));
        $t->same('(100px<width<200px)', $parser->minifyList('(100px < width < 200px)'));
        $t->same('(100px<=width<=200px)', $parser->minifyList('(100px <= width <= 200px)'));
        $t->same('(width>=30em) and (width<=50em)', $parser->minifyList('(min-width: 30em) and (max-width: 50em)'));
    },
    'media query parser maps upstream feature values qualifiers and lists' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('screen,print', $parser->minifyList('screen, print'));
        $t->same('(hover:hover)', $parser->minifyList('(hover: hover)'));
        $t->same('(hover)', $parser->minifyList('(hover)'));
        $t->same('(aspect-ratio:11/5)', $parser->minifyList('(aspect-ratio: 11 / 5)'));
        $t->same('(aspect-ratio:2)', $parser->minifyList('(aspect-ratio: 2/1)'));
        $t->same('not screen and (color)', $parser->minifyList('not screen and (color)'));
        $t->same('only screen and (color)', $parser->minifyList('only screen and (color)'));
        $t->same('(update:slow) or (hover:none)', $parser->minifyList('(update: slow) or (hover: none)'));
        $t->same('(not (color)) or (hover)', $parser->minifyList('(not (color)) or (hover)'));
    },
    'media query parser folds simple same-unit calc values' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('(min-width: calc(200px + 40px))'));
        $t->same('(width>=calc(1em + 5px))', $parser->minifyList('(min-width: calc(1em + 5px))'));
        $t->same('(width>=6px)', $parser->minifyList('(width >= calc(2px + 4px))'));
        $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList('&test, speech'));
    },
    'media query parser lowers range syntax for legacy target fallbacks' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('(width >= 240px)'));
        $t->same('not (max-width:240px)', $parser->lowerRangeSyntaxList('(width > 240px)'));
        $t->same('(not (min-width:240px)) and (hover)', $parser->lowerRangeSyntaxList('(width < 240px) and (hover)'));
        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('not (width < 240px)'));
        $t->same('(min-width:100px) and (max-width:200px)', $parser->lowerRangeSyntaxList('(100px <= width <= 200px)'));
        $t->same('(hover) or ((min-width:100px) and (max-width:200px))', $parser->lowerRangeSyntaxList('(hover) or (100px <= width <= 200px)'));
        $t->same('(not (max-width:100px)) and (not (min-width:200px))', $parser->lowerRangeSyntaxList('(100px < width < 200px)'));
        $t->same('not ((not (max-width:100px)) and (not (min-width:200px)))', $parser->lowerRangeSyntaxList('not (100px < width < 200px)'));
        $t->same('(max-width:200px) and (min-width:100px)', $parser->lowerRangeSyntaxList('(200px >= width >= 100px)'));
    },
    'css minifier normalizes media query preludes before blocks' => static function (TestRunner $t): void {
        $css = '@media (min-width: 240px) and (hover: hover) { .foo { color: chartreuse; } }';

        $t->same('@media (width>=240px) and (hover:hover){.foo{color:#7fff00}}', (new CssMinifier())->minify($css));
    },
];
