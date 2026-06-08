# Pandoc Syntax Highlighting Current-Base Fish Shell Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T204357Z`

Accepted base: `6479f65c1465d77f871d7146aaaa2d022aa27e3f`

## Scope

This slice adds a bounded native Fish shell syntax-highlighting handoff:

- aliases: `fish`, `fish-shell`, and Pandoc-style `language-fish`
- token coverage: comments, strings, `$variables`, simple array indexing, Fish control keywords, `set`/`source`/`command` builtins, long and short options, command-substitution operators, pipes/redirections, numeric return codes, and common WordPress import helper commands such as `jq`, `string`, `read`, `path`, `printf`, and `wp`
- fixture/example coverage: a numbered Fish code block in the existing WordPress syntax-highlighting fixture and example handoff

## Source Truth

The active supervisor contract assigns `pandoc-syntax-highlighting-core-*` to fixture-backed language alias/style/token handoff work under `lanes/pandoc/**`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Fish shell execution, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed. The slice ports the bounded handoff contract into native PHP.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed before this slice.
- Baseline focused test before edits: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1864 assertions, 0 failures`.
- Red-first probe before the source change: `fish`, `fish-shell`, and `language-fish` normalized to `NULL` and emitted `unsupported-language` diagnostics.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1889 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.

Status delta: +1 PHP PASS case and +25 focused assertions. `lane-status.json` moves `phpPass` from `1824` to `1825`; `UPSTREAM_TEST_MANIFEST.json` moves the mapped denominator from `2248` to `2249` and adds `mappedSyntaxHighlightingFishCases: 1` plus `syntaxHighlightingFishAssertions: 25`.

## Dependency Closure

No new support component is needed. The implementation reuses native `SyntaxHighlighter` scanning, `MarkdownReader` fenced-code metadata, `AstNode` code-block attributes, `WordPressBlockWriter` raw HTML output, the existing syntax fixture/example path, and focused `SyntaxHighlighterTest.php` coverage.

## Non-Overlap

This avoids accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP islands, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, custom theme metadata, token-title metadata, and unsupported-language fallback behavior.

## Follow-Up

Good next non-overlapping syntax targets are sed scripts, Erlang, Raku, Scheme/Racket, Objective-C, or richer Fish command-context state. Keep the same no-external-runner boundary unless the supervisor explicitly assigns an upstream runner dependency audit.
