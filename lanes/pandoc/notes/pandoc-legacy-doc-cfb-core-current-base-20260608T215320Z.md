# Pandoc Legacy DOC/CFB CHPX Revision Marks - 2026-06-08

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T215320Z`
Base accepted HEAD: `58f31919899f7fed2d6c14d26071a889af1e2099`

## Change

- Added bounded parsing of character FKP `ChpxFkp` CHPX property runs for revision-mark metadata.
- `LegacyDocReader` now reports inserted and deleted CHPX revision marks from `sprmCFRMarkIns`, `sprmCFRMarkDel`, `sprmCIbstRMark`, `sprmCIbstRMarkDel`, `sprmCDttmRMark`, and `sprmCDttmRMarkDel`.
- CHPX author indices are validated against `SttbfRMark` when that table is present, and resolved author names are exposed as metadata-only review records.
- WordPress and Markdown rendering are unchanged: revision application remains disabled and metadata stays out of rendered blocks.

## Source Truth

- Microsoft MS-DOC `ChpxFkp` defines the 512-byte FKP shape, `rgfc` run offsets, `rgb` CHPX pointers, and `crun` run count.
- Microsoft MS-DOC `Character Properties` defines the inserted/deleted revision SPRMs, `SttbfRMark` author index bounds, and DTTM timestamp operands.

## Verification

- `php -l lanes/pandoc/src/LegacyDocReader.php` - pass.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` - pass.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` - pass.
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` - pass, `1 test files, 1697 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` - pass, `legacy doc handoff self-test ok`.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` - pass.
- `git diff --check -- lanes/pandoc` - pass.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses `CompoundFileBinary`, `LegacyDocReader`, existing `SttbfRMark` decoding, existing FKP formatting-run metadata, and lane-local PHP fixtures. Full tracked-revision application remains intentionally out of scope until a later CHPX/PAPX property-application layer exists.

## Follow-Up

A non-overlapping legacy DOC/CFB follow-up could parse character/paragraph property revision marks such as `sprmCPropRMark` or `sprmPPropRMark`, wire FFData references through CHPX runs, or map deeper master-document metadata.
