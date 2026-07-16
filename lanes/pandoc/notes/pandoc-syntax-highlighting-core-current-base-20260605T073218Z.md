# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T073218Z`

Base accepted HEAD: `cd0986bc277453245fc4dca9a970b9fe374ee8e7`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` R script handoff:
  - normalizes `r`, `Rscript`, `S`, and `q` code-block aliases into canonical
    `r` highlighting;
  - tokenizes bounded R review snippets for comments, control words,
    constants, strings, backquoted identifiers, named arguments, assignment,
    namespace calls, native pipe operators, functions, variables, and numeric
    literals using the existing Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for data-analysis migration review
    packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered R review snippet so reviewers can inspect import-analysis code
  without invoking Pandoc, R, Skylighting, external highlighters, or browser
  renderers.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes, carries
  built-in style names, and maps listings `r`/`s` names to R/S highlighting.
- Skylighting's R XML syntax definition declares `R Script`, extensions
  `*.R`, `*.r`, `*.S`, `*.s`, and `*.q`, and categories for control words,
  reserved constants, comments/headlines, strings, backquoted identifiers,
  function calls, assignment operators, other operators, numbers, and numeric
  suffixes. This slice ports a bounded token handoff, not the full KDE XML
  state machine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/r.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, R
  runtime, Skylighting runtime, external highlighter, browser renderer,
  online sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or
  online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 359 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 384 assertions, 0 failures`
- Focused lane directory after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8678 assertions, 0 failures`
- PASS-line count check:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `728`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, WordPress writer opt-in, Haskell,
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua,
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, JSX/React, or
Pandoc JSON `.theme` support. It also avoids Markdown/HTML reader coverage,
XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC,
archive compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate,
table geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB
slices. It owns only bounded R script syntax-highlighting alias and token
handoff.

## Follow-Up

Keep Roxygen comments, R Markdown chunk option highlighting, Shiny-specific
HTML/R mixed snippets, parser-state-aware R formula context, full
KDE/Skylighting XML syntax-definition parity, token title attributes,
incremental lexer state, writer-wide default highlighting policy, and
additional language grammars as separate bounded slices.
