# Pandoc Syntax Highlighting Scala Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T073140Z`
Base accepted HEAD: `fc2ba34a75a980b11ada0190096c265328c8d9ce`

## Behavior

- Added bounded native Scala syntax-highlighting support to `SyntaxHighlighter`.
- Added `scala`, `sbt`, and `scala-sbt` alias normalization.
- Preserves representative Scala 3 package/import paths, annotations, case
  classes, generic types, keyword declarations, singleton objects, placeholder
  lambdas, string interpolation, booleans, constants, operators, numbered
  source wrappers, and WordPress raw HTML style metadata.
- Added a WordPress import review fixture block and example self-test coverage.

## Evidence

Baseline focused run before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1588 assertions, 0 failures
```

Red-first direct probe before implementation:

```text
normalizeLanguage("scala") => NULL
highlight(..., "scala") => unsupported-language
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1620 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: `1588 -> 1620` (`+32`).
- Focused PHP PASS cases: `+1`.
- `lanes/pandoc/lane-status.json` `phpPass`: `1562 -> 1563`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1983 -> 1984`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter` scanning, `MarkdownReader` fenced-code metadata, `AstNode`
code-block handoff, `WordPressBlockWriter` raw HTML output, the existing
syntax-highlighting fixture/example path, and focused PHP tests.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for CSS, Rust,
AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart,
Swift, Clojure/EDN, PHPDoc, PHP attributes, embedded HTML/PHP, GraphQL, CMake,
Nginx, Twig, Mustache, Mermaid, SQL/PostgreSQL, Apache, TSX, JavaScript, C#,
Java, Go, PowerShell, DOT, SCSS, Nix, XML/XSLT, shell, PHP heredoc/nowdoc,
Lua, Haskell, TeX, Markdown, diff, Ruby, R, Python, C/C++, Dockerfile,
Makefile, INI, TOML, Perl, token-title metadata, custom Pandoc JSON themes, or
unsupported language fallbacks.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Scala,
sbt, external highlighter, browser renderer, JavaScript runtime, online
service, live provider test, or live-service provider test was executed.

## Follow-Up

Keep full Skylighting XML parity, richer Scala XML literal and interpolator
state, build.sbt DSL refinements, nested-language cross-highlighting, and
upstream Haskell runner comparison as separate bounded slices.
