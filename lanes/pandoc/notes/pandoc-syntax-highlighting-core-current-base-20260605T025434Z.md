# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T025434Z`

Base accepted HEAD: `eee2b26c7e4190b7a49f028c56f653361d9caf62`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Ruby/Rake handoff:
  - normalizes `ruby`, `rb`, and `rake` to canonical `ruby`;
  - tokenizes bounded Ruby review snippets for comments, strings, symbols and
    keyword arguments, instance/global/class and local variables, keywords,
    constants/modules/classes, numeric literals, built-in calls, method calls,
    and common operators;
  - preserves WordPress raw HTML handoff with Pandoc/Skylighting-style short
    token classes and highlight style metadata.
- Updated the WordPress syntax-highlighting fixture and example smoke with a
  Ruby import-audit snippet so Markdown-reader-to-WordPress handoff proves the
  Ruby alias and token path without invoking external highlighters.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
  (`https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`)
  delegates code-block highlighting to Skylighting syntax lookup, lists Ruby
  in the listings-language mapping, and uses the same built-in styles and
  `sourceCode`/numbered-source format options already ported in this lane.
- Skylighting's Ruby syntax definition
  (`https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/ruby.xml`)
  covers Ruby keywords, variables, constants, built-in functions, strings,
  comments, and operator families. This slice ports the bounded token handoff,
  not the full XML syntax engine.
- No Pandoc binary, Cabal build, Haskell runner, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, TeX/PDF engine, Typst,
  roff, or online service was executed.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result before implementation: `1 test files, 147 assertions, 2 failures`
  - Failure: `rb`/`rake` aliases normalized to unsupported language and Ruby
    blocks fell back to plain escaped text.
- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 171 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5983 assertions, 0 failures`
  - PASS lines: `555`
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
TeX/LaTeX, diff/patch, or Markdown-family highlighting. It also avoids
Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3 package handoff,
DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine diagnostics,
BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX conversion,
charset/Unicode, and legacy DOC/CFB slices. It owns only bounded Ruby/Rake
syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, custom KDE theme parsing,
embedded-language highlighting inside fenced Markdown code, token title
attributes, incremental lexer state, line-number color parity, writer-wide
default highlighting policy, and additional language grammars as separate
bounded slices.
