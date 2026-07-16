# Pandoc ODF OpenDocument Core Slice

## Scope

Extended the native `OdfReader` OpenDocument Text handoff for bounded
`draw:object-ole` embedded object frames:

- Maps inline and block-level OLE object frames to explicit
  `odf-embedded-object` review placeholders instead of silently dropping them.
- Resolves URI-encoded `xlink:href` object targets against the manifest using
  the existing safe package-path normalization.
- Records object path, manifest source part, media type, contained package
  parts, contained byte length, existence, and missing-object counts in the AST
  and import report.
- Keeps opaque embedded object payload bytes out of Markdown and WordPress
  output while still reporting package metadata for reviewer triage.
- Tightens ODT media reporting so `application/vnd.openxmlformats...oleObject`
  is not misclassified as XML merely because the subtype contains the string
  `xml`.

This is bounded to ODT/OpenDocument package/content XML mapping. It does not
invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external
office tools, browser renderers, external conversion services, or online
services.

## Source Truth

The local upstream cache for this isolated worktree does not include a hydrated
Pandoc checkout or Cabal package files. This slice uses the ODF/OpenDocument
support row already activated for the lane: OpenDocument drawing frames may
carry package-local object references, and WordPress import should preserve
those opaque embedded objects as auditable placeholders rather than render or
discard their bytes.

## Evidence

- Red-first after adding the object-ole expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: failed because object-ole frames were dropped; the new test saw
    only one block and no embedded-object placeholder metadata.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: 1 test file, 598 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'`
- Whitespace:
  - `git diff --check -- lanes/pandoc`

## Status Delta

- `phpPass`: 808 -> 809.
- `benchmarkDenominator.mapped`: 1268 -> 1269.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 247.
- Focused `OdfReaderTest.php`: 25 -> 26 PASS cases, 568 -> 598 assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`OdfReader`, `ZipPackage`, AST, `MarkdownWriter`, and `WordPressBlockWriter`
components. Full upstream Pandoc runner parity remains blocked on hydrating and
building the pinned Haskell Pandoc checkout, but ODT-local object-ole handoff is
not blocked by that runner.

## Non-Overlap

This avoids the accepted ODT mimetype/content/manifest/media/table/list base
cluster and the later bookmark, reference mark, sequence, field,
bibliography-mark, annotation range, nested-list style inheritance,
text-position, MathML object, linked/protected section, tracked-change,
encrypted-manifest, image-dimension, link-metadata, list-header, protected table
metadata, and URI-decoded package-reference clusters. It adds only bounded
OpenDocument `draw:object-ole` embedded-object review placeholders and the
minimal media-type classifier fix needed to report OLE package parts.

Remaining ODT follow-up stays separate: forms, charts, richer style cascades,
embedded-object preview extraction beyond opaque placeholders, table
continuation semantics, export-side ODT writing, and full Pandoc ODT reader
parity.
