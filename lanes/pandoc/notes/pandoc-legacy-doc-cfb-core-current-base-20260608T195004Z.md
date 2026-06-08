# pandoc-legacy-doc-cfb-core-current-base-20260608T195004Z

## Scope

- Lane: `pandoc`
- Accepted base: `e33874b6a59046b0ea8a8d0d93a0e5bb2e4b1b0b`
- Behavior cluster: bounded CFB DIFAT padding preflight for legacy DOC stream lookup.

This slice makes `CompoundFileBinary` reject non-`FREESECT` reserved markers in unused DIFAT FAT-sector-location slots before any `WordDocument` stream is exposed. The guard covers both the header DIFAT array and DIFAT overflow sectors. The DIFAT next-sector terminator remains valid only in the dedicated next-DIFAT pointer field.

Source truth:

- Microsoft MS-CFB header structure records unused header DIFAT entries as `FREESECT`: https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/68530324-9d3d-4441-9ea9-66a2c8f79567
- Microsoft MS-CFB DIFAT sectors define FAT-sector-location slots separately from the final next-DIFAT-sector field: https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/0afa4e43-b18f-432a-9917-4f276eca7a73
- Microsoft MS-CFB sector numbers reserve `ENDOFCHAIN`, `FATSECT`, `DIFSECT`, and `FREESECT`; `FREESECT` is the unallocated marker in DIFAT arrays: https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/9d33df18-7aee-4065-9121-4eabe41c29d4

## Evidence

Red-first focused check after adding the test and before the parser guard:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1486 assertions, 1 failures
```

Final focused check after the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1493 assertions, 0 failures
```

## Status Delta

- `lane-status.json` `phpPass`: `1750 -> 1751`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2166 -> 2167`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 72`.
- Focused `LegacyDocReaderTest.php`: final `1493` assertions with 8 new DIFAT padding preflight assertions.

## Dependency Closure

No new support component is needed. This reuses the native `CompoundFileBinary` parser and the existing `LegacyDocReaderTest.php` CFB fixture builders.

Out of scope: executing Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, or live-service provider tests.

## Non-Overlap And Follow-Up

This does not repeat accepted CFB header signature/version, directory-tree reachability/sorting/color, MiniFAT cutoff, stream-sector overlap, DIFAT overflow-chain termination, surplus regular DIFAT FAT-sector listings, directory start-sector, FIB, CLX, Plcfld, SttbFnm, field-result, ObjectPool, macro, picture, bookmark, notes/comments, section, style, list-table, or include-field behavior. It only tightens DIFAT padding semantics for non-regular reserved markers in FAT-sector-location arrays.

Useful follow-up slices: version-3 stream-size bounds, master-document subdocument metadata, mail-merge metadata, or another non-overlapping PLC table.
