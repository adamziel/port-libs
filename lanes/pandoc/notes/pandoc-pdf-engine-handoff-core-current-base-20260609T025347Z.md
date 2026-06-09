# Pandoc PDF Engine Handoff FieldMDP Policies

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T025347Z`
Base accepted HEAD: `9cd15b979dbce56dff062c3edd6bb41ff92b9b7e`

## Scope

This slice adds bounded native fake-runner inspection for produced-PDF
signature FieldMDP transform policy handoff. `PdfEngineHandoff::fakeRun()` now
reports `pdfSignatureFieldMdpPolicies`, and `fakeRunSequence()` carries the
same metadata through `finalPdfSignatureFieldMdpPolicies`.

The handoff resolves signature `/Reference` entries whose `/TransformMethod` is
`/FieldMDP`, associates them with the relevant signature field when available,
and cross-checks transform actions and field lists against the field `/Lock`
dictionary. It preserves matched-lock state, deterministic review status, and
issues for missing locks, action mismatches, field-list mismatches, missing
transform actions, and malformed action/field combinations.

This is metadata handoff only. It does not render, validate, sign, decrypt, or
execute PDFs.

## Evidence

- Rework note check before work: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  files existed.
- Red-first focused command after adding the test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 1144 assertions, 1 failures` because
  `pdfSignatureFieldMdpPolicies` was absent.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1155 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added 1 mapped native PDF engine handoff case.
- `lane-status.json` `phpPass`: `2188 -> 2189`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2600 -> 2601`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 121`.
- Focused PDF test coverage: red-first `1144` assertions, final `1155`
  assertions (`+13` focused assertions for the new case).

## Non-Overlap

This does not repeat accepted PDF handoff work for engine argv/source/resource
planning, TeX recorder/transcript/SyncTeX sidecars, log/rerun diagnostics,
page trees, outlines, page boxes, page production/display/timing/viewports,
page content streams, resource dictionaries, XMP/PDF-A/PDF-UA/PDF-X metadata,
output-intent policies, catalog preferences, destinations, name trees,
tagging, annotations, rich media, embedded files, AcroForm field summaries,
signature metadata, seed-value filter/digest/timestamp/MDP constraints,
signature byte-range policy, signature revision provenance, DSS, active
actions, optional content, collections, threads, encryption, crypt filters, or
signature seed `/LockDocument` and field `/Lock` policy summaries.

The new surface is limited to produced-PDF FieldMDP transform dictionaries and
their agreement with signature field `/Lock` dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`PdfEngineHandoff` PDF object/value parser, signature field traversal,
fake-runner result contract, and the lane-local WordPress PDF handoff example.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external
converters, TeX/PDF engines, Typst, browser renderers, roff renderers, external
PDF validators, signing engines, online services, live provider tests, and
live-service provider tests were not executed.

Follow-up can stay local by adding DSS/VRI timestamp provenance, xref repair
diagnostics, or signature transform policy edge cases not covered by this
FieldMDP `/Lock` cross-check.
