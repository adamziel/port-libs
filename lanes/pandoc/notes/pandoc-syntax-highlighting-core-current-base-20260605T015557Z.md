# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T015557Z`

Base accepted HEAD: `8de98de08373f006264a82593ed3bdce6dc6d28e`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` diff/patch handoff:
  - normalizes `diff`, `patch`, `udiff`, `git-diff`, and `unified-diff` to
    canonical `diff`;
  - tokenizes unified-diff reviewer snippets into Pandoc/Skylighting-style
    region (`re`), metadata (`ot`), deletion (`al`), addition (`in`), and
    no-newline diagnostic (`co`) token classes;
  - emits CSS rules for the `in` and `re` token classes in all bounded native
    highlight styles;
  - preserves numbered `sourceCode`/`numberSource` WordPress HTML handoff for
    migration review patches.
- Updated the WordPress syntax-highlighting fixture and smoke example with a
  bounded source diff review packet.

## Source Truth

- The existing syntax-highlighting support row follows Pandoc's
  Skylighting-backed HTML handoff contract: language alias normalization,
  built-in highlight style names, short token classes, `sourceCode` wrappers,
  and numbered source-line wrappers.
- Diff/patch source blocks are common Pandoc/Skylighting code-block language
  aliases for unified-diff review packets; this slice ports the bounded format
  contract and reviewer-visible token handoff, not the full Skylighting XML
  grammar engine.
- No Pandoc binary, Cabal build, Haskell runner, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, TeX/PDF engine, or online
  service was executed.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result before implementation: `1 test files, 101 assertions, 2 failures`
  - Failure: `patch` / `unified-diff` aliases normalized to no language and
    diff blocks fell back to plain escaped text.
- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 128 assertions, 0 failures`
  - PASS lines: 12
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5429 assertions, 0 failures`
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

This patch does not repeat accepted base syntax-highlighting language/style
coverage, line-number anchor rendering, WordPress writer opt-in highlighting,
PHP/JSON/YAML/HTML/SQL/Haskell/LHS/TeX/LaTeX token handoff, Markdown/HTML
reader coverage, XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT
package parsing, ZIP/OPC relationship behavior, archive compression streams,
PDF engine fake-runner diagnostics, BibTeX/CSL, YAML, doctemplate, table
geometry, math/TeX conversion, charset/Unicode, or legacy DOC/CFB slices. It
owns only bounded diff/patch syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax definitions, custom KDE theme parsing, token
title attributes, incremental lexer state, line-number color parity,
writer-wide default highlighting policy, and additional language grammars as
separate bounded slices.
