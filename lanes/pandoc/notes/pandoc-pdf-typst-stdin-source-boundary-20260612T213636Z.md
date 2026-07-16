# PDF/Typst stdin source boundary provenance current-base slice

Bead: plib-oel9k
Base: current main c53068873e3cc6e5f07a88f4a77e46e9863c447b
Date: 2026-06-12 UTC

## Scope

Added Typst stdin source boundary provenance to `PdfEngineHandoff` without
invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

The handoff now exposes `sourceInput` metadata when the Typst source path is
`-`, hashes supplied stdin source bytes without staging a fake `-` workspace
file, and keeps Typst root-boundary checks focused on real dependency inputs
reported by sidecars.

This slice does not change direct-format parity accounting. It is a bounded
PDF/Typst boundary provenance improvement only.

## Focused Coverage

Added one `PdfEngineHandoffTest` fixture covering:

- Typst `compile` argv with `-` source input;
- `sourceInput` propagation through plan, fake run, artifact review, and fake
  run sequence summary;
- stdin source hashing without adding `-` to produced artifact hashes;
- depfile `-` input tokens treated as stdin provenance, not workspace files;
- root read-boundary policy over a real in-root dependency input.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2197 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73416 assertions, 0 failures`
