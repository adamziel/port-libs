# Pandoc BibTeX Reader Review Provenance - 2026-07-01

## Scope

- Added metadata-only `bibtexReview` provenance for direct `bibtex` and
  `biblatex` bibliography reader inputs.
- The document carries `bibtexReview`, `bibtexItemReviews`, and the same review
  under `bibliography.bibtexReview`.
- The review summarizes item ids, BibTeX entry types, CSL item types, source
  field names/counts, title/link-bearing records, CSL name/date variables,
  identifier fields, source-file candidate diagnostics, relation references,
  and BibLaTeX custom field/list/name/annotation coverage.
- Source values for titles, URLs, DOIs, attachment paths, keywords, custom
  fields, annotations, inherited xdata values, and related entry values remain
  omitted from the review payload.

## Direct-Format Parity

This keeps direct reader parity accounting active for native Pandoc
citation/bibliography imports: CSL JSON and RIS already expose bounded
reader-review metadata, and BibTeX/BibLaTeX now expose the same class of
non-payload provenance without shelling out to Pandoc, Citeproc, BibTeX, Biber,
bibliography managers, office suites, TeX/PDF engines, browser renderers,
external validators, online services, live providers, `unzip`, or Node.
