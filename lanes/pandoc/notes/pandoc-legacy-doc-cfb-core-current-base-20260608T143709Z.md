# pandoc-legacy-doc-cfb-core-current-base-20260608T143709Z

## Scope

- Lane: `pandoc`
- Accepted base: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`
- Behavior cluster: bounded legacy DOC Compound File Binary FAT marker preflight.

This slice extends the native CFB parser so every physical-sector FAT entry is validated before directory traversal or stream lookup. Reserved marker `0xfffffffb`, regular sector pointers beyond the physical sector count, and FATSECT/DIFSECT markers on sectors not listed by FAT/DIFAT metadata now fail closed before any legacy DOC content is exposed.

Source truth is the MS-CFB sector allocation contract: FAT entries are either regular sector chain links, FREESECT/ENDOFCHAIN sentinels, or FATSECT/DIFSECT markers that describe the actual FAT/DIFAT sectors. Reserved or misplaced marker values are not valid stream-sector ownership.

## Evidence

Red-first focused check before the parser change:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1373 assertions, 1 failures
```

Final focused checks after the patch:

```text
php -l lanes/pandoc/src/CompoundFileBinary.php
No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1376 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

## Status Delta

- `lane-status.json` `phpPass`: `1689 -> 1690`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2109 -> 2110`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 68`

## Dependency Closure

No new support component is needed. The slice reuses `CompoundFileBinary`, `LegacyDocReader`, and the existing bounded legacy DOC fixture builder. It does not run Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runners, external office tools, online services, live provider tests, or live-service provider tests.

## Non-Overlap And Follow-Up

This intentionally avoids the accepted MiniFAT cutoff, surplus DIFAT, directory start-sector, RouteSlip metadata, ASK/FILLIN prompt-field, include-field, Plcfld endnote, and FFData decoding slices. A useful follow-up would wire true CHPX/Data-stream FFData pointers after SPRM support, add hyperlink object payload metadata, or add another CFB allocation preflight that is not already covered by FAT EOF, surplus DIFAT, or start-sector validation.
