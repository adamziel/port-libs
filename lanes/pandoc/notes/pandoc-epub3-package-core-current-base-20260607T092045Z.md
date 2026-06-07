# pandoc-epub3-package-core-current-base-20260607T092045Z

Slice: EPUB3 OPF identifier/date metadata handoff.

Base accepted HEAD: `606c3128dac72f6c41bc0ebf57b4a59313d5faf9`

## Source Truth

- Pinned Pandoc EPUB package metadata support carries OPF metadata maps into document metadata before EPUB writer/reader handoff.
- EPUB OPF metadata keeps publication identifiers in `dc:identifier` entries, allows identifier scheme/type metadata through attributes/refinements, and carries publication/review dates through `dc:date` plus event metadata.

## Behavior

- `EpubReader` now exposes normalized `metadata.identifierDetails` with identifier scheme, identifier-type refinements, selected unique-identifier binding, duplicate-value flags, duplicate ids/indexes, linked resources, and raw refinements.
- `metadata.identifierSummary`, `identifiersByType`, and `identifiersByScheme` give WordPress import review code stable grouping without scraping raw OPF refinement arrays.
- `metadata.dateDetails`, `datesByEvent`, and `dateSummary` preserve `dc:date` event attributes and event refinements while keeping raw `dc` metadata intact.
- Duplicate identifier values become explicit review diagnostics instead of being hidden behind the first unique-identifier value.
- The WordPress EPUB3 package handoff example now exercises UUID/ISBN identifiers, duplicate UUID review, publication/review date events, and document metadata propagation.

## Evidence

Baseline focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1767 assertions, 0 failures
```

Red-first focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL summarizes OPF identifier schemes and date events for review handoff
identifierDetails/dateDetails were absent
1 test files, 1767 assertions, 1 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1816 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+49` focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage` fixtures, DOM/XML parsing, existing `EpubReader` OPF metadata/refinement parsing, document metadata handoff, and the WordPress EPUB3 package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, browser renderer, JavaScript/media execution, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat EPUB OCF container/rootfile handling, OPF vendor metadata, title/contributor summaries, nav/NCX/page-list handling, guide/collection links, manifest/spine parsing, fallback chains, media overlays, CFI fragments, OCF sidecars, XHTML resource scans, trigger handling, cover/asset reports, or raw metadata-refinement grouping. It owns only normalized identifier scheme/type and date-event metadata handoff.

## Follow-Up

Useful next EPUB work should target parser-level XHTML body conversion, nav accessibility metadata, or a manifest media policy handoff with focused tests. Keep external EPUB rendering, media playback, browser layout, Pandoc/Haskell runners, Word/LibreOffice, and zip/unzip execution out of this lane unless explicitly authorized.
