# YAML Metadata Core Current Base: Schema Boolean Scalars

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T011950Z`
Base accepted HEAD: `403bbfa850b87a30b18d0488738d4e785be58580`
Date: 2026-06-09 UTC

## Behavior

`MarkdownReader` now tracks supported `%YAML` directives per isolated metadata
block. Under `%YAML 1.2`, implicit legacy boolean words such as `y`, `n`,
`yes`, `no`, `on`, and `off` remain strings while `true` and `false` still
coerce to booleans. `%YAML 1.1` and directive-free metadata keep the existing
legacy synonym coercion. Explicit `!!bool` tags keep the existing explicit
boolean coercion so reviewer provenance remains visible.

## Evidence

- No current `port-pandoc` lane rework notes existed for this session.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4278 assertions, 0 failures`.
- Red-first after adding the schema test: the same command failed with
  `1 test files, 4279 assertions, 1 failures` because YAML 1.2 `yes` still
  coerced to `true`.
- Final focused reader test: the same command passed with
  `1 test files, 4318 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- PHP lint passed for changed PHP files.
- JSON metadata validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2029 -> 2030`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2444 -> 2445`.
- Added manifest counters: `mappedYamlMetadataSchemaBooleanCases = 1` and
  `yamlMetadataSchemaBooleanAssertions = 40`.

## Non-Overlap

This avoids accepted YAML slices for anchors, aliases, merge diagnostics,
directive boundary diagnostics, explicit typed scalar children, tagged keys,
quoted ambiguous keys, writer-side scalar quoting, and invalid merge values.
It owns only schema-version-specific implicit boolean resolution inside the
native YAML metadata parser and WordPress YAML metadata handoff example.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader` YAML metadata parser, existing typed scalar provenance, and
the WordPress YAML metadata handoff example. No Pandoc, Cabal solver/build/test
command, Haskell runner, external YAML parser, online service, live provider
test, or live-service provider test was executed.
