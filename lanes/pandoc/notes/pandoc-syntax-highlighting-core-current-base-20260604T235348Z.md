# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260604T235348Z`

Base accepted HEAD: `d06a1fbbeb92cce81238ad305c8b5a4fc4d9e7b6`

## Behavior Added

- Extended the bounded native `SyntaxHighlighter` line-number handoff:
  - treats `numberLines`, `number-lines`, `number`, `lineAnchors`, and
    `line-anchors` as Pandoc structural code-block classes, not languages;
  - consumes code-block `id`, classes, and `startFrom` attributes from the
    existing AST;
  - renders numbered highlighted HTML with Pandoc/Skylighting-style
    `numberSource` containers, line span IDs, line anchors, and code
    `counter-reset` style;
  - preserves escaped plain-text fallback for unsupported languages while
    retaining requested line numbering.
- Updated the WordPress syntax-highlighting smoke so reviewer handoff output
  proves the numbered/anchored source block path in addition to normal
  highlighted PHP snippets.

## Source Truth

- Pandoc User Guide, fenced code attributes and syntax highlighting
  (`https://pandoc.org/demo/example33/15-syntax-highlighting.html` and
  `https://pandoc.org/MANUAL.txt`):
  `numberLines` / `number-lines`, `startFrom`, and `lineAnchors` /
  `line-anchors`.
- Pandoc `Text.Pandoc.Highlighting` source
  (`https://raw.githubusercontent.com/jgm/pandoc/main/src/Text/Pandoc/Highlighting.hs`)
  imports Skylighting format options for `startNumber`, `lineAnchors`,
  `numberLines`, and `lineIdPrefix`, and treats `number`, `numberLines`, and
  `number-lines` as numbered-line classes.
- Skylighting HTML formatter source
  (`https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-format-blaze-html/src/Skylighting/Format/HTML.hs`)
  renders numbered blocks with `numberSource`, source-line spans, line anchors,
  and `counter-reset` CSS.
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
  - Result: `1 test files, 68 assertions, 0 failures`
  - PASS lines: 8
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 4,141 assertions, 0 failures`
  - PASS lines: 419

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader` fenced-code attributes, and bounded native `SyntaxHighlighter`
support row. Full upstream runner parity remains gated on hydrating the Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and producing a Cabal
solver/build plan for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted Markdown/HTML reader coverage, XML/HTML5
DOM support, EPUB3 package handoff, DOCX/ODT package parsing, ZIP/OPC
relationship behavior, archive compression streams, PDF engine fake-runner
diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX,
charset/Unicode, or legacy DOC/CFB slices. It owns only bounded syntax
highlighting line-number/style/token handoff.

## Follow-Up

Keep full Skylighting XML syntax definitions, custom theme JSON parsing,
line-number color parity with Skylighting themes, incremental lexer state, and
writer-wide automatic highlighting as separate bounded slices.
