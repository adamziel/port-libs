# Migrated Release Notes

This **legacy post** links back to the [source archive](https://example.test/archive) and keeps `shortcodes` visible for follow-up cleanup.

![Release archive frame][release-frame]

Inline media audit: ![thumbnail](https://example.test/uploads/thumb.jpg "Thumbnail title") remains in paragraph text.

Reference audit links: [migration checklist][checklist] and <https://example.test/audit?post=42&status=ready>.

Contact importer: <importer@example.test>.

Footnote audit: migration source[^source-note] and inline editor note.^[Inline note keeps [audit link](https://example.test/audit-footnote) and `]` marker visible.]

[checklist]: </wp-admin/post.php?post=42&action=edit> "Edit imported post"
[release-frame]: https://example.test/uploads/release-frame.jpg "Release archive frame"

[^source-note]: Source archive footnote keeps the reviewer trail.

    Confirm media IDs before publishing.

          do_action('import_note');

Reviewer _import note_ flags ___urgent media cleanup___ before publishing.

Chemistry note: H~2~O import and a^*draft*^ status need ~~legacy cleanup~~.

Migration editor said, "Don't flatten 'legacy' captions..." Keep dates 1987--1999 and one---two review notes.

Entity import note: AT&amp;T sponsor text and 4 < 5 comparator stay visible for review.

Migration math note: $x \in y$ and \cite[22-23]{smith.1899} stay visible for reviewer checks.

Display math for import proof:
$$\alpha + \omega \times x^2$$

- Preserve editorial emphasis in imported paragraphs
- Keep source links clickable for reviewer audits

  *   *   *   *   *

> Reviewer note: keep the archive URL attached to the imported post.

1. Convert Markdown to the shared AST
2. Write WordPress block comments and HTML
3. Record reviewer follow-up.

    Confirm shortcode cleanup in the migration log.

(2) Confirm source identifiers
(3) Schedule staged import
    iv. Review roman checkpoint
    v.  Approve nested audit

Import note
: Keep the archive URL attached
and mention reviewer follow-up.

Cleanup pass
: Check legacy shortcodes after block conversion.

    Record manual remediation notes.

Source glossary

~ Preserve alternate marker notes from older Pandoc exports.

~ Verify nested review tasks

    1. Confirm block conversion
    2. Attach media IDs

<div>Migration audit
:   - Preserve div-wrapped glossary notes from legacy imports
</div>

Raw import table:

<table>
<tr>
<td>*Legacy caption*</td>
<td>**Reviewer flag**</td>
</tr>
</table>

Import metrics:

| Item | Count | Notes |
| :--- | ----: | :---- |
| Posts | 42 | **ready** |
| Media | 7 | needs `alt` |

  : Migration batch summary.

Import field widths:

| Field | Count | Review Notes |
 |---------|----------|---------------------------------------|
| Posts | 42 | This long reviewer note should keep the wide column for migration summaries |
| Media | 7 | Check `alt` text before publish |

<!-- Preserve migration audit marker -->

<hr class="legacy-import-divider" />

Raw TeX table:

\begin{tabular}{|l|l|}\hline
Field & Value \\ \hline
Post ID & 42 \\
\end{tabular}

```php
do_shortcode('[legacy-gallery]');
```
