# Direct CSL entry subtype genre aliases

Slice `plib-bugsq` keeps the CSL citation/bibliography handoff metadata-only while closing a direct CSL JSON alias parity gap for entry subtype fields.

- Direct CSL JSON now normalizes `entry-subtype`, `entrySubtype`, and `entrysubtype` once during item ingestion.
- When `genre` is absent, the normalized entry subtype becomes the genre fallback, matching the existing BibLaTeX subtype behavior and making styles that render `genre` see subtype-only direct records.
- Explicit `genre` still wins over `entrysubtype`, while `entrySubtype` remains separately available for `entry-subtype` rendering and raw alias provenance.

Validation for this slice used focused PHP syntax checks plus CitationCslProcessor, BibtexCslProcessor, and BibliographyReader tests. No external Pandoc, citeproc, BibTeX, Biber, office, TeX/browser, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
