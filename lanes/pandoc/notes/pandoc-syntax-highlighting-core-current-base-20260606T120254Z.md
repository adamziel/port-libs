# Pandoc Syntax Highlighting AsciiDoc Runbook Slice

Date: 2026-06-06 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260606T120254Z
Accepted base: d47b3c76a0d3fdd485e8cd24ceaaf45bbcc209b6

## Scope

This slice adds bounded native PHP syntax-highlighting support for AsciiDoc
review packets. It maps `asciidoc`, `adoc`, `asc`, and `asciidoctor` aliases to
one tokenizer and carries highlighted output through Markdown fenced-code
attributes plus the WordPress syntax-highlighting handoff example.

The tokenizer covers the conversion-relevant contract for imported documentation
runbooks:

- AsciiDoc comments, headings, document attributes, anchors, admonitions,
  block/inline macros, source-listing delimiters, listing bodies, and callouts.
- Pandoc-style numbered source wrappers, source language classes, style
  metadata, and WordPress raw HTML block handoff.

## Source Truth

Pandoc's syntax-highlighting handoff is driven by code-block language classes
and delegates language definitions to Skylighting. This patch ports the bounded
format contract needed by local AsciiDoc/adoc review packets; it does not
implement a full AsciiDoc parser, a full Skylighting XML engine, or source-code
delegation inside AsciiDoc listings.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime,
AsciiDoc processor, external highlighter, browser renderer, JavaScript runtime,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline focused test before editing:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1208 assertions, 0 failures
```

Red-first alias probe before implementation:

```text
normalizeLanguage("adoc") => NULL
normalizeLanguage("asciidoc") => NULL
highlight("...", "adoc") diagnostic => unsupported-language
```

An intermediate focused test run after adding the fixture and first tokenizer
shape failed with one AsciiDoc assertion mismatch, exposing over-classified
plain prose and URL macro target splitting. The scanner now lets URL targets win
after macro prefixes and leaves ordinary prose as text.

## Verification

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1239 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: 1208 -> 1239.
- Focused PHP PASS cases: +1.
- `lanes/pandoc/lane-status.json` `phpPass`: 1323 -> 1324.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: 1737 -> 1738.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the
existing syntax-highlighting fixture, the WordPress handoff example, and the
focused PHP test harness.

The upstream-runner blocker remains unchanged: full upstream Pandoc runner
parity still needs a hydrated Pandoc checkout at
0640c4c9859aa5a3ede082c190fcd5883c24ac83 plus Cabal project/package files and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for PHP 8
attributes/enums, GraphQL, HTML embedded CSS/JavaScript/PHP islands, Mermaid,
Mustache/Handlebars, Twig, Nginx, CMake, TSX, RST, Apache, SQL/PostgreSQL, C#,
JavaScript, Graphviz DOT, PowerShell, Go, SCSS/Sass, Nix, Rust, CSS, shell
heredocs, PHP heredoc/nowdoc, XML/XSLT, Java, Perl, TOML, INI, Makefile,
Dockerfile, C/C++, Python, R, JSX, TypeScript, Lua, Ruby, Markdown, diff,
TeX/LaTeX, Haskell, token titles, custom Pandoc JSON themes, or unsupported
language fallbacks.

It owns only bounded AsciiDoc/adoc runbook alias and token handoff.

## Follow-Up

Keep full Skylighting XML parity, richer AsciiDoc block/inline macro state,
table/list passthrough parsing, embedded source-language delegation inside
AsciiDoc listings, complete theme coverage, and upstream Haskell runner
comparison as separate bounded syntax-highlighting slices.
