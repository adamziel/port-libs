# pandoc-syntax-highlighting-core-current-base-20260609T015232Z

Lane: `pandoc`
Accepted base: `21742a408faf47b66c5937f3cfd9d335c203497c`

## Scope

This slice adds a bounded native Fortran syntax-highlighting handoff under
`lanes/pandoc/**`.

- `SyntaxHighlighter` now maps `f`, `f77`, `f90`, `f95`, `f03`, `f08`,
  `f18`, `for`, `ftn`, `fortran`, `fortran-free`, and `fortran-fixed`
  aliases to `fortran`.
- The bounded tokenizer handles Fortran comments, strings, free-form
  continuation markers, declarations, attributes, keywords, logical constants,
  datatypes, common intrinsics, keyword arguments, variables, and source
  punctuation.
- The WordPress syntax-highlighting fixture and handoff example now include a
  numbered Fortran module review snippet.

## Source Truth

Pandoc carries fenced-code language aliases, code block attributes, source-line
numbering, style metadata, and highlighted HTML handoff through its syntax
highlighting path. This patch ports only the bounded PHP support-library
contract needed for Fortran review packets. It does not implement full
Skylighting grammar parity or a full Fortran compiler parser.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting,
Fortran compiler, external highlighter, browser renderer, JavaScript runtime,
online service, live provider test, or live-service provider test was executed.

## Verification

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed.
- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  passed with `1 test files, 2309 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  passed with `1 test files, 2341 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  passed with `syntax highlighting handoff self-test ok`.
- PHP lint passed for changed PHP files:
  `lanes/pandoc/src/SyntaxHighlighter.php`,
  `lanes/pandoc/tests/SyntaxHighlighterTest.php`, and
  `lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

Focused assertion delta: `2309 -> 2341` (`+32`).
Focused PHP PASS delta: `+1`.
Mapped denominator delta: `2501 -> 2502`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `SyntaxHighlighter`,
`MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the existing syntax
fixture, the focused lane test runner, and the WordPress handoff example.

## Non-Overlap

This avoids accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS/Sass,
Go, PowerShell, DOT, JavaScript, C#, TypeScript/TSX, CMake, Nginx, Twig,
Mustache/Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP islands,
GraphQL, PHP attributes/PHPDoc, AsciiDoc, HCL/Terraform, Liquid, Elm, JSONC,
LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN, Scala, Elixir, Vue, OCaml,
Julia, SQL/PostgreSQL, Apache, RST, Haskell, TeX/LaTeX, diff, Markdown, Ruby,
Lua, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, Java, XML/XSLT,
Bash, AWK, Windows batch/CMD, MATLAB/Octave, Fish, Sed, BibTeX/BibLaTeX,
Vimscript, Scheme/Racket, CSV/TSV, Erlang, Objective-C, Raku, Fennel,
Meson/Justfile, Protobuf, Tcl, line-highlight metadata, custom theme metadata,
token-title metadata, and unsupported-language fallback behavior.

## Follow-Up

Choose another non-overlapping syntax-highlighting support gap, such as D,
Common Lisp, or Pascal, or deepen fixed-form Fortran label/continuation
handling with fixture-backed tests while keeping the no-external-runner
boundary unless a future slice is explicitly assigned as an upstream runner
dependency audit.
