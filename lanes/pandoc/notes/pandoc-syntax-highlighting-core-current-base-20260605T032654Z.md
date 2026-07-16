# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T032654Z`

Base accepted HEAD: `08a7a09f877e3fc4f057a64676c1c2f50f3a8074`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Lua filter handoff:
  - normalizes `lua` and `pandoc-lua` code-block classes to canonical `lua`;
  - tokenizes bounded Pandoc Lua filter review snippets for comments, Lua
    keywords, `pandoc` module/constructor access, variables, functions,
    constants, numeric literals, strings, and operators using existing
    Pandoc/Skylighting-style short token classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for Lua filter review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a line-numbered Pandoc Lua filter snippet so import reviewers can inspect
  filter source without invoking external highlighters.

## Source Truth

- Existing accepted syntax-highlighting slices established the local
  Pandoc/Skylighting handoff contract: normalize code-block language aliases,
  use Pandoc built-in style names, emit `sourceCode` / `numberSource` wrappers,
  and preserve short token classes such as `kw`, `dt`, `fu`, `va`, `cn`,
  `st`, `co`, `dv`, and `op`.
- The current upstream-runner dependency audit records
  `pandoc-lua-engine/test/` as part of the pinned Pandoc upstream inventory and
  keeps `test-pandoc-lua-engine` blocked only on hydrating/building the Haskell
  runner closure. This slice ports the bounded native PHP source-code handoff
  for Lua filter review packets, not the Lua engine or full Skylighting XML
  grammar.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external highlighter, browser renderer, online
  sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or online
  service was executed.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result before implementation: `1 test files, 168 assertions, 2 failures`
  - Failure: `lua` normalized to `null`, and `pandoc-lua` code blocks fell
    back to unsupported plain text.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 190 assertions, 0 failures`
- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6370 assertions, 0 failures`
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
TeX/LaTeX, diff/patch, Markdown-family, or Ruby/Rake highlighting. It also
avoids Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3 package
handoff, DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine
diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX
conversion, charset/Unicode, and legacy DOC/CFB slices. It owns only bounded
Lua/Pandoc Lua filter syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, custom KDE theme parsing,
embedded-language highlighting inside fenced Markdown code, token title
attributes, incremental lexer state, line-number color parity, writer-wide
default highlighting policy, and additional language grammars as separate
bounded slices.
