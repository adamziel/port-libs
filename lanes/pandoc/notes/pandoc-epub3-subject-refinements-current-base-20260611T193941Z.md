# Pandoc EPUB3 Subject Refinement Package Slice

Date: 2026-06-11 UTC
Bead: `plib-qybpp`
Base: `357d049ce`

## Source Truth

EPUB OPF `dc:subject` entries can carry package metadata refinements such as
`authority`, `term`, `display-seq`, and `alternate-script`. WordPress import
review packets need that provenance alongside the scalar subject list without
running Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

## Implemented Behavior

- `EpubPackage` now exposes `subjectDetails`, `subjectsByTerm`,
  `subjectsByAuthority`, and `subjectSummary` from OPF metadata.
- Subject detail packets preserve id, scheme, language, direction, authority
  refinements, term refinements, display sequence, alternate scripts, and raw
  refinements.
- `summary()['wordpressImport']['metadataDetails']` now carries the same
  subject detail and summary packets for handoff review.

## Evidence

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed: `1 test files, 1531 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: `44 test files, 66127 assertions, 0 failures`.

## Status Delta

- Added one focused EPUB package PASS case.
- Added `epubSubjectRefinementAssertions = 22`.
- `lane-status.json` `phpPass`: `3122 -> 3123`; `phpFail` remains `0`.

## Non-Overlap

This does not repeat existing EPUB OCF container/rootfile handling, manifest
resource reports, guide/collection links, package links, accessibility,
contributors, identifier/date/source/bibliographic summaries, bindings,
fallbacks, encryption, sidecars, navigation diagnostics, or media overlays.
