# Pandoc Syntax Highlighting CSV/TSV Current-Base Slice

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T221658Z`
- Accepted base: `238c756134d68ede9072631361599c436a2f8d32`
- Implemented one bounded native syntax-highlighting cluster for CSV and TSV review-packet code fences.

## Source Truth

This slice follows Pandoc/Skylighting-style language alias and token handoff expectations for fenced code review packets, but keeps the implementation local and bounded. It adds native PHP alias normalization for `csv`, `comma-separated-values`, `tsv`, and `tab-separated-values`, then tokenizes delimited text into header fields, delimiters, quoted strings, numeric fields, boolean/null constants, comments, and unquoted values.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting process, external highlighter, spreadsheet/CSV engine, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2008 assertions, 0 failures`.
- Red-first check after adding the focused fixture/test failed with `1 test files, 2010 assertions, 1 failures` because `csv` aliases were unsupported.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2030 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1912 -> 1913`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2335 -> 2336`.
- Focused assertion growth: `+22` final assertions in `SyntaxHighlighterTest.php` over the pre-slice baseline.
- Inventory keys added: `mappedSyntaxHighlightingCsvTsvCases: 1` and `syntaxHighlightingCsvTsvAssertions: 22`.

## Dependency Closure

No new support component is needed. This slice reuses native `SyntaxHighlighter` alias normalization/token handoff, `MarkdownReader` fenced-code attribute parsing, `AstNode` metadata, and `WordPressBlockWriter` code-block output.

## Non-Overlap

This intentionally avoids accepted syntax-highlighting clusters for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, Fish, Sed, BibTeX/BibLaTeX, Vimscript, Scheme/Racket, custom theme metadata, token-title metadata, and unsupported fallback behavior.

## Follow-Up

Choose a non-overlapping bounded language handoff such as Objective-C, Erlang, Raku, or another fixture-backed alias/token gap. Keep the slice native PHP only and do not run Pandoc, Cabal/Haskell runners, Skylighting, external highlighters, compilers, browser renderers, online services, live provider tests, or live-service provider tests from this lane.
