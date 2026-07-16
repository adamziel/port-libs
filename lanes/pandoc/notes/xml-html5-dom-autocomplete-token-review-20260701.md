# XML/HTML5 DOM autocomplete token review

This slice extends the native PHP `XmlHtmlDom` form-control constraint summary
for HTML `autocomplete` attributes. Control summaries now preserve the existing
raw token list, normalized token list, state, and validity fields while adding
bounded reviewer metadata:

- normalized token counts, unique counts, known/unknown token inventories, and
  duplicate token lists;
- section, grouping, contact, field-name, state, and `webauthn` credential
  token classification;
- issue codes for empty attributes, invalid token syntax, unknown tokens,
  duplicates, state tokens mixed with detail tokens, misplaced `section-*`
  prefixes, and misplaced credential tokens.

The review is lexical/provenance-only. It does not invoke browser autofill
behavior, form submission, external validators, or any DOM engine beyond the
lane's native PHP parser/serializer path.
