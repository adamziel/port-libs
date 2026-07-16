# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T044843Z`
- Accepted base: `d7bbd055d2b343ba284f15156b6eb3ca6d158f17`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for ODT bibliography
marks:

- Reads `text:bibliography-mark` inline nodes from content XML.
- Maps each bibliography mark into the shared `citation` AST node shape with
  `sourceFormat=odt`, normalized `id`, Pandoc-style fallback text
  `[@identifier]`, visible `displayText`, and optional `citationNumber`.
- Preserves the visible inline citation label as citation children so paragraph
  text and rendered handoff output do not drop the ODT content.
- Counts recursive citation nodes in `importReport.content.citationCount`.
- Updates the WordPress ODF handoff example self-test so bibliography marks
  survive into rendered WordPress blocks.

Source truth: upstream Pandoc `Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` includes
`text:bibliography-mark` handling in the ODT inline reader path. This PHP slice
ports the bounded citation handoff contract without invoking Pandoc,
LibreOffice, Word, zip/unzip, Haskell runners, external template engines,
TeX/PDF engines, browser renderers, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 380 assertions, 0 failures`
- Red-first after adding the bibliography-mark expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 382 assertions, 1 failures`
  - Expected failure: ODT bibliography marks were dropped from paragraph text
    and no citation AST nodes were produced.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 402 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `631 -> 632`.
- `benchmarkDenominator.mapped`: `1106 -> 1107`.
- Focused `OdfReaderTest.php`: `15 -> 16` cases, `380 -> 402` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `16` mapped cases / `402` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`,
`WordPressBlockWriter`, and `CitationCslProcessor` handoff components. Full
upstream Pandoc runner parity remains blocked on hydrating/building the
Haskell Pandoc checkout at the manifest commit, but ODT-local bibliography-mark
parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list
restart/list-continuation/annotation/text-box/image presence, footnote/endnote,
bookmark-reference, reference-mark/reference-ref, sequence, tracked-change,
encrypted-manifest, MathML object, linked/protected section, page-layout/
master-page, and image-dimension clusters. It adds only bounded OpenDocument
`text:bibliography-mark` citation handoff.

Remaining ODT follow-up stays separate: bibliography database resolution,
forms, charts, richer style cascades, embedded-object preview policy beyond
MathML, table continuation semantics, export-side ODT writing, and full Pandoc
ODT reader parity.
