# Pandoc YAML Metadata Current-Base Writer Comment Scalars

Slice: `pandoc-yaml-metadata-core-current-base-20260608T204011Z`
Base: `ae5f6fd385045c5bd4eaa3669e2cb41d0fecb36c`

## Scope

This slice owns one bounded YAML/front-matter writer behavior: metadata scalar
values that begin with `#` must be quoted when `MarkdownWriter` emits YAML
front matter. Without this guard, values such as `#needs-review` and
`#wp-import` are emitted as YAML comments/nulls and do not round-trip through
the native `MarkdownReader`.

No Pandoc, Cabal/Haskell runner, external YAML parser, online service, live
provider test, or live-service provider test was executed.

## Behavior

- `MarkdownWriter` now treats leading `#` as a YAML plain-scalar indicator
  boundary and serializes those values as quoted strings.
- The focused test covers nested mapping values, sequence values, a top-level
  keyword, preservation of an internal `#` in `safe#inside`, native
  `MarkdownReader` round-trip metadata, and WordPress heading handoff.
- The WordPress YAML metadata example self-test now includes hashtag-style
  reviewer labels/status metadata and verifies writer quoting plus round-trip
  preservation.

## Non-Overlap

This avoids already mapped YAML metadata clusters for anchors, aliases, merge
keys, flow mappings, explicit null keys, explicit sequence keys, ambiguous
quoted field names, block-scalar document markers, and alias diagnostic paths.
It is writer-side YAML emission preservation, not another reader parser case.

## Evidence

- Baseline before the new case:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 4002 assertions, 0 failures`.
- Red-first probe after adding the focused case:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 4003 assertions, 1 failures`; writer output included
  `status: #needs-review`, `- #migration`, and `- #wp-import`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 4014 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  -> pass.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownWriter.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  -> pass.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  -> pass.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1824 -> 1825`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2248 -> 2249`.
- Added manifest counters:
  `mappedYamlMetadataWriterCommentScalarCases = 1`,
  `yamlMetadataWriterCommentScalarAssertions = 12`.

## Dependency Closure

No new support component is needed. This reuses native `MarkdownWriter`
front-matter emission, native `MarkdownReader` YAML/front-matter parsing,
`WordPressBlockWriter`, the focused Markdown reader/writer test family, and the
lane-local WordPress YAML metadata handoff example.

## Follow-Up

Choose a non-overlapping YAML metadata gap such as writer-side quoting for
another YAML indicator boundary, nested collection diagnostic spans, or
explicit collection-tag review metadata. Keep the slice native PHP and external
tool free.
