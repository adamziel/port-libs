# Pandoc BibTeX/CSL Printing Supplement Number Handoff

Slice: `plib-oxfj`
Session: `20260610T155452Z`

## Scope

Implemented the bounded BibTeX ingestion path for CSL `printing-number` and `supplement-number` variables.

`CitationCslProcessor` already normalized and rendered these CSL variables for direct item input. This slice closes the missing BibTeX package handoff by mapping `printingnumber`, `printing-number`, `printnumber`, and `print-number` into CSL `printing-number`, plus `supplementnumber` and `supplement-number` into CSL `supplement-number`.

## Evidence

- Red probe before the change: `CitationCslProcessor::bibtexItems()` returned `array (0 => NULL, 1 => NULL)` for `printingnumber={2}` and `supplement-number={1}`.
- Syntax: `php -l lanes/pandoc/src/BibtexCslParser.php` passed.
- Syntax: `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` passed.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed `1 test files, 4308 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/pandoc/tests` passed `44 test files, 60540 assertions, 0 failures`.

## Coverage

The regression covers both compact and hyphenated BibTeX field spellings, raw BibTeX provenance preservation, normalized processor item fields, CSL label/number rendering, bibliography rendering, and WordPress paragraph plus bibliography block output.

## Boundaries

No Pandoc, BibTeX, Biber, citeproc, office suite, TeX/PDF engine, browser renderer, zip/unzip, external validator, online service, live provider test, or live-service provider test was executed.
