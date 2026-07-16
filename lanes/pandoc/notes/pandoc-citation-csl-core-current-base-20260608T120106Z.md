# Pandoc Citation/CSL Core Current-Base Handoff

Slice: `pandoc-citation-csl-core-current-base-20260608T120106Z`
Base accepted HEAD: `4ddf14ad85da5fb33d38631852b70aaae3e4a2e4`

## Summary

Added bounded native CSL locator vocabulary support for uncommon but CSL-recognized locator labels:
`article-locator`, `book`, `canon`, `elocation`, `folio`, `opus`, `part`, `rule`, `sub-verbo`, `supplement`, `timestamp`, and `title`.

Markdown bracketed citation tails such as `bk. 2-3`, `canon 4`, `s.v. migration`, `timestamp 01:02:03`, `art. 3-4`, and `eloc 55` now infer the correct CSL locator labels instead of falling back to page locators. Direct citation nodes also normalize aliases such as `s. v.` and `article` before CSL conditional routing and term rendering.

## Source Truth

This is bounded support-library work under `lanes/pandoc/**`. It follows the existing CSL locator condition support already present in `CslStyle::supportedLocatorConditions()` and extends native Markdown parsing/default term handling to the same locator vocabulary. No external citation processor or Pandoc runner was used.

## Files

- `lanes/pandoc/src/MarkdownReader.php`
- `lanes/pandoc/src/CitationCslProcessor.php`
- `lanes/pandoc/src/CslStyle.php`
- `lanes/pandoc/tests/CitationCslProcessorTest.php`
- `lanes/pandoc/examples/wordpress-citation-csl-uncommon-locator-handoff.php`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

## Verification

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2440 assertions, 1 failures
```

Failure: the new uncommon-locator citation case parsed each uncommon tail as a page locator before implementation.

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2444 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 3740 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-uncommon-locator-handoff.php --self-test
wordpress-citation-csl-uncommon-locator-handoff self-test passed
```

PHP lint passed for:

```text
lanes/pandoc/src/CitationCslProcessor.php
lanes/pandoc/src/CslStyle.php
lanes/pandoc/src/MarkdownReader.php
lanes/pandoc/tests/CitationCslProcessorTest.php
lanes/pandoc/examples/wordpress-citation-csl-uncommon-locator-handoff.php
```

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native Markdown reader, `CitationCslProcessor`, `CslStyle` term handling, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids the recent table-geometry, ODF, XML/HTML5 DOM, math, DOCX, ZIP, charset, and upstream-runner audit slices. It is limited to Citation/CSL locator vocabulary inference/rendering and one WordPress handoff smoke.

## Next

If a future upstream citation fixture requires it, extend the same bounded locator path into a larger CSL style fixture. Keep it native-PHP and avoid external citeproc/Pandoc execution.
