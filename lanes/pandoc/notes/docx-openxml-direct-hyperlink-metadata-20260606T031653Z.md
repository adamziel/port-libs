## DOCX/OpenXML Direct Hyperlink Metadata - 2026-06-06

Slice: `pandoc-docx-openxml-core-current-base-20260606T031653Z`

Base accepted HEAD: `7239adb44a5c31a787201003ade2e98da17a96c8`

Implemented one bounded WordprocessingML hyperlink metadata cluster:

- `DocxReader` now preserves direct `w:hyperlink` metadata for `w:tooltip`, `w:tgtFrame`, `w:history`, `w:docLocation`, relationship id, and anchor when DOCX-specific hyperlink metadata is present.
- `w:tooltip` is exposed as the normal link `title` plus `data-docx-tooltip`; other DOCX-only fields are carried as `data-docx-*` review attributes.
- Ordinary direct hyperlinks without this metadata keep their existing Markdown and WordPress output, avoiding broad rendering churn.
- The DOCX WordPress handoff example now exercises the enriched source-packet hyperlink path.

Verification:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 1666 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`

Notes:

- The first post-implementation focused test run exposed over-broad provenance on ordinary direct links; the helper was narrowed so metadata attributes are emitted only when DOCX-specific hyperlink metadata is present.
- The same run exposed a stale table-metadata expected string in `DocxReaderTest.php` and the DOCX body example; those expected strings now match the existing richer `data-docx-table-description` output.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online sanitizer, online service, or live provider test was executed.

Dependency closure:

- No new support component was needed.
- Reused native PHP `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`, `ZipPackage`, and OPC relationship support.
- Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout and Haskell Tasty/Cabal runner closure as recorded in `lane-status.json`.

Next bounded follow-up:

- Keep hyperlink subaddress edge cases, relationship target security/reporting parity, richer DrawingML/VML object metadata, and full upstream runner parity as separate slices.
