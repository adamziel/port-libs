# Direct CSL publisher/location aliases

This slice keeps direct CSL JSON publisher metadata aligned with the local
BibTeX/BibLaTeX ingestion handoff.

## Boundary

- Normalize `institution`, `organization`, and `school` as direct publisher
  aliases when `publisher` is absent.
- Normalize compact and list forms for publisher aliases:
  `publisherlist`, `institutionList`, `organizationList`, and `schoolList`.
- Normalize direct place aliases `publisherplace`, `location`, `address`, and
  compact/list location and address list forms into `publisher-place`.
- Preserve the existing local-only citation, bibliography, and WordPress block
  handoff paths without invoking external Pandoc, citeproc, BibTeX, Biber,
  bibliography managers, browser renderers, online validators, or live
  providers.

## Coverage

`CitationCslProcessorTest` now checks scalar direct aliases and list fallback
aliases through normalized item access, CSL styled citation rendering, styled
bibliography rendering, and appended WordPress bibliography output.
