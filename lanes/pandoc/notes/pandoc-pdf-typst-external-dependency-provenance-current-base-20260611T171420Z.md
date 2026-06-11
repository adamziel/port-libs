# pandoc-pdf-typst-external-dependency-provenance-current-base-20260611T171420Z

Slice: `plib-juc7o`, PDF/Typst boundary provenance.
Base: `0091a9f73`.

## Change

`PdfEngineHandoff` now keeps raw external dependency provenance from Typst
dependency sidecars. The fake runner records `typstExternalDependencyPolicy`
for sidecar inputs that normalize into external dependencies, preserving:

- normalized input label;
- raw sidecar token;
- dependency kind (`uri`, `file-uri`, `absolute`, `windows-absolute`,
  `typst-package`, or `invalid`);
- package and non-package counts;
- review issues for remote URI and file URI dependency boundaries.

The policy is exposed on fake-run results, artifact provenance review, and
fake-run sequence summaries as `finalTypstExternalDependencyPolicy`.

## Boundary

This is inert reviewer metadata only. It does not invoke Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services,
live provider tests, or live-service provider tests, and it does not fetch
remote/file URI dependency inputs.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1686 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64234 assertions, 0 failures.
