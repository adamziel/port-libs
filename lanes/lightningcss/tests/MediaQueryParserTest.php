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
        $t->same('(width<240px)', $parser->minifyList('(240px > width)'));
        $t->same('(width<=240px)', $parser->minifyList('(240px >= width)'));
        $t->same('(width<600px) and (height<600px)', $parser->minifyList('(width < 600px) and (height < 600px)'));
    },
    'media query parser maps upstream negated simple range normalization' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('not (width < 240px)'));
        $t->same('(width>240px)', $parser->minifyList('not (width <= 240px)'));
        $t->same('(width<=240px)', $parser->minifyList('not (width > 240px)'));
        $t->same('(width<240px)', $parser->minifyList('not (width >= 240px)'));
        $t->same('(width<240px)', $parser->minifyList('not (min-width: 240px)'));
        $t->same('(width>240px)', $parser->minifyList('not (max-width: 240px)'));
        $t->same('(color<=2)', $parser->minifyList('not (color > 2)'));
        $t->same('(resolution<2dppx)', $parser->minifyList('not (resolution >= 2dppx)'));
        $t->same('screen and (width>=240px)', $parser->minifyList('screen and not (width < 240px)'));
        $t->same('(width>=240px)', $parser->minifyList('(not (width < 240px))'));
        $t->same('screen and (width>=240px)', $parser->minifyList('screen and (not (width < 240px))'));
        $t->same('(hover) and ((width>=240px))', $parser->minifyList('(hover) and (not (width < 240px))'));
        $t->same('not (100px<=width<=200px)', $parser->minifyList('not (100px <= width <= 200px)'));
    },
    'media query parser maps upstream typed range feature families' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(aspect-ratio>=16/9)', $parser->minifyList('(min-aspect-ratio: 16 / 9)'));
        $t->same('(device-aspect-ratio<=2)', $parser->minifyList('(device-aspect-ratio <= 2/1)'));
        $t->same('(device-width<=480px)', $parser->minifyList('(device-width <= 480px)'));
        $t->same('(device-height>320px)', $parser->minifyList('(320px < device-height)'));
        $t->same('(color-index>=2)', $parser->minifyList('(color-index >= 2)'));
        $t->same('(1<=monochrome<=4)', $parser->minifyList('(1 <= monochrome <= 4)'));
        $t->same('(horizontal-viewport-segments>=2)', $parser->minifyList('(horizontal-viewport-segments >= 2)'));
        $t->same('(vertical-viewport-segments<3)', $parser->minifyList('(3 > vertical-viewport-segments)'));
        $t->same('(-webkit-device-pixel-ratio>=2)', $parser->minifyList('(-webkit-device-pixel-ratio >= 2)'));
        $t->same('(-moz-device-pixel-ratio<1.5)', $parser->minifyList('(-moz-device-pixel-ratio < 1.5)'));
    },
    'media query parser maps upstream unknown range feature parity' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(theme-breakpoint>=2)', $parser->minifyList('(theme-breakpoint >= 2)'));
        $t->same('(2<theme-ratio<3)', $parser->minifyList('(2 / 1 < theme-ratio < 3 / 1)'));
        $t->same('(theme-density>=1.5dppx)', $parser->minifyList('(theme-density >= 1.5dppx)'));
        $t->same('(theme-state=expanded)', $parser->minifyList('(theme-state = expanded)'));
        $t->same('(--wp-breakpoint>env(--wp-breakpoint))', $parser->minifyList('(--wp-breakpoint > env(--wp-breakpoint))'));
        $t->same('(min-theme-breakpoint:2)', $parser->minifyList('(min-theme-breakpoint: 2)'));
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
        $t->same('(color)', $parser->minifyList('all and (color)'));
        $t->same('(width>=240px)', $parser->minifyList('all and (width >= 240px)'));
        $t->same('(color) or (hover)', $parser->minifyList('all and ((color) or (hover))'));
        $t->same('not all and (color)', $parser->minifyList('not all and (color)'));
        $t->same('only all and (color)', $parser->minifyList('only all and (color)'));
        $t->same('(update:slow) or (hover:none)', $parser->minifyList('(update: slow) or (hover: none)'));
        $t->same('(not (color)) or (hover)', $parser->minifyList('(not (color)) or (hover)'));
        $t->same('not ((color) or (hover))', $parser->minifyList('not (((color) or (hover)))'));
        $t->same('(hover) and (color) and (test)', $parser->minifyList('(hover) and ((color) and (test))'));
    },
    'media query parser folds simple same-unit calc values' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('(min-width: calc(200px + 40px))'));
        $t->same('(width>=calc(1em + 5px))', $parser->minifyList('(min-width: calc(1em + 5px))'));
        $t->same('(width>=6px)', $parser->minifyList('(width >= calc(2px + 4px))'));
        $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList('&test, speech'));
    },
    'media query parser rejects upstream invalid range and feature syntax' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();
        $invalid = [
            '(example, all,), speech',
            '&test',
            '(min-width: hi)',
            '(width >= hi)',
            '(width >= 2/1)',
            '(600px <= min-height)',
            '(scan >= 1)',
            '(min-scan: interlace)',
            '(1px <= width <= bar)',
            '(1px <= min-width <= 2px)',
            '(1px <= scan <= 2px)',
            '(grid: 10)',
            '(prefers-color-scheme = dark)',
            'unknown(foo)',
            'calc(foo)',
            'env(--theme-breakpoint)',
            'var(--theme-breakpoint)',
            'screen and var(--theme-breakpoint)',
            '()',
            'screen and ()',
        ];

        foreach ($invalid as $query) {
            $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList($query));
        }
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
        $t->same('(min-aspect-ratio:16/9)', $parser->lowerRangeSyntaxList('(aspect-ratio >= 16 / 9)'));
        $t->same('not (max-color-index:2)', $parser->lowerRangeSyntaxList('(color-index > 2)'));
        $t->same('(min-monochrome:1) and (max-monochrome:4)', $parser->lowerRangeSyntaxList('(1 <= monochrome <= 4)'));
        $t->same('(max-device-width:480px)', $parser->lowerRangeSyntaxList('(device-width <= 480px)'));
        $t->same('(min-horizontal-viewport-segments:2)', $parser->lowerRangeSyntaxList('(horizontal-viewport-segments >= 2)'));
        $t->same('(width:240px)', $parser->lowerRangeSyntaxList('(width = 240px)'));
        $t->same('(width:240px)', $parser->lowerRangeSyntaxList('(240px = width)'));
        $t->same('(theme-state:expanded)', $parser->lowerRangeSyntaxList('(theme-state = expanded)'));
        $t->same('(--wp-breakpoint:env(--wp-breakpoint))', $parser->lowerRangeSyntaxList('(--wp-breakpoint = env(--wp-breakpoint))'));
        $t->same('not screen and not (min-width:240px)', $parser->lowerRangeSyntaxList('not screen and (width < 240px)'));
        $t->same('only screen and (min-width:240px)', $parser->lowerRangeSyntaxList('only screen and (width >= 240px)'));
        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('all and (width >= 240px)'));
        $t->same('screen and not (max-width:max(10px,1rem))', $parser->lowerRangeSyntaxList('screen and (width > max(10px, 1rem))'));
        $t->same('screen and (not (min-width:240px)) and (hover)', $parser->lowerRangeSyntaxList('screen and (width < 240px) and (hover)'));
    },
    'media query parser rejects upstream invalid typed range features' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        foreach ([
            '(min-width: hi)',
            '(width >= hi)',
            '(width >= 2/1)',
            '(600px <= min-height)',
            '(scan >= 1)',
            '(min-scan: interlace)',
            '(1px <= width <= bar)',
            '(1px <= min-width <= 2px)',
            '(1px <= scan <= 2px)',
            '(grid: 10)',
            '(prefers-color-scheme = dark)',
            '(aspect-ratio >= 2px)',
            '(color-index >= 1.5)',
            '(device-width >= 2/1)',
            '(horizontal-viewport-segments >= 2px)',
            '(100px < width > 200px)',
            '(100px <= width > 200px)',
            '(100px > width < 200px)',
            '(1 < color-index > 3)',
            '(100px = width = 200px)',
        ] as $query) {
            $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList($query));
        }
    },
    'css minifier normalizes media query preludes before blocks' => static function (TestRunner $t): void {
        $css = '@media (min-width: 240px) and (hover: hover) { .foo { color: chartreuse; } }';

        $t->same('@media (width>=240px) and (hover:hover){.foo{color:#7fff00}}', (new CssMinifier())->minify($css));
        $t->same('.foo{color:#7fff00}', (new CssMinifier())->minify('@media { .foo { color: chartreuse } }'));
        $t->same('.foo{color:#7fff00}', (new CssMinifier())->minify('@media all { .foo { color: chartreuse } }'));
        $t->same('', (new CssMinifier())->minify('@media not all { .foo { color: chartreuse } }'));
        $t->same('@media not ((color) or (hover)){.foo{color:#7fff00}}', (new CssMinifier())->minify('@media not (((color) or (hover))) { .foo { color: chartreuse } }'));
        $t->same('@media (hover) and (color) and (test){.foo{color:#7fff00}}', (new CssMinifier())->minify('@media (hover) and ((color) and (test)) { .foo { color: chartreuse } }'));
        $t->same('@layer blocks{@media (width>=240px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media not (width < 240px) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (width>=960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media (not (width < 960px)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media screen and (width>=960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media screen and (not (width < 960px)) { .foo { color: chartreuse } } }'));
        $t->same('@media (width>=240px){.foo{color:#7fff00}}', (new CssMinifier())->minify('@media all and (width >= 240px) { .foo { color: chartreuse } }'));
        $t->same('@layer blocks{@media (width>=600px) and (hover){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media all and (min-width: 600px) and (hover) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (color) or (hover){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media all and ((color) or (hover)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media not all and (color){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media not all and (color) { .foo { color: chartreuse } } }'));
    },
    'css minifier rejects invalid media ranges inside cascade layers' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}}',
            $minifier->minify('@layer blocks { @media (min-width: 240px) { .wp-block-query { color: chartreuse; } } }')
        );

        foreach ([
            '@layer blocks { @media (min-width: hi) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= 2/1) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (1px <= min-width <= 2px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (scan >= 1) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (grid: 10) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (prefers-color-scheme = dark) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media var(--theme-breakpoint) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and calc(theme-breakpoint) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and () { .wp-block-query { color: chartreuse; } } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify($css));
        }
    },
];
