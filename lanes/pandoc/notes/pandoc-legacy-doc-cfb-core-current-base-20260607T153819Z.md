# pandoc-legacy-doc-cfb-core-current-base-20260607T153819Z

## Scope

Implemented one bounded legacy DOC/CFB support-library cluster: exact MiniFAT
sector-count preflight. `CompoundFileBinary` now rejects a MiniFAT FAT chain
whose actual chain length exceeds or otherwise differs from the header-declared
MiniFAT sector count before any `WordDocument` stream is exposed.

Source truth: the Microsoft Compound Binary File Format header defines the
MiniFAT start sector and the number of sectors in the MiniFAT chain, while
MiniFAT sector locations are stored as a standard FAT chain:
https://download.microsoft.com/download/0/b/e/0be8bdd7-e5e8-422a-abfd-4342ed7ad886/windowscompoundbinaryfileformatspecification.pdf

This avoids overlapping accepted CFB allocation/header preflight, MiniFAT
absence for sub-cutoff streams, surplus DIFAT FAT-sector listings, directory
start-sector mismatches, FIB flags, CLX piece-table extraction, OLE metadata,
ObjectPool metadata, Plcfld field metadata, bookmarks, notes/comments,
sections, styles, lists, hyperlinks, prompt fields, data fields, and symbol
fields.

## Evidence

- Red-first focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed as expected with `1 test files, 1024 assertions, 1 failures` because
  an overlong MiniFAT chain was accepted.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1024 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.

## Mapping Delta

- `phpPass`: `1523 -> 1524`
- `benchmarkDenominator.mapped`: `1943 -> 1944`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 65`
- Focused assertions: `+1` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary` FAT/MiniFAT chain validation, the existing in-memory CFB
fixture builder, focused legacy DOC tests, and the existing WordPress legacy DOC
handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-CFB/MS-DOC preflight
or metadata such as exact directory stream count handling, additional STTBF/PLC
metadata, or remaining field/table provenance. Full upstream Pandoc runner
parity remains out of this slice because external Pandoc/Haskell/office runners
were not authorized or needed for this bounded support-library case.
