# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260609T021953Z`
Lane: `pandoc`
Accepted base: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`

## Implementation

- Added bounded native D syntax-highlighting aliases to `SyntaxHighlighter`:
  `d`, `dlang`, `d-source`, and `d-language`.
- Added a shallow D scanner for WordPress review packets covering comments,
  raw/regular strings, `@safe`-style attributes, preprocessor directives,
  D keywords, constants, built-in datatypes, numeric literals, function names,
  D template-call syntax such as `format!"..."(...)`, variables, and operators.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered D code block for an import-review helper.

## Source Truth

Pandoc hands fenced-code classes through its syntax-highlighting contract and
uses Skylighting syntax lookup for language aliases. This slice ports one
bounded native PHP D-language token handoff for review packets; it does not try
to reproduce full KDE/Skylighting parser-state behavior.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
- Result: `1 test files, 2341 assertions, 0 failures`

Red probe before edits:

- `php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $r = $h->highlight("module wp.import.review;", "d"); var_export([$r["language"], $r["diagnostics"][0]["code"] ?? null, $r["html"]]); echo "\n";'`
- Result: unsupported language fallback with empty canonical language and
  `unsupported-language` diagnostic.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
- Result: `1 test files, 2371 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
- Result: `syntax highlighting handoff self-test ok`

Focused assertion delta: `2341 -> 2371` (`+30`) with one new PHP PASS case.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`SyntaxHighlighter` scanner, `MarkdownReader` fenced-code metadata,
`WordPressBlockWriter` raw HTML handoff, and the existing syntax-highlighting
example path. Full Pandoc runner parity, Skylighting/Haskell runner parity,
external highlighters, browser renderers, JavaScript runtimes, online services,
live provider tests, and live-service provider tests remain separate bounded
follow-up work.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for base
language/style wrappers, numbered lines, token-title attributes, custom Pandoc
theme JSON, PHP/PHPDoc, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript,
JSX/TSX, R, Python, C/C++, C#, Java, Kotlin, Dart, Swift, Rust, Go, Scala,
Elixir, Erlang, Fennel, Scheme/Racket, Clojure/EDN, OCaml, Julia, Fortran,
Tcl, Protobuf, Meson, Just, Vimscript, BibTeX, sed, fish, MATLAB/Octave, AWK,
Batch, CSS/SCSS/Sass/LESS, Nix, HCL, Liquid, Elm, JSON/JSONC/YAML/TOML, SQL,
XML/XSLT, GraphQL, HTML islands, Vue, Mermaid, Mustache/Handlebars, Twig,
nginx, Apache, Dockerfile, or Makefile handoffs. It owns only bounded D alias
and token handoff for fixture-backed WordPress review code blocks.

## Follow-Up

Keep full D parser-state handling, nested `/+ +/` comment depth, token strings,
mixins, Ddoc comment semantics, Common Lisp, Pascal, external highlighter
parity, and writer-wide default highlighting policy as separate bounded slices.
