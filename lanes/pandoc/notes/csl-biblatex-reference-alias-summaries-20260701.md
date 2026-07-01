# CSL BibLaTeX Reference Alias Summaries

This slice closes a bounded CSL handoff gap for BibLaTeX relation fields that arrive
through alias spellings rather than the canonical `related` field.

- `BibtexCslProcessor` now recognizes `related`, `related-keys`, and `relatedkeys`
  when building relation provenance.
- Direct CSL items expose canonical camelCase relation keys alongside the existing
  kebab-case handoff keys for related and xref references.
- Resolved relation summaries carry missing-key lists, related options, related
  type/string metadata, xref summaries, and data-only reference markers without
  shelling out to external bibliography tools.

Focused coverage lives in `BibtexCslProcessorTest` under the alias relation
summary regression.
