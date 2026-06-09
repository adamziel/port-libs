# pandoc-syntax-highlighting-core-current-base-20260609T011120Z

Lane: `pandoc`
Accepted base: `09109401d59cee7a589aaf8125432abbe4aef718`

## Scope

This slice adds a bounded native Tcl syntax-highlighting handoff under
`lanes/pandoc/**`.

- `SyntaxHighlighter` now maps `tcl`, `tclsh`, `Tcl/Tk`, and `expect` aliases
  to `tcl`.
- The bounded tokenizer handles Tcl comments, package/proc/set/if/return/dict
  command keywords, `eq`/`ne` expression operators, variables, quoted strings,
  numbers, option flags, `exec`, `wp`, `puts`, and source punctuation.
- The WordPress syntax-highlighting fixture and handoff example now include a
  numbered Tcl migration-review script.

## Source Truth

Pandoc delegates fenced-code highlighting through its syntax-highlighting layer
and carries language classes, line numbering, style metadata, and HTML writer
handoff output. This ports only the bounded PHP support-library behavior needed
for Tcl code-block review packets. It does not implement a full Tcl parser or a
Skylighting XML engine.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting, Tcl/Tk,
external highlighter, browser renderer, JavaScript runtime, online service, live
provider test, or live-service provider test was executed.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 2272 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: clean.

Focused assertion delta: `2240 -> 2272` (`+32`).
Lane status delta: `phpPass` `2027 -> 2028`; mapped denominator
`2442 -> 2443`; added `mappedSyntaxHighlightingTclCases: 1` and
`syntaxHighlightingTclAssertions: 32`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `SyntaxHighlighter`,
`MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the existing syntax
fixture, the focused lane test runner, and the WordPress handoff example.

## Non-Overlap

This avoids accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS/Sass,
Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc,
PHPDoc/attributes, RST, TSX, CMake, Nginx, Twig, Mustache/Handlebars, Mermaid,
embedded HTML CSS/JavaScript/PHP islands, GraphQL, AsciiDoc, HCL/Terraform,
Liquid, Elm, JSONC/JSON5, LESS, Typst, Kotlin, Dart, Swift, Clojure/EDN,
Scala, Elixir, Vue, OCaml, Julia, AWK, Windows batch/CMD, MATLAB/Octave, Fish,
Sed, BibTeX/BibLaTeX, Vimscript, Scheme/Racket, CSV/TSV, Erlang, Objective-C,
Raku, Fennel, Meson/Justfile, Protobuf, custom theme metadata, token-title
metadata, and unsupported-language fallback behavior.

Next non-overlapping syntax slices can cover another unsupported Skylighting
alias family such as D, Fortran, Lisp, or Pascal.
