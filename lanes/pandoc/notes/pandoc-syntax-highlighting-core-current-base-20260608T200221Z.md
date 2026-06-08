# Pandoc syntax highlighting current-base batch/CMD handoff

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T200221Z`
Base accepted HEAD: `e4416a27234df3582c58620f35f477531567f5a3`

## Scope

Added a bounded native Windows batch/CMD syntax-highlighting handoff under the existing `SyntaxHighlighter` support path. The new cluster maps `bat`, `batch`, `batchfile`, `cmd`, `cmd.exe`, and `dosbatch` aliases to `batch`, then highlights Windows import-review scripts with REM comments, labels, percent variables, `%~dp0` arguments, delayed `!ERRORLEVEL!` variables, `%%` loop variables, CMD comparison operators, slash/long switches, `wp`/`php` command tokens, numbered source wrappers, and WordPress raw HTML style metadata.

This deliberately does not run Pandoc, Cabal/Haskell runners, Skylighting, external highlighters, browser renderers, JavaScript runtimes, online sanitizers, online services, live provider tests, or live-service provider tests.

## Evidence

No lane rework note existed for this pandoc slice before editing.

Red-first unsupported alias probe before the patch:

```text
bat norm=null diag=unsupported-language
batch norm=null diag=unsupported-language
cmd norm=null diag=unsupported-language
dosbatch norm=null diag=unsupported-language
```

Focused verification after the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1835 assertions, 0 failures
```

Baseline focused verification before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1807 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Focused assertion delta: `+28`.
Focused PHP PASS delta: `+1` test case in the existing lane-focused test file.
Mapped denominator delta: `2196 -> 2197`.

## Non-Overlap

This slice avoids the already accepted syntax-highlighting clusters for CSS, Rust, Nix, SCSS, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc/PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid, HTML embedded CSS/JS/PHP, GraphQL, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml, Julia, and AWK. It only adds the Windows batch/CMD alias and token handoff cluster.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `SyntaxHighlighter`, `MarkdownReader`, and WordPress HTML block handoff. The next syntax-highlighting task should pick a non-overlapping fixture-backed alias/token family such as R, MATLAB/Octave, Objective-C, or Erlang.
