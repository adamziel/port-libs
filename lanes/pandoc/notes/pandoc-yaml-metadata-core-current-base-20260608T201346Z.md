# Pandoc YAML Metadata Current-Base Slice

Slice: `pandoc-yaml-metadata-core-current-base-20260608T201346Z`
Base accepted HEAD: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

## Behavior

`MarkdownReader` now fail-closes Pandoc YAML metadata blocks when a multiline
flow map or flow sequence starts in front matter but never balances its closing
delimiter. The malformed source remains visible as Markdown body content and in
WordPress block output instead of being partly accepted as metadata.

This covers both map-style values such as `review: { ...` and sequence-style
values such as `labels: [ ...` with later top-level lines before the flow
collection is closed.

## Non-Overlap

This does not repeat accepted YAML coverage for block placement, omitted
opening markers, directives, comments, anchors/aliases, duplicate keys, merge
precedence diagnostics, scalar/collection provenance, explicit/null keys,
valid multiline flow collections, or block-scalar indentation. It owns only the
malformed multiline flow collection fail-closed path.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Evidence

Red-first probe before implementation:

```text
php -r '... MarkdownReader malformed flow map probe ...'
```

Result: the unterminated `review: {` flow map was accepted as metadata with
`review => "{"` and `owner => "Import Desk"`, and only the final heading
remained in the body.

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 3886 assertions, 0 failures

php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

PHP lint passed for:

```text
php -l lanes/pandoc/src/MarkdownReader.php
php -l lanes/pandoc/tests/MarkdownReaderTest.php
php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php
```

Lane JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and
`lanes/pandoc/lane-status.json`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1795 -> 1796`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2215 -> 2216`.
- New manifest counters:
  `mappedYamlMetadataMalformedFlowCollectionCases: 1` and
  `yamlMetadataMalformedFlowCollectionAssertions: 14`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP
`MarkdownReader` YAML/front-matter parser, `WordPressBlockWriter` fallback
rendering, focused `MarkdownReaderTest.php` coverage, and the existing
WordPress YAML metadata handoff example. Full upstream Pandoc runner parity
remains a separate upstream-runner dependency audit path.

## Follow-Up

Next YAML metadata work should stay non-overlapping: flow collection
diagnostics, directive/tag source spans, or writer-side metadata provenance are
reasonable bounded targets.
