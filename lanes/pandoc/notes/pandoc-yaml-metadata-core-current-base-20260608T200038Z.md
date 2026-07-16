# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260608T200038Z`

Accepted base: `e4416a27234df3582c58620f35f477531567f5a3`.

## Scope

This slice stays inside native PHP YAML/front-matter metadata parsing for
`MarkdownReader`. It does not run Pandoc, Cabal solver/build/test commands,
Haskell runners, external YAML parsers, Word, LibreOffice, zip/unzip, external
template engines, TeX/PDF engines, online services, live provider tests, or
live-service provider tests.

## Behavior

`MarkdownReader` now preserves explicit YAML collection tag provenance in
`yamlMetadataCollectionProvenance` for built-in collection tags:

- `!!set` records `explicitTag: set` on mapping-shaped set collections;
- `!!omap` records `explicitTag: omap` on ordered-pair sequence handoffs;
- `!!pairs` records `explicitTag: pairs` on ordered-pair sequence handoffs.

The parsed metadata values are unchanged, and provenance remains outside plain
`meta`. The WordPress YAML metadata smoke now verifies explicit collection tag
paths in the large migration-front-matter packet.

## Non-Overlap

This does not repeat accepted YAML directive provenance, valid or invalid `%TAG`
handling, source-comment provenance, duplicate-key diagnostics, alias diagnostic
paths, anchors, merge keys, merge-sequence shadow diagnostics, explicit merge
tags, scalar provenance, generic collection provenance, block scalars,
explicit/null keys, or top-level flow mapping documents. It owns only explicit
collection tag provenance for `!!set`, `!!omap`, and `!!pairs`.

## Evidence

Baseline focused command before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 3851 assertions, 0 failures`.

Red-first focused command after adding the probe:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 3864 assertions, 1 failures`; the new probe failed
because `yamlMetadataCollectionProvenance` lacked `explicitTag` records for
`!!set`, `!!omap`, and `!!pairs`.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 3898 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1778 -> 1779`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2196 -> 2197`.
- New manifest counters:
  `mappedYamlMetadataExplicitCollectionTagProvenanceCases: 1` and
  `yamlMetadataExplicitCollectionTagProvenanceAssertions: 47`.

## Dependency Closure

No new native support component is needed. This reuses the existing
`MarkdownReader` YAML/front-matter parser, AST metadata attributes,
`MarkdownReaderTest.php`, and `wordpress-yaml-metadata-handoff.php` smoke.

## Follow-Up

Next YAML metadata work should stay non-overlapping: directive end-marker
handling, nested collection source-span detail, or writer-side metadata
provenance are reasonable bounded targets.
