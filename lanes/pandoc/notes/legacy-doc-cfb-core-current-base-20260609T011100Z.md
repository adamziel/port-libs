# Legacy DOC/CFB Root Creation Timestamp

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T011100Z`
Base: `09109401d59cee7a589aaf8125432abbe4aef718`
Date: 2026-06-09 UTC

## Source Truth

Microsoft MS-CFB root directory entry rules require the root storage creation
time field to be all zeroes, while root modified time may be all zeroes and is
stored separately from ordinary child stream/storage timestamps:
https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/026fde6e-143d-41bf-a7da-c08b2130d50e

## Implementation

- `CompoundFileBinary` now rejects a nonzero root storage creation FILETIME
  before stream lookup or `WordDocument` extraction.
- Existing root modified-time metadata remains accepted for review provenance.
- `LegacyDocReaderTest` adds a focused CFB fixture where stream extraction would
  otherwise succeed unless root creation timestamp bytes are preflighted.

## Evidence

- Rework scan: no current `port-pandoc-*.needs-lane-rework.md` notes existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1843 assertions, 0 failures`.
- Red-first: the same focused command failed with
  `1 test files, 1844 assertions, 1 failures` because the expected
  `RuntimeException` was not thrown.
- Final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1844 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2027` -> `2028`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2442` -> `2443`.
- `legacyDocCfbCoreCases`: `7` -> `8`.
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `65`.

## Non-Overlap

This does not repeat accepted Legacy DOC/CFB MiniFAT cutoff/allocation,
surplus-DIFAT, directory start-sector, endnote-field, ASK/FILLIN, picture
placeholder, or FIB subdocument CP-boundary slices. It also does not run or
shell out to Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell test
binaries, external office tools, online services, live provider tests, or
live-service provider tests.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`CompoundFileBinary` parser, `LegacyDocReader`, focused legacy DOC tests, and
the existing WordPress legacy DOC handoff smoke. Full upstream Pandoc runner
parity remains gated on the existing hydrated-checkout and reviewed
non-mutating Cabal-plan dependency closure.

## Follow-Up

A next non-overlapping Legacy DOC/CFB slice could cover unallocated
directory-entry hygiene, master-document subdocument metadata, or mail-merge
settings beyond explicit field/source linkage.
