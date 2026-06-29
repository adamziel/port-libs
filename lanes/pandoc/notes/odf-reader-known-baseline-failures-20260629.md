# ODF Reader Known Baseline Failures - 2026-06-29

Hook: `plib-3s1lk`

## Context

Refinery verification for MR `plib-wisp-0oo` found that
`OdfReaderTest.php` was already red on the ODF integration target before the MR
was applied.

- Target branch: `origin/integration/pandoc-package-odf`
- Target baseline checked by refinery:
  `6c23f4a9ae0151844d7b1706a6523fc4f4daab81`
- Target result: `1 test files, 4990 assertions, 22 failures`
- Rebased MR result reported by refinery:
  `1 test files, 5003 assertions, 22 failures`
- Focused MR package gate reported by refinery:
  `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with `1 test files, 1911 assertions, 0 failures`

I reproduced the target baseline result at
`6c23f4a9ae0151844d7b1706a6523fc4f4daab81`:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 4990 assertions, 22 failures
```

## Failure Set

These are broad ODF reader/writer output-shape expectation mismatches that were
already present on the target baseline:

1. `maps ODT indented paragraph styles into Pandoc block quotes`
2. `does not turn indented ODT list paragraphs into block quotes`
3. `maps ODT preformatted paragraph styles into code blocks`
4. `maps ODT paragraph text properties into styled inline content`
5. `assigns Pandoc-style auto identifiers to ODT headings`
6. `continues ODT ordered lists from named source list ids`
7. `preserves ODT image list style metadata for WordPress review`
8. `preserves ODT list level text properties for WordPress marker review`
9. `maps ODT list headers as unnumbered review content`
10. `maps ODT footnotes endnotes and bookmark references into reviewable AST nodes`
11. `maps ODT reference marks and references into internal review links`
12. `maps ODT bibliography marks into citation handoff nodes`
13. `attaches following ODT table caption paragraphs to table nodes like upstream post process`
14. `maps ODT embedded MathML objects into display math handoff nodes`
15. `normalizes ODT URI encoded package part references for media and objects`
16. `maps block-level ODT frame text-box image captions into figure handoff`
17. `maps ODT draw frame captions into figure caption metadata`
18. `preserves ODT frame image dimensions for Markdown and WordPress handoff`
19. `preserves ODT frame image xlink metadata for review handoff`
20. `preserves ODT image frame anchor metadata for review handoff`
21. `maps ODT drawing layers into frame review metadata`
22. `renders ODT handoff nodes through Markdown and WordPress writers`

## Triage

The failures cluster around legacy ODF rendering expectations rather than the
package byte-policy and sidecar provenance path touched by `plib-wisp-0oo`:

- blockquote and code-fence markup shape;
- inline style nesting and heading auto identifiers;
- ordered/list metadata attributes and list-header review containers;
- empty bookmark/reference anchors now carrying `data-pandoc-anchor`;
- bibliography handoff through WordPress citation spans;
- caption, MathML, image dimension, xlink, frame, drawing-layer, and table
  writer output shape.

This baseline should be treated as a separate ODF reader/writer expectation
backlog. Package-ingestion MRs that only change ODF package provenance should
continue to rely on focused `OpenDocumentPackageTest.php` gates plus selected
ODF package-provenance cases, and should not be rejected solely because the
full `OdfReaderTest.php` file retains this existing 22-failure baseline.
