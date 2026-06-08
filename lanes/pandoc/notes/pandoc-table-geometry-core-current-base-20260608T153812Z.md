# Pandoc Table Geometry Core Current Base - HTML Rowspan Zero Provenance

Date: 2026-06-08 UTC

Micro-slice: `pandoc-table-geometry-core-current-base-20260608T153812Z`

Base accepted HEAD: `f922b306fce650315c26b7148db82f2371b8d024`

## Behavior

Mapped one additional native table geometry handoff case for HTML `rowspan="0"` source semantics. The existing HTML reader path already normalizes the cell to a finite tbody-local row span for WordPress output; this slice preserves the original zero-rowspan source form as review metadata so downstream handoffs can distinguish explicit source `rowspan=0` from an ordinary finite rowspan.

`TableGeometry` now carries `sourceRowspanAttribute=0` and `sourceRowspanMode=to-section-end` through:

- cell coverage records;
- section grid slots, including covered slots;
- row matrix records;
- flat grid records and flat-grid fallback diagnostics;
- review-packet summaries via `rowspanToEndCellCount`, `hasRowspanToEndCells`, and `rowspanToEndSections`;
- Markdown/RST/LaTeX writer downgrade and requirement diagnostics.

WordPress table output still renders finite tbody-local `rowspan="3"` for the fixture, preserving accepted visible output while keeping provenance in the review packet.

## Source Truth

This follows Pandoc-style HTML reader table geometry semantics already represented in this lane: HTML `rowspan="0"` expands to the current table body group, does not cross into a later `tbody`, and must be serialized for review before lossy writers flatten spans. No external Pandoc, Haskell, Cabal, writer, browser, or online runner was executed.

## Evidence

Red-first focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
```

Result before implementation: `1 test files, 511 assertions, 1 failures`; the new assertion failed because `sourceRowspanAttribute` was absent.

Final focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
```

Result after implementation: `1 test files, 554 assertions, 0 failures`.

Added focused assertions: `23` over the current accepted baseline (`531 -> 554`).

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
```

Result: `table geometry handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This reuses `MarkdownReader` HTML table parsing, `TableGeometry` span normalization and review packets, `WordPressBlockWriter` finite-rowspan output, and existing writer-downgrade diagnostics.

## Non-Overlap

This does not overlap recent accepted table geometry slices for flat-grid fallback diagnostics, cell/column decimal alignment provenance, source colgroup scope, header axis metadata, row matrix handoff, source summary metadata, global row coordinates, or footer-section writer diagnostics.

## Next

Choose another non-overlapping table geometry handoff gap, such as additional source table attribute provenance, writer downgrade metadata, or AST/WordPress table review behavior. Keep external Pandoc/Haskell runners, external writers, browser renderers, online services, live provider tests, and live-service provider tests out of scope.
