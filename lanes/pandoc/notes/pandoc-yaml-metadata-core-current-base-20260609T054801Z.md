# pandoc-yaml-metadata-core-current-base-20260609T054801Z

Base accepted HEAD: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Behavior

This slice adds a bounded YAML metadata review-summary handoff for WordPress import queues. `MarkdownReader` now exposes `yamlMetadataReviewSummary` on the document with:

- `reviewStatus` as `clean` or `needs-review`;
- final public metadata field names and field count;
- YAML stream document count, source kinds, and source line bounds;
- diagnostic reason/type counts and stream-overridden fields;
- tag, directive, comment, anchor, scalar, collection, and stream provenance counts.

The summary is derived from the existing native YAML parser diagnostics/provenance and stays out of plain `meta`, so imported metadata remains compatible with the existing Pandoc-like contract while review tooling gets a compact status packet.

## Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4735 assertions, 0 failures
```

Red-first check after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4742 assertions, 1 failures
```

Failure: `yamlMetadataReviewSummary` was absent.

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4758 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

Status delta:

- `lane-status.json` `phpPass`: `2399` -> `2400`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2790` -> `2791`
- `mappedYamlMetadataReviewSummaryCases`: `1`
- `yamlMetadataReviewSummaryAssertions`: `23`

## Non-Overlap

This does not repeat accepted YAML slices for anchors, aliases, merge keys, explicit keys, directive/tag handling, invalid double-quoted escape diagnostics, stream override diagnostics, scalar/collection provenance, or YAML writer comment preservation. It only adds the compact review-summary surface over already parsed metadata diagnostics/provenance.

## Dependency Closure

No new support component is needed. This reuses the native PHP `MarkdownReader` YAML metadata parser, existing diagnostics/provenance arrays, focused `MarkdownReaderTest.php` coverage, and the existing WordPress YAML metadata handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML parser, Word, LibreOffice, zip/unzip, external converter, external validator, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed. Root harness was not run for this isolated micro-slice.

## Follow-Up

A non-overlapping YAML follow-up could target downstream WordPress metadata review display, additional source-span grouping for review UIs, or writer diagnostics for malformed scalar provenance.
