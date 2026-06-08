# Pandoc YAML Metadata Typed Scalar Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260608T095832Z`

Base: `0562d818cd891a6f7e8ab8d95873ec1e5b686ccc`

## Behavior

This slice adds bounded native YAML metadata source provenance for scalar
values that Pandoc-style front matter resolves as typed metadata:

- plain and explicit-tag booleans;
- plain and explicit-tag nulls;
- plain and explicit-tag integers/floats, including sexagesimal and infinity
  forms already supported by the lane parser;
- plain and explicit-tag timestamps.

`MarkdownReader` now records these as `yaml-typed-scalar` entries in the
existing `yamlMetadataScalarProvenance` document attr. Each record preserves the
metadata path, source scalar text, scalar type, value kind, source line, and
explicit tag when present. Parsed `meta` values are unchanged. Quoted scalar
lookalikes and invalid typed-looking scalars remain excluded from typed
provenance.

## Source Truth / Non-Overlap

Existing YAML slices already covered block placement, JSON/flow documents,
anchors, aliases, merge keys, block scalars, quoted scalar escaping, explicit
null keys, flow explicit null keys, and plain scalar source provenance. This
slice is additive and only covers source provenance for successful typed scalar
coercions.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, external converter, online service, live provider test, or
live-service provider test was run.

## Evidence

Baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 3633 assertions, 0 failures
```

Red-first probe before implementation:

```text
MarkdownReader returned no yamlMetadataScalarProvenance entries for typed YAML
front-matter scalars such as yes, null, 0x2A, and timestamps.
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 3686 assertions, 0 failures

php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

PHP lint passed for changed PHP files. Lane JSON validation and
`git diff --check -- lanes/pandoc` passed.

## Status Delta

- `phpPass`: `1606 -> 1607`
- `benchmarkDenominator.mapped`: `2025 -> 2026`
- Focused assertions: `3633 -> 3686` in `MarkdownReaderTest.php` (`+53`)
- New manifest counters:
  - `mappedYamlMetadataTypedScalarProvenanceCases`: `1`
  - `yamlMetadataTypedScalarProvenanceAssertions`: `53`

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP
`MarkdownReader` YAML front-matter parser, existing metadata scalar provenance
attrs, the focused Markdown reader test family, and the WordPress YAML metadata
handoff example.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal plan.

## Follow-Up

Possible non-overlapping YAML follow-ups are directive/comment provenance,
quoted scalar review metadata, or writer-side metadata provenance preservation.
