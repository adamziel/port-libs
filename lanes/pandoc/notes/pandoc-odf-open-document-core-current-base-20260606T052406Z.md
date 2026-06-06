# Pandoc ODF OpenDocument Core 2026-06-06

## Scope

Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T052406Z`.

Accepted base: `acf12984b3f1531972a266d07322821b4a812a25`.

This is a bounded native PHP ODF/OpenDocument support-library slice. No Pandoc
binary, Cabal solver/build/test command, Haskell test binary, Word,
LibreOffice, `zip`, `unzip`, external office converter, online conversion
service, online sanitizer, live provider test, or other external converter was
executed as progress.

## Source Truth

The pinned upstream Pandoc commit remains
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The relevant ODF source-truth
shape is `ContentReader.read_frame_img`, which applies `fixRelativeLink` to the
image `xlink:href` before constructing the Pandoc image target.

The local worktree and upstream cache do not contain a hydrated Pandoc checkout
for Haskell runner comparison, so this slice ports the bounded contract into
the native PHP reader and focused tests.

## Implemented Behavior

`OdfReader::frameImageNode()` now normalizes draw-frame image links with the
same `fixRelativeLink()` helper already used for `text:a` links. A frame image
such as `../Pictures/hero.png?download=1#hero` is exposed as:

- rendered URL: `Pictures/hero.png?download=1#hero`
- source package part: `Pictures/hero.png`
- package media metadata and byte counts from the manifest entry

This keeps parent-relative ODT package paths out of Markdown and WordPress
handoff output while preserving query and fragment text in the rendered image
target.

## Focused Evidence

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1169 assertions, 0 failures`
- PHP lint:
  `php -l lanes/pandoc/src/OdfReader.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - `No syntax errors detected`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1179 assertions, 0 failures`
  - Focused delta from this current-base ODF reader baseline:
    `+1` PASS case / `+10` assertions
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1208 -> 1209`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1654 -> 1655`
- `odfOpenDocumentCoreCases`: `10 -> 11`
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`
- `odfOpenDocumentCoreAssertions`: `217 -> 227`

## Dependency Closure

No new native PHP support component is needed. This slice reuses `OdfReader`,
`ZipPackage`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream runner parity remains blocked by the missing hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal
project/package files and Haskell Tasty executable builds.

## Non-Overlap

This patch only covers ODF frame image parent-relative href normalization. It
deliberately avoids the already accepted ODF text-link relative normalization,
frame image dimensions, text-box caption image handoff, manifest/media
metadata, sections, fields, annotations, tracked changes, table/list mapping,
DOCX/OpenXML, EPUB3, ZIP/OPC, XML/HTML5 DOM, CSL/BibTeX, YAML, table geometry,
math/TeX, PDF engine, charset/Unicode, and upstream-runner audit surfaces.

## Next Activation Gate

Continue ODF/OpenDocument parity in separate bounded slices: richer draw object
and frame handling, advanced style/layout mapping, embedded package exposure
policy, or hydrated upstream Haskell runner comparison once the pinned checkout
and Cabal test executables are available.
