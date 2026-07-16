# Pandoc YAML Metadata Core Current Base

Date: 2026-06-09 UTC
Base accepted HEAD: `a90c290373fc105bb0c871a8045e20501401691f`
Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T023134Z`

## Behavior

This slice records YAML front-matter comments immediately before explicit block
sequence mapping-key items at the normalized item-key path. For example, a
comment before `- ? [source, uri]` in `review-items` now records provenance at
`/review-items/0/[source, uri]` instead of only `/review-items`.

The implementation defers standalone sequence comments only when the next
sequence item is an explicit mapping key. Ordinary sequence comments still
record immediately, preserving existing comment ordering for previously mapped
YAML metadata cases.

## Evidence

- Baseline focused check before this slice: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 4389 assertions, 0 failures`.
- Red-first check after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` failed with `1 test files, 4402 assertions, 1 failures`; the parser recorded comments at `/review-items` and `/references/0/review-links` instead of the explicit item-key paths.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 4406 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2153 -> 2154`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2577 -> 2578`.
- Added inventory counters for one explicit sequence-key comment provenance case and 17 focused assertions.

## Non-Overlap

This does not repeat accepted YAML coverage for standalone block-sequence
comments, flow comments, trailing comments, explicit-key separator comments,
block scalar comments, directive/tag/anchor/alias/merge comments, ordered pairs,
or collection provenance. This slice only owns comments immediately before
explicit block-sequence mapping-key items.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`MarkdownReader` YAML/front-matter parser, existing AST attribute handoff, the
focused `MarkdownReaderTest.php` suite, and the existing WordPress YAML metadata
handoff example. No Pandoc, Haskell runner, office tool, zip/unzip, external
template engine, TeX/PDF engine, browser renderer, online service, or live
provider test was invoked.

## Follow-Up

Useful next YAML gates are multiline explicit mapping-key normalization,
writer-side metadata comment emission, and source-span diagnostics for
reviewer-facing metadata provenance.
