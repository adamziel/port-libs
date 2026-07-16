# Pandoc YAML Metadata Stream Override Diagnostics

Slice: `pandoc-yaml-metadata-core-current-base-20260608T221109Z`
Base: `b5a355b21ceda7875c2975dde96ac65abe5fde9b`
Date: 2026-06-08 UTC

## Behavior

This slice keeps the existing Pandoc-style last-wins YAML metadata behavior for
adjacent stream documents, and adds bounded native reviewer diagnostics when a
later YAML metadata document overrides a top-level field from an earlier
metadata document. The diagnostics are exposed through
`yamlMetadataDiagnostics` with reason `stream-field-overridden`, JSON-pointer
style top-level paths, previous/current document indexes, and source line
metadata. Internal `__yamlMetadata*` fields remain filtered from public `meta`.

The WordPress YAML metadata handoff smoke now verifies the review metadata
override and the emitted stream override diagnostic before exposing importer
review packets.

## Source Truth And Boundary

Pandoc YAML metadata blocks are parsed as document metadata before conversion;
the accepted native lane already models adjacent YAML stream documents and
last-wins field merging. This patch extends that native stream provenance into
review diagnostics. No local Pandoc checkout exists under the checked upstream
cache for this worker, so the slice used accepted manifest/status source truth
and the existing native YAML format contract.

No Pandoc, Cabal solver/build/test command, Haskell runner, Stack, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

## Evidence

- Rework-note check: no current `port-pandoc-*.needs-lane-rework.md` note was present for this lane.
- Red-first focused run after adding the test failed as expected:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  produced `1 test files, 4083 assertions, 1 failures` at missing stream override diagnostics.
- Final focused reader run:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  produced `1 test files, 4092 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  produced `yaml metadata handoff self-test ok`.

Final PHP lint, JSON validation, and `git diff --check -- lanes/pandoc` evidence
is recorded in `lane-status.json` for the handoff.

## Status Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2331 -> 2332`.
- `lanes/pandoc/lane-status.json` `phpPass`: `1909 -> 1910`.
- Added one mapped YAML metadata stream override diagnostic case with 13 focused assertions.

## Dependency Closure

No new support component is needed. The patch reuses `MarkdownReader`'s native
YAML parser, stream provenance records, diagnostic filtering, and the existing
WordPress YAML metadata handoff example.

## Non-Overlap

This does not repeat prior YAML slices for anchors, aliases, merge keys, custom
tags, tag URI suffixes, flow explicit/null keys, quoted ambiguous field names,
indented document markers, scalar provenance, collection provenance, or TAG
directive provenance. The new behavior is limited to override diagnostics
between adjacent YAML metadata stream documents.

Root harness: not run - isolated micro-slice.
