# Pandoc CSL BibLaTeX Series Creator Ingress

Bead: `plib-v3ksd`

Scope:
- Import bounded BibLaTeX `seriescreator` and `series-creator` name fields into the existing CSL `series-creator` variable.
- Keep name annotation provenance aligned by classifying both spellings as name-bearing fields.
- Reuse the existing CSL normalization and rendering path that already handles direct CSL JSON `series-creator`.

Out of scope:
- New CSL variables beyond `series-creator`.
- External CSL validation, Pandoc execution, office tooling, browser engines, or live provider checks.

Verification:
- Focused parser/render regression in `lanes/pandoc/tests/CitationCslProcessorTest.php`.
- Full `lanes/pandoc/tests` lane before completion.
