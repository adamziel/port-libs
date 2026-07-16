# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260604T232328Z`

Base accepted HEAD: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Behavior Added

- Added `SyntaxHighlighter` as a bounded native PHP support helper for code
  language/style/token handoff:
  - normalizes common Pandoc/Skylighting language aliases such as `language-js`,
    `html5`, `yml`, and `postgresql`;
  - ignores structural code block classes such as `sourceCode` and
    `numberLines` when selecting the language;
  - tokenizes bounded PHP, HTML/XML, JSON, YAML, SQL, JavaScript, Python,
    Bash, and CSS review snippets into Pandoc/Skylighting-style token classes
    such as `kw`, `fu`, `st`, `co`, `dv`, `ot`, `va`, `op`, and `pp`;
  - emits built-in highlight-style CSS metadata for Pandoc-style style names
    including `pygments`, `tango`, `espresso`, `zenburn`, `kate`,
    `monochrome`, `breezedark`, and `haddock`;
  - returns explicit unsupported-language diagnostics while preserving escaped
    plain code instead of dropping or executing source text;
  - renders a WordPress raw HTML block containing style metadata and
    highlighted code spans for reviewer handoff.
- Added a fixture-backed WordPress syntax-highlight packet and an example
  smoke for PHP migration snippets.

## Source Truth

- This slice implements the bounded `pandoc-syntax-highlighting-core` support
  row recorded by the upstream-runner dependency audits. It mirrors Pandoc's
  high-level handoff contract: language aliases, built-in highlight style
  names, and short token class names used by Skylighting-backed HTML output.
- No Pandoc binary, Cabal build, Haskell test executable, Skylighting runtime,
  external highlighter, browser renderer, online sanitizer, or online service
  was executed.
- This is intentionally not full Skylighting XML syntax-definition parity,
  incremental lexer state support, line-number anchors, custom theme parsing,
  or writer-wide automatic highlighting.

## Verification

- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 46 assertions, 0 failures`
  - PASS lines: 6
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `15 test files, 3,779 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This activates the already-audited
`pandoc-syntax-highlighting-core` bounded support row with native PHP code and
focused tests. It reuses the existing `AstNode` and Markdown reader code-block
attributes. Full upstream runner parity remains gated on hydrating the Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and producing a Cabal
solver/build plan for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted Markdown/HTML reader coverage, XML/HTML5
DOM support, EPUB3 package handoff, DOCX body/media/comment/bookmark/field-code
support, ZIP/OPC relationship graph behavior, archive compression streams, PDF
engine fake-runner diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry,
math/TeX, charset/Unicode, or legacy DOC/CFB FIB preflight slices. It owns only
bounded syntax-highlighting language/style/token handoff and the WordPress
highlighted-code smoke.

## Follow-Up

Keep full Skylighting XML syntax definitions, line-number anchors, theme JSON
parsing, additional language grammars, incremental lexer state, and writer-wide
automatic highlighting as separate bounded slices.
