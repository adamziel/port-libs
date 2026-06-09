# YAML metadata core current-base: invalid merge value diagnostics

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T003319Z`
Base accepted HEAD: `28428232606f6fb6b3df81661dee1f40b90244b3`
Date: 2026-06-09 UTC

## Behavior

`MarkdownReader` now records `yaml-merge` diagnostics when a YAML merge key
contains a scalar, null, or sequence value where a mapping or sequence of
mappings is required. Valid maps in the same merge sequence still apply with
the existing earlier-map precedence, so WordPress import review packets keep
usable inherited metadata while surfacing malformed merge inputs for reviewers.

Diagnostics include:

- `reason: invalid-merge-value`
- JSON-pointer-style path ending in `/<<`
- `valueKind`
- `mergeIndex` for invalid entries inside merge sequences
- source-line metadata

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4235 assertions, 0 failures`
- Red-first focused after adding the invalid merge test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4246 assertions, 1 failures`
  - Failure: invalid merge diagnostics were absent.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4256 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `2009 -> 2010`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped`
  `2425 -> 2426`.
- Added inventory keys:
  - `mappedYamlMetadataInvalidMergeValueDiagnosticCases`: `1`
  - `yamlMetadataInvalidMergeValueDiagnosticAssertions`: `21`

## Non-Overlap

This does not repeat accepted YAML merge sequence precedence, merge-sequence
shadow diagnostics, explicit merge-tag keys, anchors, aliases, directive
provenance, explicit/null keys, scalar typing, or collection provenance. The
new behavior is limited to diagnostics for invalid values under merge keys.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader` YAML/front-matter parser, existing merge-key precedence,
diagnostic provenance helpers, focused `MarkdownReaderTest.php` coverage, and
the WordPress YAML metadata handoff smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

A non-overlapping YAML follow-up could cover schema-version-specific scalar
diagnostics, additional writer-side metadata provenance, or
citation/bibliography consumers of parsed metadata.
