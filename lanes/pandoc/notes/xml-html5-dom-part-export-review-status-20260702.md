# XML/HTML DOM part/exportparts review status

Date: 2026-07-02

Slice: `plib-4adh9`

Summary:
- `XmlHtmlDom` now reports compact review status and issue-count metadata for global `part` token lists and `exportparts` mappings.
- Combined fragment-level `part`/`exportparts` review metadata now exposes `partExportReviewStatus`, `partExportIssueCount`, and an explicit no-shadow-DOM handoff flag.
- Focused native PHP tests cover duplicate/invalid part tokens, duplicate/invalid export mappings, unobserved export sources, and the clean ok path without browser or shadow DOM execution.
