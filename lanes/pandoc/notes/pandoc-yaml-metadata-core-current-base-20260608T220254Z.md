# YAML Metadata Directive Boundary Diagnostics

Slice: `pandoc-yaml-metadata-core-current-base-20260608T220254Z`
Base accepted HEAD: `744d742adbbbf391182231a7a5b4f2d0d558edc2`
Date: 2026-06-08 UTC

## Behavior

Native `MarkdownReader` now treats YAML directives found after metadata document
content as diagnostics-only boundary violations. `%YAML` and `%TAG` directives
are still honored in the document preamble, including the accepted Pandoc
front-matter form where directives are followed by an inner `---` document
start marker.

Late directives now add a `yaml-directive` diagnostic with
`reason: directive-after-document-content`, preserve source-line metadata, and
do not rebind tag handles. This keeps later tagged metadata values on the
preamble-defined handle, which is important for WordPress import review packets
that should not let a stray late `%TAG` line silently change source provenance.

The WordPress YAML handoff example now includes a small late-directive fixture
and asserts that the body still renders while the review diagnostic is exposed.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4079 assertions, 0 failures`.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  failed with `1 test files, 4083 assertions, 1 failures`; late `%TAG`
  directives had no boundary diagnostic.
- Final: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4095 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted YAML directive preamble parsing, valid
`%TAG` provenance, invalid preamble `%TAG` diagnostics, source-line provenance,
anchors, aliases, merge keys, custom tag expansion, document-marker scalar
handling, or writer-side ambiguous scalar quoting. It only owns directive
boundary behavior after metadata document content has already started.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses the
existing `MarkdownReader` YAML directive scanner, metadata diagnostic/provenance
helpers, `MarkdownWriter` round-trip coverage, and `WordPressBlockWriter`
handoff.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Next Task

A non-overlapping YAML follow-up can target nested collection source-span
detail, additional writer-side scalar preservation, or handoff from YAML
metadata into citation/bibliography consumers.
