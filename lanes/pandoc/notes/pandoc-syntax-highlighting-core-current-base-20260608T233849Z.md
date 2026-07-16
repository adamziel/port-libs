# Pandoc Syntax Highlighting Current Base - Fennel/Fnl

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T233849Z`

Accepted base: `475b85e029e16dfc514361ae0145c8d6dab388cb`

## Scope

Added a bounded native PHP syntax-highlighting handoff for Fennel review code fences:

- `fennel`, `fnl`, and `fennel-lang` aliases normalize to `fennel`.
- Fennel comments, strings, colon-key table metadata, local/require forms, `fn`, `let`, `if`, `collect`, `when`, constants, numbers, Lua-style module function calls, symbols, and punctuation are tokenized.
- The WordPress syntax-highlighting fixture now includes a numbered Fennel review helper and the example smoke highlights it as a raw HTML block with existing style metadata.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2128 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2159 assertions, 0 failures`.
- Assertion delta: `+31` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `SyntaxHighlighter`, `MarkdownReader` fenced-code metadata, `AstNode` code blocks, existing syntax fixture/example wiring, and WordPress raw HTML handoff.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Fennel runtime, Lua runtime, external highlighter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice intentionally avoids accepted syntax-highlighting cases for CSS, Rust, AsciiDoc, HCL/Terraform, Typst, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX, sed, fish, Matlab/Octave, batch, awk, and Raku. A next non-overlapping syntax target could cover Meson/Justfile aliases or deeper Raku POD/quote forms.
