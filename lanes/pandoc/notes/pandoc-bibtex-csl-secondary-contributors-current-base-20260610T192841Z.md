# Pandoc BibTeX/CSL secondary contributor handoff slice

Slice: `plib-mq4a4` on 2026-06-10 UTC.

This slice keeps the lightweight `BibtexCslProcessor` handoff aligned with CSL creator variables already rendered by the native citation processor. Direct BibTeX/BibLaTeX fields for secondary contributors now map into CSL name variables:

- `compiler`
- `editorialdirector` / `editorial-director`
- `redactor`
- `commentator`
- `annotator`
- `founder`
- `continuator`
- `reviser`
- `collaborator`
- `introduction`
- `foreword`
- `afterword`

The regression fixture verifies a bounded collection record carrying all of these fields into normalized CSL name arrays, preserving the source field map and existing bibliography text behavior.

Verification:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed 2 files / 4541 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 61625 assertions / 0 failures after rebase onto `3866414a3872bc8b19eaf933ca45b4725ec4b2f0`.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite, zip/unzip, browser renderer, external validator, online service, or live provider test was invoked.
