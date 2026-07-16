# ODF OpenDocument Paragraph Blockquotes

Slice: `pandoc-odf-open-document-core-current-base-20260605T171602Z`

Base accepted HEAD: `0ecec447889840d3232c52ba84143aeb59b90343`

## Source Truth

- Pinned upstream: `jgm/pandoc` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `src/Text/Pandoc/Readers/ODT/ContentReader.hs` routes `text:p` through `constructPara`, reads the paragraph style chain, and applies `getParaModifier`.
- `getParaModifier` maps paragraph styles to `blockQuote` when `fo:text-indent`, `fo:margin-left`, or their matching-unit sum exceeds 5mm or 5%.
- `src/Text/Pandoc/Readers/ODT/StyleReader.hs` parses `style:paragraph-properties` `fo:text-indent` and `fo:margin-left` into paragraph properties.

## Implementation

- `OdfReader` now preserves ODF paragraph `fo:text-indent`, `fo:margin-left`, and derived point/percent metadata in resolved style records.
- `OdfReader` maps quote-width styled `text:p` blocks to Pandoc-like `blockquote` nodes while retaining the styled paragraph as the quote body.
- The import report now includes `content.blockquoteCount` for ODF style-derived quote handoffs.
- The WordPress ODF handoff example includes one quoted source decision and verifies it renders as a WordPress quote block.

## Verification

Red-first focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 942 assertions, 1 failures
```

Green focused check after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 963 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

## Dependency Closure

No new support component is needed. The slice reuses native PHP `OdfReader` style catalogs, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and `ZipPackage` fixtures. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online sanitizer, or online conversion service was executed.

## Non-overlap

This does not repeat accepted ODF mimetype, manifest/content/styles/meta, style inheritance, list restarts, sections, annotations, text boxes, frame images, sequence fields, bibliography marks, media metadata, table spans, encrypted manifest, embedded objects, math/chart/form controls, TOC/generated indexes, or `text:tab` normalization slices. The behavior is limited to upstream-backed paragraph-style quote modifier handling.
