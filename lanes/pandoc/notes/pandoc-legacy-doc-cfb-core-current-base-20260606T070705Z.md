# Legacy DOC/CFB Unicode Stream Lookup Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T070705Z`
Base: `db9d063d4b899a4750fc66ce1ed63b6f4c935c9d`

## Source Truth

- MS-CFB directory names are Unicode strings and the directory tree ordering is case-insensitive over those Unicode names.
- The native CFB reader already validates directory order with uppercase Unicode sort units, but stream lookup normalized keys with ASCII-only `strtolower`.
- For WordPress/Pandoc-like review handoff, CFB streams under non-ASCII storage names must remain discoverable when callers use different Unicode case in the path.

## Patch

- `CompoundFileBinary::normalizeName()` now uses `mb_strtolower(..., 'UTF-8')` when mbstring is available, falling back to ASCII lowercasing otherwise.
- `LegacyDocReaderTest` adds a CFB fixture with `Résumé/Σύνοψη` and verifies mixed-case Unicode `hasStream`, `streamSize`, and `readStream` lookups.
- The WordPress legacy DOC handoff example now includes a non-ASCII review stream and self-tests Unicode stream lookup. Its corrupt-directory helper now follows the generated FAT directory chain instead of assuming directory entries are physically contiguous after the first directory sector.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 747 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 749 assertions, 1 failure` because Unicode CFB lookup used ASCII-only folding.
- Focused tests after patch: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 751 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reused native PHP `CompoundFileBinary`, `LegacyDocReader`, the lane-local CFB fixture builders, mbstring when present, and the existing WordPress legacy DOC handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, or live provider test was executed.

## Non-Overlap

This slice owns only Unicode-aware CFB stream path lookup. It avoids accepted legacy DOC/CFB work for CFB header version and directory-count preflight, MiniFAT/FAT/DIFAT sector identity checks, sector overlap checks, directory sibling color/order/black-height validation, orphaned active directory entries, directory timestamps/CLSID/state-bit provenance, OLE property metadata, encrypted FIB and `lKey` preflight, `fExtChar` direct Unicode text, FibRgLw97 subdocument trimming, CLX PCD flag validation, ObjectPool metadata/reference reporting, macros, SttbfAssoc, Plcfld field ranges, styles, sections, notes, bookmarks, lists, and formatting runs.

Follow-up should keep FFData option decoding, header/footer/textbox field tables, inline picture extraction, encrypted/decryption support, directory-tree salvage policy, and any embedded-package byte export policy as separate bounded slices.
