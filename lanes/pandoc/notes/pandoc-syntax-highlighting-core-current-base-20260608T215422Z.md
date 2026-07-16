# Pandoc Syntax Highlighting Current-Base Scheme/Racket Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T215422Z`

Base: `d291953d10cb3a81d9c31878d6d7b3226cc33af0`

## Scope

This slice adds bounded native PHP Scheme/Racket syntax-highlighting handoff support:

- aliases: `scheme`, `scm`, `racket`, `rkt`, and Pandoc-style `language-racket`
- token coverage: `#lang`, line and block comments, strings, booleans, character constants, quoted symbols, `#:` keyword arguments, core forms such as `struct`, `define`, `let*`, `if`, `match`, `for/list`, and `provide`, selected review-helper builtins, datatypes, variables, and S-expression operators
- fixture/example coverage: a numbered Racket WordPress review code block in the existing syntax-highlighting fixture and example handoff

The active supervisor contract assigns `pandoc-syntax-highlighting-core-*` to fixture-backed language alias/style/token handoff work under `lanes/pandoc/**`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Racket/Scheme runtime, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Evidence

Baseline from the immediately previous accepted syntax slice:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1973 assertions, 0 failures
```

Red-first alias probe before the source change:

```text
scheme norm=NULL lang='' diag=unsupported-language
scm norm=NULL lang='' diag=unsupported-language
racket norm=NULL lang='' diag=unsupported-language
rkt norm=NULL lang='' diag=unsupported-language
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2008 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Lint, JSON, and diff checks:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'
manifest json ok

git diff --check -- lanes/pandoc
```

Status delta: +1 PHP PASS case and +35 focused assertions. `lane-status.json` moves `phpPass` from `1895` to `1896`; `UPSTREAM_TEST_MANIFEST.json` moves the mapped denominator from `2317` to `2318` and adds `mappedSyntaxHighlightingSchemeRacketCases: 1` plus `syntaxHighlightingSchemeRacketAssertions: 35`.

## Dependency Closure

No new support component is needed. The implementation reuses native `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the existing syntax-highlighting fixture, the WordPress handoff example, and focused `SyntaxHighlighterTest.php` coverage.

The local Pandoc upstream cache was not present in this isolated worktree, so this handoff uses the accepted lane manifest/status and does not claim upstream runner parity.

## Non-Overlap

This avoids accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP islands, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, Fish, Sed, BibTeX/BibLaTeX, Vimscript, custom theme metadata, token-title metadata, and unsupported-language fallback behavior.

It owns only bounded Scheme/Racket alias and token handoff.

## Follow-Up

Future syntax-highlighting work should choose a non-overlapping bounded language or embedded-state gap such as CSV/TSV table snippets, richer Markdown fenced-code delegation, or another unsupported Skylighting alias family.
