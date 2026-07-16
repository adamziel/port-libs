# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T024431Z`

Accepted base: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`

## Behavior

`MarkdownReader` now records member source spans for block-style YAML metadata
collections:

- `contentStartLine` / `contentEndLine` still cover the retained collection
  content range, including comments and blank padding.
- `memberStartLine` / `memberEndLine` identify the first and last significant
  map or sequence member lines inside that block collection.
- nested maps and sequences get the same member-span treatment through the
  existing YAML collection provenance records.

This preserves reviewer-visible provenance around comments while giving
WordPress import/review tooling a precise line range for the actual editable
metadata members.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
No hydrated upstream Pandoc checkout was available in
`/home/claude/port-libs/.upstream-cache/pandoc`, so this slice used the native
PHP YAML/front-matter implementation and focused lane tests as source-truth
evidence.

Baseline focused run before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4439 assertions, 0 failures`.

Final focused run:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4466 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2177 -> 2178`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2591 -> 2592`.
- Added manifest counters:
  `mappedYamlMetadataBlockCollectionMemberSpanCases: 1` and
  `yamlMetadataBlockCollectionMemberSpanAssertions: 27`.

## Non-Overlap

This does not change YAML scalar parsing, flow collection parsing, YAML
directives, tags, anchors, aliases, merge keys, ordered-pair/set handling, or
writer-side YAML emission. It owns only additive provenance attributes for
block-style YAML metadata collection records and the directly coupled
WordPress metadata handoff smoke.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader` YAML/front-matter parser, existing collection provenance
records, focused `MarkdownReaderTest.php` coverage, and
`wordpress-yaml-metadata-handoff.php`.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, browser renderer, online service, live provider test, or live-service
provider test was executed.

## Follow-Up

Next YAML metadata work should stay non-overlapping: scalar-adjacent standalone
comments, writer-side ordered-pair emission, or downstream metadata consumer
handoff are reasonable bounded follow-ups.
