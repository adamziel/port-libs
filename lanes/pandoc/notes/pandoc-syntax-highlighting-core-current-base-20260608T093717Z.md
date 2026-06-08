# Pandoc Syntax Highlighting Elixir Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T093717Z`
Base accepted HEAD: `2b83c4721fda5d12ebc3889977c6036f00e56a51`

## Behavior

- Added bounded native Elixir syntax-highlighting support to `SyntaxHighlighter`.
- Added `elixir`, `ex`, and `exs` alias normalization.
- Preserves representative Phoenix/Elixir review snippets with comments,
  `defmodule`/`def`/`defstruct`, module attributes, `@spec`, atoms, keyword-list
  keys, module aliases, guard clauses, pipeline operators, `case`/`with`
  branches, numbered source wrappers, and WordPress raw HTML style metadata.
- Added one WordPress import review fixture block and example self-test
  coverage.

## Source Truth

- Pandoc delegates fenced-code highlighting through its syntax-highlighting
  layer and uses the Skylighting highlighter/language-definition contract.
- Skylighting derives generated highlighters from KDE XML syntax definitions;
  historical Skylighting language inventories include Elixir.
- This slice ports a bounded PHP token handoff only. It does not implement a
  full KDE XML syntax engine, full Elixir parser, Phoenix compiler behavior, or
  upstream Pandoc runner parity.

## Evidence

Baseline focused run before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1620 assertions, 0 failures
```

Red-first direct probe before implementation:

```text
normalizeLanguage("elixir") => NULL
highlight(..., "elixir") => unsupported-language
```

Intermediate focused runs after implementation exposed expected-string drift
only:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1640 assertions, 1 failures

php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1654 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1655 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: `1620 -> 1655` (`+35`).
- Focused PHP PASS cases: `+1`.
- `lanes/pandoc/lane-status.json` `phpPass`: `1598 -> 1599`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `2017 -> 2018`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter` scanning, `MarkdownReader` fenced-code metadata, `AstNode`
code-block handoff, `WordPressBlockWriter` raw HTML output, the existing
syntax-highlighting fixture/example path, and the focused PHP lane harness.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for CSS, Rust,
AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart,
Swift, Clojure/EDN, Scala, PHPDoc, PHP attributes, embedded HTML/PHP, GraphQL,
CMake, Nginx, Twig, Mustache, Mermaid, SQL/PostgreSQL, Apache, TSX,
JavaScript, C#, Java, Go, PowerShell, DOT, SCSS, Nix, XML/XSLT, shell, PHP
heredoc/nowdoc, Lua, Haskell, TeX, Markdown, diff, Ruby, R, Python, C/C++,
Dockerfile, Makefile, INI, TOML, Perl, token-title metadata, custom Pandoc JSON
themes, or unsupported language fallbacks.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting,
Elixir, Mix, Phoenix, external highlighter, browser renderer, JavaScript
runtime, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

Keep full Skylighting XML parity, richer Elixir heredoc/sigil states, compiler
diagnostics, nested HEEx/Phoenix template highlighting, Mix/build metadata, and
upstream Haskell runner comparison as separate bounded slices.
