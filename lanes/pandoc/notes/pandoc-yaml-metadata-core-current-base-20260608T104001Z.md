# Pandoc YAML Metadata Core Current Base - Quoted Scalar Provenance

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-yaml-metadata-core-current-base-20260608T104001Z`
- Accepted base: `84b4162fa47ccf352ed51e23acf414da0446c583`

This slice adds bounded native YAML front-matter provenance for quoted scalar metadata values. The existing MarkdownReader YAML parser now records `yaml-quoted-scalar` entries in `yamlMetadataScalarProvenance` for double-quoted and single-quoted values, including multiline block values, flow map values, flow/sequence items, line-continuation sources, and explicit `!!str` quoted values.

Quoted keys are intentionally parsed without scalar-value provenance recording, so source-quoted keys do not create noisy value records. Existing typed scalar provenance remains suppressed for quoted booleans/numbers unless an explicit scalar tag owns the typed conversion.

## Source Truth And Non-Overlap

- Source truth: Pandoc YAML metadata/front-matter format contract around preserving source metadata semantics; implemented as native PHP support without invoking upstream Pandoc or external YAML parsers.
- Non-overlap: avoids prior YAML slices for ambiguous quoted top-level field names, alias diagnostic paths, explicit/null keys, plain scalar provenance, typed scalar provenance, block scalar provenance, indented document markers in block scalars, and top-level flow mapping documents.
- WordPress path: the existing `wordpress-yaml-metadata-handoff.php` self-test now checks quoted scalar provenance in the handoff metadata while preserving rendered WordPress body output.

## Status Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator `2035 -> 2036`.
- `lanes/pandoc/lane-status.json`: `phpPass` `1616 -> 1617`.
- Focused assertion delta: `MarkdownReaderTest.php` baseline `3686` assertions, final `3740` assertions, `+54`.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php` - pass.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - pass.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` - pass.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - `1 test files, 3740 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` - `yaml metadata handoff self-test ok`.
- Lane JSON validation - pass.
- `git diff --check -- lanes/pandoc` - pass.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing MarkdownReader YAML/front-matter parser, scalar path tracking, metadata provenance document attributes, focused MarkdownReader tests, and WordPress YAML metadata handoff example.

## Follow-Up

Choose a non-overlapping YAML metadata parser behavior such as collection source spans, additional directive/tag source provenance, or metadata writer parity. Do not run Pandoc, Cabal/Haskell runners, external YAML parsers, online services, live provider tests, or live-service provider tests from this lane.
