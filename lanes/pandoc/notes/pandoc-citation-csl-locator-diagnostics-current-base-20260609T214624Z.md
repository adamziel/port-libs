# Pandoc Citation/CSL Locator Diagnostics WordPress Block Assertion Slice

## Scope

Current main already includes focused native PHP coverage for citation AST
nodes that provide `locatorValue` without an explicit `locatorLabel`. The
normalization path defaults those values to CSL `page` and exposes the
`citation-locator-explicit-value-defaulted-page` review diagnostic.

This rebased slice keeps the accepted diagnostic coverage and adds the missing
WordPress block regression check for the same direct-AST defaulted page
handoff. It does not add another PHP PASS case or repeat the accepted locator
diagnostics for unlabeled Markdown locator text, unsupported explicit locator
labels, or label-only locator diagnostics.

## Files

- `lanes/pandoc/tests/CitationCslProcessorTest.php`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/pandoc-citation-csl-locator-diagnostics-current-base-20260609T214624Z.md`

## Verification

Focused gate:

```bash
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
jq empty lanes/pandoc/lane-status.json
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
php lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php --self-test
php tools/run-tests.php lanes/pandoc/tests
```

Result: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
passed `1` file, `4172` assertions, `0` failures, and the locator diagnostics
handoff self-test passed. The full post-rebase Pandoc PHP suite passed `42`
files, `58422` assertions, `0` failures.

Status delta after rebase: +0 focused PHP PASS cases and +2 focused/full-suite
assertions. `phpPass` remains `2907`, `suiteProgress` remains `810`, and
`phpFail` remains `0`.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
TeX/PDF engine, browser renderer, unzip/zip, external validator, online
service, live provider test, or live-service provider test is required.

## Follow-up

A useful non-overlapping follow-up would be richer direct-AST locator source
metadata for intentionally page-labeled values versus values imported from
format-specific package metadata, while keeping rendered CSL behavior unchanged.
