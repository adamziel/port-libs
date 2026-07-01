# DOCX OpenXML numbering relationship sidecar target path provenance

Issue: plib-d3q

Scope: native DOCX/OpenXML package ingestion only. This slice extends the
numbering relationship sidecar review for `word/_rels/numbering.xml.rels`
without reading target bytes beyond existing digest metadata and without
invoking Pandoc, office suites, zip/unzip, browser engines, Node tooling,
external validators, or online services.

Implemented:

- Adds per-relationship target directory, directory basename, basename,
  basename stem, and path-segment metadata for internal numbering sidecar
  targets.
- Adds sidecar-level target directory, directory-basename, basename,
  basename-stem, and path-segment rollups.
- Promotes concise numbering sidecar target path counters into
  `packageProvenance.summary`.

Direct-format parity accounting:

- `mappedDocxNumberingRelationshipSidecarCases`: `1 -> 2`
- `docxNumberingRelationshipSidecarAssertions`: `114 -> 154`

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/integration/pandoc-package-docx...HEAD`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result after rebase: 1 file, 15590 assertions, 0 failures.

This is metadata-only package provenance. It does not claim broader DOCX
conversion parity or external relationship target resolution.
