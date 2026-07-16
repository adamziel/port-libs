# Pandoc Syntax Highlighting Core - Common Lisp

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T024431Z`
- Base accepted HEAD: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`
- Bounded behavior: Common Lisp syntax highlighting aliases and token handoff for WordPress review-packet code blocks.

## Source Truth

- Pandoc/Skylighting-style contract: fenced code blocks keep requested language metadata while a canonical highlighter language drives token spans and style CSS.
- This slice maps `common-lisp`, `commonlisp`, `lisp`, `lsp`, and `cl` to `commonlisp`.
- Native tokenizer coverage is intentionally bounded to the WordPress import review fixture: line/block comments, strings, character/uninterned symbols, colon keywords, special variables, `nil`/`t` constants, core type names, special forms, builtins/accessors, numbers, and list punctuation.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` -> `1 test files, 2371 assertions, 0 failures`.
- Red probe before implementation: `SyntaxHighlighter::normalizeLanguage('common-lisp')` returned `NULL` and highlighting reported `unsupported-language`.
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` -> `1 test files, 2402 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` -> `syntax highlighting handoff self-test ok`.
- PHP lint: changed PHP files reported no syntax errors.
- External tools not run: Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, online services, and live provider tests.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2177 -> 2178`.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage: `2591 -> 2592`.
- Focused assertion delta: `+31` assertions in `SyntaxHighlighterTest.php`.

## Dependency Closure

- No new support component is required.
- Reused existing native PHP support: `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the syntax highlighting fixture, and the WordPress handoff example.

## Non-Overlap

- This does not repeat the accepted Fortran or D syntax highlighting slices.
- This stays within fixture-backed language alias/style/token handoff and does not touch DOCX, EPUB, ODF, CSL, math, archive, or upstream-runner dependency work.

## Follow-Up

- Add Pascal syntax highlighting aliases and tokenizer coverage.
- Deepen Common Lisp reader-macro handling only if a later fixture requires full reader state beyond this bounded handoff.
