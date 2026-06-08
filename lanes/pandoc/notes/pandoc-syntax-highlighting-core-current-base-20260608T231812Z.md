# Pandoc Syntax Highlighting Current-Base Raku Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T231812Z`

Accepted base: `c2add54cd754c7dc4d03da25c95616285192e050`

## Scope

This slice adds one bounded native Raku syntax-highlighting handoff for WordPress review packets:

- aliases: `raku`, `perl6`, `pl6`, `pm6`, `p6`, `rakumod`, `rakutest`, `rakudoc`, plus Pandoc-style `language-*` wrappers
- token coverage: comments/POD markers, strings, regex literals, `:$pair` arguments, named `=>` pairs, sigiled variables, module/class/sub/multi declarations, traits, datatypes, word operators, functions, numbers, and punctuation
- fixture/example coverage: a numbered Raku code block in the existing WordPress syntax-highlighting fixture and handoff example

## Source Truth

The active supervisor contract assigns `pandoc-syntax-highlighting-core-*` to fixture-backed language alias/style/token handoff work under `lanes/pandoc/**`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Raku execution, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed. This ports the bounded handoff contract into native PHP.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed before this slice.
- Baseline focused test before edits: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2090 assertions, 0 failures`.
- Red-first probe before the source change: `raku` and `perl6` normalized to `NULL` and `raku` emitted an `unsupported-language` diagnostic.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2128 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.

Status delta: +1 PHP PASS case and +38 focused assertions. `lane-status.json` moves `phpPass` from `1961` to `1962`; `UPSTREAM_TEST_MANIFEST.json` moves the mapped denominator from `2382` to `2383` and adds `mappedSyntaxHighlightingRakuCases: 1` plus `syntaxHighlightingRakuAssertions: 38`.

## Dependency Closure

No new support component is needed. The implementation reuses native `SyntaxHighlighter` scanning, `MarkdownReader` fenced-code metadata, `AstNode` code-block attributes, `WordPressBlockWriter` raw HTML output, the existing syntax fixture/example path, and focused `SyntaxHighlighterTest.php` coverage.

## Non-Overlap

This avoids accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP islands, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, Fish, sed, BibTeX, Vimscript, Scheme/Racket, CSV/TSV, Erlang, Objective-C, custom theme metadata, token-title metadata, and unsupported-language fallback behavior.

## Follow-Up

Good next non-overlapping syntax targets are richer Raku POD/quote forms, Fennel, Meson, or another unclaimed Skylighting language alias. Keep the same no-external-runner boundary unless the supervisor explicitly assigns an upstream runner dependency audit.
