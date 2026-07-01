# Pandoc BibLaTeX Original Location Alias Handoff

## Scope

- Added original publication location aliases for BibLaTeX/CSL handoff:
  `original-location`, `orig-location`, `origplace`, `orig-place`,
  `original-place`, `orig-address`, and original publisher location spellings.
- Kept the canonical CSL handoff field as `original-publisher-place` and
  `original-publisher-place-list`, with style aliases for `original-location`
  and `original-location-list`.
- Covered parity across `BibtexCslProcessor`, `BibtexCslParser`,
  `CitationCslProcessor::fromBibtex()`, direct CSL item input, style rendering,
  and WordPress bibliography handoff.

## Direct-format parity accounting

This slice does not repeat prior original publisher, original language,
literal-list, original edition, original identifier, or jurisdiction work. It is
limited to original publication location spelling aliases that should normalize
to the existing original publisher place CSL metadata.
