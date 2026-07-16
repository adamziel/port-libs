# Legacy DOC/CFB DATA Mail-Merge Redirect Handoff

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T231345Z`
Base: `2e9d106a5085fd98176497cfade7ca0a16be2709`

## Source Truth

- MS-DOC `Plcfld` field type `0x28` is `DATA`.
- MS-DOC defines the field code as `DATA datafile [headerfile]`, redirecting the mail-merge data file and optional header file.
- Official reference checked: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179

## Implementation

- `LegacyDocReader` now maps Plcfld flt `0x28` to `data`.
- `DATA` field instructions are preserved as metadata-only mail-merge redirect spans with:
  - data source and optional header document basename/kind;
  - `metadata-only-native-review` and `can-expose-bytes=false`;
  - matching `SttbfAssoc` data-source/header-document records;
  - matching `SttbFnm` mail-merge data-source external filename records when present;
  - inert switch and formatting metadata.
- WordPress output keeps only the displayed field result as visible text; source paths stay in review attributes.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 1772 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 1817 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`
- Syntax: `php -l lanes/pandoc/src/LegacyDocReader.php && php -l lanes/pandoc/tests/LegacyDocReaderTest.php && php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - no syntax errors
- JSON validation: `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - both lane JSON files decoded
- Whitespace: `git diff --check -- lanes/pandoc`
  - passed

## Status Delta

- Adds one mapped legacy DOC/CFB support case.
- Focused legacy DOC reader assertions move `1772 -> 1817` (`+45`).
- Lane `phpPass` moves `1961 -> 1962`.
- Manifest `benchmarkDenominator.mapped` moves `2382 -> 2383`.
- `legacyDocCfbCoreCases` and `mappedLegacyDocCfbCoreCases` move `7 -> 8`.
- `legacyDocCfbCoreAssertions` moves `64 -> 109`.

## Non-Overlap

This is not the prior `MERGEFIELD` source-link handoff. That slice connected ordinary mail-merge fields to associated source metadata. This slice owns the distinct MS-DOC `DATA` redirect field and its explicit `datafile` / optional `headerfile` instruction payload.

## Dependency Closure

No new support component is needed. The slice reuses native CFB/WordDocument parsing, Plcfld field tables, `SttbfAssoc`, `SttbFnm`, AST spans, MarkdownWriter, and WordPressBlockWriter. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, and live-service provider tests were not executed.

## Follow-Up

Potential follow-up remains mail-merge settings tables beyond explicit `DATA` fields, or additional legacy field/result PLC metadata, if the backlog wants deeper Word mail-merge import review.
