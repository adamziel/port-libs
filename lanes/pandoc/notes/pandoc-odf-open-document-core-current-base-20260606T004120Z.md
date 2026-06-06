# ODF OpenDocument Frame Text-Box Caption Slice

Session: `port-dev-pandoc-odf-package-20260606T004120Z`
Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T004120Z`
Accepted base: `6ff9ce922d7da926750e516e5b60aad181e2afa4`

## Source Truth

Pinned upstream Pandoc commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`

Targeted upstream source read:

```sh
curl -fsSL https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs | rg -n -C 5 "read_frame_text_box|read_frame ::|text-box|read_frame_child"
```

Relevant behavior: ODT `read_frame` dispatches `draw:text-box` children through `read_frame_text_box`, and `read_img_with_caption` treats an image followed by paragraph text as an image with a `fig:` title caption handoff. The local upstream cache did not contain a hydrated Pandoc checkout, so the pinned official GitHub raw source was used for this source-truth read only.

## Implementation

`OdfReader` now recognizes inline `draw:frame` elements whose child `draw:text-box` contains a paragraph with a nested `draw:image` and trailing caption text. The reader returns one image AST node with:

- caption text as `alt` and image label text;
- `fig:`-prefixed title metadata when the source image has a title;
- source part/package byte provenance from the existing image path;
- `odf-text-box-image-caption` plus `data-odf-text-box-caption` and `data-odf-text-box-frame-name` for Markdown and WordPress handoff.

The WordPress ODT handoff example now includes a captioned text-box image and checks the rendered `<img>` metadata.

## Verification

```sh
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
```

Result: `1 test files, 1039 assertions, 0 failures`.

```sh
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
```

Result: `odf open document handoff self-test ok`.

Additional final verification for PHP syntax and diff whitespace is recorded in the worker final response.

## Status Delta

- `lane-status.json` `phpPass`: `1122 -> 1123`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1574 -> 1575`.
- ODF/OpenDocument mapped core cases: `10 -> 11`.
- ODF/OpenDocument mapped core assertions: `217 -> 231`.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage`, `OdfReader`, `MarkdownWriter`, and `WordPressBlockWriter` behavior only. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was executed.

## Non-Overlap

This slice avoids the accepted ODT text:tab normalization, blockquote paragraph-style mapping, top-level block text-box preservation, direct frame-image handoff, form controls, tracked changes, MathML/OLE/chart object placeholders, manifest encryption, and table/index/field support. Remaining ODF follow-up stays bounded to richer placeholder fields, tab-stop style position metadata, index-entry tab-stop layout application, export-side ODT writing, and full upstream-runner parity.
