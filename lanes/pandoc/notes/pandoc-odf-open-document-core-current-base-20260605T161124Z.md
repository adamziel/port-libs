# Pandoc ODF OpenDocument Core Slice

Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T161124Z`

Base accepted HEAD: `d8c5378cfd71f3d3e903f3a54d8aa0ca34a9c783`

## Behavior

Implemented bounded OpenDocument Text `text:tab` normalization in the native
`OdfReader`.

- Maps `text:tab` to a normal Pandoc space in inline content.
- Covers paragraph, span, and heading inline paths.
- Prevents literal tab characters from leaking into Markdown and WordPress
  review output.
- Updates the WordPress ODF handoff smoke to prove visible block output.

The source-truth behavior is the pinned Pandoc
`Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, where `read_tab` matches
`text:tab` and returns `space`. The local upstream cache did not contain a
hydrated Pandoc checkout, so the source was read directly from the pinned
official GitHub file without running Pandoc, Cabal, or Haskell tests.

## Red-First Evidence

Baseline before the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 931 assertions, 0 failures
```

After adding the focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
FAIL maps ODT tab stops to Pandoc spaces in inline content
Expected: 'Before after and inner tab.'
Actual: 'Before	after and inner	tab.'
1 test files, 932 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 940 assertions, 0 failures
```

Delta: +1 focused PASS case and +9 focused assertions.

## Verification

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 940 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Manifest / Status Delta

- `phpPass`: 991 -> 992.
- `benchmarkDenominator.mapped`: 1446 -> 1447.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 226.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ODF package reader plus shared `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` output paths.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external converter, external office tool, online
validator, online sanitizer, or online service was executed.

## Non-Overlap

This avoids the accepted ODT mimetype/content/manifest/media/table/list base
cluster and the later bookmark/reference, sequence, field, ruby,
soft-page-break, form-control, table-of-contents, generated-index,
linked/protected-section, tracked-change, encrypted-manifest, MathML object,
chart object, OLE object, URI normalization, image-dimension, table-template,
and table cell formula/value clusters. It owns only bounded `text:tab` inline
normalization.

Remaining ODT follow-up stays separate: richer placeholder fields, tab-stop
style position metadata, index-entry tab-stop layout application, export-side
ODT writing, and full Pandoc ODT reader parity.
