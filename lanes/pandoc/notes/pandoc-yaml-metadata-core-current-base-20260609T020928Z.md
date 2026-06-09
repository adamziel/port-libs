# Pandoc YAML Metadata Current-Base Slice

Slice: `pandoc-yaml-metadata-core-current-base-20260609T020928Z`
Base accepted HEAD: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`
Date: 2026-06-09 UTC

## Behavior

`MarkdownReader` now treats standalone comments before and between YAML block
sequence items as comments rather than scalar text. The parsed sequence values
stay clean, and each comment is recorded in `yamlMetadataCommentProvenance` at
the current sequence path with source-line metadata for WordPress reviewer
handoffs.

Example covered shape:

```yaml
review:
  steps:
    # collect reviewer source comment
    - Collect source metadata
    # publish reviewer source comment
    - Publish WordPress blocks
```

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4344 assertions, 0 failures`.
- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4346 assertions, 1 failures`.
  - Failure: sequence comments were parsed into scalar item text and no comment
    provenance was available.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4358 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `2124 -> 2125`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator
  `2551 -> 2552`.
- Added manifest counters:
  - `mappedYamlMetadataBlockSequenceCommentProvenanceCases: 1`
  - `yamlMetadataBlockSequenceCommentProvenanceAssertions: 14`

## Non-Overlap

This slice does not repeat accepted YAML flow-comment provenance, standalone
mapping comments, trailing comments, block-scalar comments, explicit-key
separator comments, flow null-key diagnostics, invalid merge diagnostics,
anchors/aliases, tags, stream overrides, or collection provenance. It covers
only standalone comments at block-sequence item boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP `MarkdownReader`
YAML/front-matter parser, existing AST metadata attributes, focused
`MarkdownReaderTest.php`, and the WordPress YAML metadata handoff example. No
Pandoc binary, Cabal build/test command, Haskell runner, external YAML parser,
Word, LibreOffice, zip/unzip, external converter, online service, live provider
test, or live-service provider test was executed.

## Follow-Up

A non-overlapping YAML follow-up could cover comments around explicit sequence
mapping keys, writer-side metadata provenance, or additional source-span detail
for block collection boundaries.
