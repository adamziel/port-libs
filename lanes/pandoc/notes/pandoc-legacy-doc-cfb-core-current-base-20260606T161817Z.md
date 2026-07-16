# Legacy DOC/CFB Directory Name Preflight

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T161817Z`
Base: `b0745b711922fec4e93573eb719ea5fcb3413b9d`

## Behavior

`CompoundFileBinary` now rejects malformed active CFB directory entry names
before `LegacyDocReader` resolves `WordDocument`:

- active directory entries with name lengths outside the CFB UTF-16LE bounded
  range now fail closed instead of being downgraded to unused slots;
- active directory names must end with the UTF-16LE null terminator included in
  the declared name length.

The WordPress legacy DOC handoff self-test now includes a corrupt directory-name
fixture so reviewer packets do not expose text or metadata from malformed CFB
packages.

## Source Truth

MS-CFB directory entries carry a 64-byte UTF-16LE name field plus a byte length
that includes the terminating null. Active stream, storage, and root entries
must keep a valid declared name, so the native PHP reader fails closed during
CFB preflight rather than ignoring or repairing malformed active records.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 859 assertions, 0 failures`.
- Focused:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 861 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  returned `legacy doc handoff self-test ok`.
- `php -l lanes/pandoc/src/CompoundFileBinary.php`
  reported no syntax errors.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  reported no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  reported no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  returned `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  produced no output.

Root harness was not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1362` -> `1363`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1775` -> `1776`.
- Legacy DOC/CFB mapped cases: `7` -> `8`.
- Legacy DOC/CFB assertion inventory: `64` -> `66`.
- Focused `LegacyDocReaderTest.php`: `859` -> `861` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, focused lane tests, and the WordPress
legacy DOC handoff example.

Remaining follow-up stays bounded and separate: version-4 CFB directory-chain
coverage, additional FIB/PLC metadata handoffs, actual legacy DOC picture byte
extraction/export policy, OfficeArt/BLIP drawing parsing, encrypted DOC
decryption policy, full upstream Pandoc runner parity, and external office
converter parity.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for CFB header
versions, version-3 directory-sector counts, header CLSID/reserved fields,
MiniFAT/DIFAT chain consistency, surplus DIFAT FAT-sector listings, FAT/DIFAT
sector identity, sector overlap checks, small-stream MiniFAT cutoff preflight,
directory sibling-tree validation, orphaned directory entries, directory
start-sector validation, timestamp/CLSID/state-bit provenance, encrypted FIB
rejection, `fExtChar` direct Unicode extraction, FibRgLw97 subdocument
boundaries, CLX PCD flag validation, PlcfldEdn metadata, ObjectPool metadata,
inline picture placeholders, bookmarks, notes, sections, styles, lists, or
field-table handoffs. It adds only active CFB directory-name length and
terminator validation before stream lookup.
