# YAML Metadata Current-Base Slice 2026-06-09T071750Z

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T071750Z`
Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Scope

Implemented bounded native PHP support for explicit YAML `!!binary` scalar provenance in Pandoc metadata front matter. `MarkdownReader` now records `yaml-typed-scalar` provenance for valid explicit binary scalars across nested mappings, block scalars, sequences, and flow mappings.

Invalid explicit binary scalars remain in parsed metadata as source text, but now emit non-fatal `yaml-scalar` diagnostics with `reason: invalid-binary-scalar`, metadata path, source line, original source scalar, and the expected valid-base64 message. This gives WordPress review/import handoffs an audit signal without dropping the metadata field.

## Focused Evidence

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4867 assertions, 0 failures
```

The new focused test `records pandoc yaml explicit binary scalar provenance and invalid diagnostics` adds 26 assertions covering inline, block, sequence, and flow explicit binary scalars plus invalid-base64 diagnostics.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

## Non-Overlap

No current `port-pandoc-*.needs-lane-rework.md` note existed before editing. This slice avoids accepted YAML work for scalar/core tag coercion, explicit typed block scalars, invalid double-quoted escape diagnostics, duplicate set diagnostics, undefined tag handles, reserved directives, document-marker comments, tag directive URI suffixes, block scalar provenance, and explicit collection tags. It only covers explicit `!!binary` scalar provenance and invalid-base64 diagnostics.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `2482 -> 2483`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped` `2861 -> 2862`.
- Added inventory keys:
  - `mappedYamlMetadataBinaryScalarProvenanceCases`: `1`
  - `yamlMetadataBinaryScalarProvenanceAssertions`: `26`

## Dependency Closure

No new support component is needed. This reuses the native PHP `MarkdownReader` YAML metadata parser, existing scalar provenance/diagnostic handoff records, and the lane-local WordPress YAML metadata smoke. No Pandoc, Cabal, Haskell runner, external YAML parser, online service, or live-service provider test was executed.

Full upstream runner parity remains a separate upstream-runner dependency task gated on a hydrated pinned Pandoc checkout and reviewed non-mutating runner plan.

## Root Harness

not run - isolated micro-slice.

## Next

A non-overlapping YAML follow-up could target binary scalar writer round-trips, directive boundary recovery, or scalar style provenance in nested explicit-key contexts.
