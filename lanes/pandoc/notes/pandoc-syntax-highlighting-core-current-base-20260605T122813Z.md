# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T122813Z`

Base accepted HEAD: `83d14850b25025929d0658c79f2dae5d9193bbe0`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` CSS handoff:
  - tokenizes CSS at-rules such as `@media` and `@supports` as keyword spans;
  - keeps class, id, and type selectors visible as selector-like datatype spans;
  - tokenizes pseudo-classes and pseudo-elements, including `:hover`,
    `:focus-visible`, and `::before`;
  - preserves custom properties, `var()` calls, hex color literals, expanded
    CSS dimensions, quoted strings, and `!important` as distinct
    Pandoc/Skylighting-style token spans;
  - keeps Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for block/theme CSS review packets.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered CSS block review snippet. No Pandoc, Skylighting runtime, external
  highlighter, browser renderer, JavaScript, online sanitizer, or online
  service is needed.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and carries
  `startFrom`, `numberLines`, `lineAnchors`, `lineIdPrefix`, source-code
  classes, and built-in/custom styles through formatter options.
- CSS is part of the already accepted bounded syntax-highlighting support row.
  This slice deepens the CSS token handoff for WordPress block/theme review
  packets; it does not implement the full KDE XML syntax-definition engine,
  CSS parsing, cascade evaluation, or media resource loading.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external highlighter, browser renderer, JavaScript,
  online sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or
  online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 553 assertions, 0 failures`
- Red-first focused probe:
  - Direct CSS review packet highlighting split `@media` as raw `@` plus
    `media`, split selectors into punctuation/text fragments, split `#005cc5`
    as `#` plus text/number spans, and split `!important` across raw text and
    keyword spans.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 573 assertions, 0 failures`
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
JSON `.theme` parsing, token title attributes, Haskell, TeX/LaTeX,
diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua, TypeScript, Python,
C/C++, Dockerfile/Containerfile, Makefile, JSX/React, R, INI/config,
TOML/Cargo.lock, Perl, Java, XML/XSLT, or Bash heredoc token-state handoff. It
also avoids Markdown/HTML reader coverage, XML/HTML5 DOM parser/sanitizer
support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC, archive
compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate, table
geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB slices. It
owns only bounded CSS at-rule, selector, pseudo-selector, custom-property,
color, dimension, string, and `!important` syntax-highlighting handoff.

## Follow-Up

Keep parser-state-aware embedded-language highlighting, full CSS parser/cascade
semantics, CSS media/resource loading, full KDE/Skylighting XML
syntax-definition parity, incremental lexer state, richer exact token subtype
mapping, and writer-wide default highlighting policy as separate bounded
slices.
