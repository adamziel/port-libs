# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260608T194408Z`

Accepted base: `0e08b0211035224e3b0171e35911136f174e36c0`.

## Scope

This slice stays inside native PHP YAML/front-matter metadata parsing for
`MarkdownReader`. It does not run Pandoc, Cabal solver/build/test commands,
Haskell runners, external YAML parsers, Word, LibreOffice, zip/unzip, external
template engines, TeX/PDF engines, online services, live provider tests, or
live-service provider tests.

## Behavior

`MarkdownReader` now records non-fatal `yaml-merge` diagnostics when a YAML
merge sequence contains a later map key shadowed by an earlier map. Final
metadata values keep the existing YAML precedence:

- earlier maps in `<<: [*override, *base]` keep precedence over later maps;
- local keys still override merged keys;
- diagnostics expose `field`, JSON-pointer `path`, `mergeIndex`,
  `shadowedByMergeIndex`, and source line for reviewer/audit tooling.

The WordPress YAML metadata handoff smoke now checks merge-shadow diagnostic
paths in the large migration-front-matter packet while continuing to preserve
the existing merge results.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`
diagnostics, source-comment provenance, duplicate-key diagnostics, alias
diagnostic paths, anchors, explicit merge-tag handling, flow collection member
source lines, flow comments, scalar provenance, collection provenance, block
scalars, explicit/null keys, or top-level flow mapping documents. It owns only
merge-sequence shadow diagnostics for keys discarded by YAML merge precedence.

## Evidence

Baseline focused command before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 3819 assertions, 0 failures`.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 3839 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1750 -> 1751`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2166 -> 2167`.
- New manifest counters:
  `mappedYamlMetadataMergeSequenceShadowDiagnosticCases: 1` and
  `yamlMetadataMergeSequenceShadowDiagnosticAssertions: 20`.

## Dependency Closure

No new native support component is needed. This reuses the existing
`MarkdownReader` YAML/front-matter parser, AST metadata attributes,
`MarkdownReaderTest.php`, and `wordpress-yaml-metadata-handoff.php` smoke.

## Follow-Up

Next YAML metadata work should stay non-overlapping: flow collection diagnostics,
additional directive/tag provenance detail, scalar/source-position review
metadata, or writer-side metadata provenance are reasonable bounded targets.
