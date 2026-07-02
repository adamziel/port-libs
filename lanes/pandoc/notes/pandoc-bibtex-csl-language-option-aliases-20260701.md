# BibTeX/CSL Language Option Aliases

Slice: `plib-6pq2p`
Date: `2026-07-01`

## Behavior

This slice extends the accepted BibLaTeX language-options handoff beyond canonical `langidopts`:

- `language-options`, `langid-options`, and `hyphenationoptions` are accepted from `.bib` entries.
- Parsed aliases are normalized into canonical `biblatex-language-options` CSL item metadata.
- Raw BibTeX field provenance remains visible under `rawBibtex.fields`.
- Direct bibliography text and CSL variables continue to expose `biblatex-language-options`, `language-options`, and language-option summaries.

The slice preserves the existing bounded contract: language options are reviewer-visible metadata only. It does not run or emulate Biber locale processing.

## Focused Evidence

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed with `1 test files, 1037 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+12` focused assertions in `BibtexCslProcessorTest.php`.

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, online services, live provider tests, or live-service provider tests were invoked.

## Non-Overlap

This does not repeat the accepted `langidopts` language-options slice. It covers only BibLaTeX language-option alias spellings that previously normalized in direct CSL input but were dropped during `.bib` conversion.
