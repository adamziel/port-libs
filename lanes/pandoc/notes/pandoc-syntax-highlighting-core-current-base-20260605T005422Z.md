# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T005422Z`

Base accepted HEAD: `c39e6ef5dc53ab6c10abe3cd85218cbaaa83096e`

## Behavior Added

- Added an explicit `WordPressBlockWriter` syntax-highlighting opt-in:
  - `highlightCodeBlocks` preserves the default `wp:code` output when false;
  - `highlightStyle` passes the requested Pandoc-style theme name to the
    existing bounded native `SyntaxHighlighter`;
  - highlighted writer output emits WordPress raw HTML blocks with style
    metadata, `sourceCode`/`numberSource` classes, `startFrom` counter resets,
    and line anchors for fenced code block IDs;
  - unsupported or unhighlighted defaults remain escaped and non-executing.
- Updated the WordPress syntax-highlighting handoff example so its self-test
  proves both direct highlighter output and the normal writer opt-in path.

## Source Truth

- This extends the already accepted bounded Pandoc/Skylighting handoff
  contract from earlier syntax slices: normalized language aliases, built-in
  style names, short token classes, Pandoc code-block attributes, and
  numbered-line/anchor markup.
- The behavior is intentionally opt-in at the WordPress writer boundary so
  existing code-block output remains stable unless a conversion caller requests
  highlighted source blocks.
- No Pandoc binary, Cabal build, Haskell runner, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, or online service was
  executed.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result before implementation: `1 test files, 86 assertions, 1 failures`
  - Failure: `WordPressBlockWriter` still emitted plain `wp:code` when the new
    highlighted writer opt-in was requested.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 93 assertions, 0 failures`
  - PASS lines: 10
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4,838 assertions, 0 failures`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted Markdown/HTML reader coverage, base
syntax-highlighting language/style/token coverage, line-number helper
coverage, Haskell/LHS token handoff, XML/HTML5 DOM support, EPUB3 package
handoff, DOCX/ODT package parsing, ZIP/OPC relationship behavior, archive
compression streams, PDF engine fake-runner diagnostics, BibTeX/CSL, YAML,
doctemplate, table geometry, math/TeX, charset/Unicode, or legacy DOC/CFB
slices. It owns only normal WordPress writer integration for the existing
bounded syntax-highlighting helper.

## Follow-Up

Keep full Skylighting XML syntax definitions, custom theme JSON parsing,
incremental lexer state, writer-wide default highlighting policy, additional
language grammars, line-number color parity, and nested-list code-block style
sharing as separate bounded slices.
