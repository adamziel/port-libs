# Legacy DOC/CFB Automatic Numbering List Cross-Reference

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T223150Z`
Base accepted HEAD: `a93e698ac06f7885c2a47509237e09731628d097`

## Source Truth

MS-DOC legacy Word list tables carry LSTF list definitions, LVL level records,
LFO override records, and an automatic-numbering field discriminator. Field
results remain cached display text in the document stream. This slice preserves
that relationship as metadata-only review provenance on AUTONUM, AUTONUMOUT,
and AUTONUMLGL field spans instead of trying to regenerate numbering.

## Implementation

- `LegacyDocReader` now keeps the parsed list table and LFO override records
  active while constructing paragraph field spans.
- Automatic numbering fields now expose matched list-table provenance:
  `ilfo`, `lsid`, first paragraph CP, list index, template code, simple flag,
  level format, text template, follow mode, and override start value.
- The legacy DOC WordPress handoff example now uses an AUTONUMLGL LFO marker so
  its existing AUTONUMLGL field demonstrates the cross-reference.

## Evidence

- Red-first check: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 1747 assertions, 1 failures` because the new
  Markdown assertion did not account for Pandoc-style escaping of a leading
  numeric marker in a bracketed span.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1753 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- PHP lint passed for:
  `lanes/pandoc/src/LegacyDocReader.php`,
  `lanes/pandoc/tests/LegacyDocReaderTest.php`, and
  `lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Diff hygiene: `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1928 -> 1929`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2350 -> 2351`.
- Legacy DOC/CFB mapped cases: `7 -> 8`.
- Legacy DOC/CFB focused assertions: `64 -> 95`.

## Dependency Closure

No new support component is needed. This reuses native `LegacyDocReader` list
table parsing, existing field-tokenization handoff, `MarkdownWriter`,
`WordPressBlockWriter`, and in-memory CFB fixtures. No Pandoc, Cabal/Haskell
runner, Word, LibreOffice, zip/unzip, external office tool, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat the accepted DOC property/INFO field, ASK/FILLIN,
PlcfldEdn, directory start-sector, surplus DIFAT, MiniFAT cutoff, or FibRgLw97
subdocument slices. A useful follow-up is a different legacy DOC/CFB metadata
gap, such as OLE link metadata or another PLC table family.
