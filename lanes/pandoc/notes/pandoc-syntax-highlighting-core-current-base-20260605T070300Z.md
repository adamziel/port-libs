# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T070300Z`

Base accepted HEAD: `a082cab10bdb18b88ae8978f2779c698a9d629b2`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` JavaScript React/JSX handoff:
  - normalizes `jsx` and `javascript-react` code-block classes into canonical
    `jsx` highlighting;
  - tokenizes bounded React/Gutenberg review snippets for comments, imports,
    exports, functions, element tags, component tags, attributes, evaluated
    braces, strings, variables, datatypes, and operators using the existing
    Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for JSX migration review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered JSX block-preview snippet so reviewers can inspect Gutenberg
  component source without invoking Pandoc, Skylighting, Node, browser
  renderers, or online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and carries
  `startFrom`, `numberLines`, `lineAnchors`, `lineIdPrefix`, source code
  classes, and built-in styles through formatter options.
- Skylighting's `javascript-react.xml` defines `JavaScript React (JSX)`,
  declares the `*.jsx` extension and `JSX` alternative name, and gives element
  tags, component tags, attributes, values, comments, symbols, keywords,
  modules, templates, substitutions, and code brackets visible highlighting
  categories. This slice ports a bounded token handoff, not the full KDE XML
  syntax engine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/javascript-react.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Node, browser renderer, external highlighter, online
  sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or online
  conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 338 assertions, 0 failures`
- Red check before source implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 309 assertions, 2 failures`
  - Failure shape: `jsx` normalized to `NULL` and the JSX fixture fell back to
    plain text.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 359 assertions, 0 failures`
- Focused lane directory after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8392 assertions, 0 failures`
- PASS-line count check:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `713`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.

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
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua,
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, or Pandoc JSON
`.theme` support. It also avoids Markdown/HTML reader coverage, XML/HTML5 DOM
support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC, archive
compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate, table
geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB slices. It
owns only bounded JavaScript React/JSX syntax-highlighting alias and token
handoff.

## Follow-Up

Keep TSX, full KDE/Skylighting XML syntax-definition parity,
parser-state-aware JSX expression/template context, token title attributes,
incremental lexer state, line-number color parity, writer-wide default
highlighting policy, and additional language grammars as separate bounded
slices.
