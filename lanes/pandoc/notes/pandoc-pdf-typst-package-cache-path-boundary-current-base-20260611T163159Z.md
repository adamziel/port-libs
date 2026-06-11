# Pandoc PDF/Typst Package Cache Path Boundary Current Base 20260611T163159Z

## Scope

- Bead: plib-wx7qw, PDF/Typst boundary provenance core blocker slice.
- Current base: a886765f4.
- Change: `PdfEngineHandoff` now treats current Typst
  `--package-cache-path` options as aliases for package-cache boundary
  provenance.
- Handoff behavior: unsafe package cache history, the selected safe cache path,
  override review metadata, artifact provenance review, and fake-run sequence
  summaries stay visible without invoking external renderers.

The option name matches current Typst compile help/manpage wording for custom
package cache paths.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed 1 file / 1763 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 65419
  assertions / 0 failures.

No Pandoc binary, Typst, TeX/PDF engine, browser renderer, external validator,
online service, live provider test, or live-service provider test was executed.
