# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260608T213449Z`

Accepted base: `17b111d85a0bb4b5cb849a471da21f0b1ab9bf09`

## Behavior

`MarkdownReader` now records non-fatal `yaml-flow-key` diagnostics for flow
mapping entries that have a key but no value, while preserving the existing
YAML result where those entries parse as `null`.

Each diagnostic includes:

- `reason: flow-key-only-null`
- `syntax: explicit` for `? key` items and `implicit` for bare flow keys
- normalized `field`
- JSON-pointer `path`
- original trimmed `source`
- `sourceLine` when source-line tracking is active

The WordPress YAML metadata handoff smoke now checks the same diagnostics for
its existing explicit and implicit flow null-key examples.

## Evidence

Baseline focused run before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4041 assertions, 0 failures`.

Red-first run after adding the focused diagnostic test:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4052 assertions, 1 failures`; the new test found zero
`flow-key-only-null` diagnostics.

Final focused run:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4061 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1871 -> 1872`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2297 -> 2298`.
- Added manifest counters:
  `mappedYamlMetadataFlowNullKeyDiagnosticCases: 1` and
  `yamlMetadataFlowNullKeyDiagnosticAssertions: 20`.

## Non-Overlap

This slice does not repeat flow explicit/implicit null-key parsing, block
explicit null-key parsing, merge-sequence shadow diagnostics, directive
provenance, alias diagnostic paths, duplicate-key diagnostics, ambiguous
top-level field-name handling, block scalars, explicit map/sequence keys, or
writer-side YAML emission. It only adds reviewer diagnostics for key-only flow
mapping items that already parsed as null.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader` YAML/front-matter parser, AST metadata diagnostics, focused
`MarkdownReaderTest.php`, and the existing WordPress YAML metadata handoff
example.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Follow-Up

Next YAML metadata work should stay non-overlapping: writer-side metadata
provenance, additional tag URI review metadata, or source-span detail for block
collections are reasonable bounded targets.
