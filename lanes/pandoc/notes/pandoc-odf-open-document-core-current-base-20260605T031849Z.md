# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T031849Z`
- Accepted base: `ade3bedea1d5f41d2a42f4498c3f970f11a0b9a1`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for page and master
style metadata:

- Parses `style:page-layout` entries from `styles.xml` and content automatic
  styles, preserving page usage, dimensions, margins, print orientation,
  writing mode, and bounded point conversions for review tooling.
- Parses `style:master-page` entries, preserving display names, page-layout
  links, next-style sequencing, draw style links, and header/footer text.
- Preserves `style:master-page-name` and page-break paragraph properties on
  paragraph style definitions so imported content can be audited against its
  source page style.
- Exposes page layouts and master pages on the document node, package result,
  and import report.
- Updates the WordPress ODF handoff smoke to verify page-layout and master-page
  metadata without invoking office tooling.

This is bounded to OpenDocument package/styles/content XML mapping. It does
not invoke Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external
template engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 301 assertions, 0 failures`
- Red-first after adding page-layout/master-page expectations:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 302 assertions, 1 failures`
  - Expected failure: `pageLayouts` / `masterPages` were not exposed by
    `OdfReader`.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 332 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`:
    no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `575 -> 576`.
- `benchmarkDenominator.mapped`: `1051 -> 1052`.
- Focused `OdfReaderTest.php`: `12 -> 13` cases, `301 -> 332`
  assertions.
- ODF manifest subcounters were normalized from the accepted manifest's older
  `10` / `217` values to the current focused `13` cases / `332` assertions;
  this slice's direct focused delta is the new page-layout/master-page case
  and `301 -> 332` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local page-layout and master-page metadata parsing is not
blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list/
annotation/text-box/image, footnote/endnote, bookmark-reference,
reference-mark/reference-ref, sequence, tracked-change, encrypted-manifest,
MathML object, and linked/protected section clusters. It adds only bounded
OpenDocument page-layout/master-page metadata and related import-report
wiring.

Remaining ODT follow-up stays separate: forms, charts, richer style cascades,
embedded-object preview policy beyond MathML, table continuation semantics,
export-side ODT writing, and full Pandoc ODT reader parity.
