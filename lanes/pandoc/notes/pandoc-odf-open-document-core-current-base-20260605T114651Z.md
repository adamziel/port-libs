# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T114651Z`
- Base accepted HEAD: `b0b72874e66840fd6a7239e395a47d03eb6b09cc`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for content field declarations:

- Collects `text:sequence-decls/text:sequence-decl` metadata from `content.xml`.
- Collects `text:variable-decls/text:variable-decl` metadata from `content.xml`.
- Collects `text:user-field-decls/text:user-field-decl` metadata including value type, string/value/date/time/boolean/currency values.
- Exposes declaration maps and counts through the document attrs, top-level `readPackage()` result, and `importReport.contentDeclarations`.
- Uses declared user-field values only as fallback text/metadata for empty `text:user-field-get` spans, preserving existing output for populated user-field spans.
- Updates the WordPress ODF handoff example to include declaration metadata and a declared empty user-field reference.

## Source Truth And Non-Overlap

The local upstream Pandoc checkout path recorded in the manifest was unavailable in this isolated worktree. This slice used accepted lane source truth plus the OpenDocument XML contract already encoded in the ODF reader: declaration elements live under `office:text`, and user-field declarations carry the source values for `text:user-field-get` references.

This patch does not overlap accepted ODF mimetype, manifest, metadata, styles, page-layout/master-page, table, list, section, link, annotation, tracked-change, bibliography-mark, soft-page-break, image, MathML object, form-control, chart object, or object-ole behavior.

## Red-First Evidence

Before implementation, the new field-declaration test failed as expected:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 684 assertions, 1 failures`.

The failure showed `contentDeclarations` was absent and empty `text:user-field-get` spans rendered without their declared values.

## Focused Verification

Final focused test run:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 726 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`.

Syntax checks:

- `php -l lanes/pandoc/src/OdfReader.php` passed.
- `php -l lanes/pandoc/tests/OdfReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` passed.

Metadata and diff hygiene:

- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " valid\n"; }'` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `876 -> 877`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1334 -> 1335`.
- ODF OpenDocument core cases: `10 -> 11`.
- Mapped ODF OpenDocument core cases: `10 -> 11`.
- ODF OpenDocument core assertions: `217 -> 260`.
- Focused `OdfReaderTest.php` coverage moved from `29` PASS cases and `683` assertions to `30` PASS cases and `726` assertions.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP ODF DOM/XML reader, `ZipPackage`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Word, LibreOffice, office automation, zip/unzip, Cabal build, Haskell runner, browser renderer, external validator, online sanitizer, or online conversion service was executed.

## Follow-Up

Keep variable-state propagation across `text:variable-set/get`, `text:expression` evaluation, database display fields, richer style cascades, chart data extraction, form submission/action semantics, live widgets, validation, scripting, export-side ODT writing, and full Pandoc Haskell runner parity as separate bounded slices.

Root harness status: not run - isolated micro-slice.
