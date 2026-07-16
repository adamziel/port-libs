# pandoc-epub3-language-metadata-current-base-20260610T1124Z

Slice: `pandoc-epub3-language-metadata-current-base-20260610T1124Z`

## Behavior

EPUB OPF packages can declare multiple `dc:language` values. `EpubReader`
previously kept the first scalar language but did not expose declaration-level
language review metadata for package ingestion.

This slice adds native PHP OPF language declaration handoff:

- `metadata.languages` preserves every `dc:language` text value.
- `metadata.languageDetails` records id, scheme, inherited XML language,
  display-seq refinements, bounded language-tag components, duplicate tag
  diagnostics, and invalid tag diagnostics.
- `metadata.languagesByPrimarySubtag` groups well-formed declarations by primary
  language subtag.
- `metadata.languageSummary` rolls up counts, normalized tags, primary subtags,
  region subtags, duplicate tags, invalid tags, and diagnostics.

The same metadata flows through `importReport.metadata` and document metadata so
WordPress review queues can inspect language provenance without fetching or
validating outside the package.

## Focused Evidence

- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed because `metadata.languageDetails`, `metadata.languages`, and
  `metadata.languageSummary` were absent.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3954 assertions, 0 failures`.
- EPUB handoff smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/EpubReader.php`,
  `php -l lanes/pandoc/tests/EpubReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  passed.
- Full PHP gate:
  `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 59993 assertions, 0 failures`.

## Status Delta

- `lane-status.json` `phpPass`: `2954 -> 2955`
- `lane-status.json` `phpFail`: `0`

## Dependency Closure

No new support component is needed. The slice reuses native `EpubReader`,
existing OPF metadata/refinement parsing, `ZipPackage`, DOM/libxml XML parsing,
and the lane-local PHP test runner.

No Pandoc, Cabal/Haskell runner, office suite, TeX/browser engine, EPUBCheck,
zip/unzip, JavaScript/Node tooling, external validator, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This patch does not repeat accepted OCF container/rootfile validation, OPF
identifier/date/source/bibliographic metadata summaries, title/agent
refinements, accessibility metadata, vendor metadata, nav/NCX parsing, guide and
collection links, alternate renditions, spine/media-overlay behavior, fallback
chains, encryption, OCF sidecars, XHTML resource scans, stylesheet scans, EPUB
CFI/media fragments, or ZIP package integrity work.

The new surface is only bounded OPF `dc:language` declaration review metadata
and diagnostics for EPUB3 package ingestion.
