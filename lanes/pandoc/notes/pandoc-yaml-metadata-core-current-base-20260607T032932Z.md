# Pandoc YAML Metadata Core Current Base - Block Explicit Null Keys

## Behavior Added

Pandoc delegates `yaml_metadata_block` content to YAML parsing. This slice adds
the bounded native equivalent for block-style explicit mapping keys that omit a
following `:` value. `MarkdownReader` now preserves those entries as
null-valued metadata keys instead of dropping them.

Covered forms include scalar keys, source-quoted keys, sequence-valued keys,
mapping-valued keys, and custom-tagged key provenance inside a nested block
mapping:

```yaml
---
block-explicit-null-review:
  ? source
  ? "source:key"
  ? [source, uri]
  ? {owner: desk, ticket: 7}
  ? !wp-null tagged-source
  status: approved
references:
  - id: block-explicit-null-ref
    metadata:
      ? [source, key]
      ? {type: review}
      state: kept
...
```

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files were present before editing.
- Baseline focused test before the patch: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3303 assertions, 0 failures`.
- Red-first probe before the implementation showed only `status => approved` under `block-explicit-null-review`; the block explicit key-only entries were dropped while no diagnostic was emitted.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3324 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.
- PHP lint: changed PHP files passed `php -l`.
- JSON validation: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json` parsed with `JSON_THROW_ON_ERROR`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1448 -> 1449`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1864 -> 1865`.
- Inventory counters added: `mappedYamlMetadataBlockExplicitNullKeyCases: 1` and `yamlMetadataBlockExplicitNullKeyAssertions: 21`.
- Focused test delta: `+1` PHP PASS case and `+21` focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`MarkdownReader` YAML metadata parser, AST metadata attrs, `MarkdownWriter`,
`WordPressBlockWriter`, the existing WordPress YAML metadata handoff example,
and the lane PHP harness. Full upstream YAML runner parity remains out of scope
for this worker because Pandoc/Cabal/Haskell runner execution and external YAML
parser parity are intentionally not invoked here.

## Non-Overlap

This slice does not repeat accepted YAML block placement, omitted opening
marker, directive document start, document-marker comments, fenced-code
exclusion, JSON/top-level flow documents, multiline flow, comments, quoted
scalars, anchors/aliases including punctuation anchors, alias diagnostics/path,
duplicate-key diagnostics, merge sequences/merge tags, explicit or implicit
flow null keys, explicit key/value sequence/map keys, block/flow sets, ordered
maps/pairs, block scalar chomping/document-marker scalar handling, ambiguous
fields, or writer emission work. It owns only block-style explicit key-only
mapping entries whose value is omitted and therefore becomes null metadata.

## Follow-Up

Next YAML/front-matter work should stay bounded to non-overlapping parser or
writer gaps such as directive diagnostics, tag provenance edge cases,
writer-side YAML emission parity, or additional diagnostic path coverage without
invoking external YAML tooling or upstream Pandoc runners.
