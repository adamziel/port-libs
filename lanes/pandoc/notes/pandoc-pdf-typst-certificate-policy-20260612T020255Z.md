# pandoc-pdf-typst-certificate-policy-20260612T020255Z

Slice: `plib-4al20`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst boundary provenance for
`--cert` inputs. Plans now add a `certificatePolicy` review summary when
certificate paths include unsafe trust-boundary inputs such as external URI
certificates or invalid empty values.

The policy records total, safe, unsafe, relative, workspace, absolute, URI, and
invalid certificate counts plus unique certificate issues. The focused fixture
uses one local certificate, one external URI certificate, and one missing
certificate value, then verifies plan diagnostics, fake-run artifact review,
and multipass sequence propagation.

Verification:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file, 1954 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 68827 assertions, 0 failures)

Accounting:
- Extends the focused `PdfEngineHandoffTest.php` Typst certificate PASS case
  with certificate trust-boundary policy assertions.
- No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
  `zip`/`unzip`, Node tooling, external validators, online services, live
  provider tests, or live-service provider tests were run.
