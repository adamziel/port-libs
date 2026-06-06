# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260606T035013Z`
Base: `e6e270a95e14f4f7d39cb5ce4b34b7a26d8a52c6`

## Behavior

Implemented native ODF heading bookmark anchors in `OdfReader`.

Source truth is the pinned Pandoc ODT reader at `jgm/pandoc`
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, as already captured in the
lane static inventory and previous heading-id slice: `ContentReader.hs`
`getHeaderAnchor`/`read_header` applies heading anchors before constructing
Pandoc Header nodes.

This slice maps the bounded PHP contract for:

- `text:h` headings containing `text:bookmark-start` or `text:bookmark`.
- Paragraph styles resolved to headings through `style:default-outline-level`.
- Consuming the empty heading bookmark anchor so Markdown and WordPress do not
  emit a nested empty `<span>` inside the heading.
- Reserving explicit bookmark ids so later auto-generated headings receive a
  unique suffix.

## Evidence

Baseline:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1105 assertions, 0 failures`.

Red-first:

- Added the focused heading-bookmark test before changing `OdfReader`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1108 assertions, 1 failures`.
- Failure: first heading id was `heading-from-source-bookmark` instead of
  `source-review-anchor`.

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1132 assertions, 0 failures`.

Example smoke:

- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
- Result: `odf open document handoff self-test ok`.

Status movement:

- `lane-status.json` `phpPass`: `1181 -> 1182`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1631 -> 1632`.
- ODF core mapping in the current manifest: `10 -> 11` cases,
  `217 -> 244` focused assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader`
inline parsing, existing style-derived heading handling, the shared AST,
`MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, or live provider test was run.

## Non-Overlap

This does not repeat accepted ODF text-tab normalization, generated heading
auto identifiers, paragraph blockquote style mapping, link normalization,
bookmark-reference paragraphs, reference marks, table captions, soft page
breaks, sequence fields, bibliography marks, TOC/generated indexes, tracked
changes, MathML objects, chart/OLE objects, URI-encoded media references, or
frame image dimension slices.

Follow-up remains separate for `text:id`/`xml:id` heading attributes, broader
Unicode punctuation parity in auto identifiers, outline-number labels,
generated TOC refresh semantics, and full upstream ODT runner parity.
