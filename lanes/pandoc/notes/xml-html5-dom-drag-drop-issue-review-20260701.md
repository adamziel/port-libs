# XML/HTML5 DOM Drag And Drop Issue Review

This slice adds bounded native PHP review metadata for HTML drag-and-drop
global attributes in `XmlHtmlDom::summarizeHtmlFragment()`.

- `draggable` now exposes `draggableReviewPolicy`, review status, issue codes,
  issue count, and an explicit metadata-only/no-drag-engine handoff flag.
- `dropzone` now exposes review status, issue codes, issue count, and the same
  metadata-only handoff flag over the existing token/effect/MIME summaries.
- Issue buckets distinguish invalid draggable tokens, empty dropzone token
  lists, invalid dropzone tokens, and multiple dropzone effects.

Focused coverage lives in
`lanes/pandoc/tests/XmlHtmlDomDragDropIssueReviewTest.php` and exercises valid,
invalid, empty, and multi-effect drag/drop attribute packets through raw HTML
serialization and WordPress block handoff.

This does not add browser drag-and-drop behavior, execute scripts, shell out to
Pandoc, or invoke external validators. It only improves importer-facing
XML/HTML5 DOM review metadata for a bounded core blocker edge.
