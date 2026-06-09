# Pandoc Syntax Highlighting Core - Crystal

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T035524Z`

Base accepted HEAD: `4cca1c57da8720c140326c22572dbfb45205f318`

## Scope

Added one bounded native PHP syntax-highlighting handoff for Crystal review code
blocks:

- `crystal`, `cr`, `crystal-lang`, and `crystal-source` aliases normalize to
  canonical `crystal`.
- `SyntaxHighlighter` now tokenizes Crystal comments, strings, annotations,
  `require`/`module`/`struct`/`property`/`def`/`rescue` keywords, JSON namespace
  datatypes, constants, numbers, functions, variables, and Crystal operators.
- The WordPress syntax-highlighting fixture and handoff example now include a
  numbered Crystal import-review helper and preserve it as raw HTML with style
  metadata.

## Source Truth

Pandoc carries fenced-code classes, source-line numbering, and style metadata
through its syntax-highlighting path and delegates language lookup to
Skylighting. This slice ports only the bounded alias/style/token handoff needed
for Crystal review packets. It does not implement a full Crystal parser or a
full Skylighting XML engine.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting,
Crystal runtime/compiler, external highlighter, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 2470 assertions, 0 failures`
- Red-first probe before implementation:
  `crystal`, `cr`, and `crystal-lang` returned `unsupported-language`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 2503 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/SyntaxHighlighter.php`,
  `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  reported no syntax errors.
- JSON validation:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully.
- Whitespace:
  `git diff --check -- lanes/pandoc` passed.

Focused assertion delta: `2470 -> 2503` (`+33`).

Lane status delta: `phpPass` `2260 -> 2261`; mapped denominator
`2665 -> 2666`; added `mappedSyntaxHighlightingCrystalCases: 1` and
`syntaxHighlightingCrystalAssertions: 33`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the
existing syntax fixture, the WordPress syntax handoff example, and focused
`SyntaxHighlighterTest.php` coverage.

Full upstream Pandoc/Skylighting runner parity remains a separate
upstream-runner dependency task requiring hydrated pinned upstream sources and
Haskell test executables.

## Non-Overlap

This avoids accepted syntax-highlighting slices for base language/style
wrappers, line highlighting, custom themes, token titles, unsupported fallback,
PHP/PHPDoc, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript, JS/JSX/TSX,
R, Python, C/C++, C#, Java, Kotlin, Dart, Swift, Rust, Go, Scala, Elixir,
Erlang, Fennel, Scheme/Racket, Clojure/EDN, OCaml, Julia, Fortran, Tcl,
Protobuf, Meson, Justfile, Vimscript, BibTeX, sed, fish, MATLAB/Octave, AWK,
Batch, CSS/SCSS/Sass/LESS, Nix, HCL, Liquid, Elm, JSON/JSONC/YAML/TOML, SQL,
XML/XSLT, GraphQL, HTML islands, Vue, Mermaid, Mustache/Handlebars, Twig,
nginx, Apache, Dockerfile, Makefile, D, Common Lisp, Pascal, Groovy, Raku, and
Objective-C handoffs. It owns only bounded Crystal alias and token handoff for
fixture-backed WordPress review code blocks.

## Follow-Up

Choose another non-overlapping syntax-highlighting gap, such as Nim aliases,
shell-session prompts, or richer language state for an already mapped language,
while keeping the same no-external-runner boundary unless a future slice is
explicitly assigned as an upstream runner dependency audit.
