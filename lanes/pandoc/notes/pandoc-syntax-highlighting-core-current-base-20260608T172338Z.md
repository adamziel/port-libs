# Pandoc Syntax Highlighting Current-Base Julia/JL Handoff

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T172338Z`
Base accepted HEAD: `19e469ac5fba851474b6c82ad19f3b8c0f411282`

## Scope

- Added bounded native Julia syntax-highlighting support to `SyntaxHighlighter`.
- Added `julia`, `jl`, `julia-source`, and `julia-repl` language alias normalization.
- Added a fixture-backed WordPress import review packet using Pandoc-style `{.jl #julia-review .numberLines startFrom=900}` code attributes.
- Added focused HTML and WordPress block assertions for macro, keyword, datatype, function, keyword-argument, constant, number-line, and style metadata handoff.

## Source Truth

This is a bounded support-library implementation for Pandoc-style code-language alias, style, token, numbered-line, and WordPress HTML block handoff. No local Pandoc/Skylighting runner was used, and no external highlighter or Julia runtime was executed.

## Verification

- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1720 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1751 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `SyntaxHighlighter`, `MarkdownReader`, `AstNode` code-block attributes, and `WordPressBlockWriter`/`wordpressHtmlBlock` style handoff. Full Pandoc/Skylighting parity, Cabal/Haskell runners, Julia execution, external highlighters, browser renderers, JavaScript runtimes, online services, live provider tests, and live-service provider tests remain out of scope.

## Non-Overlap

This patch does not touch DOCX/OpenXML, ODF/OpenDocument, YAML metadata, CSL/BibTeX, archive/compression, math/TeX, XML/HTML5 DOM, table geometry, charset/Unicode, or PDF-engine handoff behavior. It is limited to a new native syntax-highlighting Julia/JL cluster under `lanes/pandoc/**`.

## Follow-Up

Possible follow-up work should remain non-overlapping, such as Julia REPL prompts, additional Skylighting aliases, or writer default-highlight policy. Do not execute Pandoc, Cabal/Haskell runners, Skylighting, language runtimes, external highlighters, browser renderers, JavaScript runtimes, online services, live provider tests, or live-service provider tests from this lane.
