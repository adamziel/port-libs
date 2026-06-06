# Pandoc Syntax Highlighting HTML PHP Island Slice

Date: 2026-06-06 UTC

Lane: `pandoc`

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260606T084803Z`

Accepted base: `71c5eeae4c9f23694b119f6527acf05ccda9fdea`

## Scope

This slice adds one bounded native PHP syntax-highlighting behavior under
`lanes/pandoc/**`: HTML code blocks now delegate `<?php ... ?>` and `<?= ... ?>`
template islands to the existing PHP tokenizer while preserving the existing
HTML token handoff for wrapper tags, comments, attributes, line numbering, and
WordPress raw HTML style metadata.

The fixture covers a WordPress theme-template review packet with PHP
alternative syntax, escaped-output tags, and HTML wrapper markup. The highlighted
PHP is never executed.

## Source Truth

Pandoc syntax highlighting is driven by code-block language classes and emits
HTML spans/styles for highlighted source. Pandoc delegates grammar handling to
Skylighting; this is a bounded PHP handoff for HTML/PHP template review blocks,
not a full Skylighting XML grammar engine or PHP template runtime.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime,
PHP template engine, browser renderer, JavaScript runtime, external highlighter,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Verification

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1142 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1161 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter coverage: `1142 -> 1161` assertions.
- Focused PHP PASS cases: `+1`.
- `lanes/pandoc/lane-status.json` `phpPass`: `1256 -> 1257`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1700 -> 1701`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `WordPressBlockWriter`, the existing
syntax-highlighting fixture, the WordPress handoff example, and lane-local
manifest/status machinery.

Full upstream runner parity remains gated on hydrating a local Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present before
any bounded non-mutating Cabal/Haskell runner plan can be marked ready.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for standalone
HTML attributes, embedded CSS and JavaScript raw-text bodies, CSS, Rust,
Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, TSX, CMake, Nginx, Twig,
Mustache/Handlebars, Mermaid, SQL/PostgreSQL, Apache, RST, Haskell, TeX/LaTeX,
diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua, TypeScript, R, Python,
C/C++, Dockerfile/Containerfile, Makefile, INI/config, TOML, Perl, Java,
XML/XSLT, Bash heredoc state, PHP heredoc/nowdoc, or Pandoc JSON theme support.

It owns only bounded PHP island token delegation inside HTML code blocks for
WordPress template review packets.

## Follow-Up

Keep full Skylighting XML parity, PHP islands inside HTML raw-text edge cases,
embedded CSS/JavaScript/PHP source-location diagnostics, richer PHP
alternative-syntax/template-token categories, writer-wide default highlighting
policy, and additional language grammars as separate bounded slices.
