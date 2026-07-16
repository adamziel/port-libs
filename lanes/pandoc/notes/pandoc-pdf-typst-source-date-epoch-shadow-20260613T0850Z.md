# Pandoc PDF/Typst Source Date Epoch Shadow Slice

Session: `port_libs/polecats/898`
Issue: `plib-det6p`
Base: `4251113cce`

## Behavior

`PdfEngineHandoff` now preserves `SOURCE_DATE_EPOCH` provenance even when a
Typst `--creation-timestamp` engine option selects the timestamp used for the
handoff plan.

The selected CLI timestamp remains the effective `creationTimestamp`. The
shadowed environment timestamp is reported separately as
`creationTimestampEnvironment` with:

- the raw environment value;
- normalized Unix-second and UTC ISO-8601 metadata when valid;
- `source: environment` and `environmentVariable: SOURCE_DATE_EPOCH`;
- `shadowedBy: engine-option`;
- the selected CLI timestamp;
- `creation-timestamp-environment-shadowed` review diagnostics.

The metadata is carried through the plan, fake-run artifact review, and
multi-pass fake-run sequence summary without executing Typst or a PDF engine.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Focused result: `1 test files, 2250 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
- Full result: `46 test files, 76002 assertions, 0 failures`

## Accounting

- `phpPass`: `3365 -> 3366`
- `phpFail`: remains `0`
- `benchmarkDenominator.mapped`: `3243 -> 3244`
- `mappedTypstCreationTimestampBoundaryProvenanceCases`: `1 -> 2`
- `typstCreationTimestampBoundaryProvenanceAssertions`: `14 -> 27`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
