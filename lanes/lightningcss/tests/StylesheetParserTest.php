<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssRule;
use PortLibs\LightningCSS\StylesheetParser;

return [
    'stylesheet parser separates selectors and declarations' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('.wp-block-button, .entry-content a:hover { color: red; margin: calc(1rem + 2px); }');

        $t->same(1, count($rules));
        $t->same(CssRule::TYPE_STYLE, $rules[0]->type);
        $t->same(['.wp-block-button', '.entry-content a:hover'], $rules[0]->selectors);
        $t->same('red', $rules[0]->declarations['color']);
        $t->same('calc(1rem + 2px)', $rules[0]->declarations['margin']);
    },
    'stylesheet parser keeps at-rule statements distinct from style rules' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('@import url("theme.css") layer(theme); .site { color: blue; }');

        $t->same(CssRule::TYPE_AT_RULE, $rules[0]->type);
        $t->same('import', $rules[0]->name);
        $t->same('url("theme.css") layer(theme)', $rules[0]->prelude);
        $t->same(CssRule::TYPE_STYLE, $rules[1]->type);
    },
    'stylesheet parser parses media at-rule blocks with nested style rules' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('@media (min-width: 600px) { .wp-site-blocks { padding: 2rem; } }');

        $media = $rules[0];
        $t->same(CssRule::TYPE_AT_RULE, $media->type);
        $t->same('media', $media->name);
        $t->same('(min-width: 600px)', $media->prelude);
        $t->same(['.wp-site-blocks'], $media->rules[0]->selectors);
        $t->same('2rem', $media->rules[0]->declarations['padding']);
    },
    'stylesheet parser parses nested WordPress selectors inside style rules' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('.wp-block-group { color: red; & .wp-block-button__link { color: white; } @supports (display: grid) { & > .wp-block-columns { display: grid; } } }');

        $group = $rules[0];
        $t->same('red', $group->declarations['color']);
        $t->same(['& .wp-block-button__link'], $group->rules[0]->selectors);
        $t->same('white', $group->rules[0]->declarations['color']);
        $t->same('supports', $group->rules[1]->name);
        $t->same(['& > .wp-block-columns'], $group->rules[1]->rules[0]->selectors);
        $t->same('grid', $group->rules[1]->rules[0]->declarations['display']);
    },
    'stylesheet parser ignores comments but preserves braces in strings' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('.notice { /* hidden */ content: "{}"; }');

        $t->same('{}', trim($rules[0]->declarations['content'], '"'));
    },
    'stylesheet parser rejects malformed blocks' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new StylesheetParser())->parse('.broken { color: red; '));
    },
];
