# Pandoc PDF Engine Handoff Signature Lock Policies

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T022238Z`
Base accepted HEAD: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`

## Scope

This slice adds bounded native fake-runner inspection for produced-PDF
signature lock policy metadata. `PdfEngineHandoff::fakeRun()` now reports
`pdfSignatureLockPolicies`, and `fakeRunSequence()` carries the same metadata
through `finalPdfSignatureLockPolicies`.

The handoff resolves inline and referenced signature seed-value dictionaries
and signature field lock dictionaries, preserving:

- seed `/LockDocument` mode;
- field `/Lock` object, type, action, and listed field names;
- review status;
- deterministic policy issues such as a seed `NoChanges` mode combined with a
  field-list lock action.

This is metadata handoff only. It does not render, validate, sign, decrypt, or
execute PDFs.

## Evidence

- Rework note check before work: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  files existed.
- Red-first focused command after adding the test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 1120 assertions, 1 failures` because
  `pdfSignatureLockPolicies` was absent.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1130 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added 1 mapped native PDF engine handoff case.
- `lane-status.json` `phpPass`: `2130 -> 2131`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2557 -> 2558`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 120`.
- Focused PDF test coverage: prior PDF-engine note baseline `1118` assertions,
  final `1130` assertions (`+12`).

## Non-Overlap

This does not repeat accepted PDF handoff work for engine argv/source/resource
planning, TeX recorder/transcript/SyncTeX sidecars, log/rerun diagnostics,
page trees, outlines, page boxes, page production/display/timing/viewports,
page content streams, resource dictionaries, XMP/PDF-A/PDF-UA/PDF-X metadata,
output-intent policies, catalog preferences, destinations, name trees,
tagging, annotations, rich media, embedded files, AcroForm field summaries,
signature metadata, seed-value filter/digest/timestamp/MDP constraints,
signature byte-range policy, signature revision provenance, DSS, active
actions, optional content, collections, threads, encryption, or crypt filters.

The new surface is limited to produced-PDF seed `/LockDocument` and field
`/Lock` policy review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`PdfEngineHandoff` PDF object/value parser, signature field traversal,
fake-runner result contract, and the lane-local WordPress PDF handoff example.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external
converters, TeX/PDF engines, Typst, browser renderers, roff renderers, external
PDF validators, signing engines, online services, live provider tests, and
live-service provider tests were not executed.

Follow-up can stay local by adding FieldMDP transform cross-checks against
field lock dictionaries, DSS/VRI timestamp provenance, or xref repair
diagnostics without invoking external renderers or validators.
