# pandoc-epub3-package-core-current-base-20260607T001946Z

Accepted base: `13afa6bbcfe66cce46d4907c863b6703a36c5f2e`

## Source Truth

- W3C EPUB 3.3 core package/media model: core media type resources are distinguishable from EPUB content documents; foreign resources need fallback coverage unless exempt or handled by package bindings.
- Local static Pandoc upstream checkout was not present under `/home/claude/port-libs/.upstream-cache/pandoc`, so this slice stayed on the bounded native PHP EPUB3 package contract and did not run Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external validators, online services, live provider tests, or live-service provider tests.

## Behavior Added

- `EpubReader` now emits a `mediaTypes` OPF manifest review packet with per-item and aggregate media type state:
  - core media type kind;
  - EPUB content document classification;
  - foreign resource and exempt resource classification;
  - manifest fallback coverage;
  - OPF binding-handler fallback coverage;
  - review diagnostics for non-core, non-exempt resources that have no manifest fallback or usable XHTML binding handler.
- The media type packet is exposed on the top-level package result, `importReport`, document attributes, manifest entries, and asset reports so WordPress handoff code can surface review-required resources without rejecting the package.
- The WordPress EPUB3 package handoff smoke now asserts the new core/foreign/binding coverage and prints compact media-type review counters.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 1652 assertions, 0 failures`
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 1653 assertions, 1 failures`
  - expected failure: new `mediaTypes` report was absent before implementation.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 1693 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - `epub3 package handoff self-test ok`
- PHP lint for changed files:
  - `php -l lanes/pandoc/src/EpubReader.php`: no syntax errors detected.
  - `php -l lanes/pandoc/tests/EpubReaderTest.php`: no syntax errors detected.
  - `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`: no syntax errors detected.
- `git diff --check -- lanes/pandoc`: passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `ZipPackage`/`EpubReader` package parser, existing OPF manifest/binding parsing, and the existing WordPress EPUB3 package handoff example. Full upstream Pandoc runner parity remains blocked by the absent pinned upstream checkout and by lack of authorization for Haskell/Cabal solver/build/runner work, not by a missing EPUB package primitive.

## Non-Overlap

This does not overlap prior accepted EPUB3 package work for OCF container/rootfiles, metadata sidecars, OPF metadata/manifest/spine parsing, nav/NCX target resolution, missing non-spine assets, XHTML raw-block handoff, guide/collections/bindings extraction, remote resources, cover images, media overlays, or CFI reporting. The added behavior is specifically OPF media type/core-media/foreign-resource fallback review.

## Next Task

Keep follow-up EPUB3 package work bounded to non-overlapping XHTML-to-AST conversion, CSS/media export review metadata, active media playback handoff, EPUBCheck-style validation gaps, or reading-system layout metadata.
