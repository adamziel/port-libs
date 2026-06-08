# pandoc-yaml-metadata-core-current-base-20260608T202219Z

## Scope

Implemented one native YAML metadata behavior cluster for explicit core
collection tags. Front matter using `!!map` and `!!seq` now preserves the
normalized `explicitTag` in `yamlMetadataCollectionProvenance` for block and
flow mappings/sequences, including nested collections and direct sequence-item
tags, while leaving parsed metadata values unchanged.

This does not shell out to Pandoc, Cabal/Haskell runners, external YAML
parsers, online services, live provider tests, or live-service provider tests.

## Non-overlap

This slice does not repeat accepted YAML directive parsing, `%TAG` handling,
custom tag provenance, `!!set`/`!!omap`/`!!pairs` collection provenance, merge
keys, aliases, scalar provenance, generic collection provenance, explicit/null
keys, block scalar marker handling, alias diagnostics, or top-level flow
mapping documents. It only adds explicit core `!!map`/`!!seq` collection tag
provenance.

## Evidence

- Baseline before the new case: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` -> `1 test files, 3919 assertions, 0 failures`.
- Red-first after adding the case: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` -> `1 test files, 3929 assertions, 1 failures`; failure was missing `/review` `map` explicit tag provenance.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` -> `1 test files, 3988 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` -> `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1802` -> `1803`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2225` -> `2226`.
- Added inventory counters: `mappedYamlMetadataExplicitCoreCollectionTagProvenanceCases = 1` and `yamlMetadataExplicitCoreCollectionTagProvenanceAssertions = 69`.

## Dependency Closure

No new support component is needed. The slice reuses `MarkdownReader`,
existing AST metadata attributes, focused `MarkdownReaderTest.php` coverage,
and the lane-local WordPress YAML metadata handoff example.

## Follow-up

Next YAML work can target a non-overlapping writer-side provenance handoff,
directive boundary diagnostics, or deeper source-span detail for anchors and
merge keys.
