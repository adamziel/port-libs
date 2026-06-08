# pandoc-legacy-doc-cfb-core-current-base-20260608T105311Z

## Scope

Implemented one bounded legacy DOC/CFB support-library cluster: CFB FAT reserved-marker consistency. `CompoundFileBinary` now rejects physical sectors whose FAT entry is `FATSECT` or `DIFSECT` unless that sector is declared by the FAT/DIFAT sector lists. This prevents hidden or malformed metadata-sector claims from being accepted before legacy `WordDocument` stream lookup.

## Source Truth

- MS-CFB reserves `FATSECT` and `DIFSECT` for sectors that are actually FAT or DIFAT sectors.
- This slice ports only that bounded container preflight contract. It does not repair CFB files, recover corrupt FAT chains, decrypt DOC files, evaluate fields, execute OLE/macros, or shell out to Word, LibreOffice, Pandoc, zip/unzip, Cabal, Haskell runners, online services, live provider tests, or live-service provider tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Baseline focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1272 assertions, 0 failures`.
- Red-first focused command after adding the regression:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed as expected with `1 test files, 1273 assertions, 1 failures` because an appended unlisted sector marked `FATSECT` was accepted.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1274 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/CompoundFileBinary.php` and
  `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  both reported no syntax errors.
- JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  passed for both lane JSON files.
- Whitespace check:
  `git diff --check -- lanes/pandoc` passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1622 -> 1623`
- `benchmarkDenominator.mapped`: `2041 -> 2042`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 66`
- Focused assertion delta: `+2` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP `CompoundFileBinary`, the existing sector/FAT/DIFAT parser, `LegacyDocReader` fixture builders, and the existing WordPress legacy DOC handoff example.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header version/count checks, MiniFAT cutoff and chain consistency, DIFAT overflow traversal, surplus DIFAT listings, directory tree/order/color validation, directory timestamp/CLSID/state-bit provenance, start-sector mismatch checks, stream-sector overlap checks, FIB/CLX piece tables, FibRgLw97 subdocument ranges, Plcfld stories, bookmarks/notes/comments, DOP/document metadata, ObjectPool/OLE metadata, macros, and field-result handoffs. The only new behavior is rejecting unlisted `FATSECT`/`DIFSECT` FAT markers.

## Follow-Up

Next legacy DOC/CFB work should choose a non-overlapping native MS-DOC/CFB gap such as FFData form-option decoding, route-slip metadata, hyperlink object payload metadata, or another safe table-stream review handoff.
