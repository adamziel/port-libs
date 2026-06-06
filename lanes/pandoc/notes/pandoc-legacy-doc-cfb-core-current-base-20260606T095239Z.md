# Legacy DOC/CFB MiniFAT Cutoff Preflight

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T095239Z`
Base: `0b6666acb8a9aa6e856d8e275d77b28730056167`

## Behavior

`CompoundFileBinary` now rejects any non-empty stream below the 4096-byte mini
stream cutoff when the package does not provide usable MiniFAT metadata. This
keeps legacy DOC package preflight aligned with the MS-CFB stream-location
contract before `LegacyDocReader` exposes `WordDocument` text or metadata.

The parser decision is shared between `readStream()` and allocation validation,
so a malformed small stream cannot be reinterpreted as a regular FAT chain
during preflight and later read through a different path.

Source truth:

- Microsoft MS-CFB "Other Directory Entries" specifies that streams below the
  mini stream cutoff exist in the mini stream and use the MiniFAT to track
  their sector chain:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/b37413bb-f3ef-4adc-b18e-29bddd62c26e
- Microsoft MS-CFB "Compound File Mini FAT Sectors" specifies that the MiniFAT
  and mini stream are not required only when all user streams are greater than
  the cutoff:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/c5d235f7-b73c-4ec5-bf8d-5c08306cd023

## Evidence

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 788 assertions, 0 failures`.
- Red-first expectation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 789 assertions, 1 failures` because the malformed
  small regular stream was accepted.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 789 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.

Status delta:

- `lane-status.json` `phpPass`: `1292` -> `1293`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1706` -> `1707`.
- Legacy DOC/CFB mapped cases: `6` -> `7`.
- Legacy DOC/CFB assertions: `38` -> `39`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, focused lane tests, and the WordPress
legacy DOC handoff example. No Pandoc, Cabal solver/build/test command,
Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted legacy DOC coverage for CFB header version,
version-3 directory-sector count, DIFAT overflow traversal, FAT/DIFAT sector
identity checks, sector overlap checks, directory sibling tree validation,
directory timestamp/CLSID/state-bit provenance, encrypted FIB rejection,
`fExtChar` Unicode text ranges, FibRgLw97 subdocument boundaries, CLX PCD flag
validation, associated strings, fields, lists, styles, bookmarks, notes,
embedded objects, macros, or WordPress block rendering. The owned behavior is
only fail-closed MiniFAT metadata selection for non-empty streams below the
mini stream cutoff.
