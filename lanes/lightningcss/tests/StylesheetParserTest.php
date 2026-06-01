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
        $t->same('(width>=600px)', $media->prelude);
        $t->same(['.wp-site-blocks'], $media->rules[0]->selectors);
        $t->same('2rem', $media->rules[0]->declarations['padding']);
    },
    'stylesheet parser normalizes media range preludes inside cascade layers' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('@layer theme.blocks { @media screen and (min-width: 48rem), (hover) { .wp-site-blocks { padding-inline: clamp(1rem, 2vw, 2rem); } } }');

        $layer = $rules[0];
        $media = $layer->rules[0];
        $block = $media->rules[0];
        $t->same(CssRule::TYPE_AT_RULE, $layer->type);
        $t->same('layer', $layer->name);
        $t->same('theme.blocks', $layer->prelude);
        $t->same(CssRule::TYPE_AT_RULE, $media->type);
        $t->same('media', $media->name);
        $t->same('screen and (width>=48rem),(hover)', $media->prelude);
        $t->same(CssRule::TYPE_STYLE, $block->type);
        $t->same(['.wp-site-blocks'], $block->selectors);
        $t->same('clamp(1rem, 2vw, 2rem)', $block->declarations['padding-inline']);
    },
    'stylesheet parser maps layered media range paths after nested layer statements' => static function (TestRunner $t): void {
        $parser = new StylesheetParser();
        $css = <<<'CSS'
@layer theme.blocks {
  @layer reset;
  @media (min-width: 48rem) {
    .wp-site-blocks {
      padding-inline: clamp(1rem, 2vw, 2rem);
    }
  }
}
CSS;

        $rules = $parser->parse($css);
        $layer = $rules[0];
        $statement = $layer->rules[0];
        $media = $layer->rules[1];
        $block = $media->rules[0];

        $t->same('layer', $statement->name);
        $t->same('reset', $statement->prelude);
        $t->same('media', $media->name);
        $t->same('(width>=48rem)', $media->prelude);
        $t->same(['.wp-site-blocks'], $block->selectors);
        $t->same('clamp(1rem, 2vw, 2rem)', $block->declarations['padding-inline']);
        $t->same(
            [
                'key' => ['start' => ['line' => 5, 'column' => 7], 'end' => ['line' => 5, 'column' => 21]],
                'value' => ['start' => ['line' => 5, 'column' => 23], 'end' => ['line' => 5, 'column' => 45]],
            ],
            $parser->propertyLocation($css, [0, 1, 0], 0)
        );
    },
    'stylesheet parser parses nested WordPress selectors inside style rules' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('.wp-block-group { color: red; & .wp-block-button__link { color: white; } @supports (display: grid) { & > .wp-block-columns { display: grid; } } }');

        $group = $rules[0];
        $t->same('red', $group->declarations['color']);
        $t->same(['& .wp-block-button__link'], $group->rules[0]->selectors);
        $t->same('#fff', $group->rules[0]->declarations['color']);
        $t->same('supports', $group->rules[1]->name);
        $t->same(['& > .wp-block-columns'], $group->rules[1]->rules[0]->selectors);
        $t->same('grid', $group->rules[1]->rules[0]->declarations['display']);
    },
    'stylesheet parser maps upstream cssom declaration property locations' => static function (TestRunner $t): void {
        $parser = new StylesheetParser();
        $css = <<<'CSS'
.foo {
  color: green;
}
.bar {
  color: red;
  background: pink;
}

@media print {
  .baz {
    color: green;
  }
}
CSS;

        $t->same(
            [
                'key' => ['start' => ['line' => 5, 'column' => 3], 'end' => ['line' => 5, 'column' => 8]],
                'value' => ['start' => ['line' => 5, 'column' => 10], 'end' => ['line' => 5, 'column' => 13]],
            ],
            $parser->propertyLocation($css, [1], 0)
        );
        $t->same(
            [
                'key' => ['start' => ['line' => 6, 'column' => 3], 'end' => ['line' => 6, 'column' => 13]],
                'value' => ['start' => ['line' => 6, 'column' => 15], 'end' => ['line' => 6, 'column' => 19]],
            ],
            $parser->propertyLocation($css, [1], 1)
        );
        $t->same(
            [
                'key' => ['start' => ['line' => 11, 'column' => 5], 'end' => ['line' => 11, 'column' => 10]],
                'value' => ['start' => ['line' => 11, 'column' => 12], 'end' => ['line' => 11, 'column' => 17]],
            ],
            $parser->propertyLocation($css, [2, 0], 0)
        );
        $t->same(null, $parser->propertyLocation($css, [2, 0], 1));
        $t->same(null, $parser->propertyLocation($css, [99], 0));
        $t->throws(InvalidArgumentException::class, static fn () => $parser->propertyLocation($css, [], 0));
    },
    'stylesheet parser ignores comments but preserves braces in strings' => static function (TestRunner $t): void {
        $rules = (new StylesheetParser())->parse('.notice { /* hidden */ content: "{}"; }');

        $t->same('{}', trim($rules[0]->declarations['content'], '"'));
    },
    'stylesheet parser rejects malformed blocks' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new StylesheetParser())->parse('.broken { color: red; '));
    },
];
