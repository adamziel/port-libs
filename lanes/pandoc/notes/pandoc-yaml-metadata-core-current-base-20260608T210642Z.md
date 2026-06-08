# YAML Metadata Writer Sexagesimal Scalars

Slice: `pandoc-yaml-metadata-core-current-base-20260608T210642Z`
Base accepted HEAD: `abc313637c76f7f217fa1dc23516e40d06807602`
Date: 2026-06-08 UTC

## Behavior

Native `MarkdownWriter` now quotes string metadata scalars that look like YAML
sexagesimal numbers, such as `2:03`, `1:20:30.5`, and sequence items such as
`0:01`.

This closes a writer/reader round-trip gap where the writer emitted those
strings as plain scalars and `MarkdownReader` parsed them back as numeric
sexagesimal metadata values. Ordinary colon-containing strings that do not
match YAML numeric sexagesimal form, such as safe URI values and `safe:inside`,
continue to emit as plain scalars.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this slice before
  editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4026 assertions, 0 failures`.
- Red-first: the same focused test failed with `1 test files, 4027 assertions,
  1 failures` because `duration: 2:03` was emitted without quotes.
- Final: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4041 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed.

## Status Delta

- `lane-status.json` `phpPass`: `1849` -> `1850`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2277` -> `2278`.
- New inventory counters:
  `mappedYamlMetadataWriterSexagesimalScalarCases: 1` and
  `yamlMetadataWriterSexagesimalScalarAssertions: 15`.

## Non-Overlap

This slice does not repeat accepted YAML anchors, aliases, merge keys, explicit
tags, explicit merge tags, tag directive provenance, quoted ambiguous field
names, flow explicit null keys, indented document-marker scalar handling,
writer hashtag quoting, or writer leading-colon quoting. It only changes
writer-side preservation for sexagesimal-looking string scalars that the native
reader already recognizes as numeric when emitted plain.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses
`MarkdownWriter` YAML scalar ambiguity detection, `MarkdownReader`
front-matter parsing, and the WordPress YAML metadata handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Next Task

A non-overlapping YAML follow-up should target bounded YAML schema edges such
as `.inf`/`.nan` string preservation, directive boundary diagnostics, or nested
collection source-span provenance.
