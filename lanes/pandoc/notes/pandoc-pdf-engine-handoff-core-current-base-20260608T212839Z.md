# Pandoc PDF Engine Handoff ByteRange Policy Slice

Session: `port-dev-pandoc-pdf-handoff-20260608T212839Z`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T212839Z`
Base accepted HEAD: `9fca7a8f155d1a30d46db28e808e4b225a69a919`

## Scope

Implemented one bounded native fake-runner diagnostic for produced PDF bytes:
signature ByteRange policy review metadata. `PdfEngineHandoff` now summarizes
field signatures and catalog permission signatures with range ordering,
non-overlap, file-fit, first gap, covered bytes, coverage-to-end,
`/Contents`-fits-gap, review status, and issue diagnostics.

This does not validate cryptographic signatures, execute JavaScript, or run a
PDF engine. It only maps already-produced fake-runner PDF bytes into review
metadata for WordPress import/export queues.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` files existed in the
  handoff-candidates directory before editing.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 977 assertions, 1 failures` because
  `pdfSignatureByteRangePolicy` was absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 984 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.

Additional verification is recorded after the final run:

- PHP lint passed:
  `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Adds one focused PHP PASS case.
- `PdfEngineHandoffTest.php` focused assertion count moves from the red-first
  `977` to final `984` assertions.
- `lanes/pandoc/lane-status.json` `phpPass` moves from `1868` to `1869`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2295` to
  `2296`.
- PDF engine inventory moves from `12` mapped cases and `108` assertions to
  `13` mapped cases and `115` assertions.

## Non-Overlap

This slice does not repeat PDF XMP/PDF-A metadata, output intents, tagged
structure, URI base metadata, page display/review metadata, LegalAttestation
policy, document security store metadata, active actions, annotation/form
actions, AcroForm field metadata, action target lists, or calculation order
work. It adds ByteRange gap/coverage/content-fit policy derived from signature
byte ranges already extracted by the native fake-runner parser.

## Dependency Closure

No new support component is needed. The slice reuses native
`PdfEngineHandoff` produced-PDF object parsing, signature extraction, catalog
permission extraction, fake-runner diagnostics, and the existing WordPress PDF
handoff example. Full upstream Pandoc/Haskell runner parity, TeX/PDF engine
rendering, Typst/browser/roff rendering, JavaScript execution, and external PDF
signature validation remain out of scope for this isolated lane.

## Follow-Up

Next non-overlapping PDF engine handoff work should target one bounded
produced-PDF review gap such as JavaScript safety metadata, form submit/export
policy, signature appearance-state review, or annotation appearance stream
policy without running external PDF engines, validators, browser renderers, or
online services.
