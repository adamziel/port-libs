# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T002404Z`

Base accepted HEAD: `4c0f6049be2ab0d99e8dc12a6fb071ccbc3f5783`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` language handoff for Haskell
  review snippets:
  - normalizes `haskell`, `hs`, `lhs`, `literate-haskell`, and
    `literateHaskell` to the canonical `haskell` language;
  - tokenizes bounded Haskell/LHS code for comments, strings, keywords,
    constructors/constants, module/type names, variables, numeric literals, and
    common operators using existing Pandoc/Skylighting short token classes;
  - preserves WordPress raw HTML handoff with style metadata for highlighted
    Haskell source blocks without invoking Pandoc or Skylighting.
- Updated the WordPress syntax-highlighting smoke so reviewer packets prove
  both the prior PHP/line-number path and the new Haskell/LHS source path.

## Source Truth

- Existing Pandoc manifest evidence maps upstream literate-Haskell fixtures
  including `test/lhs-test.markdown+lhs`, `test/lhs-test-markdown.native`, and
  Haskell code-block review paths.
- Existing syntax-highlighting slices already established the bounded
  Pandoc/Skylighting handoff contract: normalized language aliases, built-in
  style names, and short token classes such as `kw`, `dt`, `cn`, `va`, `co`,
  `dv`, and `op`.
- No Pandoc binary, Cabal build, Haskell runner, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, or online service was
  executed.

## Verification

- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 83 assertions, 0 failures`
  - PASS lines: 9
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4,609 assertions, 0 failures`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, and bounded native `SyntaxHighlighter` support row. Full
upstream runner parity remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and producing a Cabal solver/build
plan for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted Markdown/HTML reader coverage, line-number
anchor rendering, XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT
package parsing, ZIP/OPC relationship behavior, archive compression streams,
PDF engine fake-runner diagnostics, BibTeX/CSL, YAML, doctemplate, table
geometry, math/TeX, charset/Unicode, or legacy DOC/CFB slices. It owns only
bounded Haskell/LHS syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax definitions, incremental lexer state,
custom theme JSON parsing, remaining language grammars, line-number color
parity, and writer-wide automatic highlighting as separate bounded slices.
