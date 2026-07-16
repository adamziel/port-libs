# Pandoc BibTeX/CSL Authority Identifier Slice

Date: 2026-06-10 UTC
Base: 823a2ea5909edee69a914e8ab03779dc343ae2b7
Micro-slice: pandoc-bibtex-csl-authority-identifiers-current-base-20260610T120347Z

## Scope

This slice maps one bounded BibTeX/BibLaTeX and direct-CSL authority
identifier metadata cluster without invoking Pandoc, citeproc, BibTeX, Biber,
Cabal/Haskell runners, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

The implementation reuses the native PHP BibTeX parser, CSL item normalizer,
CSL text-variable renderer, Markdown citation parser, and WordPress bibliography
handoff:

- BibTeX/BibLaTeX fields now preserve ORCID, ISNI, VIAF, ROR, and Wikidata
  identifiers on CSL items.
- Direct CSL item input accepts common cased and hyphenated aliases for the same
  identifiers.
- Default bibliography output emits labeled authority identifiers for reviewer
  audit packets.
- CSL styles can render individual variables and combined
  `authority-identifiers` or `authority-identifier-summary` values.
- WordPress bibliography review blocks keep the same identifiers visible after
  Markdown citation expansion.

## Evidence

Syntax and whitespace checks:

```text
git diff --check
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
```

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4262 assertions, 0 failures
```

Full Pandoc PHP lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 59813 assertions, 0 failures
```

Bookkeeping delta:

- `phpPass`: `2955 -> 2956`
- `phpFail`: `0`
- Focused mapped handoff checks: `860 -> 861`

## Non-Overlap

This does not repeat the accepted registry identifier, media identifier, PubMed,
eprint archive, source attachment, original publication, date, or creator role
CSL slices. It is limited to authority/source identifiers used by reviewer and
bibliography audit workflows.

## Dependency Closure

No new support component is needed. The slice remains native PHP and
external-tool free. Follow-up CSL work should target a separate behavior such as
remaining BibLaTeX name-list edge cases, additional style conditionals, or
broader citeproc parity.
