# Pandoc Syntax Highlighting Kotlin Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260607T154221Z`
Base accepted HEAD: `ae851dc273eeed6158fd120747071605c45efcaa`

## Behavior

- Added bounded native Kotlin syntax-highlighting support to `SyntaxHighlighter`.
- Added `kotlin`, `kt`, `kts`, `kotlin-script`, and `kotlinscript` aliases.
- Preserves representative Kotlin package/import paths, annotations, data
  classes, nullable and generic types, function declarations, generic decode
  calls, safe-call chains, Elvis fallback, map entries, numbered source
  wrappers, and WordPress raw HTML style metadata.
- Added a WordPress Android/mobile import review fixture block and example
  self-test coverage.

## Evidence

Baseline focused run before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1443 assertions, 0 failures
```

Red-first direct probe before implementation:

```text
normalizeLanguage("kotlin") => NULL
highlight(..., "kt") => unsupported-language
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1475 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: `1443 -> 1475` (`+32`).
- Focused PHP PASS cases: `+1`.
- `lanes/pandoc/lane-status.json` `phpPass`: `1525 -> 1526`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1945 -> 1946`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter` scanning, `MarkdownReader` fenced-code metadata, `AstNode`
code-block handoff, `WordPressBlockWriter` raw HTML output, the existing
syntax-highlighting fixture/example path, and focused PHP tests.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for CSS, Rust,
AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, PHPDoc, PHP
attributes, embedded HTML/PHP, GraphQL, CMake, Nginx, Twig, Mustache, Mermaid,
SQL/PostgreSQL, Apache, TSX, JavaScript, C#, Java, Go, PowerShell, DOT, SCSS,
Nix, XML/XSLT, shell, PHP heredoc/nowdoc, Lua, Haskell, TeX, Markdown, diff,
Ruby, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, token-title
metadata, custom Pandoc JSON themes, or unsupported language fallbacks.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Kotlin
compiler, Gradle, external highlighter, browser renderer, JavaScript runtime,
online service, live provider test, or live-service provider test was executed.

## Follow-Up

Keep full Skylighting XML parity, richer Kotlin string interpolation state,
Gradle Kotlin DSL-specific highlighting, Android resource/XML cross-highlighting,
and upstream Haskell runner comparison as separate bounded slices.
