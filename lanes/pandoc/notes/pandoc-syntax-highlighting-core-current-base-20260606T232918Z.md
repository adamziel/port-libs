# Pandoc Syntax Highlighting AsciiDoc Source Listing Slice

Date: 2026-06-06 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260606T232918Z
Accepted base: 4ef8e94178706df6f4d32e99bcf89492aec2d4f5

## Scope

This slice adds bounded native PHP syntax-highlighting delegation for AsciiDoc
source listings. When an AsciiDoc/adoc review packet contains a `[source,lang]`
attribute immediately before a listing delimiter, the listing body is tokenized
with the existing bounded language highlighter for that `lang`. Unsupported
source languages keep the prior generic listing-body fallback.

The focused fixture path is WordPress review documentation containing:

- `[source,php]` followed by a `----` listing.
- PHP token handoff for `echo`, `esc_html`, `$title`, operators, and trailing
  reviewer comments/callouts.
- A direct `[source,js,linenums]` probe to ensure additional AsciiDoc source
  options do not block language detection.
- A direct unsupported source-language probe to preserve the safe generic
  listing fallback.

## Source Truth

Pandoc preserves code-block language classes for syntax highlighting and
delegates tokenization to Skylighting. The previous bounded AsciiDoc slice
intentionally left embedded source-language delegation inside AsciiDoc listings
as follow-up work. This slice ports that bounded format contract without
implementing a full AsciiDoc parser, full Skylighting XML engine, or external
AsciiDoc processor.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, Skylighting
runtime, AsciiDoc processor, external highlighter, browser renderer,
JavaScript runtime, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Red-First Evidence

Baseline focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1260 assertions, 0 failures
```

Red-first source-listing probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; ... highlight("[source,php]\n----\necho esc_html($title); // reviewed output <1>\n----", "adoc") ...'
red probe failed as expected: AsciiDoc source listing body is not delegated to PHP tokenizer
```

## Verification

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1270 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: 1260 -> 1270.
- Focused PHP PASS cases: +1.
- `lanes/pandoc/lane-status.json` `phpPass`: 1417 -> 1418.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: 1830 -> 1831.

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

This slice does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, token-title attributes, custom
Pandoc JSON themes, PHP 8 attributes/enums, PHPDoc annotations, HTML embedded
CSS/JavaScript/PHP islands, GraphQL, Mermaid, Mustache/Handlebars, Twig,
Nginx, CMake, TSX, RST, Apache, SQL/PostgreSQL, C#, JavaScript, Graphviz DOT,
PowerShell, Go, SCSS/Sass, Nix, Rust, CSS, shell heredocs, XML/XSLT, Java,
Perl, TOML, INI, Makefile, Dockerfile, C/C++, Python, R, JSX, TypeScript, Lua,
Ruby, Markdown, diff, TeX/LaTeX, Haskell, or unsupported-language fallback
diagnostics.

It owns only bounded AsciiDoc `[source,lang]` listing-body token delegation.

## Follow-Up

Keep full Skylighting XML parity, richer AsciiDoc table/list passthrough,
additional source-listing delimiter forms, embedded-language tokenization
inside PHP strings, and upstream Haskell runner comparison as separate bounded
syntax-highlighting slices.
