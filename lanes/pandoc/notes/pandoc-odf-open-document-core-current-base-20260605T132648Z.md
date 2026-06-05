# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T132648Z`
- Base accepted HEAD: `ff354339b75c243b8f35c6ff885525ed4f3a954f`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for bounded
`text:ruby` inline annotations:

- Preserves `text:ruby-base` children as visible inline content instead of
  dropping the whole ruby container.
- Exposes `text:ruby-text` as `rubyText` plus safe `data-odf-ruby-text`
  review metadata for Markdown and WordPress output.
- Preserves `text:style-name` from both the ruby container and ruby-text child
  as review metadata.
- Adds recursive `importReport.content.rubyCount` accounting.
- Updates the WordPress ODF handoff smoke so importer review packets keep ruby
  annotations visible without external office tooling.

## Source Truth And Non-Overlap

The local upstream Pandoc checkout path recorded in the manifest was unavailable
in this isolated worktree. This slice used the OpenDocument XML contract already
encoded in the ODF reader: `text:ruby` is an inline content container with base
text and ruby annotation text. WordPress import should preserve the base text
and keep the annotation auditable.

This patch does not overlap accepted ODF mimetype, manifest, metadata, styles,
page-layout/master-page, table name/protection, table cell typed values,
table spans, list continuation/header, section, link metadata, annotation range,
tracked changes, bibliography-mark, soft-page-break, image, MathML object,
form-control, chart object, object-ole, field declaration, or URI-decoded
package-reference behavior.

## Red-First Evidence

Baseline before edit:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 755 assertions, 0 failures`.

After adding the ruby expectation and before implementation:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 756 assertions, 1 failures`.

Failure shape: the paragraph text was `Localized  label and  note.` because
`text:ruby` was skipped by `inlineNodesFromNodeList()`.

## Focused Verification

Final focused tests:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 773 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - Result: `1 test files, 95 assertions, 0 failures`.

Example smoke:

- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`.

Required handoff checks:

- `php -l lanes/pandoc/src/OdfReader.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/OdfReader.php`.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php`.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `921 -> 922`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1378 -> 1379`.
- ODF OpenDocument core cases: `10 -> 11`.
- Mapped ODF OpenDocument core cases: `10 -> 11`.
- ODF OpenDocument core assertions: `217 -> 235`.
- Focused `OdfReaderTest.php` coverage moved from `31` PASS cases and `755`
  assertions to `32` PASS cases and `773` assertions.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP
ODF DOM/XML reader, `ZipPackage`, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter`.

No Pandoc, Word, LibreOffice, office automation, zip/unzip, Cabal build,
Haskell runner, browser renderer, external validator, online sanitizer, or
online conversion service was executed.

## Follow-Up

Keep full ruby layout/export semantics, richer style cascades, table
continuation/database ranges, formula recalculation, chart data extraction,
form submission/action semantics, live widgets, validation, scripting,
export-side ODT writing, and full Pandoc Haskell runner parity as separate
bounded slices.

Root harness status: not run - isolated micro-slice.
