# pandoc-yaml-metadata-core-current-base-20260609T051012Z

Base accepted HEAD: `516b4c2368ab923eeb7c71f762618468a7a4d437`

## Behavior

`MarkdownReader` now records `yaml-explicit-key-scalar` provenance for explicit
YAML metadata keys whose source key is a scalar token. The record preserves the
normalized metadata path, key source, scalar style, syntax bucket, source line,
and source span metadata for:

- block explicit scalar keys, including nested mappings;
- explicit scalar keys inside `!!set` members;
- flow explicit scalar keys with values;
- flow explicit scalar keys with implicit null values.

This keeps structured and quoted reviewer metadata keys auditable without
changing the parsed `meta` values or leaking provenance into plain metadata.

## Evidence

Red check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4692 assertions, 1 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4716 assertions, 0 failures
```

The new focused test `records pandoc yaml explicit key scalar style provenance
in nested metadata` adds 60 assertions over block, nested, sequence-item, set,
and flow explicit-key forms.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

## Non-Overlap

This does not repeat accepted YAML slices for invalid quoted escape diagnostics,
directive boundary diagnostics, `%TAG` URI suffix handling, explicit collection
tags, typed/block scalar provenance, comments, anchors, aliases, merge keys, or
writer trailing comments. It only records scalar style/source provenance for
explicit key tokens that were already parsed as metadata keys.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, tar, gzip, lz4,
ZipArchive, TeX/PDF engine, Typst, browser renderer, external converter,
external validator, online service, live provider test, or live-service
provider test was run.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2354 -> 2355`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2749 -> 2750`.
- Added inventory counters:
  - `mappedYamlMetadataExplicitKeyScalarProvenanceCases`: `1`
  - `yamlMetadataExplicitKeyScalarProvenanceAssertions`: `60`

## Dependency Closure

No new support component is needed. This reuses the native PHP `MarkdownReader`
YAML metadata parser, focused `MarkdownReaderTest.php`, and the existing
WordPress YAML metadata handoff example. Full upstream Pandoc runner parity
remains a separate upstream-runner dependency task requiring a hydrated Pandoc
checkout and Haskell test executables.

## Follow-Up

A non-overlapping YAML follow-up could target block-scalar trailing comment
placement diagnostics, writer diagnostics for malformed scalar provenance, or
downstream WordPress metadata review display.
