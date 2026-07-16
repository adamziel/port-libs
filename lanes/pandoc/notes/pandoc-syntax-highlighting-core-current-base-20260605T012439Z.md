# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T012439Z`

Base accepted HEAD: `9a81647b982b902735992b9b04cc479f9539d7f2`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` TeX/LaTeX handoff:
  - normalizes `latex` and `tex` to canonical `tex`, matching Pandoc's
    high-level language handoff for TeX-family code blocks;
  - tokenizes bounded TeX reviewer snippets for comments, core document
    commands, general control sequences, brace-group names, numeric option
    values, plain option keys, and Pandoc-template `$name$` placeholders;
  - keeps WordPress highlighted-code output on the existing raw HTML handoff
    path with style metadata and escaped source text.
- Updated the WordPress syntax-highlighting smoke fixture with a LaTeX review
  snippet containing a template placeholder and media include command.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` lists the built-in highlight
  styles and maps `tex`/`latex` to TeX-family highlighting/listings names.
- Skylighting HTML formatter source documents the same short token classes and
  highlighted HTML block shape already used by this lane (`kw`, `fu`, `co`,
  `dt`, `dv`, `va`, `op`, `sourceCode`, and numbered source-line wrappers).
- No Pandoc binary, Cabal build, Haskell runner, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, TeX/PDF engine, Typst,
  roff, or online service was executed.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result before implementation: `1 test files, 89 assertions, 2 failures`
  - Failure: `latex`/`TeX` aliases normalized to `null`/unsupported language.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 109 assertions, 0 failures`
  - PASS lines: 11
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5127 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted base syntax-highlighting language/style
coverage, line-number anchor rendering, Haskell/LHS token handoff, WordPress
writer opt-in highlighting, Markdown/HTML reader coverage, XML/HTML5 DOM
support, EPUB3 package handoff, DOCX/ODT package parsing, ZIP/OPC relationship
behavior, archive compression streams, PDF engine fake-runner diagnostics,
BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX conversion,
charset/Unicode, or legacy DOC/CFB slices. It owns only bounded TeX/LaTeX
syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax definitions, custom KDE theme parsing, token
title attributes, incremental lexer state, line-number color parity, writer-wide
default highlighting policy, and additional language grammars as separate
bounded slices.
