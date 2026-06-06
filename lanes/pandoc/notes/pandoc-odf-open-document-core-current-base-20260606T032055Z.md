# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260606T032055Z`
Base: `3c8b9e6cdbfac97ac54f81052e1e910b2e2834ae`

## Behavior

Implemented native ODF heading auto identifiers in `OdfReader`.

Source truth was the pinned Pandoc ODT reader at `jgm/pandoc` commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`: `ContentReader.hs`
`getHeaderAnchor` derives a unique identifier from heading children with
`uniqueIdent`, and `read_header` applies that anchor before constructing the
Header node. This slice maps that bounded contract in PHP for:

- `text:h` headings.
- Paragraph styles resolved to headings through `style:default-outline-level`.
- Duplicate heading text with `-1`, `-2`, ... suffixes.
- Empty/punctuation-only headings with the `section` fallback.
- Markdown `{#id}` heading attributes and WordPress `<hN id="...">` output.

## Evidence

Baseline:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1084 assertions, 0 failures`.

Red-first:

- Added the focused heading-anchor case before changing `OdfReader`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1087 assertions, 1 failures`.
- Failure: first ODT heading id was `NULL` instead of `odt-source-packet`.

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1105 assertions, 0 failures`.

Status movement:

- `lane-status.json` `phpPass`: `1174 -> 1175`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1624 -> 1625`.
- ODF core mapping: `10 -> 11` cases, `217 -> 238` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader`
inline-text/style parsing plus existing Markdown and WordPress writers.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, or live provider test was run.

## Non-Overlap

This does not repeat accepted ODF text-tab normalization, ODF blockquote style
mapping, table captions, soft page break, sequence field, bibliography mark,
TOC/generated index, tracked-change, MathML object, chart/OLE object, URI
encoded media reference, or frame image dimension slices.

Follow-up remains separate for explicit source heading ids/bookmarks, broader
Unicode punctuation parity in auto identifiers, outline-number labels,
generated TOC refresh semantics, and full upstream ODT runner parity.
