# BibTeX/CSL Processor Standalone Entry Options

Slice: `plib-redbo` on 2026-06-27.

`BibtexCslProcessor` now lifts bounded standalone BibLaTeX entry-option fields
into the existing `biblatex-options` CSL review channel. The processor path now
matches the native BibTeX parser for skip/display switches, name/list
disambiguation controls, label-date parts, sort locale, name-use toggles, and
name/item count bounds.

Explicit standalone fields replace the same option from `options={...}`, so
`skipbib={true}` overrides `options={skipbib=false}` deterministically.
Standalone `dataonly={true}` entries stay out of top-level CSL items while
referenced entryset summaries still mark them `dataOnly`. `dataonly={false}`
remains visible as provenance on ordinary items.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer,
online service, or validator was invoked.

Focused validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` - 709 assertions, 0 failures
