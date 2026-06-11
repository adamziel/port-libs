# PDF/Typst output-format option provenance current-base slice

Bead: `plib-wuznt`

Base: `e25fac12621238ccfbf5b538de7ebfd27f763613`

Scope:
- Keep the Pandoc PDF/Typst handoff native-PHP only.
- Preserve Typst `--format` option history as inert review metadata.
- Avoid invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Implemented:
- `typstOutputFormatPolicy` now carries multi-entry or locally invalid `formatOptionProvenance` rows with raw value, normalized value, selected format, compatibility, and per-entry issues.
- The selected raw `--format` option is preserved as `selectedFormatOption` for review when history matters.
- Plan diagnostics now expose the count of preserved Typst output-format option rows.
- Added a fake-run test covering conflicting `--format=html`, `--format pdf`, and missing trailing `--format` values through plan, artifact review, and sequence summary provenance.

Verification:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed: 1 test file, 1846 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66854 assertions, 0 failures.
