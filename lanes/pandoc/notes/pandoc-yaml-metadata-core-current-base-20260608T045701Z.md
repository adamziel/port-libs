# Pandoc YAML Metadata Core Current-Base Slice

Date: 2026-06-08 UTC
Lane: pandoc
Micro-slice: pandoc-yaml-metadata-core-current-base-20260608T045701Z
Base accepted HEAD: a7130e39566f87e0f070ab864cbb860b9ffe3872

## Behavior

`MarkdownReader` now parses Pandoc/YAML sexagesimal float metadata scalars into native PHP floats for explicit `!!float` and plain YAML values. Covered examples include `1:20:30`, `1:20:30.5`, `-0:00:02.25`, underscore-bearing components such as `1:02:0_3.5`, flow-map values, and nested reference metadata.

Invalid sexagesimal float forms remain source strings instead of being coerced. The focused cases preserve invalid minute components such as `1:60.5` and token-bearing values such as `1:2x:3.5`.

This is bounded YAML metadata support only. It does not introduce an external YAML parser, Pandoc runner, Haskell runner, Cabal solver/build/test command, online service, or live provider test.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3588 assertions, 0 failures`.
- Red probe: a source-only probe before implementation showed `1:20:30.5`, `-0:00:02.25`, `1:60.5`, and nested `2:03.5` YAML metadata were still returned as strings.
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3609 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1541 -> 1542`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1962 -> 1963`.
- New manifest inventory keys: `mappedYamlMetadataSexagesimalFloatCases = 1`, `yamlMetadataSexagesimalFloatAssertions = 21`.
- Focused assertion delta: `+21` assertions in `MarkdownReaderTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `MarkdownReader` YAML metadata scalar parser, `WordPressBlockWriter` handoff coverage, and `wordpress-yaml-metadata-handoff.php` self-test path.

## Non-Overlap

This does not repeat existing YAML support for directives, `%TAG`, tag URI suffixes, anchors, aliases, merge keys, unresolved alias paths, explicit/null keys, block scalar document-marker handling, plain scalar folding, ordinary decimal/exponent floats, infinities/NaN, or sexagesimal integers. The new behavior is limited to YAML sexagesimal float scalar coercion and invalid-form preservation.
