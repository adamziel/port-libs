# DOCX/OpenXML Note/Comment Relationship Diagnostics - 2026-06-13T0150Z

Hook: `plib-t5oio` (`[GAS TOWN] polecat 713`, rig `port_libs`).

## Scope

Implemented one bounded DOCX/OpenXML reader diagnostic slice beyond the prior
`w:subDoc` work: footnote, endnote, and comment part-local relationship
diagnostics now flow into native DOCX review summaries.

`DocxOpenXmlReader` now carries each note/comment part's `.rels` file into the
reader path and summarizes:

- relationship part name and relationship IDs
- internal and external relationship counts
- target part and external target summaries
- missing target and missing content-type issue counts/codes
- target query/fragment suffix provenance
- per-footnote, per-endnote, and per-comment referenced relationship IDs

External relationships are surfaced as external targets, not treated as missing
package parts. Missing local relationship targets receive bounded diagnostic
issue codes without invoking external validators.

## Counters

- DOCX/OpenXML local passing evidence: 94 / 35 static upstream DOCX rows.
- Pandoc lane: 3,338 PHP passes / 0 failures.
- Mapped upstream cases: 3,297 / 2,276.
- New mapped slice counter: `mappedDocxOpenXmlNoteRelationshipDiagnosticsCases = 1`.
- New focused assertion counter: `docxOpenXmlNoteRelationshipDiagnosticsAssertions = 31`.

## Remaining Critical DOCX Gaps

DOCX/OpenXML is still partial, not ship-ready. Remaining critical gaps include
broader direct WordprocessingML reader parity for fields, content controls,
revision markup, list/table behavior, and package edge cases. Writer parity is
outside this input burn-down slice.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 2,483 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 45 test files, 75,121 assertions, 0 failures

No Pandoc binary, Word, LibreOffice, office suite, zip/unzip, ZipArchive,
browser renderer, online service, live provider test, or external validator was
used for this slice.
