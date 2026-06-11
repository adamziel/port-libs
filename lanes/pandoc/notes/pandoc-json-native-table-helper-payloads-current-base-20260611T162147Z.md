# Pandoc JSON/native table helper payloads

2026-06-11 current-base slice for `plib-t9xkl`.

Pandoc JSON/native AST ingestion now preserves raw table helper payloads for
column alignment, column width, row-head columns, row span, and column span:

- table attrs: `alignmentNatives`, `columnWidthNatives`
- body attrs: `rowHeadColumnsNative`
- cell attrs: `alignmentNative`, `rowSpanNative`, `colSpanNative`

`PandocJsonWriter` and `NativeWriter` reuse those payloads only while the
normalized AST values still match. If an editor changes alignment, width,
row-head columns, row span, or column span, the writers regenerate canonical
Pandoc helpers instead of leaking stale native provenance.

Direct-format parity remains native PHP only. Verification used focused
JSON/native AST fixtures and the full `lanes/pandoc/tests` gate, without
invoking Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, zip/unzip, Jupyter, Node, external validators,
online services, live provider tests, or live-service provider tests.
