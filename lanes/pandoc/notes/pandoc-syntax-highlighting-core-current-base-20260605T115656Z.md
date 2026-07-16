# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T115656Z`

Base accepted HEAD: `fcc103fb6d74e86d76cca3d82c3e4f57663c4ab9`

## Behavior Added

- Added an opt-in token-title metadata handoff to the native
  `SyntaxHighlighter`:
  - treats `tokenTitles`, `token-titles`, `titleAttributes`, and related
    token-title classes as structural code-block classes instead of languages;
  - accepts `tokenTitles` as a direct highlighter option and
    `data-token-titles`/`tokenTitles` style code-block attributes;
  - renders token spans with Skylighting-style token constructor title
    attributes such as `KeywordTok`, `FunctionTok`, `VariableTok`,
    `OperatorTok`, `CommentTok`, and `PreprocessorTok` when requested;
  - keeps the default highlighted HTML unchanged when token titles are not
    requested.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered PHP review block using `.tokenTitles`, so reviewer tooling can
  inspect token-kind metadata without invoking Pandoc, Skylighting, external
  highlighters, browser renderers, or online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and carries
  `startFrom`, `numberLines`, `lineAnchors`, `lineIdPrefix`, source-code
  classes, and built-in/custom styles through formatter options.
- Skylighting token/style names already accepted in this lane include
  `KeywordTok`, `StringTok`, `CommentTok`, `FunctionTok`, `VariableTok`,
  `OperatorTok`, `AttributeTok`, `AlertTok`, `PreprocessorTok`,
  `RegionMarkerTok`, and `InformationTok`. This slice exposes those bounded
  token kinds as opt-in HTML title metadata; it does not implement the full KDE
  XML syntax-definition engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external highlighter, browser renderer, online
  sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or online
  service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 539 assertions, 0 failures`
- Red-first focused probe:
  - A direct `.tokenTitles` PHP code-block probe rendered ordinary spans such
    as `<span class="kw">echo</span>` and did not emit any token title
    metadata.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 553 assertions, 0 failures`
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
- JSON status/manifest validity:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- Diff hygiene:
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
language/style/token support, line anchors, WordPress writer opt-in, Pandoc
JSON `.theme` parsing, Haskell, TeX/LaTeX, diff/patch, Markdown-family,
Ruby/Rake, Lua/Pandoc-Lua, TypeScript, Python, C/C++, Dockerfile/Containerfile,
Makefile, JSX/React, R, INI/config, TOML/Cargo.lock, Perl, Java, XML/XSLT, or
Bash heredoc token-state handoff. It also avoids Markdown/HTML reader
coverage, XML/HTML5 DOM parser/sanitizer support, EPUB3 package handoff,
DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine diagnostics,
BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX conversion,
charset/Unicode, and legacy DOC/CFB slices. It owns only bounded opt-in token
title metadata for highlighted HTML/WordPress handoff.

## Follow-Up

Keep parser-state-aware embedded-language highlighting, full shell
arithmetic/process-substitution state, token titles for richer exact
Skylighting subtypes beyond this bounded map, full KDE/Skylighting XML
syntax-definition parity, incremental lexer state, and writer-wide default
highlighting policy as separate bounded slices.
