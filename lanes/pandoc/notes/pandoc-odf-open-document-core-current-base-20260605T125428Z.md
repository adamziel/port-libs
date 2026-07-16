# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T125428Z`
- Base accepted HEAD: `97c11b6d278bd1942bd719cfd7817066baa00cb7`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text table-cell handoff for formula and typed-value review metadata:

- Collects `table:formula` from `table:table-cell`.
- Collects typed `office:*` values: `value-type`, `value`, `currency`, `string-value`, `date-value`, `time-value`, and `boolean-value`.
- Exposes the data as `odfCellMetadata` on `table_cell` AST nodes.
- Emits safe `data-odf-cell-*` HTML attributes and `odf-table-cell-value` / `odf-table-cell-formula` classes for WordPress table cells.
- Lets `TableGeometry` carry the same source attributes in review packets.
- Keeps visible cell text unchanged and does not evaluate formulas.

The WordPress ODF handoff example now includes a calculated colspan cell and asserts that the formula/value metadata survives both rendered table markup and the table-geometry review packet.

## Source Truth And Non-Overlap

The local upstream Pandoc checkout path recorded in the manifest was unavailable in this isolated worktree. This slice used accepted lane source truth plus the OpenDocument XML contract already encoded in the ODF reader: `table:table-cell` carries formula and typed value attributes in the `table` and `office` namespaces.

This patch does not overlap accepted ODF mimetype, manifest, package media, metadata, styles, page-layout/master-page, table name/protection, table span, list, section, link, annotation, tracked-change, bibliography-mark, soft-page-break, image, MathML object, form-control, chart object, object-ole, or field-declaration behavior.

## Red-First Evidence

Before implementation, the new table-cell metadata test failed as expected:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 730 assertions, 1 failures`.

Failure shape: `odfCellMetadata`, WordPress table-cell classes, and `data-odf-cell-*` attributes were absent for typed/formula cells.

## Focused Verification

Final focused test run:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 755 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`.

Syntax checks:

- `php -l lanes/pandoc/src/OdfReader.php` passed.
- `php -l lanes/pandoc/tests/OdfReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` passed.

Metadata validation:

- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " valid\n"; }'` passed.

Diff hygiene:

- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `905 -> 906`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1363 -> 1364`.
- ODF OpenDocument core cases: `10 -> 11`.
- Mapped ODF OpenDocument core cases: `10 -> 11`.
- ODF OpenDocument core assertions: `217 -> 246`.
- Focused `OdfReaderTest.php` coverage moved from `30` PASS cases and `726` assertions to `31` PASS cases and `755` assertions.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP ODF DOM/XML reader, `ZipPackage`, `AstNode`, `TableGeometry`, and `WordPressBlockWriter`.

No Pandoc, Word, LibreOffice, office automation, zip/unzip, Cabal build, Haskell runner, browser renderer, external validator, online sanitizer, or online conversion service was executed.

## Follow-Up

Keep formula evaluation/recalculation, repeated and matrix formulas, database ranges, validation constraints, live form widgets, chart data extraction, style cascade parity, export-side ODT writing, and full Pandoc Haskell runner parity as separate bounded slices.

Root harness status: not run - isolated micro-slice.
