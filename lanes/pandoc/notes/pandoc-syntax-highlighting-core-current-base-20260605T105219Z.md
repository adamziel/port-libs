# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T105219Z`

Base accepted HEAD: `930df03574b4d097c50462cde2b85359e1c69d4d`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` XML/XSLT handoff:
  - normalizes `xml`, `svg`, `xsd`, `rss`, `rdf`, and `atom` into canonical
    XML highlighting;
  - normalizes `xsl` and `xslt` into canonical XSLT highlighting;
  - tokenizes XML declarations, doctype/entity declarations, comments,
    namespaced tags, namespace attributes, numeric text, entity references,
    CDATA, SVG tag attributes, and XSLT tag/attribute source using the existing
    Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for WXR/XML review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered WXR-style XML review snippet and a direct XSLT probe, without
  invoking Pandoc, Skylighting, browser renderers, external highlighters, or
  online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates highlighting to
  Skylighting syntax lookup by code-block classes and carries `startFrom`,
  `numberLines`, `lineAnchors`, `lineIdPrefix`, source code classes, and
  built-in styles through formatter options.
- Pandoc's listings language map includes `xml` and `xslt`, and Skylighting's
  XML/XSLT syntax definitions identify XML declarations, tags, attributes,
  strings, entities/CDATA-style source, and XSLT element/attribute names as
  visible highlighting categories. This slice ports a bounded token handoff,
  not the full KDE XML syntax-definition engine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/xml.xml`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/xslt.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external XML/XSLT parser, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 482 assertions, 0 failures`
- Red-first focused expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: failed with `1 test files, 466 assertions, 2 failures` because
    `xml` still normalized to `html` and the new XML/XSLT handoff expected
    canonical XML token output.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 512 assertions, 0 failures`
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
- JSON validity:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: no output.

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
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, JSX/React, R,
INI/config, TOML/Cargo.lock, Perl, Java, or Pandoc JSON `.theme` support. It
also avoids Markdown/HTML reader coverage, XML/HTML5 DOM parser/sanitizer
support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC, archive
compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate, table
geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB slices. It
owns only bounded XML/SVG/XSD and XSL/XSLT syntax-highlighting alias and token
handoff.

## Follow-Up

Keep shell parser state, token title attributes, parser-state-aware embedded
language highlighting, full XPath tokenization inside XSLT attributes, and
full KDE/Skylighting XML syntax-definition parity as separate bounded slices.
