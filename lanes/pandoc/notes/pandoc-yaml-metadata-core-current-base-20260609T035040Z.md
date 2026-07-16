# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T035040Z`

Accepted base: `64291fcd23e3d1b723e600a8842760d1fbcdb417`

## Scope

This slice stays inside native PHP YAML/front-matter metadata parsing. It does
not shell out to Pandoc, Cabal/Haskell runners, external YAML parsers, Word,
LibreOffice, zip/unzip, external converters, online services, live provider
tests, or live-service provider tests.

## Behavior

`MarkdownReader` now records non-fatal `yaml-tag` diagnostics with reason
`undefined-tag-handle` when metadata uses an undeclared named tag handle such
as `!missing!reviewer`. The diagnostic records `handle`, `suffix`,
`sourceTag`, `expected`, `path`, and `sourceLine`.

The metadata value remains available to import/review callers, and existing
tag provenance still records both unresolved source tags and declared `%TAG`
handle expansions.

Covered shapes:

- block mapping scalar value
- flow sequence scalar values
- flow mapping scalar value
- WordPress YAML metadata handoff review packet

Non-overlap:

- no repeat of `%TAG` directive URI suffix provenance
- no repeat of invalid `%TAG` syntax diagnostics
- no repeat of non-specific tags
- no repeat of duplicate mapping/set/ordered-pair diagnostics
- no repeat of alias, merge, collection, scalar, or source-span provenance
- no repeat of writer set/omap emission

## Evidence

Baseline before the focused case:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4560 assertions, 0 failures`

Red-first after adding the focused case:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4568 assertions, 1 failures`

Failure: expected four `undefined-tag-handle` diagnostics, received zero.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4583 assertions, 0 failures`

Example smoke:

`php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`

Result: `yaml metadata handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2254 -> 2255`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2660 -> 2661`
- New manifest counters:
  - `mappedYamlMetadataUndefinedTagHandleDiagnosticCases`: `1`
  - `yamlMetadataUndefinedTagHandleDiagnosticAssertions`: `23`

## Dependency Closure

No new support component is needed. This reuses the native `MarkdownReader`
YAML/front-matter parser, existing metadata diagnostics/tag provenance
handoff, focused `MarkdownReaderTest.php` coverage, and the WordPress YAML
metadata handoff smoke. Full upstream Pandoc runner parity remains separate
because it requires a hydrated Pandoc checkout and Haskell test executables.

## Follow-up

A non-overlapping follow-up can target writer-side source comment emission,
downstream metadata consumer handoff, or additional collection/source-span
provenance.
