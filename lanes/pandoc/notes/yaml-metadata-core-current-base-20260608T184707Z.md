# YAML Metadata TAG Directive Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260608T184707Z`
Base accepted HEAD: `307a601051e9f25717d7e310792b824a3d11215f`
Date: 2026-06-08 UTC

## Behavior

Native `MarkdownReader` now records valid `%TAG` YAML directives in
`yamlMetadataDirectiveProvenance` with:

- `directive: TAG`
- `handle`
- `prefix`
- `sourceLine` when source-line tracking is active

This is additive to the existing tag-handle resolver. Explicit metadata tags
still expand through the same handle map, and invalid `%TAG` directives remain
diagnostics-only.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3794 assertions, 0 failures`.
- Red-first: the same focused test failed with 2 failures because TAG directive
  provenance only contained `%YAML` entries.
- Final: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3805 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed.

## Non-Overlap

This slice does not repeat accepted YAML anchors, aliases, merge keys, custom
tag expansion, explicit merge tags, tag URI suffix handling, invalid TAG
diagnostics, flow explicit null keys, or indented document-marker scalar
handling. It adds provenance for valid `%TAG` directive declarations
themselves.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses the
existing MarkdownReader YAML directive scanner, tag-handle resolver,
source-line provenance helper, and WordPress YAML metadata handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Next Task

A non-overlapping YAML follow-up should target bounded YAML 1.2 schema edges,
tag URI suffix review metadata, or nested collection source-span provenance.
