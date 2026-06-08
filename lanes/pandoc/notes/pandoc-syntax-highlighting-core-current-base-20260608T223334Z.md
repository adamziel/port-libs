# Pandoc Syntax Highlighting Erlang Current-Base Slice

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T223334Z`
- Accepted base: `a93e698ac06f7885c2a47509237e09731628d097`
- Implemented one bounded native syntax-highlighting cluster for Erlang/OTP review-packet code fences.

## Source Truth

This slice follows Pandoc/Skylighting-style language alias and token handoff expectations for fenced code review packets, but keeps the implementation local and bounded. It adds native PHP alias normalization for `erl`, `erlang`, `erlang-header`, and `hrl`, then tokenizes Erlang snippets into comments, module attributes, macros, records, atoms, variables, modules, functions, datatypes, numbers, strings, binary-string operators, and WordPress raw HTML style output.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting process, Erlang compiler, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2030 assertions, 0 failures`.
- Red-first check after adding the focused fixture/test failed with `1 test files, 2032 assertions, 1 failures` because Erlang aliases normalized to `NULL`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2061 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/SyntaxHighlighter.php`, `lanes/pandoc/tests/SyntaxHighlighterTest.php`, and `lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1928 -> 1929`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2350 -> 2351`.
- Focused assertion growth: `+31` final assertions in `SyntaxHighlighterTest.php` over the pre-slice baseline.
- Inventory keys added: `mappedSyntaxHighlightingErlangCases: 1` and `syntaxHighlightingErlangAssertions: 31`.

## Dependency Closure

No new support component is needed. This slice reuses native `SyntaxHighlighter` scanning, `MarkdownReader` fenced-code metadata, `AstNode` code blocks, and `WordPressBlockWriter` raw HTML handoff.

## Non-Overlap

This intentionally avoids accepted syntax-highlighting clusters for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, Fish, Sed, BibTeX/BibLaTeX, Vimscript, Scheme/Racket, CSV/TSV, custom theme metadata, token-title metadata, and unsupported fallback behavior.

## Follow-Up

Choose a non-overlapping bounded language handoff such as Objective-C, Raku, or another fixture-backed alias/token gap. Keep the slice native PHP only and do not run Pandoc, Cabal/Haskell runners, Skylighting, compilers, external highlighters, browser renderers, online services, live provider tests, or live-service provider tests from this lane.
