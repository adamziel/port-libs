# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T022616Z`

Base accepted HEAD: `9175aaed2fff50dba03f4d62df09a6b8d4ac9fe1`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Markdown-family handoff:
  - normalizes `markdown`, `md`, `mmd`, `multimarkdown`, `pandoc`,
    `pandoc-markdown`, `commonmark`, and `gfm` to canonical `markdown`;
  - tokenizes bounded Markdown reviewer snippets into existing
    Pandoc/Skylighting-style short token classes for headings, horizontal
    rules, task-list checkboxes, list markers, blockquotes, links, autolinks,
    reference definitions, code spans, strikeout/emphasis markers, comments,
    and fenced-code boundaries;
  - preserves Pandoc numbered-source wrappers and WordPress raw HTML style
    metadata for Markdown review packets.
- Updated the WordPress syntax-highlight fixture and example self-test with a
  Markdown source review block using a four-backtick outer fence so nested
  fenced-code text remains literal source.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
  (`https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`)
  delegates code-block highlighting to Skylighting syntax lookup by code-block
  classes and emits `sourceCode`/numbered-source format options.
- Skylighting's bundled Markdown syntax definition
  (`https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/markdown.xml`)
  declares the `Markdown` syntax with `*.md`, `*.mmd`, and `*.markdown`
  extensions and covers headers, metadata, fenced/indented code blocks,
  lists, blockquotes, links/autolinks, reference definitions, task
  checkboxes, comments, emphasis, strikeout, tables, and horizontal rules.
- No Pandoc binary, Cabal build, Haskell runner, Skylighting runtime, external
  highlighter, browser renderer, TeX/PDF engine, online sanitizer, or online
  service was executed.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result before implementation: `1 test files, 125 assertions, 2 failures`
  - Failure: `md` and Markdown-family aliases fell back to unsupported
    language/plain escaped text.
- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 150 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5740 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, WordPress writer opt-in, Haskell,
TeX/LaTeX, or diff/patch. It also avoids Markdown/HTML reader coverage,
XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC,
archive compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate,
table geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB
slices. It owns only bounded Markdown-family syntax-highlighting alias and
token handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, embedded-language
highlighting inside fenced Markdown code, incremental lexer state, custom KDE
theme parsing, token title attributes, line-number color parity, and
writer-wide default highlighting policy as separate bounded slices.
