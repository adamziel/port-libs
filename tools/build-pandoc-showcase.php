<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocFormatRegistry;

require __DIR__ . '/bootstrap.php';

ini_set('display_errors', 'stderr');
error_reporting(E_ALL & ~E_DEPRECATED);

if (($argv[1] ?? '') === '--convert-local') {
    raise_memory_limit('512M');
    $path = $argv[2] ?? '';
    $format = $argv[3] ?? '';
    $to = $argv[4] ?? '';
    $mediaOutputDir = $argv[5] ?? '';
    $mediaDestination = $argv[6] ?? '';
    $mediaManifest = $argv[7] ?? '';
    if ($path === '' || $format === '' || $to === '') {
        fwrite(STDERR, "Usage: php tools/build-pandoc-showcase.php --convert-local <path> <from> <to> [media-output-dir media-destination media-manifest]\n");
        exit(2);
    }
    $options = showcase_converter_options($format, $to);
    if ($mediaOutputDir !== '' && $mediaDestination !== '' && $mediaManifest !== '') {
        $options['extractMedia'] = [
            'destination' => $mediaDestination,
            'outputDirectory' => $mediaOutputDir,
            'imageMode' => 'important',
        ];
        $converted = PandocConverter::convertFileWithMedia($path, $format, $to, $options);
        file_put_contents($mediaManifest, json_encode([
            'media' => $converted['media'],
            'diagnostics' => $converted['diagnostics'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo $converted['output'];
        exit(0);
    }
    echo PandocConverter::convertFile($path, $format, $to, $options);
    exit(0);
}

$root = dirname(__DIR__);
$siteDir = $root . '/pandoc-showcase';
$samplesDir = $siteDir . '/samples';
$outputsDir = $siteDir . '/outputs';
$rawBase = 'https://raw.githubusercontent.com/jgm/pandoc/' . PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT . '/';

function raise_memory_limit(string $minimum): void
{
    $current = ini_get('memory_limit');
    if ($current === false || $current === '-1') {
        return;
    }
    if (memory_limit_bytes($current) < memory_limit_bytes($minimum)) {
        ini_set('memory_limit', $minimum);
    }
}

function memory_limit_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    return match ($unit) {
        'g' => (int) ($number * 1073741824),
        'm' => (int) ($number * 1048576),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

/**
 * @return array<string, mixed>
 */
function upstream_sample(string $id, string $format, string $path, string $label, string $description = ''): array
{
    return [
        'id' => $id,
        'format' => $format,
        'label' => $label,
        'description' => $description,
        'url' => 'https://raw.githubusercontent.com/jgm/pandoc/' . PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT . '/' . $path,
        'source' => 'jgm/pandoc upstream fixture at ' . $path,
        'filename' => basename($path),
    ];
}

/**
 * @return array<string, mixed>
 */
function inline_sample(string $id, string $format, string $filename, string $label, string $content, string $source, string $description = ''): array
{
    return [
        'id' => $id,
        'format' => $format,
        'label' => $label,
        'description' => $description,
        'content' => $content,
        'source' => $source,
        'filename' => $filename,
    ];
}

/**
 * @return array<string, mixed>
 */
function local_sample(string $id, string $format, string $path, string $label, string $description = ''): array
{
    return [
        'id' => $id,
        'format' => $format,
        'label' => $label,
        'description' => $description,
        'localPath' => $path,
        'source' => 'Checked-in port-libs fixture at ' . $path,
        'filename' => basename($path),
    ];
}

/**
 * @return array<string, mixed>
 */
function remote_sample(string $id, string $format, string $url, string $filename, string $label, string $source, string $description = ''): array
{
    return [
        'id' => $id,
        'format' => $format,
        'label' => $label,
        'description' => $description,
        'url' => $url,
        'source' => $source,
        'filename' => $filename,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function showcase_samples(): array
{
    $mediaWikiFeature = <<<'WIKI'
= MediaWiki feature packet =

Intro with ''emphasis'', '''strong''', <code>literal</code>, [[Target Page|Target label]], [https://example.org external link], and https://pandoc.org.

* first item
* second with [[Nested]]

# one
# two

; Term : Definition body with '''strong''' text.
: Orphan definition

<syntaxhighlight lang="php">
echo '<x>';
</syntaxhighlight>

----

{|
|+ Caption text
! scope="col" style="text-align:left" | Name
! style="text-align:right" | Value
|-
| style="text-align:center" | Alpha || 42
|-
! Row head || Beta
|}

Image [[File:cover.png|thumb|Cover image]]
WIKI;

    $docbook = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<article xmlns="http://docbook.org/ns/docbook" version="5.0">
  <info><title>DocBook article sample</title></info>
  <section>
    <title>Migration notes</title>
    <para>DocBook content often appears in technical publishing pipelines.</para>
    <itemizedlist>
      <listitem><para>Section titles</para></listitem>
      <listitem><para>Lists and paragraphs</para></listitem>
    </itemizedlist>
  </section>
</article>
XML;

    $bits = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<book xmlns:xlink="http://www.w3.org/1999/xlink" dtd-version="2.0">
  <book-meta>
    <book-title-group><book-title>BITS book fragment</book-title></book-title-group>
  </book-meta>
  <book-body>
    <book-part>
      <body>
        <sec><title>Chapter section</title><p>BITS is a JATS-family book format.</p></sec>
      </body>
    </book-part>
  </book-body>
</book>
XML;

    $jira = <<<'JIRA'
h1. Import plan

This is a Jira wiki markup page from a migration ticket.

* Normalize headings
* Keep links like [WordPress|https://wordpress.org/]
* Emit block markup

||Format||Status||
|HTML|partial|
|DOCX|partial|
JIRA;

    $policyMemoHtml = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Remote Work Policy Memo</title>
</head>
<body>
  <article>
    <h1 id="remote-work-policy">Remote Work Policy Memo</h1>
    <p>This internal memo describes the default expectations for teams that split work between office and remote locations.</p>
    <h2 id="summary">Summary</h2>
    <p>Managers should keep the policy simple enough for repeat use while documenting exceptions that affect payroll, security, or customer commitments.</p>
    <ul>
      <li>Publish team availability windows.</li>
      <li>Keep customer-facing escalation paths current.</li>
      <li>Review equipment and access requests quarterly.</li>
    </ul>
    <h2 id="approval-levels">Approval levels</h2>
    <table>
      <thead>
        <tr><th>Request</th><th>Approver</th><th>Review cycle</th></tr>
      </thead>
      <tbody>
        <tr><td>One-off remote day</td><td>Team lead</td><td>Same week</td></tr>
        <tr><td>Hybrid schedule</td><td>Department manager</td><td>Quarterly</td></tr>
        <tr><td>Out-of-region work</td><td>People operations</td><td>Before travel</td></tr>
      </tbody>
    </table>
    <h2 id="checklist">Checklist</h2>
    <ol>
      <li>Confirm the employee has a secure workstation.</li>
      <li>Record the expected working region.</li>
      <li>Link the schedule in the team handbook.</li>
    </ol>
    <p>See the <a href="#approval-levels">approval table</a> before granting a recurring exception.</p>
  </article>
</body>
</html>
HTML;

    $projectStatusHtml = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Project Status Update</title>
</head>
<body>
  <main>
    <h1 id="project-status-update">Project Status Update</h1>
    <p>The publishing migration remains on schedule for the July review window.</p>
    <h2 id="milestones">Milestones</h2>
    <table>
      <thead><tr><th>Milestone</th><th>Owner</th><th>Status</th></tr></thead>
      <tbody>
        <tr><td>Sample corpus refresh</td><td>Content operations</td><td>Complete</td></tr>
        <tr><td>Editor import review</td><td>Web platform</td><td>In progress</td></tr>
        <tr><td>Accessibility pass</td><td>Design systems</td><td>Scheduled</td></tr>
      </tbody>
    </table>
    <h2 id="risks">Risks</h2>
    <p>The main remaining risk is inconsistent source document quality. Teams should attach the original file to each review ticket.</p>
    <ul>
      <li>Confirm images imported when they appear in the rendered article.</li>
      <li>Check local links after the import.</li>
      <li>Flag pages that rely on Custom HTML blocks.</li>
    </ul>
    <p>Jump back to <a href="#milestones">milestones</a>.</p>
  </main>
</body>
</html>
HTML;

    $githubMarkdown = <<<'GFM'
# GitHub Flavored Markdown full syntax packet

This document intentionally exercises the rendered syntax surface of GitHub Flavored Markdown. It includes CommonMark leaf blocks, container blocks, inline syntax, and the GFM extensions for tables, task list items, strikethrough, autolinks, and raw HTML filtering.

Setext heading level 1
======================

Setext heading level 2
----------------------

## Paragraphs, breaks, and escapes

Adjacent lines in one paragraph
stay in one rendered paragraph unless a blank line separates them.
This next line uses a backslash hard break.\
This line follows the backslash hard break.

Backslash escapes render punctuation literally: \*not emphasis\*, \[not a link\], \# not a heading, and \`not code\`.

Character references render as characters: &amp; &lt; &gt; &quot; &#35; &#x1F680;.

---

***

___

## Emphasis, strong emphasis, and strikethrough

This paragraph uses *asterisk emphasis*, _underscore emphasis_, **asterisk strong**, __underscore strong__, ***combined strong emphasis***, ___combined underscore strong emphasis___, and ~~GFM strikethrough~~.

Intraword underscores should stay literal in snake_case_identifier, while intraword emphasis can still appear as foo*bar*baz.

## Code spans

Use `inline code`, ``code with ` one backtick``, and `` spaced code span `` inside normal prose.

## Indented and fenced code blocks

    Four leading spaces create an indented code block.
    Markdown syntax like **strong** stays literal here.

```php
<?php
function render_packet(array $items): string
{
    return implode("\n", array_map('strtoupper', $items));
}
```

~~~js
const rows = ["tables", "tasks", "autolinks"];
console.log(rows.map((row) => row.toUpperCase()).join(", "));
~~~

````
Fence length can contain shorter fences:
```
not the end of this block
```
````

```mermaid
flowchart LR
  markdown_github --> parser
  parser --> html
  parser --> wordpress
```

## Block quotes

> A block quote can contain a paragraph with **inline formatting**.
>
> - A list inside a quote
> - A second item with `code`
>
> > Nested quotes should remain nested.

> [!NOTE]
> GitHub alert syntax should render as a callout-style block when supported.

> [!TIP]
> Alerts can include [links](https://docs.github.com/) and **formatting**.

> [!IMPORTANT]
> Important callouts should survive as quoted content even without native alert blocks.

> [!WARNING]
> Warning callouts exercise another alert label.

> [!CAUTION]
> Caution callouts complete the GitHub alert set.

## Lists

- Tight unordered item one
- Tight unordered item two
  - Nested bullet
  - Nested bullet with **strong text**

* Asterisk bullet marker
+ Plus bullet marker
- Hyphen bullet marker

1. Ordered item one
2. Ordered item two
   1. Nested ordered item
   2. Nested ordered item with `code`
3. Ordered item three

1999. Ordered lists can start at a non-one number.
2000. The next marker should continue the list.

- Loose unordered item with a paragraph.

  Continuation paragraph in the same list item.

- Second loose item with a nested quote.

  > Quote inside a loose list item.

1. Loose ordered item with a paragraph.

   Continuation paragraph in the ordered item.

2. Second loose ordered item.

## Task list items

- [x] Parse checked task list syntax
- [X] Parse uppercase checked task markers
- [ ] Preserve unchecked tasks
- [x] Render nested formatting with **bold and _italic_ text**
  - [ ] Nested follow-up task with `inline code`
  - [x] Nested completed task with ~~old wording~~

## Tables

| Feature | Rendered example | Alignment |
| :--- | :---: | ---: |
| Bold | **This is bold text** | left |
| Italic | _This text is italicized_ | center |
| Strikethrough | ~~This was mistaken text~~ | right |
| Inline code | `git status --short` | code |
| Link | [OpenAI](https://openai.com/) | link |
| Escaped pipe | `a \| b` | cell |
| Entity | &copy; 2026 | entity |

| Short header | Extra body cell |
| --- | --- |
| Missing body cell |
| Body | cells | beyond | header |

## Links

Inline link: [OpenAI](https://openai.com/ "OpenAI home").

Reference link: [GitHub Flavored Markdown spec][gfm].

Collapsed reference link: [CommonMark][].

Shortcut reference link: [GitHub Docs].

Reference labels can be reused with different casing: [gfm].

Autolink URI: <https://github.github.com/gfm/>.

Autolink email: <support@github.com>.

Bare GFM autolinks: https://github.com/openai, http://example.com/a_(b), www.github.com, and user@example.com.

Issue-like and mention-like tokens should remain visible: #42, GH-123, @octocat, and @github/docs.

[gfm]: https://github.github.com/gfm/ "GFM spec"
[CommonMark]: https://spec.commonmark.org/
[GitHub Docs]: https://docs.github.com/

## Images

Inline image:

![GitHub mark](https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png "GitHub mark")

Reference image:

![Octocat logo][octocat]

[octocat]: https://github.githubassets.com/images/icons/emoji/octocat.png "Octocat"

## Raw HTML blocks and inline HTML

<details>
<summary>Rendered details block</summary>

GitHub allows raw HTML islands such as collapsible details, and Markdown inside the block should remain inspectable.

- HTML block list item
- Another item with **formatting**

</details>

<table>
<tr><th>HTML table head</th><th>Value</th></tr>
<tr><td>Inline HTML <strong>strong</strong></td><td>42</td></tr>
</table>

Inline HTML appears in prose: <kbd>Ctrl</kbd> + <kbd>K</kbd>, <sub>subscript</sub>, <sup>superscript</sup>, and <span data-kind="sample">span text</span>.

Disallowed raw HTML tags should be escaped or filtered by a GFM-safe renderer:

<script type="text/plain">alert("blocked")</script>

<style media="not all">body { color: red; }</style>

<iframe src="about:blank" title="blocked iframe"></iframe>

<noembed>blocked noembed</noembed>

<noframes>blocked noframes</noframes>

<textarea>blocked textarea</textarea>

## Footnotes, emoji, math, and diagrams used on GitHub

Footnotes should stay connected to their references.[^gfm-note]

Emoji shortcodes such as :rocket:, :heart:, :+1:, and :octocat: should remain visible for GitHub-style rendering.

Inline math uses dollar delimiters when enabled: $E = mc^2$.

Block math:

$$
\int_0^1 x^2\,dx = \frac{1}{3}
$$

```geojson
{
  "type": "Point",
  "coordinates": [-122.4194, 37.7749]
}
```

```topojson
{
  "type": "Topology",
  "objects": {},
  "arcs": []
}
```

```stl
solid cube
endsolid cube
```

[^gfm-note]: Footnote bodies can include **formatting**, links, `code`, and lists.

    This indented line remains part of the footnote.
GFM;

    $json = <<<'JSON'
{
  "pandoc-api-version": [1, 23, 1],
  "meta": {
    "title": { "t": "MetaInlines", "c": [{ "t": "Str", "c": "Pandoc JSON sample" }] }
  },
  "blocks": [
    { "t": "Header", "c": [1, ["pandoc-json-sample", [], []], [{ "t": "Str", "c": "Pandoc JSON sample" }]] },
    { "t": "Para", "c": [{ "t": "Str", "c": "JSON AST input used by Pandoc-compatible tools." }] }
  ]
}
JSON;

    $native = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList []})
[ Header 1 ("native-sample",[],[]) [Str "Native",Space,Str "sample"]
, Para [Str "Pandoc",Space,Str "native",Space,Str "format",Space,Str "fixture."]
]
NATIVE;

    $opml = <<<'OPML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Research outline</title></head>
  <body>
    <outline text="Migration guide">
      <outline text="Inventory formats"/>
      <outline text="Compare Haskell Pandoc and PHP port"/>
    </outline>
  </body>
</opml>
OPML;

    $csl = <<<'JSON'
[
  {
    "id": "knuth-1984",
    "type": "book",
    "title": "The TeXbook",
    "author": [{ "family": "Knuth", "given": "Donald E." }],
    "issued": { "date-parts": [[1984]] },
    "publisher": "Addison-Wesley"
  }
]
JSON;

    $ris = <<<'RIS'
TY  - BOOK
AU  - Knuth, Donald E.
TI  - The TeXbook
PY  - 1984
PB  - Addison-Wesley
ER  -
RIS;

    $tsv = "name\tformat\tstatus\nDocument\tDOCX\tpartial\nNotebook\tIPYNB\tpartial\nSlides\tPPTX\tpartial\n";

    $groups = [
        'bibtex' => [
            upstream_sample('bibtex-biblio', 'bibtex', 'test/command/biblio.bib', 'Pandoc bibliography fixture', 'BibTeX entries used by Pandoc command tests.'),
            upstream_sample('bibtex-averroes', 'bibtex', 'test/command/averroes.bib', 'Averroes bibliography fixture', 'A smaller BibTeX sample with humanistic bibliography data.'),
        ],
        'biblatex' => [
            upstream_sample('biblatex-biblio', 'biblatex', 'test/command/biblio.bib', 'BibLaTeX-style bibliography', 'Read through the local BibLaTeX-compatible bibliography path.'),
            inline_sample('biblatex-online', 'biblatex', 'biblatex-online.bib', 'Online entry sample', "@online{wp,\n  author = {WordPress Contributors},\n  title = {WordPress},\n  year = {2026},\n  url = {https://wordpress.org/}\n}\n", 'Inline public bibliographic entry assembled from real WordPress project metadata.'),
        ],
        'bits' => [
            inline_sample('bits-book', 'bits', 'bits-book.xml', 'BITS book fragment', $bits, 'Inline BITS/JATS-family XML modeled on NISO BITS book structure.'),
            inline_sample('bits-section', 'bits', 'bits-section.xml', 'BITS section fragment', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<book><book-meta><book-title-group><book-title>Second BITS fragment</book-title></book-title-group></book-meta><book-body><book-part><body><sec><title>Methods</title><p>A second compact BITS sample.</p></sec></body></book-part></book-body></book>\n", 'Inline BITS XML fragment.'),
        ],
        'commonmark' => [
            remote_sample(
                'commonmark-js-readme',
                'commonmark',
                'https://raw.githubusercontent.com/commonmark/commonmark.js/master/README.md',
                'README.md',
                'commonmark.js README',
                'commonmark/commonmark.js project README',
                'Real CommonMark project README with headings, lists, reference links, raw autolinks, code fences, API examples, and nested blockquote examples.'
            ),
        ],
        'commonmark_x' => [
            remote_sample(
                'commonmarkx-markdownlint-rules',
                'commonmark_x',
                'https://raw.githubusercontent.com/DavidAnson/markdownlint/main/doc/Rules.md',
                'Rules.md',
                'markdownlint rules reference',
                'DavidAnson/markdownlint doc/Rules.md',
                'Real 71 KB rules reference with extensive Markdown examples, headings, fenced code, lists, links, inline HTML, and extension edge cases.'
            ),
        ],
        'csljson' => [
            inline_sample('csljson-book', 'csljson', 'csljson-book.json', 'CSL JSON book item', $csl, 'Inline CSL JSON item using a real published book.'),
            inline_sample('csljson-article', 'csljson', 'csljson-article.json', 'CSL JSON article item', "[{\"id\":\"berners-lee-1994\",\"type\":\"article-journal\",\"title\":\"The World-Wide Web\",\"author\":[{\"family\":\"Berners-Lee\",\"given\":\"Tim\"}],\"issued\":{\"date-parts\":[[1994]]},\"container-title\":\"Communications of the ACM\"}]\n", 'Inline CSL JSON item for a real published article.'),
        ],
        'csv' => [
            inline_sample('csv-format-status', 'csv', 'format-status.csv', 'Format status CSV', "name,format,status\nDocument,DOCX,partial\nNotebook,IPYNB,partial\nSlides,PPTX,partial\n", 'Inline CSV inventory data for this showcase.'),
            upstream_sample('csv-rst-table', 'csv', 'test/command/3533-rst-csv-tables.csv', 'RST CSV table data', 'CSV data used in an upstream RST table command fixture.'),
        ],
        'tsv' => [
            inline_sample('tsv-format-status', 'tsv', 'format-status.tsv', 'Format status TSV', $tsv, 'Inline tabular inventory data for this showcase.'),
            inline_sample('tsv-source-status', 'tsv', 'source-status.tsv', 'Source status TSV', "source\tkind\trows\nPandoc upstream\tfixtures\tmany\nport-libs\tfixtures\tseveral\n", 'Inline tab-separated source inventory.'),
        ],
        'docbook' => [
            inline_sample('docbook-article', 'docbook', 'docbook-article.xml', 'DocBook article sample', $docbook, 'Inline DocBook 5 article modeled on technical documentation.'),
            inline_sample('docbook-table', 'docbook', 'docbook-table.xml', 'DocBook table sample', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<article xmlns=\"http://docbook.org/ns/docbook\" version=\"5.0\"><info><title>DocBook table</title></info><section><title>Coverage</title><informaltable><tgroup cols=\"2\"><tbody><row><entry>HTML</entry><entry>partial</entry></row><row><entry>DOCX</entry><entry>partial</entry></row></tbody></tgroup></informaltable></section></article>\n", 'Inline DocBook 5 table sample.'),
        ],
        'docx' => [
            local_sample('docx-quarterly-operations-report', 'docx', 'lanes/pandoc/fixtures/showcase-user-docs/quarterly-operations-report.docx', 'Quarterly operations report DOCX', 'Generated normal-user report DOCX with headings, paragraphs, status table, ordered list, and bullets.'),
            local_sample('docx-support-specialist-resume', 'docx', 'lanes/pandoc/fixtures/showcase-user-docs/support-specialist-resume.docx', 'Support specialist resume DOCX', 'Generated normal-user resume DOCX with contact details, experience sections, bullets, and skills table.'),
            local_sample('docx-remote-work-policy', 'docx', 'lanes/pandoc/fixtures/showcase-user-docs/remote-work-policy.docx', 'Remote work policy DOCX', 'Generated normal-user policy DOCX with request process, approval matrix, lists, and exception notes.'),
            upstream_sample('docx-headers', 'docx', 'test/docx/headers.docx', 'DOCX headers', 'WordprocessingML package from upstream Pandoc DOCX reader tests.'),
            upstream_sample('docx-tables', 'docx', 'test/docx/tables.docx', 'DOCX tables', 'DOCX table coverage from upstream Pandoc reader tests.'),
            upstream_sample('docx-notes', 'docx', 'test/docx/notes.docx', 'DOCX footnotes and endnotes', 'DOCX notes fixture from upstream Pandoc reader tests.'),
            upstream_sample('docx-inline-images', 'docx', 'test/docx/inline_images.docx', 'DOCX inline images', 'DOCX fixture with packaged image relationships from upstream Pandoc tests.'),
            [
                'id' => 'docx-oasis-kmip-spec',
                'format' => 'docx',
                'label' => 'OASIS KMIP specification DOCX',
                'description' => 'Real standards-track DOCX with many sections, tables, lists, references, styles, and packaged media.',
                'url' => 'https://docs.oasis-open.org/kmip/spec/v1.4/os/kmip-spec-v1.4-os.docx',
                'source' => 'OASIS KMIP Specification v1.4 editable DOCX',
                'filename' => 'kmip-spec-v1.4-os.docx',
            ],
            [
                'id' => 'docx-microsoft-excel-migration',
                'format' => 'docx',
                'label' => 'Microsoft Excel migration guide DOCX',
                'description' => 'Real Microsoft Office guide with styled headings, lists, notes, package relationships, and embedded media.',
                'url' => 'https://download.microsoft.com/download/2/C/4/2C44D34C-BDF0-4A64-9241-3F9E0FEA7B5E/Migrating%20to%20Excel%202010.docx',
                'source' => 'Microsoft Download Center Migrating to Excel 2010.docx',
                'filename' => 'Migrating-to-Excel-2010.docx',
            ],
        ],
        'endnotexml' => [
            upstream_sample('endnotexml-reader', 'endnotexml', 'test/endnotexml-reader.xml', 'EndNote XML reader fixture', 'Bibliography XML fixture from upstream Pandoc tests.'),
            inline_sample('endnotexml-book', 'endnotexml', 'endnote-book.xml', 'EndNote XML book record', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<xml><records><record><ref-type name=\"Book\">6</ref-type><contributors><authors><author>Knuth, Donald E.</author></authors></contributors><titles><title>The TeXbook</title></titles><dates><year>1984</year></dates><publisher>Addison-Wesley</publisher></record></records></xml>\n", 'Inline EndNote XML record for a real published book.'),
        ],
        'epub' => [
            upstream_sample('epub-wasteland', 'epub', 'test/epub/wasteland.epub', 'The Waste Land EPUB', 'EPUB book fixture from upstream Pandoc tests.'),
            upstream_sample('epub-features', 'epub', 'test/epub/features.epub', 'EPUB feature coverage', 'EPUB feature fixture from upstream Pandoc tests.'),
            upstream_sample('epub-picture', 'epub', 'test/epub/epub2_picture.epub', 'EPUB 2 picture book', 'EPUB fixture with packaged image content from upstream Pandoc tests.'),
            upstream_sample('epub-image', 'epub', 'test/epub/img.epub', 'EPUB image coverage', 'EPUB fixture focused on embedded image handling.'),
            [
                'id' => 'epub-gutenberg-alice-illustrated',
                'format' => 'epub',
                'label' => 'Illustrated Alice EPUB',
                'description' => 'Real Project Gutenberg EPUB3 with chapter XHTML, CSS, navigation, cover art, and dozens of illustrations.',
                'url' => 'https://www.gutenberg.org/ebooks/28885.epub3.images',
                'source' => 'Project Gutenberg illustrated Alice EPUB3 with images',
                'filename' => 'alice-illustrated.epub',
            ],
        ],
        'fb2' => [
            upstream_sample('fb2-basic', 'fb2', 'test/fb2/basic.fb2', 'FB2 basic book', 'FictionBook XML sample from upstream Pandoc tests.'),
            upstream_sample('fb2-notes', 'fb2', 'test/fb2/reader/notes.fb2', 'FB2 notes', 'FictionBook notes fixture from upstream Pandoc tests.'),
        ],
        'gfm' => [
            remote_sample(
                'gfm-gitlab-markdown-guide',
                'gfm',
                'https://raw.githubusercontent.com/gitlabhq/gitlabhq/master/doc/user/markdown.md',
                'gitlab-markdown.md',
                'GitLab Markdown guide',
                'gitlabhq/gitlabhq doc/user/markdown.md',
                'Real 81 KB GitLab Flavored Markdown guide with front matter, tables, fenced code, task lists, strikethrough, math, diagrams, references, images, and nested lists.'
            ),
        ],
        'markdown_github' => [
            inline_sample(
                'markdown-github-rendered-syntax',
                'markdown_github',
                'github-rendered-syntax.md',
                'Full GitHub syntax packet',
                $githubMarkdown,
                'Inline GFM sample with broad unescaped syntax rendered as document content.',
                'Expanded GitHub Flavored Markdown showcase covering CommonMark leaf blocks, container blocks, inline syntax, GFM tables, task lists, strikethrough, autolinks, raw HTML filtering, alerts, footnotes, emoji, math, and diagram fences.'
            ),
        ],
        'html' => [
            upstream_sample('html-reader', 'html', 'test/html-reader.html', 'HTML reader fixture', 'HTML fixture from upstream Pandoc tests.'),
            upstream_sample('html-template', 'html', 'data/templates/styles.html', 'Pandoc HTML template fragment', 'Real Pandoc project HTML template asset.'),
            inline_sample('html-remote-work-policy', 'html', 'remote-work-policy.html', 'Remote work policy memo HTML', $policyMemoHtml, 'Inline policy memo HTML modeled on a normal internal document.', 'Compact user-facing HTML article with headings, paragraphs, lists, a table, and a local anchor link.'),
            inline_sample('html-project-status-update', 'html', 'project-status-update.html', 'Project status update HTML', $projectStatusHtml, 'Inline project status HTML modeled on a normal internal update.', 'Compact user-facing HTML article with headings, a milestone table, a risk list, and a local anchor link.'),
        ],
        'ipynb' => [
            upstream_sample('ipynb-simple', 'ipynb', 'test/ipynb/simple.ipynb', 'Simple notebook', 'Jupyter notebook fixture from upstream Pandoc tests.'),
            upstream_sample('ipynb-mime', 'ipynb', 'test/ipynb/mime.ipynb', 'Notebook with MIME output', 'Notebook MIME fixture from upstream Pandoc tests.'),
        ],
        'jats' => [
            inline_sample('jats-article', 'jats', 'jats-article.xml', 'JATS article fragment', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<article xmlns:xlink=\"http://www.w3.org/1999/xlink\">\n  <front><article-meta><title-group><article-title>JATS article fragment</article-title></title-group></article-meta></front>\n  <body><sec><title>Abstracted section</title><p>JATS is common in scholarly publishing pipelines.</p></sec></body>\n</article>\n", 'Inline JATS XML modeled on scholarly article structure.'),
            upstream_sample('jats-reader', 'jats', 'test/jats-reader.xml', 'Upstream JATS reader fixture', 'JATS XML fixture from upstream Pandoc tests; local reader currently rejects the doctype.'),
        ],
        'jira' => [
            inline_sample('jira-ticket', 'jira', 'jira-ticket.jira', 'Jira ticket markup', $jira, 'Inline Jira-style project markup.'),
            inline_sample('jira-release', 'jira', 'jira-release.jira', 'Jira release markup', "h2. Release checklist\n\n# Convert source files\n# Review block markup\n# Publish static showcase\n", 'Inline Jira-style release checklist.'),
        ],
        'json' => [
            inline_sample('json-pandoc-ast', 'json', 'pandoc-ast.json', 'Pandoc JSON AST', $json, 'Inline Pandoc JSON AST document.'),
            inline_sample('json-pandoc-list', 'json', 'pandoc-list.json', 'Pandoc JSON list AST', "{\"pandoc-api-version\":[1,23,1],\"meta\":{},\"blocks\":[{\"t\":\"BulletList\",\"c\":[[{\"t\":\"Plain\",\"c\":[{\"t\":\"Str\",\"c\":\"First\"}]}],[{\"t\":\"Plain\",\"c\":[{\"t\":\"Str\",\"c\":\"Second\"}]}]]}]}\n", 'Inline Pandoc JSON AST list document.'),
        ],
        'latex' => [
            upstream_sample('latex-table', 'latex', 'test/command/3971b.tex', 'LaTeX command fixture', 'LaTeX fixture from upstream Pandoc command tests.'),
            upstream_sample('latex-bar', 'latex', 'test/command/bar.tex', 'LaTeX included file fixture', 'Small TeX file from upstream Pandoc command tests.'),
        ],
        'markdown' => [
            [
                'id' => 'markdown-pandoc-manual',
                'format' => 'markdown',
                'label' => 'Pandoc manual Markdown',
                'description' => 'Real 293 KB Markdown manual with dense sections, tables, links, code, lists, metadata, and large generated tables.',
                'url' => 'https://raw.githubusercontent.com/jgm/pandoc/' . PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT . '/MANUAL.txt',
                'source' => 'jgm/pandoc MANUAL.txt at upstream source commit',
                'filename' => 'MANUAL.txt',
            ],
        ],
        'markdown_mmd' => [
            remote_sample(
                'markdown-mmd-quickstart',
                'markdown_mmd',
                'https://raw.githubusercontent.com/fletcher/MultiMarkdown-6/master/QuickStart/QuickStart.txt',
                'QuickStart.txt',
                'MultiMarkdown Quick Start guide',
                'fletcher/MultiMarkdown-6 QuickStart/QuickStart.txt',
                'Real MultiMarkdown guide with metadata, citations, CriticMarkup examples, image syntax, fenced code, footnotes, glossary terms, and heading structure.'
            ),
        ],
        'markdown_phpextra' => [
            remote_sample(
                'markdown-phpextra-php-markdown-readme',
                'markdown_phpextra',
                'https://raw.githubusercontent.com/michelf/php-markdown/lib/Readme.md',
                'Readme.md',
                'PHP Markdown README',
                'michelf/php-markdown Readme.md',
                'Real PHP Markdown project README covering PHP Markdown Extra usage, code blocks, links, raw HTML block handling, special attributes, and release notes.'
            ),
        ],
        'markdown_strict' => [
            remote_sample(
                'markdown-strict-gruber-syntax',
                'markdown_strict',
                'https://daringfireball.net/projects/markdown/syntax.text',
                'syntax.text',
                'Original Markdown syntax documentation',
                'Daring Fireball Markdown syntax source',
                'Real original Markdown syntax document with setext headings, inline HTML, nested lists, block quotes, indented code, reference links, emphasis, images, and autolinks.'
            ),
        ],
        'man' => [
            local_sample('man-generated-fixture', 'man', 'lanes/pandoc/fixtures/man-corpus-smoke/generated.5', 'Generated roff manpage fixture', 'Checked-in roff manpage exercising TH/SH, font escapes, tagged paragraphs, indentation, no-fill code, and generated-man requests.'),
            local_sample('man-simple-fixture', 'man', 'lanes/pandoc/fixtures/man-corpus-smoke/simple.1', 'Simple roff manpage fixture', 'Checked-in compact roff manpage with title, section, name, and description macros.'),
        ],
        'mediawiki' => [
            inline_sample('mediawiki-feature-packet', 'mediawiki', 'mediawiki-feature-packet.wiki', 'MediaWiki feature packet', $mediaWikiFeature, 'Inline MediaWiki packet covering headings, emphasis, links, lists, definitions, syntaxhighlight code, horizontal rules, tables, and images.'),
            inline_sample('mediawiki-template-math-note', 'mediawiki', 'mediawiki-template-math-note.wiki', 'MediaWiki templates math and notes', "= Parser features =\n\nBefore <!-- hidden markup --> after &amp;.\n\n<nowiki>''raw'' [[x]] &amp;</nowiki> <math>E=mc^2</math> <ref>Note ''body''</ref> A<br />B {{tmpl|x}}\n\n{{CURRENTYEAR}}\n", 'Inline MediaWiki packet covering comments, nowiki, math, references, line breaks, templates, and raw parser-function fallback.'),
        ],
        'native' => [
            inline_sample('native-basic', 'native', 'native-basic.native', 'Pandoc native AST', $native, 'Inline Pandoc native AST fixture.'),
            upstream_sample('native-markdown-more', 'native', 'test/markdown-reader-more.native', 'Upstream native Markdown reader output', 'Pandoc native fixture from upstream tests.'),
        ],
        'odt' => [
            upstream_sample('odt-headers', 'odt', 'test/odt/odt/headers.odt', 'ODT headers', 'OpenDocument text fixture from upstream Pandoc tests.'),
            upstream_sample('odt-table-spans', 'odt', 'test/odt/odt/tableWithSpans.odt', 'ODT table spans', 'ODT table fixture from upstream Pandoc tests.'),
            [
                'id' => 'odt-oasis-opendocument-schema',
                'format' => 'odt',
                'label' => 'OASIS OpenDocument schema ODT',
                'description' => 'Real 930 KB editable ODT specification with thousands of headings, long prose, lists, tables, styles, and package metadata.',
                'url' => 'https://docs.oasis-open.org/office/OpenDocument/v1.3/os/part3-schema/OpenDocument-v1.3-os-part3-schema.odt',
                'source' => 'OASIS OpenDocument v1.3 Part 3 schema editable ODT',
                'filename' => 'OpenDocument-v1.3-os-part3-schema.odt',
            ],
        ],
        'opml' => [
            upstream_sample('opml-reader', 'opml', 'test/opml-reader.opml', 'OPML reader fixture', 'OPML outline fixture from upstream Pandoc tests.'),
            inline_sample('opml-outline', 'opml', 'research-outline.opml', 'Research outline', $opml, 'Inline OPML outline.'),
        ],
        'pptx' => [
            upstream_sample('pptx-basic', 'pptx', 'test/pptx-reader/basic.pptx', 'Basic PPTX', 'Presentation fixture from upstream Pandoc tests.'),
            upstream_sample('pptx-tables', 'pptx', 'test/pptx/tables/output.pptx', 'PPTX tables', 'Generated presentation fixture from upstream Pandoc tests.'),
            [
                'id' => 'pptx-cdc-food-safety-slides',
                'format' => 'pptx',
                'label' => 'CDC food safety classroom slides',
                'description' => 'Real CDC PowerPoint deck with 16 slides, many images, list-heavy slides, and a data table.',
                'url' => 'https://www.cdc.gov/museum/pdf/cdcm-pha-stem-keeping-food-healthy-slides.pptx',
                'source' => 'CDC Museum Public Health Academy Keeping Food Healthy slides',
                'filename' => 'cdc-food-healthy-slides.pptx',
            ],
            [
                'id' => 'pptx-who-bfhi-session-1',
                'format' => 'pptx',
                'label' => 'WHO BFHI training slides',
                'description' => 'Real WHO training PowerPoint with 13 slides, photographic assets, speaker metadata, lists, and structured slide text.',
                'url' => 'https://www.who.int/docs/default-source/breastfeeding/publication/bfhi-training-curriculum/bfhi-session-1-slides.pptx?sfvrsn=d2535153_6',
                'source' => 'WHO Baby-friendly Hospital Initiative training curriculum session 1 slides',
                'filename' => 'bfhi-session-1-slides.pptx',
            ],
        ],
        'ris' => [
            inline_sample('ris-texbook', 'ris', 'texbook.ris', 'RIS book record', $ris, 'Inline RIS record for a real published book.'),
            inline_sample('ris-web', 'ris', 'wordpress.ris', 'RIS website record', "TY  - ELEC\nAU  - WordPress Contributors\nTI  - WordPress\nPY  - 2026\nUR  - https://wordpress.org/\nER  -\n", 'Inline RIS record assembled from real WordPress project metadata.'),
        ],
        'rtf' => [
            upstream_sample('rtf-template', 'rtf', 'data/templates/default.rtf', 'Pandoc default RTF template', 'Real RTF template from Pandoc project data.'),
            inline_sample('rtf-simple', 'rtf', 'simple.rtf', 'Simple RTF document', "{\\rtf1\\ansi\\deff0 {\\fonttbl {\\f0 Arial;}}\\f0\\fs24 RTF migration sample\\par This is a second RTF input.\\par}\n", 'Inline RTF document sample.'),
            inline_sample('rtf-meeting-notes', 'rtf', 'meeting-notes.rtf', 'Meeting notes RTF document', "{\\rtf1\\ansi\\deff0 {\\fonttbl {\\f0 Arial;}}\\f0\\fs28 Meeting notes\\par\\fs24 Decisions\\par 1. Publish the updated import guide.\\par 2. Review converted tables before launch.\\par Follow-up owners will report status next week.\\par}\n", 'Inline RTF meeting notes modeled on a normal office document.'),
        ],
        'xlsx' => [
            upstream_sample('xlsx-basic', 'xlsx', 'test/xlsx-reader/basic.xlsx', 'Basic XLSX workbook', 'Spreadsheet fixture from upstream Pandoc tests.'),
            [
                'id' => 'xlsx-conditional-access-blueprint',
                'format' => 'xlsx',
                'label' => 'Conditional Access blueprint workbook',
                'description' => 'Small public workbook from a GitHub Pages repository, used as a second real-world XLSX input.',
                'url' => 'https://raw.githubusercontent.com/jasperbaes/jasperbaes.github.io/main/CAF/ConditionalAccessBlueprint-Template.xlsx',
                'source' => 'jasperbaes/jasperbaes.github.io ConditionalAccessBlueprint-Template.xlsx',
                'filename' => 'ConditionalAccessBlueprint-Template.xlsx',
            ],
            [
                'id' => 'xlsx-census-tax-parameter-workbook',
                'format' => 'xlsx',
                'label' => 'Census tax parameter workbook',
                'description' => 'Real Census Bureau workbook with 102 worksheets of federal and state tax parameters.',
                'url' => 'https://www2.census.gov/library/working-papers/2024/demo/Federal_and_State_Tax_Parameter_Workbook_TY2023.xlsx',
                'source' => 'U.S. Census Bureau Federal and State Tax Parameter Workbook TY2023',
                'filename' => 'Federal_and_State_Tax_Parameter_Workbook_TY2023.xlsx',
            ],
        ],
        'xml' => [
            inline_sample('xml-docbook-generic', 'xml', 'generic-docbook.xml', 'Generic XML document', $docbook, 'Inline XML document read through the generic XML path.'),
            inline_sample('xml-outline-generic', 'xml', 'generic-outline.xml', 'Generic XML outline', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<document><title>Generic XML outline</title><section><heading>Inventory</heading><p>Plain XML read through the generic XML path.</p></section></document>\n", 'Inline generic XML document.'),
        ],
        'pdf' => [
            [
                'id' => 'pdf-irs-w4',
                'format' => 'pdf',
                'label' => 'IRS Form W-4 PDF',
                'description' => 'Real IRS fillable tax form with labels, headings, instructions, form-style layout, and bounded extraction runtime.',
                'url' => 'https://www.irs.gov/pub/irs-pdf/fw4.pdf',
                'source' => 'Internal Revenue Service Form W-4 PDF',
                'filename' => 'irs-form-w4.pdf',
            ],
            [
                'id' => 'pdf-tracemonkey',
                'format' => 'pdf',
                'label' => 'TraceMonkey technical PDF',
                'description' => 'Known hard untagged two-column PDF from Mozilla pdf.js tests; the local PDF bridge currently produces a diagnostic extraction preview, not a faithful final document.',
                'url' => 'https://raw.githubusercontent.com/mozilla/pdf.js/master/test/pdfs/tracemonkey.pdf',
                'source' => 'mozilla/pdf.js test/pdfs/tracemonkey.pdf',
                'filename' => 'tracemonkey.pdf',
            ],
            [
                'id' => 'pdf-cdc-hand-hygiene-brochure',
                'format' => 'pdf',
                'label' => 'CDC hand hygiene brochure',
                'description' => 'Short public health brochure with flyer-like layout, large headings, side-by-side blocks, and image-heavy design elements.',
                'url' => 'https://www.cdc.gov/clean-hands/media/pdfs/cdc-handhygiene-brochure-508.pdf',
                'source' => 'Centers for Disease Control and Prevention hand hygiene brochure PDF',
                'filename' => 'cdc-handhygiene-brochure.pdf',
            ],
            [
                'id' => 'pdf-grand-canyon-north-rim-map',
                'format' => 'pdf',
                'label' => 'Grand Canyon North Rim pocket map',
                'description' => 'National Park Service leaflet-style pocket map with map panels, services, columns, and mixed visual/text layout.',
                'url' => 'https://www.nps.gov/grca/learn/news/upload/nr-pocket-map.pdf',
                'source' => 'National Park Service Grand Canyon North Rim Services Guide pocket map PDF',
                'filename' => 'grand-canyon-north-rim-pocket-map.pdf',
            ],
            [
                'id' => 'pdf-archive-motograph-book',
                'format' => 'pdf',
                'label' => 'Public-domain scanned book PDF',
                'description' => 'Internet Archive public-domain scanned small book with OCR text, title pages, and book-like page flow.',
                'url' => 'https://archive.org/download/motographmoving00laugoog/motographmoving00laugoog.pdf',
                'source' => 'Internet Archive public-domain scan of The Motograph Moving Picture Book',
                'filename' => 'motograph-moving-picture-book.pdf',
            ],
            [
                'id' => 'pdf-muir-beach-brochure',
                'format' => 'pdf',
                'label' => 'Muir Beach illustrated brochure',
                'description' => 'Small National Park Service brochure with photos, map-oriented visitor information, headings, and short panels.',
                'url' => 'https://www.nps.gov/goga/planyourvisit/upload/MUBE-sb_web_17e.pdf',
                'source' => 'National Park Service Muir Beach brochure PDF',
                'filename' => 'muir-beach-brochure.pdf',
            ],
        ],
        'doc' => [
            [
                'id' => 'doc-poi-47304',
                'format' => 'doc',
                'label' => 'Apache POI legacy Word sample',
                'description' => 'Small real Word 97-2003 binary fixture from Apache POI test data.',
                'url' => 'https://raw.githubusercontent.com/apache/poi/trunk/test-data/document/47304.doc',
                'source' => 'apache/poi test-data/document/47304.doc',
                'filename' => '47304.doc',
            ],
            [
                'id' => 'doc-poi-57843',
                'format' => 'doc',
                'label' => 'Apache POI compact Word sample',
                'description' => 'Second real Word 97-2003 binary fixture from Apache POI test data.',
                'url' => 'https://raw.githubusercontent.com/apache/poi/trunk/test-data/document/57843.doc',
                'source' => 'apache/poi test-data/document/57843.doc',
                'filename' => '57843.doc',
            ],
        ],
    ];

    $samples = [];
    foreach ($groups as $format => $formatSamples) {
        foreach ($formatSamples as $sample) {
            $samples[] = $sample;
        }
    }

    return $samples;
}

function ensure_clean_dir(string $path): void
{
    if (is_dir($path)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    } else {
        mkdir($path, 0777, true);
    }
}

function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function download_file(string $url, string $target): bool
{
    $cmd = [
        'curl',
        '-L',
        '--fail',
        '--silent',
        '--show-error',
        '--connect-timeout',
        '15',
        '--max-time',
        '60',
        '-o',
        $target,
        $url,
    ];
    $result = run_process($cmd, 75);

    return $result['exitCode'] === 0 && is_file($target) && filesize($target) > 0;
}

/**
 * @return array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>}
 */
function showcase_converter_options(string $from, string $to): array
{
    $readerOptions = [];
    $writerOptions = [];
    $canonicalInput = PandocConverter::canonicalInputFormat($from);
    $canonicalOutput = PandocConverter::canonicalOutputFormat($to);
    if ($canonicalInput === 'pdf') {
        $readerOptions['maxTextBytes'] = 80000;
        $readerOptions['pdfGeometryTables'] = false;
        $readerOptions['pdfRepairProseText'] = true;
    }
    if ($canonicalInput === 'docx' && in_array($canonicalOutput, ['html', 'wordpress'], true)) {
        $readerOptions['preserveRunStyles'] = true;
        $readerOptions['preserveImportStyles'] = true;
    }
    if (in_array($canonicalOutput, ['html', 'wordpress'], true)) {
        $writerOptions['writerHTMLMathMethod'] = 'mathml';
    }

    return array_filter([
        'readerOptions' => $readerOptions,
        'writerOptions' => $writerOptions,
    ]);
}

function wrap_local_html_document(string $body, string $title): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>'
        . '<style>'
        . 'html{line-height:1.5;font-family:Georgia,"Times New Roman",serif;font-size:18px;color:#1f2933;background:#fdfdfd;}'
        . 'body{margin:0 auto;max-width:46em;padding:2.5em 1.25em;}'
        . 'h1,h2,h3,h4,h5,h6{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.25;margin:1.4em 0 .45em;}'
        . 'h1{font-size:2em;}h2{font-size:1.55em;}h3{font-size:1.25em;}p{margin:1em 0;}'
        . 'a{color:#1f6feb;}img,svg,video{max-width:100%;height:auto;}'
        . 'pre,code,kbd,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.9em;}'
        . 'code{background:#eef1f5;padding:.1em .25em;border-radius:3px;}'
        . 'pre{overflow:auto;background:#f6f8fa;border:1px solid #d8dde3;border-radius:6px;padding:1em;}pre code{background:transparent;padding:0;}'
        . 'blockquote{margin:1.25em 0;padding-left:1em;border-left:4px solid #d8dde3;color:#4b5563;}'
        . 'table{border-collapse:collapse;margin:1.25em 0;width:100%;font-size:.92em;}th,td{border:1px solid #d8dde3;padding:.35em .55em;vertical-align:top;}th{background:#f6f8fa;}'
        . 'math{font-family:math,serif;}math[display="block"]{display:block;margin:1em 0;overflow-x:auto;}'
        . '</style></head><body>' . $body . '</body></html>';
}

/**
 * @param list<string> $cmd
 * @return array{exitCode:int, stdout:string, stderr:string}
 */
function run_process(array $cmd, int $timeoutSeconds = 0): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Unable to start process.'];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $started = time();
    while (true) {
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if ($timeoutSeconds > 0 && time() - $started >= $timeoutSeconds) {
            proc_terminate($process);
            usleep(200000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return ['exitCode' => 124, 'stdout' => $stdout, 'stderr' => trim($stderr . "\nTimed out after {$timeoutSeconds}s.")];
        }
        usleep(100000);
    }
    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['exitCode' => $exitCode, 'stdout' => is_string($stdout) ? $stdout : '', 'stderr' => is_string($stderr) ? $stderr : ''];
}

/**
 * @return array{ok:bool, path?:string, error?:string, media?:list<array<string,mixed>>, mediaDiagnostics?:list<string>}
 */
function write_output_from_process(string $dir, string $name, string $sourcePath, string $from, string $to): array
{
    $timeout = PandocConverter::canonicalInputFormat($from) === 'pdf' || (is_file($sourcePath) && filesize($sourcePath) > 262144) ? 75 : 25;
    $mediaDir = $dir . '/media';
    $manifestPath = tempnam(sys_get_temp_dir(), 'pandoc-media-manifest-');
    if (!is_string($manifestPath)) {
        $manifestPath = $dir . '/' . $name . '.media.json';
    }
    $result = run_process([PHP_BINARY, __FILE__, '--convert-local', $sourcePath, $from, $to, $mediaDir, 'media', $manifestPath], $timeout);
    if ($result['exitCode'] === 0) {
        $stdout = $to === 'html'
            ? wrap_local_html_document($result['stdout'], 'PHP Pandoc HTML output')
            : $result['stdout'];
        file_put_contents($dir . '/' . $name, $stdout);

        $manifest = read_media_manifest($manifestPath, basename($dir));
        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        return [
            'ok' => true,
            'path' => 'outputs/' . basename($dir) . '/' . $name,
            'media' => $manifest['media'],
            'mediaDiagnostics' => $manifest['diagnostics'],
        ];
    }

    $message = sanitize_generated_text(trim($result['stderr'] . "\n" . $result['stdout']));
    if ($message === '') {
        $message = 'Local converter exited with code ' . $result['exitCode'];
    }
    if (is_file($manifestPath)) {
        unlink($manifestPath);
    }
    file_put_contents($dir . '/' . $name . '.error.txt', $message);

    return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/' . $name . '.error.txt'];
}

/**
 * @return array{media:list<array<string,mixed>>, diagnostics:list<string>}
 */
function read_media_manifest(string $path, string $sampleId): array
{
    if (!is_file($path)) {
        return ['media' => [], 'diagnostics' => []];
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        return ['media' => [], 'diagnostics' => ['extract-media-manifest-unreadable']];
    }

    $media = [];
    foreach (($json['media'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pathValue = isset($entry['path']) && is_string($entry['path']) ? $entry['path'] : '';
        if ($pathValue !== '' && str_starts_with($pathValue, 'media/')) {
            $entry['path'] = 'outputs/' . $sampleId . '/' . $pathValue;
        }
        $media[] = $entry;
    }
    $diagnostics = [];
    foreach (($json['diagnostics'] ?? []) as $diagnostic) {
        if (is_string($diagnostic) && $diagnostic !== '') {
            $diagnostics[] = $diagnostic;
        }
    }

    return ['media' => $media, 'diagnostics' => $diagnostics];
}

/**
 * @return array<string, int>
 */
function wordpress_block_counts(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $source = file_get_contents($path);
    if (!is_string($source) || $source === '') {
        return [];
    }

    preg_match_all('/<!--\s+wp:([A-Za-z0-9_\/-]+)/', $source, $matches);
    $counts = [];
    foreach ($matches[1] ?? [] as $name) {
        $name = strtolower((string) $name);
        $counts[$name] = ($counts[$name] ?? 0) + 1;
    }
    ksort($counts);

    return $counts;
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{totals: array<string, int>, sampleCount:int}
 */
function aggregate_wordpress_block_counts(array $records): array
{
    $totals = [];
    $sampleCount = 0;
    foreach ($records as $record) {
        $counts = $record['wpBlockCounts'] ?? [];
        if (!is_array($counts) || $counts === []) {
            continue;
        }
        $sampleCount++;
        foreach ($counts as $name => $count) {
            $name = (string) $name;
            $totals[$name] = ($totals[$name] ?? 0) + (int) $count;
        }
    }
    ksort($totals);

    return ['totals' => $totals, 'sampleCount' => $sampleCount];
}

/**
 * @return array<string, mixed>
 */
function showcase_record_faithfulness(string $siteDir, array $record): array
{
    $baselineKey = (($record['haskell']['ok'] ?? false) === true) ? 'haskell' : ((($record['phpHtml']['ok'] ?? false) === true) ? 'phpHtml' : '');
    if ($baselineKey === '') {
        return ['baseline' => null, 'comparisons' => []];
    }
    $baselinePath = (string) ($record[$baselineKey]['path'] ?? '');
    $baselineText = showcase_output_text($siteDir, $baselinePath);
    $baselineVisual = showcase_output_visual_signature($siteDir, $baselinePath);
    if ($baselineText === '') {
        return ['baseline' => null, 'comparisons' => []];
    }

    $comparisons = [];
    foreach (['wpBlocks' => 'PHP WordPress blocks', 'phpHtml' => 'PHP HTML'] as $key => $label) {
        if ($key === $baselineKey || (($record[$key]['ok'] ?? false) !== true)) {
            continue;
        }
        $text = showcase_output_text($siteDir, (string) ($record[$key]['path'] ?? ''));
        if ($text === '') {
            $comparisons[$key] = [
                'label' => $label,
                'status' => 'no_text',
                'score' => 0.0,
                'textStatus' => 'no_text',
                'textScore' => 0.0,
                'visualStatus' => 'no_visual_structure',
                'visualScore' => 0.0,
            ];
            continue;
        }
        $textScore = showcase_text_similarity($baselineText, $text);
        $visualScore = showcase_visual_signature_similarity(
            $baselineVisual,
            showcase_output_visual_signature($siteDir, (string) ($record[$key]['path'] ?? ''))
        );
        $comparisons[$key] = [
            'label' => $label,
            'status' => $textScore >= 0.80 ? 'faithful_enough' : ($textScore >= 0.55 ? 'review' : 'divergent'),
            'score' => $textScore,
            'textStatus' => $textScore >= 0.80 ? 'faithful_enough' : ($textScore >= 0.55 ? 'review' : 'divergent'),
            'textScore' => $textScore,
            'visualStatus' => $visualScore >= 0.75 ? 'faithful_enough' : ($visualScore >= 0.50 ? 'review' : 'divergent'),
            'visualScore' => $visualScore,
        ];
    }

    return [
        'baseline' => $baselineKey,
        'comparisons' => $comparisons,
    ];
}

function showcase_output_text(string $siteDir, string $relativePath): string
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '') {
        return '';
    }
    if (class_exists(DOMDocument::class)) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded) {
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//head|//script|//style|//noscript|//template|//*[@id="title-block-header"]') ?: [] as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
            $body = $dom->getElementsByTagName('body')->item(0);
            $text = $body instanceof DOMElement ? $body->textContent : $dom->textContent;
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

            return trim($text);
        }
    }
    $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
    $html = preg_replace('/<(head|script|style|noscript|template)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
    $html = preg_replace('/<[^>]*\bid=["\']title-block-header["\'][^>]*>.*?<\/[^>]+>/is', '', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function showcase_output_html(string $siteDir, string $relativePath): string
{
    if ($relativePath === '' || str_contains($relativePath, "\0") || str_contains($relativePath, '..')) {
        return '';
    }
    $path = $siteDir . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        return '';
    }
    $html = file_get_contents($path);

    return is_string($html) ? $html : '';
}

/**
 * @return array<string, int>
 */
function showcase_output_visual_signature(string $siteDir, string $relativePath): array
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '') {
        return [];
    }
    $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
    $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
    preg_match_all('/<\s*(h[1-6]|p|li|ul|ol|table|thead|tbody|tr|th|td|img|figure|figcaption|pre|code|blockquote|math|svg)\b/i', $html, $matches);

    $counts = [];
    foreach ($matches[1] ?? [] as $tag) {
        $tag = strtolower((string) $tag);
        if (preg_match('/^h[1-6]$/', $tag) === 1) {
            $counts['heading'] = ($counts['heading'] ?? 0) + 1;
        }
        $counts[$tag] = ($counts[$tag] ?? 0) + 1;
    }
    ksort($counts);

    return $counts;
}

/**
 * @param array<string, int> $expected
 * @param array<string, int> $actual
 */
function showcase_visual_signature_similarity(array $expected, array $actual): float
{
    if ($expected === [] && $actual === []) {
        return 1.0;
    }
    if ($expected === [] || $actual === []) {
        return 0.0;
    }

    $keys = array_values(array_unique([...array_keys($expected), ...array_keys($actual)]));
    $overlap = 0;
    $total = 0;
    foreach ($keys as $key) {
        $left = max(0, (int) ($expected[$key] ?? 0));
        $right = max(0, (int) ($actual[$key] ?? 0));
        $overlap += min($left, $right);
        $total += max($left, $right);
    }

    return $total === 0 ? 1.0 : round($overlap / $total, 4);
}

function showcase_text_similarity(string $expected, string $actual): float
{
    $expectedTokens = showcase_text_tokens($expected);
    $actualTokens = showcase_text_tokens($actual);
    if ($expectedTokens === [] && $actualTokens === []) {
        return 1.0;
    }
    if ($expectedTokens === [] || $actualTokens === []) {
        return 0.0;
    }

    $expectedCounts = array_count_values($expectedTokens);
    $actualCounts = array_count_values($actualTokens);
    $overlap = 0;
    foreach ($expectedCounts as $token => $count) {
        $overlap += min($count, $actualCounts[$token] ?? 0);
    }

    return round((2.0 * $overlap) / (count($expectedTokens) + count($actualTokens)), 4);
}

/**
 * @return list<string>
 */
function showcase_text_tokens(string $text): array
{
    $text = mb_strtolower($text, 'UTF-8');
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];

    return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{comparisons:int, faithfulEnough:int, review:int, divergent:int, noText:int, visualComparisons:int, visualFaithfulEnough:int, visualReview:int, visualDivergent:int, visualNoStructure:int}
 */
function showcase_faithfulness_summary(array $records): array
{
    $summary = [
        'comparisons' => 0,
        'faithfulEnough' => 0,
        'review' => 0,
        'divergent' => 0,
        'noText' => 0,
        'visualComparisons' => 0,
        'visualFaithfulEnough' => 0,
        'visualReview' => 0,
        'visualDivergent' => 0,
        'visualNoStructure' => 0,
    ];
    foreach ($records as $record) {
        $faithfulness = $record['faithfulness'] ?? [];
        if (!is_array($faithfulness)) {
            continue;
        }
        foreach (($faithfulness['comparisons'] ?? []) as $comparison) {
            if (!is_array($comparison)) {
                continue;
            }
            $summary['comparisons']++;
            $status = (string) ($comparison['status'] ?? '');
            if ($status === 'faithful_enough') {
                $summary['faithfulEnough']++;
            } elseif ($status === 'review') {
                $summary['review']++;
            } elseif ($status === 'divergent') {
                $summary['divergent']++;
            } else {
                $summary['noText']++;
            }
            $summary['visualComparisons']++;
            $visualStatus = (string) ($comparison['visualStatus'] ?? '');
            if ($visualStatus === 'faithful_enough') {
                $summary['visualFaithfulEnough']++;
            } elseif ($visualStatus === 'review') {
                $summary['visualReview']++;
            } elseif ($visualStatus === 'divergent') {
                $summary['visualDivergent']++;
            } else {
                $summary['visualNoStructure']++;
            }
        }
    }

    return $summary;
}

/**
 * @return array{status:string, gates:array<string,array<string,mixed>>, summary:array<string,int>}
 */
function showcase_record_import_quality(string $siteDir, array $record): array
{
    $gates = [];
    if (($record['wpBlocks']['ok'] ?? false) !== true) {
        $gates['conversion'] = [
            'status' => 'fail',
            'detail' => (string) ($record['wpBlocks']['error'] ?? 'WordPress block conversion failed.'),
        ];

        return showcase_import_quality_result($gates);
    }

    $wpPath = (string) ($record['wpBlocks']['path'] ?? '');
    $baselineKey = (string) (($record['faithfulness']['baseline'] ?? '') ?: '');
    $baselinePath = $baselineKey !== '' ? (string) ($record[$baselineKey]['path'] ?? '') : '';
    $wpVisual = showcase_output_visual_signature($siteDir, $wpPath);
    $baselineVisual = $baselinePath === '' ? [] : showcase_output_visual_signature($siteDir, $baselinePath);
    $comparison = is_array($record['faithfulness']['comparisons']['wpBlocks'] ?? null)
        ? $record['faithfulness']['comparisons']['wpBlocks']
        : [];

    $textScore = isset($comparison['textScore']) ? (float) $comparison['textScore'] : null;
    $gates['text_completeness'] = showcase_score_gate($textScore, 0.80, 0.55, 'visible text overlap with the baseline');

    $visualScore = isset($comparison['visualScore']) ? (float) $comparison['visualScore'] : null;
    $gates['visual_structure'] = showcase_score_gate($visualScore, 0.75, 0.50, 'heading/list/table/image shape overlap with the baseline');

    $gates['paragraph_merge_split'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['p'] ?? 0),
        (int) ($wpVisual['p'] ?? 0),
        'paragraph count ratio'
    );
    $gates['heading_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['heading'] ?? 0),
        (int) ($wpVisual['heading'] ?? 0),
        'heading count ratio'
    );
    $gates['list_count'] = showcase_count_ratio_gate(
        (int) (($baselineVisual['ul'] ?? 0) + ($baselineVisual['ol'] ?? 0)),
        (int) (($wpVisual['ul'] ?? 0) + ($wpVisual['ol'] ?? 0)),
        'list count ratio'
    );
    $gates['table_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['table'] ?? 0),
        (int) ($wpVisual['table'] ?? 0),
        'table count ratio'
    );
    $gates['image_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['img'] ?? 0),
        (int) ($wpVisual['img'] ?? 0),
        'image count ratio'
    );

    $mediaProblems = showcase_media_problem_diagnostics($record['wpBlocks']['mediaDiagnostics'] ?? []);
    $gates['media_imported'] = [
        'status' => $mediaProblems === [] ? 'pass' : 'review',
        'expected' => 0,
        'actual' => count($mediaProblems),
        'detail' => $mediaProblems === [] ? 'no media extraction problems reported' : implode('; ', array_slice($mediaProblems, 0, 3)),
    ];

    $anchorIssues = showcase_output_anchor_issues($siteDir, $wpPath);
    $gates['anchor_validity'] = [
        'status' => $anchorIssues === [] ? 'pass' : 'fail',
        'expected' => 0,
        'actual' => count($anchorIssues),
        'detail' => $anchorIssues === [] ? 'all local fragment links resolve' : implode(', ', array_slice($anchorIssues, 0, 5)),
    ];

    $counts = is_array($record['wpBlockCounts'] ?? null) ? $record['wpBlockCounts'] : [];
    $totalBlocks = array_sum(array_map('intval', $counts));
    $customHtmlBlocks = (int) ($counts['html'] ?? 0);
    $customHtmlShare = $totalBlocks <= 0 ? 0.0 : round($customHtmlBlocks / $totalBlocks, 4);
    $gates['custom_html_percentage'] = [
        'status' => $customHtmlShare <= 0.05 ? 'pass' : ($customHtmlShare <= 0.15 ? 'review' : 'fail'),
        'expected' => '<=0.05',
        'actual' => $customHtmlShare,
        'detail' => $customHtmlBlocks . '/' . $totalBlocks . ' WordPress blocks are Custom HTML',
    ];

    return showcase_import_quality_result($gates);
}

/**
 * @param array<string,array<string,mixed>> $gates
 * @return array{status:string, gates:array<string,array<string,mixed>>, summary:array<string,int>}
 */
function showcase_import_quality_result(array $gates): array
{
    $summary = ['pass' => 0, 'review' => 0, 'fail' => 0];
    foreach ($gates as $gate) {
        $status = (string) ($gate['status'] ?? 'review');
        if (!isset($summary[$status])) {
            $status = 'review';
        }
        $summary[$status]++;
    }
    $status = $summary['fail'] > 0 ? 'fail' : ($summary['review'] > 0 ? 'review' : 'pass');

    return [
        'status' => $status,
        'gates' => $gates,
        'summary' => $summary,
    ];
}

/**
 * @return array{status:string, expected:mixed, actual:mixed, detail:string}
 */
function showcase_score_gate(?float $score, float $passThreshold, float $reviewThreshold, string $detail): array
{
    if ($score === null) {
        return [
            'status' => 'review',
            'expected' => '>=' . $passThreshold,
            'actual' => null,
            'detail' => 'no baseline comparison available for ' . $detail,
        ];
    }

    return [
        'status' => $score >= $passThreshold ? 'pass' : ($score >= $reviewThreshold ? 'review' : 'fail'),
        'expected' => '>=' . $passThreshold,
        'actual' => round($score, 4),
        'detail' => $detail,
    ];
}

/**
 * @return array{status:string, expected:string, actual:mixed, detail:string}
 */
function showcase_count_ratio_gate(int $expected, int $actual, string $detail): array
{
    if ($expected === 0 && $actual === 0) {
        return ['status' => 'pass', 'expected' => '0', 'actual' => 0, 'detail' => $detail];
    }
    if ($expected === 0) {
        return ['status' => 'review', 'expected' => '0', 'actual' => $actual, 'detail' => $detail . ' has no baseline count'];
    }
    $ratio = round($actual / max(1, $expected), 4);

    return [
        'status' => ($ratio >= 0.80 && $ratio <= 1.25) ? 'pass' : (($ratio >= 0.50 && $ratio <= 2.00) ? 'review' : 'fail'),
        'expected' => '0.80-1.25',
        'actual' => $ratio,
        'detail' => $detail . ' expected ' . $expected . ', got ' . $actual,
    ];
}

/**
 * @param mixed $diagnostics
 * @return list<string>
 */
function showcase_media_problem_diagnostics(mixed $diagnostics): array
{
    if (!is_array($diagnostics)) {
        return [];
    }
    $problems = [];
    foreach ($diagnostics as $diagnostic) {
        $diagnostic = (string) $diagnostic;
        if ($diagnostic === '' || str_starts_with($diagnostic, 'extract-media-pdf-image-unimportant:')) {
            continue;
        }
        if (preg_match('/(?:missing|failed|unreadable|invalid|skipped|too-large|limit|conflict)/i', $diagnostic) === 1) {
            $problems[] = $diagnostic;
        }
    }

    return array_values(array_unique($problems));
}

/**
 * @return list<string>
 */
function showcase_output_anchor_issues(string $siteDir, string $relativePath): array
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '' || !class_exists(DOMDocument::class)) {
        return [];
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return ['html-parse-failed'];
    }

    $targets = [];
    foreach ($dom->getElementsByTagName('*') as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }
        foreach (['id', 'name'] as $attr) {
            $value = trim($element->getAttribute($attr));
            if ($value !== '') {
                $targets[$value] = true;
            }
        }
    }

    $issues = [];
    foreach ($dom->getElementsByTagName('a') as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }
        $href = trim($anchor->getAttribute('href'));
        if ($href === '' || $href === '#' || !str_starts_with($href, '#')) {
            continue;
        }
        $fragment = rawurldecode(substr($href, 1));
        if ($fragment !== '' && !isset($targets[$fragment])) {
            $issues[] = $href;
        }
    }

    return array_values(array_unique($issues));
}

/**
 * @param list<array<string,mixed>> $records
 * @return array{samples:int, pass:int, review:int, fail:int}
 */
function showcase_import_quality_summary(array $records): array
{
    $summary = ['samples' => 0, 'pass' => 0, 'review' => 0, 'fail' => 0];
    foreach ($records as $record) {
        $quality = $record['importQuality'] ?? null;
        if (!is_array($quality)) {
            continue;
        }
        $summary['samples']++;
        $status = (string) ($quality['status'] ?? 'review');
        if (!isset($summary[$status])) {
            $status = 'review';
        }
        $summary[$status]++;
    }

    return $summary;
}

/**
 * @return array<string, true>
 */
function showcase_common_import_format_set(): array
{
    return array_fill_keys([
        'csv',
        'docx',
        'epub',
        'gfm',
        'html',
        'markdown',
        'markdown_github',
        'odt',
        'pdf',
        'pptx',
        'rtf',
        'tsv',
        'xlsx',
    ], true);
}

/**
 * @param list<array<string,mixed>> $records
 * @return array{
 *     common:array{samples:int, pass:int, review:int, fail:int, wpFailures:int, passOrReview:int, formats:array<string,array{samples:int, pass:int, review:int, fail:int, wpFailures:int, passOrReview:int}>},
 *     exotic:array{samples:int, pass:int, review:int, fail:int, wpFailures:int, passOrReview:int, formats:array<string,array{samples:int, pass:int, review:int, fail:int, wpFailures:int, passOrReview:int}>}
 * }
 */
function showcase_import_quality_segment_summary(array $records): array
{
    $commonFormats = showcase_common_import_format_set();
    $empty = static fn (): array => [
        'samples' => 0,
        'pass' => 0,
        'review' => 0,
        'fail' => 0,
        'wpFailures' => 0,
        'passOrReview' => 0,
        'formats' => [],
    ];
    $emptyFormat = static fn (): array => [
        'samples' => 0,
        'pass' => 0,
        'review' => 0,
        'fail' => 0,
        'wpFailures' => 0,
        'passOrReview' => 0,
    ];
    $summary = [
        'common' => $empty(),
        'exotic' => $empty(),
    ];

    foreach ($records as $record) {
        $format = (string) ($record['format'] ?? '');
        $segment = isset($commonFormats[$format]) ? 'common' : 'exotic';
        $summary[$segment]['samples']++;
        $summary[$segment]['formats'][$format] ??= $emptyFormat();
        $summary[$segment]['formats'][$format]['samples']++;

        if (($record['wpBlocks']['ok'] ?? false) !== true) {
            $summary[$segment]['wpFailures']++;
            $summary[$segment]['formats'][$format]['wpFailures']++;
            continue;
        }

        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $status = (string) ($quality['status'] ?? 'fail');
        if (!in_array($status, ['pass', 'review', 'fail'], true)) {
            $status = 'fail';
        }
        $summary[$segment][$status]++;
        $summary[$segment]['formats'][$format][$status]++;
        if ($status === 'pass' || $status === 'review') {
            $summary[$segment]['passOrReview']++;
            $summary[$segment]['formats'][$format]['passOrReview']++;
        }
    }

    foreach (['common', 'exotic'] as $segment) {
        ksort($summary[$segment]['formats']);
    }

    return $summary;
}

/**
 * @return array<string, array<string, mixed>>
 */
function showcase_import_quality_thresholds(): array
{
    return [
        'common' => [
            'required' => true,
            'minSamples' => 44,
            'maxWpFailures' => 0,
            'minPass' => 7,
            'minPassOrReview' => 24,
            'maxFail' => 20,
        ],
        'exotic' => [
            'required' => false,
            'minSamples' => 43,
            'maxWpFailures' => 2,
            'minPass' => 7,
            'minPassOrReview' => 22,
            'maxFail' => 19,
        ],
    ];
}

/**
 * @param array<string, mixed> $summary
 * @return array<string, mixed>
 */
function showcase_import_quality_threshold_gate(array $summary): array
{
    $thresholds = showcase_import_quality_thresholds();
    $segments = [];
    $blockingFailures = [];
    $trackedFailures = [];

    foreach ($thresholds as $segment => $threshold) {
        $actual = is_array($summary[$segment] ?? null) ? $summary[$segment] : [];
        $checks = [
            'minSamples' => [
                'status' => (int) ($actual['samples'] ?? 0) >= (int) $threshold['minSamples'] ? 'pass' : 'fail',
                'expected' => '>=' . (string) $threshold['minSamples'],
                'actual' => (int) ($actual['samples'] ?? 0),
            ],
            'maxWpFailures' => [
                'status' => (int) ($actual['wpFailures'] ?? 0) <= (int) $threshold['maxWpFailures'] ? 'pass' : 'fail',
                'expected' => '<=' . (string) $threshold['maxWpFailures'],
                'actual' => (int) ($actual['wpFailures'] ?? 0),
            ],
            'minPass' => [
                'status' => (int) ($actual['pass'] ?? 0) >= (int) $threshold['minPass'] ? 'pass' : 'fail',
                'expected' => '>=' . (string) $threshold['minPass'],
                'actual' => (int) ($actual['pass'] ?? 0),
            ],
            'minPassOrReview' => [
                'status' => (int) ($actual['passOrReview'] ?? 0) >= (int) $threshold['minPassOrReview'] ? 'pass' : 'fail',
                'expected' => '>=' . (string) $threshold['minPassOrReview'],
                'actual' => (int) ($actual['passOrReview'] ?? 0),
            ],
            'maxFail' => [
                'status' => (int) ($actual['fail'] ?? 0) <= (int) $threshold['maxFail'] ? 'pass' : 'fail',
                'expected' => '<=' . (string) $threshold['maxFail'],
                'actual' => (int) ($actual['fail'] ?? 0),
            ],
        ];

        $failedChecks = array_keys(array_filter(
            $checks,
            static fn (array $check): bool => ($check['status'] ?? '') === 'fail'
        ));
        $status = $failedChecks === [] ? 'pass' : 'fail';
        $required = (bool) ($threshold['required'] ?? false);
        if ($status === 'fail') {
            if ($required) {
                $blockingFailures[] = $segment;
            }
            $trackedFailures[] = $segment;
        }

        $segments[$segment] = [
            'status' => $status,
            'required' => $required,
            'thresholds' => $threshold,
            'actual' => [
                'samples' => (int) ($actual['samples'] ?? 0),
                'wpFailures' => (int) ($actual['wpFailures'] ?? 0),
                'pass' => (int) ($actual['pass'] ?? 0),
                'review' => (int) ($actual['review'] ?? 0),
                'fail' => (int) ($actual['fail'] ?? 0),
                'passOrReview' => (int) ($actual['passOrReview'] ?? 0),
            ],
            'checks' => $checks,
            'failedChecks' => $failedChecks,
        ];
    }

    return [
        'status' => $blockingFailures === [] ? 'pass' : 'fail',
        'trackedStatus' => $trackedFailures === [] ? 'pass' : 'fail',
        'blockingSegments' => $blockingFailures,
        'trackedFailureSegments' => $trackedFailures,
        'segments' => $segments,
    ];
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{
 *   totalSamples:int,
 *   totalConversions:int,
 *   successfulConversions:int,
 *   failedConversions:int,
 *   byConverter:array<string, array{label:string, ok:int, failed:int, total:int}>,
 *   byFormat:array<string, array{samples:int, ok:int, failed:int, total:int}>
 * }
 */
function conversion_summary(array $records): array
{
    $converters = [
        'wpBlocks' => 'PHP WordPress blocks',
        'phpHtml' => 'PHP HTML',
        'haskell' => 'Haskell Pandoc HTML',
    ];
    $byConverter = [];
    foreach ($converters as $key => $label) {
        $byConverter[$key] = [
            'label' => $label,
            'ok' => 0,
            'failed' => 0,
            'total' => 0,
        ];
    }

    $byFormat = [];
    $successfulConversions = 0;
    foreach ($records as $record) {
        $format = (string) ($record['format'] ?? 'unknown');
        if (!isset($byFormat[$format])) {
            $byFormat[$format] = [
                'samples' => 0,
                'ok' => 0,
                'failed' => 0,
                'total' => 0,
            ];
        }
        $byFormat[$format]['samples']++;
        foreach ($converters as $key => $label) {
            $ok = (($record[$key]['ok'] ?? false) === true);
            $byConverter[$key]['total']++;
            $byFormat[$format]['total']++;
            if ($ok) {
                $byConverter[$key]['ok']++;
                $byFormat[$format]['ok']++;
                $successfulConversions++;
            } else {
                $byConverter[$key]['failed']++;
                $byFormat[$format]['failed']++;
            }
        }
    }
    ksort($byFormat);
    $totalConversions = count($records) * count($converters);

    return [
        'totalSamples' => count($records),
        'totalConversions' => $totalConversions,
        'successfulConversions' => $successfulConversions,
        'failedConversions' => $totalConversions - $successfulConversions,
        'byConverter' => $byConverter,
        'byFormat' => $byFormat,
    ];
}

function result_badge(array $result): string
{
    $ok = (($result['ok'] ?? false) === true);
    $class = $ok ? 'status-ok' : 'status-fail';
    $label = $ok ? 'ok' : 'failed';
    $path = (string) ($result['path'] ?? '');
    if ($path === '') {
        return '<span class="' . $class . '">' . $label . '</span>';
    }

    return '<a class="' . $class . '" href="' . h($path) . '">' . $label . '</a>';
}

function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{total:int, samples:int, previews:list<array<string,mixed>>}
 */
function extracted_media_preview(array $records): array
{
    $total = 0;
    $sampleIds = [];
    $candidates = [];
    $seen = [];
    $priority = array_flip([
        'pdf-muir-beach-brochure',
        'pdf-cdc-hand-hygiene-brochure',
        'pdf-grand-canyon-north-rim-map',
        'pdf-archive-motograph-book',
        'epub-picture',
        'epub-gutenberg-alice-illustrated',
        'docx-inline-images',
        'pptx-cdc-food-safety-slides',
        'odt-oasis-opendocument-schema',
    ]);
    foreach ($records as $record) {
        foreach (['wpBlocks' => 'WordPress blocks', 'phpHtml' => 'PHP HTML'] as $key => $label) {
            $result = $record[$key] ?? null;
            if (!is_array($result)) {
                continue;
            }
            foreach (($result['media'] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $mimeType = (string) ($entry['mimeType'] ?? '');
                $path = (string) ($entry['path'] ?? '');
                if ($path === '' || !str_starts_with($mimeType, 'image/')) {
                    continue;
                }
                $total++;
                $sampleIds[(string) $record['id']] = true;
                $dedupeKey = $path . '|' . (string) ($entry['sha1'] ?? '');
                if (isset($seen[$dedupeKey]) || !media_preview_can_render($mimeType, $path)) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $candidates[] = [
                    'path' => $path,
                    'mimeType' => $mimeType,
                    'byteLength' => (int) ($entry['byteLength'] ?? 0),
                    'source' => (string) ($entry['source'] ?? ''),
                    'sampleId' => (string) $record['id'],
                    'sampleLabel' => (string) $record['label'],
                    'format' => (string) $record['format'],
                    'converter' => $label,
                ];
            }
        }
    }

    usort($candidates, static function (array $a, array $b) use ($priority): int {
        $aPriority = $priority[(string) $a['sampleId']] ?? 1000;
        $bPriority = $priority[(string) $b['sampleId']] ?? 1000;
        if ($aPriority !== $bPriority) {
            return $aPriority <=> $bPriority;
        }
        $sample = (string) $a['sampleId'] <=> (string) $b['sampleId'];
        if ($sample !== 0) {
            return $sample;
        }
        $converter = (string) $a['converter'] <=> (string) $b['converter'];
        if ($converter !== 0) {
            return $converter;
        }

        return (string) $a['path'] <=> (string) $b['path'];
    });

    $previews = [];
    $perSample = [];
    foreach ($candidates as $candidate) {
        $sampleId = (string) $candidate['sampleId'];
        if (($perSample[$sampleId] ?? 0) >= 10) {
            continue;
        }
        $previews[] = $candidate;
        $perSample[$sampleId] = ($perSample[$sampleId] ?? 0) + 1;
        if (count($previews) >= 96) {
            break;
        }
    }

    return ['total' => $total, 'samples' => count($sampleIds), 'previews' => $previews];
}

function media_preview_can_render(string $mimeType, string $path): bool
{
    if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'image/bmp', 'image/apng', 'image/avif'], true)) {
        return true;
    }

    return preg_match('/\.(?:jpe?g|png|gif|svg|webp|bmp|apng|avif)(?:[?#].*)?\z/i', $path) === 1;
}

/**
 * @param list<array<string, mixed>> $records
 * @param list<string> $coveredFormats
 * @param list<string> $missingFormats
 * @param array<string, mixed> $summary
 * @param array<string, mixed> $blockUsage
 */
function write_conversion_report(
    string $siteDir,
    array $records,
    array $coveredFormats,
    array $missingFormats,
    array $summary,
    array $blockUsage,
    array $faithfulnessSummary,
    array $importQualitySummary,
    array $importQualityGate
): void {
    $recordsById = [];
    foreach ($records as $record) {
        $recordsById[(string) $record['id']] = $record;
    }
    $curatedIds = [
        'pdf-cdc-hand-hygiene-brochure',
        'pdf-grand-canyon-north-rim-map',
        'pdf-muir-beach-brochure',
        'pdf-archive-motograph-book',
        'pdf-tracemonkey',
        'epub-gutenberg-alice-illustrated',
        'epub-picture',
        'docx-oasis-kmip-spec',
        'docx-inline-images',
        'docx-tables',
        'odt-oasis-opendocument-schema',
        'odt-table-spans',
        'pptx-cdc-food-safety-slides',
        'pptx-who-bfhi-session-1',
        'xlsx-census-tax-parameter-workbook',
        'markdown-github-rendered-syntax',
        'markdown-pandoc-manual',
        'mediawiki-feature-packet',
        'man-generated-fixture',
    ];

    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    $html .= '<title>Pandoc PHP Port Conversion Report</title><link rel="stylesheet" href="styles.css"></head><body>';
    $html .= '<header class="hero"><div class="hero-inner"><p class="eyebrow">conversion report</p><h1>How the pulled test files converted</h1>';
    $html .= '<p class="lede">Every source file in this showcase is run through three paths: Haskell Pandoc to HTML, the PHP port to HTML, and the PHP port to WordPress block markup. This report shows the current pass/fail shape and links into a curated stress set.</p>';
    $html .= '<div class="stats">';
    $html .= '<div class="stat"><strong>' . h((string) $summary['totalSamples']) . '</strong><span>source files</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) count($coveredFormats)) . '</strong><span>covered input formats</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) $summary['successfulConversions']) . '/' . h((string) $summary['totalConversions']) . '</strong><span>successful conversions</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) $summary['failedConversions']) . '</strong><span>known failures</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($faithfulnessSummary['faithfulEnough'] ?? 0)) . '/' . h((string) ($faithfulnessSummary['comparisons'] ?? 0)) . '</strong><span>text-faithful comparisons</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($faithfulnessSummary['visualFaithfulEnough'] ?? 0)) . '/' . h((string) ($faithfulnessSummary['visualComparisons'] ?? 0)) . '</strong><span>visual-structure matches</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($importQualitySummary['pass'] ?? 0)) . '/' . h((string) ($importQualitySummary['samples'] ?? 0)) . '</strong><span>import-quality passes</span></div>';
    $html .= '</div><div class="hero-actions"><a href="index.html">Full showcase</a><a href="playground-converter.html">Convert in WordPress Playground</a><a href="manifest.json">Manifest JSON</a></div></div></header>';
    $html .= '<main class="content-page report-page">';

    $html .= '<section><h2>Success by conversion path</h2><div class="report-grid">';
    foreach ($summary['byConverter'] as $converter) {
        $html .= '<div class="report-card"><h3>' . h((string) $converter['label']) . '</h3>';
        $html .= '<p class="report-number">' . h((string) $converter['ok']) . '/' . h((string) $converter['total']) . '</p>';
        $html .= '<p class="meta">' . h((string) $converter['failed']) . ' failed</p></div>';
    }
    $html .= '</div></section>';

    $html .= '<section><h2>Faithful enough diff checks</h2>';
    $html .= '<p>These checks compare generated outputs against Haskell Pandoc when available, or PHP HTML as a fallback baseline. Text scores compare normalized visible words. Visual-structure scores compare the rendered document shape: headings, paragraphs, lists, tables, images, figures, captions, code, quotes, and math.</p>';
    $html .= '<h3>Text</h3>';
    $html .= '<div class="report-grid">';
    $html .= '<div class="report-card"><h3>Faithful enough</h3><p class="report-number">' . h((string) ($faithfulnessSummary['faithfulEnough'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Needs review</h3><p class="report-number">' . h((string) ($faithfulnessSummary['review'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Divergent or empty</h3><p class="report-number">' . h((string) (($faithfulnessSummary['divergent'] ?? 0) + ($faithfulnessSummary['noText'] ?? 0))) . '</p></div>';
    $html .= '</div>';
    $html .= '<h3>Visual structure</h3>';
    $html .= '<div class="report-grid">';
    $html .= '<div class="report-card"><h3>Faithful enough</h3><p class="report-number">' . h((string) ($faithfulnessSummary['visualFaithfulEnough'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Needs review</h3><p class="report-number">' . h((string) ($faithfulnessSummary['visualReview'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Divergent or empty</h3><p class="report-number">' . h((string) (($faithfulnessSummary['visualDivergent'] ?? 0) + ($faithfulnessSummary['visualNoStructure'] ?? 0))) . '</p></div>';
    $html .= '</div></section>';

    $html .= '<section><h2>Import quality gates</h2>';
    $html .= '<p>These checks evaluate the WordPress block output as an import artifact: visible text completeness, heading/list/table/image counts, paragraph merge or split drift, media extraction diagnostics, local anchor validity, and Custom HTML block share.</p>';
    $html .= '<div class="report-grid">';
    $html .= '<div class="report-card"><h3>Pass</h3><p class="report-number">' . h((string) ($importQualitySummary['pass'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Review</h3><p class="report-number">' . h((string) ($importQualitySummary['review'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Fail</h3><p class="report-number">' . h((string) ($importQualitySummary['fail'] ?? 0)) . '</p></div>';
    $html .= '</div>';
    $segments = is_array($importQualityGate['segments'] ?? null) ? $importQualityGate['segments'] : [];
    if ($segments !== []) {
        $html .= '<h3>Actionable thresholds</h3>';
        $html .= '<p>The common-format gate is blocking and covers normal WordPress import formats. Exotic formats are tracked separately so they stay visible without diluting the common-format release signal.</p>';
        $html .= '<table class="report-table compact-table"><thead><tr><th>Segment</th><th>Status</th><th>Pass or review</th><th>Failures</th><th>Conversion failures</th><th>Policy</th></tr></thead><tbody>';
        foreach ($segments as $segment => $gate) {
            if (!is_array($gate)) {
                continue;
            }
            $actual = is_array($gate['actual'] ?? null) ? $gate['actual'] : [];
            $thresholds = is_array($gate['thresholds'] ?? null) ? $gate['thresholds'] : [];
            $status = (string) ($gate['status'] ?? 'fail');
            $required = (bool) ($gate['required'] ?? false);
            $statusClass = $status === 'pass' ? 'status-ok' : 'status-fail';
            $html .= '<tr><td><strong>' . h((string) $segment) . '</strong></td>';
            $html .= '<td><span class="' . $statusClass . '">' . h($status) . '</span></td>';
            $html .= '<td>' . h((string) ($actual['passOrReview'] ?? 0)) . '/' . h((string) ($actual['samples'] ?? 0)) . ' <span class="meta">min ' . h((string) ($thresholds['minPassOrReview'] ?? '')) . '</span></td>';
            $html .= '<td>' . h((string) ($actual['fail'] ?? 0)) . ' <span class="meta">max ' . h((string) ($thresholds['maxFail'] ?? '')) . '</span></td>';
            $html .= '<td>' . h((string) ($actual['wpFailures'] ?? 0)) . ' <span class="meta">max ' . h((string) ($thresholds['maxWpFailures'] ?? '')) . '</span></td>';
            $html .= '<td>' . ($required ? 'blocking' : 'tracked') . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '<table class="report-table compact-table"><thead><tr><th>Sample</th><th>Status</th><th>Gate summary</th></tr></thead><tbody>';
    foreach ($records as $record) {
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $status = (string) ($quality['status'] ?? 'review');
        $gateSummary = is_array($quality['summary'] ?? null) ? $quality['summary'] : [];
        if ($status === 'pass') {
            continue;
        }
        $html .= '<tr><td><a href="index.html#' . h((string) $record['id']) . '">' . h((string) $record['label']) . '</a><br><code>' . h((string) $record['format']) . '</code></td>';
        $html .= '<td><span class="' . ($status === 'fail' ? 'status-fail' : 'status-warn') . '">' . h($status) . '</span></td>';
        $html .= '<td>' . h((string) ($gateSummary['pass'] ?? 0)) . ' pass, ' . h((string) ($gateSummary['review'] ?? 0)) . ' review, ' . h((string) ($gateSummary['fail'] ?? 0)) . ' fail</td></tr>';
    }
    $html .= '</tbody></table></section>';

    $html .= '<section><h2>Curated stress showcase</h2><p>These are representative real-world files from the pulled corpus: leaflets, brochures, a scanned book, image-heavy packages, table-heavy office documents, and rich markup fixtures.</p>';
    $html .= '<table class="report-table"><thead><tr><th>Sample</th><th>Format</th><th>Size</th><th>PHP WordPress blocks</th><th>PHP HTML</th><th>Haskell HTML</th></tr></thead><tbody>';
    foreach ($curatedIds as $id) {
        if (!isset($recordsById[$id])) {
            continue;
        }
        $record = $recordsById[$id];
        $html .= '<tr><td><a href="index.html#' . h((string) $record['id']) . '">' . h((string) $record['label']) . '</a><br><span class="meta">' . h((string) $record['description']) . '</span></td>';
        $html .= '<td><code>' . h((string) $record['format']) . '</code></td>';
        $html .= '<td>' . h(human_size((int) ($record['sampleSize'] ?? 0))) . '</td>';
        $html .= '<td>' . result_badge(is_array($record['wpBlocks'] ?? null) ? $record['wpBlocks'] : []) . '</td>';
        $html .= '<td>' . result_badge(is_array($record['phpHtml'] ?? null) ? $record['phpHtml'] : []) . '</td>';
        $html .= '<td>' . result_badge(is_array($record['haskell'] ?? null) ? $record['haskell'] : []) . '</td></tr>';
    }
    $html .= '</tbody></table></section>';

    $mediaPreview = extracted_media_preview($records);
    $html .= '<section><h2>Extracted media preview</h2>';
    $html .= '<p>The PHP path now runs an <code>--extract-media</code>-style pass in <strong>images we thought were important</strong> mode. Referenced package images are written beside the converted output and their <code>&lt;img&gt;</code> URLs are rewritten to hosted files; PDF image streams are copied out when their intrinsic metadata suggests they are document content rather than tiny masks or decorative fragments.</p>';
    $html .= '<p class="meta">' . h((string) $mediaPreview['total']) . ' image media entr' . ($mediaPreview['total'] === 1 ? 'y' : 'ies') . ' extracted across ' . h((string) $mediaPreview['samples']) . ' source file' . ($mediaPreview['samples'] === 1 ? '' : 's') . '.</p>';
    if ($mediaPreview['previews'] !== []) {
        $html .= '<div class="media-gallery">';
        foreach ($mediaPreview['previews'] as $preview) {
            $html .= '<figure><a href="' . h((string) $preview['path']) . '"><img src="' . h((string) $preview['path']) . '" alt=""></a>';
            $html .= '<figcaption><strong>' . h((string) $preview['sampleLabel']) . '</strong><span><code>' . h((string) $preview['format']) . '</code> · ' . h((string) $preview['converter']) . '</span><span>' . h(basename((string) $preview['path'])) . ' · ' . h(human_size((int) $preview['byteLength'])) . '</span></figcaption></figure>';
        }
        $html .= '</div>';
    } else {
        $html .= '<p class="status-warn">No browser-previewable extracted images were found in this run.</p>';
    }
    $html .= '</section>';

    $html .= '<section><h2>Success by input format</h2><table class="report-table compact-table"><thead><tr><th>Format</th><th>Files</th><th>Successful conversions</th><th>Failures</th></tr></thead><tbody>';
    foreach ($summary['byFormat'] as $format => $formatSummary) {
        $html .= '<tr><td><a href="index.html#format-' . h((string) $format) . '"><code>' . h((string) $format) . '</code></a></td>';
        $html .= '<td>' . h((string) $formatSummary['samples']) . '</td>';
        $html .= '<td>' . h((string) $formatSummary['ok']) . '/' . h((string) $formatSummary['total']) . '</td>';
        $html .= '<td>' . h((string) $formatSummary['failed']) . '</td></tr>';
    }
    $html .= '</tbody></table></section>';

    $html .= '<section><h2>WordPress block coverage</h2><p>The WordPress output generated blocks for ' . h((string) ($blockUsage['sampleCount'] ?? 0)) . ' samples.</p>';
    $html .= '<table class="report-table compact-table"><thead><tr><th>Block</th><th>Count</th></tr></thead><tbody>';
    foreach (($blockUsage['totals'] ?? []) as $block => $count) {
        $html .= '<tr><td><code>' . h((string) $block) . '</code></td><td>' . h((string) $count) . '</td></tr>';
    }
    $html .= '</tbody></table></section>';

    if ($missingFormats !== []) {
        $html .= '<section><h2>Still missing samples</h2><p class="status-warn">' . h(implode(', ', $missingFormats)) . '</p></section>';
    }

    $html .= '</main></body></html>';
    file_put_contents($siteDir . '/conversion-report.html', $html);
}

/**
 * @return array{ok:bool, path?:string, error?:string}
 */
function run_haskell_pandoc(string $path, string $format, string $dir): array
{
    $out = $dir . '/haskell.html';
    $result = run_process(['pandoc', '--from=' . $format, '--to=html', '--standalone', '--metadata', 'title=Haskell Pandoc output', '--output', $out, $path], 35);
    if ($result['exitCode'] !== 0 || !is_file($out)) {
        $message = sanitize_generated_text(trim($result['stderr'] . "\n" . $result['stdout']));
        if ($message === '') {
            $message = 'pandoc exited with code ' . $result['exitCode'];
        }
        file_put_contents($dir . '/haskell.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/haskell.html.error.txt'];
    }

    return ['ok' => true, 'path' => 'outputs/' . basename($dir) . '/haskell.html'];
}

function is_text_file(string $path): bool
{
    $bytes = file_get_contents($path, false, null, 0, 4096);
    if (!is_string($bytes)) {
        return false;
    }

    return !str_contains($bytes, "\0");
}

function sample_preview_html(string $path): string
{
    if (!is_text_file($path)) {
        return '';
    }
    $text = file_get_contents($path);
    if (!is_string($text)) {
        return '';
    }
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    $lines = explode("\n", $text);
    $preview = implode("\n", array_slice($lines, 0, 80));
    if (count($lines) > 80) {
        $preview .= "\n...";
    }

    return $preview;
}

function h(string $value): string
{
    $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return preg_replace_callback('/ +(?=\r?\n|$)/', static fn (array $match): string => str_repeat('&#32;', strlen($match[0])), $escaped) ?? $escaped;
}

function rel(string $absolute, string $base): string
{
    return ltrim(str_replace($base, '', $absolute), '/');
}

function sanitize_generated_text(string $text): string
{
    $root = dirname(__DIR__);
    $text = str_replace($root, '$REPO', $text);
    $text = preg_replace('#/Users/admin[^\\s\'")<]*#', '$REPO', $text) ?? $text;
    $text = preg_replace('#/private/tmp[^\\s\'")<]*#', '$TMP', $text) ?? $text;

    return $text;
}

/**
 * @return array<string, string>
 */
function existing_showcase_samples(string $samplesDir): array
{
    if (!is_dir($samplesDir)) {
        return [];
    }

    $samples = [];
    foreach (glob($samplesDir . '/*') ?: [] as $path) {
        if (!is_file($path) || str_ends_with($path, '.download-error.txt')) {
            continue;
        }
        $bytes = file_get_contents($path);
        if (is_string($bytes)) {
            $samples[basename($path)] = $bytes;
        }
    }

    return $samples;
}

$existingSamples = existing_showcase_samples($samplesDir);

ensure_dir($siteDir);
ensure_clean_dir($samplesDir);
ensure_clean_dir($outputsDir);

$records = [];
$support = array_replace(PandocFormatRegistry::phpInputSupport(), PandocFormatRegistry::phpLocalInputSupport());
$formats = [];
foreach ($support as $format => $entry) {
    if (($entry['status'] ?? 'unsupported') !== 'unsupported') {
        $formats[] = $format;
    }
}
sort($formats);
$samples = showcase_samples();

foreach ($samples as $sample) {
    $format = (string) $sample['format'];
    $id = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $sample['id']));
    $filename = (string) $sample['filename'];
    $target = $samplesDir . '/' . $id . '-' . $filename;
    $downloadError = null;
    if (isset($sample['content'])) {
        file_put_contents($target, (string) $sample['content']);
    } elseif (isset($sample['localPath'])) {
        $localPath = $root . '/' . ltrim((string) $sample['localPath'], '/');
        if (is_file($localPath)) {
            copy($localPath, $target);
        } else {
            $downloadError = 'Unable to copy local sample ' . $sample['localPath'];
            file_put_contents($target . '.download-error.txt', $downloadError);
        }
    } elseif (isset($sample['url'])) {
        if (!download_file((string) $sample['url'], $target)) {
            if (isset($existingSamples[basename($target)])) {
                file_put_contents($target, $existingSamples[basename($target)]);
            } else {
                $downloadError = 'Unable to download ' . $sample['url'];
                file_put_contents($target . '.download-error.txt', $downloadError);
            }
        }
    }
    $sourcePath = is_file($target) ? $target : $target . '.download-error.txt';
    $outDir = $outputsDir . '/' . $id;
    ensure_dir($outDir);

    $haskell = is_file($target) ? run_haskell_pandoc($target, $format, $outDir) : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $phpHtml = is_file($target) ? write_output_from_process($outDir, 'php.html', $target, $format, 'html') : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $wpBlocks = is_file($target) ? write_output_from_process($outDir, 'wordpress-blocks.html', $target, $format, 'wordpress') : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $wpBlockCounts = (($wpBlocks['ok'] ?? false) === true && isset($wpBlocks['path']))
        ? wordpress_block_counts($siteDir . '/' . $wpBlocks['path'])
        : [];
    $preview = is_file($target) ? sample_preview_html($target) : '';

    $record = [
        'id' => $id,
        'format' => $format,
        'label' => (string) $sample['label'],
        'description' => (string) ($sample['description'] ?? ''),
        'source' => (string) $sample['source'],
        'sourceUrl' => (string) ($sample['url'] ?? ''),
        'samplePath' => rel($sourcePath, $siteDir),
        'sampleSize' => is_file($target) ? filesize($target) : 0,
        'preview' => $preview,
        'support' => $support[$format] ?? ['status' => 'partial', 'implementation' => 'unknown', 'notes' => ''],
        'haskell' => $haskell,
        'phpHtml' => $phpHtml,
        'wpBlocks' => $wpBlocks,
        'wpBlockCounts' => $wpBlockCounts,
    ];
    $record['faithfulness'] = showcase_record_faithfulness($siteDir, $record);
    $record['importQuality'] = showcase_record_import_quality($siteDir, $record);
    $records[] = $record;
}

$coveredFormats = array_values(array_unique(array_map(fn (array $record): string => $record['format'], $records)));
sort($coveredFormats);
$missingFormats = array_values(array_diff($formats, $coveredFormats));
$blockUsage = aggregate_wordpress_block_counts($records);
$conversionSummary = conversion_summary($records);
$faithfulnessSummary = showcase_faithfulness_summary($records);
$importQualitySummary = showcase_import_quality_summary($records);
$importQualitySegmentSummary = showcase_import_quality_segment_summary($records);
$importQualityGate = showcase_import_quality_threshold_gate($importQualitySegmentSummary);

file_put_contents($siteDir . '/manifest.json', json_encode([
    'generatedAt' => gmdate('c'),
    'pandocVersion' => sanitize_generated_text(trim(run_process(['pandoc', '--version'], 10)['stdout'])),
    'mediaImageMode' => 'important',
    'formats' => $formats,
    'coveredFormats' => $coveredFormats,
    'missingFormats' => $missingFormats,
    'conversionSummary' => $conversionSummary,
    'faithfulnessSummary' => $faithfulnessSummary,
    'importQualitySummary' => $importQualitySummary,
    'importQualitySegmentSummary' => $importQualitySegmentSummary,
    'importQualityGate' => $importQualityGate,
    'blockUsage' => $blockUsage,
    'records' => $records,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$css = <<<'CSS'
:root {
  color-scheme: light;
  --ink: #202124;
  --muted: #65707a;
  --line: #d8dde3;
  --paper: #ffffff;
  --wash: #f5f7f9;
  --accent: #1f6feb;
  --accent-ink: #ffffff;
  --ok: #146c43;
  --bad: #a52828;
  --warn: #8a5a00;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  margin: 0;
  font: 15px/1.55 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  color: var(--ink);
  background: var(--wash);
}
a { color: var(--accent); }
.hero {
  background: #ffffff;
  border-bottom: 1px solid var(--line);
}
.hero-inner {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 44px 0 28px;
}
.eyebrow {
  margin: 0 0 8px;
  color: var(--muted);
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0;
  font-weight: 700;
}
h1 {
  margin: 0;
  max-width: 920px;
  font-size: clamp(32px, 5vw, 58px);
  line-height: 1.03;
  letter-spacing: 0;
}
.lede {
  max-width: 860px;
  margin: 18px 0 0;
  font-size: 18px;
  color: #3f4852;
}
.stats {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 26px;
}
.stat {
  border: 1px solid var(--line);
  background: #fbfcfd;
  padding: 10px 12px;
  min-width: 150px;
}
.stat strong {
  display: block;
  font-size: 24px;
  line-height: 1.1;
}
.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
}
.hero-actions a {
  display: inline-flex;
  align-items: center;
  min-height: 36px;
  padding: 6px 12px;
  border: 1px solid var(--line);
  background: #fff;
  color: var(--ink);
  text-decoration: none;
}
.stress-samples {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 18px;
  padding: 0;
  list-style: none;
}
.stress-samples a {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding: 5px 9px;
  border: 1px solid var(--line);
  background: #fbfcfd;
  color: var(--ink);
  text-decoration: none;
  font-size: 13px;
}
.layout {
  width: min(1180px, calc(100% - 32px));
  margin: 24px auto 64px;
}
.content-page {
  width: min(1040px, calc(100% - 32px));
  margin: 28px auto 64px;
}
.content-page section {
  margin-top: 28px;
}
.content-page h2 {
  margin: 0 0 10px;
  font-size: 26px;
}
.content-page p {
  max-width: 820px;
}
.usage-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid var(--line);
}
.usage-table th,
.usage-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: top;
}
.usage-table th {
  background: #fbfcfd;
  font-size: 13px;
}
.usage-table td:first-child {
  white-space: nowrap;
}
.report-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.report-card,
.report-table {
  background: #fff;
  border: 1px solid var(--line);
}
.report-card {
  padding: 16px;
  border-radius: 8px;
}
.report-card h3 {
  margin: 0;
  font-size: 15px;
}
.report-number {
  margin: 10px 0 0;
  font-size: 32px;
  line-height: 1.1;
  font-weight: 750;
}
.report-table {
  width: 100%;
  border-collapse: collapse;
}
.report-table th,
.report-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: top;
}
.report-table th {
  background: #fbfcfd;
  font-size: 13px;
}
.report-table tr:last-child td {
  border-bottom: 0;
}
.media-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
  margin-top: 14px;
}
.media-gallery figure {
  margin: 0;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 8px;
  overflow: hidden;
}
.media-gallery a {
  display: grid;
  place-items: center;
  height: 140px;
  background: #f7f9fb;
}
.media-gallery img {
  max-width: 100%;
  max-height: 140px;
  object-fit: contain;
}
.media-gallery figcaption {
  display: grid;
  gap: 2px;
  padding: 9px 10px 10px;
  font-size: 12px;
  color: var(--muted);
}
.media-gallery figcaption strong {
  color: var(--ink);
  font-size: 13px;
  line-height: 1.25;
}
.compact-table td:first-child {
  white-space: nowrap;
}
.report-page code,
.content-page code,
.usage-table code {
  font: 12px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  background: #eef1f5;
  padding: 1px 4px;
}
.format-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 24px;
}
.format-nav a {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  padding: 5px 10px;
  border: 1px solid var(--line);
  background: #fff;
  color: var(--ink);
  text-decoration: none;
  font-size: 13px;
}
.format-section {
  margin-top: 28px;
}
.format-heading {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin: 0 0 12px;
}
.format-heading h2 {
  margin: 0;
  font-size: 28px;
}
.format-heading span {
  color: var(--muted);
}
.comparison {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
  margin-bottom: 18px;
  align-items: stretch;
}
.original,
.converted {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 8px;
  min-width: 0;
  overflow: hidden;
}
.panel-head {
  padding: 12px 14px;
  border-bottom: 1px solid var(--line);
  background: #fbfcfd;
}
.panel-head h3 {
  margin: 0;
  font-size: 16px;
  line-height: 1.25;
}
.meta {
  margin: 5px 0 0;
  color: var(--muted);
  font-size: 13px;
}
.original-body {
  padding: 14px;
}
.source-preview {
  margin: 0;
  max-height: 390px;
  overflow: auto;
  padding: 12px;
  background: #111827;
  color: #e5e7eb;
  border-radius: 6px;
  font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.download-only {
  border: 1px dashed var(--line);
  padding: 18px;
  background: #fbfcfd;
}
.converted-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  padding: 10px;
  border-bottom: 1px solid var(--line);
}
.comparison-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0;
}
.conversion-pane {
  min-width: 0;
  border-right: 1px solid var(--line);
  background: #fff;
}
.conversion-pane:last-child {
  border-right: 0;
}
.pane-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  min-height: 43px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
  background: #fbfcfd;
}
.pane-title {
  font-weight: 700;
  font-size: 13px;
}
.pane-status {
  font-size: 12px;
}
.view-source {
  appearance: none;
  border: 1px solid var(--line);
  background: #fff;
  color: var(--ink);
  min-height: 34px;
  padding: 5px 10px;
  border-radius: 6px;
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  margin-left: auto;
}
.status-ok { color: var(--ok); }
.status-fail { color: var(--bad); }
.status-warn { color: var(--warn); }
.render-frame {
  width: 100%;
  height: 520px;
  border: 0;
  background: #fff;
  display: block;
}
.error-box,
.source-box {
  margin: 0;
  height: 520px;
  overflow: auto;
  padding: 14px;
  background: #111827;
  color: #f8fafc;
  font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.source-box {
  display: none;
}
.source-mode .render-frame,
.source-mode .error-box {
  display: none;
}
.source-mode .source-box {
  display: block;
}
.note {
  padding: 12px 14px;
  border-top: 1px solid var(--line);
  background: #fbfcfd;
  color: var(--muted);
  font-size: 13px;
}
@media (max-width: 860px) {
  .comparison-grid {
    grid-template-columns: 1fr;
  }
  .conversion-pane {
    border-right: 0;
    border-bottom: 1px solid var(--line);
  }
  .conversion-pane:last-child {
    border-bottom: 0;
  }
  h1 {
    font-size: 38px;
  }
  .view-source {
    margin-left: 0;
  }
  .report-grid {
    grid-template-columns: 1fr;
  }
  .report-table {
    display: block;
    overflow-x: auto;
  }
}
CSS;

$js = <<<'JS'
document.addEventListener('click', (event) => {
  const source = event.target.closest('[data-source-toggle]');
  if (source) {
    const box = source.closest('.converted');
    const enabled = box.classList.toggle('source-mode');
    source.textContent = enabled ? 'Rendered view' : 'View source';
  }
});

const syncGroups = new Map();
let syncing = false;

function scrollState(scroller) {
  const maxTop = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
  const maxLeft = Math.max(0, scroller.scrollWidth - scroller.clientWidth);

  return {
    top: maxTop === 0 ? 0 : scroller.scrollTop / maxTop,
    left: maxLeft === 0 ? 0 : scroller.scrollLeft / maxLeft,
  };
}

function applyScrollState(scroller, state) {
  const maxTop = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
  const maxLeft = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
  scroller.scrollTop = Math.round(maxTop * state.top);
  scroller.scrollLeft = Math.round(maxLeft * state.left);
}

function wireScroller(group, scroller) {
  if (!scroller || scroller.dataset.syncWired === 'true') {
    return;
  }
  scroller.dataset.syncWired = 'true';
  if (!syncGroups.has(group)) {
    syncGroups.set(group, new Set());
  }
  syncGroups.get(group).add(scroller);
  scroller.addEventListener('scroll', () => {
    if (syncing) {
      return;
    }
    syncing = true;
    const state = scrollState(scroller);
    for (const peer of syncGroups.get(group) || []) {
      if (peer !== scroller) {
        applyScrollState(peer, state);
      }
    }
    window.requestAnimationFrame(() => {
      syncing = false;
    });
  }, { passive: true });
}

function iframeScroller(frame) {
  try {
    const doc = frame.contentDocument;
    return doc ? (doc.scrollingElement || doc.documentElement || doc.body) : null;
  } catch (error) {
    return null;
  }
}

function wireSyncPane(pane) {
  const group = pane.getAttribute('data-sync-group');
  if (!group) {
    return;
  }
  if (pane.tagName === 'IFRAME') {
    const attach = () => wireScroller(group, iframeScroller(pane));
    pane.addEventListener('load', attach);
    attach();
    return;
  }
  wireScroller(group, pane);
}

document.querySelectorAll('[data-sync-group]').forEach(wireSyncPane);
JS;

file_put_contents($siteDir . '/styles.css', $css);
file_put_contents($siteDir . '/showcase.js', $js);

$byFormat = [];
foreach ($records as $record) {
    $byFormat[$record['format']][] = $record;
}
ksort($byFormat);

$successCount = 0;
foreach ($records as $record) {
    foreach (['haskell', 'phpHtml', 'wpBlocks'] as $key) {
        if (($record[$key]['ok'] ?? false) === true) {
            $successCount++;
        }
    }
}
$totalConversions = count($records) * 3;

$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<title>Pandoc PHP Port Conversion Showcase</title><link rel="stylesheet" href="styles.css"></head><body>';
$html .= '<header class="hero"><div class="hero-inner"><p class="eyebrow">adamziel/port-libs</p><h1>Pandoc PHP port conversion showcase</h1>';
$html .= '<p class="lede">Real public documents and upstream fixtures are converted three ways: Haskell Pandoc to HTML, the local PHP port to HTML, and the local PHP port to WordPress block markup. The stress samples are intentionally messy enough to expose timeouts, memory limits, package complexity, and block coverage.</p>';
$html .= '<div class="stats">';
$html .= '<div class="stat"><strong>' . count($coveredFormats) . '</strong><span>covered input formats</span></div>';
$html .= '<div class="stat"><strong>' . count($records) . '</strong><span>source files</span></div>';
$html .= '<div class="stat"><strong>' . $successCount . '/' . $totalConversions . '</strong><span>successful conversions</span></div>';
$html .= '<div class="stat"><strong>' . gmdate('Y-m-d') . '</strong><span>generated</span></div>';
$html .= '</div><ul class="stress-samples" aria-label="Real-world stress samples">';
foreach ([
    'docx-oasis-kmip-spec' => 'OASIS KMIP DOCX',
    'epub-gutenberg-alice-illustrated' => 'Illustrated Alice EPUB',
    'markdown-pandoc-manual' => 'Pandoc manual Markdown',
    'odt-oasis-opendocument-schema' => 'OASIS OpenDocument ODT',
    'pdf-irs-w4' => 'IRS Form W-4 PDF',
    'pptx-cdc-food-safety-slides' => 'CDC PPTX',
    'xlsx-census-tax-parameter-workbook' => 'Census XLSX',
] as $id => $label) {
    $html .= '<li><a href="#' . h($id) . '">' . h($label) . '</a></li>';
}
$html .= '</ul><div class="hero-actions"><a href="conversion-report.html">Conversion report</a><a href="playground-converter.html">Convert in WordPress Playground</a><a href="block-usage.html">WordPress block usage guide</a><a href="manifest.json">Manifest JSON</a></div></div></header><main class="layout">';
$html .= '<nav class="format-nav" aria-label="Formats">';
foreach (array_keys($byFormat) as $format) {
    $html .= '<a href="#format-' . h($format) . '">' . h($format) . '</a>';
}
$html .= '</nav>';

if ($missingFormats !== []) {
    $html .= '<p class="status-warn">Missing local support entries with no sample yet: ' . h(implode(', ', $missingFormats)) . '</p>';
}

foreach ($byFormat as $format => $formatRecords) {
    $html .= '<section class="format-section" id="format-' . h($format) . '">';
    $html .= '<div class="format-heading"><h2>' . h($format) . '</h2><span>' . count($formatRecords) . ' sample' . (count($formatRecords) === 1 ? '' : 's') . '</span></div>';
    foreach ($formatRecords as $record) {
        $sourceLabel = $record['sourceUrl'] !== '' ? '<a href="' . h($record['sourceUrl']) . '">' . h($record['source']) . '</a>' : h($record['source']);
        $html .= '<article class="comparison" id="' . h($record['id']) . '">';
        $html .= '<section class="original"><div class="panel-head"><h3>' . h($record['label']) . '</h3><p class="meta">' . h((string) $record['sampleSize']) . ' bytes | ' . $sourceLabel . '</p></div><div class="original-body">';
        if ($record['preview'] !== '') {
            $html .= '<pre class="source-preview">' . h($record['preview']) . '</pre>';
            $html .= '<p class="meta"><a href="' . h($record['samplePath']) . '">Download original file</a></p>';
        } else {
            $html .= '<div class="download-only"><p>No local thumbnail is available for this binary or packaged file.</p><p><a href="' . h($record['samplePath']) . '">Download original file</a></p></div>';
        }
        if ($record['description'] !== '') {
            $html .= '<p class="meta">' . h($record['description']) . '</p>';
        }
        $html .= '</div></section>';
        $html .= '<section class="converted" data-sample="' . h($record['id']) . '">';
        $html .= '<div class="converted-toolbar"><span class="meta">Converted representations</span><button class="view-source" type="button" data-source-toggle>View source</button></div>';
        $html .= '<div class="comparison-grid">';
        $tabs = [
            'wpBlocks' => ['label' => 'PHP WordPress blocks', 'short' => 'WP blocks'],
            'phpHtml' => ['label' => 'PHP HTML', 'short' => 'PHP HTML'],
            'haskell' => ['label' => 'Haskell Pandoc HTML', 'short' => 'Haskell HTML'],
        ];
        foreach ($tabs as $key => $tabInfo) {
            $panelId = $record['id'] . '-' . $key;
            $result = $record[$key];
            $ok = ($result['ok'] ?? false) === true;
            $state = $ok ? 'status-ok' : 'status-fail';
            $html .= '<section class="conversion-pane" id="' . h($panelId) . '">';
            $html .= '<div class="pane-head"><span class="pane-title">' . h($tabInfo['short']) . '</span><span class="pane-status ' . $state . '">' . ($ok ? 'ok' : 'failed') . '</span></div>';
            if (($result['ok'] ?? false) === true) {
                $path = (string) $result['path'];
                $source = file_get_contents($siteDir . '/' . $path);
                $html .= '<iframe class="render-frame" data-sync-group="' . h($record['id']) . '" title="' . h($record['label'] . ' ' . $tabInfo['label']) . '" src="' . h($path) . '"></iframe>';
                $html .= '<pre class="source-box" data-sync-group="' . h($record['id']) . '">' . h(is_string($source) ? $source : '') . '</pre>';
            } else {
                $error = (string) ($result['error'] ?? 'Conversion failed.');
                $errorPath = isset($result['path']) ? $siteDir . '/' . $result['path'] : '';
                $source = is_file($errorPath) ? file_get_contents($errorPath) : $error;
                $html .= '<pre class="error-box" data-sync-group="' . h($record['id']) . '">' . h($error) . '</pre>';
                $html .= '<pre class="source-box" data-sync-group="' . h($record['id']) . '">' . h(is_string($source) ? $source : $error) . '</pre>';
            }
            $html .= '</section>';
        }
        $notes = is_array($record['support']) ? (string) ($record['support']['notes'] ?? '') : '';
        $html .= '</div><div class="note">' . h($notes) . '</div></section>';
        $html .= '</article>';
    }
    $html .= '</section>';
}
$html .= '</main><script src="showcase.js"></script></body></html>';

file_put_contents($siteDir . '/index.html', $html);
write_conversion_report(
    $siteDir,
    $records,
    $coveredFormats,
    $missingFormats,
    $conversionSummary,
    $blockUsage,
    $faithfulnessSummary,
    $importQualitySummary,
    $importQualityGate
);

$knownBlockRows = [
    'group' => [
        'name' => 'core/group',
        'used' => 'Pandoc Div containers, metadata review wrappers, mixed figures, definition-list wrappers, list headers, and generated footnotes.',
        'markup' => '<!-- wp:group --> with a <div class="wp-block-group ..."> wrapper and nested core blocks.',
        'fallback' => 'Used when a source structure is a container rather than a single paragraph/list/table/image block.',
    ],
    'paragraph' => [
        'name' => 'core/paragraph',
        'used' => 'Pandoc paragraphs, plain text blocks, generated figure captions, definition terms, and inline-only fallback content.',
        'markup' => '<!-- wp:paragraph --> with <p> content.',
        'fallback' => 'Inline formats stay inside the paragraph as WordPress-rich-text-compatible HTML tags.',
    ],
    'heading' => [
        'name' => 'core/heading',
        'used' => 'Pandoc headings and headings nested inside group-rendered containers.',
        'markup' => '<!-- wp:heading {"level":N} --> with <hN> content.',
        'fallback' => 'Heading levels are clamped by the reader before this writer sees them.',
    ],
    'list' => [
        'name' => 'core/list',
        'used' => 'Bullet lists, ordered lists, task lists, metadata entries, definition values, and footnote lists.',
        'markup' => '<!-- wp:list --> or <!-- wp:list {"ordered":true} --> with <ul>/<ol>.',
        'fallback' => 'Definition lists use editable list blocks because WordPress core has no definition-list block.',
    ],
    'table' => [
        'name' => 'core/table',
        'used' => 'Pandoc tables, including captions, column metadata, row spans, and cell attributes where available.',
        'markup' => '<!-- wp:table --> with a <figure class="wp-block-table"><table>...</table></figure> body.',
        'fallback' => 'Table internals remain HTML because that is how the core table block stores rows and cells.',
    ],
    'image' => [
        'name' => 'core/image',
        'used' => 'Image-only paragraphs and figures where the figure can be represented as one image plus optional caption.',
        'markup' => '<!-- wp:image --> with <figure class="wp-block-image">.',
        'fallback' => 'Figures with mixed child blocks become core/group so their children remain editable.',
    ],
    'quote' => [
        'name' => 'core/quote',
        'used' => 'Pandoc block quotes.',
        'markup' => '<!-- wp:quote --> with <blockquote class="wp-block-quote">.',
        'fallback' => 'Nested content is serialized as standard HTML inside the quote body, matching core quote storage.',
    ],
    'verse' => [
        'name' => 'core/verse',
        'used' => 'Pandoc line blocks, poetry-style text, and line-sensitive extracted content.',
        'markup' => '<!-- wp:verse --> with <pre class="wp-block-verse">.',
        'fallback' => 'Used instead of a paragraph with manual <br> tags when the source is a Pandoc LineBlock.',
    ],
    'code' => [
        'name' => 'core/code',
        'used' => 'Code blocks, raw TeX blocks, and raw non-HTML format blocks.',
        'markup' => '<!-- wp:code --> with <pre class="wp-block-code"><code>.',
        'fallback' => 'Highlighted code can become Custom HTML because the highlighter emits styled HTML.',
    ],
    'separator' => [
        'name' => 'core/separator',
        'used' => 'Pandoc horizontal rules.',
        'markup' => '<!-- wp:separator --> with <hr class="wp-block-separator ..."/>.',
        'fallback' => 'No fallback is normally needed.',
    ],
    'html' => [
        'name' => 'core/html',
        'used' => 'Explicit source raw HTML, HTML raw-format blocks, highlighted code output, and last-resort unknown block fallback.',
        'markup' => '<!-- wp:html --> with the original or generated HTML.',
        'fallback' => 'This is intentionally kept for source HTML where converting to another block would change meaning.',
    ],
    'syntaxhighlighter/code' => [
        'name' => 'syntaxhighlighter/code',
        'used' => 'Optional SyntaxHighlighter plugin output when that writer option is enabled.',
        'markup' => '<!-- wp:syntaxhighlighter/code -->.',
        'fallback' => 'The showcase does not enable this option by default.',
    ],
];

$guideRows = [];
$seenBlocks = [];
foreach ($knownBlockRows as $marker => $info) {
    $count = (int) ($blockUsage['totals'][$marker] ?? 0);
    $guideRows[] = ['marker' => $marker, 'count' => $count] + $info;
    $seenBlocks[$marker] = true;
}
foreach ($blockUsage['totals'] as $marker => $count) {
    if (isset($seenBlocks[$marker])) {
        continue;
    }
    $guideRows[] = [
        'marker' => (string) $marker,
        'name' => (string) $marker,
        'used' => 'Block marker found in generated WordPress output.',
        'markup' => '<!-- wp:' . (string) $marker . ' -->.',
        'fallback' => 'Not part of the hand-authored guide table; inspect the sample source for context.',
        'count' => (int) $count,
    ];
}

$guide = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
$guide .= '<title>WordPress Block Usage Guide</title><link rel="stylesheet" href="styles.css"></head><body>';
$guide .= '<header class="hero"><div class="hero-inner"><p class="eyebrow">Pandoc PHP port</p><h1>WordPress block usage guide</h1>';
$guide .= '<p class="lede">The WordPress writer prefers core blocks for structures WordPress can edit directly. It keeps Custom HTML for explicit raw HTML and narrow cases where changing the block type would lose source meaning.</p>';
$guide .= '<div class="stats">';
$guide .= '<div class="stat"><strong>' . array_sum($blockUsage['totals']) . '</strong><span>block instances</span></div>';
$guide .= '<div class="stat"><strong>' . count($blockUsage['totals']) . '</strong><span>block types emitted</span></div>';
$guide .= '<div class="stat"><strong>' . (int) $blockUsage['sampleCount'] . '</strong><span>successful WP samples counted</span></div>';
$guide .= '</div><div class="hero-actions"><a href="index.html">Showcase</a><a href="manifest.json">Manifest JSON</a></div></div></header>';
$guide .= '<main class="content-page">';
$guide .= '<section><h2>Block Selection</h2><table class="usage-table"><thead><tr><th>Block</th><th>Count</th><th>Used when</th><th>Serialized as</th><th>Fallback rule</th></tr></thead><tbody>';
foreach ($guideRows as $row) {
    $guide .= '<tr><td><code>' . h((string) $row['name']) . '</code><br><span class="meta">wp:' . h((string) $row['marker']) . '</span></td>'
        . '<td>' . (int) $row['count'] . '</td>'
        . '<td>' . h((string) $row['used']) . '</td>'
        . '<td>' . h((string) $row['markup']) . '</td>'
        . '<td>' . h((string) $row['fallback']) . '</td></tr>';
}
$guide .= '</tbody></table></section>';
$guide .= '<section><h2>Inline Formats</h2><p>Inline Pandoc nodes stay inside editable core blocks whenever the surrounding block is editable. WordPress stores these formats as HTML inside rich text fields.</p>';
$guide .= '<table class="usage-table"><thead><tr><th>Pandoc inline</th><th>WordPress-rich-text markup</th></tr></thead><tbody>';
foreach ([
    'Strong' => '<strong>...</strong>',
    'Emph' => '<em>...</em>',
    'Code' => '<code>...</code>',
    'Link' => '<a href="...">...</a>',
    'Strikeout' => '<del>...</del>',
    'Underline' => '<u>...</u>',
    'Superscript / Subscript' => '<sup>...</sup> / <sub>...</sub>',
    'LineBreak' => '<br/>',
    'SmallCaps' => '<span style="font-variant:small-caps">...</span>',
    'Note' => 'An inline backlink reference plus a generated footnotes group/list at the end.',
] as $inline => $markup) {
    $guide .= '<tr><td>' . h($inline) . '</td><td><code>' . h($markup) . '</code></td></tr>';
}
$guide .= '</tbody></table></section>';
$guide .= '<section><h2>Custom HTML Boundaries</h2><p><code>core/html</code> is still used deliberately for raw HTML input, raw HTML format blocks, and highlighter output. Those are source-authored or generated HTML fragments where converting to another block would be less faithful.</p></section>';
$guide .= '</main></body></html>';

file_put_contents($siteDir . '/block-usage.html', $guide);

echo 'Generated pandoc-showcase with ' . count($records) . ' samples across ' . count($coveredFormats) . " formats.\n";
if ($missingFormats !== []) {
    echo 'Missing sample formats: ' . implode(', ', $missingFormats) . "\n";
}
