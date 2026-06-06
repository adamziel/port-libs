# Legacy DOC/CFB Core Current-Base Slice - 2026-06-06

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T193005Z`
Accepted base: `25ea07f71d9d374a0547131630b25b485b558f60`

## Behavior

`CompoundFileBinary` now fails closed when CFB stream directory entries carry storage-only metadata before `LegacyDocReader` looks up `WordDocument`:

- stream object CLSID bytes are rejected;
- stream object state bits are rejected;
- stream object creation FILETIME bytes are rejected;
- stream object modification FILETIME bytes are rejected.

The stream checks preserve storage/root provenance behavior while preventing malformed legacy DOC streams from exposing storage-only metadata in review packets. This follows the MS-CFB directory-entry split between stream objects and storage/root objects.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Evidence

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 864 assertions, 0 failures`

Red check after adding the focused case:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 865 assertions, 1 failures`
- Failure: `CompoundFileBinary` accepted a stream directory entry carrying storage-only CLSID/state/timestamp metadata.

Green focused check after the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 868 assertions, 0 failures`

Status delta:

- `phpPass`: `1393 -> 1394`
- mapped denominator: `1806 -> 1807`
- Legacy DOC/CFB core cases: `7 -> 8`
- Legacy DOC/CFB core assertions: `64 -> 68`
- New focused assertions: `+4`

## Verification

- `php -l lanes/pandoc/src/CompoundFileBinary.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Final local result: PHP lint passed for all changed PHP files; the focused test passed with `1 test files, 868 assertions, 0 failures`; the WordPress legacy DOC self-test printed `legacy doc handoff self-test ok`; and `git diff --check -- lanes/pandoc` produced no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CompoundFileBinary` parser, `LegacyDocReader`, the focused lane test harness, and the WordPress legacy DOC handoff example. Full CFB repair/recovery, actual picture byte extraction/export policy, drawing anchoring, OfficeArt/BLIP parsing, encrypted DOC decryption, Word/LibreOffice/Pandoc execution, Cabal/Haskell runners, and upstream Word binary runner parity remain separate bounded follow-up work.

## Non-Overlap

This avoids accepted Legacy DOC/CFB slices for CFB header version/directory-count preflight, root/storage CLSID and state-bit provenance, encrypted FIB rejection, `fExtChar` direct text decoding, `FibRgLw97` subdocument CP-count boundaries, CLX PCD flag validation, `PlcfldEdn` endnote field metadata, surplus DIFAT FAT-sector listings, MiniFAT cutoff consistency, and directory start-sector mismatch rejection. This slice only owns stream-object storage metadata rejection before stream lookup.
