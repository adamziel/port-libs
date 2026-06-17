# DOCX/OpenXML Header/Footer Media Relationships - 2026-06-17

## Scope

- Bead: `plib-343l6`
- Slice: `pandoc-docx-openxml-header-footer-media-relationships`
- Lane: `pandoc`
- Constraint: native PHP only. No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
- Rebase note: the implementation and test coverage already landed on `main` in `821950d55f` (`plib-57ip0`). This merge preserves the `plib-343l6` provenance note only and does not double-count the case.

## Implementation

- `DocxOpenXmlReader` now aggregates image relationships declared by header and footer relationship sidecars into `headerFooterMediaRelationships`.
- The provenance keeps each media target tied to its header/footer source part, source document relationship, sidecar path, composite relationship key, target query/fragment suffix, content type, byte length, CRC32, SHA-256, external target policy, and issue codes.
- Package summaries now expose header/footer media relationship counts, header/footer split counts, existing/missing/external/unsafe counts, issue buckets, and `header-footer-media` package inventory roles for internal media targets.

## Coverage

- Added one focused DOCX/OpenXML package-ingestion case:
  - `summarizes docx header and footer media relationships for package review handoff`
- Current counter state after rebase onto `ba74975b84`:
  - `phpPass`: `17045`
  - `phpFail`: `0`
  - upstream mapped cases: `16631`
  - root mapped inventory: `16600`
  - benchmark denominator mapped cases: `3769`
  - `mappedDocxOpenXmlHeaderFooterMediaRelationshipCases`: `1`
  - `docxOpenXmlHeaderFooterMediaRelationshipAssertions`: `88`
- This note-only rebase adds no new mapped case and no new assertions beyond the existing ledger totals.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 4828 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 files, 176596 assertions, 0 failures
- PHP JSON manifest/status validation
- `git diff --check`
- conflict-marker scan
