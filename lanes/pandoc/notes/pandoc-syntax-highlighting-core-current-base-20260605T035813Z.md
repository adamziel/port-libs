# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T035813Z`

Base accepted HEAD: `9cb6468056fab1a0e88e67740ac56fbb783fc17f`

## Behavior Added

- Added bounded Pandoc JSON `.theme` support to the native
  `SyntaxHighlighter`:
  - rejects UTF-8 BOMs and invalid JSON/theme color/font flag payloads;
  - maps Skylighting token-style names such as `KeywordTok`, `StringTok`,
    `CommentTok`, `FunctionTok`, `VariableTok`, `OperatorTok`, `AttributeTok`,
    and `AlertTok` into the lane's existing Pandoc/Skylighting short CSS
    classes;
  - applies custom token foreground/background colors plus bold, italic, and
    underline flags to generated CSS;
  - applies custom line-number foreground/background colors to numbered source
    CSS;
  - preserves unsupported token-style diagnostics instead of silently trusting
    unknown theme names.
- Extended `highlightCodeBlock()` and `wordpressHtmlBlock()` with an optional
  `themeJson` option so WordPress review packets can carry custom highlight
  CSS while keeping existing built-in style behavior unchanged.
- Updated the WordPress syntax-highlighting smoke so custom theme style names,
  token CSS, line-number colors, and numbered code anchors are verified without
  invoking external highlighters.

## Source Truth

- Pandoc User's Guide, Syntax highlighting:
  `--print-highlight-style` generates a JSON `.theme` file that can be edited
  and supplied to `--syntax-highlighting`; UTF-8 without BOM is required.
  URL: `https://pandoc.org/demo/example33/15-syntax-highlighting.html`
- Pandoc / Skylighting API docs record that JSON decoding of `Style` accepts
  KDE syntax-theme keys, token styles, default/background colors, and
  line-number colors.
  URL: `https://hackage-content.haskell.org/package/pandoc-3.8/docs/Text-Pandoc-Highlighting.html`
  URL: `https://hackage.haskell.org/package/skylighting-core-0.14.3/docs/Skylighting-Types.html`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external highlighter, browser renderer, online
  sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or online
  service was executed.

## Verification

- `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 216 assertions, 0 failures`
  - PASS lines: `17`
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
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, or Lua highlighting. It also
avoids Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3 package
handoff, DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine
diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX
conversion, charset/Unicode, and legacy DOC/CFB slices. It owns only bounded
Pandoc JSON `.theme` parsing and custom-theme CSS/WordPress handoff.

## Follow-Up

Keep full KDE XML syntax-definition parsing, embedded-language highlighting
inside fenced Markdown code, token title attributes, incremental lexer state,
custom theme file discovery, line-number color parity outside CSS output,
writer-wide default highlighting policy, and additional language grammars as
separate bounded slices.
