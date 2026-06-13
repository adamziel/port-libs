# PDF/Typst Package Dependency Conflict Policy Provenance

Date: 2026-06-13
Issue: plib-va7tv
Base: `d099006810`

## Slice

`PdfEngineHandoff` fake runs now extend `typstPackageDependencyPolicy` for Typst
`--deps` sidecars. The policy keeps the landed package dependency fields and
adds namespace counts, package coordinates, sidecar package input summaries,
metadata-only byte exposure, non-executed network policy, and multi-version
package conflict diagnostics.

This closes one bounded PDF/Typst boundary provenance gap. It does not attempt
full Typst/PDF output parity.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2225 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 45 test files, 74226 assertions, 0 failures

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.

## Remaining Gaps

PDF/Typst remains not shippable as full output parity. Remaining work includes a
native Typst reader, stronger Typst/PDF output comparison evidence, and broader
PDF engine behavior coverage outside the current no-external-engine policy.
