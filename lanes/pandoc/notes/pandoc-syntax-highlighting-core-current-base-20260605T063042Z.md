# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T063042Z`

Base accepted HEAD: `1ed87b827224c550d6a0bc5984190e8dfbaa79ec`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Makefile review handoff:
  - normalizes `make`, `Makefile`, `GNUmakefile`, and `mk` code-block aliases
    into canonical `makefile` highlighting;
  - tokenizes bounded Makefile review snippets for comments, directives,
    assignment keys, targets, variable references, recipe command words,
    options, numbers, and operators using the existing Pandoc/Skylighting-style
    short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for build/deploy migration review
    packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered Makefile review snippet so reviewers can inspect build and wp-cli
  source without invoking Make, npm, wp-cli, external highlighters, Pandoc, or
  Skylighting.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block highlighting
  to Skylighting syntax lookup by code-block classes, carries the built-in
  styles, and maps `make`/`makefile` as Makefile-style listings names.
- Skylighting's Makefile syntax definition lists Makefile directives,
  conditionals, special targets, and built-in functions as syntax categories
  for highlighting. This slice ports a bounded token handoff, not the full
  Skylighting XML syntax engine or Make parser.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/makefile.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Make, npm, wp-cli, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 314 assertions, 0 failures`
- Red check before source implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 302 assertions, 2 failures`
  - Failure shape: `make` normalized to `NULL` and the Makefile fixture fell
    back to plain text.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 338 assertions, 0 failures`
- Focused lane directory after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8130 assertions, 0 failures`
- PASS-line count check:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `693`
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
TypeScript, Python, C/C++, Dockerfile/Containerfile, or Pandoc JSON `.theme`
support. It also avoids Markdown/HTML reader coverage, XML/HTML5 DOM support,
EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC, archive compression, PDF
engine diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX
conversion, charset/Unicode, and legacy DOC/CFB slices. It owns only bounded
Makefile syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, parser-state-aware
Makefile recipe context, nested `$(call ...)` expansion highlighting, BSD make
conditional state, token title attributes, line-number color parity,
writer-wide default highlighting policy, and additional language grammars as
separate bounded slices.
