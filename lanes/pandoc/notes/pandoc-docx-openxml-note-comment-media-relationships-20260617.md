# DOCX/OpenXML Note And Comment Media Relationships - 2026-06-17

## Scope

- Bead: `plib-6kedr`
- Slice: `pandoc-docx-openxml-note-comment-media-relationships`
- Lane: `pandoc`
- Constraint: native PHP only. No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Implementation

- `DocxOpenXmlReader` now aggregates image relationships declared by footnote, endnote, and comment relationship sidecars into `noteCommentMediaRelationships`.
- The provenance keeps each media target tied to its note/comment source kind, source part, relationship sidecar, composite relationship key, target suffix/query/fragment, referenced/orphaned state, content type, byte length, CRC32, SHA-256, external target policy, and issue codes.
- Package summaries now expose note/comment media relationship counts, footnote/endnote/comment split counts, referenced/orphaned counts, existing/missing/external/unsafe counts, issue buckets, and `note-comment-media` package inventory roles for internal media targets.

## Coverage

- Added one focused DOCX/OpenXML package-ingestion case:
  - `summarizes docx note and comment media relationships for package review handoff`
- Counter updates:
  - `phpPass`: `17052 -> 17053`
  - `phpFail`: `0`
  - upstream mapped cases: `16638 -> 16639`
  - root mapped inventory: `16607 -> 16608`
  - benchmark denominator mapped cases: `3776 -> 3777`
  - `mappedDocxOpenXmlNoteCommentMediaRelationshipCases`: `1`
  - `docxOpenXmlNoteCommentMediaRelationshipAssertions`: `111`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 5133 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 files, 176901 assertions, 0 failures
- PHP JSON manifest/status validation
- `git diff --check`
- conflict-marker scan
