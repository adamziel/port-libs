# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T065656Z`
- Accepted base: `13a03f44f03f1a17e55a3c59df211c0698381848`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for vertical-position
text styles:

- Reads `style:text-position` from `style:text-properties`.
- Maps values containing `super` to shared AST `superscript` nodes.
- Maps values containing `sub` to shared AST `subscript` nodes.
- Preserves the existing `text:span` review metadata inside those nodes so
  Markdown and WordPress output still expose the ODT source style name.
- Updates the WordPress ODF handoff smoke so source superscript/subscript cues
  survive rendered review blocks.

Source truth: upstream Pandoc
`Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` applies subscript/superscript
inline modifiers when ODT text style vertical-position changes:
https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs

This is bounded to OpenDocument content/styles XML mapping. It does not invoke
Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template
engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 470 assertions, 0 failures`
- Red-first after adding the text-position expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 473 assertions, 1 failures`
  - Expected failure: `style:text-position` was absent from parsed
    `textProperties`, so the span was not emitted as `superscript`.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 485 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax checks:
  `php -l lanes/pandoc/src/OdfReader.php`,
  `php -l lanes/pandoc/tests/OdfReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - all reported no syntax errors.
- Lane-local JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `729 -> 730`.
- `benchmarkDenominator.mapped`: `1188 -> 1189`.
- Focused `OdfReaderTest.php`: `20 -> 21` cases, `470 -> 485` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `21` mapped cases / `485` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local text-position style parsing is not blocked by that
runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list
restart/list continuation/annotation/text-box/image, footnote/endnote,
bookmark-reference, reference-mark/reference-ref, sequence, field,
bibliography-mark, tracked-change, encrypted-manifest, MathML object,
linked/protected section, page-layout/master-page, image-dimension,
annotation-range, and inherited nested-list style clusters. It adds only
bounded OpenDocument `style:text-position` handoff for text styles.

Remaining ODT follow-up stays separate: forms, charts, embedded-object preview
policy beyond MathML, table continuation semantics, export-side ODT writing,
and full Pandoc ODT reader parity.
