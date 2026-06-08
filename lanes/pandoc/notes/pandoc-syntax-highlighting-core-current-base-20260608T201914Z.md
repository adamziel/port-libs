# Pandoc Syntax Highlighting MATLAB/Octave Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T201914Z`
Base accepted HEAD: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Scope

Added a bounded native MATLAB/Octave syntax-highlighting handoff under the
existing `SyntaxHighlighter` path.

- Normalizes `matlab`, `matlab-source`, `matlab-octave`, `octave`,
  `gnu-octave`, `m-file`, and `m` to canonical `matlab`.
- Tokenizes bounded MATLAB/Octave review snippets for `%`/`#` comments,
  strings, `function`/`arguments`/`if`/`end` keywords, datatypes, constants,
  built-in calls, ordinary identifiers, element-wise operators, numbered source
  wrappers, and WordPress raw HTML style metadata.
- Extends the WordPress syntax-highlighting fixture/example with a technical
  note scoring snippet relevant to imported engineering/scientific documents.

This ports the format contract for Pandoc-style code-block language, style,
token, and line-number handoff. It does not implement the full Skylighting XML
grammar or a MATLAB/Octave parser.

## Source Truth

The lane has no local Pandoc/Skylighting checkout in `.upstream-cache` for this
micro-slice, so this stays within the lane's accepted static-inventory contract:
Pandoc delegates fenced-code highlighting through its syntax-highlighting layer
and preserves code-block language classes, style selection, token classes, and
numbered-line attributes for writers. This patch ports only the bounded native
PHP handoff needed by the local Markdown and WordPress review path.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime,
MATLAB/Octave runtime, external highlighter, browser renderer, JavaScript
runtime, online service, live provider test, or live-service provider test was
executed.

## Evidence

Rework-note check:

```text
ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md
# no output
```

Baseline focused syntax test before editing:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1835 assertions, 0 failures
```

Red-first direct probe before implementation:

```text
matlab norm=NULL diag=unsupported-language
octave norm=NULL diag=unsupported-language
m norm=NULL diag=unsupported-language
```

Intermediate focused run after implementation exposed scanner-order drift:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1853 assertions, 1 failures
```

The failure showed MATLAB assignment targets were being tokenized as attributes.
The final scanner leaves ordinary assignment targets as variables.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1864 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

PHP lint:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
```

## Status Delta

- Focused SyntaxHighlighter assertions: `1835 -> 1864` (`+29`).
- Focused PHP PASS cases: `+1` in the existing lane-focused syntax test file.
- `lanes/pandoc/lane-status.json` `phpPass`: `1802 -> 1803`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `2225 -> 2226`.

## Dependency Closure

No new support component is needed. This reuses native PHP `SyntaxHighlighter`
scanning, `MarkdownReader` fenced-code metadata, `AstNode` code-block
attributes, `WordPressBlockWriter` raw HTML output, the existing syntax fixture
and example path, and the focused PHP lane harness.

## Non-Overlap

This slice avoids accepted syntax-highlighting coverage for CSS, Rust, Nix,
SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache,
Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig,
Mustache/Handlebars, Mermaid, HTML embedded CSS/JavaScript/PHP, GraphQL,
AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart,
Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD,
custom theme metadata, token-title metadata, and unsupported-language fallback
coverage. It owns only the MATLAB/Octave alias and token handoff cluster.

## Follow-Up

Choose a non-overlapping fixture-backed language/state gap such as
Objective-C, Erlang, fish, sed, Raku, Scheme/Racket, or richer MATLAB state
handling. Keep it native PHP and external-tool free.
