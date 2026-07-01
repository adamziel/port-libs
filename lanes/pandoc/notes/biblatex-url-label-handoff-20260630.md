# BibLaTeX URL label handoff slice - 2026-06-30

## Scope

- Area: Citation/CSL BibLaTeX bibliography handoff.
- Bounded change: carry URL label aliases from BibLaTeX records into default bibliography text and existing CSL style rendering.
- Covered aliases: `urldescription`, `urltitle`, and `url-label`.
- No external Pandoc, citeproc, BibTeX, Biber, browser, TeX, office, ZIP, Node, or validator processes are invoked.

## Behavior

- `BibtexCslProcessor` already maps URL label aliases into `URL-label`; default bibliography text now exposes that metadata before the URL.
- Existing CSL rendering continues to normalize the field as `urlLabel` and render `url-label` / `url-description` style variables.
- Citation handoff and WordPress bibliography output preserve the URL label text for review.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

Focused result: 1 file, 658 assertions, 0 failures.
