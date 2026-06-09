# Legacy DOC CFB Core: CP_WINUNICODE Custom Property Dictionaries

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T030947Z`
Base accepted HEAD: `6ab30597dbaeef18dd989f9dad5bd875e13a7661`

## Source truth

- Microsoft MS-OLEPS `CodePageString`: dictionary strings depend on the property set CodePage; CP_WINUNICODE (`0x04B0`) uses 16-bit Unicode characters and zero padding to a 4-byte boundary.
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/a4c32611-5b79-4965-8f50-50639c138e16
- Microsoft MS-OLEPS `Dictionary Property`: property sets with named properties use a Dictionary property, and duplicate property identifiers or names are invalid.
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/4177a4bc-5547-49fe-a4d9-4767350fd9cf
- Microsoft MS-OLEPS dictionary entry examples show null-terminated 16-bit Unicode names with zero padding.
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/ccf935f2-4a15-41cf-a965-e24392905959

## Implementation

- `LegacyDocReader::readDictionary()` now validates dictionary names before metadata exposure:
  - CP_WINUNICODE names must end with a UTF-16LE null terminator.
  - CP_WINUNICODE per-entry padding must be zeroed.
  - non-Unicode dictionary names must include an 8-bit null terminator.
  - duplicate-name comparison now uses `mb_strtolower()` when available.
- Added a focused legacy DOC fixture that decodes CP_WINUNICODE user-defined custom-property names, keeps those values metadata-only in WordPress blocks, and rejects dirty Unicode padding plus unterminated Unicode/ANSI dictionary names.
- Updated the WordPress legacy DOC handoff smoke to use a CP_WINUNICODE user-defined custom-property dictionary and verify the Unicode custom property stays metadata-only.

## Verification

- Baseline before source change: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 1977 assertions, 0 failures`
- Red-first after adding the focused test, before parser fix: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - failed as expected: `Expected exception RuntimeException was not thrown`
  - `1 test files, 1983 assertions, 1 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 1985 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`

## Dependency closure

No new support component is needed. This reuses the existing native PHP CFB and OLE property-set parser; no Pandoc, Word, LibreOffice, zip/unzip, TeX/PDF engine, Haskell runner, online service, or external template/conversion service was invoked.

## Non-overlap

This slice avoids the accepted legacy DOC clusters for CFB directory hygiene, FIB encryption/Unicode text, DOP, field-code handoff, SttbFnm/Pms mail-merge metadata, embedded objects, macros, and reserved hyperlink properties. It owns only MS-OLEPS dictionary-entry validation and CP_WINUNICODE user-defined custom-property names.
