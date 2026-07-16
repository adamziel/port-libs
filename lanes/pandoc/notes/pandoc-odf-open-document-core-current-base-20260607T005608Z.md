# Pandoc ODF OpenDocument Sparse List-Level Fallback

Slice: `pandoc-odf-open-document-core-current-base-20260607T005608Z`
Base: `6842b8783a56f1d4106f7630a35ba63a84799539`
Date: 2026-06-07 UTC

## Source Truth

Pinned upstream Pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83` resolves ODT list level styles by exact nesting level first, then by the closest lower defined level when the requested level is missing. This slice ports that bounded `StyleReader` contract into the native PHP ODF reader without running Pandoc or any external office/conversion tools.

## Implementation

- `OdfReader::listDefinition()` now keeps exact list-level definitions as the first choice.
- When an ODT list style omits the current nesting level, the reader chooses the highest defined lower level instead of falling back to the first style definition.
- If no exact or lower definition is available, the previous first-definition/bullet fallback remains unchanged.
- The WordPress ODF handoff example now includes a sparse nested checklist item that exercises the same fallback through Markdown and block output.

## Evidence

Baseline before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1309 assertions, 0 failures
```

After the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1341 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Status delta:

- `phpPass`: `1423 -> 1424`
- mapped denominator: `1838 -> 1839`
- `odfOpenDocumentCoreCases`: `11 -> 12`
- `mappedOdfOpenDocumentCoreCases`: `11 -> 12`
- `odfOpenDocumentCoreAssertions`: `251 -> 283`
- Focused assertion delta: `+32`

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader` list-style parsing, `ZipPackage` ODT fixture construction, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream Pandoc runner parity remains outside this isolated slice: no Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF text:tab normalization, heading auto/source ids, conditional/hidden fields, table captions, table metadata, annotations, tracked changes, MathML/object placeholders, frame image dimensions, list continuation, inherited style-name propagation, prefix/suffix delimiter mapping, or list-header handling. The new behavior is specifically sparse nested list-level style fallback to the nearest lower defined level.

## Follow-Up

ODF follow-up should stay bounded to non-overlapping ODT content/styles/meta/package mapping such as text style inheritance, table/list edge cases, or object metadata, and should continue to avoid external converters and full Pandoc runner work unless explicitly authorized.
