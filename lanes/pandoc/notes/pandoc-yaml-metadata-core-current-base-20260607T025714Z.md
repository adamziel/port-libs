# Pandoc YAML Metadata Current-Base Flow Implicit Null Keys

## Behavior Added

Pandoc delegates `yaml_metadata_block` content to YAML parsing. This slice adds the bounded local equivalent for YAML flow mappings that contain implicit key-only entries without `?` or `:` syntax. Native `MarkdownReader` now preserves those entries as null-valued metadata keys instead of silently dropping them.

Covered forms include scalar keys, source-quoted scalar keys containing punctuation, sequence-valued keys, and mapping-valued keys inside both top-level metadata and nested reference metadata:

```yaml
---
flow-implicit-null-review: {source, "source:key", [source, uri], {owner: desk, ticket: 7}, status: approved}
references:
  - id: flow-implicit-null-ref
    metadata: {[source, key], {type: review}, state: kept}
...
```

## Source Truth Boundary

The source-truth contract is Pandoc YAML metadata behavior, not a full external YAML parser port. This slice keeps support bounded to front-matter metadata parsing in native PHP and does not run Pandoc, Cabal, Haskell runners, external YAML parsers, online services, live provider tests, or live-service provider tests.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files were present before editing.
- Red-first probe before the implementation:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\MarkdownReader; $doc=(new MarkdownReader())->read("---\nflow-review: {source, \"source:key\", [source, uri], {owner: desk, ticket: 7}, status: approved}\n...\n\n# Body\n"); var_export($doc->attr("meta", [])); echo "\n"; var_export($doc->attr("yamlMetadataDiagnostics", [])); echo "\n";'
```

The probe returned only `status => approved` under `flow-review`, proving implicit key-only flow-map entries were being dropped while no diagnostic was emitted.

- Baseline focused test before the patch: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3286 assertions, 0 failures`.
- Final focused test after the patch: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3303 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.
- PHP lint: changed PHP files passed `php -l`.
- JSON metadata validation: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json` parsed with `JSON_THROW_ON_ERROR`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1442 -> 1443`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1859 -> 1860`.
- Inventory counters added: `mappedYamlMetadataFlowImplicitNullKeyCases: 1` and `yamlMetadataFlowImplicitNullKeyAssertions: 17`.
- Focused test delta: `+1` PHP PASS case and `+17` focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses the native `MarkdownReader` YAML parser, AST metadata attrs, `MarkdownWriter`, `WordPressBlockWriter`, the existing WordPress YAML metadata handoff example, and the lane PHP harness. Full upstream YAML runner parity remains out of scope for this worker because Pandoc/Cabal/Haskell runner execution and external YAML parser parity are intentionally not invoked here.

## Non-Overlap

This slice does not repeat accepted YAML block placement, omitted opening marker, directive document start, document-marker comments, fenced-code exclusion, JSON/top-level flow documents, multiline flow, comments, quoted scalars, anchors/aliases including punctuation anchors, alias diagnostics/path, duplicate-key diagnostics, merge sequences/merge tags, explicit `?` null keys, explicit sequence/map keys, block/flow sets, ordered maps/pairs, block scalar chomping/document-marker scalar handling, ambiguous fields, or writer emission work.

## Follow-Up

Next YAML/front-matter work should stay bounded to non-overlapping metadata parser gaps such as tag provenance, numeric/date scalar provenance, or additional diagnostic path coverage without invoking external YAML tooling or upstream Pandoc runners.
