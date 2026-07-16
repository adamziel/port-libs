# YAML Metadata Core Current Base: Schema Numeric Scalars

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T014306Z`
Base accepted HEAD: `9ab19c9e2380838c7ca01f28e9b3c5ee81262c5f`
Date: 2026-06-09 UTC

## Behavior

`MarkdownReader` now applies the supported `%YAML 1.2` schema boundary to
implicit sexagesimal-looking numeric scalars. Under `%YAML 1.2`, source values
such as `1:20:30`, `1:20:30.5`, and flow-map values like `0:01:05` remain
metadata strings and do not emit `yaml-typed-scalar` provenance.

Explicit source intent remains preserved: `!!int 1:20:30` and
`!!float 1:20:30.5` still use the existing explicit-tag path, producing numeric
metadata values and typed provenance. `%YAML 1.1` keeps the accepted legacy
implicit sexagesimal parsing behavior.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4318 assertions, 0 failures`
- Pre-implementation probe:
  - `%YAML 1.2` implicit `1:20:30` and `1:20:30.5` were coerced to `4830` and
    `4830.5`, which is the legacy sexagesimal behavior now bounded to YAML 1.1
    and explicit tags.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4344 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `2072 -> 2073`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped`
  `2484 -> 2485`.
- Added inventory counters:
  - `mappedYamlMetadataSchemaNumericCases: 1`
  - `yamlMetadataSchemaNumericAssertions: 26`

## Non-Overlap

This does not repeat accepted YAML 1.2 boolean handling, explicit typed block
scalars, explicit typed sequence or mapping child scalars, invalid merge
diagnostics, tagged keys, quoted ambiguous field names, directive boundary
diagnostics, writer-side sexagesimal string quoting, or alias/anchor/collection
provenance. It owns only schema-version-specific implicit sexagesimal numeric
resolution in YAML metadata parsing plus the directly coupled WordPress handoff
smoke.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader` YAML metadata parser, existing explicit scalar tag coercion,
`yamlMetadataScalarProvenance` records, focused `MarkdownReaderTest.php`
coverage, and the lane-local WordPress YAML metadata handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

A non-overlapping YAML follow-up could cover YAML 1.2 octal/leading-zero
numeric policy, nested collection source-span detail, or metadata handoff into
citation/bibliography consumers.
