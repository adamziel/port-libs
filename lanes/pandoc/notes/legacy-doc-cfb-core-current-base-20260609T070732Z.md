# Legacy DOC CFB List-Level Formatting Metadata

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T070732Z`
Accepted base: `030e94cf137586963da96dca64555cebe2ff01ee`

## Source Truth

Legacy Word list tables carry list-format records in `PlfLst` and list-format
overrides in `PlfLfo`. Each `LVL` record can include PAPX and CHPX property
groups before the number-text template. This slice preserves that bounded
formatting data as reviewer metadata only; it does not apply numbering,
execute Word field logic, or render formatting metadata into imported
WordPress blocks.

Non-overlap: this does not repeat accepted FIB Unicode extraction, encryption
preflight, CFB transaction/exact-chain validation, OLE property padding,
CHPX/PAPX FKP formatting runs, revision marks, list table IDs/overrides,
automatic numbering field cross-references, built-in field-code handoffs, or
inline picture/OLE metadata. It adds only list-level PAPX/CHPX metadata
extraction for `PlfLst` and `LFOLVL` records.

## Implementation

- `LegacyDocReader::parseLvl()` now parses PAPX paragraph properties and CHPX
  text properties embedded directly in list-level records.
- Parsed list-level properties are tagged with `source` `PlfLst` or `LFOLVL`
  plus `sourceRecord` `LVL`, keeping the existing `sourceSprm`,
  normalized value, and `metadata-only-native-review` policy.
- Document metadata now reports `listLevelParagraphPropertyCount`,
  `listLevelTextPropertyCount`, and `listLevelFormattingPolicy` when list
  templates carry supported direct formatting metadata.
- The WordPress legacy DOC handoff fixture now uses valid list-level SPRM
  bytes and self-tests that the metadata is present in review data while the
  rendered WordPress blocks keep only visible imported text.

## Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2345 assertions, 0 failures
```

Red-first after adding the focused expectations before the source change:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2324 assertions, 1 failures
```

Focused verification after this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2399 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok

php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'json_decode(...)' for lane status and manifest
pandoc JSON ok

git diff --check -- lanes/pandoc
passed with no output
```

Manifest/status delta:

- `phpPass`: `2470 -> 2472`
- `benchmarkDenominator.mapped`: `2851 -> 2853`
- `legacyDocCfbCoreCases`: `7 -> 9`
- `mappedLegacyDocCfbCoreCases`: `7 -> 9`
- `legacyDocCfbCoreAssertions`: `64 -> 118`
- Focused assertion delta: `+54` in `LegacyDocReaderTest.php`

## Dependency Closure

No new support component is needed. This reuses the native PHP CFB parser,
`LegacyDocReader` PAPX/CHPX metadata helpers, Pandoc-like AST, Markdown writer,
WordPress block writer, and lane-local legacy DOC fixtures.

## Exclusions

No Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external
template engines, external converters, TeX/PDF engines, browser renderers,
online services, live provider tests, or live-service provider tests were run.
Root harness was not run for this isolated micro-slice.

## Next Task

A useful non-overlapping follow-up would resolve stylesheet-linked list
formatting, bounded table/shape property structures, or additional safe CFB/OLE
scalar guards without invoking external office tools.
