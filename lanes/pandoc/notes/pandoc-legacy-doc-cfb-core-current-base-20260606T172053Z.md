# Legacy DOC/CFB Version-4 Directory Count Preflight

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T172053Z`
Base: `1eadbc21a9035a80b42c4cd6fea8780a0e3f7c72`

## Behavior

`CompoundFileBinary` now validates version-4 CFB header-sector boundaries before
`LegacyDocReader` resolves `WordDocument` streams. Version-4 containers use a
4096-byte header sector whose bytes after the 512-byte header must be zero, and
they must declare the same directory-sector count in the header as the actual
FAT-backed directory chain length. A mismatched count or dirty v4 header padding
now fails closed instead of letting stream lookup proceed from a header/directory
graph contradiction.

The focused fixture builder now emits bounded 4096-byte-sector version-4 CFB
packages, and the WordPress legacy DOC handoff self-test builds a minimal v4
container to prove readable v4 streams still work while count mismatches are
rejected.

Source truth:

- Microsoft MS-CFB `Compound File Header` defines the Number of Directory
  Sectors field and states that version-4 CFB files use 4,096-byte sectors,
  with the remaining bytes in the larger header sector zero-filled:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/05060311-bfce-4b12-874d-71fd4ce63aea
- Microsoft MS-CFB `Compound File Directory Sectors` defines the directory
  sector chain and root storage entry position:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/a94d7445-c4be-49cd-b6b9-2f4abc663817

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 861 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 863 assertions, 1 failures` because the v4
  directory-count mismatch was accepted.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 864 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/CompoundFileBinary.php`,
  `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` reported no
  syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  returned `pandoc json ok`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc` passed with no output.

Status delta:

- `lane-status.json` `phpPass`: `1375` -> `1376`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1788` -> `1789`.
- Legacy DOC/CFB mapped cases: `7` -> `8`.
- Legacy DOC/CFB focused assertion inventory: `64` -> `67`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, focused in-memory CFB fixtures, and
the existing WordPress legacy DOC handoff example.

Remaining follow-up stays bounded and separate: very large version-4 directory
chain fixtures, actual picture byte extraction/export policy, OfficeArt/BLIP
drawing parsing, encrypted DOC decryption policy, FFData form option decoding,
annotation bookmark owner ranges, hydrated upstream Pandoc runner parity, and
external office converter parity.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for version-3 directory
sector count rejection, CFB header CLSID/reserved fields, MiniFAT/DIFAT start
sector consistency, unterminated DIFAT overflow chains, surplus DIFAT FAT
sector listings, FAT/DIFAT sector identity, stream/directory sector overlap,
small-stream MiniFAT cutoff preflight, directory sibling-tree validation,
orphaned directory entries, malformed directory names, directory start-sector
validation, timestamp/CLSID/state-bit provenance, encrypted FIB rejection,
`fExtChar` direct Unicode extraction, FibRgLw97 subdocument boundaries, CLX
PCD flag validation, PlcfldEdn metadata, ObjectPool metadata, inline picture
placeholders, bookmarks, notes, sections, styles, lists, or field-table
handoffs. It owns only version-4 CFB header-sector handling: directory-sector
count validation, full-header-sector offsets, and zero-padding preflight.
