# YAML metadata core current-base: explicit typed nested sequence scalars

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260608T224433Z`
Base accepted HEAD: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`

## Behavior

`MarkdownReader` now applies explicit YAML core scalar tags that are placed on
block sequence item markers before the indented child scalar is implicitly
typed. This keeps the coerced metadata value and the reviewer provenance aligned
for source like:

```yaml
review-items:
  - !!int
    0x2A
  - !!bool >-
    true
  - !!timestamp
    2026-06-08 12:34:56Z
  - !!null |-
    reviewer note is intentionally nulled
```

The resulting `yaml-typed-scalar` provenance is recorded at
`/review-items/<index>` with the sequence item source line and explicit tag.
Invalid explicit numeric child scalars remain visible as source strings and do
not receive typed provenance.

## Source truth and non-overlap

- Source truth: Pandoc YAML metadata/front-matter behavior for explicit core
  scalar tags, bounded to native PHP `MarkdownReader` metadata parsing and the
  existing WordPress YAML handoff.
- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- This follows the accepted explicit typed block-scalar slice without repeating
  it: the new behavior is specifically for nested block sequence item tags whose
  scalar value is supplied by child lines.
- This does not overlap accepted YAML explicit null-key, alias diagnostic path,
  top-level flow mapping, quoted ambiguous field, indented document-marker
  scalar, stream override, or collection provenance slices.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
  parser, online service, live provider test, or live-service provider test was
  executed.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4152 assertions, 1 failures`
  - Failure: the new explicit typed nested sequence scalar case expected 4
    typed provenance entries and found only 3.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4172 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownReader.php` -> no syntax errors
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php` -> no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` -> no syntax errors
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no output
- Root harness: not run - isolated micro-slice.

## Status delta

- `lanes/pandoc/lane-status.json`: `phpPass` `1936 -> 1937`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped`
  `2357 -> 2358`.
- Added inventory keys:
  - `mappedYamlMetadataExplicitTypedSequenceScalarCases`: `1`
  - `yamlMetadataExplicitTypedSequenceScalarAssertions`: `28`

## Dependency closure

No new support component is needed. This reuses the native PHP Markdown/YAML
metadata parser, existing scalar provenance records, and the lane-local
WordPress YAML metadata handoff smoke. Full upstream runner parity remains
gated on a hydrated pinned upstream checkout and a reviewed non-mutating Cabal
plan.

## Follow-up

A non-overlapping YAML follow-up could cover explicit core scalar tags on
indented block mapping values or broader YAML 1.2 tag/provenance diagnostics.
