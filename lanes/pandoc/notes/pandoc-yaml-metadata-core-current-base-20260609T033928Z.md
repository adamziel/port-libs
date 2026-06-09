# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T033928Z`

Accepted base: `74dfce3206dc1728f34071078950751a79a89c47`.

## Scope

This slice stays inside native PHP YAML/front-matter metadata parsing for
`MarkdownReader`. It does not run Pandoc, Cabal solver/build/test commands,
Haskell runners, external YAML parsers, Word, LibreOffice, zip/unzip, external
template engines, TeX/PDF engines, online services, live provider tests, or
live-service provider tests.

## Behavior

`MarkdownReader` now records non-fatal `yaml-duplicate-key` diagnostics when an
explicit YAML `!!set` repeats a member. Sets are represented as maps with null
values, so duplicate members are duplicate mapping keys for review purposes.
The parser keeps the existing collapsed set values while exposing duplicate
member paths for audit tooling.

Covered shapes:

- top-level flow set members such as `!!set {front-matter, wordpress, wordpress}`;
- block set members such as repeated `? migration`;
- nested flow and block sets inside sequence metadata items.

The WordPress YAML metadata handoff smoke now includes duplicate set members in
the migration packet and checks the emitted diagnostic paths.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`
diagnostics, source-comment provenance, ordinary duplicate mapping-key
diagnostics, alias diagnostic paths, anchors, explicit merge-tag handling, merge
sequence shadow diagnostics, flow key-only null diagnostics, collection
provenance, scalar provenance, block scalars, explicit/null keys, or top-level
flow mapping documents. It owns only duplicate member diagnostics for explicit
YAML set collections.

## Evidence

Baseline focused command before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4524 assertions, 0 failures`.

Red-first focused command after adding the test:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4532 assertions, 1 failures` because duplicate set
diagnostics were absent.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4540 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2238 -> 2239`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2647 -> 2648`.
- New manifest counters:
  `mappedYamlMetadataDuplicateSetMemberDiagnosticCases: 1` and
  `yamlMetadataDuplicateSetMemberDiagnosticAssertions: 16`.

## Dependency Closure

No new native support component is needed. This reuses the existing
`MarkdownReader` YAML/front-matter parser, existing `yaml-duplicate-key`
diagnostic shape, `MarkdownReaderTest.php`, and
`wordpress-yaml-metadata-handoff.php` smoke.

## Follow-Up

Next YAML metadata work should stay non-overlapping: additional flow collection
diagnostics, directive/tag provenance detail, scalar/source-position review
metadata, or writer-side metadata provenance are reasonable bounded targets.
