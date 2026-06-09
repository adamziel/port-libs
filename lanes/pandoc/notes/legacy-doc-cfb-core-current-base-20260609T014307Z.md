# Legacy DOC/CFB Unallocated Directory Entry Hygiene

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T014307Z`
Base: `9ab19c9e2380838c7ca01f28e9b3c5ee81262c5f`
Date: 2026-06-09 UTC

## Source Truth

Microsoft MS-CFB directory-entry semantics require unallocated directory entries to be zeroed, with left sibling, right sibling, and child stream IDs set to NOSTREAM (`0xffffffff`). This slice ports that bounded package preflight into the native PHP CFB reader before `LegacyDocReader` exposes `WordDocument` text.

Source reference: https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/026fde6e-143d-41bf-a7da-c08b2130d50e

## Implementation

- `CompoundFileBinary::parseDirectory()` now validates unallocated directory entries instead of treating any object type `0x00` entry as harmless padding.
- Valid CFB fixtures now pad directory sectors with canonical unallocated entries rather than all-zero bytes.
- `LegacyDocReaderTest.php` covers valid text extraction plus dirty unallocated entry name bytes, name length, sibling pointer, and start-sector rejection before stream lookup.
- The WordPress legacy DOC handoff example self-test now includes the same corrupt-directory preflight case.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 1876 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 1881 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

Status delta: +1 lane PHP PASS case, +5 focused assertions, manifest mapped denominator `2485`, `legacyDocCfbCoreCases` `8`, `legacyDocCfbCoreAssertions` `69`.

## Non-Overlap

This slice does not repeat the accepted MiniFAT cutoff, directory start-sector mismatch, surplus DIFAT, CLSID/state-bit, root timestamp, FibRgLw97, endnote field-table, ASK/FILLIN, or picture-placeholder legacy DOC/CFB slices.

## Dependency Closure

No new support component is needed. The work reuses native `CompoundFileBinary`, `LegacyDocReader`, focused PHP tests, and the lane-local WordPress legacy DOC handoff example. No Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Choose a non-overlapping native legacy DOC/CFB gap such as master-document subdocument metadata, mail-merge settings beyond explicit field/source linkage, or additional CFB allocation invariants.
