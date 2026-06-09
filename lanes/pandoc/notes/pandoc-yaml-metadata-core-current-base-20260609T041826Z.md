# Pandoc YAML Metadata Core Current Base

Date: 2026-06-09 UTC
Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T041826Z`
Base accepted HEAD: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`

## Behavior

`MarkdownWriter` now preserves safe standalone YAML source comments that were
recorded by `MarkdownReader` in `yamlMetadataCommentProvenance`.

The writer emits those comments only immediately before scalar-valued rendered
metadata paths. Covered shapes include:

- scalar mapping entries;
- scalar sequence items;
- scalar fields inside compact mapping sequence items;
- scalar ordered-pair values.

This lets WordPress review packets keep reviewer comments attached to explicit
YAML mapping keys such as `[source, uri]` and `{owner: desk, ticket: 7}` after
native PHP read/write round-trips.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4612 assertions, 0 failures`.
- Red-first focused after adding the writer-comment assertions:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4614 assertions, 1 failures`.
  - Failure: generated YAML did not include the source URI reviewer comment
    before the scalar explicit mapping-key sequence item.
- Red-first WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: failed with
    `YAML metadata self-test did not preserve standalone source comments in writer metadata`.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4628 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2286 -> 2287`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2687 -> 2688`.
- Added manifest counters:
  - `mappedYamlMetadataWriterStandaloneCommentCases: 1`
  - `yamlMetadataWriterStandaloneCommentAssertions: 16`

## Non-Overlap

This does not repeat accepted YAML reader support for standalone comment
provenance, flow comment parsing, trailing comment capture, collection tags,
source spans, JSON metadata, YAML 1.2 schema handling, scalar quoting, block
scalars, explicit null keys, anchors, aliases, or merge-key diagnostics.

This slice owns only writer-side emission of safe standalone comments from
existing provenance for scalar-valued metadata paths. Collection-path and
trailing-comment emission are intentionally excluded because indiscriminate
collection comments can invalidate the bounded generated YAML shape.

## Dependency Closure

No new support component is needed. This reuses native PHP `MarkdownReader`
comment provenance, `MarkdownWriter` metadata emission, focused
`MarkdownReaderTest.php` coverage, and the WordPress YAML metadata handoff
example.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, Word, LibreOffice, zip/unzip, external template engine,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Useful non-overlapping YAML follow-ups remain richer source-span diagnostics,
bounded trailing-comment handoff, and downstream metadata consumer review
output.
