# Pandoc YAML Metadata Current-Base Writer Block Scalars

Slice: `pandoc-yaml-metadata-core-current-base-20260607T094801Z`
Base: `89b8ba4aae1770f8a4893d04b0dafbf09afb50c6`
Date: 2026-06-07 UTC

## Behavior

`MarkdownWriter` now emits safe multiline YAML metadata strings as literal block
scalars when `yamlMetadata` output is enabled. The handoff covers top-level map
values, nested map values, and sequence items, preserving exact round-trip values
through `MarkdownReader` while selecting YAML chomping indicators from the source
string shape:

- `|-` for multiline values without a trailing newline.
- `|` for values with one trailing newline.
- `|+` for values with more than one trailing newline.

Strings containing unsafe control characters or carriage returns continue to use
the existing inline quoted scalar path.

## Evidence

- No matching `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused test before the slice:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  reported `1 test files, 3384 assertions, 0 failures`.
- Red-first writer probe failed as expected before implementation because
  multiline metadata emitted escaped inline `\n` strings instead of literal block
  scalars.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  reported `1 test files, 3399 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  reported `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownWriter.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  all reported no syntax errors.
- JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
  passed.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1485` -> `1486`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1903` -> `1904`.
- New inventory keys:
  `mappedYamlMetadataWriterBlockScalarCases = 1`;
  `yamlMetadataWriterBlockScalarAssertions = 15`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`MarkdownWriter` YAML metadata emission, `MarkdownReader` block-scalar parsing,
AST metadata attributes, and the existing WordPress YAML metadata handoff
example. Pandoc, Cabal/Haskell runners, external YAML parsers, online services,
live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This does not revisit accepted YAML reader coverage for block scalars, aliases,
merge keys, explicit tags, explicit sequence keys, quoted ambiguous keys, null
keys, indented document-marker scalars, or ODF/OpenDocument work from the prior
slice. Follow-up work should stay bounded to writer-side YAML directives,
comments/source-location diagnostics, explicit multi-document stream policy, or
front-matter provenance summaries with focused PHP tests.
