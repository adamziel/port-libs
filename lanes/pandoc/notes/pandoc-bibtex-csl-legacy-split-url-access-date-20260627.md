# Pandoc BibTeX CSL Legacy Split URL Access Date Slice

## Scope

This slice covers the older `BibtexCslProcessor` handoff path, not the richer
`BibtexCslParser` path that already handled split URL access dates.

The legacy processor now maps split BibLaTeX URL access-date aliases into CSL
`accessed` metadata:

- `urlyear`
- `urlmonth`
- `urlday`

Whole scalar access-date fields keep precedence through the existing
`urldate`/`accessed`/`accessdate` path, and the scalar legacy aliases
`lastchecked`, `lastaccessed`, and `visited` are accepted by the same bounded
date path.

## Validation

Focused validation passed:

```bash
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Result: `1 test files, 631 assertions, 0 failures`.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, office suite,
or external validator was invoked.
