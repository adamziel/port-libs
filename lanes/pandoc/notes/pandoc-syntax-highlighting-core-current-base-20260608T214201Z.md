# Pandoc Syntax Highlighting Current-Base Vimscript Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T214201Z`

Base: `a0d85bbfea71fbea16acdfcda87bce21bb3681b0`

## Scope

This slice adds bounded native PHP Vimscript syntax-highlighting handoff support:

- aliases: `vim`, `vimscript`, `viml`, `vim-script`, and Pandoc-style `language-vim-script`
- token coverage: source comments, Vim command/control keywords, `v:true`/`v:false` constants, scoped variables such as `g:`/`s:`/`l:`/`a:`, options, user-command flags, function calls, strings, regex literals, line mappings, and hex highlight colors
- fixture/example coverage: a numbered Vimscript code block in the existing WordPress syntax-highlighting fixture and example handoff

The active supervisor contract assigns `pandoc-syntax-highlighting-core-*` to fixture-backed language alias/style/token handoff work under `lanes/pandoc/**`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Vim, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Evidence

Baseline focused test before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1943 assertions, 0 failures
```

Red-first alias probe before the source change:

```text
vim => language='', diag=unsupported-language
vimscript => language='', diag=unsupported-language
language-vim => language='', diag=unsupported-language
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1973 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Status delta: +1 PHP PASS case and +30 focused assertions. `lane-status.json` moves `phpPass` from `1880` to `1881`; `UPSTREAM_TEST_MANIFEST.json` moves the mapped denominator from `2303` to `2304` and adds `mappedSyntaxHighlightingVimscriptCases: 1` plus `syntaxHighlightingVimscriptAssertions: 30`.

## Dependency Closure

No new support component is needed. The implementation reuses native `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the existing syntax-highlighting fixture, the WordPress handoff example, and focused `SyntaxHighlighterTest.php` coverage.

The local Pandoc upstream cache was not present in this isolated worktree, so this handoff uses the accepted lane manifest/status and does not claim upstream runner parity.

## Non-Overlap

This avoids accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP islands, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, Fish, Sed, BibTeX/BibLaTeX, custom theme metadata, token-title metadata, and unsupported-language fallback behavior.

It owns only bounded Vimscript alias and token handoff.

## Follow-Up

Future syntax-highlighting work should choose a non-overlapping bounded language or embedded-state gap such as CSV/TSV table snippets or richer Markdown fenced-code delegation.
