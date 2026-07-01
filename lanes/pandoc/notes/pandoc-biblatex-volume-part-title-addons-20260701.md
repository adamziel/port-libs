# BibLaTeX Volume And Part Title Addenda

Work item: plib-8ww5n

The CSL/BibTeX handoff now preserves BibLaTeX volume and part title addendum fields:

- `volumetitleaddon`
- `volume-title-addon`
- `parttitleaddon`
- `part-title-addon`

Both BibTeX ingestion paths carry the fields. `BibtexCslProcessor` emits `volume-title-addon` and `part-title-addon` in legacy CSL items and bibliography text, while `BibtexCslParser` feeds the same metadata into `CitationCslProcessor::fromBibtex()`. `CitationCslProcessor` now normalizes the fields as `volumeTitleAddon` and `partTitleAddon`, renders them in default bibliography output, exposes them to CSL `<text variable="...">`, and allows them as citation/bibliography sort variables.

Focused validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorVolumePartTitleAddonTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorVolumePartTitleAddonTest.php` with 1 file, 30 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorVolumePartTitleAddonTest.php` with 3 files, 7242 assertions, 0 failures

Full lane result and final gates:

- `php tools/run-tests.php lanes/pandoc/tests` completed with 535 files, 142324 assertions, 8912 failures, matching the broader baseline-red lane outside this CSL/BibLaTeX title-addendum slice
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `git diff --cached --check`
- `git diff --check`
- conflict-marker scan of changed lane files

No external Pandoc, citeproc, BibTeX/Biber, TeX/browser engines, Node tooling, online services, or external validators were invoked.
