# Pandoc CSL Empty Text Literal Slice

Slice: `pandoc-csl-empty-text-literal`
Date: 2026-06-15

## Scope

Bounded native PHP CSL parser/rendering hardening for explicit empty `<text value="">` literals.

## Change

- `CslStyle` now records an explicitly present empty `value` attribute as a text literal instead of falling through to an empty macro reference.
- `CitationCslProcessorTest` covers style-summary preservation, no blank macro fallback, citation rendering, bibliography rendering, and WordPress bibliography handoff.

## External Tool Boundary

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test is required for this slice.
