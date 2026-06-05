# Pandoc PDF Engine Handoff Current-Base Collection Metadata

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T165550Z`
Base: `79319606ded5fc1e2be3cede8ba0365fbde09a23`

## Behavior

Added bounded produced-PDF catalog `/Collection` portfolio metadata extraction to `PdfEngineHandoff::fakeRun()` and `fakeRunSequence()` without invoking Pandoc or any PDF renderer.

The handoff now reports:

- `pdfCollectionMetadata`
- `finalPdfCollectionMetadata`
- diagnostics for collection presence, view mode, default document, schema field count, and sort field count.

The extracted metadata is intentionally shallow and native-PHP only: collection type, view mode, default document, schema fields (`Subtype`, `N`, `O`, `V`, `E`), and sort fields/ascending flags. It does not validate portfolio item file specs, embedded file membership, JavaScript behavior, signatures, or full PDF conformance.

## Evidence

Baseline before the new test:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 476 assertions, 0 failures`

Red-first after adding the focused test and before implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: failed on missing `pdfCollectionMetadata`, proving the case was not already covered.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 485 assertions, 0 failures`

Additional verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`: no syntax errors
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`: no syntax errors
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`: `pdf engine handoff self-test ok`
- JSON parse check for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `pandoc json ok`
- `git diff --check -- lanes/pandoc`: no output

Expected mapped movement:

- `phpPass`: `1010 -> 1011`
- `benchmarkDenominator.mapped`: `1465 -> 1466`
- `pdfEngineHandoffCoreCases`: `10 -> 11`
- `pdfEngineHandoffCoreAssertions`: `95 -> 104`

## Dependency Closure

No new support component is needed. This slice reuses `PdfEngineHandoff` PDF byte/dictionary helpers and does not require Pandoc, TeX/PDF engines, Typst, browser renderers, roff, JavaScript execution, external PDF validators, online services, or external converters.

The upstream-runner blocker remains unchanged: full Pandoc runner parity still requires hydrating a local Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal package/project files and buildable Tasty runner executables.

## Follow-Up

Keep these as separate bounded slices: collection item file-spec cross-checks, richer annotation metadata, DSS/LTV validation dictionaries, optional-content membership dictionaries, and full PDF validation.
