# Pandoc Syntax Highlighting Nim Handoff

Slice: `pandoc-syntax-highlighting-core-current-base-duplicate-20260609T045415Z`
Base accepted HEAD: `e3e201377d66d62da0039dedbb153200e0a6e366`
Date: 2026-06-09 UTC

## Scope

Added one bounded native PHP syntax-highlighting support cluster for Nim review
packets:

- Normalizes Pandoc/Skylighting-style Nim aliases: `nim`, `nim-lang`,
  `nim-source`, `nimrod`, `nims`, and `nimscript`.
- Tokenizes Nim comments, block comments, pragmas, raw/triple strings,
  keywords, constants, datatypes, proc/function calls, variables, operators,
  and typed numeric literals.
- Extends the WordPress syntax-highlighting fixture and example so Nim review
  packets keep numbered source-line anchors and style metadata through the
  native WordPress HTML block handoff.

## Source Truth And Non-Overlap

This slice follows the lane-local Pandoc/Skylighting alias and token handoff
contract already used by `SyntaxHighlighter`. It does not overlap the accepted
syntax-highlighting handoffs for Crystal, shell sessions, embedded HTML/PHP
islands, CMake, Groovy, Pascal, Common Lisp, D, Fortran, Tcl, Protobuf, Meson,
Justfile, Fennel, Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket,
Vimscript, BibTeX, sed, fish, MATLAB/Octave, Windows batch, awk, PHP
heredoc/nowdoc, Lua, bash heredocs, or the existing JSON/YAML/CSS/HTML/SQL
families.

No Pandoc executable, Haskell runner, external highlighter, Nim compiler,
Word/LibreOffice, zip/unzip, browser renderer, online service, live provider
test, or live-service provider test was executed.

## Evidence

Baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2550 assertions, 0 failures
```

Focused verification after implementation:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2582 assertions, 0 failures

php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+32` focused assertions in
`SyntaxHighlighterTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`SyntaxHighlighter` scanner, `MarkdownReader` fenced-code attribute parsing,
`WordPressBlockWriter` HTML block handoff, the existing focused PHP test
runner, and the lane-local WordPress syntax-highlighting example. Full upstream
Pandoc/Skylighting runner parity remains a separate upstream-runner dependency
task requiring hydrated pinned upstream sources and Haskell test executables.

## Follow-Up

Next syntax-highlighting work should choose a non-overlapping fixture-backed
alias/token gap such as V language, Idris, or another unsupported Skylighting
language alias. Keep the follow-up native PHP and external-tool free.
