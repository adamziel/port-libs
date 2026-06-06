# Pandoc Syntax Highlighting Core Current Base 20260606T081445Z

## Summary

Implemented one bounded syntax-highlighting support behavior: HTML code blocks
now delegate `<style>` raw-text bodies to the native CSS tokenizer and
`<script>` raw-text bodies to the native JavaScript tokenizer while preserving
the existing HTML token handoff for wrapper tags, comments, attributes, and
WordPress raw HTML style metadata.

The slice adds a WordPress review fixture for embedded block asset markup and
updates the syntax-highlighting handoff example self-test.

## Source Truth

- Pandoc syntax highlighting is driven by code-block language classes and emits
  HTML spans/styles for highlighted source:
  https://pandoc.org/demo/example33/15-syntax-highlighting.html
- Pandoc uses Skylighting for syntax highlighting; Skylighting carries an HTML
  syntax definition with embedded raw-text sublanguages for HTML `style` and
  `script` bodies:
  https://github.com/jgm/skylighting/tree/master/skylighting-core/xml

This is a bounded PHP handoff for the format contract, not a full Skylighting
XML grammar engine.

## Pre-Edit Probe

Baseline focused test:

```console
$ php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1121 assertions, 0 failures
```

Direct red-first probe before the source change:

```console
$ php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $code = "<style>.wp-block { color: var(--accent-color); }</style>\n<script>const block = wp.element.createElement(\"p\", null, \"ok\");</script>"; $result = $h->highlight($code, "html"); var_export(["language" => $result["language"], "hasCssAtRule" => str_contains($result["html"], "<span class=\"ot\">color</span>"), "hasJsConst" => str_contains($result["html"], "<span class=\"kw\">const</span>")]); echo "\n";'
array (
  'language' => 'html',
  'hasCssAtRule' => false,
  'hasJsConst' => false,
)
```

## Verification

```console
$ php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

$ php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

$ php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

$ php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1142 assertions, 0 failures

$ php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1250 -> 1251`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1694 -> 1695`.
- Focused SyntaxHighlighter assertions: `1121 -> 1142` (`+21`).

## Dependency Closure

No new support component is needed. This reuses the native PHP
`SyntaxHighlighter` HTML, CSS, and JavaScript tokenizers plus the existing
Markdown reader and WordPress block handoff example.

The upstream-runner blocker remains unchanged: full upstream Pandoc parity still
requires a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
with Cabal package/project files and Haskell Tasty executable builds for
`test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This slice does not repeat the accepted standalone CSS at-rule/selector,
Rust alias/token, Mustache/Handlebars, Mermaid, JavaScript, TypeScript, SCSS,
Nix, Go, PowerShell, DOT, SQL, XML, shell, or custom-theme highlighting
handoffs. It only covers embedded CSS and JavaScript raw-text delegation inside
HTML code blocks.

## Follow-Up

Keep full Skylighting XML grammar parity, parser-state-aware nested HTML
raw-text edge cases, embedded Markdown fenced-code delegation, and writer-wide
default highlight policy as separate bounded slices.
