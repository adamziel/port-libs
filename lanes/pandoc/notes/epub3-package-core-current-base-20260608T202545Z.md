# EPUB3 Package Current-Base XHTML Language Handoff

Slice: `pandoc-epub3-package-core-current-base-20260608T202545Z`
Base: `949603f1e0e4e058a31177669f645269029646a8`

## Behavior

Native `EpubReader` XHTML content scanning now preserves content-document language and direction provenance from `<html>` and `<body>`:

- `<html>` `xml:lang`, `lang`, and `dir`
- `<body>` presence, `id`, raw `class`, class token list, `xml:lang`, `lang`, `dir`, raw attributes, and `epub:type` tokens
- selected content `language` and `direction`, preferring body metadata over html metadata
- WordPress raw HTML handoff block attributes: `contentLanguage`, `contentDirection`, and `contentBodyEpubTypes`

This extends the existing EPUB XHTML viewport metadata path rather than adding a new parser or support component.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` files existed before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 2648 assertions, 0 failures`
- Red-first: same focused test failed after adding language/direction expectations:
  - `1 test files, 2625 assertions, 1 failures`
  - Failure was missing `htmlXmlLang` in the XHTML content metadata report.
- Final: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 2666 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - `epub3 package handoff self-test ok`

## Dependency Closure

No new support component is needed. The slice reuses lane-local `EpubReader`, `XmlHtmlDom`, `ZipPackage`, `AstNode`, existing EPUB fixtures, and the WordPress EPUB handoff example. No Pandoc, Word, LibreOffice, zip/unzip, browser renderer, Cabal/Haskell runner, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB container, OPF metadata, spine rendition, nav/NCX target, XHTML viewport, switch, trigger, semantic, remote-resource, OCF sidecar, or media-overlay slices. The new behavior is the content-document language/direction/body provenance handoff.

## Follow-Up

Next EPUB3 work should choose a different native package gap such as XHTML manifest/script/resource policy, nav/NCX target provenance, OPF metadata refinement coverage, or media-overlay handoff not already mapped.
