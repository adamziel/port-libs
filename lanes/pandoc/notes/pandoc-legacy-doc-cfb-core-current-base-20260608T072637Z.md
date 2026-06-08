# Legacy DOC/CFB Plcfld End Flags

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T072637Z`
Base: `fa0bf1a496fd8fffbd7a8cd81e2d1c2d1eb8804a`

## Source Truth

- Microsoft MS-DOC `grffldEnd` documents the saved field-end flag byte for legacy Word field descriptors: differ, zombie embed, result dirty, result edited, locked, private result, nested, and has-separator. Source: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28ab752b-055a-4725-8797-159bba0d125c
- This slice ports that bounded format contract only. It does not evaluate fields, recalculate Word results, decrypt DOC files, execute OLE/macros, or shell out to Word, LibreOffice, Pandoc, zip/unzip, Cabal, Haskell runners, online services, live provider tests, or live-service provider tests.

## Patch

- `LegacyDocReader::parsePlcfld()` now preserves the second byte of end `Fld` descriptors as additive field review metadata:
  - `endFlags`
  - `endFlagNames`
  - `differ`
  - `zombieEmbed`
  - `resultDirty`
  - `resultEdited`
  - `locked`
  - `privateResult`
  - `nested`
  - `hasSeparatorFlag`
  - `separatorFlagMatchesRange`
- The same metadata is attached to both closed `fields[]` entries and their matching end records in `fieldCharacters[]`.
- The lane-local Plcfld fixture builders now accept `flags`/`endFlags` for end records while preserving the existing `typeCode` behavior for begin records.
- The WordPress handoff example now emits realistic PAGE field end flags and checks them in `--self-test` without changing visible block rendering.

## Focused Evidence

- Baseline focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1178 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1204 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/LegacyDocReader.php`
  - `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: all reported no syntax errors.

## Status Delta

- `phpPass`: `1560 -> 1561`
- Mapped denominator: `1981 -> 1982`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 90`
- Focused assertion delta: `+26`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CFB reader, legacy DOC FIB/table-stream parser, field handoff logic, and lane-local synthetic CFB fixtures.

## Non-Overlap

This avoids the already accepted CFB header, DIFAT, MiniFAT, directory timestamp/CLSID/state-bit, FibRgLw97, PlcfldEdn, ASK/FILLIN, INCLUDEPICTURE/INCLUDETEXT, form-field, hyperlink, cross-reference, SET rendering, and external-runner dependency-audit slices. The only new behavior is safe preservation of the legacy DOC field-end descriptor flag byte for review metadata.

## Follow-Up

Potential follow-up legacy DOC work remains bounded to native PHP parser gaps such as nested field review rendering, FFData form-option decoding, hyperlink object payload metadata, or additional story-specific field tables.
