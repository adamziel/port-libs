# Legacy DOC/CFB Surplus DIFAT Preflight

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T144122Z`
Base: `c225160401688bd1c3ca993be227a17e71dcecc4`

## Behavior

`CompoundFileBinary` now rejects CFB packages whose header DIFAT or overflow
DIFAT chain lists more regular FAT sector IDs than the header-declared FAT
sector count. This keeps the parser fail-closed before `LegacyDocReader`
resolves `WordDocument` streams from a package whose FAT allocation graph has
surplus, undeclared FAT-sector listings.

The guard is applied after DIFAT overflow-chain traversal and before FAT
sector loading, so both header DIFAT entries and overflow DIFAT-sector entries
must agree with the declared FAT sector count.

## Source Truth

MS-CFB separates the header's declared count of FAT sectors from the DIFAT
array that lists the sector numbers for those FAT sectors. A bounded native
preflight should not silently accept extra regular sector IDs beyond that
declared count because they create an allocation graph that the header did not
declare as part of the FAT.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external office tool, online service, live provider
test, or live-service provider test was executed.

## Evidence

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 855 assertions, 1 failures` because the surplus
  DIFAT entry was accepted.
- `php -l lanes/pandoc/src/CompoundFileBinary.php`
  - `No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 856 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - no output

Root harness was not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1349` -> `1350`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1763` -> `1764`.
- Legacy DOC/CFB mapped cases: `7` -> `8`.
- Legacy DOC/CFB assertion inventory: `64` -> `66`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, focused lane tests, and the
WordPress legacy DOC handoff example.

Remaining follow-up stays bounded and separate: actual legacy DOC picture byte
extraction/export policy, OfficeArt/BLIP drawing parsing, encrypted DOC
decryption policy, hydrated upstream Pandoc runner parity, and external office
converter parity.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for CFB header
version checks, version-3 directory-sector count, MiniFAT/DIFAT header
start-sector/count consistency, unterminated DIFAT overflow chains, FAT/DIFAT
sector identity checks, sector overlap checks, small-stream MiniFAT cutoff
preflight, directory sibling-tree validation, orphaned directory entries,
directory timestamp/CLSID/state-bit provenance, encrypted FIB rejection,
`fExtChar` direct Unicode extraction, FibRgLw97 subdocument boundaries, CLX
PCD flag validation, ObjectPool metadata, inline picture placeholders,
bookmarks, notes, sections, styles, lists, or field-table handoffs. It adds
only declared-FAT-count enforcement for surplus regular FAT sector listings in
DIFAT arrays.
