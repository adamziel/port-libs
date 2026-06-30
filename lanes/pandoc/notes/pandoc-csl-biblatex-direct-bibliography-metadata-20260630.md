# Pandoc CSL BibLaTeX Direct Bibliography Metadata

Slice: `plib-t4l1h`
Date: 2026-06-30

This slice keeps already-parsed legacy BibLaTeX/CSL review metadata visible in
`BibtexCslProcessor::renderBibliographyText()` and the downstream Markdown /
WordPress bibliography handoff. The direct text renderer now emits sort and
label keys, explicit shorthand-list sort keys, collection/release state fields,
URL labels, relation aliases, and inherited `xref` keys without invoking
external cite processors.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with 655 assertions and 0 failures.
