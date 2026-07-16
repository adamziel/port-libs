# pandoc-legacy-doc-cfb-core-current-base-20260609T024431Z

Base accepted HEAD: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`

Implemented one bounded legacy DOC/CFB support-library slice: `CompoundFileBinary` now rejects non-empty root mini streams when MiniFAT metadata is absent. This closes a CFB allocation preflight gap where a malformed package could carry root mini-stream bytes even though no MiniFAT header chain was declared and all user streams were regular-sized.

Non-overlap: this does not repeat the accepted small-stream-without-MiniFAT guard, MiniFAT header consistency checks, MiniFAT beyond-root allocation checks, DIFAT/FAT ownership checks, CFB directory-tree hygiene, encrypted DOC/FIB rejection, DOC field-code handling, FFData decoding, DOCX/ODF work, or archive/ZIP package slices.

Focused evidence:

- Baseline `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`: `1 test files, 1972 assertions, 0 failures`.
- Red check after adding the targeted fixture, before the source guard: `1 test files, 1973 assertions, 1 failures` with `Expected exception RuntimeException was not thrown`.
- Final `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`: `1 test files, 1973 assertions, 0 failures`.
- New focused coverage: +1 PHP PASS case and +1 assertion.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`: `legacy doc handoff self-test ok`.

Status delta:

- `lanes/pandoc/lane-status.json` `phpPass`: `2177` -> `2178`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2591` -> `2592`.
- `legacyDocCfbCoreCases` / `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `65`.

Dependency closure:

No new support component is needed. This reuses the existing native PHP CFB parser, FAT/sector-chain validators, legacy DOC reader, `MarkdownWriter`, `WordPressBlockWriter`, and the lane-local legacy DOC WordPress example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

Follow-up:

For legacy DOC/CFB, choose a non-overlapping native `.doc` gap such as FFData linkage through formatting runs, further CFB allocation invariants, or bounded legacy Word metadata tables.
