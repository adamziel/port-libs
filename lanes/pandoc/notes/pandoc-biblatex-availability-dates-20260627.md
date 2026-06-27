# Pandoc BibLaTeX Availability Date Handoff

Slice: `pandoc-biblatex-availability-dates-20260627`

## Summary

- `BibtexCslProcessor` now maps legacy BibLaTeX `availabledate`/`available-date`, split `availableyear`/`availablemonth`/`availableday`, `submitteddate`/`submitted-date`/`submitted`, split submitted dates, and `labeldate`/`label-date`/split label dates into CSL date variables.
- Direct bibliography text now exposes `Available date`, `Submitted date`, and `Label date` when those metadata-only date variables are present.
- The focused regression covers raw field preservation, CSL style rendering, citation handoff, and WordPress bibliography output without invoking Pandoc, citeproc, BibTeX/Biber, external bibliography managers, or network services.

## Verification

```bash
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Result: `1` test file, `714` assertions, `0` failures.
