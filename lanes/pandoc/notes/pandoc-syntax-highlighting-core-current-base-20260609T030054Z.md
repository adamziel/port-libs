# Pandoc Syntax Highlighting Core - Pascal

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T030054Z`
- Base accepted HEAD: `05069a2190fe377801777d2d97b726785a631773`
- Bounded behavior: Pascal/Object Pascal syntax highlighting aliases and token handoff for WordPress review-packet code blocks.

## Source Truth

- Pandoc/Skylighting-style contract: fenced code blocks keep requested language metadata while a canonical highlighter language drives token spans and style CSS.
- This slice maps `pascal`, `pas`, `pp`, `delphi`, `fpc`, `freepascal`, `objectpascal`, and `object-pascal` to `pascal`.
- Native tokenizer coverage is intentionally bounded to the WordPress import review fixture: `//`, `{...}`, and `(*...*)` comments, `{$...}` compiler directives, Pascal single-quoted strings, constants, datatypes, routines, numbers, variables, assignment/range operators, and statement punctuation.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` -> `1 test files, 2402 assertions, 0 failures`.
- Red probe before implementation: `pascal`, `delphi`, and `objectpascal` highlighting returned `unsupported-language` diagnostics.
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` -> `1 test files, 2434 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` -> `syntax highlighting handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/SyntaxHighlighter.php`, `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`, and `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php` reported no syntax errors.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully.
- External tools not run: Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, online services, external syntax highlighters, and live provider tests.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2196 -> 2197`.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage: `2609 -> 2610`.
- Focused assertion delta: `+32` assertions in `SyntaxHighlighterTest.php`.

## Dependency Closure

- No new support component is required.
- Reused existing native PHP support: `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the syntax highlighting fixture, and the WordPress handoff example.

## Non-Overlap

- This does not repeat the accepted Fortran, D, or Common Lisp syntax highlighting slices.
- This stays within fixture-backed language alias/style/token handoff and does not touch DOCX, EPUB, ODF, CSL, math, archive, or upstream-runner dependency work.

## Follow-Up

- Add a non-overlapping syntax highlighting language/state slice such as shell-session prompts, Erlang records, Lua long-bracket nesting, or Tcl command substitutions if needed by a later WordPress import review fixture.
- Deepen Pascal state only if a later fixture requires full Delphi/Object Pascal constructs beyond this bounded handoff.
