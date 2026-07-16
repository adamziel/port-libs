# Pandoc Syntax Highlighting Core Current Base

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T143710Z`

Base accepted HEAD: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`

## Behavior

- Added bounded native OCaml syntax-highlighting handoff to `SyntaxHighlighter`.
- Normalizes `ocaml`, `ml`, `mli`, `ocaml-interface`, `reason`, and `reasonml` to canonical `ocaml`.
- Tokenizes OCaml comments, strings, character literals, attributes/extensions, labels/record fields, keywords, constants, built-in datatypes, modules/constructors, numeric literals, functions, variables, and operators into existing Pandoc-style classes.
- Added a WordPress review fixture using a Pandoc `.ml` code block with numbered lines and `#ocaml-review` anchors.
- Extended the WordPress syntax-highlighting example smoke to verify OCaml HTML and WordPress block output.

## Source Truth

This slice follows the lane contract for `pandoc-syntax-highlighting-core-*`: port the bounded language alias/style/token handoff needed by Pandoc conversion without invoking Pandoc, Skylighting, Haskell runners, compilers, or external highlighters. No hydrated Pandoc/Skylighting checkout is present in this isolated worktree, so this is a fixture-backed native PHP handoff aligned with the existing syntax-highlighting support rows rather than upstream-runner parity.

## Red-First Probe

Before the change, a focused alias probe showed these labels normalized to `NULL`: `ocaml`, `ml`, `reason`, and `reasonml`. The existing `SyntaxHighlighterTest.php` baseline passed at `1 test files, 1690 assertions, 0 failures`.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1720 assertions, 0 failures`
  - Delta: `+30` focused assertions and one new passing test case.
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.

## Status Delta

- `lanes/pandoc/lane-status.json`
  - `phpPass`: `1689 -> 1690`
  - `phpFail`: `0`
  - `latestFocusedSlice`: `pandoc-syntax-highlighting-core-current-base-20260608T143710Z`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - `benchmarkDenominator.mapped`: `2109 -> 2110`
  - Added one mapped native OCaml/Reason syntax-highlighting support case with `30` focused assertions.

## Dependency Closure

No new native PHP support component is needed. The patch reuses `SyntaxHighlighter` alias normalization and scanner token classes, `MarkdownReader` fixture parsing, and `WordPressBlockWriter` raw HTML handoff. External execution remains excluded: Pandoc, Cabal/Haskell runners, Skylighting, OCaml/Reason compilers, external highlighters, browser renderers, JavaScript runtimes, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat the accepted CSS, Rust, AsciiDoc, Typst, Scala, Elixir, Vue SFC, HCL/Terraform, PHP island, or XML/HTML5 DOM support slices. The new coverage is specifically OCaml/ML requested-language wrappers plus Reason alias normalization.

## Follow-Up

Potential syntax-highlighting follow-ups should choose a non-overlapping language/state gap such as Scheme/Racket, Julia, MATLAB/Octave, Objective-C, or richer OCaml module-signature/state handling.
