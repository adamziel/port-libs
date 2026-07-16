# Pandoc BibTeX CSL Core Current Base

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T082753Z`
Base: `75860db0d8f82bab3cef4a380ecf196e649730a6`

## Behavior

- Added bounded BibLaTeX event-organizer handoff support.
- `BibtexCslParser` now maps explicit `eventorganizer` / `organizer` name
  lists, and proceedings-style `organization` names for `@proceedings`,
  `@conference`, and `@inproceedings`, into CSL item `event-organizer`
  metadata.
- Existing `crossref` inheritance now carries parent proceedings organizers to
  child conference papers without adding a separate inheritance path.
- `CitationCslProcessor` normalizes `event-organizer` into `eventOrganizers`,
  renders it in fallback bibliography entries as `Event organizer: ...`, and
  exposes it to bounded CSL style rendering through
  `<names variable="event-organizer"/>` and the `organizer` alias.
- Updated the WordPress BibTeX handoff example so organizer-rich conference
  papers and webinars keep review-owner names visible in WordPress blocks.

## Source Truth

This is a bounded native PHP slice of the lane's BibLaTeX-to-CSL handoff
contract. It extends the accepted event metadata path without running Pandoc,
citeproc, BibTeX, Biber, or bibliography managers.

## Evidence

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS maps bounded biblatex event organizer metadata into csl handoff
...
1 test files, 875 assertions, 0 failures
```

The new event-organizer case adds 20 focused assertions.

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
```

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

`git diff --check -- lanes/pandoc` passed after the status/note updates.

Root harness: not run - isolated micro-slice.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped count: `1233 -> 1234`.
- `mappedBibtexCslCoreCases`: `2 -> 3`.
- `bibtexCslCoreAssertions`: `38 -> 58`.
- `lane-status.json` keeps `phpPass` at `774` because this adds assertions
  inside an existing passing test file rather than adding a new passing test
  file.

## Non-overlap

This does not repeat accepted BibTeX/CSL crossref basics, xdata, entry sets,
related entries, source-file attachment policy, title/subtitle/addon fields,
publication identifiers, main-title and volume metadata, legal/patent fields,
date ranges, editorial roles, name annotations, software/dataset release state,
or event title/type/place/date metadata.

Remaining bounded BibTeX/CSL follow-ups include localized event labels,
proceedings-specific style disambiguation, note-style citation output, broader
CSL style catalogs, and full citeproc parity.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
BibTeX parser, CSL item normalizer, CSL style renderer, Markdown reader/writer,
and WordPress block writer. Full upstream Pandoc/citeproc runner parity remains
gated on hydrating a Pandoc checkout with `cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal`; this slice did not run external
converters or Haskell tooling.
