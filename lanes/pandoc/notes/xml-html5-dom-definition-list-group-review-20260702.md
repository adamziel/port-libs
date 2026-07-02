# XML/HTML5 DOM Definition List Group Review

Bead: `plib-087w4`

Added metadata-only diagnostics for `dl` item grouping in `XmlHtmlDom`.
Existing term/definition counts and grouped `items` are preserved, with new
review fields for missing-term, missing-definition, and empty-list cases.

Focused coverage:
- `XmlHtmlDomDefinitionListGroupReviewTest.php`
- 1 mapped case
- 53 focused assertions

No browser, upstream Pandoc, external validator, or converter is invoked.
