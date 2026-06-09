# pandoc-legacy-doc-cfb-core-current-base-20260609T012742Z

Base accepted HEAD: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

Implemented one bounded legacy DOC/CFB support-library slice: `LegacyDocReader` now reads PAPX paragraph formatting property exceptions from `PapxFkp` / `PapxInFkp`, extracts active `sprmPPropRMark` paragraph-property revision metadata, links the author index to `SttbfRMark`, and keeps the revision author/timestamp metadata out of Markdown and WordPress output.

Non-overlap: this does not repeat the existing CHPX inserted/deleted run revision-mark slice, CHPX picture/Data stream metadata, DOC field-code provenance, CFB directory preflight, or table-geometry work. It is paragraph-property revision metadata only and leaves full paragraph formatting expansion disabled.

Focused evidence:

- Baseline `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`: `1 test files, 1843 assertions, 0 failures`.
- Final `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`: `1 test files, 1875 assertions, 0 failures`.
- New focused coverage: +1 PHP PASS case and +32 assertions.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`: `legacy doc handoff self-test ok`.
- `php -l lanes/pandoc/src/LegacyDocReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`: passed with no output.

Status delta:

- `lanes/pandoc/lane-status.json` `phpPass`: `2037` -> `2038`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2451` -> `2452`.
- `legacyDocCfbCoreCases` / `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `96`.

Dependency closure:

No new support component is needed. This reuses native CFB stream access, the existing legacy DOC formatting-table reader, existing `SttbfRMark` parsing, `MarkdownWriter`, `WordPressBlockWriter`, and the lane-local legacy DOC WordPress example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

Follow-up:

For legacy DOC/CFB, choose a non-overlapping native `.doc` gap such as further paragraph/character property revision edges, FFData references through CHPX runs, or bounded master-document metadata.
