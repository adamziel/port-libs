# pandoc-pdf-typst-font-path-policy-20260612T015438Z

Slice: `plib-d38fv`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst boundary provenance for
unsafe `--font-path` inputs. Plans now add a `fontPathPolicy` review summary
when font search paths cross the local handoff boundary through absolute paths
or URI paths. The summary records total, safe, unsafe, relative, workspace,
absolute, URI, and invalid font-path counts plus review issues.

The focused fixture keeps one local font path and adds absolute plus URI font
paths. The plan, fake-run artifact review, and multipass sequence all preserve
the same `fontPathPolicy` without invoking external engines.

Verification:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file, 1952 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 68768 assertions, 0 failures)

Accounting:
- Adds one focused `PdfEngineHandoffTest.php` PASS case for Typst unsafe
  font-path boundary provenance.
- No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
  `zip`/`unzip`, Node tooling, external validators, online services, live
  provider tests, or live-service provider tests were run.
