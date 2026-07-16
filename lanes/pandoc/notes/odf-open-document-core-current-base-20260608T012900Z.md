# ODF OpenDocument List Indentation Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T012900Z`
Base: `7ed7b0181dae439571f64983f19fbb9b6bfce3fe`

## Behavior

Native ODT import now applies quote-width paragraph style modifiers only outside `text:list` context. A top-level paragraph with an inherited `fo:margin-left` quote-width style still imports as a blockquote, while a paragraph with the same style inside an ordered list remains a normal list item paragraph. This matches the bounded Pandoc ContentReader list-level guard for paragraph modifiers without executing Pandoc or Haskell test runners.

## Files

- `lanes/pandoc/src/OdfReader.php`
- `lanes/pandoc/tests/OdfReaderTest.php`
- `lanes/pandoc/examples/wordpress-odf-list-indentation-handoff.php`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/odf-open-document-core-current-base-20260608T012900Z.md`

## Evidence

Red-first focused test:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result before implementation: `1 test files, 1690 assertions, 1 failures`; the new list-indentation case failed because the indented list paragraph imported as `blockquote` instead of `paragraph`.

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 1699 assertions, 0 failures`.

WordPress example smoke:

`php lanes/pandoc/examples/wordpress-odf-list-indentation-handoff.php --self-test`

Result: `odf list indentation handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `OdfReader`, `MarkdownWriter`, `WordPressBlockWriter`, and `ZipPackage` support. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, and live-service provider tests remain out of scope.

## Non-Overlap

No `port-pandoc-*.needs-lane-rework.md` notes existed before this patch. This ODF handoff is separate from the already mapped text:tab normalization, top-level paragraph blockquote mapping, heading anchors/source ids, conditional/hidden fields, chart metadata, and object/media handoff slices.

## Next

Choose a non-overlapping ODF/OpenDocument gap such as form controls, field metadata, table/object metadata, or style inheritance not already mapped.
