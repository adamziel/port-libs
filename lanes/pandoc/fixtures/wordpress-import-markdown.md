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

Plain HTML reader import table:

<table>
<tr><td>Draft posts</td><td>12</td><td>Needs review</td></tr>
<tr><td>Media files</td><td>7</td><td>Ready</td></tr>
</table>

Body-headed HTML reader import table:

<table>
<tbody data-batch="audit">
<tr><th>Queue</th><th>Items</th><th>Status</th></tr>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
<tr><td colspan="3">Review body-local headers before publish</td></tr>
</tbody>
<tfoot>
<tr><th>Total</th><td>42</td><td>Ready</td></tr>
</tfoot>
</table>

HTML reader quote import paragraph:

<p>Reviewer source says <q cite="https://example.test/import-log#quote">ready for block import</q><br />
Confirm citation metadata before publishing.</p>

HTML reader editorial inline marks:

<p><span style="font-variant: small-caps;">source glossary</span> flags <u>underlined source text</u>, <ins>inserted reviewer note</ins>, <s>stale caption</s>, <strike>old shortcode</strike>, and <del>deleted widget</del>.</p>

HTML reader legacy code export:

<pre><code class="language-php">do_shortcode('[legacy-carousel]');
echo esc_html($title);
</code></pre>

HTML reader blockquote import:

<blockquote>
<p>Reviewer checklist:</p>
<pre><code class="language-php">wp_update_post($post);
</code></pre>
<ol>
<li>Confirm source quote</li>
<li>Publish block version</li>
</ol>
<blockquote>
<p>Nested reviewer approval stays attached.</p>
</blockquote>
</blockquote>

HTML reader list import:

<ul>
<li>Review imported posts</li>
<li>Attach media audit<ul>
<li>Confirm alt text</li>
<li>Verify captions</li>
</ul>
</li>
</ul>
<ol start="4" class="lower-roman">
<li><p>Queue editorial pass</p></li>
<li><p>Publish reviewed batch</p></li>
</ol>

<h2>HTML reader nested checklist</h2>
<ul>
<li>Audit source sections<ul>
<li>Posts<ul>
<li>Confirm nested review note</li>
</ul></li>
</ul></li>
</ul>
<p>HTML reader fancy queue:</p>
<ol start="2" class="decimal">
<li>Import source batch</li>
<li><p>Review media mapping</p><p>Record continuation note</p>
<ol start="4" class="lower-roman">
<li>Check roman subqueue</li>
<li>Escalate captions<ol class="upper-alpha">
<li>Alt text</li>
<li>Credit line</li>
</ol></li>
</ol></li>
</ol>

HTML reader definition import:

<dl>
<dt>Migration glossary</dt>
<dd>Source term list.</dd>
<dd>Reviewer FAQ entry.</dd>
<dt>Reusable block</dt>
<dt>Synced pattern</dt>
<dd>Shared block-era naming stays linked.</dd>
</dl>

HTML reader inline markup import:

<p>Empty importer marks <strong></strong> and <em></em>.
<p>An <em><a href="/wp-admin/post.php?post=42&action=edit">emphasized edit link</a></em> stays attached to review copy.</p>
<p><strong><em>Urgent media cleanup</em></strong> stays nested for reviewer emphasis.</p>
<p>Legacy block source: <code>&lt;!-- wp:paragraph --&gt;</code>, <code>$post_id</code>, and <code>\$literal</code>.</p>

HTML reader literal punctuation import:

<hr />
<p>"HTML source quotes" and 70's apostrophe stay literal, with one---two and dates 1987-1999 unchanged.</p>
<p>Quoted HTML source '<code>code</code>' and a "<a href="https://example.test/review?item=42&amp;stage=html">review link</a>" stay literal.</p>

<h2>HTML reader LaTeX literal import</h2>
<ul>
<li>\cite[22-23]{smith.1899}</li>
<li>$x \in y$</li>
<li>Here's the source math literal: $\alpha + \omega \times x^2$.</li>
</ul>
<p>\begin{tabular}{|l|l|}\hline Field &amp; Value \\ \hline Posts &amp; 42 \\ \hline \end{tabular}</p>

