# Pandoc BibTeX/CSL Legacy Title Family Slice

Slice: `pandoc-bibtex-csl-legacy-title-family-20260615T1045Z`
Base: `dd53ddb025`

## Behavior

`BibtexCslProcessor` now preserves bounded BibLaTeX title-family aliases in
legacy CSL handoff items:

- `maintitle`, `main-title`, `maintitletext`, and `main-title-text` compose
  with `mainsubtitle` / `main-subtitle` into `main-title`.
- `maintitleaddon` / `main-title-addon` map to `main-title-addon`.
- `volumetitle`, `volume-title`, `volumetitletext`, and `volume-title-text`
  compose with `volumesubtitle` / `volume-subtitle` into `volume-title`.
- `shortvolumetitle`, `short-volume-title`, `volumetitleshort`, and
  `volume-title-short` map to `volume-title-short`.
- `parttitle`, `part-title`, `parttitletext`, and `part-title-text` compose
  with `partsubtitle` / `part-subtitle` into `part-title`.
- `issuetitle`, `issue-title`, `issuetitletext`, and `issue-title-text`
  compose with `issuesubtitle` / `issue-subtitle` into `issue-title`.
- `issuetitleaddon`, `issue-title-addon`, and `issuetitle-addon` map to
  `issue-title-addon`.

The default legacy bibliography text exposes these fields as reviewer-visible
title-family summaries, and the focused test verifies downstream
`CitationCslProcessor` style rendering plus WordPress bibliography handoff.

## Evidence

Red probe before implementation:

```sh
php -r 'require "tools/bootstrap.php"; $items=(new PortLibs\Pandoc\BibtexCslProcessor())->cslItems("@book{source,title={Source Chapter},maintitle={Main Corpus},volumetitle={Volume Review},parttitle={Part Ledger},year={2026}}"); var_export([$items["source"]["main-title"] ?? null, $items["source"]["volume-title"] ?? null, $items["source"]["part-title"] ?? null]); echo PHP_EOL;'
```

Result: all three values were `NULL`.

Final focused verification:

```sh
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Result after rebase onto current `origin/main`: focused
`BibtexCslProcessorTest.php` passed with `1 test files, 284 assertions, 0
failures`; full `lanes/pandoc/tests` passed with `46 test files, 88173
assertions, 0 failures`.

## Accounting

- `phpPass`: `3718 -> 3719`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped counters: `3738 -> 3739` and
  `3248 -> 3249`
- `mappedBibtexCslProcessorCases`: `7 -> 8`
- `mappedBibtexCslProcessorTitleFamilyCases`: `1`
- `bibtexCslProcessorTitleFamilyAssertions`: `26`

## Non-Overlap

This does not repeat the stricter `BibtexCslParser` /
`CitationCslProcessor` title-family slices for main, volume, part, issue,
title-text, subtitle, direct CSL JSON, or registry aliases. It only fills the
legacy `BibtexCslProcessor` BibLaTeX handoff gap and keeps the lane native PHP
only, without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers,
browser renderers, external validators, online services, live provider tests,
or live-service provider tests.
