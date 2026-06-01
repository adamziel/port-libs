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
        $t->same('(width>=240px) and (hover)', $parser->minifyList('(width >= 240px) AND (hover)'));
        $t->same('(width>=240px) or (hover)', $parser->minifyList('(width >= 240px) Or (hover)'));
        $t->same('((width>480px) and (hover)) or (pointer:coarse)', $parser->minifyList('((width > 480px) AnD (hover)) Or (pointer: coarse)'));
        $t->same('(width<240px)', $parser->minifyList('(240px > width)'));
        $t->same('(width<=240px)', $parser->minifyList('(240px >= width)'));
        $t->same('(width<600px) and (height<600px)', $parser->minifyList('(width < 600px) and (height < 600px)'));
        $t->same('(width>=.5px)', $parser->minifyList('(width >= 0.5px)'));
        $t->same('(.5px<=width<=1.5px)', $parser->minifyList('(0.5px <= width <= 1.50px)'));
        $t->same('(width>=-.5px)', $parser->minifyList('(width >= -0.5px)'));
        $t->same('(width>=0)', $parser->minifyList('(width >= 0px)'));
        $t->same('(width>=0)', $parser->minifyList('(width >= 0)'));
        $t->same('(width>=2px)', $parser->minifyList('(width >= 2)'));
        $t->same('(height<3px)', $parser->minifyList('(height < 3)'));
        $t->same('(2px<=width<=4px)', $parser->minifyList('(2 <= width <= 4)'));
        $t->same('(width=2px)', $parser->minifyList('(width = 2)'));
        $t->same('(aspect-ratio>=.5)', $parser->minifyList('(aspect-ratio >= 0.5/1.0)'));
        $t->same('(theme-breakpoint>=.5rem)', $parser->minifyList('(theme-breakpoint >= +0.5rem)'));
        $t->same('(width>=1000px)', $parser->minifyList('(width >= 1e3px)'));
        $t->same('(100px<=width<=200px)', $parser->minifyList('(1e2px <= width <= 2e2px)'));
        $t->same('(width>=.125rem)', $parser->minifyList('(width >= 1.25e-1rem)'));
        $t->same('(width>=1e-7px)', $parser->minifyList('(width >= 1e-7px)'));
        $t->same('(width>=1000px)', $parser->minifyList('(width >= +1E3px)'));
    },
    'media query parser maps upstream negated simple range normalization' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('not (width < 240px)'));
        $t->same('(width>=240px)', $parser->minifyList('not (((width < 240px)))'));
        $t->same('(width>240px)', $parser->minifyList('not (width <= 240px)'));
        $t->same('(width<=240px)', $parser->minifyList('not (width > 240px)'));
        $t->same('(width<240px)', $parser->minifyList('not (width >= 240px)'));
        $t->same('(width<240px)', $parser->minifyList('not (((min-width: 240px)))'));
        $t->same('(width>=2px)', $parser->minifyList('not (width < 2)'));
        $t->same('not (color)', $parser->minifyList('not (((color)))'));
        $t->same('(width<240px)', $parser->minifyList('not (not (width < 240px))'));
        $t->same('(width<240px)', $parser->minifyList('not (min-width: 240px)'));
        $t->same('(width>240px)', $parser->minifyList('not (max-width: 240px)'));
        $t->same('(color<=2)', $parser->minifyList('not (color > 2)'));
        $t->same('(resolution<2dppx)', $parser->minifyList('not (resolution >= 2dppx)'));
        $t->same('(Theme-Breakpoint<2)', $parser->minifyList('not (Theme-Breakpoint >= 2)'));
        $t->same('(--WP-Breakpoint<3)', $parser->minifyList('not (--WP-Breakpoint >= 3)'));
        $t->same('Speech and (--WP-Breakpoint<3)', $parser->minifyList('Speech and (not (--WP-Breakpoint >= 3))'));
        $t->same('screen and (width>=240px)', $parser->minifyList('screen and not (width < 240px)'));
        $t->same('(width>=240px)', $parser->minifyList('(not (width < 240px))'));
        $t->same('screen and (width>=240px)', $parser->minifyList('screen and (not (width < 240px))'));
        $t->same('(hover) and (width>=240px)', $parser->minifyList('(hover) and (not (width < 240px))'));
        $t->same('(hover) and (width<240px)', $parser->minifyList('(hover) and (not (not (width < 240px)))'));
        $t->same('not (100px<=width<=200px)', $parser->minifyList('not (100px <= width <= 200px)'));
    },
    'media query parser maps upstream negated equality range normalization' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width=240px)', $parser->minifyList('not (width = 240px)'));
        $t->same('(width=240px)', $parser->minifyList('not (240px = width)'));
        $t->same('(theme-state=expanded)', $parser->minifyList('not (theme-state = expanded)'));
        $t->same('(Theme-State=Expanded)', $parser->minifyList('not (Theme-State = Expanded)'));
        $t->same('(--wp-breakpoint=env(--wp-breakpoint))', $parser->minifyList('not (--wp-breakpoint = env(--wp-breakpoint))'));
        $t->same('(--WP-Breakpoint=env(--WP-Breakpoint))', $parser->minifyList('not (--WP-Breakpoint = env(--WP-Breakpoint))'));
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
        $t->same('(resolution>=2x)', $parser->minifyList('(resolution >= 2x)'));
        $t->same('(resolution>=2dppx)', $parser->minifyList('(resolution >= 2e0dppx)'));
        $t->same('(aspect-ratio>=16/9)', $parser->minifyList('(aspect-ratio >= 16e0 / 9e0)'));
        $t->same('(-webkit-device-pixel-ratio>=2)', $parser->minifyList('(-webkit-device-pixel-ratio >= 2)'));
        $t->same('(-webkit-device-pixel-ratio>=2)', $parser->minifyList('(-webkit-device-pixel-ratio >= 2e0)'));
        $t->same('(-moz-device-pixel-ratio<1.5)', $parser->minifyList('(-moz-device-pixel-ratio < 1.5)'));
        $t->same('(-webkit-device-pixel-ratio>=2)', $parser->minifyList('(-webkit-min-device-pixel-ratio: 2)'));
        $t->same('(-webkit-device-pixel-ratio<=1.5)', $parser->minifyList('(-webkit-max-device-pixel-ratio: 1.5)'));
        $t->same('(-moz-device-pixel-ratio>=2)', $parser->minifyList('(min--moz-device-pixel-ratio: 2)'));
        $t->same('(-moz-device-pixel-ratio<=1.5)', $parser->minifyList('(max--moz-device-pixel-ratio: 1.5)'));
        $t->same('(-webkit-device-pixel-ratio)', $parser->minifyList('(-webkit-min-device-pixel-ratio)'));
        $t->same('(-moz-device-pixel-ratio)', $parser->minifyList('(max--moz-device-pixel-ratio)'));
        $t->same('(width)', $parser->minifyList('(min-width)'));
        $t->same('(scan)', $parser->minifyList('(max-scan)'));
    },
    'media query parser maps upstream unknown range feature parity' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(theme-breakpoint>=2)', $parser->minifyList('(theme-breakpoint >= 2)'));
        $t->same('(theme-breakpoint>=100px)', $parser->minifyList('(theme-breakpoint >= 1e2px)'));
        $t->same('(1.5<theme-ratio<3)', $parser->minifyList('(1.5e0 / 1 < theme-ratio < 3e0 / 1)'));
        $t->same('(2<theme-ratio<3)', $parser->minifyList('(2 / 1 < theme-ratio < 3 / 1)'));
        $t->same('(theme-density>=1.5dppx)', $parser->minifyList('(theme-density >= 1.5dppx)'));
        $t->same('(theme-state=expanded)', $parser->minifyList('(theme-state = expanded)'));
        $t->same('(--wp-breakpoint>env(--wp-breakpoint))', $parser->minifyList('(--wp-breakpoint > env(--wp-breakpoint))'));
        $t->same('(min-theme-breakpoint:2)', $parser->minifyList('(min-theme-breakpoint: 2)'));
        $t->same('(Theme-Breakpoint>=2)', $parser->minifyList('(Theme-Breakpoint >= 2)'));
        $t->same('(--WP-Breakpoint>=2)', $parser->minifyList('(--WP-Breakpoint >= 2)'));
        $t->same('(Theme-State=Expanded)', $parser->minifyList('(Theme-State = Expanded)'));
        $t->same('Speech and (--WP-Breakpoint>=2)', $parser->minifyList('Speech and (--WP-Breakpoint >= 2)'));
    },
    'media query parser decodes escaped range feature identifiers like upstream' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('(w\\69 dth >= 240px)'));
        $t->same('(width>=240px)', $parser->minifyList('(min-w\\69 dth: 240px)'));
        $t->same('(100px<=width<=200px)', $parser->minifyList('(100px <= w\\69 dth <= 200px)'));
        $t->same('(--wp-breakpoint>=2)', $parser->minifyList('(--wp\\2d breakpoint >= 2)'));
        $t->same('(theme-breakpoint>=2)', $parser->minifyList('(theme\\-breakpoint >= 2)'));
        $t->same('(theme-state=expanded)', $parser->minifyList('(theme\\2d state = exp\\61 nded)'));
        $t->same('screen and (width>=240px)', $parser->minifyList('scr\\65 en and (w\\69 dth >= 240px)'));
        $t->same('only screen and (width>=240px)', $parser->minifyList('\\6f nly scr\\65 en a\\6e d (w\\69 dth >= 240px)'));
        $t->same('(width>=240px)', $parser->minifyList('n\\6f t (w\\69 dth < 240px)'));
        $t->same('(hover) or (100px<=width<=200px)', $parser->minifyList('(hover) o\\72 (100px <= w\\69 dth <= 200px)'));
        $t->same('((width>480px) and (hover)) or (pointer:coarse)', $parser->minifyList('((w\\69 dth > 480px) a\\6e d (hover)) o\\72 (pointer: coarse)'));
        $t->same('not (min-width:240px)', $parser->lowerRangeSyntaxList('(w\\69 dth < 240px)'));
        $t->same('only screen and (min-width:240px)', $parser->lowerRangeSyntaxList('\\6f nly scr\\65 en a\\6e d (w\\69 dth >= 240px)'));
        $t->same('(hover) or ((min-width:100px) and (max-width:200px))', $parser->lowerRangeSyntaxList('(hover) o\\72 (100px <= w\\69 dth <= 200px)'));
        $t->same('(min-width:100px) and (max-width:200px)', $parser->lowerRangeSyntaxList('(100px <= w\\69 dth <= 200px)'));
        $t->same('(min-theme-breakpoint:2)', $parser->lowerRangeSyntaxList('(theme\\2d breakpoint >= 2)'));
    },
    'media query parser maps upstream feature values qualifiers and lists' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('', $parser->minifyList(''));
        $t->same('screen,print', $parser->minifyList('screen, print'));
        $t->same('screen', $parser->minifyList('screen,'));
        $t->same('(width>=240px)', $parser->minifyList('(width >= 240px),'));
        $t->same('(hover:hover)', $parser->minifyList('(hover: hover)'));
        $t->same('(hover)', $parser->minifyList('(hover)'));
        $t->same('(hover:env(--wp-hover))', $parser->minifyList('(hover: env(--wp-hover))'));
        $t->same('(prefers-color-scheme:env(--wp-scheme))', $parser->minifyList('(prefers-color-scheme: env(--wp-scheme))'));
        $t->same('(width:240px)', $parser->minifyList('(width: 240px)'));
        $t->same('(height:0)', $parser->minifyList('(height: 0)'));
        $t->same('(resolution:2dppx)', $parser->minifyList('(resolution: 2dppx)'));
        $t->same('(color:2)', $parser->minifyList('(color: +2)'));
        $t->same('(color-index:2)', $parser->minifyList('(color-index: 02)'));
        $t->same('(monochrome:0)', $parser->minifyList('(monochrome: -0)'));
        $t->same('(aspect-ratio:11/5)', $parser->minifyList('(aspect-ratio: 11 / 5)'));
        $t->same('(aspect-ratio:2)', $parser->minifyList('(aspect-ratio: 2/1)'));
        $t->same('(theme-state:10)', $parser->minifyList('(theme-state: 10)'));
        $t->same('(theme-ratio:3/2)', $parser->minifyList('(theme-ratio: 3 / 2)'));
        $t->same('(theme-breakpoint:15rem)', $parser->minifyList('(theme-breakpoint: 15rem)'));
        $t->same('(theme-density:1.5dppx)', $parser->minifyList('(theme-density: 1.5dppx)'));
        $t->same('(grid:1)', $parser->minifyList('(grid: +1)'));
        $t->same('(grid:1)', $parser->minifyList('(grid: 01)'));
        $t->same('(grid:0)', $parser->minifyList('(grid: +0)'));
        $t->same('(grid:0)', $parser->minifyList('(grid: -0)'));
        $t->same('not screen and (color)', $parser->minifyList('not screen and (color)'));
        $t->same('only screen and (color)', $parser->minifyList('only screen and (color)'));
        $t->same('(color)', $parser->minifyList('all and (color)'));
        $t->same('(width>=240px)', $parser->minifyList('all and (width >= 240px)'));
        $t->same('(color) or (hover)', $parser->minifyList('all and ((color) or (hover))'));
        $t->same('not all and (color)', $parser->minifyList('not all and (color)'));
        $t->same('only all and (color)', $parser->minifyList('only all and (color)'));
        $t->same('screen and not (color)', $parser->minifyList('screen and not (color)'));
        $t->same('screen and (not (color)) and (hover)', $parser->minifyList('screen and (not (color)) and (hover)'));
        $t->same('all,all', $parser->minifyList('all, all'));
        $t->same('not all,not all', $parser->minifyList('not all, not all'));
        $t->true($parser->alwaysMatchesList('all, all'));
        $t->same(false, $parser->alwaysMatchesList('all, (hover)'));
        $t->true($parser->neverMatchesList('not all, not all'));
        $t->same(false, $parser->neverMatchesList('not all, (hover)'));
        $t->same('(update:slow) or (hover:none)', $parser->minifyList('(update: slow) or (hover: none)'));
        $t->same('(not (color)) or (hover)', $parser->minifyList('(not (color)) or (hover)'));
        $t->same('not ((color) or (hover))', $parser->minifyList('not (((color) or (hover)))'));
        $t->same('screen and ((color) or (hover))', $parser->minifyList('screen and ((color) or (hover))'));
        $t->same('only screen and ((color) or (hover))', $parser->minifyList('only screen and ((color) or (hover))'));
        $t->same('(hover) and (color) and (test)', $parser->minifyList('(hover) and ((color) and (test))'));
    },
    'media query parser treats comments as upstream media-list trivia' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('screen and (width>=240px)', $parser->minifyList('screen/* migration */and (width >= 240px)'));
        $t->same('(hover) or (100px<=width<=200px)', $parser->minifyList('(hover)/* stale, alias */or/* breakpoint */(100px <= width <= 200px)'));
        $t->same('(width>=1px),(hover)', $parser->minifyList('(width >= 1px)/* stale, comma */, (hover)'));
        $t->same('screen', $parser->minifyList('/* generated import note */ screen, /* trailing build note */'));
        $t->same('(width>=240px)', $parser->minifyList('(width >= 240px)/* unclosed build note'));
        $t->same('not (color)', $parser->minifyList('not/* generated */(color)'));
        $t->same('not (color)', $parser->minifyList('n\\6f t (color)'));
    },
    'media query parser maps upstream media query conjunction semantics' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=250px) and (color)', $parser->andQuery('(min-width: 250px)', '(color)'));
        $t->same('((width>=250px) or (color)) and (orientation:landscape)', $parser->andQuery('(min-width: 250px) or (color)', '(orientation: landscape)'));
        $t->same('(width>=250px) and (color) and (orientation:landscape)', $parser->andQuery('(min-width: 250px) and (color)', '(orientation: landscape)'));
        $t->same('print', $parser->andQuery('all', 'print'));
        $t->same('print', $parser->andQuery('print', 'all'));
        $t->same('not print', $parser->andQuery('all', 'not print'));
        $t->same('not print', $parser->andQuery('not print', 'all'));
        $t->same('not all', $parser->andQuery('not all', 'print'));
        $t->same('not all', $parser->andQuery('print', 'not all'));
        $t->same('not all', $parser->andQuery('print', 'screen'));
        $t->same('screen', $parser->andQuery('not print', 'screen'));
        $t->same('print', $parser->andQuery('print', 'not screen'));
        $t->same('print', $parser->andQuery('not screen', 'print'));
        $t->same('not all', $parser->andQuery('not screen', 'not all'));
        $t->same('print and (width>=250px)', $parser->andQuery('print', '(min-width: 250px)'));
        $t->same('print and (width>=250px)', $parser->andQuery('(min-width: 250px)', 'print'));
        $t->same('print and (width>=250px) and (color)', $parser->andQuery('print and (min-width: 250px)', '(color)'));
        $t->same('only screen', $parser->andQuery('all', 'only screen'));
        $t->same('only screen', $parser->andQuery('only screen', 'all'));
        $t->same('print', $parser->andQuery('print', 'print'));
        $t->throws(InvalidArgumentException::class, static fn () => $parser->andQuery('not print', 'not screen'));
    },
    'media query parser folds simple same-unit calc and math function values' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width>=240px)', $parser->minifyList('(min-width: calc(200px + 40px))'));
        $t->same('(width>=calc(1em + 5px))', $parser->minifyList('(min-width: calc(1em + 5px))'));
        $t->same('(width>=calc(1em + 5px))', $parser->minifyList('(min-width: calc(1em+5px))'));
        $t->same('(width>=6px)', $parser->minifyList('(width >= calc(2px + 4px))'));
        $t->same('(width>=6px)', $parser->minifyList('(width >= calc(2 * 3px))'));
        $t->same('(width>=6px)', $parser->minifyList('(width >= calc(2px * 3))'));
        $t->same('(width>=3px)', $parser->minifyList('(width >= calc(6px / 2))'));
        $t->same('(width>=6)', $parser->minifyList('(width >= calc(2 * 3))'));
        $t->same('(width>calc(1px + 1rem))', $parser->minifyList('(width > calc(1px+1rem))'));
        $t->same('(width>20px)', $parser->minifyList('(width > max(10px, 20px))'));
        $t->same('(width>2)', $parser->minifyList('(width > max(1, 2))'));
        $t->same('(width>10px)', $parser->minifyList('(width > min(10px, 20px))'));
        $t->same('(width>15px)', $parser->minifyList('(width > clamp(10px, 15px, 20px))'));
        $t->same('(width>20px)', $parser->minifyList('(width > clamp(10px, 25px, 20px))'));
        $t->same('(width>10px)', $parser->minifyList('(width > clamp(10px, 5px, 20px))'));
        $t->same('(width>max(10px,1rem))', $parser->minifyList('(width > max(10px, 1rem))'));
        $t->same('(aspect-ratio>=.5)', $parser->minifyList('(aspect-ratio >= max(1 / 2, 1 / 3))'));
        $t->same('(aspect-ratio>=.5)', $parser->minifyList('(aspect-ratio >= min(1 / 2, 2))'));
        $t->same('(aspect-ratio>=.5)', $parser->minifyList('(aspect-ratio >= clamp(1 / 4, 1 / 2, 3 / 4))'));
        $t->same('(1<=aspect-ratio<=3)', $parser->minifyList('(1 <= aspect-ratio <= max(2, 3))'));
        $t->same('(theme-ratio>=.5)', $parser->minifyList('(theme-ratio >= max(1 / 2, 1 / 3))'));
        $t->same('(theme-ratio>=.5)', $parser->minifyList('(theme-ratio >= clamp(1 / 4, 1 / 2, 3 / 4))'));
        $t->same('(width>=20px)', $parser->minifyList('(width >= round(22px, 5px))'));
        $t->same('(width>=25px)', $parser->minifyList('(width >= round(up, 22px, 5px))'));
        $t->same('(width>=3px)', $parser->minifyList('(width >= rem(18px, 5px))'));
        $t->same('(width>=3px)', $parser->minifyList('(width >= mod(18px, 5px))'));
        $t->same('(width>=5px)', $parser->minifyList('(width >= hypot(3px, 4px))'));
        $t->same('(width>=2px)', $parser->minifyList('(width >= abs(-2px))'));
        $t->same('(width>=2)', $parser->minifyList('(width >= abs(-2))'));
        $t->same('(width>=2)', $parser->minifyList('(width >= round(2, 1))'));
        $t->same('(width>=1)', $parser->minifyList('(width >= rem(5, 2))'));
        $t->same('(width>=1)', $parser->minifyList('(width >= mod(5, 2))'));
        $t->same('(width>=5)', $parser->minifyList('(width >= hypot(3, 4))'));
        $t->same('(width>=1)', $parser->minifyList('(width >= sign(10px))'));
        $t->same('(width>=-1)', $parser->minifyList('(width >= sign(-10px))'));
        $t->same('(width>=0)', $parser->minifyList('(width >= sign(-0px))'));
        $t->same('(width>=1)', $parser->minifyList('(width >= sign(10 / 2))'));
        $t->same('(width>=-1)', $parser->minifyList('(width >= sign(calc(1px - 2px)))'));
        $t->same('(10px<=width<=1)', $parser->minifyList('(10px <= width <= sign(20px))'));
        $t->same('(theme-breakpoint>=1)', $parser->minifyList('(theme-breakpoint >= sign(10rem))'));
        $t->same('(aspect-ratio>=1)', $parser->minifyList('(aspect-ratio >= sign(10 / 2))'));
        $t->same('(-webkit-device-pixel-ratio>=1)', $parser->minifyList('(-webkit-device-pixel-ratio >= sign(10 / 2))'));
        $t->same('(20px<=width<=25px)', $parser->minifyList('(round(22px, 5px) <= width <= round(up, 22px, 5px))'));
        $t->same('(-webkit-device-pixel-ratio>=2)', $parser->minifyList('(-webkit-device-pixel-ratio >= calc(1 + 1))'));
        $t->same('(-webkit-device-pixel-ratio>=2)', $parser->minifyList('(-webkit-device-pixel-ratio >= max(1, 2))'));
        $t->same('(1<=-moz-device-pixel-ratio<=2)', $parser->minifyList('(1 <= -moz-device-pixel-ratio <= calc(1 + 1))'));
        $t->same('not (max-width:calc(1px + 1rem))', $parser->lowerRangeSyntaxList('(width > calc(1px+1rem))'));
        $t->same('(min-width:6px)', $parser->lowerRangeSyntaxList('(width >= calc(2 * 3px))'));
        $t->same('(min-width:6)', $parser->lowerRangeSyntaxList('(width >= calc(2 * 3))'));
        $t->same('not (max-width:20px)', $parser->lowerRangeSyntaxList('(width > max(10px, 20px))'));
        $t->same('not (max-width:2)', $parser->lowerRangeSyntaxList('(width > max(1, 2))'));
        $t->same('(min-width:15px)', $parser->lowerRangeSyntaxList('(width >= clamp(10px, 15px, 20px))'));
        $t->same('(min-aspect-ratio:.5)', $parser->lowerRangeSyntaxList('(aspect-ratio >= max(1 / 2, 1 / 3))'));
        $t->same('(min-aspect-ratio:1) and (max-aspect-ratio:3)', $parser->lowerRangeSyntaxList('(1 <= aspect-ratio <= max(2, 3))'));
        $t->same('(min-theme-ratio:.5)', $parser->lowerRangeSyntaxList('(theme-ratio >= max(1 / 2, 1 / 3))'));
        $t->same('(min-theme-ratio:.5)', $parser->lowerRangeSyntaxList('(theme-ratio >= clamp(1 / 4, 1 / 2, 3 / 4))'));
        $t->same('(min-width:20px)', $parser->lowerRangeSyntaxList('(width >= round(22px, 5px))'));
        $t->same('(min-width:3px)', $parser->lowerRangeSyntaxList('(width >= rem(18px, 5px))'));
        $t->same('(min-width:3px)', $parser->lowerRangeSyntaxList('(width >= mod(18px, 5px))'));
        $t->same('(min-width:5px)', $parser->lowerRangeSyntaxList('(width >= hypot(3px, 4px))'));
        $t->same('(min-width:2px)', $parser->lowerRangeSyntaxList('(width >= abs(-2px))'));
        $t->same('(min-width:2)', $parser->lowerRangeSyntaxList('(width >= abs(-2))'));
        $t->same('(min-width:2)', $parser->lowerRangeSyntaxList('(width >= round(2, 1))'));
        $t->same('(min-width:1)', $parser->lowerRangeSyntaxList('(width >= rem(5, 2))'));
        $t->same('(min-width:1)', $parser->lowerRangeSyntaxList('(width >= mod(5, 2))'));
        $t->same('(min-width:5)', $parser->lowerRangeSyntaxList('(width >= hypot(3, 4))'));
        $t->same('(min-width:1)', $parser->lowerRangeSyntaxList('(width >= sign(10px))'));
        $t->same('(min-width:-1)', $parser->lowerRangeSyntaxList('(width >= sign(-10px))'));
        $t->same('(min-width:0)', $parser->lowerRangeSyntaxList('(width >= sign(0px))'));
        $t->same('(min-width:10px) and (max-width:1)', $parser->lowerRangeSyntaxList('(10px <= width <= sign(20px))'));
        $t->same('(min-theme-breakpoint:1)', $parser->lowerRangeSyntaxList('(theme-breakpoint >= sign(10rem))'));
        $t->same('(min-aspect-ratio:1)', $parser->lowerRangeSyntaxList('(aspect-ratio >= sign(10 / 2))'));
        $t->same('(-webkit-min-device-pixel-ratio:1)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio >= sign(10 / 2))'));
        $t->same('(min-width:20px) and (max-width:25px)', $parser->lowerRangeSyntaxList('(round(22px, 5px) <= width <= round(up, 22px, 5px))'));
        $t->same('(min-width:round(22px,5vw))', $parser->lowerRangeSyntaxList('(width >= round(22px, 5vw))'));
        $t->same('(not (max-width:100px)) and (not (min-width:calc(100vw - 50px)))', $parser->lowerRangeSyntaxList('(100px < width < calc(100vw-50px))'));
        $t->same('(-webkit-min-device-pixel-ratio:2)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio >= calc(1 + 1))'));
        $t->same('(-webkit-min-device-pixel-ratio:2)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio >= max(1, 2))'));
        $t->same('(min--moz-device-pixel-ratio:1) and (max--moz-device-pixel-ratio:2)', $parser->lowerRangeSyntaxList('(1 <= -moz-device-pixel-ratio <= calc(1 + 1))'));
        $t->same(
            '@layer blocks{@media (-webkit-device-pixel-ratio>=2){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (-webkit-device-pixel-ratio >= calc(1 + 1)) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (width>15px){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (width > clamp(10px, 15px, 20px)) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (width>=6px){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (width >= calc(2 * 3px)) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (aspect-ratio>=.5){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (aspect-ratio >= max(1 / 2, 1 / 3)) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (20px<=width<=25px){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (round(22px, 5px) <= width <= round(up, 22px, 5px)) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (width>=1){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (width >= sign(10px)) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (width>=5){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (width >= hypot(3, 4)) { .wp-block-query { color: yellow; } } }')
        );
        $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList('&test, speech'));
    },
    'media query parser maps upstream redundant calc parentheses in ranges' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();
        $minifier = new CssMinifier();

        $t->same('(width>=6px)', $parser->minifyList('(width >= calc((2px + 4px)))'));
        $t->same('(width>=6px)', $parser->minifyList('(width >= calc((2 * 3px)))'));
        $t->same('(6px<=width<=12px)', $parser->minifyList('(calc((2px + 4px)) <= width <= calc((10px + 2px)))'));
        $t->same('(width>calc(1px + 1rem))', $parser->minifyList('(width > calc((1px + 1rem)))'));
        $t->same('(aspect-ratio>=.5)', $parser->minifyList('(aspect-ratio >= calc((1 / 2)))'));
        $t->same('(theme-ratio>=.5)', $parser->minifyList('(theme-ratio >= calc((1 / 2)))'));
        $t->same('(min-width:6px)', $parser->lowerRangeSyntaxList('(width >= calc((2px + 4px)))'));
        $t->same('(min-width:6px) and (max-width:12px)', $parser->lowerRangeSyntaxList('(calc((2px + 4px)) <= width <= calc((10px + 2px)))'));
        $t->same('not (max-width:calc(1px + 1rem))', $parser->lowerRangeSyntaxList('(width > calc((1px + 1rem)))'));
        $t->same('(min-aspect-ratio:.5)', $parser->lowerRangeSyntaxList('(aspect-ratio >= calc((1 / 2)))'));
        $t->same('(min-theme-ratio:.5)', $parser->lowerRangeSyntaxList('(theme-ratio >= calc((1 / 2)))'));
        $t->same(
            '@layer blocks{@media (width>=6px){.wp-block-query{color:#ff0}}}',
            $minifier->minify('@layer blocks { @media (width >= calc((2px + 4px))) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (6px<=width<=12px){.wp-block-query{color:#ff0}}}',
            $minifier->minify('@layer blocks { @media (calc((2px + 4px)) <= width <= calc((10px + 2px))) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media (aspect-ratio>=.5){.wp-block-query{color:#ff0}}}',
            $minifier->minify('@layer blocks { @media (aspect-ratio >= calc((1 / 2))) { .wp-block-query { color: yellow; } } }')
        );
    },
    'media query parser maps upstream environment variable range values' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(width<=env(--branding-small))', $parser->minifyList('(max-width: env(--branding-small))'));
        $t->same('(width<=env(--branding-small 1))', $parser->minifyList('(max-width: env(--branding-small 1))'));
        $t->same('(width<=env(--branding-small 1,20px))', $parser->minifyList('(max-width: env(--branding-small 1, 20px))'));
        $t->same('(width<=env(safe-area-inset-top))', $parser->minifyList('(max-width: env(safe-area-inset-top))'));
        $t->same('(width<=env(unknown))', $parser->minifyList('(max-width: env(unknown))'));
        $t->same('(max-width:env(--branding-small 1,20px))', $parser->lowerRangeSyntaxList('(max-width: env(--branding-small 1, 20px))'));
        $t->same('(max-width:env(safe-area-inset-top))', $parser->lowerRangeSyntaxList('(max-width: env(safe-area-inset-top))'));
        $t->same('(resolution>=env(--wp-density-floor))', $parser->minifyList('(min-resolution: env(--wp-density-floor))'));
        $t->same('(min-resolution:env(--wp-density-floor)) and (max-resolution:2dppx)', $parser->lowerRangeSyntaxList('(env(--wp-density-floor) <= resolution <= 2dppx)'));
    },
    'media query parser rejects upstream invalid range and feature syntax' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();
        $invalid = [
            '(example, all,), speech',
            ',',
            ',,',
            ', ,',
            ',screen',
            'screen,,print',
            'screen, ,print',
            '(width >= 1px),,(hover)',
            '&test',
            '(min-width: hi)',
            '(width >= hi)',
            '(min-width: 50%)',
            '(width >= 50%)',
            '(50% <= width <= 75%)',
            '(width >= 2/1)',
            '(600px <= min-height)',
            '(scan >= 1)',
            '(min-scan: interlace)',
            '(1px <= width <= bar)',
            '(1px <= min-width <= 2px)',
            '(1px <= scan <= 2px)',
            '(grid: 10)',
            '(grid: -1)',
            '(grid: 1.0)',
            '(grid: true)',
            '(hover: 1)',
            '(pointer: 1)',
            '(orientation: 1)',
            '(prefers-color-scheme: 10)',
            '(update: 2px)',
            '(display-mode: 1/2)',
            '(width: var(--theme-breakpoint))',
            '(resolution: 2)',
            '(color: 1.0)',
            '(color-index: 1e0)',
            '(aspect-ratio: 2px)',
            '(theme-state: var(--foo))',
            '(theme-state: #fff)',
            '(theme-state: url(foo))',
            '(prefers-color-scheme = dark)',
            '(color >= calc(1 + 1))',
            '(color >= 1e0)',
            '(color-index >= 1e0)',
            '(theme-breakpoint >= 50%)',
            '(--wp-breakpoint >= 50%)',
            '(50% <= theme-breakpoint <= 75%)',
            '(theme-breakpoint: 50%)',
            '(theme-breakpoint >= max(10%, 20%))',
            '(resolution >= calc(1 + 1dppx))',
            '(width >= max(10%, 20%))',
            '(width >= calc(50% + 1px))',
            '(width >= calc(6px / 2px))',
            '(width >= calc(6 / 2px))',
            '(width >= calc(6px * 2px))',
            '(width >= var(--theme-breakpoint))',
            '(--theme-breakpoint >= var(--theme-breakpoint))',
            '(-webkit-min-device-pixel-ratio: hi)',
            '(-webkit-min-device-pixel-ratio >= 2)',
            'unknown(foo)',
            'calc(foo)',
            'env(--theme-breakpoint)',
            'var(--theme-breakpoint)',
            'screen and var(--theme-breakpoint)',
            'not(color)',
            'n\\6f t(color)',
            'screen and not(color)',
            'all and all',
            'not all and all',
            'all and not all',
            'screen and print',
            'screen and not all',
            'screen and only all',
            'screen and (color) and not (hover)',
            'screen and not (color) and (hover)',
            '(not unknown(foo))',
            '(not calc(foo))',
            '(unk\\6e own(foo))',
            '(width >= 240px) and (unk\\6e own(foo))',
            '(hover) and (unk\\6e own(foo))',
            '((color) or unknown(foo))',
            '((color) and unknown(foo))',
            '((color) or not unknown(foo))',
            'screen and (color) or (hover)',
            'screen or (hover)',
            'not screen and (color) or (hover)',
            'only screen and (width >= 240px) or (hover)',
            'all and (color) or (hover)',
            'and (hover)',
            'or (hover)',
            '(hover) and',
            '(hover) or',
            'screen and',
            'not screen and',
            'only screen and',
            '()',
            'screen and ()',
            'screen (width >= 240px)',
            'screen not (width >= 240px)',
            'only screen (width >= 240px)',
            'not screen (width >= 240px)',
            'print (100px <= width <= 200px)',
        ];

        foreach ($invalid as $query) {
            $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList($query));
        }
    },
    'media query parser rejects mixed boolean operators without grouping' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('((width>1px) and (hover)) or (pointer)', $parser->minifyList('((width > 1px) and (hover)) or (pointer)'));
        $t->same('(width>1px) and ((hover) or (pointer))', $parser->minifyList('(width > 1px) and ((hover) or (pointer))'));
        $t->same(
            '@layer blocks{@media ((width>1px) and (hover)) or (pointer){.wp-block-query{color:#7fff00}}}',
            (new CssMinifier())->minify('@layer blocks { @media ((width > 1px) and (hover)) or (pointer) { .wp-block-query { color: chartreuse; } } }')
        );

        foreach ([
            '(color) and (hover) or (pointer)',
            '(color) or (hover) and (pointer)',
            '(width > 1px) and (hover) or (pointer)',
            '(width > 1px) or (hover) and (pointer)',
            '((width > 1px) and (hover) or (pointer))',
            '((hover) and)',
        ] as $query) {
            $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList($query));
        }

        foreach ([
            '@layer blocks { @media (width > 1px) and (hover) or (pointer) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media ((width > 1px) and (hover) or (pointer)) { .wp-block-query { color: chartreuse; } } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssMinifier())->minify($css));
        }
    },
    'media query parser rejects upstream bare not operands in boolean conditions' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('not (color)', $parser->minifyList('not (color)'));
        $t->same('(not (color)) and (hover)', $parser->minifyList('(not (color)) and (hover)'));
        $t->same('screen and not (color)', $parser->minifyList('screen and not (color)'));
        $t->same('screen and (not (color)) and (hover)', $parser->minifyList('screen and ((not (color)) and (hover))'));
        $t->same(
            '@layer blocks{@media (not (color)) and (hover){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media (not (color)) and (hover) { .wp-block-query { color: yellow; } } }')
        );
        $t->same(
            '@layer blocks{@media screen and (not (color)) and (hover){.wp-block-query{color:#ff0}}}',
            (new CssMinifier())->minify('@layer blocks { @media screen and ((not (color)) and (hover)) { .wp-block-query { color: yellow; } } }')
        );

        foreach ([
            'not (color) and (hover)',
            'not (color) or (hover)',
            'not (width < 240px) and (hover)',
            '(hover) and not (color)',
            '(hover) or not (color)',
            'screen and not (color) and (hover)',
            'screen and (hover) and not (color)',
        ] as $query) {
            $t->throws(InvalidArgumentException::class, static fn () => $parser->minifyList($query));
        }

        foreach ([
            '@layer blocks { @media not (width < 240px) and (hover) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (hover) and not (color) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and not (color) and (hover) { .wp-block-query { color: chartreuse; } } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssMinifier())->minify($css));
        }
    },
    'media query parser lowers range syntax for legacy target fallbacks' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('(width >= 240px)'));
        $t->same('not (max-width:240px)', $parser->lowerRangeSyntaxList('(width > 240px)'));
        $t->same('(not (min-width:240px)) and (hover)', $parser->lowerRangeSyntaxList('(width < 240px) and (hover)'));
        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('not (width < 240px)'));
        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('not (((width < 240px)))'));
        $t->same('(min-width:100px) and (max-width:200px)', $parser->lowerRangeSyntaxList('(100px <= width <= 200px)'));
        $t->same('(hover) or ((min-width:100px) and (max-width:200px))', $parser->lowerRangeSyntaxList('(hover) or (100px <= width <= 200px)'));
        $t->same('(not (max-width:100px)) and (not (min-width:200px))', $parser->lowerRangeSyntaxList('(100px < width < 200px)'));
        $t->same('not ((not (max-width:100px)) and (not (min-width:200px)))', $parser->lowerRangeSyntaxList('not (100px < width < 200px)'));
        $t->same('screen and not ((min-width:200px) and (not (min-width:500px)))', $parser->lowerRangeSyntaxList('screen and not (200px <= width < 500px)'));
        $t->same('(hover) and (not ((min-width:200px) and (not (min-width:500px))))', $parser->lowerRangeSyntaxList('(hover) and (not (200px <= width < 500px))'));
        $t->same('(hover) and (min-width:240px)', $parser->lowerRangeSyntaxList('(hover) and (not (width < 240px))'));
        $t->same('not ((not (min-width:240px)) or (hover))', $parser->lowerRangeSyntaxList('not ((width < 240px) or (hover))'));
        $t->same('not (((min-width:100px) and (max-width:200px)) or (hover))', $parser->lowerRangeSyntaxList('not ((100px <= width <= 200px) or (hover))'));
        $t->same('(max-width:200px) and (min-width:100px)', $parser->lowerRangeSyntaxList('(200px >= width >= 100px)'));
        $t->same('(min-aspect-ratio:16/9)', $parser->lowerRangeSyntaxList('(aspect-ratio >= 16 / 9)'));
        $t->same('not (max-color-index:2)', $parser->lowerRangeSyntaxList('(color-index > 2)'));
        $t->same('(min-monochrome:1) and (max-monochrome:4)', $parser->lowerRangeSyntaxList('(1 <= monochrome <= 4)'));
        $t->same('(max-device-width:480px)', $parser->lowerRangeSyntaxList('(device-width <= 480px)'));
        $t->same('(min-horizontal-viewport-segments:2)', $parser->lowerRangeSyntaxList('(horizontal-viewport-segments >= 2)'));
        $t->same('not (max-color:2)', $parser->lowerRangeSyntaxList('(color > 2)'));
        $t->same('not (min-color:2)', $parser->lowerRangeSyntaxList('(color < 2)'));
        $t->same('not ((min-width:100px) and (max-width:200px))', $parser->lowerRangeSyntaxList('not (100px <= width <= 200px)'));
        $t->same('(not (max-width:100px)) and (not (min-width:200px))', $parser->lowerRangeSyntaxList('(100px < width < 200px)'));
        $t->same('not ((not (max-width:100px)) and (not (min-width:200px)))', $parser->lowerRangeSyntaxList('not (100px < width < 200px)'));
        $t->same('(width:240px)', $parser->lowerRangeSyntaxList('(width = 240px)'));
        $t->same('(width:240px)', $parser->lowerRangeSyntaxList('(240px = width)'));
        $t->same('(theme-state:expanded)', $parser->lowerRangeSyntaxList('(theme-state = expanded)'));
        $t->same('(--wp-breakpoint:env(--wp-breakpoint))', $parser->lowerRangeSyntaxList('(--wp-breakpoint = env(--wp-breakpoint))'));
        $t->same('(-webkit-min-device-pixel-ratio:2)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio >= 2)'));
        $t->same('not (-webkit-max-device-pixel-ratio:2)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio > 2)'));
        $t->same('(-webkit-max-device-pixel-ratio:1.5)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio <= 1.5)'));
        $t->same('not (min--moz-device-pixel-ratio:1.5)', $parser->lowerRangeSyntaxList('(-moz-device-pixel-ratio < 1.5)'));
        $t->same('(-webkit-min-device-pixel-ratio:2) and (-webkit-max-device-pixel-ratio:3)', $parser->lowerRangeSyntaxList('(2 <= -webkit-device-pixel-ratio <= 3)'));
        $t->same('(not (-webkit-max-device-pixel-ratio:2)) and (not (-webkit-min-device-pixel-ratio:3))', $parser->lowerRangeSyntaxList('(2 < -webkit-device-pixel-ratio < 3)'));
        $t->same('(resolution:2dppx)', $parser->useDppxResolutionUnitList($parser->lowerRangeSyntaxList('(resolution = 2x)')));
        $t->same('(min-resolution:2dppx)', $parser->useDppxResolutionUnitList($parser->lowerRangeSyntaxList('(resolution >= 2x)')));
        $t->same('(min-resolution:.5dppx) and (max-resolution:1.5dppx)', $parser->useDppxResolutionUnitList($parser->lowerRangeSyntaxList('(.5x <= resolution <= 1.5x)')));
        $t->same('(.5x<=resolution<=1.5x)', $parser->useXResolutionUnitList($parser->minifyList('(.5dppx <= resolution <= 1.5dppx)')));
        $t->same('(.5dppx<=resolution<=1.5dppx)', $parser->useDppxResolutionUnitList($parser->minifyList('(.5x <= resolution <= 1.5x)')));
        $t->same('(resolution>=2x)', $parser->useXResolutionUnitList($parser->minifyList('(2dppx <= resolution)')));
        $t->same('(2x<=resolution)', $parser->useXResolutionUnitList('(2dppx<=resolution)'));
        $t->same('(2dppx<=resolution)', $parser->useDppxResolutionUnitList('(2x<=resolution)'));
        $t->same('(min-width:.5px)', $parser->lowerRangeSyntaxList('(width >= 0.5px)'));
        $t->same('(min-width:.5px) and (max-width:1.5px)', $parser->lowerRangeSyntaxList('(0.5px <= width <= 1.50px)'));
        $t->same('(min-width:-.5px)', $parser->lowerRangeSyntaxList('(width >= -0.5px)'));
        $t->same('(min-width:0)', $parser->lowerRangeSyntaxList('(width >= 0px)'));
        $t->same('(min-width:2px)', $parser->lowerRangeSyntaxList('(width >= 2)'));
        $t->same('not (min-height:3px)', $parser->lowerRangeSyntaxList('(height < 3)'));
        $t->same('(min-width:2px) and (max-width:4px)', $parser->lowerRangeSyntaxList('(2 <= width <= 4)'));
        $t->same('(width:2px)', $parser->lowerRangeSyntaxList('(width = 2)'));
        $t->same('(min-aspect-ratio:.5)', $parser->lowerRangeSyntaxList('(aspect-ratio >= 0.5/1.0)'));
        $t->same('(min-theme-breakpoint:.5rem)', $parser->lowerRangeSyntaxList('(theme-breakpoint >= +0.5rem)'));
        $t->same('(min-width:1000px)', $parser->lowerRangeSyntaxList('(width >= 1e3px)'));
        $t->same('(min-width:100px) and (max-width:200px)', $parser->lowerRangeSyntaxList('(1e2px <= width <= 2e2px)'));
        $t->same('(min-width:1e-7px)', $parser->lowerRangeSyntaxList('(width >= 1e-7px)'));
        $t->same('(min-aspect-ratio:16/9)', $parser->lowerRangeSyntaxList('(aspect-ratio >= 16e0 / 9e0)'));
        $t->same('(min-resolution:2dppx)', $parser->lowerRangeSyntaxList('(resolution >= 2e0dppx)'));
        $t->same('(min-theme-breakpoint:100px)', $parser->lowerRangeSyntaxList('(theme-breakpoint >= 1e2px)'));
        $t->same('(-webkit-min-device-pixel-ratio:2)', $parser->lowerRangeSyntaxList('(-webkit-device-pixel-ratio >= 2e0)'));
        $t->same('(min-Theme-Breakpoint:2)', $parser->lowerRangeSyntaxList('(Theme-Breakpoint >= 2)'));
        $t->same('not (min-Theme-Breakpoint:2)', $parser->lowerRangeSyntaxList('not (Theme-Breakpoint >= 2)'));
        $t->same('(min---WP-Breakpoint:2)', $parser->lowerRangeSyntaxList('(--WP-Breakpoint >= 2)'));
        $t->same('not (min---WP-Breakpoint:3)', $parser->lowerRangeSyntaxList('not (--WP-Breakpoint >= 3)'));
        $t->same('(Theme-State:Expanded)', $parser->lowerRangeSyntaxList('(Theme-State = Expanded)'));
        $t->same('(Theme-State:Expanded)', $parser->lowerRangeSyntaxList('not (Theme-State = Expanded)'));
        $t->same('Speech and (min---WP-Breakpoint:2)', $parser->lowerRangeSyntaxList('Speech and (--WP-Breakpoint >= 2)'));
        $t->same('Speech and not (min---WP-Breakpoint:3)', $parser->lowerRangeSyntaxList('Speech and (not (--WP-Breakpoint >= 3))'));
        $t->same('not screen and not (min-width:240px)', $parser->lowerRangeSyntaxList('not screen and (width < 240px)'));
        $t->same('only screen and (min-width:240px)', $parser->lowerRangeSyntaxList('only screen and (width >= 240px)'));
        $t->same('(min-width:240px)', $parser->lowerRangeSyntaxList('all and (width >= 240px)'));
        $t->same('(min-width:2px)', $parser->lowerRangeSyntaxList('not (width < 2)'));
        $t->same('screen and not (max-width:max(10px,1rem))', $parser->lowerRangeSyntaxList('screen and (width > max(10px, 1rem))'));
        $t->same('screen and (not (min-width:240px)) and (hover)', $parser->lowerRangeSyntaxList('screen and (width < 240px) and (hover)'));
    },
    'media query parser rejects upstream invalid typed range features' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();

        foreach ([
            '(min-width: hi)',
            '(min-width: 50%)',
            '(width >= 50%)',
            '(50% <= width <= 75%)',
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
            '(color-index >= 1e0)',
            '(color >= 1e0)',
            '(device-width >= 2/1)',
            '(horizontal-viewport-segments >= 2px)',
            '(theme-breakpoint >= 50%)',
            '(--wp-breakpoint >= 50%)',
            '(50% <= theme-breakpoint <= 75%)',
            '(theme-breakpoint: 50%)',
            '(theme-breakpoint >= max(10%, 20%))',
            '(width >= calc(6px / 2px))',
            '(width >= calc(6 / 2px))',
            '(width >= calc(6px * 2px))',
            '(width >= calc((6px / 2px)))',
            '(width >= calc((6 / 2px)))',
            '(width >= calc((6px * 2px)))',
            '(width >= max(10%, 20%))',
            '(width >= calc(50% + 1px))',
            '(aspect-ratio >= max(1/2, 1px))',
            '(aspect-ratio >= max(1px, 2px))',
            '(aspect-ratio >= calc(1px + 1em))',
            '(aspect-ratio >= calc((1px + 1em)))',
            '(width >= sign(10%))',
            '(width >= sign(10dppx))',
            '(width >= sign(var(--theme-breakpoint)))',
            '(color >= sign(10))',
            '(resolution >= sign(10dppx))',
            '(aspect-ratio >= sign(10px))',
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
        $t->same('.foo{color:#7fff00}', (new CssMinifier())->minify('@media all, all { .foo { color: chartreuse } }'));
        $t->same('', (new CssMinifier())->minify('@media not all, not all { .foo { color: chartreuse } }'));
        $t->same('@media not all,(hover){.foo{color:#7fff00}}', (new CssMinifier())->minify('@media not all, (hover) { .foo { color: chartreuse } }'));
        $t->same('@layer blocks{@media (width>=240px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media not (width < 240px) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{.foo{color:#7fff00}}', (new CssMinifier())->minify('@layer blocks { @media all, all { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks;', (new CssMinifier())->minify('@layer blocks { @media not all, not all { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (width>=960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media (not (width < 960px)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (width>=960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media not (((width < 960px))) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (width<960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media not (not (width < 960px)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media screen and (width>=960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media screen and (not (width < 960px)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (hover) and (width>=960px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media (hover) and (not (width < 960px)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (width<=env(--branding-small 1,20px)){.foo{padding:env(--branding-padding 2,20px)}}}', (new CssMinifier())->minify('@layer blocks { @media (max-width: env(--branding-small 1, 20px)) { .foo { padding: env(--branding-padding 2, 20px); } } }'));
        $t->same('@layer blocks{@media (width<=env(safe-area-inset-top)){.foo{padding-top:env(safe-area-inset-top)}}}', (new CssMinifier())->minify('@layer blocks { @media (max-width: env(safe-area-inset-top)) { .foo { padding-top: env(safe-area-inset-top); } } }'));
        $t->same('@media (width>=240px){.foo{color:#7fff00}}', (new CssMinifier())->minify('@media all and (width >= 240px) { .foo { color: chartreuse } }'));
        $t->same('@layer blocks{@media (width>=600px) and (hover){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media all and (min-width: 600px) and (hover) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (color) or (hover){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media all and ((color) or (hover)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (width>=600px) and (hover) and (color){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media all AND (min-width: 600px) AnD ((hover) AND (color)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media ((width>480px) and (hover)) or (pointer:coarse){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media ((width > 480px) AnD (hover)) Or (pointer: coarse) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media not all and (color){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media not all and (color) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media screen and ((color) or (hover)){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media screen and ((color) or (hover)) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (grid:1){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media (grid: +1) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (prefers-color-scheme:env(--wp-scheme)) and (width:240px){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media (prefers-color-scheme: env(--wp-scheme)) and (width: 240px) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media (width=240px){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media not (width = 240px) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media (--wp-breakpoint=env(--wp-breakpoint)){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media not (--wp-breakpoint = env(--wp-breakpoint)) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media screen and (width>=240px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media scr\\65 en and (w\\69 dth >= 240px) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media only screen and (width>=240px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media \\6f nly scr\\65 en a\\6e d (w\\69 dth >= 240px) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (hover) or (100px<=width<=200px){.foo{color:#7fff00}}}', (new CssMinifier())->minify('@layer blocks { @media (hover) o\\72 (100px <= w\\69 dth <= 200px) { .foo { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (theme-state=expanded){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media (theme\\2d state = exp\\61 nded) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media (Theme-Breakpoint>=2) and (--WP-Breakpoint>=3){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media (Theme-Breakpoint >= 2) and (--WP-Breakpoint >= 3) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media (Theme-Breakpoint<2){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media not (Theme-Breakpoint >= 2) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media Speech and (--WP-Breakpoint<3){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media Speech and (not (--WP-Breakpoint >= 3)) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media (width>=240px){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media (width >= 240px), { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media screen and not (color){.foo{color:#ff0}}}', (new CssMinifier())->minify('@layer blocks { @media screen and not (color) { .foo { color: yellow } } }'));
    },
    'media query parser flattens upstream redundant boolean wrappers inside layers' => static function (TestRunner $t): void {
        $parser = new MediaQueryParser();
        $minifier = new CssMinifier();

        $t->same('(hover) and (color) and (width>=1px)', $parser->minifyList('((hover) and ((color) and (width >= 1px)))'));
        $t->same('(hover) or (color) or (width>=1px)', $parser->minifyList('((hover) or ((color) or (width >= 1px)))'));
        $t->same('(hover) and ((color) or (width>=1px))', $parser->minifyList('((hover) and ((color) or (width >= 1px)))'));
        $t->same('(hover) or ((color) and (width>=1px))', $parser->minifyList('((hover) or ((color) and (width >= 1px)))'));
        $t->same('screen and (hover) and (color) and (width>=1px)', $parser->minifyList('screen and ((hover) and ((color) and (width >= 1px)))'));
        $t->same('screen and ((hover) or ((color) and (width>=1px)))', $parser->minifyList('screen and ((hover) or ((color) and (width >= 1px)))'));
        $t->same('not (color)', $parser->minifyList('(not ((color)))'));
        $t->same('@layer blocks{@media (hover) and (color) and (width>=1px){.foo{color:#ff0}}}', $minifier->minify('@layer blocks { @media ((hover) and ((color) and (width >= 1px))) { .foo { color: yellow } } }'));
        $t->same('@layer blocks{@media screen and ((hover) or ((color) and (width>=1px))){.foo{color:#ff0}}}', $minifier->minify('@layer blocks { @media screen and ((hover) or ((color) and (width >= 1px))) { .foo { color: yellow } } }'));
    },
    'css minifier treats comments as media query token separators inside layers' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('@layer blocks{@media screen and (width>=240px){.wp-block-query{color:#ff0}}}', $minifier->minify('@layer blocks { @media screen/* migration */and (width >= 240px) { .wp-block-query { color: yellow } } }'));
        $t->same('@layer blocks{@media only screen and (width>=240px){.wp-block-query{color:#ff0}}}', $minifier->minify('@layer blocks { @media only/* legacy */screen/* migration */and (min-width: 240px) { .wp-block-query { color: yellow } } }'));
        $t->same('@layer blocks{@media not screen and (width>=240px){.wp-block-query{color:#ff0}}}', $minifier->minify('@layer blocks { @media not/* legacy */screen/* migration */and (width >= 240px) { .wp-block-query { color: yellow } } }'));
        $t->same('@layer blocks{@media (width>=600px){.wp-block-query{color:#7fff00}}}', $minifier->minify('@layer blocks { @media all/* token */and (min-width: 600px) { .wp-block-query { color: chartreuse } } }'));
        $t->same('@layer blocks{@media (hover) or (100px<=width<=200px){.wp-block-query{color:#ff0}}}', $minifier->minify('@layer blocks { @media (hover)/* migration */or/* breakpoint */(100px <= width <= 200px) { .wp-block-query { color: yellow } } }'));
    },
    'css minifier rejects invalid media ranges inside cascade layers' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}}',
            $minifier->minify('@layer blocks { @media (min-width: 240px) { .wp-block-query { color: chartreuse; } } }')
        );

        foreach ([
            '@layer blocks { @media (min-width: hi) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (min-width: 50%) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= 50%) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (50% <= width <= 75%) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= 2/1) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (1px <= min-width <= 2px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (scan >= 1) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (grid: 10) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (grid: 1.0) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (hover: 1) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (prefers-color-scheme: 10) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width: var(--theme-breakpoint)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (resolution: 2) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (color: 1.0) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (theme-state: #fff) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (prefers-color-scheme = dark) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (color >= calc(1 + 1)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (theme-breakpoint >= 50%) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (--wp-breakpoint >= 50%) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (50% <= theme-breakpoint <= 75%) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (theme-breakpoint >= max(10%, 20%)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (resolution >= calc(1 + 1dppx)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (resolution >= round(2dppx, 1dppx)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= max(10%, 20%)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= calc(50% + 1px)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= calc(6px / 2px)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= var(--theme-breakpoint)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media var(--theme-breakpoint) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and calc(theme-breakpoint) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (unk\\6e own(foo)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= 240px) and (unk\\6e own(foo)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (not unknown(foo)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media ((color) or unknown(foo)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media ((color) and not unknown(foo)) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and (color) or (hover) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen or (hover) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (hover) and { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and () { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen (width >= 240px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen not (width >= 240px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media not screen (width >= 240px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media only screen (width >= 240px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media print (100px <= width <= 200px) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media (width >= 240px),, (hover) { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media ,screen { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media , { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media all and all { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and not all { .wp-block-query { color: chartreuse; } } }',
            '@layer blocks { @media screen and (color) and not (hover) { .wp-block-query { color: chartreuse; } } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify($css));
        }
    },
];