<h2 id="special-characters">HTML reader special characters import</h2>
<p>Here is some unicode:</p>
<ul>
<li>I hat: Î</li>
<li>section: §</li>
<li>set membership: ∈</li>
<li>copyright: ©</li>
</ul>
<p>AT&amp;T import source decodes once and writes safely.</p>
<p>4 &lt; 5 and 6 &gt; 5 stay text for reviewer copy.</p>
<p>Escapes stay literal: \ ` * _ { } [ ] ( ) &gt; # . ! + -.</p>

<h2>HTML reader link import</h2>
<p>Review <a href="/wp-admin/post.php?post=42&amp;action=edit" title="Edit &amp; verify">source edit link</a> and <a href="">empty migration placeholder</a>.</p>
<p>Reference-like text [legacy-source] stays literal while <a href="https://example.test/import?post=42&amp;stage=links">audit link</a> stays linked.</p>
HTML reader source contact (importer [at] example.test)<p><a href="">Empty legacy link placeholder</a>.</p>
<p>Auto-links should not occur here: <code>&lt;https://example.test/import&gt;</code></p>

<h2>HTML reader image import</h2>
<p><img src="https://example.test/uploads/html-legacy-frame.jpg" title="Legacy frame title" alt="Legacy frame"></p>
<p>Inline HTML media <img src="https://example.test/uploads/html-inline-icon.jpg" alt="inline icon"> stays inside reviewer copy.</p>

Empty import audit table:

<table>
<tbody>
</tbody>
</table>
<table>
</table>

Nested import table:

<table>
<tr>
<td>
<table><tr><td>Inner posts</td><td>42</td></tr></table>
</td>
<td>Batch status</td>
</tr>
<tr>
<td>Reviewer</td><td>Ready</td>
</tr>
</table>

Deep nested import table:

<table>
<tr>
<td>
<table>
<tr>
<td>Outer note</td>
<td><table><tr><td>Inner posts</td><td>42</td></tr></table></td>
</tr>
</table>
</td>
<td>Batch status</td>
</tr>
</table>

Structured HTML import table:

<table id="nordics" data-source="wikipedia">
<caption><p>States belonging to the <em>Nordics.</em></p></caption>
<colgroup>
<col style="width: 30%" />
<col style="width: 30%" />
<col style="width: 20%" />
<col style="width: 20%" />
</colgroup>
<thead class="simple-head">
<tr>
<th style="text-align: center;">Name</th>
<th style="text-align: center;">Capital</th>
<th style="text-align: center;">Population<br />
(in 2018)</th>
<th style="text-align: center;">Area<br />
(in km<sup>2</sup>)</th>
</tr>
</thead>
<tbody class="souvereign-states">
<tr class="country">
<th style="text-align: center;">Denmark</th>
<td style="text-align: left;">Copenhagen</td>
<td style="text-align: left;">5,809,502</td>
<td style="text-align: left;">43,094</td>
</tr>
<tr class="country">
<th style="text-align: center;">Finland</th>
<td style="text-align: left;">Helsinki</td>
<td style="text-align: left;">5,537,364</td>
<td style="text-align: left;">338,145</td>
</tr>
<tr class="country">
<th style="text-align: center;">Iceland</th>
<td style="text-align: left;">Reykjavik</td>
<td style="text-align: left;">343,518</td>
<td style="text-align: left;">103,000</td>
</tr>
<tr class="country">
<th style="text-align: center;">Norway</th>
<td style="text-align: left;">Oslo</td>
<td style="text-align: left;">5,372,191</td>
<td style="text-align: left;">323,802</td>
</tr>
<tr class="country">
<th style="text-align: center;">Sweden</th>
<td style="text-align: left;">Stockholm</td>
<td style="text-align: left;">10,313,447</td>
<td style="text-align: left;">450,295</td>
</tr>
</tbody><tfoot>
<tr id="summary">
<td style="text-align: center;">Total</td>
<td style="text-align: left;"></td>
<td id="total-population" style="text-align: left;">27,376,022</td>
<td id="total-area" style="text-align: left;">1,258,336</td>
</tr>
</tfoot>

</table>

Segmented HTML import table:

<table>
<thead>
<tr><th>Batch</th><th>Posts</th><th>Status</th></tr>
</thead>
<tbody data-batch="published">
<tr><td>May archive</td><td><p>12</p></td><td>Published</td></tr>
</tbody>
<tbody data-batch="review">
<tr><td>June archive</td><td>8</td><td>Needs media review</td></tr>
</tbody>
</table>

Import metrics:

| Item | Count | Notes |
| :--- | ----: | :---- |
| Posts | 42 | **ready** |
| Media | 7 | needs `alt` |

  : **Migration** [batch summary][checklist] for `wp_posts`.

Import field widths:

| Field | Count | Review Notes |
 |---------|----------|---------------------------------------|
| Posts | 42 | This long reviewer note should keep the wide column for migration summaries |
| Media | 7 | Check `alt` text before publish |

Legacy source totals:

    Field Count    Status
  ------- ----- ---------
    Posts 42    Ready
    Media 7     Needs alt text

  : Legacy simple-table summary.

Wrapped review summary:

  ---------------------------------------------------------------
   Section    Owner             Count Review note
    Name      Team              Value
  ----------- ---------- ------------ ---------------------------
   Posts      Editorial          42.0 Needs reviewer approval
                                      before publish.

   Media      Library             7.0 Check alt text before
                                      attachment import.
  ---------------------------------------------------------------

  : Wrapped legacy review summary.

Short-caption LaTeX import:

\begin{table}
\caption[Batch 42]{Long source table caption for reviewer handoff.}
\begin{tabular}{lr}
Posts & 42 \\
Media & 7 \\
\end{tabular}
\end{table}

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
