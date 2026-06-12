# XML/HTML5 DOM header/footer landmarks

## Summary

- Added bounded reviewer metadata for HTML `<header>` and `<footer>` elements in `XmlHtmlDom::summarizeHtmlFragment()`.
- Top-level headers and footers now expose implicit document landmarks as `banner` and `contentinfo`.
- Nested headers and footers retain their region metadata while recording the nearest outline ancestor scope instead of being reported as document landmarks.

## Verification

- Focused coverage exercises document-level header/footer regions, article-scoped header/footer regions, heading extraction, nearest scope IDs, nested `nav` outline metadata, `address` contact metadata, HTML serialization, and WordPress raw HTML handoff.

## Scope

This slice is limited to HTML5 `<header>` and `<footer>` region provenance. It does not change accepted outline, `hgroup`, `search`, `address`, table, text semantic, custom element, ARIA, language direction, description-list, or package ingestion behavior.
