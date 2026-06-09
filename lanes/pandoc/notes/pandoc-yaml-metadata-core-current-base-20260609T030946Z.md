# Pandoc YAML Metadata Core Current Base

Date: 2026-06-09 UTC
Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T030946Z`
Base accepted HEAD: `6ab30597dbaeef18dd989f9dad5bd875e13a7661`

## Behavior

`MarkdownWriter` now uses `yamlMetadataCollectionProvenance` from
`MarkdownReader` when writing YAML metadata blocks. If a metadata sequence was
read from an explicit YAML `!!omap` or `!!pairs` tag and still has the native
ordered-pair shape (`[{key, value}, ...]`), the writer emits the same tag and
writes sequence members as single-pair mappings.

This preserves duplicate ordered-map keys and reviewer pair semantics in
WordPress review front matter. Previously the writer preserved the PHP value
shape but flattened generated YAML to ordinary `key`/`value` records, losing
the YAML ordered-pair contract on the next read.

## Evidence

- Rework notes: only stale 2026-05-25 Pandoc rework notes existed under the
  handoff-candidate stale directory; no current-base rework note matched this
  session or micro-slice.
- Red-first focused check after adding the new assertion:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4502 assertions, 1 failures`.
  - Failure: generated YAML contained `- key: source-title` and `value:
    "Original export"` instead of `ordered-review: !!omap`.
- Final focused check:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4524 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2208 -> 2209`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2618 -> 2619`.
- Added manifest counters:
  - `mappedYamlMetadataWriterOrderedPairTagCases: 1`
  - `yamlMetadataWriterOrderedPairTagAssertions: 22`
- Focused assertion count in `MarkdownReaderTest.php`: `4474 -> 4524`
  relative to the previous accepted YAML metadata slice recorded in lane
  status.

## Non-Overlap

This does not repeat accepted YAML reader support for parsing `!!omap` or
`!!pairs`, invalid ordered-pair diagnostics, collection provenance recording,
tags, anchors, aliases, merge keys, block collection member spans, or
writer-side ambiguity quoting for booleans, numerics, special floats,
sexagesimal values, comments, colons, block scalars, or timestamps. It owns
only writer-side reuse of reader collection provenance to emit explicit
ordered-pair collection tags.

## Dependency Closure

No new support component is needed. This reuses native PHP `MarkdownReader`
collection provenance, `MarkdownWriter` YAML metadata emission, the focused
`MarkdownReaderTest.php` suite, and the existing WordPress YAML metadata
handoff example.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, browser renderer, online service, live provider test, or live-service
provider test was executed.

## Follow-Up

Useful non-overlapping YAML follow-ups remain writer-side source comment
emission, explicit `!!set` emission, richer source-span diagnostics, and
downstream metadata consumer handoff.
