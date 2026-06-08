# Pandoc YAML Metadata Current-Base Writer Colon Scalars

Slice: `pandoc-yaml-metadata-core-current-base-20260608T205255Z`
Base: `65a6df3ab5094e251e3a86a2aa20ace8a8f50ea8`

## Scope

This slice owns one bounded YAML/front-matter writer behavior: metadata scalar
values that begin with `:` must be quoted when `MarkdownWriter` emits YAML
front matter. Embedded-colon values such as `safe:inside`, URI schemes, and
source fragments remain plain when otherwise safe.

No Pandoc, Cabal/Haskell runner, external YAML parser, online service, live
provider test, or live-service provider test was executed.

## Behavior

- `MarkdownWriter` now treats leading `:` as a YAML plain-scalar indicator
  boundary and serializes those values as quoted strings.
- The focused test covers nested mapping values, sequence values, a top-level
  keyword, preservation of an internal `:` in `safe:inside`, native
  `MarkdownReader` round-trip metadata, and WordPress heading handoff.
- The WordPress YAML metadata example self-test now includes colon-prefixed
  reviewer label/status metadata and verifies writer quoting plus round-trip
  preservation.

## Non-Overlap

This avoids already mapped YAML metadata clusters for anchors, aliases, merge
keys, flow mappings, explicit null keys, explicit sequence keys, ambiguous
quoted field names, block-scalar document markers, alias diagnostic paths,
explicit collection tag provenance, malformed flow collection fail-closed
handling, and writer-side `#` comment-scalar quoting. It is writer-side YAML
emission preservation for leading `:`, not another reader parser case.

## Evidence

- Baseline before the new case:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 4014 assertions, 0 failures`.
- Red-first probe after adding the focused case:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 4015 assertions, 1 failures`; writer output included
  `status: :needs-review`, `- :migration`, and `- :wp-import`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 4026 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  -> `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownWriter.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  -> pass.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  -> `pandoc json metadata valid`.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  -> pass.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1838 -> 1839`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2262 -> 2263`.
- Added manifest counters:
  `mappedYamlMetadataWriterColonIndicatorScalarCases = 1`,
  `yamlMetadataWriterColonIndicatorScalarAssertions = 12`.

## Dependency Closure

No new support component is needed. This reuses native `MarkdownWriter`
front-matter emission, native `MarkdownReader` YAML/front-matter parsing,
`WordPressBlockWriter`, the focused Markdown reader/writer test family, and the
lane-local WordPress YAML metadata handoff example.

## Follow-Up

Choose a non-overlapping YAML metadata gap such as writer-side quoting for
another YAML indicator boundary, directive boundary diagnostics, or nested
collection source-span detail. Keep the slice native PHP and external-tool free.
