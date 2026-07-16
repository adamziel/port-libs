# Pandoc YAML Metadata Core Current Base

Date: 2026-06-09 UTC
Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T032954Z`
Base accepted HEAD: `507b06f9840603abbb77bf4b360c0377f959830e`

## Behavior

`MarkdownWriter` now preserves explicit YAML `!!set` collection tags when
writing metadata blocks from documents that were read by `MarkdownReader`.
The writer uses `yamlMetadataCollectionProvenance` for set-shaped metadata
maps whose members are all `null`, and emits YAML set entries instead of
flattening reviewer label sets to ordinary null-valued maps.

Covered shapes:

- top-level explicit flow sets;
- nested mapping set values;
- set values inside metadata sequences;
- explicit empty sets, now retained through collection provenance as
  `!!set {}`.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4524 assertions, 0 failures`.
- Red-first focused after adding the set-writer assertions:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4525 assertions, 1 failures`.
  - Failure: generated YAML emitted `front-matter: null` and `[]` instead of
    explicit `!!set` metadata.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4544 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2233 -> 2234`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2642 -> 2643`.
- Added manifest counters:
  - `mappedYamlMetadataWriterSetTagCases: 1`
  - `yamlMetadataWriterSetTagAssertions: 20`

## Non-Overlap

This does not repeat accepted YAML reader support for parsing `!!set`, `!!omap`,
or `!!pairs`, ordered-pair diagnostics, writer ordered-pair tag emission,
timestamp/numeric/comment/colon/block-scalar writer quoting, YAML 1.2 schema
numeric/boolean handling, aliases, merge diagnostics, or source-span
provenance. This slice owns only writer-side preservation of explicit `!!set`
collection semantics from existing reader provenance, plus the minimal empty
explicit collection provenance needed to distinguish `!!set {}` from an empty
sequence.

## Dependency Closure

No new support component is needed. This reuses native PHP `MarkdownReader`
YAML/front-matter parsing, collection provenance records, `MarkdownWriter`
metadata emission, the focused `MarkdownReaderTest.php` suite, and the
WordPress YAML metadata handoff example.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, Word, LibreOffice, zip/unzip, external template engine,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Useful non-overlapping YAML follow-ups remain writer-side source comment
emission, richer source-span diagnostics, and downstream metadata consumer
handoff.
