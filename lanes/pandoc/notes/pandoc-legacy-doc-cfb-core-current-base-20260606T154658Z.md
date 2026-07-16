# Legacy DOC/CFB Directory Start-Sector Preflight

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T154658Z`
Base: `fcc419a73630550abf6ce8bf9772fa5c0f06b701`

## Behavior

`CompoundFileBinary` now rejects CFB directory entries whose object type and
start-sector fields contradict each other before `LegacyDocReader` resolves
the `WordDocument` stream:

- storage entries must not reference stream sectors;
- zero-length stream entries must not keep a regular start sector;
- an empty Root Entry mini stream must not claim a start sector.

This closes a preflight gap where malformed legacy DOC containers could carry
sector claims on non-stream or empty directory objects and still reach normal
stream lookup.

## Source Truth

MS-CFB directory entries use the start-sector field only for actual stream
chains and the Root Entry mini stream. Storage entries are directory
containers, and zero-sized streams/root mini streams have no chain to follow.
A bounded native parser should fail closed on these impossible combinations
instead of ignoring the sector claim.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external office tool, online service, live provider
test, or live-service provider test was executed.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 856 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 857 assertions, 1 failures` because the new
  start-sector corruption case was accepted before implementation.
- `php -l lanes/pandoc/src/CompoundFileBinary.php`
  - `No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 859 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - no output

Root harness was not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1357` -> `1358`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1771` -> `1772`.
- Legacy DOC/CFB mapped cases: `7` -> `8`.
- Legacy DOC/CFB assertion inventory: `64` -> `67`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, focused lane tests, and the
WordPress legacy DOC handoff example.

Remaining follow-up stays bounded and separate: actual legacy DOC picture byte
extraction/export policy, OfficeArt/BLIP drawing parsing, encrypted DOC
decryption policy, version-4 CFB directory-chain coverage, stricter malformed
directory-entry preflight, hydrated upstream Pandoc runner parity, and
external office converter parity.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for CFB header
version checks, version-3 directory-sector count, MiniFAT/DIFAT header
start-sector/count consistency, unterminated DIFAT overflow chains, surplus
DIFAT FAT-sector listings, FAT/DIFAT sector identity checks, sector overlap
checks, small-stream MiniFAT cutoff preflight, directory sibling-tree
validation, orphaned directory entries, directory timestamp/CLSID/state-bit
provenance, encrypted FIB rejection, `fExtChar` direct Unicode extraction,
FibRgLw97 subdocument boundaries, CLX PCD flag validation, PlcfldEdn
metadata, ObjectPool metadata, inline picture placeholders, bookmarks, notes,
sections, styles, lists, or field-table handoffs. It adds only CFB directory
start-sector consistency for non-stream and empty stream objects.
