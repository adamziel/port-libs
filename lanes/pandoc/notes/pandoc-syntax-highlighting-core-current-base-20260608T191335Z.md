# Pandoc Syntax Highlighting AWK Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T191335Z`
Base accepted HEAD: `d057fc34a05090199b091f73d0a8aa3124240396`

## Behavior

- Added native AWK language aliases for `awk`, `gawk`, `mawk`, `nawk`, and `awk-script`.
- Added a bounded AWK tokenizer for comments, `BEGIN`/`END` regions, control keywords, AWK print/getline/function keywords, built-in variables, field variables, strings, regex literals, char-class regexes, numeric literals, functions, and operators.
- Added a WordPress export-review AWK fixture and example handoff with numbered line anchors starting at `940`.

## Source Truth

- Upstream source truth: Skylighting AWK syntax definition at `skylighting-core/xml/awk.xml` from `jgm/skylighting`, including AWK aliases/extensions, comment/string/regex contexts, field variables, built-in variables, keywords, control flow, functions, and `BEGIN`/`END` region markers.
- Red-first local probe before implementation:
  - `SyntaxHighlighter::normalizeLanguage('awk')` returned `NULL`.
  - `highlight('BEGIN { print "ok" }', 'awk')` rendered as plain text with no normalized language.

## Verification

- Baseline focused syntax test before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1778 assertions, 0 failures`.
- Final focused syntax test:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1807 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses `SyntaxHighlighter`, `MarkdownReader` fixture parsing, `SyntaxHighlighterTest`, and the WordPress syntax-highlighting example. Pandoc, Cabal/Haskell runners, Skylighting executables, AWK interpreters, external highlighters, browser renderers, JavaScript runtimes, online services, live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This does not repeat accepted syntax highlighting CSS, Rust, AsciiDoc, Typst, Vue, OCaml, Julia, HTML/PHP island, or custom-theme slices. It is limited to AWK/gawk token handoff for WordPress review filters.

## Next Task

Choose a non-overlapping Skylighting-backed language or style/token gap such as sed, fish, Raku, or additional theme/token metadata, and keep it native PHP with focused fixture-backed tests.
