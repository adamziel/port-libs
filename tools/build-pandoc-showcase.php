<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\ShowcaseHaskellReferenceTimeout;

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
    $pdfRasterManifest = $argv[8] ?? '';
    if ($path === '' || $format === '' || $to === '') {
        fwrite(STDERR, "Usage: php tools/build-pandoc-showcase.php --convert-local <path> <from> <to> [media-output-dir media-destination media-manifest pdf-raster-manifest]\n");
        exit(2);
    }
    $options = showcase_converter_options($format, $to);
    if ($mediaOutputDir !== '' && $mediaDestination !== '' && $mediaManifest !== '') {
        $options['extractMedia'] = [
            'destination' => $mediaDestination,
            'outputDirectory' => $mediaOutputDir,
            'imageMode' => 'important',
        ];
        $pdfRasterImages = showcase_pdf_raster_images_from_manifest($pdfRasterManifest);
        if ($pdfRasterImages !== []) {
            $options['extractMedia']['pdfRasterImages'] = $pdfRasterImages;
        }
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
$refreshSources = in_array('--refresh-sources', $argv, true);

const SHOWCASE_EXAMPLES_AUTOMATIC_MAX_BYTES = 250000;
// Keep the static viewer in step with the WordPress importer's bounded
// browser-rendered Form-XObject handoff. A PDF can use Form XObjects for
// diagrams, charts, and other complete vector scenes; they are not ordinary
// image streams that the media extractor can write by itself.
const SHOWCASE_PDF_FORM_RENDER_MAX_REQUESTS = 48;

if (($argv[1] ?? '') === '--build-examples-page') {
    $manifestPath = $siteDir . '/manifest.json';
    $manifest = is_file($manifestPath)
        ? json_decode((string) file_get_contents($manifestPath), true)
        : null;
    if (!is_array($manifest) || !is_array($manifest['records'] ?? null)) {
        fwrite(STDERR, "Unable to read generated showcase records from {$manifestPath}.\n");
        exit(1);
    }

    showcase_write_examples_page(
        $siteDir,
        $manifest['records'],
        (string) ($manifest['generatedAt'] ?? gmdate('c'))
    );
    echo "Generated lightweight pandoc-showcase examples page.\n";
    exit(0);
}

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

function copy_local_resource_tree(string $source, string $destination): bool
{
    if (!is_dir($source)) {
        return false;
    }
    ensure_clean_dir($destination);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            ensure_dir($target);
            continue;
        }
        ensure_dir(dirname($target));
        if (!copy($item->getPathname(), $target)) {
            return false;
        }
    }

    return true;
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

/** @return list<array<string, mixed>> */
function pdf_layout_corpus_samples(): array
{
    $path = __DIR__ . '/pdf-layout-corpus-manifest.json';
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Unable to read PDF layout corpus manifest at {$path}.");
    }

    $samples = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach (['id', 'label', 'url', 'filename', 'source'] as $required) {
            if (!is_string($entry[$required] ?? null) || $entry[$required] === '') {
                throw new RuntimeException("PDF layout corpus entry is missing {$required}.");
            }
        }
        $samples[] = remote_sample(
            'pdf-layout-' . $entry['id'],
            'pdf',
            $entry['url'],
            $entry['filename'],
            $entry['label'],
            $entry['source'],
            (string) ($entry['notes'] ?? '')
        );
    }

    return $samples;
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

    $latexTable = <<<'TEX'
Before table.

\begin{table}
\caption{Conversion counts}
\begin{tabular}{lr}
Name & Value \\
HTML & 1 \\
WordPress & 2 \\
\end{tabular}
\end{table}

After table.
TEX;

    $tsv = "name\tformat\tstatus\nDocument\tDOCX\tpartial\nNotebook\tIPYNB\tpartial\nSlides\tPPTX\tpartial\n";

    $dokuWikiShowcase = <<<'DOKU'
====== DokuWiki import packet ======

This paragraph has **strong text**, //emphasis//, __underline__, ''literal code'', and [[https://example.org|an external link]].

{{data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JJR0AAAAASUVORK5CYII=?240x120|Hosted import diagram}}

  * Review the converted page
    * Confirm the nested item
  - Publish the result

^ Source ^ Status ^
| DokuWiki | Imported |
| WordPress | Blocks |

<code php>
echo 'portable import';
</code>
DOKU;

    $mdocShowcase = <<<'MDOC'
.Dd July 9, 2026
.Dt IMPORTCTL 1
.Os
.Sh NAME
.Nm importctl
.Nd import a document into a WordPress page
.Sh SYNOPSIS
.Nm
.Op Fl f Ar format
.Op Fl n
.Ar input
.Sh DESCRIPTION
The
.Nm importctl
command converts
.Ar input
into portable blocks and writes media beside the document.
.Bl -bullet
.It
Use
.Fl f
to select a source format.
.It
Use
.Fl n
to omit images.
.El
.Bl -tag -width Ds
.It Cm verify
Check the generated HTML and block markup.
.It Pa /tmp/imports
Default directory for conversion artifacts.
.El
MDOC;

    $rstShowcase = <<<'RST'
reStructuredText import packet
==============================

Intro with *emphasis*, **strong text**, ``literal code``, and `a link <https://example.org>`_.

- First source note
- Second source note

1. Convert the document
2. Review the block markup

:Owner: Content operations
:Status: Ready

.. image:: data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JJR0AAAAASUVORK5CYII=
   :alt: Hosted RST diagram
   :width: 240px

.. csv-table:: Format coverage
   :header: "Format", "Output"

   "RST", "WordPress blocks"
   "HTML", "Rendered page"

.. code:: php

   echo "import";
RST;

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
        'dokuwiki' => [
            inline_sample('dokuwiki-import-packet', 'dokuwiki', 'dokuwiki-import-packet.dokuwiki', 'DokuWiki import packet', $dokuWikiShowcase, 'Feature-rich DokuWiki input with inline styles, links, an embedded image, nested lists, a table, and a code block.'),
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
            [
                'id' => 'epub-gutenberg-ulysses',
                'format' => 'epub',
                'label' => 'Ulysses full-book EPUB',
                'description' => 'A real Project Gutenberg book-scale EPUB with 31 XHTML spine documents, a long boilerplate section, a contents table, and dense literary prose.',
                'url' => 'https://www.gutenberg.org/ebooks/4300.epub.noimages',
                'source' => 'Project Gutenberg Ulysses EPUB without images',
                'filename' => 'ulysses.epub',
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
            [
                'id' => 'latex-native-academic-article',
                'format' => 'latex',
                'label' => 'Native LaTeX academic article',
                'description' => 'Checked-in academic article with metadata, abstract, structure, math, lists, figure media, booktabs table, cross-references, local include, and BibTeX citations.',
                'localPath' => 'lanes/pandoc/fixtures/latex-reader/academic-article.tex',
                'localResourceRoot' => 'lanes/pandoc/fixtures/latex-reader',
                'source' => 'Checked-in port-libs LaTeX reader corpus with companion TeX, BibTeX, and SVG assets.',
                'filename' => 'academic-article.tex',
            ],
            inline_sample('latex-table', 'latex', 'latex-table.tex', 'LaTeX table import packet', $latexTable, 'Inline LaTeX fragment with captioned tabular data.', 'Valid LaTeX table fragment exercising paragraph, table, caption, alignment, and WordPress table-block conversion.'),
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
        'mdoc' => [
            inline_sample('mdoc-importctl-manual', 'mdoc', 'importctl.1', 'mdoc importctl manual', $mdocShowcase, 'Feature-rich BSD mdoc manual with metadata, name/description, synopsis, callable inline macros, bullet items, and tagged options.'),
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
        'rst' => [
            inline_sample('rst-import-packet', 'rst', 'rst-import-packet.rst', 'reStructuredText import packet', $rstShowcase, 'Feature-rich reStructuredText input with inlines, bullet and ordered lists, field lists, an embedded image, a CSV table directive, and a code directive.'),
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
            ...pdf_layout_corpus_samples(),
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
                'description' => 'Untagged two-column PDF from Mozilla pdf.js tests, with dense prose, diagrams, code listings, and figures that exercise reading-order reconstruction.',
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
            [
                'id' => 'pdf-quickbooks-invoice-template',
                'format' => 'pdf',
                'label' => 'QuickBooks invoice template PDF',
                'description' => 'Public invoice template with explanatory prose, shaded table headers, line items, totals, and two editable invoice layouts.',
                'url' => 'https://quickbooks.intuit.com/oidam/intuit/sbseg/en_au/quickbooks-online/web/content/QuickBooks-Invoice-Template-PDF.pdf',
                'source' => 'Intuit QuickBooks Invoice Template PDF',
                'filename' => 'quickbooks-invoice-template.pdf',
            ],
            [
                'id' => 'pdf-tabula-spreadsheet-no-frame',
                'format' => 'pdf',
                'label' => 'Ruling-free crop table PDF',
                'description' => 'Public Tabula fixture with a data table inferred from aligned text rather than a boxed grid, followed by explanatory prose.',
                'url' => 'https://raw.githubusercontent.com/tabulapdf/tabula-java/master/src/test/resources/technology/tabula/spreadsheet_no_bounding_frame.pdf',
                'source' => 'tabulapdf/tabula-java spreadsheet_no_bounding_frame.pdf',
                'filename' => 'crop-table-no-frame.pdf',
            ],
            [
                'id' => 'pdf-tabula-multicolumn',
                'format' => 'pdf',
                'label' => 'Multi-column numeric table PDF',
                'description' => 'Public Tabula fixture with six numeric columns arranged as two adjacent visual groups, exercising coordinate-based table grouping.',
                'url' => 'https://raw.githubusercontent.com/tabulapdf/tabula-java/master/src/test/resources/technology/tabula/MultiColumn.pdf',
                'source' => 'tabulapdf/tabula-java MultiColumn.pdf',
                'filename' => 'multi-column.pdf',
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
    ensure_dir(dirname($target));
    $temporary = tempnam(dirname($target), '.' . basename($target) . '.download-');
    if ($temporary === false) {
        return false;
    }
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
        $temporary,
        $url,
    ];
    try {
        $result = run_process($cmd, 75);
        if ($result['exitCode'] !== 0 || !is_file($temporary) || filesize($temporary) <= 0) {
            return false;
        }

        return rename($temporary, $target);
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
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
        $readerOptions['maxTextBytes'] = PHP_INT_MAX;
        $readerOptions['pdfFastTextOnly'] = false;
        $readerOptions['pdfGeometryTables'] = true;
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

/**
 * Build the bounded browser-render plan consumed by the lightweight static
 * example viewer. PDF.js paints each requested Form XObject as a clipped page
 * crop, preserving vectors, nested Forms, clipping, and compositing that a
 * stream-level image extractor cannot reconstruct.
 *
 * @return array{ok:bool,path:string,bytes:int,count:int,error?:string}|null
 */
function showcase_pdf_form_render_plan(string $sourcePath, string $format, string $samplePath, string $outDir): ?array
{
    if (PandocConverter::canonicalInputFormat($format) !== 'pdf') {
        return null;
    }

    try {
        $options = showcase_converter_options($format, 'wordpress');
        $readerOptions = is_array($options['readerOptions'] ?? null) ? $options['readerOptions'] : [];
        $readerOptions['pdfCollectFormXObjectPlacements'] = true;
        $document = PandocConverter::readFile($sourcePath, $format, $readerOptions);
        $metadata = $document->attr('meta', []);
        $placements = is_array($metadata) && is_array($metadata['pdfFormXObjectPlacements'] ?? null)
            ? $metadata['pdfFormXObjectPlacements']
            : [];
        $requests = [];
        foreach ($placements as $placement) {
            if (!is_array($placement)
                || ($placement['visible'] ?? false) !== true
                || ($placement['placementEligible'] ?? false) !== true) {
                continue;
            }
            $bbox = showcase_pdf_form_render_bbox($placement['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }
            $width = $bbox['x2'] - $bbox['x1'];
            $height = $bbox['y2'] - $bbox['y1'];
            // Small decorative forms do not belong in an article preview.
            // This matches the server-side browser-render request threshold.
            if ($width < 12.0 || $height < 12.0 || $width > 10000.0 || $height > 10000.0) {
                continue;
            }
            $formId = (string) ($placement['id'] ?? 'form');
            $page = max(1, (int) ($placement['page'] ?? 1));
            $requests[] = [
                'id' => 'form-' . substr(hash('sha256', $samplePath . "\0" . $formId), 0, 28),
                'path' => $samplePath,
                'page' => $page,
                'bbox' => $bbox,
                'formId' => $formId,
                'object' => (int) ($placement['object'] ?? 0),
                'paintOrder' => (int) ($placement['paintOrder'] ?? 0),
                'precedingText' => is_string($placement['precedingText'] ?? null) ? $placement['precedingText'] : null,
                'followingText' => is_string($placement['followingText'] ?? null) ? $placement['followingText'] : null,
                'label' => 'PDF figure on page ' . $page,
            ];
            if (count($requests) >= SHOWCASE_PDF_FORM_RENDER_MAX_REQUESTS) {
                break;
            }
        }

        if ($requests === []) {
            return null;
        }

        $filename = 'pdf-form-renders.json';
        $absolutePath = $outDir . '/' . $filename;
        $encoded = json_encode([
            'version' => 1,
            'samplePath' => $samplePath,
            'requests' => $requests,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($absolutePath, $encoded . "\n") === false) {
            throw new RuntimeException('Unable to write PDF Form render plan.');
        }

        return [
            'ok' => true,
            'path' => 'outputs/' . basename($outDir) . '/' . $filename,
            'bytes' => (int) filesize($absolutePath),
            'count' => count($requests),
        ];
    } catch (Throwable $error) {
        return [
            'ok' => false,
            'path' => '',
            'bytes' => 0,
            'count' => 0,
            'error' => sanitize_generated_text($error->getMessage()),
        ];
    }
}

/**
 * @return array{x1:float,y1:float,x2:float,y2:float}|null
 */
function showcase_pdf_form_render_bbox(mixed $bbox): ?array
{
    if (!is_array($bbox)) {
        return null;
    }
    foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
        if (!is_numeric($bbox[$coordinate] ?? null) || !is_finite((float) $bbox[$coordinate])) {
            return null;
        }
    }
    $x1 = (float) $bbox['x1'];
    $y1 = (float) $bbox['y1'];
    $x2 = (float) $bbox['x2'];
    $y2 = (float) $bbox['y2'];
    if ($x2 <= $x1 || $y2 <= $y1) {
        return null;
    }

    return compact('x1', 'y1', 'x2', 'y2');
}

/**
 * Count source raw HTML blocks that deliberately become WordPress Custom HTML
 * blocks. This distinguishes source preservation from an unsupported-block
 * fallback without tying the rule to any individual sample.
 */
function showcase_source_raw_html_block_count(string $sourcePath, string $format): ?int
{
    try {
        $options = showcase_converter_options($format, 'wordpress');
        $readerOptions = is_array($options['readerOptions'] ?? null) ? $options['readerOptions'] : [];
        $document = PandocConverter::readFile($sourcePath, $format, $readerOptions);

        return showcase_count_source_raw_html_blocks($document);
    } catch (Throwable) {
        return null;
    }
}

function showcase_count_source_raw_html_blocks(\PortLibs\Pandoc\AstNode $node): int
{
    $count = $node->type === 'raw_html' ? 1 : 0;
    if ($node->type === 'raw_block') {
        $format = strtolower((string) $node->attr('format', ''));
        if (\PortLibs\Pandoc\MarkdownFormatProfile::rawFamily($format) === 'html') {
            $count++;
        }
    }

    foreach ($node->children as $child) {
        $count += showcase_count_source_raw_html_blocks($child);
    }

    return $count;
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
 * Keep WordPress block output as the raw Gutenberg fragment that the parser
 * round-trips, while giving the lightweight browser a standalone, readable
 * document to load in its iframe.
 */
function wrap_wordpress_block_preview_document(string $body, string $title): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; img-src &#39;self&#39; data: https: http:; media-src &#39;self&#39; data: https: http:; style-src &#39;unsafe-inline&#39;; font-src &#39;self&#39; data:; connect-src &#39;none&#39;; object-src &#39;none&#39;; frame-src &#39;none&#39;; form-action &#39;none&#39;; base-uri &#39;none&#39;">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>'
        . '<style>'
        . 'html{line-height:1.5;font-family:Georgia,"Times New Roman",serif;font-size:18px;color:#1f2933;background:#fdfdfd;}'
        . 'body{margin:0 auto;max-width:46em;padding:2.5em 1.25em;overflow-wrap:break-word;}'
        . 'h1,h2,h3,h4,h5,h6{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.25;margin:1.4em 0 .45em;}'
        . 'h1{font-size:2em;}h2{font-size:1.55em;}h3{font-size:1.25em;}p{margin:1em 0;}'
        . 'a{color:#1f6feb;}img,svg,video{max-width:100%;height:auto;}.wp-block-image{margin:1.25em 0;}.wp-block-image img{display:block;}'
        . 'pre,code,kbd,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.9em;}'
        . 'code{background:#eef1f5;padding:.1em .25em;border-radius:3px;}.wp-block-code,.wp-block-verse,pre{overflow:auto;background:#f6f8fa;border:1px solid #d8dde3;border-radius:6px;padding:1em;}.wp-block-code code,pre code{background:transparent;padding:0;}'
        . '.wp-block-quote,blockquote{margin:1.25em 0;padding-left:1em;border-left:4px solid #d8dde3;color:#4b5563;}'
        . '.wp-block-table{max-width:100%;margin:1.25em 0;overflow-x:auto;}.wp-block-table table,table{width:100%;border-collapse:collapse;font-size:.92em;}.wp-block-table th,.wp-block-table td,th,td{border:1px solid #d8dde3;padding:.35em .55em;vertical-align:top;}.wp-block-table th,th{background:#f6f8fa;}'
        . '.wp-block-group{margin:1.25em 0;}.wp-block-separator,hr{border:0;border-top:1px solid #d8dde3;margin:2em 0;}.wp-element-caption,figcaption{margin-top:.45em;color:#4b5563;font-size:.9em;}'
        . 'ul,ol{padding-left:1.5em;}li+li{margin-top:.25em;}math{font-family:math,serif;}math[display="block"]{display:block;margin:1em 0;overflow-x:auto;}'
        . '</style></head><body>' . $body . '</body></html>';
}

/**
 * @param list<string> $cmd
 * @return array{exitCode:int, stdout:string, stderr:string}
 */
function run_process(array $cmd, int $timeoutSeconds = 0, ?string $workingDirectory = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptors, $pipes, $workingDirectory);
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
    global $root;

    $timeout = PandocConverter::canonicalInputFormat($from) === 'pdf' || (is_file($sourcePath) && filesize($sourcePath) > 262144) ? 75 : 25;
    $mediaDir = $dir . '/media';
    $manifestPath = tempnam(sys_get_temp_dir(), 'pandoc-media-manifest-');
    $pdfRasterManifest = '';
    if (!is_string($manifestPath)) {
        $manifestPath = $dir . '/' . $name . '.media.json';
    }
    if (PandocConverter::canonicalInputFormat($from) === 'pdf') {
        $candidate = tempnam(sys_get_temp_dir(), 'pandoc-pdf-raster-manifest-');
        if (is_string($candidate)) {
            $rasterResult = run_process([
                'node',
                $root . '/tools/decode-pdf-raster-media.mjs',
                '--input',
                $sourcePath,
                '--output',
                $candidate,
                '--image-mode',
                'important',
            ], $timeout);
            if ($rasterResult['exitCode'] === 0 && is_file($candidate) && filesize($candidate) > 0) {
                $pdfRasterManifest = $candidate;
            } elseif (is_file($candidate)) {
                unlink($candidate);
            }
        }
    }
    $result = run_process([PHP_BINARY, __FILE__, '--convert-local', $sourcePath, $from, $to, $mediaDir, 'media', $manifestPath, $pdfRasterManifest], $timeout);
    if ($result['exitCode'] === 0) {
        $stdout = $to === 'html'
            ? wrap_local_html_document($result['stdout'], 'PHP Pandoc HTML output')
            : $result['stdout'];
        file_put_contents($dir . '/' . $name, $stdout);

        $manifest = read_media_manifest($manifestPath, basename($dir));
        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }
        if ($pdfRasterManifest !== '' && is_file($pdfRasterManifest)) {
            unlink($pdfRasterManifest);
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
    if ($pdfRasterManifest !== '' && is_file($pdfRasterManifest)) {
        unlink($pdfRasterManifest);
    }
    file_put_contents($dir . '/' . $name . '.error.txt', $message);

    return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/' . $name . '.error.txt'];
}

/**
 * @return list<array{object:string,contents:string,mimeType:string,width:int,height:int}>
 */
function showcase_pdf_raster_images_from_manifest(string $path): array
{
    if ($path === '' || !is_file($path)) {
        return [];
    }
    $manifest = json_decode((string) file_get_contents($path), true);
    if (!is_array($manifest) || !is_array($manifest['rasters'] ?? null)) {
        return [];
    }

    $rasters = [];
    foreach ($manifest['rasters'] as $raster) {
        if (!is_array($raster)) {
            continue;
        }
        $object = (string) ($raster['object'] ?? '');
        $contents = base64_decode((string) ($raster['bytes'] ?? ''), true);
        $mimeType = strtolower(trim((string) ($raster['mimeType'] ?? '')));
        $width = $raster['width'] ?? null;
        $height = $raster['height'] ?? null;
        if (preg_match('/^\d+$/', $object) !== 1 || !is_string($contents) || $contents === ''
            || !in_array($mimeType, ['image/png', 'image/avif'], true) || !is_numeric($width) || !is_numeric($height)) {
            continue;
        }
        $rasters[] = [
            'object' => $object,
            'contents' => $contents,
            'mimeType' => $mimeType,
            'width' => (int) $width,
            'height' => (int) $height,
        ];
    }

    return $rasters;
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
 * Importers sometimes preserve source-level metadata and generated semantic
 * structures that fragment-mode reference HTML intentionally omits. Keep
 * those structures auditable without treating their extra visible text as a
 * body-content mismatch.
 *
 * @return array{format:string,metadata:array<string,list<string>>,structures:list<string>,comparisonExclusionClasses:list<string>}|null
 */
function showcase_source_import_semantics(string $sourcePath, string $format): ?array
{
    if (PandocConverter::canonicalInputFormat($format) !== 'latex') {
        return null;
    }

    try {
        $document = PandocConverter::readFile($sourcePath, $format);
    } catch (Throwable) {
        return null;
    }
    $meta = $document->attr('meta', []);
    $meta = is_array($meta) ? $meta : [];
    $latex = $document->attr('latex', []);
    $latex = is_array($latex) ? $latex : [];
    $metadata = [];
    foreach (['title', 'date', 'abstract'] as $name) {
        $value = trim((string) ($meta[$name] ?? ''));
        if ($value !== '') {
            $metadata[$name] = [$value];
        }
    }
    foreach (['author', 'affiliations', 'authorNotes', 'keywords'] as $name) {
        $values = is_array($meta[$name] ?? null) ? $meta[$name] : [];
        $values = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            $values
        ), static fn (string $value): bool => $value !== ''));
        if ($values !== []) {
            $metadata[$name] = $values;
        }
    }

    $structures = [];
    foreach (['latex-title-block', 'latex-abstract', 'latex-table-of-contents'] as $class) {
        if (showcase_ast_has_class($document, $class)) {
            $structures[] = $class;
        }
    }
    if (($latex['bibliographyResolved'] ?? false) === true) {
        $structures[] = 'pandoc-csl-bibliography';
    }

    return [
        'format' => 'latex',
        'metadata' => $metadata,
        'structures' => $structures,
        'comparisonExclusionClasses' => $structures,
    ];
}

function showcase_ast_has_class(AstNode $node, string $class): bool
{
    $classes = $node->attr('classes', []);
    if (is_array($classes) && in_array($class, $classes, true)) {
        return true;
    }
    foreach ($node->children as $child) {
        if (showcase_ast_has_class($child, $class)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<string, mixed>
 */
function showcase_record_faithfulness(string $siteDir, array $record): array
{
    // Citeproc bibliography output is a semantic reference rendering, while
    // the PHP port deliberately emits editable definition-list blocks. Compare
    // that family through its own reference-aware contract below.
    if (($record['haskell']['renderedWithCiteproc'] ?? false) === true) {
        return ['baseline' => null, 'comparisons' => []];
    }

    $externalReference = is_array($record['externalReference'] ?? null) ? $record['externalReference'] : [];
    $requiresExternalReference = in_array(
        PandocConverter::canonicalInputFormat((string) ($record['format'] ?? '')),
        ['doc', 'pdf', 'xml'],
        true
    );
    $baselineKey = (($record['haskell']['ok'] ?? false) === true)
        ? 'haskell'
        : (($externalReference['ok'] ?? false) === true ? 'externalReference' : ($requiresExternalReference ? '' : ((($record['phpHtml']['ok'] ?? false) === true) ? 'phpHtml' : '')));
    if ($baselineKey === '') {
        return ['baseline' => null, 'comparisons' => []];
    }
    $pdfKitTextGeometryReference = $baselineKey === 'externalReference'
        && ($externalReference['kind'] ?? null) === 'macos-pdfkit-text-geometry';
    $baselinePath = (string) ($record[$baselineKey]['path'] ?? '');
    $baselineText = showcase_output_text($siteDir, $baselinePath);
    $baselineVisual = showcase_output_visual_signature($siteDir, $baselinePath);
    if ($baselineText === '') {
        return ['baseline' => null, 'comparisons' => []];
    }

    $comparisons = [];
    $semantic = is_array($record['importSemantics'] ?? null) ? $record['importSemantics'] : [];
    $comparisonExclusionClasses = is_array($semantic['comparisonExclusionClasses'] ?? null)
        ? array_values(array_filter($semantic['comparisonExclusionClasses'], 'is_string'))
        : [];
    foreach (['wpBlocks' => 'PHP WordPress blocks', 'phpHtml' => 'PHP HTML'] as $key => $label) {
        if ($key === $baselineKey || (($record[$key]['ok'] ?? false) !== true)) {
            continue;
        }
        $text = showcase_output_text(
            $siteDir,
            (string) ($record[$key]['path'] ?? ''),
            $comparisonExclusionClasses
        );
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
        $actualVisual = showcase_output_visual_signature(
            $siteDir,
            (string) ($record[$key]['path'] ?? ''),
            $comparisonExclusionClasses
        );
        $visualScore = null;
        $visualStatus = 'not_applicable';
        if (!$pdfKitTextGeometryReference) {
            $visualScore = showcase_visual_signature_similarity(
                $baselineVisual,
                $actualVisual
            );
            if ($textScore >= 0.80 && $baselineVisual === [] && showcase_text_only_visual_signature($actualVisual)) {
                $visualScore = 1.0;
            }
            $visualStatus = $visualScore >= 0.75 ? 'faithful_enough' : ($visualScore >= 0.50 ? 'review' : 'divergent');
        }
        $comparisons[$key] = [
            'label' => $label,
            'status' => $textScore >= 0.80 ? 'faithful_enough' : ($textScore >= 0.55 ? 'review' : 'divergent'),
            'score' => $textScore,
            'textStatus' => $textScore >= 0.80 ? 'faithful_enough' : ($textScore >= 0.55 ? 'review' : 'divergent'),
            'textScore' => $textScore,
            'visualStatus' => $visualStatus,
            'visualScore' => $visualScore,
        ];
    }

    return [
        'baseline' => $baselineKey,
        'comparisons' => $comparisons,
    ];
}

/**
 * @return array{itemCount:int,itemIds:list<string>}|null
 */
function showcase_bibliography_source_summary(string $sourcePath, string $format): ?array
{
    if (!showcase_bibliography_input_format($format)) {
        return null;
    }

    try {
        $document = PandocConverter::readFile($sourcePath, $format);
    } catch (Throwable) {
        return null;
    }

    $itemCount = $document->attr('cslItemCount');
    $itemIds = $document->attr('cslItemIds');
    if (!is_int($itemCount) && !is_numeric($itemCount)) {
        return null;
    }

    return [
        'itemCount' => max(0, (int) $itemCount),
        'itemIds' => is_array($itemIds)
            ? array_values(array_filter(array_map('strval', $itemIds), static fn (string $id): bool => $id !== ''))
            : [],
    ];
}

/**
 * Compare bibliography imports against Pandoc Citeproc's rendered references.
 * The source reader is checked by reference count and the WordPress output by
 * baseline-token coverage, so different editable HTML wrappers do not mask a
 * lost citation field or reference entry.
 *
 * @return array{available:bool,expectedEntryCount?:int,actualEntryCount?:int,expectedTokenCount?:int,matchedTokenCount?:int,contentCoverage?:float,detail?:string}
 */
function showcase_record_bibliography_comparison(string $siteDir, array $record): array
{
    if (($record['haskell']['renderedWithCiteproc'] ?? false) !== true) {
        return ['available' => false];
    }

    $haskellPath = (string) ($record['haskell']['path'] ?? '');
    $wpPath = (string) ($record['wpBlocks']['path'] ?? '');
    $source = is_array($record['bibliographySource'] ?? null) ? $record['bibliographySource'] : null;
    if ($haskellPath === '' || $wpPath === '' || $source === null) {
        return ['available' => false];
    }

    $baselineText = showcase_output_text($siteDir, $haskellPath);
    $wordpressText = showcase_output_text($siteDir, $wpPath);
    $expectedEntries = showcase_citeproc_entry_count($siteDir, $haskellPath);
    $actualEntries = (int) ($source['itemCount'] ?? -1);
    if ($baselineText === '' || $expectedEntries === null || $actualEntries < 0) {
        return ['available' => false];
    }

    $expectedTokens = showcase_text_tokens($baselineText);
    $actualTokens = showcase_text_tokens($wordpressText);
    $expectedCounts = array_count_values($expectedTokens);
    $actualCounts = array_count_values($actualTokens);
    $matched = 0;
    foreach ($expectedCounts as $token => $count) {
        $matched += min($count, $actualCounts[$token] ?? 0);
    }
    $coverage = $expectedTokens === [] ? 1.0 : round($matched / count($expectedTokens), 4);

    return [
        'available' => true,
        'expectedEntryCount' => $expectedEntries,
        'actualEntryCount' => $actualEntries,
        'expectedTokenCount' => count($expectedTokens),
        'matchedTokenCount' => $matched,
        'contentCoverage' => $coverage,
        'detail' => $matched . '/' . count($expectedTokens) . ' Citeproc reference tokens present in WordPress output',
    ];
}

function showcase_citeproc_entry_count(string $siteDir, string $relativePath): ?int
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '' || !class_exists(DOMDocument::class)) {
        return null;
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
        return null;
    }

    $xpath = new DOMXPath($dom);

    return $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " csl-entry ")]')?->length;
}

function showcase_output_text(string $siteDir, string $relativePath, array $excludeClasses = []): string
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '') {
        return '';
    }
    if ($excludeClasses !== []) {
        $html = showcase_remove_elements_with_classes($html, $excludeClasses);
    }
    $html = showcase_visible_html($html);
    if (class_exists(DOMDocument::class)) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
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
            $text = $body instanceof DOMElement ? showcase_dom_visible_text($body) : showcase_dom_visible_text($dom);
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

            return trim($text);
        }
    }
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function showcase_output_contains(string $siteDir, string $relativePath, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return str_contains(showcase_output_html($siteDir, $relativePath), $needle);
}

function showcase_visible_html(string $html): string
{
    $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
    $html = preg_replace('/<(head|script|style|noscript|template)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
    $html = preg_replace('/<header\b[^>]*\bid=["\']title-block-header["\'][^>]*>.*?<\/header>/is', '', $html) ?? $html;

    return $html;
}

function showcase_dom_visible_text(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return $node->wholeText;
    }
    if ($node instanceof DOMComment || $node instanceof DOMProcessingInstruction) {
        return '';
    }
    $name = $node instanceof DOMElement ? strtolower($node->tagName) : '';
    if (in_array($name, ['head', 'script', 'style', 'noscript', 'template'], true)) {
        return '';
    }
    if ($node instanceof DOMElement && $node->getAttribute('id') === 'title-block-header') {
        return '';
    }

    $text = '';
    foreach ($node->childNodes as $child) {
        $text .= showcase_dom_visible_text($child);
        if ($child instanceof DOMElement && showcase_dom_text_separator_after(strtolower($child->tagName))) {
            $text .= ' ';
        }
    }

    return showcase_dom_text_separator_around($name) ? ' ' . $text . ' ' : $text;
}

function showcase_dom_text_separator_after(string $tagName): bool
{
    return in_array($tagName, ['br', 'td', 'th', 'tr', 'li', 'dt', 'dd'], true);
}

function showcase_dom_text_separator_around(string $tagName): bool
{
    return in_array($tagName, [
        'address',
        'article',
        'aside',
        'blockquote',
        'body',
        'caption',
        'div',
        'figcaption',
        'figure',
        'footer',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'hr',
        'main',
        'nav',
        'ol',
        'p',
        'pre',
        'section',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'ul',
    ], true);
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
function showcase_output_visual_signature(string $siteDir, string $relativePath, array $excludeClasses = []): array
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '') {
        return [];
    }
    if ($excludeClasses !== []) {
        $html = showcase_remove_elements_with_classes($html, $excludeClasses);
    }
    $html = showcase_visible_html($html);

    $semanticSignature = showcase_html_visual_signature($html);
    if ($semanticSignature !== null) {
        return $semanticSignature;
    }
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
 * @param list<string> $classes
 */
function showcase_remove_elements_with_classes(string $html, array $classes): string
{
    $classes = array_values(array_filter(array_unique(array_map('strval', $classes)), static fn (string $class): bool => $class !== ''));
    if ($classes === []) {
        return $html;
    }
    if (!class_exists(DOMDocument::class)) {
        return $html;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return $html;
    }
    $xpath = new DOMXPath($dom);
    $targets = [];
    foreach ($classes as $class) {
        $query = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
        foreach ($xpath->query($query) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $targets[] = $node;
            }
        }
    }
    foreach ($targets as $node) {
        if ($node->parentNode !== null) {
            $node->parentNode->removeChild($node);
        }
    }
    $body = $dom->getElementsByTagName('body')->item(0);

    return $body instanceof DOMElement ? (string) $dom->saveHTML($body) : $html;
}

/**
 * Build a document-level structural signature rather than counting incidental
 * HTML wrappers. Pandoc HTML commonly puts paragraphs inside list/table/
 * definition-list items, while WordPress blocks use the container semantics
 * directly. Treating those wrappers as document paragraphs falsely reports a
 * loss of structure on otherwise equivalent conversions.
 *
 * @return array<string, int>|null
 */
function showcase_html_visual_signature(string $html): ?array
{
    if (!class_exists(DOMDocument::class)) {
        return null;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return null;
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        return null;
    }

    $counts = [];
    foreach ($body->getElementsByTagName('*') as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($element->tagName);
        if (preg_match('/^h[1-6]$/', $tag) === 1) {
            showcase_increment_visual_signature($counts, 'heading');
            showcase_increment_visual_signature($counts, $tag);
            continue;
        }

        if ($tag === 'p') {
            if (showcase_is_image_only_paragraph($element)) {
                continue;
            }
            if (showcase_element_has_class($element, 'linegroup')) {
                showcase_increment_visual_signature($counts, 'linegroup');
            } elseif (showcase_is_document_paragraph($element)) {
                showcase_increment_visual_signature($counts, 'p');
            }
            continue;
        }

        if ($tag === 'div' && showcase_element_has_class($element, 'linegroup')) {
            showcase_increment_visual_signature($counts, 'linegroup');
            continue;
        }

        if ($tag === 'dl') {
            showcase_increment_visual_signature($counts, 'dl');
            continue;
        }
        if ($tag === 'div' && showcase_element_has_class($element, 'pandoc-definition-list')) {
            showcase_increment_visual_signature($counts, 'dl');
            continue;
        }

        if ($tag === 'ul' || $tag === 'ol') {
            if (!showcase_element_has_class($element, 'pandoc-definition-values')) {
                showcase_increment_visual_signature($counts, $tag);
            }
            continue;
        }
        if ($tag === 'li') {
            if (!showcase_element_has_ancestor_class($element, 'pandoc-definition-values')) {
                showcase_increment_visual_signature($counts, 'li');
            }
            continue;
        }

        if ($tag === 'figure') {
            if (!showcase_is_image_only_figure($element)) {
                showcase_increment_visual_signature($counts, 'figure');
            }
            continue;
        }

        if (in_array($tag, ['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'img', 'figcaption', 'pre', 'code', 'blockquote', 'math', 'svg'], true)) {
            showcase_increment_visual_signature($counts, $tag);
        }
    }
    ksort($counts);

    return $counts;
}

/**
 * @param array<string, int> $counts
 */
function showcase_increment_visual_signature(array &$counts, string $name): void
{
    $counts[$name] = ($counts[$name] ?? 0) + 1;
}

function showcase_is_document_paragraph(DOMElement $element): bool
{
    if (showcase_element_has_class($element, 'pandoc-definition-term')) {
        return false;
    }

    return !showcase_element_has_ancestor_tag($element, ['caption', 'dd', 'dt', 'figcaption', 'li', 'td', 'th'])
        && !showcase_element_has_ancestor_class($element, 'pandoc-definition-list')
        && !showcase_element_has_ancestor_class($element, 'linegroup');
}

function showcase_is_image_only_paragraph(DOMElement $element): bool
{
    return showcase_is_image_only_container($element);
}

function showcase_is_image_only_figure(DOMElement $element): bool
{
    return showcase_is_image_only_container($element);
}

function showcase_is_image_only_container(DOMElement $element): bool
{
    $containsImage = false;
    $stack = [$element];
    while ($stack !== []) {
        $node = array_pop($stack);
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->wholeText) !== '') {
                    return false;
                }
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if ($tag === 'img') {
                $containsImage = true;
                continue;
            }
            if (!in_array($tag, ['a', 'picture', 'source'], true)) {
                return false;
            }
            $stack[] = $child;
        }
    }

    return $containsImage;
}

/**
 * @param list<string> $tags
 */
function showcase_element_has_ancestor_tag(DOMElement $element, array $tags): bool
{
    $lookup = array_fill_keys($tags, true);
    for ($node = $element->parentNode; $node !== null; $node = $node->parentNode) {
        if ($node instanceof DOMElement && isset($lookup[strtolower($node->tagName)])) {
            return true;
        }
    }

    return false;
}

function showcase_element_has_ancestor_class(DOMElement $element, string $class): bool
{
    for ($node = $element->parentNode; $node !== null; $node = $node->parentNode) {
        if ($node instanceof DOMElement && showcase_element_has_class($node, $class)) {
            return true;
        }
    }

    return false;
}

function showcase_element_has_class(DOMElement $element, string $class): bool
{
    $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

    return in_array($class, $classes, true);
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

/**
 * @param array<string, int> $signature
 */
function showcase_text_only_visual_signature(array $signature): bool
{
    if ($signature === []) {
        return true;
    }
    foreach ($signature as $key => $count) {
        if ((int) $count <= 0) {
            continue;
        }
        if (!in_array($key, ['p'], true)) {
            return false;
        }
    }

    return true;
}

function showcase_text_similarity(string $expected, string $actual): float
{
    return showcase_text_overlap_metrics($expected, $actual)['f1'];
}

/**
 * @return array{expectedTokenCount:int,actualTokenCount:int,matchedTokenCount:int,expectedCoverage:float,actualPrecision:float,f1:float}
 */
function showcase_text_overlap_metrics(string $expected, string $actual): array
{
    $expectedTokens = showcase_text_tokens($expected);
    $actualTokens = showcase_text_tokens($actual);
    if ($expectedTokens === [] && $actualTokens === []) {
        return [
            'expectedTokenCount' => 0,
            'actualTokenCount' => 0,
            'matchedTokenCount' => 0,
            'expectedCoverage' => 1.0,
            'actualPrecision' => 1.0,
            'f1' => 1.0,
        ];
    }
    if ($expectedTokens === [] || $actualTokens === []) {
        return [
            'expectedTokenCount' => count($expectedTokens),
            'actualTokenCount' => count($actualTokens),
            'matchedTokenCount' => 0,
            'expectedCoverage' => 0.0,
            'actualPrecision' => 0.0,
            'f1' => 0.0,
        ];
    }

    $expectedCounts = array_count_values($expectedTokens);
    $actualCounts = array_count_values($actualTokens);
    $overlap = 0;
    foreach ($expectedCounts as $token => $count) {
        $overlap += min($count, $actualCounts[$token] ?? 0);
    }

    return [
        'expectedTokenCount' => count($expectedTokens),
        'actualTokenCount' => count($actualTokens),
        'matchedTokenCount' => $overlap,
        'expectedCoverage' => round($overlap / count($expectedTokens), 4),
        'actualPrecision' => round($overlap / count($actualTokens), 4),
        'f1' => round((2.0 * $overlap) / (count($expectedTokens) + count($actualTokens)), 4),
    ];
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
 * @return array{comparisons:int, faithfulEnough:int, review:int, divergent:int, noText:int, visualComparisons:int, visualFaithfulEnough:int, visualReview:int, visualDivergent:int, visualNoStructure:int, visualNotApplicable:int}
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
        'visualNotApplicable' => 0,
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
            $visualStatus = (string) ($comparison['visualStatus'] ?? '');
            if ($visualStatus === 'not_applicable') {
                $summary['visualNotApplicable']++;
                continue;
            }
            $summary['visualComparisons']++;
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
 * @param list<array<string,mixed>> $records
 * @return array{samples:int, available:int, pass:int, review:int, fail:int, unavailable:int}
 */
function showcase_bibliography_comparison_summary(array $records): array
{
    $summary = ['samples' => 0, 'available' => 0, 'pass' => 0, 'review' => 0, 'fail' => 0, 'unavailable' => 0];
    foreach ($records as $record) {
        if (!showcase_bibliography_input_format((string) ($record['format'] ?? ''))) {
            continue;
        }
        $summary['samples']++;
        $comparison = is_array($record['bibliographyComparison'] ?? null) ? $record['bibliographyComparison'] : [];
        if (($comparison['available'] ?? false) !== true) {
            $summary['unavailable']++;
            continue;
        }
        $summary['available']++;
        $status = (string) ($record['importQuality']['status'] ?? 'fail');
        if (isset($summary[$status])) {
            $summary[$status]++;
        } else {
            $summary['fail']++;
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

    $bibliographyQuality = showcase_bibliography_import_quality($siteDir, $record);
    if ($bibliographyQuality !== null) {
        return $bibliographyQuality;
    }

    $pdfQuality = showcase_pdf_import_quality($siteDir, $record);
    if ($pdfQuality !== null) {
        return $pdfQuality;
    }

    $wpPath = (string) ($record['wpBlocks']['path'] ?? '');
    $baselineKey = (string) (($record['faithfulness']['baseline'] ?? '') ?: '');
    $hasBaseline = $baselineKey !== '';
    $baselinePath = $baselineKey !== '' ? (string) ($record[$baselineKey]['path'] ?? '') : '';
    $semantic = is_array($record['importSemantics'] ?? null) ? $record['importSemantics'] : [];
    $comparisonExclusionClasses = is_array($semantic['comparisonExclusionClasses'] ?? null)
        ? array_values(array_filter($semantic['comparisonExclusionClasses'], 'is_string'))
        : [];
    $wpVisual = showcase_output_visual_signature($siteDir, $wpPath, $comparisonExclusionClasses);
    $baselineVisual = $baselinePath === '' ? [] : showcase_output_visual_signature($siteDir, $baselinePath);
    $countGateWpVisual = $wpVisual;
    $countGateDetailSuffix = '';
    $comparison = is_array($record['faithfulness']['comparisons']['wpBlocks'] ?? null)
        ? $record['faithfulness']['comparisons']['wpBlocks']
        : [];

    $textScore = isset($comparison['textScore']) ? (float) $comparison['textScore'] : null;
    $textOnlyBaseline = $textScore !== null
        && $textScore >= 0.80
        && $baselineVisual === []
        && showcase_text_only_visual_signature($wpVisual);
    $gates['text_completeness'] = showcase_score_gate($textScore, 0.80, 0.55, 'visible text overlap with the baseline', $hasBaseline);

    $visualScore = isset($comparison['visualScore']) ? (float) $comparison['visualScore'] : null;
    $gates['visual_structure'] = showcase_score_gate($visualScore, 0.75, 0.50, 'heading/list/table/image shape overlap with the baseline', $hasBaseline);

    $gates['paragraph_merge_split'] = $textOnlyBaseline
        ? [
            'status' => 'pass',
            'expected' => 'text-only baseline',
            'actual' => (int) ($wpVisual['p'] ?? 0),
            'detail' => 'text-only baseline represented as WordPress paragraph content',
        ]
        : showcase_count_ratio_gate(
            (int) ($baselineVisual['p'] ?? 0),
            (int) ($countGateWpVisual['p'] ?? 0),
            'paragraph count ratio' . $countGateDetailSuffix,
            $hasBaseline
        );
    $gates['heading_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['heading'] ?? 0),
        (int) ($countGateWpVisual['heading'] ?? 0),
        'heading count ratio' . $countGateDetailSuffix,
        $hasBaseline
    );
    $gates['list_count'] = showcase_count_ratio_gate(
        (int) (($baselineVisual['ul'] ?? 0) + ($baselineVisual['ol'] ?? 0)),
        (int) (($countGateWpVisual['ul'] ?? 0) + ($countGateWpVisual['ol'] ?? 0)),
        'list count ratio' . $countGateDetailSuffix,
        $hasBaseline
    );
    $gates['definition_list_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['dl'] ?? 0),
        (int) ($countGateWpVisual['dl'] ?? 0),
        'definition list count ratio' . $countGateDetailSuffix,
        $hasBaseline
    );
    $gates['table_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['table'] ?? 0),
        (int) ($countGateWpVisual['table'] ?? 0),
        'table count ratio' . $countGateDetailSuffix,
        $hasBaseline
    );
    $gates['image_count'] = showcase_count_ratio_gate(
        (int) ($baselineVisual['img'] ?? 0),
        (int) ($countGateWpVisual['img'] ?? 0),
        'image count ratio' . $countGateDetailSuffix,
        $hasBaseline
    );
    $semanticGate = showcase_import_semantic_metadata_gate($siteDir, $record);
    if ($semanticGate !== null) {
        $gates['source_metadata_semantics'] = $semanticGate;
    }

    showcase_add_output_integrity_gates($gates, $siteDir, $record, $wpPath);

    return showcase_import_quality_result($gates);
}

/**
 * @return array{status:string,expected:mixed,actual:mixed,detail:string}|null
 */
function showcase_import_semantic_metadata_gate(string $siteDir, array $record): ?array
{
    $semantics = is_array($record['importSemantics'] ?? null) ? $record['importSemantics'] : [];
    $metadata = is_array($semantics['metadata'] ?? null) ? $semantics['metadata'] : [];
    $structures = is_array($semantics['structures'] ?? null) ? $semantics['structures'] : [];
    if ($metadata === [] && $structures === []) {
        return null;
    }
    $wpPath = (string) ($record['wpBlocks']['path'] ?? '');
    $html = showcase_output_html($siteDir, $wpPath);
    $text = showcase_output_text($siteDir, $wpPath);
    if ($html === '' || $text === '') {
        return [
            'status' => 'fail',
            'expected' => count($structures) + array_sum(array_map('count', $metadata)),
            'actual' => 0,
            'detail' => 'source metadata and generated semantic structures retained in WordPress output',
        ];
    }

    $expected = 0;
    $matched = 0;
    foreach ($metadata as $values) {
        if (!is_array($values)) {
            continue;
        }
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            ++$expected;
            if (showcase_semantic_text_present($text, $value)) {
                ++$matched;
            }
        }
    }
    foreach ($structures as $class) {
        if (!is_string($class) || $class === '') {
            continue;
        }
        ++$expected;
        if (showcase_html_has_class($html, $class)) {
            ++$matched;
        }
    }
    $score = $expected === 0 ? 1.0 : $matched / $expected;

    $gate = showcase_score_gate(
        $score,
        1.0,
        0.80,
        'source metadata and generated semantic structures retained in WordPress output',
        true
    );
    $gate['expected'] = $expected;
    $gate['actual'] = $matched;

    return $gate;
}

function showcase_semantic_text_present(string $haystack, string $needle): bool
{
    $normalize = static function (string $value): string {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };

    return str_contains($normalize($haystack), $normalize($needle));
}

function showcase_html_has_class(string $html, string $class): bool
{
    return preg_match(
        "/\\bclass=[\"'](?:[^\"']*\\s)?" . preg_quote($class, '/') . "(?:\\s[^\"']*)?[\"']/iu",
        $html
    ) === 1;
}

/**
 * @return array{status:string, gates:array<string,array<string,mixed>>, summary:array<string,int>}|null
 */
function showcase_bibliography_import_quality(string $siteDir, array $record): ?array
{
    $comparison = is_array($record['bibliographyComparison'] ?? null)
        ? $record['bibliographyComparison']
        : [];
    if (($comparison['available'] ?? false) !== true) {
        return null;
    }

    $coverage = isset($comparison['contentCoverage']) ? (float) $comparison['contentCoverage'] : null;
    $gates = [
        'citeproc_content_coverage' => showcase_score_gate(
            $coverage,
            0.80,
            0.55,
            (string) ($comparison['detail'] ?? 'Citeproc reference token coverage'),
            true
        ),
        'citeproc_entry_count' => showcase_count_ratio_gate(
            (int) ($comparison['expectedEntryCount'] ?? 0),
            (int) ($comparison['actualEntryCount'] ?? 0),
            'Citeproc reference entry count ratio',
            true
        ),
    ];
    $wpPath = (string) ($record['wpBlocks']['path'] ?? '');
    showcase_add_output_integrity_gates($gates, $siteDir, $record, $wpPath);

    return showcase_import_quality_result($gates);
}

/**
 * PDFKit provides an independent source-side text and geometry reference, but
 * a PDF page does not generally encode an HTML semantic tree. Do not turn its
 * visual lines into fake paragraphs merely to reuse an HTML-to-HTML score.
 *
 * @return array{status:string, gates:array<string,array<string,mixed>>, summary:array<string,int>}|null
 */
function showcase_pdf_import_quality(string $siteDir, array $record): ?array
{
    if (PandocConverter::canonicalInputFormat((string) ($record['format'] ?? '')) !== 'pdf') {
        return null;
    }

    $reference = is_array($record['externalReference'] ?? null) ? $record['externalReference'] : [];
    if (($reference['ok'] ?? false) !== true || ($reference['kind'] ?? null) !== 'macos-pdfkit-text-geometry') {
        return null;
    }

    $wpPath = (string) ($record['wpBlocks']['path'] ?? '');
    $referencePath = (string) ($reference['path'] ?? '');
    $nativeExpectedText = showcase_output_text($siteDir, $referencePath);
    $expectedText = showcase_pdfkit_stable_body_text($siteDir, $reference) ?? $nativeExpectedText;
    $actualText = showcase_output_text($siteDir, $wpPath);
    $textMetrics = showcase_text_overlap_metrics($expectedText, $actualText);
    $nativeTextMetrics = showcase_text_overlap_metrics($nativeExpectedText, $actualText);
    // A two-token difference in a long PDF is below the stated 0.1% gate
    // precision and commonly comes from independent glyph normalization.
    $nativeSourceCoverage = round((float) $nativeTextMetrics['expectedCoverage'], 3);
    $referenceMetrics = is_array($reference['metrics'] ?? null) ? $reference['metrics'] : [];
    $wpVisual = showcase_output_visual_signature($siteDir, $wpPath);
    $tableDominant = showcase_pdf_output_is_table_dominant($siteDir, $wpPath);
    $textCompletenessScore = $tableDominant
        ? (float) $textMetrics['expectedCoverage']
        : (float) $textMetrics['f1'];
    $textCompletenessDetail = $tableDominant
        ? 'stable body-text coverage with a table-dominant WordPress output and the independent macOS PDFKit reference'
        : 'bidirectional stable body-text overlap with the independent macOS PDFKit reference';

    $gates = [
        'text_completeness' => showcase_score_gate(
            $textCompletenessScore,
            0.80,
            0.55,
            $textCompletenessDetail,
            true
        ),
        'native_source_coverage' => showcase_score_gate(
            $nativeSourceCoverage,
            0.65,
            0.45,
            'native PDFKit source tokens retained in the WordPress output',
            true
        ),
        'pdf_geometry_reference' => showcase_pdf_geometry_reference_gate($referenceMetrics),
        'paragraph_merge_split' => showcase_pdf_paragraph_geometry_gate($referenceMetrics, $wpVisual),
    ];

    showcase_add_output_integrity_gates($gates, $siteDir, $record, $wpPath);

    return showcase_import_quality_result($gates);
}

function showcase_pdf_output_is_table_dominant(string $siteDir, string $relativePath): bool
{
    $html = showcase_output_html($siteDir, $relativePath);
    if ($html === '') {
        return false;
    }

    $visibleHtml = showcase_visible_html($html);
    $documentTokenCount = count(showcase_text_tokens(strip_tags($visibleHtml)));
    if ($documentTokenCount === 0) {
        return false;
    }

    preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $visibleHtml, $matches);
    $tableText = '';
    foreach ($matches[1] ?? [] as $tableHtml) {
        $tableText .= ' ' . strip_tags((string) $tableHtml);
    }
    $tableTokenCount = count(showcase_text_tokens($tableText));

    return $tableTokenCount >= 24 && ($tableTokenCount / $documentTokenCount) >= 0.55;
}

/**
 * PDFKit exposes every painted text run, including isolated diagram labels and
 * clipped display fragments. The semantic text score uses only lines that
 * participate in stable body geometry, while native_source_coverage above
 * remains a separate gate over the complete raw PDFKit text layer.
 *
 * @param array<string,mixed> $reference
 */
function showcase_pdfkit_stable_body_text(string $siteDir, array $reference): ?string
{
    $dataPath = trim((string) ($reference['dataPath'] ?? ''));
    if ($dataPath === '') {
        return null;
    }

    $path = $siteDir . '/' . ltrim($dataPath, '/');
    if (!is_file($path)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !is_array($data['pages'] ?? null)) {
        return null;
    }

    $pages = [];
    foreach ($data['pages'] as $page) {
        if (!is_array($page)) {
            continue;
        }
        $lines = is_array($page['lines'] ?? null) ? $page['lines'] : [];
        $stableLines = showcase_pdfkit_stable_body_lines($lines);
        if ($stableLines === []) {
            $text = trim((string) ($page['text'] ?? ''));
            if ($text !== '') {
                $pages[] = $text;
            }
            continue;
        }
        $pages[] = implode("\n", $stableLines);
    }

    $text = trim(implode("\n", $pages));

    return $text === '' ? null : $text;
}

/**
 * @param list<mixed> $lines
 * @return list<string>
 */
function showcase_pdfkit_stable_body_lines(array $lines): array
{
    $normalized = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $text = trim((string) ($line['text'] ?? ''));
        if ($text === '' || !isset($line['x'], $line['y'], $line['width'], $line['height'])) {
            continue;
        }
        $normalized[] = [
            'text' => $text,
            'x' => (float) $line['x'],
            'y' => (float) $line['y'],
            'width' => max(0.0, (float) $line['width']),
            'height' => max(0.0, (float) $line['height']),
            'words' => count(showcase_text_tokens($text)),
        ];
    }
    if (count($normalized) < 8) {
        return array_values(array_map(static fn (array $line): string => $line['text'], $normalized));
    }

    $heights = array_values(array_filter(array_map(
        static fn (array $line): float => $line['height'],
        $normalized
    ), static fn (float $height): bool => $height > 0.0));
    sort($heights, SORT_NUMERIC);
    $medianHeight = $heights === [] ? 10.0 : $heights[intdiv(count($heights), 2)];
    $startTolerance = max(8.0, $medianHeight * 1.25);
    $anchors = [];
    foreach ($normalized as $index => $line) {
        if ($line['words'] < 3 && mb_strlen($line['text'], 'UTF-8') < 12) {
            continue;
        }
        $anchorIndex = null;
        foreach ($anchors as $candidateIndex => $anchor) {
            if (abs($line['x'] - $anchor['x']) <= $startTolerance) {
                $anchorIndex = $candidateIndex;
                break;
            }
        }
        if ($anchorIndex === null) {
            $anchors[] = ['x' => $line['x'], 'indexes' => [$index]];
            continue;
        }
        $anchors[$anchorIndex]['indexes'][] = $index;
        $starts = array_map(static fn (int $itemIndex): float => $normalized[$itemIndex]['x'], $anchors[$anchorIndex]['indexes']);
        sort($starts, SORT_NUMERIC);
        $anchors[$anchorIndex]['x'] = $starts[intdiv(count($starts), 2)];
    }

    $bodyAnchors = [];
    foreach ($anchors as $anchor) {
        if (count($anchor['indexes']) < 4) {
            continue;
        }
        $widths = array_map(static fn (int $index): float => $normalized[$index]['width'], $anchor['indexes']);
        sort($widths, SORT_NUMERIC);
        $medianWidth = $widths[intdiv(count($widths), 2)];
        if ($medianWidth < max(70.0, $medianHeight * 6.0)) {
            continue;
        }
        $bodyAnchors[] = $anchor;
    }
    if ($bodyAnchors === []) {
        return array_values(array_map(static fn (array $line): string => $line['text'], $normalized));
    }

    $kept = [];
    foreach ($normalized as $index => $line) {
        $anchor = null;
        foreach ($bodyAnchors as $candidate) {
            if (abs($line['x'] - $candidate['x']) <= $startTolerance) {
                $anchor = $candidate;
                break;
            }
        }
        if ($anchor === null) {
            if ($line['words'] >= 8 && preg_match('/[.!?]\s*$/u', $line['text']) === 1) {
                $kept[] = $line['text'];
            }
            continue;
        }

        $hasRhythmNeighbor = false;
        foreach ($anchor['indexes'] as $neighborIndex) {
            if ($neighborIndex === $index) {
                continue;
            }
            $distance = abs($line['y'] - $normalized[$neighborIndex]['y']);
            if ($distance >= $medianHeight * 0.25 && $distance <= $medianHeight * 3.0) {
                $hasRhythmNeighbor = true;
                break;
            }
        }
        if ($line['words'] >= 3 || $hasRhythmNeighbor) {
            $kept[] = $line['text'];
        }
    }

    return $kept;
}

/**
 * @param array<string,mixed> $metrics
 * @return array{status:string,expected:string,actual:array<string,int>,detail:string}
 */
function showcase_pdf_geometry_reference_gate(array $metrics): array
{
    $pages = max(0, (int) ($metrics['pageCount'] ?? 0));
    $textPages = max(0, (int) ($metrics['textPageCount'] ?? 0));
    $lines = max(0, (int) ($metrics['lineCount'] ?? 0));
    $scanWithoutTextLayer = $pages > 0 && $textPages === 0 && $lines === 0;

    return [
        'status' => $pages > 0 && $textPages > 0 && $lines > 0
            ? 'pass'
            : ($scanWithoutTextLayer ? 'review' : 'fail'),
        'expected' => $scanWithoutTextLayer
            ? 'page geometry plus browser or server OCR'
            : 'native PDF page and line geometry',
        'actual' => ['pages' => $pages, 'textPages' => $textPages, 'lines' => $lines],
        'detail' => $scanWithoutTextLayer
            ? 'PDFKit exposed scanned pages but no native text layer; this fixture requires the explicit OCR continuation path.'
            : 'macOS PDFKit independently exposed source page boundaries and visual text lines; untagged PDFs do not expose an HTML heading/list/table/image tree.',
    ];
}

/**
 * @param array<string,mixed> $metrics
 * @param array<string,int> $wpVisual
 * @return array{status:string,expected:string,actual:array<string,int>,detail:string}
 */
function showcase_pdf_paragraph_geometry_gate(array $metrics, array $wpVisual): array
{
    $sourceLines = max(0, (int) ($metrics['lineCount'] ?? 0));
    $sourcePages = max(0, (int) ($metrics['pageCount'] ?? 0));
    $sourceTextPages = max(0, (int) ($metrics['textPageCount'] ?? 0));
    $textPages = max(1, (int) ($metrics['textPageCount'] ?? 0));
    $paragraphs = max(0, (int) ($wpVisual['p'] ?? 0));
    $textBlocks = $paragraphs
        + max(0, (int) ($wpVisual['heading'] ?? 0))
        + max(0, (int) ($wpVisual['li'] ?? 0))
        + max(0, (int) ($wpVisual['td'] ?? 0))
        + max(0, (int) ($wpVisual['th'] ?? 0))
        + max(0, (int) ($wpVisual['pre'] ?? 0))
        + max(0, (int) ($wpVisual['linegroup'] ?? 0));
    $maxParagraphs = $sourceLines < 20 ? PHP_INT_MAX : max(1, (int) floor($sourceLines * 0.90));

    $scanWithoutTextLayer = $sourcePages > 0 && $sourceTextPages === 0 && $sourceLines === 0;
    $status = $scanWithoutTextLayer
        ? 'review'
        : ($textBlocks < $textPages
            ? 'fail'
            : ($paragraphs > $maxParagraphs ? 'review' : 'pass'));

    return [
        'status' => $status,
        'expected' => $scanWithoutTextLayer
            ? 'semantic text blocks after OCR continuation'
            : ($sourceLines < 20
            ? 'at least ' . $textPages . ' semantic text blocks'
            : 'at least ' . $textPages . ' semantic text blocks and no more than 90% of ' . $sourceLines . ' visual lines as paragraphs'),
        'actual' => ['textBlocks' => $textBlocks, 'paragraphs' => $paragraphs, 'sourceLines' => $sourceLines],
        'detail' => $scanWithoutTextLayer
            ? 'A page-only scan cannot be scored for paragraph reconstruction until OCR supplies text.'
            : 'native line geometry guards against collapsing a multi-page source or emitting one paragraph for every visual line.',
    ];
}

/**
 * @param array<string,array<string,mixed>> $gates
 */
function showcase_add_output_integrity_gates(array &$gates, string $siteDir, array $record, string $wpPath): void
{
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
    $sourceRawHtmlBlocks = isset($record['sourceRawHtmlBlockCount'])
        ? max(0, (int) $record['sourceRawHtmlBlockCount'])
        : null;
    $expectedCustomHtmlBlocks = $sourceRawHtmlBlocks === null
        ? 0
        : min($customHtmlBlocks, $sourceRawHtmlBlocks);
    $unexpectedCustomHtmlBlocks = $customHtmlBlocks - $expectedCustomHtmlBlocks;
    $customHtmlShare = $totalBlocks <= 0 ? 0.0 : round($unexpectedCustomHtmlBlocks / $totalBlocks, 4);
    $customHtmlDetail = $customHtmlBlocks . '/' . $totalBlocks . ' WordPress blocks are Custom HTML';
    if ($sourceRawHtmlBlocks !== null) {
        $customHtmlDetail .= '; ' . $expectedCustomHtmlBlocks . ' source raw HTML block'
            . ($expectedCustomHtmlBlocks === 1 ? '' : 's')
            . ', ' . $unexpectedCustomHtmlBlocks . ' unexpected';
    }
    $gates['custom_html_percentage'] = [
        'status' => $customHtmlShare <= 0.05 ? 'pass' : ($customHtmlShare <= 0.15 ? 'review' : 'fail'),
        'expected' => '<=0.05',
        'actual' => $customHtmlShare,
        'detail' => $customHtmlDetail,
    ];
}

/**
 * @param array<string,array<string,mixed>> $gates
 * @return array{status:string, gates:array<string,array<string,mixed>>, summary:array<string,int>}
 */
function showcase_import_quality_result(array $gates): array
{
    $summary = ['pass' => 0, 'review' => 0, 'fail' => 0, 'unbenchmarked' => 0];
    foreach ($gates as $gate) {
        $status = (string) ($gate['status'] ?? 'review');
        if (!isset($summary[$status])) {
            $status = 'review';
        }
        $summary[$status]++;
    }
    $status = $summary['fail'] > 0
        ? 'fail'
        : ($summary['review'] > 0 ? 'review' : ($summary['unbenchmarked'] > 0 ? 'unbenchmarked' : 'pass'));

    return [
        'status' => $status,
        'gates' => $gates,
        'summary' => $summary,
    ];
}

/**
 * @return array{status:string, expected:mixed, actual:mixed, detail:string}
 */
function showcase_score_gate(?float $score, float $passThreshold, float $reviewThreshold, string $detail, bool $hasBaseline): array
{
    if (!$hasBaseline) {
        return [
            'status' => 'unbenchmarked',
            'expected' => 'comparison baseline',
            'actual' => null,
            'detail' => 'no comparison baseline is available for ' . $detail,
        ];
    }

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
function showcase_count_ratio_gate(int $expected, int $actual, string $detail, bool $hasBaseline): array
{
    if (!$hasBaseline) {
        return [
            'status' => 'unbenchmarked',
            'expected' => 'comparison baseline',
            'actual' => null,
            'detail' => 'no comparison baseline is available for ' . $detail,
        ];
    }

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
 * @return array{samples:int, pass:int, review:int, fail:int, unbenchmarked:int}
 */
function showcase_import_quality_summary(array $records): array
{
    $summary = ['samples' => 0, 'pass' => 0, 'review' => 0, 'fail' => 0, 'unbenchmarked' => 0];
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
 *     common:array{samples:int, pass:int, review:int, fail:int, unbenchmarked:int, wpFailures:int, passReviewOrUnbenchmarked:int, formats:array<string,array{samples:int, pass:int, review:int, fail:int, unbenchmarked:int, wpFailures:int, passReviewOrUnbenchmarked:int}>},
 *     exotic:array{samples:int, pass:int, review:int, fail:int, unbenchmarked:int, wpFailures:int, passReviewOrUnbenchmarked:int, formats:array<string,array{samples:int, pass:int, review:int, fail:int, unbenchmarked:int, wpFailures:int, passReviewOrUnbenchmarked:int}>}
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
        'unbenchmarked' => 0,
        'wpFailures' => 0,
        'passReviewOrUnbenchmarked' => 0,
        'formats' => [],
    ];
    $emptyFormat = static fn (): array => [
        'samples' => 0,
        'pass' => 0,
        'review' => 0,
        'fail' => 0,
        'unbenchmarked' => 0,
        'wpFailures' => 0,
        'passReviewOrUnbenchmarked' => 0,
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
        if (!in_array($status, ['pass', 'review', 'fail', 'unbenchmarked'], true)) {
            $status = 'fail';
        }
        $summary[$segment][$status]++;
        $summary[$segment]['formats'][$format][$status]++;
        if (in_array($status, ['pass', 'review', 'unbenchmarked'], true)) {
            $summary[$segment]['passReviewOrUnbenchmarked']++;
            $summary[$segment]['formats'][$format]['passReviewOrUnbenchmarked']++;
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
            'minPass' => 29,
            'minPassReviewOrUnbenchmarked' => 39,
            'maxFail' => 5,
        ],
        'exotic' => [
            'required' => false,
            'minSamples' => 43,
            'maxWpFailures' => 2,
            'minPass' => 18,
            'minPassReviewOrUnbenchmarked' => 39,
            'maxFail' => 2,
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
            'minPassReviewOrUnbenchmarked' => [
                'status' => (int) ($actual['passReviewOrUnbenchmarked'] ?? 0) >= (int) $threshold['minPassReviewOrUnbenchmarked'] ? 'pass' : 'fail',
                'expected' => '>=' . (string) $threshold['minPassReviewOrUnbenchmarked'],
                'actual' => (int) ($actual['passReviewOrUnbenchmarked'] ?? 0),
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
                'unbenchmarked' => (int) ($actual['unbenchmarked'] ?? 0),
                'passReviewOrUnbenchmarked' => (int) ($actual['passReviewOrUnbenchmarked'] ?? 0),
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
    array $bibliographyComparisonSummary,
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
        'pdf-quickbooks-invoice-template',
        'pdf-tabula-spreadsheet-no-frame',
        'pdf-tabula-multicolumn',
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
    $html .= '<p class="lede">Every source file is converted to PHP HTML and WordPress block markup. Haskell Pandoc provides the primary external reference where it can read the format; format-native references are added where Pandoc has no reader. PDFs use macOS PDFKit for independent text and line-geometry evidence, while generic XML uses libxml2/libxslt source structure. PDF HTML semantics remain explicit inference rather than fabricated source tags. This report shows the current pass/fail shape and links into a curated stress set.</p>';
    $html .= '<div class="stats">';
    $html .= '<div class="stat"><strong>' . h((string) $summary['totalSamples']) . '</strong><span>source files</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) count($coveredFormats)) . '</strong><span>covered input formats</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) $summary['successfulConversions']) . '/' . h((string) $summary['totalConversions']) . '</strong><span>successful conversions</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) $summary['failedConversions']) . '</strong><span>known failures</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($faithfulnessSummary['faithfulEnough'] ?? 0)) . '/' . h((string) ($faithfulnessSummary['comparisons'] ?? 0)) . '</strong><span>text-faithful comparisons</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($faithfulnessSummary['visualFaithfulEnough'] ?? 0)) . '/' . h((string) ($faithfulnessSummary['visualComparisons'] ?? 0)) . '</strong><span>visual-structure matches</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($bibliographyComparisonSummary['pass'] ?? 0)) . '/' . h((string) ($bibliographyComparisonSummary['available'] ?? 0)) . '</strong><span>Citeproc semantic comparisons</span></div>';
    $html .= '<div class="stat"><strong>' . h((string) ($importQualitySummary['pass'] ?? 0)) . '/' . h((string) ($importQualitySummary['samples'] ?? 0)) . '</strong><span>import-quality passes</span></div>';
    $html .= '</div><div class="hero-actions"><a href="examples.html">One example at a time</a><a href="index.html">Full showcase</a><a href="playground-converter.html">Convert in WordPress Playground</a><a href="manifest.json">Manifest JSON</a></div></div></header>';
    $html .= '<main class="content-page report-page">';

    $html .= '<section><h2>Success by conversion path</h2><div class="report-grid">';
    foreach ($summary['byConverter'] as $converter) {
        $html .= '<div class="report-card"><h3>' . h((string) $converter['label']) . '</h3>';
        $html .= '<p class="report-number">' . h((string) $converter['ok']) . '/' . h((string) $converter['total']) . '</p>';
        $html .= '<p class="meta">' . h((string) $converter['failed']) . ' failed</p></div>';
    }
    $html .= '</div></section>';

    $html .= '<section><h2>Faithful enough diff checks</h2>';
    $html .= '<p>These checks compare generated outputs against Haskell Pandoc when it can read the source, a format-native external reference where one is available, or PHP HTML only as a disclosed fallback. Text scores compare normalized visible words. Visual-structure scores compare the rendered document shape: headings, paragraphs, lists, tables, images, figures, captions, code, quotes, and math. Generic XML uses an independent libxml2/libxslt transform over common structural element names. PDFKit supplies text and geometry, not an HTML semantic tree, so PDF visual tag scores are explicitly excluded rather than reported as a false mismatch.</p>';
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
    $html .= '<div class="report-card"><h3>Source semantics unavailable</h3><p class="report-number">' . h((string) ($faithfulnessSummary['visualNotApplicable'] ?? 0)) . '</p></div>';
    $html .= '</div></section>';

    if (($bibliographyComparisonSummary['samples'] ?? 0) > 0) {
        $html .= '<section><h2>Bibliography semantics</h2>';
        $html .= '<p>Standalone BibTeX, BibLaTeX, CSL JSON, EndNote XML, and RIS inputs are rendered by Haskell Pandoc through Citeproc. Their WordPress output is checked by exact reference count and Citeproc token coverage, not by wrapper tags, because the PHP port deliberately emits editable definition-list blocks.</p>';
        $html .= '<div class="report-grid">';
        $html .= '<div class="report-card"><h3>Proven</h3><p class="report-number">' . h((string) ($bibliographyComparisonSummary['pass'] ?? 0)) . '</p></div>';
        $html .= '<div class="report-card"><h3>Needs review</h3><p class="report-number">' . h((string) ($bibliographyComparisonSummary['review'] ?? 0)) . '</p></div>';
        $html .= '<div class="report-card"><h3>No baseline</h3><p class="report-number">' . h((string) ($bibliographyComparisonSummary['unavailable'] ?? 0)) . '</p></div>';
        $html .= '</div></section>';
    }

    $html .= '<section><h2>Import quality gates</h2>';
    $html .= '<p>These checks evaluate the WordPress block output as an import artifact: visible text completeness, paragraph merge or split drift, Citeproc reference coverage where applicable, media extraction diagnostics, local anchor validity, and Custom HTML block share. Generic XML is checked against independent libxml2/libxslt source structure. PDFs additionally use independent native source-token coverage and line geometry because untagged PDFs do not encode HTML heading, list, table, or image semantics.</p>';
    $html .= '<div class="report-grid">';
    $html .= '<div class="report-card"><h3>Pass</h3><p class="report-number">' . h((string) ($importQualitySummary['pass'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Review</h3><p class="report-number">' . h((string) ($importQualitySummary['review'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Unbenchmarked</h3><p class="report-number">' . h((string) ($importQualitySummary['unbenchmarked'] ?? 0)) . '</p></div>';
    $html .= '<div class="report-card"><h3>Fail</h3><p class="report-number">' . h((string) ($importQualitySummary['fail'] ?? 0)) . '</p></div>';
    $html .= '</div>';
    $segments = is_array($importQualityGate['segments'] ?? null) ? $importQualityGate['segments'] : [];
    if ($segments !== []) {
        $html .= '<h3>Actionable thresholds</h3>';
        $html .= '<p>The common-format gate is blocking and covers normal WordPress import formats. Exotic formats are tracked separately so they stay visible without diluting the common-format release signal.</p>';
        $html .= '<table class="report-table compact-table"><thead><tr><th>Segment</th><th>Status</th><th>Quality coverage</th><th>Failures</th><th>Conversion failures</th><th>Policy</th></tr></thead><tbody>';
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
            $html .= '<td>' . h((string) ($actual['passReviewOrUnbenchmarked'] ?? 0)) . '/' . h((string) ($actual['samples'] ?? 0)) . ' <span class="meta">pass, review, or unbenchmarked; min ' . h((string) ($thresholds['minPassReviewOrUnbenchmarked'] ?? '')) . '</span></td>';
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
        $html .= '<td>' . h((string) ($gateSummary['pass'] ?? 0)) . ' pass, ' . h((string) ($gateSummary['review'] ?? 0)) . ' review, ' . h((string) ($gateSummary['unbenchmarked'] ?? 0)) . ' unbenchmarked, ' . h((string) ($gateSummary['fail'] ?? 0)) . ' fail</td></tr>';
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
    $html .= '<p>The PHP path now runs an <code>--extract-media</code>-style pass in <strong>images we thought were important</strong> mode. Referenced package images are written beside the converted output and their <code>&lt;img&gt;</code> URLs are rewritten to hosted files; directly embeddable PDF streams are copied out, while JBIG2 streams are decoded through the bundled browser-compatible PDF.js/PDFium raster path into validated 1-bit PNG media.</p>';
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
 * @return array{ok:bool, path?:string, error?:string, renderedWithCiteproc?:bool}
 */
function haskell_pandoc_timeout_seconds(string $path): int
{
    return ShowcaseHaskellReferenceTimeout::secondsFor($path);
}

/**
 * Keep a successful external reference reusable when neither its source nor
 * the installed Haskell Pandoc changed. This prevents a pathological office
 * reader from replacing a known-good baseline with a timeout artifact during
 * an otherwise unrelated showcase rebuild.
 *
 * @return array{html:string,sourceSha256:string,pandocVersion:string,renderedWithCiteproc:bool}|null
 */
function cached_haskell_pandoc_reference(string $dir, string $path, string $format): ?array
{
    if (haskell_pandoc_timeout_seconds($path) <= 300) {
        return null;
    }
    $htmlPath = $dir . '/haskell.html';
    if (!is_file($htmlPath) || !is_file($path)) {
        return null;
    }
    $html = file_get_contents($htmlPath);
    $sourceSha256 = hash_file('sha256', $path);
    if (!is_string($html) || $html === '' || !is_string($sourceSha256) || $sourceSha256 === '') {
        return null;
    }

    $pandocVersion = haskell_pandoc_version_signature();
    $metadataPath = $dir . '/haskell.cache.json';
    if (is_file($metadataPath)) {
        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        if (!is_array($metadata)
            || ($metadata['sourceSha256'] ?? null) !== $sourceSha256
            || ($metadata['pandocVersion'] ?? null) !== $pandocVersion
            || ($metadata['format'] ?? null) !== $format) {
            return null;
        }
    }

    return [
        'html' => $html,
        'sourceSha256' => $sourceSha256,
        'pandocVersion' => $pandocVersion,
        'renderedWithCiteproc' => showcase_bibliography_input_format($format),
    ];
}

function haskell_pandoc_version_signature(): string
{
    static $signature = null;
    if (is_string($signature)) {
        return $signature;
    }
    $result = run_process(['pandoc', '--version'], 10);
    $firstLine = strtok(trim((string) ($result['stdout'] ?? '')), "\r\n");
    $signature = is_string($firstLine) && $firstLine !== ''
        ? sanitize_generated_text($firstLine)
        : 'pandoc-version-unavailable';

    return $signature;
}

/** @param array{html:string,sourceSha256:string,pandocVersion:string,renderedWithCiteproc:bool} $cached */
function restore_cached_haskell_pandoc_reference(string $dir, string $format, array $cached): array
{
    file_put_contents($dir . '/haskell.html', $cached['html']);
    file_put_contents($dir . '/haskell.cache.json', json_encode([
        'sourceSha256' => $cached['sourceSha256'],
        'pandocVersion' => $cached['pandocVersion'],
        'format' => $format,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    return [
        'ok' => true,
        'path' => 'outputs/' . basename($dir) . '/haskell.html',
        'renderedWithCiteproc' => $cached['renderedWithCiteproc'],
        'cached' => true,
    ];
}

function write_haskell_pandoc_cache_metadata(string $dir, string $path, string $format): void
{
    if (haskell_pandoc_timeout_seconds($path) <= 300) {
        return;
    }
    $sourceSha256 = is_file($path) ? hash_file('sha256', $path) : false;
    if (!is_string($sourceSha256) || $sourceSha256 === '') {
        return;
    }
    file_put_contents($dir . '/haskell.cache.json', json_encode([
        'sourceSha256' => $sourceSha256,
        'pandocVersion' => haskell_pandoc_version_signature(),
        'format' => $format,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function run_haskell_pandoc(string $path, string $format, string $dir): array
{
    $out = $dir . '/haskell.html';
    $bodyPath = $dir . '/haskell.body.html';
    $renderedWithCiteproc = showcase_bibliography_input_format($format);
    $command = ['pandoc', '--from=' . $format, '--to=html'];
    if ($renderedWithCiteproc) {
        $command[] = '--citeproc';
    }
    $command[] = '--output';
    $command[] = $bodyPath;
    $command[] = $path;
    // Readers such as LaTeX resolve \input, bibliography, and media paths
    // against the source document. Keep the external reference in that same
    // context instead of the showcase builder's working directory.
    $sourceDirectory = dirname($path);
    $result = run_process($command, haskell_pandoc_timeout_seconds($path), $sourceDirectory);
    if ($result['exitCode'] !== 0 || !is_file($bodyPath)) {
        $message = sanitize_generated_text(trim($result['stderr'] . "\n" . $result['stdout']));
        if ($message === '') {
            $message = 'pandoc exited with code ' . $result['exitCode'];
        }
        @unlink($bodyPath);
        file_put_contents($dir . '/haskell.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/haskell.html.error.txt'];
    }

    $body = file_get_contents($bodyPath);
    @unlink($bodyPath);
    if (!is_string($body)) {
        $message = 'pandoc produced unreadable HTML output';
        file_put_contents($dir . '/haskell.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/haskell.html.error.txt'];
    }
    file_put_contents($out, wrap_local_html_document(
        $body,
        $renderedWithCiteproc ? 'Haskell Pandoc Citeproc HTML output' : 'Haskell Pandoc HTML output'
    ));
    write_haskell_pandoc_cache_metadata($dir, $path, $format);

    return [
        'ok' => true,
        'path' => 'outputs/' . basename($dir) . '/haskell.html',
        'renderedWithCiteproc' => $renderedWithCiteproc,
    ];
}

/**
 * @return array{ok:bool,kind?:string,label?:string,path?:string,error?:string}|null
 */
function run_external_reference(string $path, string $format, string $dir): ?array
{
    return match (PandocConverter::canonicalInputFormat($format)) {
        'doc' => run_textutil_doc_reference($path, $dir),
        'pdf' => run_pdfkit_pdf_reference($path, $dir),
        'xml' => run_libxml_xml_reference($path, $dir),
        default => null,
    };
}

/**
 * @return array{ok:bool,kind?:string,label?:string,path?:string,error?:string}
 */
function run_textutil_doc_reference(string $path, string $dir): array
{
    $output = $dir . '/textutil.html';
    if (PHP_OS_FAMILY !== 'Darwin' || !is_executable('/usr/bin/textutil')) {
        $message = 'macOS TextUtil is unavailable for this legacy DOC reference conversion.';
        file_put_contents($dir . '/textutil.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/textutil.html.error.txt'];
    }

    $result = run_process(['/usr/bin/textutil', '-convert', 'html', '-encoding', 'UTF-8', '-stdout', $path], 45);
    $body = $result['exitCode'] === 0 ? showcase_textutil_reference_body($result['stdout']) : '';
    if ($body === '') {
        $message = sanitize_generated_text(trim($result['stderr'] . "\n" . $result['stdout']));
        if ($message === '') {
            $message = 'macOS TextUtil produced no readable HTML body.';
        }
        file_put_contents($dir . '/textutil.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/textutil.html.error.txt'];
    }

    file_put_contents($output, wrap_local_html_document($body, 'macOS TextUtil HTML reference'));

    return [
        'ok' => true,
        'kind' => 'macos-textutil-html',
        'label' => 'macOS TextUtil HTML reference',
        'path' => 'outputs/' . basename($dir) . '/textutil.html',
    ];
}

function showcase_textutil_reference_body(string $html): string
{
    $html = str_replace("\0", '', $html);
    if (preg_match('/<body\\b[^>]*>(.*)<\\/body>/is', $html, $matches) !== 1) {
        return '';
    }

    $body = preg_replace_callback('/<p\\b[^>]*>(.*?)<\\/p>/is', static function (array $match): string {
        $text = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = trim(preg_replace('/\\s+/u', ' ', $text) ?? $text);

        return $text === '' ? '' : $match[0];
    }, (string) $matches[1]);

    return trim($body ?? '');
}

/**
 * Use libxml2/libxslt as a source-side reference for bounded generic XML.
 * XML has no universal document vocabulary, so the accompanying stylesheet
 * only maps common structural element names and leaves unknown nodes as
 * transparent containers. It is an independently executed source-side
 * transform, not a PHP-output self-baseline.
 *
 * @return array{ok:bool,kind?:string,label?:string,path?:string,error?:string}
 */
function run_libxml_xml_reference(string $path, string $dir): array
{
    global $root;

    $output = $dir . '/xml-reference.html';
    $stylesheet = $root . '/tools/xml-reference.xsl';
    if (!is_executable('/usr/bin/xmllint') || !is_executable('/usr/bin/xsltproc') || !is_file($stylesheet)) {
        $message = 'libxml2/libxslt is unavailable for this generic XML reference conversion.';
        file_put_contents($dir . '/xml-reference.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/xml-reference.html.error.txt'];
    }

    $validation = run_process(['/usr/bin/xmllint', '--nonet', '--noout', $path], 45);
    if ($validation['exitCode'] !== 0) {
        $message = sanitize_generated_text(trim($validation['stderr'] . "\n" . $validation['stdout']));
        if ($message === '') {
            $message = 'libxml2 rejected the XML source.';
        }
        file_put_contents($dir . '/xml-reference.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/xml-reference.html.error.txt'];
    }

    $result = run_process(['/usr/bin/xsltproc', '--nonet', $stylesheet, $path], 45);
    $html = trim($result['stdout']);
    if ($result['exitCode'] !== 0 || $html === '' || stripos($html, '<html') === false) {
        $message = sanitize_generated_text(trim($result['stderr'] . "\n" . $result['stdout']));
        if ($message === '') {
            $message = 'libxslt produced no readable generic XML reference HTML.';
        }
        file_put_contents($dir . '/xml-reference.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/xml-reference.html.error.txt'];
    }

    file_put_contents($output, $html . "\n");

    return [
        'ok' => true,
        'kind' => 'libxml2-libxslt-generic-xml-html',
        'label' => 'libxml2/libxslt generic XML source reference',
        'path' => 'outputs/' . basename($dir) . '/xml-reference.html',
    ];
}

/**
 * @return array{ok:bool,kind?:string,label?:string,path?:string,dataPath?:string,metrics?:array<string,int>,error?:string}
 */
function run_pdfkit_pdf_reference(string $path, string $dir): array
{
    global $root;

    $output = $dir . '/pdfkit.html';
    $dataOutput = $dir . '/pdfkit-reference.json';
    $tool = $root . '/tools/pdfkit-reference.swift';
    if (PHP_OS_FAMILY !== 'Darwin' || !is_executable('/usr/bin/xcrun') || !is_file($tool)) {
        $message = 'macOS PDFKit is unavailable for this PDF reference conversion.';
        file_put_contents($dir . '/pdfkit.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/pdfkit.html.error.txt'];
    }

    $result = run_process(['/usr/bin/xcrun', 'swift', $tool, $path], 75);
    $reference = $result['exitCode'] === 0 ? json_decode($result['stdout'], true) : null;
    if (!showcase_pdfkit_reference_is_valid($reference)) {
        $message = sanitize_generated_text(trim($result['stderr']));
        if ($message === '') {
            $message = $result['exitCode'] === 0
                ? 'macOS PDFKit produced unreadable reference JSON.'
                : 'macOS PDFKit reference extraction exited with code ' . $result['exitCode'] . '.';
        }
        file_put_contents($dir . '/pdfkit.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/pdfkit.html.error.txt'];
    }

    $body = showcase_pdfkit_reference_body($reference);
    if ($body === '') {
        $message = 'macOS PDFKit did not expose readable page text for this PDF.';
        file_put_contents($dir . '/pdfkit.html.error.txt', $message);

        return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/pdfkit.html.error.txt'];
    }

    file_put_contents($output, wrap_local_html_document($body, 'macOS PDFKit text and geometry reference'));
    file_put_contents($dataOutput, json_encode($reference, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return [
        'ok' => true,
        'kind' => 'macos-pdfkit-text-geometry',
        'label' => 'macOS PDFKit text and geometry reference',
        'path' => 'outputs/' . basename($dir) . '/pdfkit.html',
        'dataPath' => 'outputs/' . basename($dir) . '/pdfkit-reference.json',
        'metrics' => showcase_pdfkit_reference_metrics($reference),
    ];
}

function showcase_pdfkit_reference_is_valid(mixed $reference): bool
{
    if (!is_array($reference) || !is_int($reference['pageCount'] ?? null) || !is_array($reference['pages'] ?? null)) {
        return false;
    }

    return $reference['pageCount'] > 0 && count($reference['pages']) === $reference['pageCount'];
}

/**
 * @param array{pageCount:int,pages:list<array<string,mixed>>} $reference
 */
function showcase_pdfkit_reference_body(array $reference): string
{
    $body = '<div class="pdfkit-reference" data-page-count="' . (int) $reference['pageCount'] . '">';
    foreach ($reference['pages'] as $page) {
        if (!is_array($page)) {
            continue;
        }
        $number = max(1, (int) ($page['number'] ?? 0));
        $text = trim((string) ($page['text'] ?? ''));
        if ($text === '' && is_array($page['lines'] ?? null)) {
            $text = implode("\n", array_map(
                static fn (mixed $line): string => is_array($line) ? trim((string) ($line['text'] ?? '')) : '',
                $page['lines']
            ));
            $text = trim($text);
        }
        $body .= '<div class="pdfkit-page" data-page="' . $number . '">';
        if ($text !== '') {
            $body .= '<div class="pdfkit-page-text" style="white-space:pre-wrap">'
                . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</div>';
        }
        $body .= '</div>';
    }

    return $body . '</div>';
}

/**
 * @param array{pageCount:int,pages:list<array<string,mixed>>} $reference
 * @return array<string,int>
 */
function showcase_pdfkit_reference_metrics(array $reference): array
{
    $pageTexts = [];
    $nonEmptyPages = 0;
    $lineCount = 0;
    foreach ($reference['pages'] as $page) {
        if (!is_array($page)) {
            continue;
        }
        $text = trim((string) ($page['text'] ?? ''));
        if ($text !== '') {
            $nonEmptyPages++;
            $pageTexts[] = $text;
        }
        if (is_array($page['lines'] ?? null)) {
            $lineCount += count($page['lines']);
        }
    }
    $text = implode("\n", $pageTexts);

    return [
        'pageCount' => (int) $reference['pageCount'],
        'textPageCount' => $nonEmptyPages,
        'lineCount' => $lineCount,
        'textBytes' => strlen($text),
        'tokenCount' => count(showcase_text_tokens($text)),
    ];
}

function showcase_bibliography_input_format(string $format): bool
{
    return in_array(PandocConverter::canonicalInputFormat($format), [
        'bibtex',
        'biblatex',
        'csljson',
        'endnotexml',
        'ris',
    ], true);
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

/**
 * @param array<string, mixed> $record
 * @return array{ok:bool,path:string,bytes:int}
 */
function showcase_examples_view(array $record, string $view, string $siteDir): array
{
    $result = is_array($record[$view] ?? null) ? $record[$view] : [];
    $ok = ($result['ok'] ?? false) === true;
    $path = ltrim((string) ($result['path'] ?? ''), '/');
    $absolutePath = $path === '' ? '' : $siteDir . '/' . $path;

    return [
        'ok' => $ok,
        'path' => $path,
        'bytes' => $ok && is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
    ];
}

/**
 * @param array<string, mixed> $record
 * @return array{ok:bool,path:string,bytes:int}
 */
function showcase_examples_wordpress_preview_view(array $record, string $siteDir): array
{
    $rawView = showcase_examples_view($record, 'wpBlocks', $siteDir);
    if (!$rawView['ok'] || $rawView['path'] === '') {
        return $rawView;
    }

    $rawPath = $siteDir . '/' . $rawView['path'];
    $rawBody = is_file($rawPath) ? file_get_contents($rawPath) : false;
    if (!is_string($rawBody)) {
        return ['ok' => false, 'path' => '', 'bytes' => 0];
    }

    $previewPath = dirname($rawView['path']) . '/wordpress-blocks-preview.html';
    $previewAbsolutePath = $siteDir . '/' . $previewPath;
    $previewBody = wrap_wordpress_block_preview_document($rawBody, 'PHP WordPress Block markup preview');
    if (file_put_contents($previewAbsolutePath, $previewBody) === false) {
        return ['ok' => false, 'path' => '', 'bytes' => 0];
    }

    return [
        'ok' => true,
        'path' => $previewPath,
        'bytes' => (int) filesize($previewAbsolutePath),
    ];
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{generatedAt:string,automaticViewMaxBytes:int,defaultExampleId:string,examples:list<array<string,mixed>>}
 */
function showcase_examples_index(array $records, string $siteDir, string $generatedAt): array
{
    $examples = [];
    foreach ($records as $record) {
        $id = (string) ($record['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $example = [
            'id' => $id,
            'format' => (string) ($record['format'] ?? ''),
            'label' => (string) ($record['label'] ?? $id),
            'description' => (string) ($record['description'] ?? ''),
            'source' => (string) ($record['source'] ?? ''),
            'sourceUrl' => (string) ($record['sourceUrl'] ?? ''),
            'samplePath' => ltrim((string) ($record['samplePath'] ?? ''), '/'),
            'sampleSize' => (int) ($record['sampleSize'] ?? 0),
            'views' => [
                'phpHtml' => showcase_examples_view($record, 'phpHtml', $siteDir),
                'wpBlocks' => showcase_examples_wordpress_preview_view($record, $siteDir),
                'haskell' => showcase_examples_view($record, 'haskell', $siteDir),
            ],
        ];
        $formRenders = is_array($record['pdfFormRenders'] ?? null) ? $record['pdfFormRenders'] : [];
        $formRenderPath = ltrim((string) ($formRenders['path'] ?? ''), '/');
        $formRenderAbsolutePath = $formRenderPath === '' ? '' : $siteDir . '/' . $formRenderPath;
        if (($formRenders['ok'] ?? false) === true && is_file($formRenderAbsolutePath)) {
            $example['pdfFormRenders'] = [
                'ok' => true,
                'path' => $formRenderPath,
                'bytes' => (int) filesize($formRenderAbsolutePath),
                'count' => (int) ($formRenders['count'] ?? 0),
            ];
        }
        $examples[] = $example;
    }

    $defaultExampleId = '';
    foreach ([
        'docx-tables',
        'html-reader',
        'pdf-quickbooks-invoice-template',
        'gfm-gitlab-markdown-guide',
    ] as $candidateId) {
        foreach ($examples as $example) {
            $view = is_array($example['views']['phpHtml'] ?? null) ? $example['views']['phpHtml'] : [];
            if (
                $example['id'] === $candidateId
                && ($view['ok'] ?? false) === true
                && (int) ($view['bytes'] ?? 0) <= SHOWCASE_EXAMPLES_AUTOMATIC_MAX_BYTES
            ) {
                $defaultExampleId = $candidateId;
                break 2;
            }
        }
    }
    if ($defaultExampleId === '') {
        foreach ($examples as $example) {
            $view = is_array($example['views']['phpHtml'] ?? null) ? $example['views']['phpHtml'] : [];
            if (($view['ok'] ?? false) === true) {
                $defaultExampleId = (string) $example['id'];
                break;
            }
        }
    }

    return [
        'generatedAt' => $generatedAt,
        'automaticViewMaxBytes' => SHOWCASE_EXAMPLES_AUTOMATIC_MAX_BYTES,
        'defaultExampleId' => $defaultExampleId,
        'examples' => $examples,
    ];
}

/**
 * Write a separate, mobile-friendly viewer. Static examples are loaded into
 * one disposable frame and replaced whenever the visitor changes the example
 * or view. The same frame can temporarily host WordPress Playground when a
 * visitor tries their own file.
 *
 * @param list<array<string, mixed>> $records
 */
function showcase_write_examples_page(string $siteDir, array $records, string $generatedAt): void
{
    $index = showcase_examples_index($records, $siteDir, $generatedAt);
    $indexJson = json_encode($index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($indexJson)) {
        throw new RuntimeException('Unable to encode the lightweight showcase example index.');
    }
    file_put_contents($siteDir . '/examples-index.json', $indexJson . "\n");

    $css = showcase_examples_css();
    $javascript = showcase_examples_javascript();
    // Version every asset that the lightweight page ships, not just its catalogue.
    // Otherwise a UI-only update can be hidden behind a stale CSS or JavaScript cache.
    $assetVersion = substr(hash('sha256', $indexJson . "\n" . $css . "\n" . $javascript), 0, 12);
    $page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    $page .= '<title>Adam&#039;s Pandoc → PHP Port</title><link rel="stylesheet" href="examples.css?v=' . h($assetVersion) . '"></head>';
    $page .= '<body><main class="example-browser"><div class="picker-area"><div class="example-toolbar">';
    $page .= '<button id="previous-example" class="example-arrow previous-arrow" type="button" aria-label="Previous example" title="Previous example" disabled><span class="arrow-glyph" aria-hidden="true">←</span><span class="arrow-label">Previous example</span></button>';
    $page .= '<h1 class="example-title">Adam&#039;s Pandoc → PHP Port</h1>';
    $page .= '<div class="picker-controls"><label class="screen-reader-text" for="example-picker">Example</label><select id="example-picker" disabled><option>Loading examples…</option></select></div>';
    $page .= '<a id="download-source" class="download-source" href="" download hidden>Download original</a>';
    $page .= '<button id="try-own-file" class="try-own-file" type="button">Try your own file</button><input id="own-file-input" type="file" hidden>';
    $page .= '<button id="next-example" class="example-arrow next-arrow" type="button" aria-label="Next example" title="Next example" disabled><span class="arrow-glyph" aria-hidden="true">→</span><span class="arrow-label">Next example</span></button></div>';
    $page .= '<p id="viewer-status" class="viewer-status" role="status" aria-live="polite" hidden>Preparing the selected example…</p></div>';
    $page .= '<div class="view-tabs" role="group" aria-label="Preview format">';
    $page .= '<button type="button" data-example-view="phpHtml" aria-pressed="false" disabled>HTML</button>';
    $page .= '<button type="button" data-example-view="wpBlocks" aria-pressed="true" disabled>WordPress Block markup</button>';
    $page .= '<button type="button" data-example-view="haskell" aria-pressed="false" disabled>Pandoc baseline</button></div>';
    $page .= '<section class="example-preview" aria-label="Example preview">';
    $page .= '<iframe id="example-frame" title="Selected converted example" sandbox hidden></iframe></section></main>';
    $page .= '<script type="module" src="examples.js?v=' . h($assetVersion) . '"></script></body></html>';
    file_put_contents($siteDir . '/examples.html', rtrim($page) . "\n");
    file_put_contents($siteDir . '/examples.css', rtrim($css) . "\n");
    file_put_contents($siteDir . '/examples.js', rtrim($javascript) . "\n");
    showcase_write_pdf_layout_corpus_review_page($siteDir, $index);
}

/** @param array<string,mixed> $examplesIndex */
function showcase_write_pdf_layout_corpus_review_page(string $siteDir, array $examplesIndex): void
{
    $manifestPath = __DIR__ . '/pdf-layout-corpus-manifest.json';
    $entries = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($entries)) {
        throw new RuntimeException("Unable to read PDF layout corpus manifest at {$manifestPath}.");
    }

    $examplesById = [];
    foreach ($examplesIndex['examples'] ?? [] as $example) {
        if (is_array($example) && is_string($example['id'] ?? null)) {
            $examplesById[$example['id']] = $example;
        }
    }

    $reviewEntries = [];
    foreach ($entries as $entry) {
        if (!is_array($entry) || !is_string($entry['id'] ?? null) || !is_string($entry['label'] ?? null)) {
            continue;
        }
        $exampleId = 'pdf-layout-' . $entry['id'];
        $example = $examplesById[$exampleId] ?? null;
        $previewPath = is_array($example['views']['wpBlocks'] ?? null)
            && ($example['views']['wpBlocks']['ok'] ?? false) === true
            ? (string) ($example['views']['wpBlocks']['path'] ?? '')
            : '';
        $samplePath = is_array($example) ? (string) ($example['samplePath'] ?? '') : '';
        if ($previewPath === '' || $samplePath === '') {
            throw new RuntimeException("PDF layout reviewer is missing generated paths for {$exampleId}.");
        }
        $reviewEntries[] = [
            'id' => $exampleId,
            'label' => $entry['label'],
            'kind' => (string) ($entry['kind'] ?? 'PDF layout'),
            'notes' => (string) ($entry['notes'] ?? ''),
            'source' => (string) ($entry['source'] ?? ''),
            'sourceUrl' => (string) ($entry['url'] ?? ''),
            'samplePath' => $samplePath,
            'previewPath' => $previewPath,
            'success' => is_array($entry['success'] ?? null) ? $entry['success'] : [],
        ];
    }
    if (count($reviewEntries) < 10) {
        throw new RuntimeException('PDF layout reviewer requires at least 10 generated corpus entries.');
    }

    $payload = json_encode([
        'generatedAt' => (string) ($examplesIndex['generatedAt'] ?? gmdate('c')),
        'examples' => $reviewEntries,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($payload)) {
        throw new RuntimeException('Unable to encode PDF layout reviewer data.');
    }
    $css = showcase_pdf_layout_reviewer_css();
    $javascript = showcase_pdf_layout_reviewer_javascript();
    $version = substr(hash('sha256', $payload . "\n" . $css . "\n" . $javascript), 0, 12);

    $page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>PDF layout reviewer</title><link rel="stylesheet" href="pdf-layout-review.css?v=' . h($version) . '"></head><body>'
        . '<main class="reviewer" data-active-view="compare"><header class="review-header">'
        . '<div class="review-heading"><div><h1>PDF layout reviewer</h1><p id="review-position">Loading the public test corpus…</p></div>'
        . '<div class="review-verdict" role="group" aria-label="Your review"><button type="button" data-verdict="pass">Looks good</button>'
        . '<button type="button" data-verdict="fail">Needs work</button></div></div>'
        . '<div class="review-toolbar"><button id="review-previous" class="review-arrow" type="button" aria-label="Previous document">←</button>'
        . '<label class="screen-reader-text" for="review-picker">PDF document</label><select id="review-picker"></select>'
        . '<a id="review-download" class="review-action" href="" download>Download original</a>'
        . '<a id="review-detail" class="review-action" href="examples.html">Open detail tabs</a>'
        . '<button id="review-next" class="review-arrow" type="button" aria-label="Next document">→</button></div>'
        . '<div class="review-context"><div><strong id="review-kind"></strong><span id="review-notes"></span><small id="review-source"></small></div>'
        . '<div class="view-switcher" role="group" aria-label="Reviewer view"><button type="button" data-review-view="compare" aria-pressed="true">Compare</button>'
        . '<button type="button" data-review-view="converted" aria-pressed="false">Converted</button><button type="button" data-review-view="original" aria-pressed="false">Original</button></div></div>'
        . '<details class="quality-panel" open><summary><span>Automatic success criteria</span><strong id="quality-summary">Waiting for preview</strong></summary>'
        . '<ul id="quality-criteria" aria-live="polite"></ul></details></header>'
        . '<section class="review-workspace" aria-label="PDF conversion comparison">'
        . '<article class="review-pane original-pane" data-pane="original"><div class="pane-title"><h2>Original PDF</h2><span id="original-status">Not loaded</span></div>'
        . '<div id="original-viewer" class="original-frame" role="document" aria-label="Original PDF document"><div id="original-pages"></div></div></article>'
        . '<article class="review-pane converted-pane" data-pane="converted"><div class="pane-title"><h2>Converted WordPress preview</h2><span id="converted-status">Loading</span></div>'
        . '<iframe id="converted-frame" title="Converted WordPress preview" sandbox="allow-same-origin"></iframe></article></section></main>'
        . '<script id="pdf-layout-review-data" type="application/json">' . $payload . '</script>'
        . '<script type="module" src="pdf-layout-review.js?v=' . h($version) . '"></script></body></html>';
    file_put_contents($siteDir . '/pdf-layout-corpus.html', $page . "\n");
    file_put_contents($siteDir . '/pdf-layout-review.css', rtrim($css) . "\n");
    file_put_contents($siteDir . '/pdf-layout-review.js', rtrim($javascript) . "\n");
}

function showcase_pdf_layout_reviewer_css(): string
{
    return <<<'CSS'
:root {
  color-scheme: light;
  --ink: #17212b;
  --muted: #5d6875;
  --line: #cfd8e3;
  --paper: #fff;
  --wash: #edf2f7;
  --accent: #1559c5;
  --ok: #08783f;
  --bad: #b12626;
}
* { box-sizing: border-box; }
html, body { min-width: 0; min-height: 100%; margin: 0; }
body { overflow: hidden; background: var(--wash); color: var(--ink); font: 14px/1.4 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
button, select, a { font: inherit; }
button, select, .review-action { min-height: 42px; border: 1px solid var(--line); border-radius: 7px; background: var(--paper); color: var(--ink); }
button, .review-action { cursor: pointer; }
button:hover, .review-action:hover { border-color: var(--accent); background: #eef5ff; }
button:focus-visible, select:focus-visible, a:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 32%, transparent); outline-offset: 1px; }
.screen-reader-text { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
.reviewer { display: grid; grid-template-rows: auto minmax(0, 1fr); width: 100%; height: 100svh; }
.review-header { position: relative; z-index: 10; isolation: isolate; transform: translateZ(0); display: grid; gap: 9px; padding: 12px clamp(12px, 2vw, 24px); border-bottom: 1px solid var(--line); background: var(--paper); box-shadow: 0 2px 10px rgba(23, 33, 43, .08); }
.review-heading, .review-context { display: flex; align-items: center; justify-content: space-between; gap: 18px; min-width: 0; }
.review-heading h1 { margin: 0; font-size: clamp(20px, 2.1vw, 28px); line-height: 1.05; }
.review-heading p { margin: 3px 0 0; color: var(--muted); }
.review-verdict, .view-switcher { display: flex; gap: 5px; flex: none; }
.review-verdict button, .view-switcher button { min-height: 34px; padding: 5px 11px; }
.review-verdict button[aria-pressed="true"][data-verdict="pass"] { border-color: var(--ok); background: #e6f6ed; color: #04572d; }
.review-verdict button[aria-pressed="true"][data-verdict="fail"] { border-color: var(--bad); background: #fff0f0; color: #8d1717; }
.review-toolbar { display: grid; grid-template-columns: 54px minmax(220px, 1fr) auto auto 54px; gap: 7px; min-width: 0; }
.review-toolbar select { width: 100%; min-width: 0; padding: 0 34px 0 12px; font-weight: 650; }
.review-arrow { padding: 0; font-size: 26px; line-height: 1; }
.review-action { display: inline-flex; align-items: center; justify-content: center; padding: 0 13px; text-decoration: none; white-space: nowrap; }
.review-context > div:first-child { display: flex; align-items: baseline; gap: 9px; min-width: 0; overflow: hidden; }
.review-context strong { flex: none; color: var(--accent); font-size: 12px; letter-spacing: .05em; text-transform: uppercase; }
.review-context span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.review-context small { color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.view-switcher button[aria-pressed="true"] { border-color: var(--accent); background: var(--accent); color: #fff; }
.quality-panel { min-width: 0; border-top: 1px solid #e2e8f0; padding-top: 7px; }
.quality-panel summary { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--muted); }
.quality-panel summary strong { margin-left: auto; font-size: 12px; }
.quality-panel ul { display: flex; flex-wrap: wrap; gap: 5px; margin: 7px 0 0; padding: 0; list-style: none; }
.quality-panel li { padding: 3px 8px; border: 1px solid var(--line); border-radius: 999px; background: #f8fafc; font-size: 12px; }
.quality-panel li[data-status="pass"] { border-color: #8ac7a6; background: #edf9f2; color: #075b31; }
.quality-panel li[data-status="fail"] { border-color: #e1a0a0; background: #fff1f1; color: #8d1717; }
.quality-panel li[data-status="pending"] { color: var(--muted); }
.review-workspace { position: relative; z-index: 0; isolation: isolate; contain: paint; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1px; min-width: 0; min-height: 0; background: var(--line); }
.review-pane { display: grid; grid-template-rows: auto minmax(0, 1fr); min-width: 0; min-height: 0; background: var(--paper); }
.pane-title { display: flex; justify-content: space-between; align-items: center; gap: 12px; min-height: 34px; padding: 6px 12px; border-bottom: 1px solid var(--line); }
.pane-title h2 { margin: 0; font-size: 13px; }
.pane-title span { color: var(--muted); font-size: 12px; }
.review-pane iframe { display: block; width: 100%; height: 100%; min-width: 0; min-height: 0; border: 0; background: #fff; }
.original-frame { width: 100%; height: 100%; min-width: 0; min-height: 0; overflow: auto; padding: 18px; background: #343a40; }
#original-pages { display: grid; justify-items: center; gap: 18px; min-width: 0; }
.original-page { display: block; max-width: 100%; height: auto; background: #fff; box-shadow: 0 3px 16px rgba(0, 0, 0, .34); }
.original-error { align-self: start; max-width: 560px; margin: 12px auto; padding: 14px; border-radius: 8px; background: #fff0f0; color: var(--bad); }
.reviewer[data-active-view="converted"] .review-workspace, .reviewer[data-active-view="original"] .review-workspace { grid-template-columns: minmax(0, 1fr); }
.reviewer[data-active-view="converted"] [data-pane="original"], .reviewer[data-active-view="original"] [data-pane="converted"] { display: none; }
@media (max-width: 820px) {
  body { overflow: auto; }
  .reviewer { height: auto; min-height: 100svh; grid-template-rows: auto minmax(70svh, 1fr); }
  .review-header { position: sticky; top: 0; padding: 9px; }
  .review-heading { align-items: flex-start; }
  .review-heading h1 { font-size: 18px; }
  .review-heading p { font-size: 12px; }
  .review-verdict button { min-height: 32px; padding: 4px 8px; font-size: 12px; }
  .review-toolbar { grid-template-columns: 48px minmax(0, 1fr) 48px; }
  .review-toolbar .review-action { min-height: 36px; }
  #review-download { grid-column: 1 / span 2; }
  #review-detail { grid-column: 3; padding: 0 8px; font-size: 0; }
  #review-detail::after { content: "Tabs"; font-size: 12px; }
  #review-next { grid-column: 3; grid-row: 1; }
  .review-context { align-items: flex-start; }
  .review-context > div:first-child { display: grid; gap: 1px; }
  .review-context small { display: none; }
  .view-switcher button { padding: 4px 8px; font-size: 12px; }
  .quality-panel:not([open]) { padding-bottom: 0; }
  .review-workspace { min-height: 70svh; }
  .reviewer[data-active-view="compare"] .review-workspace { grid-template-columns: 1fr; grid-template-rows: minmax(55svh, 1fr) minmax(55svh, 1fr); }
}
CSS;
}

function showcase_pdf_layout_reviewer_javascript(): string
{
    return <<<'JS'
const payload = JSON.parse(document.querySelector('#pdf-layout-review-data')?.textContent || '{}');
const examples = Array.isArray(payload.examples) ? payload.examples : [];
if (examples.length < 1) throw new Error('The PDF layout reviewer has no examples.');

const root = document.querySelector('.reviewer');
const picker = document.querySelector('#review-picker');
const previous = document.querySelector('#review-previous');
const next = document.querySelector('#review-next');
const download = document.querySelector('#review-download');
const detail = document.querySelector('#review-detail');
const position = document.querySelector('#review-position');
const kind = document.querySelector('#review-kind');
const notes = document.querySelector('#review-notes');
const source = document.querySelector('#review-source');
const originalFrame = document.querySelector('#original-viewer');
const originalPages = document.querySelector('#original-pages');
const convertedFrame = document.querySelector('#converted-frame');
const originalStatus = document.querySelector('#original-status');
const convertedStatus = document.querySelector('#converted-status');
const criteriaList = document.querySelector('#quality-criteria');
const qualitySummary = document.querySelector('#quality-summary');
const viewButtons = [...document.querySelectorAll('[data-review-view]')];
const verdictButtons = [...document.querySelectorAll('[data-verdict]')];
const storagePrefix = 'port-libs:pdf-layout-review:';
let pdfjsLibraryPromise = null;
let originalLoadingTask = null;
let originalDocument = null;
let originalRenderGeneration = 0;
let activeIndex = 0;
let activeView = new URL(location.href).searchParams.get('view');
if (!['compare', 'converted', 'original'].includes(activeView)) {
  activeView = matchMedia('(max-width: 820px)').matches ? 'converted' : 'compare';
}

for (const example of examples) {
  const option = document.createElement('option');
  option.value = example.id;
  option.textContent = example.label;
  picker.append(option);
}

function selectedExample() { return examples[activeIndex]; }
function updateUrl() {
  const url = new URL(location.href);
  url.searchParams.set('example', selectedExample().id);
  url.searchParams.set('view', activeView);
  history.replaceState(null, '', url);
}
function setFramePath(frame, path) {
  if (frame.dataset.loadedPath === path) return;
  frame.dataset.loadedPath = path;
  frame.src = path;
}
function pdfjsLibrary() {
  if (!pdfjsLibraryPromise) {
    pdfjsLibraryPromise = import('./vendor/pdfjs/pdf.min.mjs').then((library) => {
      library.GlobalWorkerOptions.workerSrc = new URL('./vendor/pdfjs/pdf.worker.min.mjs', location.href).href;
      return library;
    });
  }
  return pdfjsLibraryPromise;
}
function cancelOriginalRender(clear = true) {
  originalRenderGeneration += 1;
  const loadingTask = originalLoadingTask;
  const pdfDocument = originalDocument;
  originalLoadingTask = null;
  originalDocument = null;
  if (loadingTask?.destroy) void loadingTask.destroy().catch(() => {});
  if (pdfDocument?.destroy) void pdfDocument.destroy().catch(() => {});
  delete originalFrame.dataset.requestedPath;
  delete originalFrame.dataset.loadedPath;
  if (clear) originalPages.replaceChildren();
}
async function renderOriginalPdf(example) {
  const path = example.samplePath;
  if (originalFrame.dataset.loadedPath === path && originalPages.querySelector('canvas')) return;
  if (originalFrame.dataset.requestedPath === path) return;
  cancelOriginalRender();
  const generation = originalRenderGeneration;
  originalFrame.dataset.requestedPath = path;
  originalStatus.textContent = 'Loading original';
  try {
    const library = await pdfjsLibrary();
    if (generation !== originalRenderGeneration) return;
    const base = new URL('.', location.href);
    const task = library.getDocument({
      url: new URL(path, base).href,
      cMapUrl: new URL('vendor/pdfjs/cmaps/', base).href,
      cMapPacked: true,
      standardFontDataUrl: new URL('vendor/pdfjs/standard_fonts/', base).href,
      wasmUrl: new URL('vendor/pdfjs/wasm/', base).href,
    });
    originalLoadingTask = task;
    const pdfDocument = await task.promise;
    if (generation !== originalRenderGeneration) {
      await pdfDocument.destroy();
      return;
    }
    originalLoadingTask = null;
    originalDocument = pdfDocument;
    for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
      if (generation !== originalRenderGeneration) return;
      originalStatus.textContent = `Rendering original page ${pageNumber} of ${pdfDocument.numPages}`;
      const page = await pdfDocument.getPage(pageNumber);
      const baseViewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.max(240, originalFrame.clientWidth - 36);
      const cssScale = Math.min(1.45, availableWidth / Math.max(1, baseViewport.width));
      const outputScale = Math.min(2, Math.max(1, devicePixelRatio || 1));
      const viewport = page.getViewport({ scale: cssScale * outputScale });
      const canvas = document.createElement('canvas');
      canvas.className = 'original-page';
      canvas.width = Math.max(1, Math.ceil(viewport.width));
      canvas.height = Math.max(1, Math.ceil(viewport.height));
      canvas.style.width = `${viewport.width / outputScale}px`;
      canvas.style.height = `${viewport.height / outputScale}px`;
      canvas.setAttribute('aria-label', `Original PDF page ${pageNumber}`);
      originalPages.append(canvas);
      const context = canvas.getContext('2d', { alpha: false });
      if (!context) throw new Error('Canvas rendering is unavailable.');
      context.fillStyle = '#fff';
      context.fillRect(0, 0, canvas.width, canvas.height);
      await page.render({ canvasContext: context, viewport }).promise;
      page.cleanup();
    }
    if (generation !== originalRenderGeneration) return;
    originalFrame.dataset.loadedPath = path;
    originalStatus.textContent = `Original loaded · ${pdfDocument.numPages} page${pdfDocument.numPages === 1 ? '' : 's'}`;
  } catch (error) {
    if (generation !== originalRenderGeneration) return;
    delete originalFrame.dataset.requestedPath;
    delete originalFrame.dataset.loadedPath;
    originalPages.replaceChildren();
    const message = document.createElement('p');
    message.className = 'original-error';
    message.textContent = error instanceof Error ? error.message : String(error);
    originalPages.append(message);
    originalStatus.textContent = 'Could not render original';
  }
}
function unloadOriginalWhenHidden() {
  if (activeView !== 'converted') return;
  cancelOriginalRender();
  originalStatus.textContent = 'Not loaded in converted-only view';
}
function renderVerdict() {
  let verdict = '';
  try { verdict = localStorage.getItem(storagePrefix + selectedExample().id) || ''; } catch {}
  for (const button of verdictButtons) button.setAttribute('aria-pressed', String(button.dataset.verdict === verdict));
}
function setView(view, updateHistory = true) {
  if (!['compare', 'converted', 'original'].includes(view)) return;
  activeView = view;
  root.dataset.activeView = view;
  for (const button of viewButtons) button.setAttribute('aria-pressed', String(button.dataset.reviewView === view));
  const example = selectedExample();
  if (view !== 'converted') {
    void renderOriginalPdf(example);
  } else {
    unloadOriginalWhenHidden();
  }
  if (view !== 'original') setFramePath(convertedFrame, example.previewPath);
  if (updateHistory) updateUrl();
}
function criterionLabel(key, expected) {
  const labels = {
    minTextBytes: `At least ${expected} visible-text bytes`,
    minParagraphs: `At least ${expected} paragraphs`,
    minHeadings: `At least ${expected} headings`,
    minTables: `At least ${expected} tables`,
    maxTables: `No more than ${expected} tables`,
    minLists: `At least ${expected} lists`,
    minCodeBlocks: `At least ${expected} code blocks`,
    maxCodeBlocks: `No more than ${expected} code blocks`,
    minLineOrientedBlocks: `At least ${expected} line-oriented blocks`,
    maxLineOrientedBlocks: `No more than ${expected} line-oriented blocks`,
    minDialogueParagraphs: `At least ${expected} editable dialogue paragraphs`,
    maxSingleGlyphParagraphs: `No more than ${expected} single-glyph paragraphs`,
    requiredText: `Required text: ${(Array.isArray(expected) ? expected : [expected]).join(' · ')}`,
    orderedText: `Reading order: ${(Array.isArray(expected) ? expected : [expected]).join(' → ')}`,
    allowNoText: 'Image-only source may have no extracted text',
  };
  return labels[key] || `${key}: ${JSON.stringify(expected)}`;
}
function renderCriteria(results = null) {
  const success = selectedExample().success || {};
  criteriaList.replaceChildren();
  const entries = Object.entries(success);
  entries.push(['noSpacedGlyphRuns', true], ['noHorizontalOverflow', true], ['readablePdfFills', true]);
  const extraLabels = {
    noSpacedGlyphRuns: 'No sustained inter-glyph spacing',
    noHorizontalOverflow: 'No horizontal overflow',
    readablePdfFills: 'Readable text on PDF-derived fills',
  };
  for (const [key, expected] of entries) {
    const item = document.createElement('li');
    item.textContent = extraLabels[key] || criterionLabel(key, expected);
    item.dataset.status = results ? (results[key] ? 'pass' : 'fail') : 'pending';
    criteriaList.append(item);
  }
  if (!results) {
    qualitySummary.textContent = 'Waiting for preview';
    return;
  }
  const failed = Object.values(results).filter((passed) => !passed).length;
  qualitySummary.textContent = failed === 0 ? 'All automatic checks pass' : `${failed} automatic check${failed === 1 ? '' : 's'} need attention`;
  qualitySummary.style.color = failed === 0 ? 'var(--ok)' : 'var(--bad)';
}
function iframeMetrics(documentNode) {
  const bodyText = String(documentNode.body?.innerText || '').replace(/\s+/g, ' ').trim();
  const paragraphs = [...documentNode.querySelectorAll('p')];
  const compactLength = (value) => Array.from(String(value || '').replace(/\s+/gu, '')).length;
  const singleGlyphParagraphs = paragraphs.filter((node) => compactLength(node.textContent) === 1).length;
  const spacedGlyphPattern = /(?:^|[^\p{L}\p{M}])(?:[\p{L}\p{M}]\s+){4,}[\p{L}\p{M}](?=$|[^\p{L}\p{M}])/gu;
  let spacedGlyphRuns = 0;
  for (const node of documentNode.querySelectorAll('p,h1,h2,h3,h4,h5,h6,li,pre,td,th')) {
    spacedGlyphRuns += [...String(node.textContent || '').matchAll(spacedGlyphPattern)].length;
  }
  const rgb = (value) => {
    const match = String(value || '').match(/^rgba?\(\s*(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)/i);
    return match ? match.slice(1, 4).map(Number) : null;
  };
  const luminance = (channels) => channels.map((channel) => channel / 255).map((channel) => channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4).reduce((sum, channel, index) => sum + channel * [.2126, .7152, .0722][index], 0);
  let lowContrastPdfFills = 0;
  for (const node of documentNode.querySelectorAll('[data-pdf-fill-color]')) {
    if (!String(node.textContent || '').trim()) continue;
    const style = documentNode.defaultView?.getComputedStyle(node);
    if (!style) continue;
    const foreground = rgb(style.color);
    const background = rgb(style.backgroundColor);
    if (!foreground || !background) continue;
    const ratio = (Math.max(luminance(foreground), luminance(background)) + .05) / (Math.min(luminance(foreground), luminance(background)) + .05);
    if (ratio < 4.5) lowContrastPdfFills += 1;
  }
  return {
    bodyText,
    textBytes: new TextEncoder().encode(bodyText).length,
    paragraphs: paragraphs.length,
    headings: documentNode.querySelectorAll('h1,h2,h3,h4,h5,h6').length,
    tables: documentNode.querySelectorAll('table').length,
    lists: documentNode.querySelectorAll('ul,ol').length,
    codeBlocks: documentNode.querySelectorAll('pre.wp-block-code,.wp-block-code pre').length,
    lineOrientedBlocks: documentNode.querySelectorAll('pre.wp-block-verse,.wp-block-verse').length,
    dialogueParagraphs: documentNode.querySelectorAll('p > strong:first-child + br').length,
    singleGlyphParagraphs,
    spacedGlyphRuns,
    lowContrastPdfFills,
    horizontalOverflow: Math.max(0, (documentNode.documentElement?.scrollWidth || 0) - (documentNode.documentElement?.clientWidth || 0)),
  };
}
function evaluateCriteria(metrics) {
  const criteria = selectedExample().success || {};
  const result = {};
  const comparisons = {
    minTextBytes: (value) => metrics.textBytes >= value,
    minParagraphs: (value) => metrics.paragraphs >= value,
    minHeadings: (value) => metrics.headings >= value,
    minTables: (value) => metrics.tables >= value,
    maxTables: (value) => metrics.tables <= value,
    minLists: (value) => metrics.lists >= value,
    minCodeBlocks: (value) => metrics.codeBlocks >= value,
    maxCodeBlocks: (value) => metrics.codeBlocks <= value,
    minLineOrientedBlocks: (value) => metrics.lineOrientedBlocks >= value,
    maxLineOrientedBlocks: (value) => metrics.lineOrientedBlocks <= value,
    minDialogueParagraphs: (value) => metrics.dialogueParagraphs >= value,
    maxSingleGlyphParagraphs: (value) => metrics.singleGlyphParagraphs <= value,
    allowNoText: () => true,
    requiredText: (values) => values.every((value) => metrics.bodyText.includes(String(value))),
    orderedText: (values) => {
      let offset = -1;
      return values.every((value) => {
        offset = metrics.bodyText.indexOf(String(value), offset + 1);
        return offset >= 0;
      });
    },
  };
  for (const [key, expected] of Object.entries(criteria)) result[key] = comparisons[key] ? comparisons[key](expected) : true;
  result.noSpacedGlyphRuns = metrics.spacedGlyphRuns === 0;
  result.noHorizontalOverflow = metrics.horizontalOverflow <= 2;
  result.readablePdfFills = metrics.lowContrastPdfFills === 0;
  return result;
}
function selectExample(index, updateHistory = true) {
  activeIndex = (index + examples.length) % examples.length;
  const example = selectedExample();
  picker.value = example.id;
  position.textContent = `${activeIndex + 1} of ${examples.length} public corpus documents`;
  kind.textContent = example.kind;
  notes.textContent = example.notes;
  source.textContent = example.source;
  download.href = example.samplePath;
  download.download = example.samplePath.split('/').pop() || 'original.pdf';
  detail.href = `examples.html?example=${encodeURIComponent(example.id)}`;
  document.title = `${example.label} · PDF layout reviewer`;
  convertedStatus.textContent = 'Loading conversion';
  renderCriteria();
  setFramePath(convertedFrame, example.previewPath);
  if (activeView !== 'converted') {
    void renderOriginalPdf(example);
  } else {
    unloadOriginalWhenHidden();
  }
  renderVerdict();
  if (updateHistory) updateUrl();
}

picker.addEventListener('change', () => selectExample(examples.findIndex((example) => example.id === picker.value)));
previous.addEventListener('click', () => selectExample(activeIndex - 1));
next.addEventListener('click', () => selectExample(activeIndex + 1));
for (const button of viewButtons) button.addEventListener('click', () => setView(button.dataset.reviewView));
for (const button of verdictButtons) button.addEventListener('click', () => {
  const selected = button.getAttribute('aria-pressed') === 'true' ? '' : button.dataset.verdict;
  try {
    if (selected) localStorage.setItem(storagePrefix + selectedExample().id, selected);
    else localStorage.removeItem(storagePrefix + selectedExample().id);
  } catch {}
  renderVerdict();
});
convertedFrame.addEventListener('load', () => {
  if (convertedFrame.dataset.loadedPath !== selectedExample().previewPath) return;
  try {
    const metrics = iframeMetrics(convertedFrame.contentDocument);
    renderCriteria(evaluateCriteria(metrics));
    convertedStatus.textContent = `${metrics.textBytes.toLocaleString()} text bytes`;
  } catch (error) {
    convertedStatus.textContent = 'Could not inspect preview';
    qualitySummary.textContent = error instanceof Error ? error.message : String(error);
    qualitySummary.style.color = 'var(--bad)';
  }
});
addEventListener('keydown', (event) => {
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement || event.target instanceof HTMLTextAreaElement) return;
  if (event.key === 'ArrowLeft') selectExample(activeIndex - 1);
  if (event.key === 'ArrowRight') selectExample(activeIndex + 1);
});

const requestedId = new URL(location.href).searchParams.get('example');
const requestedIndex = examples.findIndex((example) => example.id === requestedId);
activeIndex = requestedIndex >= 0 ? requestedIndex : 0;
setView(activeView, false);
selectExample(activeIndex);
JS;
}

function showcase_examples_css(): string
{
    return <<<'CSS'
:root {
  color-scheme: light;
  --ink: #18212b;
  --line: #d6dee8;
  --paper: #ffffff;
  --wash: #f4f7fb;
  --accent: #165dcc;
  --accent-ink: #ffffff;
}
* { box-sizing: border-box; }
html,
body { min-height: 100%; }
body {
  min-height: 100dvh;
  margin: 0;
  color: var(--ink);
  background: var(--wash);
  font: 16px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
button,
select { font: inherit; }
button {
  min-height: 44px;
  padding: 8px 14px;
  border: 1px solid #aeb9c7;
  border-radius: 8px;
  background: #fff;
  color: var(--ink);
  cursor: pointer;
}
button:focus-visible,
select:focus-visible,
a:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: -3px; }
button:disabled,
select:disabled { cursor: not-allowed; opacity: .58; }
.example-browser {
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
  width: 100%;
  max-width: 100%;
  min-height: 100dvh;
  min-width: 0;
  overflow-x: hidden;
  background: var(--paper);
}
.picker-area {
  --arrow-width: clamp(88px, 8vw, 120px);
  --toolbar-gap: 10px;
  padding: 12px clamp(14px, 3vw, 48px) 10px;
  min-width: 0;
}
.example-title {
  grid-area: title;
  align-self: end;
  margin: 0;
  font-size: clamp(15px, 1.8vw, 20px);
  line-height: 1.25;
}
.example-toolbar {
  display: grid;
  grid-template-columns: var(--arrow-width) minmax(0, 1fr) auto auto var(--arrow-width);
  grid-template-rows: auto 48px;
  grid-template-areas:
    "previous title . . next"
    "previous picker download own next";
  align-items: stretch;
  column-gap: var(--toolbar-gap);
  row-gap: 6px;
  min-width: 0;
}
.picker-controls {
  grid-area: picker;
  min-width: 0;
}
#example-picker {
  width: 100%;
  min-width: 0;
  height: 48px;
  min-height: 48px;
  padding: 8px 12px;
  border: 1px solid #aeb9c7;
  border-radius: 8px;
  background: #fff;
  color: var(--ink);
}
.download-source,
.try-own-file {
  display: inline-flex;
  align-self: stretch;
  align-items: center;
  justify-content: center;
  height: 48px;
  min-height: 48px;
  padding: 8px 14px;
  border: 1px solid #aeb9c7;
  border-radius: 8px;
  background: #fff;
  color: var(--ink);
  font-weight: 600;
  white-space: nowrap;
}
.download-source {
  grid-area: download;
  text-decoration: none;
}
.try-own-file {
  grid-area: own;
}
.download-source:hover { border-color: var(--accent); background: #e6eefb; }
.try-own-file:hover:not(:disabled) { border-color: var(--accent); background: #e6eefb; }
.download-source[aria-disabled="true"] { cursor: wait; opacity: .58; pointer-events: none; }
.view-tabs {
  display: flex;
  grid-row: 2;
  gap: 0;
  min-width: 0;
  overflow-x: auto;
  padding: 0 clamp(14px, 3vw, 48px);
  background: var(--wash);
  border-bottom: 1px solid var(--line);
}
.view-tabs button {
  flex: 0 0 auto;
  position: relative;
  z-index: 0;
  min-height: 46px;
  margin: 8px 2px -1px 0;
  padding: 9px 16px;
  border: 1px solid transparent;
  border-bottom: 0;
  border-radius: 8px 8px 0 0;
  background: transparent;
  color: #3e4a59;
  font-weight: 700;
}
.view-tabs button:hover:not(:disabled) { background: #e6eefb; }
.view-tabs button[aria-pressed="true"] {
  z-index: 1;
  border-color: var(--line);
  background: var(--paper);
  color: var(--ink);
  box-shadow: 0 1px 0 var(--paper);
}
.example-arrow {
  display: flex;
  align-self: stretch;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 0;
  padding: 8px 6px;
  border: 1px solid #aeb9c7;
  border-radius: 8px;
  background: #fff;
  color: var(--accent);
}
.example-arrow:hover:not(:disabled) { border-color: var(--accent); background: #e6eefb; }
.arrow-glyph { font-size: 42px; line-height: .9; }
.arrow-label { margin-top: 7px; font-size: 12px; font-weight: 700; line-height: 1.15; text-align: center; }
.previous-arrow {
  grid-area: previous;
}
.next-arrow {
  grid-area: next;
}
.viewer-status {
  margin: 8px 0 0;
  padding: 8px 10px;
  border: 1px solid #b8c7dc;
  border-radius: 8px;
  background: #f3f7fd;
  color: #24446d;
  font-size: 14px;
  line-height: 1.35;
}
.viewer-status[hidden] { display: none; }
.viewer-status[data-tone="error"] {
  border-color: #d98f8f;
  background: #fff5f5;
  color: #8d2525;
}
.example-preview {
  display: grid;
  grid-row: 3;
  min-width: 0;
  min-height: 0;
  border: 1px solid var(--line);
  border-top: 0;
  background: #fff;
}
#example-frame {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 0;
  border: 0;
  background: #fff;
}
.screen-reader-text {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
@media (max-width: 640px) {
  .picker-area { --arrow-width: clamp(54px, 17vw, 66px); --toolbar-gap: 8px; width: 100%; max-width: 100%; padding: 10px; }
  .example-title { font-size: 16px; }
  .example-toolbar {
    grid-template-columns: var(--arrow-width) minmax(0, 1fr) var(--arrow-width);
    grid-template-rows: auto 48px 48px 48px;
    grid-template-areas:
      "previous title next"
      "previous picker next"
      "previous download next"
      "previous own next";
    row-gap: 8px;
  }
  .download-source,
  .try-own-file { min-width: 0; width: 100%; padding-inline: 8px; white-space: normal; }
  .example-arrow { position: static; min-height: 0; }
  .arrow-glyph { font-size: 34px; }
  .arrow-label { margin-top: 5px; font-size: 10px; }
  .view-tabs { padding-inline: 6px; }
  .view-tabs button { min-height: 42px; margin-top: 6px; padding: 8px 10px; font-size: 13px; }
}
CSS;
}

function showcase_examples_javascript(): string
{
    return <<<'JS'
import { renderPdfFormRequests } from './pdfjs-form-rasterizer.mjs';

const catalogUrl = 'examples-index.json';
const viewLabels = {
  phpHtml: 'HTML',
  wpBlocks: 'WordPress Block markup',
  haskell: 'Pandoc baseline',
};
const defaultView = 'wpBlocks';
const exampleUrlParameter = 'example';
const playgroundPluginBuild = 'pdf-marker-safe-forms-20260716';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';
const playgroundUploadDirectory = '/tmp/port-libs-converter';
const playgroundPdfRasterByteLimit = 24_000_000;
const playgroundPdfFormTotalPixelLimit = 48_000_000;
const playgroundPdfFormTotalImageByteLimit = 24_000_000;
const ownFileStatusPollIntervalMs = 1_000;
const ownFileAdvanceRecoveryAttempts = 3;
// The static example browser runs on the visitor's device, including phones.
// Keep Form-XObject enrichment deliberately smaller than the importer handoff:
// it is an optional preview, never a reason to exhaust the browser.
const staticPdfPreviewMaxSourceBytes = 4_000_000;
const staticPdfPreviewMaxRequests = 8;
const staticPdfPreviewMaxPixels = 2_000_000;
const staticPdfPreviewMaxTotalPixels = 8_000_000;
const staticPdfPreviewMaxImageBytes = 8_000_000;

const examplePicker = document.getElementById('example-picker');
const previousButton = document.getElementById('previous-example');
const nextButton = document.getElementById('next-example');
const viewButtons = Array.from(document.querySelectorAll('[data-example-view]'));
const viewerStatus = document.getElementById('viewer-status');
const downloadSource = document.getElementById('download-source');
const tryOwnFileButton = document.getElementById('try-own-file');
const ownFileInput = document.getElementById('own-file-input');
const frame = document.getElementById('example-frame');

const state = {
  examples: [],
  selectedId: '',
  defaultExampleId: '',
  view: defaultView,
  automaticViewMaxBytes: 0,
  loadToken: 0,
  ownFileToken: 0,
  ownFileBusy: false,
  frameMode: 'example',
  playgroundClient: null,
  playgroundReady: false,
  playgroundBootPromise: null,
  startPlaygroundWeb: null,
  decodePdfJbig2Rasters: null,
  decodePdfJpxRasters: null,
  staticPdfPreviewCache: new Map(),
  staticPdfPreviewAbortController: null,
  lastOwnFileJob: null,
};

function selectedExample() {
  return state.examples.find((example) => example.id === state.selectedId) || null;
}

function selectedView(example = selectedExample()) {
  return example && example.views ? example.views[state.view] || null : null;
}

function isBrowsableView(view) {
  return Boolean(view && view.ok && view.path && view.bytes > 0
    && view.bytes <= state.automaticViewMaxBytes);
}

function browsableExamples() {
  return state.examples.filter((example) => isBrowsableView(example.views && example.views.phpHtml));
}

function setStatus(message, { visible = false, tone = 'info' } = {}) {
  viewerStatus.textContent = message;
  viewerStatus.hidden = !visible;
  if (visible) {
    viewerStatus.dataset.tone = tone;
  } else {
    delete viewerStatus.dataset.tone;
  }
}

function createOption(value, label) {
  const option = document.createElement('option');
  option.value = value;
  option.textContent = label;
  return option;
}

function exampleIdFromUrl() {
  return new URL(window.location.href).searchParams.get(exampleUrlParameter);
}

function syncExampleUrl() {
  const url = new URL(window.location.href);
  const currentExampleId = url.searchParams.get(exampleUrlParameter);
  if (state.selectedId) {
    if (currentExampleId === state.selectedId) {
      return;
    }
    url.searchParams.set(exampleUrlParameter, state.selectedId);
  } else {
    if (currentExampleId === null) {
      return;
    }
    url.searchParams.delete(exampleUrlParameter);
  }

  window.history.replaceState(null, '', url);
}

function ensureBrowsableView() {
  if (isBrowsableView(selectedView())) {
    return;
  }

  const example = selectedExample();
  for (const fallbackView of [defaultView, 'phpHtml', 'haskell']) {
    const view = example && example.views ? example.views[fallbackView] : null;
    if (isBrowsableView(view)) {
      state.view = fallbackView;
      return;
    }
  }
}

function browsableExampleId(preferredId) {
  const examples = browsableExamples();
  if (examples.some((example) => example.id === preferredId)) {
    return preferredId;
  }

  const defaultExample = examples.find((example) => example.id === state.defaultExampleId);
  return defaultExample ? defaultExample.id : (examples[0] ? examples[0].id : '');
}

function applySelectedExample(preferredId, { load = true } = {}) {
  state.selectedId = browsableExampleId(preferredId);
  ensureBrowsableView();
  examplePicker.value = state.selectedId;
  updateDownloadSource();
  updateControls();
  if (load) {
    loadSelectedExample();
  }
}

function populateExamples(preferredId = state.selectedId) {
  const examples = browsableExamples();
  examplePicker.replaceChildren();
  examples.forEach((example) => {
    examplePicker.append(createOption(example.id, example.format + ' · ' + example.label));
  });
  applySelectedExample(preferredId, { load: false });
}

function updateViewButtons() {
  viewButtons.forEach((button) => {
    const active = button.dataset.exampleView === state.view;
    button.setAttribute('aria-pressed', String(active));
  });
}

function updateDownloadSource() {
  const example = selectedExample();
  if (!example || !example.samplePath) {
    downloadSource.hidden = true;
    downloadSource.removeAttribute('href');
    return;
  }
  downloadSource.href = example.samplePath;
  downloadSource.hidden = false;
}

function updateControls() {
  const examples = browsableExamples();
  const ready = examples.length > 0;
  const example = selectedExample();
  const busy = state.ownFileBusy;
  examplePicker.disabled = !ready || busy;
  previousButton.disabled = examples.length < 2 || busy;
  nextButton.disabled = examples.length < 2 || busy;
  viewButtons.forEach((button) => {
    const view = example && example.views ? example.views[button.dataset.exampleView] : null;
    button.disabled = !ready || !isBrowsableView(view) || busy;
  });
  downloadSource.setAttribute('aria-disabled', String(busy));
  downloadSource.tabIndex = busy ? -1 : 0;
  tryOwnFileButton.disabled = busy;
  ownFileInput.disabled = busy;
  updateViewButtons();
}

function setOwnFileBusy(busy, label = '') {
  state.ownFileBusy = busy;
  tryOwnFileButton.textContent = busy ? label : 'Try your own file';
  updateControls();
}

function leavePlaygroundView() {
  if (state.frameMode !== 'playground') {
    frame.setAttribute('sandbox', '');
    return;
  }

  state.ownFileToken += 1;
  state.frameMode = 'example';
  state.playgroundClient = null;
  state.playgroundReady = false;
  state.playgroundBootPromise = null;
  delete frame.dataset.loadedPath;
  frame.removeAttribute('src');
  frame.removeAttribute('srcdoc');
  frame.setAttribute('sandbox', '');
}

function unloadCurrentExample() {
  abortStaticPdfPreview({ clearCache: true });
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
  delete frame.dataset.previewMode;
  delete frame.dataset.previewStatus;
  frame.removeAttribute('src');
  frame.removeAttribute('srcdoc');
  frame.hidden = true;
}

function staticPdfPreviewAbortError(signal) {
  if (signal?.reason instanceof Error) {
    return signal.reason;
  }
  const error = new Error('PDF chart preview was cancelled.');
  error.name = 'AbortError';
  return error;
}

function throwIfStaticPdfPreviewAborted(signal) {
  if (signal?.aborted) {
    throw staticPdfPreviewAbortError(signal);
  }
}

function abortStaticPdfPreview({ clearCache = false } = {}) {
  const controller = state.staticPdfPreviewAbortController;
  state.staticPdfPreviewAbortController = null;
  if (controller && !controller.signal.aborted) {
    controller.abort();
  }
  for (const [key, entry] of state.staticPdfPreviewCache) {
    if (entry?.controller === controller && entry.pending) {
      state.staticPdfPreviewCache.delete(key);
    }
  }
  if (clearCache) {
    state.staticPdfPreviewCache.clear();
  }
}

function rememberStaticPdfPreview(key, entry) {
  state.staticPdfPreviewCache.delete(key);
  state.staticPdfPreviewCache.set(key, entry);
  // One completed srcdoc can already contain several data-URI PNGs. Keep a
  // single LRU entry so switching through the catalogue cannot retain a
  // growing gallery in JavaScript memory.
  while (state.staticPdfPreviewCache.size > 1) {
    const oldestKey = state.staticPdfPreviewCache.keys().next().value;
    const oldest = state.staticPdfPreviewCache.get(oldestKey);
    state.staticPdfPreviewCache.delete(oldestKey);
    if (oldest?.pending && !oldest.controller.signal.aborted) {
      oldest.controller.abort();
    }
  }
}

function isCurrentExampleLoad(token, example, view) {
  const currentView = selectedView();
  return token === state.loadToken
    && state.frameMode === 'example'
    && selectedExample()?.id === example.id
    && currentView?.path === view.path;
}

function staticPdfFormPreviewEnabled(example, viewName) {
  const forms = example && example.pdfFormRenders;
  return (viewName === 'phpHtml' || viewName === 'wpBlocks')
    && Boolean(forms && forms.ok && forms.path && Number(forms.bytes) > 0);
}

function staticPdfPreviewCacheKey(example, viewName, view) {
  return [example.id, viewName, view.path].join('\u001f');
}

function staticPreviewUrl(path) {
  return new URL(path, window.location.href).href;
}

async function fetchStaticPreviewText(path, label, signal) {
  throwIfStaticPdfPreviewAborted(signal);
  const response = await fetch(staticPreviewUrl(path), { cache: 'no-store', signal });
  throwIfStaticPdfPreviewAborted(signal);
  if (!response.ok) {
    throw new Error(label + ' could not be loaded (' + response.status + ').');
  }

  const text = await response.text();
  throwIfStaticPdfPreviewAborted(signal);
  return { text, url: response.url || staticPreviewUrl(path) };
}

function staticPdfSourceLimitError() {
  const error = new Error('This PDF exceeds the static preview size limit.');
  error.code = 'static-pdf-source-limit';
  return error;
}

function staticPdfSourceLimitExceeded(error) {
  return error && typeof error === 'object' && error.code === 'static-pdf-source-limit';
}

async function fetchStaticPdfSource(samplePath, manifestUrl, signal) {
  const candidates = [
    staticPreviewUrl(samplePath),
    new URL(samplePath, manifestUrl).href,
  ].filter((candidate, index, all) => candidate && all.indexOf(candidate) === index);
  let failure = null;
  for (const url of candidates) {
    try {
      throwIfStaticPdfPreviewAborted(signal);
      const response = await fetch(url, { cache: 'no-store', signal });
      throwIfStaticPdfPreviewAborted(signal);
      if (!response.ok) {
        throw new Error('The original PDF could not be loaded (' + response.status + ').');
      }
      const announcedBytes = Number(response.headers.get('content-length'));
      if (Number.isFinite(announcedBytes) && announcedBytes > staticPdfPreviewMaxSourceBytes) {
        throw staticPdfSourceLimitError();
      }

      const bytes = new Uint8Array(await response.arrayBuffer());
      throwIfStaticPdfPreviewAborted(signal);
      if (bytes.byteLength > staticPdfPreviewMaxSourceBytes) {
        throw staticPdfSourceLimitError();
      }
      return bytes;
    } catch (error) {
      if (signal?.aborted) {
        throw staticPdfPreviewAbortError(signal);
      }
      if (staticPdfSourceLimitExceeded(error)) {
        throw error;
      }
      failure = error;
    }
  }

  throw failure || new Error('The original PDF could not be loaded.');
}

function staticPdfFilesByPath(requests, samplePath, bytes) {
  const files = new Map();
  if (samplePath) {
    files.set(samplePath, bytes);
  }
  for (const request of requests) {
    const path = String(request?.path || '');
    if (path) {
      files.set(path, bytes);
    }
  }

  return files;
}

function rewriteStaticPreviewMediaUrls(previewDocument, viewUrl) {
  for (const element of previewDocument.querySelectorAll('img[src], source[src], video[src], audio[src], track[src], object[data]')) {
    const attribute = element.hasAttribute('data') ? 'data' : 'src';
    const value = String(element.getAttribute(attribute) || '').trim();
    if (!value || /^(?:[a-z][a-z0-9+.-]*:|#|\/\/)/i.test(value)) {
      continue;
    }
    try {
      element.setAttribute(attribute, new URL(value, viewUrl).href);
    } catch {
      // Keep malformed output untouched; the static preview remains safer
      // than failing the entire enhanced view over one optional media URL.
    }
  }
}

function normalizedPreviewText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function normalizedPdfTextAnchor(value) {
  // Positioned PDF text can retain a line-ending hyphen even though the
  // reflowed preview joins the rest of that word.  Anchors are only used as
  // unique prefixes, so trim the terminal discretionary hyphen generically.
  return normalizedPreviewText(value).replace(/(?:-|\u00ad)$/u, '');
}

function staticPdfUniqueTextAnchor(candidates, text) {
  if (text.length < 3) {
    return null;
  }
  const matches = candidates.filter((element) => normalizedPreviewText(element.textContent).includes(text));
  return matches.length === 1 ? { element: matches[0], index: candidates.indexOf(matches[0]) } : null;
}

function staticPdfTextAnchor(previewDocument, request) {
  const candidates = Array.from(previewDocument.body?.querySelectorAll('p, li, figcaption, h1, h2, h3, h4, h5, h6, pre, td, th') || []);
  const preceding = normalizedPdfTextAnchor(request?.precedingText || request?.anchorBefore);
  const following = normalizedPdfTextAnchor(request?.followingText || request?.anchorAfter);
  const precedingAnchor = staticPdfUniqueTextAnchor(candidates, preceding);
  const followingAnchor = staticPdfUniqueTextAnchor(candidates, following);
  // Match the importer: a unique following anchor is safest when it follows
  // the preceding one, and must receive the figure before its text. Ambiguous
  // text never becomes a guessed placement in the static preview.
  if (followingAnchor && (!precedingAnchor || precedingAnchor.index < followingAnchor.index)) {
    return { ...followingAnchor, position: 'before' };
  }
  if (precedingAnchor) {
    return { ...precedingAnchor, position: 'after' };
  }

  return null;
}

function staticPdfFormFigure(previewDocument, request, rendered, ordinal) {
  const figure = previewDocument.createElement('figure');
  figure.className = 'pandoc-pdf-form-figure wp-block-image';
  figure.dataset.pdfFormRequest = String(request?.id || ordinal + 1);
  if (Number.isInteger(request?.object)) {
    figure.dataset.pdfFormObject = String(request.object);
  }
  const label = String(request?.alt || request?.label || request?.title || '').trim();
  if (rendered?.bytes instanceof Uint8Array) {
    const image = previewDocument.createElement('img');
    image.alt = label || 'PDF figure ' + (ordinal + 1);
    image.dataset.pandocPdfFormRendered = 'true';
    image.decoding = 'async';
    const mimeType = String(rendered.mimeType || 'image/png');
    image.src = 'data:' + mimeType + ';base64,' + base64FromBytes(rendered.bytes);
    figure.append(image);
  } else {
    figure.classList.add('pandoc-pdf-form-placeholder');
    const message = previewDocument.createElement('p');
    const detail = String(rendered?.error || '').replace(/\s+/g, ' ').trim().slice(0, 240);
    message.textContent = (label || 'PDF figure ' + (ordinal + 1)) + ' could not be rendered in this browser'
      + (detail ? ': ' + detail : '.');
    figure.append(message);
  }
  const caption = String(request?.caption || request?.label || '').trim();
  if (caption) {
    const figcaption = previewDocument.createElement('figcaption');
    figcaption.textContent = caption;
    figure.append(figcaption);
  }

  return figure;
}

function injectStaticPdfFormFigures(previewDocument, requests, rendered) {
  const requestsById = new Map(requests.map((request) => [String(request?.id || ''), request]));
  const insertionPoints = new Map();
  const body = previewDocument.body || previewDocument.documentElement;
  let successful = 0;
  let failed = 0;
  rendered.forEach((item, ordinal) => {
    const request = requestsById.get(String(item?.requestId || '')) || requests[ordinal] || {};
    const figure = staticPdfFormFigure(previewDocument, request, item, ordinal);
    const anchor = staticPdfTextAnchor(previewDocument, request);
    const insertionPoint = anchor?.position === 'after' ? insertionPoints.get(anchor.element) || anchor.element : null;
    if (anchor?.position === 'before' && anchor.element.parentNode) {
      anchor.element.before(figure);
    } else if (insertionPoint?.parentNode) {
      insertionPoint.after(figure);
      insertionPoints.set(anchor.element, figure);
    } else {
      body.append(figure);
    }
    if (item?.bytes instanceof Uint8Array) {
      successful += 1;
    } else {
      failed += 1;
    }
  });

  return { successful, failed };
}

function addStaticPdfFormStyles(previewDocument) {
  if (previewDocument.getElementById('pandoc-pdf-form-preview-styles')) {
    return;
  }
  const style = previewDocument.createElement('style');
  style.id = 'pandoc-pdf-form-preview-styles';
  style.textContent = '.pandoc-pdf-form-figure{margin:1.25em 0}.pandoc-pdf-form-figure img{display:block;max-width:100%;height:auto}.pandoc-pdf-form-figure figcaption{margin-top:.45em;color:#4b5563;font-size:.9em}.pandoc-pdf-form-placeholder{padding:1em;border:1px dashed #aeb9c7;color:#4b5563}.pandoc-pdf-form-placeholder p{margin:0}';
  (previewDocument.head || previewDocument.documentElement).append(style);
}

function staticPdfFormPlaceholderResults(requests, message) {
  return requests.map((request) => ({
    requestId: String(request?.id || ''),
    error: message,
  }));
}

function staticPdfFormRequestPlan(requests) {
  const renderable = requests.slice(0, staticPdfPreviewMaxRequests);
  const skipped = staticPdfFormPlaceholderResults(
    requests.slice(staticPdfPreviewMaxRequests),
    'This static preview renders at most ' + staticPdfPreviewMaxRequests + ' PDF charts to keep browser memory bounded.',
  );
  return { renderable, skipped };
}

function staticPdfSourceIsTooLarge(example) {
  const sourceBytes = Number(example?.sampleSize);
  return Number.isFinite(sourceBytes) && sourceBytes > staticPdfPreviewMaxSourceBytes;
}

async function buildStaticPdfFormPreview(example, view, reportProgress, signal) {
  const formMetadata = example.pdfFormRenders;
  const [staticOutput, manifestOutput] = await Promise.all([
    fetchStaticPreviewText(view.path, 'The static preview', signal),
    fetchStaticPreviewText(formMetadata.path, 'The PDF figure manifest', signal),
  ]);
  throwIfStaticPdfPreviewAborted(signal);
  let manifest;
  try {
    manifest = JSON.parse(manifestOutput.text);
  } catch {
    throw new Error('The PDF figure manifest is not valid JSON.');
  }
  const requests = Array.isArray(manifest?.requests) ? manifest.requests : [];
  const samplePath = String(manifest?.samplePath || example.samplePath || '').trim();
  if (requests.length === 0 || !samplePath) {
    throw new Error('The PDF figure manifest has no renderable source.');
  }

  const plan = staticPdfFormRequestPlan(requests);
  let rendered = plan.skipped;
  if (staticPdfSourceIsTooLarge(example)) {
    rendered = staticPdfFormPlaceholderResults(
      requests,
      'This PDF exceeds the static preview size limit; its chart is shown as a placeholder.',
    );
  } else {
    try {
      reportProgress('Opening the original PDF for its charts…');
      const sourceBytes = await fetchStaticPdfSource(samplePath, manifestOutput.url, signal);
      const renderedRequests = await renderPdfFormRequests({
        filesByPath: staticPdfFilesByPath(plan.renderable, samplePath, sourceBytes),
        requests: plan.renderable,
        pdfjs: playgroundPdfJsConfig(),
        maxPixels: staticPdfPreviewMaxPixels,
        maxTotalPixels: staticPdfPreviewMaxTotalPixels,
        maxTotalImageBytes: staticPdfPreviewMaxImageBytes,
        signal,
        onProgress({ completed, total, label }) {
          reportProgress(total > 0 ? label + ' (' + completed + ' of ' + total + ')' : label);
        },
      });
      throwIfStaticPdfPreviewAborted(signal);
      rendered = [...renderedRequests, ...plan.skipped];
    } catch (error) {
      if (signal?.aborted) {
        throw staticPdfPreviewAbortError(signal);
      }
      if (!staticPdfSourceLimitExceeded(error)) {
        throw error;
      }
      rendered = staticPdfFormPlaceholderResults(
        requests,
        'This PDF exceeds the static preview size limit; its chart is shown as a placeholder.',
      );
    }
  }
  throwIfStaticPdfPreviewAborted(signal);
  const previewDocument = new DOMParser().parseFromString(staticOutput.text, 'text/html');
  rewriteStaticPreviewMediaUrls(previewDocument, staticOutput.url);
  addStaticPdfFormStyles(previewDocument);
  const counts = injectStaticPdfFormFigures(previewDocument, requests, rendered);
  if (counts.successful === 0 && counts.failed === 0) {
    throw new Error('The PDF figure renderer returned no chart results.');
  }

  return {
    html: '<!doctype html>\n' + previewDocument.documentElement.outerHTML,
    ...counts,
  };
}

function staticPdfFormPreviewDocument(example, view, viewName, reportProgress) {
  const key = staticPdfPreviewCacheKey(example, viewName, view);
  const cached = state.staticPdfPreviewCache.get(key);
  if (cached && !cached.controller.signal.aborted) {
    rememberStaticPdfPreview(key, cached);
    return cached.promise;
  }
  if (cached) {
    state.staticPdfPreviewCache.delete(key);
  }

  const controller = new AbortController();
  const entry = {
    controller,
    pending: true,
    promise: null,
  };
  const preview = buildStaticPdfFormPreview(example, view, reportProgress, controller.signal);
  entry.promise = preview;
  state.staticPdfPreviewAbortController = controller;
  rememberStaticPdfPreview(key, entry);
  preview.then(
    () => {
      entry.pending = false;
      if (state.staticPdfPreviewAbortController === controller) {
        state.staticPdfPreviewAbortController = null;
      }
    },
    () => {
      entry.pending = false;
      if (state.staticPdfPreviewAbortController === controller) {
        state.staticPdfPreviewAbortController = null;
      }
      if (state.staticPdfPreviewCache.get(key) === entry) {
        state.staticPdfPreviewCache.delete(key);
      }
    },
  );

  return preview;
}

function loadStaticPreviewUrl(example, view, token, warning = '') {
  window.requestAnimationFrame(() => {
    if (!isCurrentExampleLoad(token, example, view)) {
      return;
    }
    frame.removeAttribute('srcdoc');
    frame.dataset.previewMode = warning ? 'fallback' : 'url';
    if (warning) {
      frame.dataset.previewStatus = warning;
    } else {
      delete frame.dataset.previewStatus;
    }
    frame.src = view.path;
  });
}

async function loadStaticPdfFormPreview(example, view, viewName, token) {
  try {
    const preview = await staticPdfFormPreviewDocument(example, view, viewName, (message) => {
      if (isCurrentExampleLoad(token, example, view)) {
        setStatus(message, { visible: true });
      }
    });
    if (!isCurrentExampleLoad(token, example, view)) {
      return;
    }
    frame.dataset.previewMode = 'pdf-forms';
    frame.dataset.previewStatus = 'Loaded ' + example.label + ' with ' + preview.successful
      + ' PDF chart' + (preview.successful === 1 ? '' : 's')
      + (preview.failed > 0 ? '; ' + preview.failed + ' chart placeholder' + (preview.failed === 1 ? ' is' : 's are') + ' shown.' : '.');
    frame.removeAttribute('src');
    frame.srcdoc = preview.html;
  } catch (error) {
    if (!isCurrentExampleLoad(token, example, view)) {
      return;
    }
    const detail = error instanceof Error ? error.message : String(error);
    loadStaticPreviewUrl(
      example,
      view,
      token,
      'Could not render PDF charts here (' + detail + '). Showing the static preview instead.',
    );
  }
}

function loadSelectedExample() {
  if (state.ownFileBusy) {
    return;
  }
  abortStaticPdfPreview();
  leavePlaygroundView();
  const example = selectedExample();
  const view = selectedView(example);
  if (!example || !isBrowsableView(view)) {
    unloadCurrentExample();
    setStatus('No ' + viewLabels[state.view] + ' result is available for this example.');
    return;
  }

  const token = state.loadToken + 1;
  state.loadToken = token;
  const viewName = state.view;
  frame.hidden = false;
  frame.loading = 'eager';
  frame.dataset.loadedPath = view.path;
  delete frame.dataset.previewMode;
  delete frame.dataset.previewStatus;
  frame.setAttribute('sandbox', '');
  frame.removeAttribute('srcdoc');
  frame.removeAttribute('src');
  frame.src = 'about:blank';
  setStatus('Loading ' + example.label + '…', { visible: true });

  if (staticPdfFormPreviewEnabled(example, viewName)) {
    void loadStaticPdfFormPreview(example, view, viewName, token);
    return;
  }
  loadStaticPreviewUrl(example, view, token);
}

function moveExample(direction) {
  if (state.ownFileBusy) {
    return;
  }
  const examples = browsableExamples();
  if (examples.length === 0) {
    setStatus('No browsable example is available.');
    return;
  }

  const current = examples.findIndex((example) => example.id === state.selectedId);
  const nextIndex = current < 0
    ? (direction > 0 ? 0 : examples.length - 1)
    : (current + direction + examples.length) % examples.length;
  applySelectedExample(examples[nextIndex].id);
  syncExampleUrl();
}

function ownFileRequestIsCurrent(token) {
  return token === state.ownFileToken && state.frameMode === 'playground';
}

async function bootOwnFilePlayground() {
  if (state.playgroundReady) {
    return;
  }
  if (state.playgroundBootPromise) {
    await state.playgroundBootPromise;
    return;
  }

  state.playgroundBootPromise = startOwnFilePlayground();
  await state.playgroundBootPromise;
}

async function startOwnFilePlayground() {
  try {
    const pluginUrl = new URL(`playground/port-libs-playground-converter.zip?v=${playgroundPluginBuild}`, window.location.href).href;
    if (!state.startPlaygroundWeb) {
      const playgroundModule = await import(playgroundClientModuleUrl);
      state.startPlaygroundWeb = playgroundModule.startPlaygroundWeb;
    }
    state.playgroundClient = await state.startPlaygroundWeb({
      iframe: frame,
      remoteUrl: 'https://playground.wordpress.net/remote.html',
      blueprint: {
        preferredVersions: {
          php: '8.4',
          wp: 'latest',
        },
        landingPage: '/',
        features: {
          networking: true,
        },
        steps: [
          { step: 'login' },
          {
            step: 'installPlugin',
            pluginData: {
              resource: 'url',
              url: pluginUrl,
            },
            options: {
              activate: true,
            },
          },
        ],
      },
    });
    await state.playgroundClient.isReady();
    state.playgroundReady = true;
  } catch (error) {
    state.playgroundBootPromise = null;
    state.playgroundClient = null;
    state.playgroundReady = false;
    throw error;
  }
}

async function openOwnFile(file) {
  if (!file || file.size <= 0) {
    setStatus('Choose a non-empty file to open in WordPress Playground.');
    return;
  }

  abortStaticPdfPreview({ clearCache: true });
  state.lastOwnFileJob = null;
  const token = state.ownFileToken + 1;
  state.ownFileToken = token;
  const reusingPlayground = state.frameMode === 'playground'
    && state.playgroundReady
    && state.playgroundClient;
  state.frameMode = 'playground';
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
  delete frame.dataset.previewMode;
  delete frame.dataset.previewStatus;
  frame.removeAttribute('srcdoc');
  if (!reusingPlayground) {
    frame.removeAttribute('src');
    frame.removeAttribute('sandbox');
  }
  frame.hidden = false;
  frame.loading = 'eager';
  setOwnFileBusy(true, state.playgroundReady ? 'Preparing file…' : 'Opening Playground…');
  setStatus(state.playgroundReady
    ? 'Preparing ' + file.name + ' for WordPress Playground…'
    : 'Opening WordPress Playground for ' + file.name + '…', { visible: true });

  let playgroundClient = null;
  let stagedPath = '';
  try {
    await bootOwnFilePlayground();
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    playgroundClient = state.playgroundClient;
    if (!playgroundClient) {
      throw new Error('WordPress Playground was not ready to receive the selected file.');
    }

    setOwnFileBusy(true, 'Preparing file…');
    setStatus('Preparing ' + file.name + ' for upload…', { visible: true });
    const prepared = await payloadFromOwnFile(file, (message) => {
      setOwnFileBusy(true, message);
      setStatus(message, { visible: true });
    });
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    setOwnFileBusy(true, 'Uploading…');
    setStatus('Uploading ' + file.name + ' to WordPress Playground…', { visible: true });
    stagedPath = await stageOwnFileInPlayground(playgroundClient, prepared.bytes, token);
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    setOwnFileBusy(true, 'Creating import…');
    setStatus('Creating an import job for ' + file.name + '…', { visible: true });
    let job = await ownFilePluginRequest(playgroundClient, '/imports', {
      ...prepared.payload,
      stagedPath,
    });
    const reportedEventKeys = new Set();
    const reportJob = (snapshot) => {
      if (!ownFileRequestIsCurrent(token)) {
        return;
      }
      const label = ownFileImportProgressLabel(snapshot);
      const latestEvent = ownFileImportLatestNewEvent(snapshot, reportedEventKeys);
      const message = latestEvent ? `${label} ${latestEvent}` : label;
      setOwnFileBusy(true, message);
      setStatus(message, { visible: true });
    };
    reportJob(job);

    while (!['complete', 'failed'].includes(String(job.status || ''))) {
      if (!ownFileRequestIsCurrent(token)) {
        return;
      }
      if (Array.isArray(job.renderRequests) && job.renderRequests.length > 0) {
        const rendered = await renderPdfFormRequests({
          filesByPath: await pdfFilesForOwnFile(playgroundClient, job, file, prepared.bytes),
          requests: job.renderRequests,
          pdfjs: playgroundPdfJsConfig(),
          maxTotalPixels: playgroundPdfFormTotalPixelLimit,
          maxTotalImageBytes: playgroundPdfFormTotalImageByteLimit,
          onProgress({ completed, total, label }) {
            if (!ownFileRequestIsCurrent(token)) {
              return;
            }
            const progress = `${label} (${completed} of ${total})`;
            setOwnFileBusy(true, progress);
            setStatus(progress, { visible: true });
          },
        });
        if (!ownFileRequestIsCurrent(token)) {
          return;
        }
        for (const item of rendered) {
          const rendererPayload = item.error
            ? { requestId: item.requestId, error: item.error }
            : {
              requestId: item.requestId,
              bytes: base64FromBytes(item.bytes),
              mimeType: item.mimeType,
              width: item.width,
              height: item.height,
            };
          job = await ownFilePluginRequest(
            playgroundClient,
            `/imports/${encodeURIComponent(job.jobId)}/rendered-media`,
            rendererPayload,
          );
          reportJob(job);
          if (!ownFileRequestIsCurrent(token)) {
            return;
          }
        }
        continue;
      }
      if (job.status === 'awaiting_renderer') {
        throw new Error('WordPress requested a PDF figure, but did not provide a renderable crop. Please try the file again.');
      }
      job = await advanceOwnFileImport(playgroundClient, job, token, reportJob);
      reportJob(job);
    }
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'Conversion failed.');
    }
    state.lastOwnFileJob = job;
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    const data = job.result;
    try {
      await playgroundClient.goTo(playgroundPath(data.pageUrl));
    } catch (pageError) {
      // Conversion and publication have already committed at this point. A
      // very large front-end render must not be reported as if 200+ pages of
      // saved import work disappeared; try the editor and retain success even
      // if this particular Playground view cannot render the result.
      try {
        await playgroundClient.goTo(playgroundPath(data.editUrl));
      } catch {
        const detail = pageError instanceof Error ? pageError.message : String(pageError);
        setStatus(
          'The import completed and the WordPress page was saved, but Playground could not display it: ' + detail,
          { visible: true, tone: 'success' },
        );
        return;
      }
    }
    if (ownFileRequestIsCurrent(token)) {
      setStatus('Import complete. Converted pages were verified privately and published. Opened a new WordPress page for ' + file.name + '.', { visible: true, tone: 'success' });
    }
  } catch (error) {
    if (ownFileRequestIsCurrent(token)) {
      const message = error instanceof Error ? error.message : String(error);
      setStatus('Could not open ' + file.name + ' in WordPress Playground: ' + message, { visible: true, tone: 'error' });
    }
  } finally {
    if (stagedPath && playgroundClient) {
      try {
        await playgroundClient.unlink(stagedPath);
      } catch {
        // The converter removes successfully read sources. A failed request
        // can still leave one behind, so cleanup remains best effort here.
      }
    }
    if (token === state.ownFileToken) {
      setOwnFileBusy(false);
    }
  }
}

// The release E2E driver opts in with ?e2e=... and verifies the actual
// WordPress rows after the UI reports success. Keep the hook absent for
// ordinary visitors and return only integrity counts, never document text.
if (new URL(window.location.href).searchParams.has('e2e')) {
  window.__portLibsImportE2E = {
    async inspectLastImport() {
      const job = state.lastOwnFileJob;
      const client = state.playgroundClient;
      if (!job?.result || !client) {
        throw new Error('No completed Playground import is available for inspection.');
      }
      const children = Array.isArray(job.result.posts) ? job.result.posts : [];
      const ids = Array.from(new Set([
        Number(job.result.postId) || 0,
        ...children.map((post) => Number(post?.postId) || 0),
      ].filter((id) => id > 0)));
      const posts = [];
      for (const postId of ids) {
        const response = await client.request({
          method: 'GET',
          url: `/wp-json/wp/v2/pages/${postId}?context=view`,
        });
        const body = typeof response.text === 'function' ? await response.text() : response.text;
        const page = JSON.parse(String(body || '{}'));
        const raw = String(page?.content?.rendered || '');
        const visible = new DOMParser().parseFromString(raw.replace(/<!--.*?-->/gs, ' '), 'text/html')
          .body.textContent.replace(/\s+/g, ' ').trim();
        posts.push({
          postId,
          status: String(page?.status || ''),
          contentBytes: new TextEncoder().encode(raw).byteLength,
          visibleTextBytes: new TextEncoder().encode(visible).byteLength,
          imageCount: (raw.match(/<img\b/gi) || []).length,
          rawDataProvenanceCount: (raw.match(/data-pandoc-media-(?:canonical-)?source=["']data:/gi) || []).length,
          restErrorCode: String(page?.code || ''),
        });
      }

      return {
        jobId: String(job.jobId || ''),
        resultPostId: Number(job.result.postId) || 0,
        childPostCount: children.length,
        posts,
      };
    },
  };
}

async function ownFilePluginRequest(playgroundClient, path, payload = {}, method = 'POST') {
  const request = {
    method,
    url: `/wp-json/port-libs/v1${path}`,
  };
  if (method !== 'GET') {
    request.headers = { 'Content-Type': 'application/json' };
    request.body = JSON.stringify(payload);
  }
  const response = await playgroundClient.request(request);
  const text = typeof response.text === 'function' ? await response.text() : response.text;
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error('WordPress Playground returned an unreadable import-job response. Please try the file again.');
  }
  if (!data.ok) {
    throw new Error(data.message || 'Conversion failed.');
  }

  return data;
}

async function advanceOwnFileImport(playgroundClient, job, token, reportJob) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  if (!jobId) {
    throw new Error('WordPress did not return an import job identifier. Please try the file again.');
  }
  let lastError = null;
  for (let attempt = 0; attempt <= ownFileAdvanceRecoveryAttempts; attempt += 1) {
    const stopPolling = startOwnFileImportStatusPolling(playgroundClient, jobId, token, reportJob);
    try {
      return await ownFilePluginRequest(playgroundClient, `/imports/${jobId}/advance`, {});
    } catch (error) {
      lastError = error;
    } finally {
      stopPolling();
    }
    if (attempt >= ownFileAdvanceRecoveryAttempts || !ownFileRequestIsCurrent(token)) {
      break;
    }
    const retry = attempt + 1;
    const recoveryLabel = `The server request ended unexpectedly. Checking saved progress (${retry} of ${ownFileAdvanceRecoveryAttempts})…`;
    setOwnFileBusy(true, recoveryLabel);
    setStatus(recoveryLabel, { visible: true });
    await new Promise((resolve) => window.setTimeout(resolve, 400 * retry));
    try {
      const recovered = await ownFilePluginRequest(playgroundClient, `/imports/${jobId}`, undefined, 'GET');
      reportJob(recovered);
      // A completed checkpoint, a renderer handoff, or a finished import is
      // safe for the outer state machine. Only a worker left mid-transition
      // needs another bounded /advance attempt.
      if (String(recovered.status || '') !== 'converting') {
        return recovered;
      }
    } catch (statusError) {
      lastError = statusError;
    }
  }
  const detail = lastError instanceof Error ? lastError.message : String(lastError || 'Unknown server error');
  throw new Error(`${detail} The completed page checkpoints remain saved in this Playground, but automatic recovery stopped to avoid a retry loop.`);
}

function startOwnFileImportStatusPolling(playgroundClient, jobId, token, reportJob) {
  let stopped = false;
  let timer = null;
  const poll = async () => {
    if (stopped || !ownFileRequestIsCurrent(token)) {
      return;
    }
    try {
      const snapshot = await ownFilePluginRequest(
        playgroundClient,
        `/imports/${jobId}`,
        undefined,
        'GET',
      );
      if (!stopped && ownFileRequestIsCurrent(token)) {
        reportJob(snapshot);
      }
    } catch {
      // The in-flight advance response remains authoritative. A transient
      // status poll failure should not abandon an otherwise healthy import.
    } finally {
      if (!stopped && ownFileRequestIsCurrent(token)) {
        timer = window.setTimeout(poll, ownFileStatusPollIntervalMs);
      }
    }
  };
  timer = window.setTimeout(poll, ownFileStatusPollIntervalMs);

  return () => {
    stopped = true;
    if (timer !== null) {
      window.clearTimeout(timer);
    }
  };
}

function ownFileImportProgressLabel(job) {
  const progress = job && typeof job.progress === 'object' ? job.progress : {};
  const label = String(progress.label || 'Import is continuing…');
  const completed = Math.max(0, Number(progress.completed || 0));
  const total = Math.max(1, Number(progress.total || 1));

  const details = [];
  const metrics = job && typeof job.metrics === 'object' ? job.metrics : {};
  const pdfTotal = Math.max(0, Number(metrics.pdfPagesTotal || 0));
  const pdfCompleted = Math.max(0, Number(metrics.pdfPagesExtracted || 0));
  if (pdfTotal > 0 && pdfCompleted < pdfTotal) {
    details.push(`${pdfCompleted} of ${pdfTotal} PDF pages saved`);
  }
  const publication = job && typeof job.publication === 'object' ? job.publication : {};
  const publicationTotal = Math.max(0, Number(publication.total || 0));
  if (publicationTotal > 0 && String(job?.status || '') === 'ready_to_publish') {
    details.push(`${Math.max(0, Number(publication.completed || 0))} of ${publicationTotal} pages published`);
  }
  const step = total > 1 ? `${label} (${completed} of ${total})` : label;

  return details.length > 0 ? `${step} ${details.join('; ')}.` : step;
}

function ownFileImportLatestNewEvent(job, reportedEventKeys) {
  let latestMessage = '';
  for (const event of Array.isArray(job?.events) ? job.events : []) {
    const key = [event?.time ?? '', event?.stage ?? '', event?.message ?? ''].join('\u001f');
    if (reportedEventKeys.has(key)) {
      continue;
    }
    reportedEventKeys.add(key);
    const message = String(event?.message || '').trim();
    if (message) {
      latestMessage = message;
    }
  }

  return latestMessage;
}

async function pdfFilesForOwnFile(playgroundClient, job, file, bytes) {
  const files = new Map();
  const requests = Array.isArray(job?.renderRequests) ? job.renderRequests : [];
  if (isLikelyPdfFile(file) && bytes instanceof Uint8Array) {
    files.set(file.name, bytes);
  }
  for (const request of requests) {
    const path = String(request?.path || '');
    if (path && isLikelyPdfFile(file) && bytes instanceof Uint8Array) {
      // The server sanitizes upload names before it persists the job. This is
      // a one-file import, so each requested source path refers to these
      // browser-held PDF bytes even when its sanitized name differs locally.
      files.set(path, bytes);
    }
  }

  for (const request of requests) {
    const path = String(request?.path || '');
    if (!path || files.has(path)) {
      continue;
    }
    const source = await ownFilePdfRenderSource(playgroundClient, job, request);
    if (source) {
      files.set(path, source);
    }
  }

  return files;
}

async function ownFilePdfRenderSource(playgroundClient, job, request) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  const requestId = encodeURIComponent(String(request?.id || ''));
  if (!jobId || !requestId) {
    return null;
  }
  try {
    const source = await ownFilePluginRequest(
      playgroundClient,
      `/imports/${jobId}/render-source/${requestId}`,
      undefined,
      'GET',
    );
    const encoded = String(source.bytes || '');
    if (!encoded) {
      return null;
    }

    return bytesFromBase64(encoded);
  } catch {
    // Older plugin builds do not expose a stored ZIP member. PDF.js will
    // report the unavailable crop to WordPress, which leaves a visible
    // placeholder while the rest of the document is still imported.
    return null;
  }
}

function playgroundPdfJsConfig() {
  const base = new URL('vendor/pdfjs/', window.location.href).href;

  return {
    pdfjsModuleUrl: new URL('pdf.min.mjs', base).href,
    pdfjsWorkerUrl: new URL('pdf.worker.min.mjs', base).href,
    pdfjsWasmUrl: new URL('wasm/', base).href,
    pdfjsCMapUrl: new URL('cmaps/', base).href,
    pdfjsStandardFontDataUrl: new URL('standard_fonts/', base).href,
  };
}

async function payloadFromOwnFile(file, reportProgress) {
  const bytes = new Uint8Array(await file.arrayBuffer());
  const payload = {
    filename: file.name,
    title: titleFromFilename(file.name),
    imageMode: 'important',
    pdfMode: 'layout',
  };
  if (!isLikelyPdfFile(file)) {
    return { payload, bytes };
  }

  const pdfRasterImages = await browserPdfRasterImages(bytes, reportProgress);
  return {
    bytes,
    payload: {
      ...payload,
      ...(pdfRasterImages.length > 0 ? { pdfRasterImages } : {}),
    },
  };
}

async function stageOwnFileInPlayground(playgroundClient, bytes, token) {
  await playgroundClient.mkdirTree(playgroundUploadDirectory);
  const id = typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : String(Date.now()) + '-' + Math.random().toString(36).slice(2);
  const stagedPath = playgroundUploadDirectory + '/' + token + '-' + id.replace(/[^A-Za-z0-9-]/g, '') + '.upload';
  await playgroundClient.writeFile(stagedPath, bytes);

  return stagedPath;
}

async function browserPdfRasterImages(bytes, reportProgress) {
  const decoderEntries = [
    {
      label: 'JBIG2',
      load: async () => {
        if (!state.decodePdfJbig2Rasters) {
          const module = await import(new URL('pdf-jbig2-rasterizer.mjs?v=jbig2-raster-20260709', window.location.href).href);
          state.decodePdfJbig2Rasters = module.decodePdfJbig2Rasters;
        }
        return state.decodePdfJbig2Rasters;
      },
    },
    {
      label: 'JPEG 2000',
      load: async () => {
        if (!state.decodePdfJpxRasters) {
          const module = await import(new URL('pdf-jpx-rasterizer.mjs?v=jpx-raster-20260714', window.location.href).href);
          state.decodePdfJpxRasters = module.decodePdfJpxRasters;
        }
        return state.decodePdfJpxRasters;
      },
    },
  ];
  const loaded = await Promise.allSettled(decoderEntries.map(async (entry) => ({
    entry,
    decode: await entry.load(),
  })));
  const rasters = [];
  const objects = new Set();
  let remainingBytes = playgroundPdfRasterByteLimit;
  for (const [index, result] of loaded.entries()) {
    if (result.status !== 'fulfilled') {
      reportProgress(`Continuing without ${decoderEntries[index]?.label || 'one'} PDF image decoder…`);
      continue;
    }
    const { decode } = result.value;
    if (typeof decode !== 'function' || remainingBytes <= 0) {
      continue;
    }
    try {
      const decoded = await decode(bytes, {
        imageMode: 'important',
        maxPngBytes: remainingBytes,
        onProgress({ completed, total }) {
          reportProgress(total > 0
            ? `Preparing PDF images (${completed} of ${total})…`
            : 'Preparing PDF images…');
        },
      });
      for (const raster of decoded.rasters || []) {
        const object = String(Number(raster.object));
        if (objects.has(object) || !(raster.bytes instanceof Uint8Array) || raster.bytes.length > remainingBytes) {
          continue;
        }
        objects.add(object);
        rasters.push(raster);
        remainingBytes -= raster.bytes.length;
      }
    } catch {
      reportProgress(`Continuing without ${result.value.entry.label} PDF image decoder…`);
    }
  }

  return rasters.map((raster) => ({
    object: raster.object,
    bytes: base64FromBytes(raster.bytes),
    mimeType: raster.mimeType,
    width: raster.width,
    height: raster.height,
  }));
}

function base64FromBytes(bytes) {
  let binary = '';
  const chunkSize = 0x8000;
  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    binary += String.fromCharCode(...bytes.subarray(offset, Math.min(offset + chunkSize, bytes.length)));
  }

  return btoa(binary);
}

function bytesFromBase64(base64) {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return bytes;
}

function isLikelyPdfFile(file) {
  return file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
}

function titleFromFilename(name) {
  const last = name.split('/').filter(Boolean).pop() || name;
  const stem = last.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
  return stem ? stem.replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Converted document';
}

function playgroundPath(url) {
  try {
    const parsed = new URL(url);
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return url || '/';
  }
}

async function initialize() {
  try {
    const response = await fetch(catalogUrl, { cache: 'no-store' });
    if (!response.ok) {
      throw new Error('catalogue request failed (' + response.status + ')');
    }
    const catalog = await response.json();
    if (!Array.isArray(catalog.examples) || catalog.examples.length === 0 || !Number.isFinite(catalog.automaticViewMaxBytes)) {
      throw new Error('catalogue payload is incomplete');
    }
    state.automaticViewMaxBytes = catalog.automaticViewMaxBytes;
    state.examples = catalog.examples.filter((example) => example && example.id && example.views);
    state.defaultExampleId = catalog.defaultExampleId || state.examples[0].id;
    const linkedExampleId = exampleIdFromUrl();
    state.selectedId = linkedExampleId === null ? state.defaultExampleId : linkedExampleId;
    populateExamples(state.selectedId);
    if (linkedExampleId !== null && linkedExampleId !== state.selectedId) {
      syncExampleUrl();
    }
    loadSelectedExample();
  } catch (error) {
    setStatus('Try reloading this page.');
  }
}

examplePicker.addEventListener('change', () => {
  if (state.ownFileBusy) {
    return;
  }
  applySelectedExample(examplePicker.value);
  syncExampleUrl();
});

previousButton.addEventListener('click', () => moveExample(-1));
nextButton.addEventListener('click', () => moveExample(1));

downloadSource.addEventListener('click', (event) => {
  if (state.ownFileBusy) {
    event.preventDefault();
  }
});

tryOwnFileButton.addEventListener('click', () => {
  if (state.ownFileBusy) {
    return;
  }
  ownFileInput.value = '';
  ownFileInput.click();
});

ownFileInput.addEventListener('change', () => {
  const file = ownFileInput.files && ownFileInput.files[0];
  ownFileInput.value = '';
  if (file) {
    void openOwnFile(file);
  }
});

viewButtons.forEach((button) => {
  button.addEventListener('click', () => {
    if (state.ownFileBusy) {
      return;
    }
    const nextView = button.dataset.exampleView;
    if (!nextView || !viewLabels[nextView] || nextView === state.view) {
      return;
    }
    state.view = nextView;
    updateControls();
    loadSelectedExample();
  });
});

frame.addEventListener('load', () => {
  const example = selectedExample();
  const path = frame.dataset.loadedPath;
  if (!example || !path) {
    return;
  }
  if (frame.dataset.previewMode === 'pdf-forms' && frame.hasAttribute('srcdoc')) {
    setStatus(frame.dataset.previewStatus || 'Loaded ' + example.label + '.', { visible: true, tone: 'success' });
    return;
  }
  if (frame.getAttribute('src') !== path) {
    return;
  }
  if (frame.dataset.previewMode === 'fallback') {
    setStatus(frame.dataset.previewStatus || 'Showing the static preview instead.', { visible: true });
    return;
  }
  setStatus('Loaded ' + example.label + '.');
});

initialize();
JS;
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

if (($argv[1] ?? '') === '--verify-quality-signature') {
    $baseline = showcase_html_visual_signature(showcase_visible_html(<<<'HTML'
<header id="title-block-header"><h1>Generated title</h1><p>July 9, 2026</p></header>
<h2>Release</h2>
<p>Introductory prose.</p>
<div class="linegroup"><div>First line.</div><div>Second line.</div></div>
<ol><li><p>Convert</p></li><li><p>Review</p></li></ol>
<dl><dt>verify</dt><dd><p>Check the output.</p></dd></dl>
<table><tbody><tr><td><p>Cell</p></td></tr></tbody></table>
HTML));
    $wordpress = showcase_html_visual_signature(showcase_visible_html(<<<'HTML'
<h2>Release</h2>
<p>Introductory prose.</p>
<p class="linegroup">First line.<br/>Second line.</p>
<ol><li>Convert</li><li>Review</li></ol>
<div class="wp-block-group pandoc-definition-list">
<p class="pandoc-definition-term"><strong>verify</strong></p>
<ul class="pandoc-definition-values"><li>Check the output.</li></ul>
</div>
<table><tbody><tr><td>Cell</td></tr></tbody></table>
HTML));
    $expected = [
        'dl' => 1,
        'h2' => 1,
        'heading' => 1,
        'li' => 2,
        'linegroup' => 1,
        'ol' => 1,
        'p' => 1,
        'table' => 1,
        'tbody' => 1,
        'td' => 1,
        'tr' => 1,
    ];
    $score = is_array($baseline) && is_array($wordpress)
        ? showcase_visual_signature_similarity($baseline, $wordpress)
        : 0.0;
    $unicodeProbe = tempnam(sys_get_temp_dir(), 'pandoc-showcase-unicode-');
    $unicodeText = '';
    if (is_string($unicodeProbe)) {
        file_put_contents($unicodeProbe, '<p>Państwo E=mc²</p>');
        $unicodeText = showcase_output_text(dirname($unicodeProbe), basename($unicodeProbe));
        unlink($unicodeProbe);
    }
    $imageParagraphSignature = showcase_html_visual_signature('<p><img src="diagram.png" alt="Diagram"/></p>');
    $imageFigureSignature = showcase_html_visual_signature('<figure class="wp-block-image"><img src="diagram.png" alt="Diagram"/></figure>');
    $unbenchmarkedDir = sys_get_temp_dir() . '/pandoc-showcase-unbenchmarked-' . bin2hex(random_bytes(6));
    $unbenchmarked = [];
    $legacyDocWithoutReference = [];
    $legacyDocFaithfulness = [];
    $pdfWithoutReference = [];
    $pdfFaithfulness = [];
    $xmlWithoutReference = [];
    $xmlFaithfulness = [];
    $preservedRawHtml = [];
    $unexpectedCustomHtml = [];
    $nativeLatexSemantics = showcase_source_import_semantics(
        dirname(__DIR__) . '/lanes/pandoc/fixtures/latex-reader/academic-article.tex',
        'latex'
    );
    $semanticFaithfulness = [];
    $semanticQuality = [];
    if (mkdir($unbenchmarkedDir, 0777, true)) {
        try {
            file_put_contents($unbenchmarkedDir . '/wordpress.html', '<p>Standalone entry</p>');
            $unbenchmarked = showcase_record_import_quality($unbenchmarkedDir, [
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'faithfulness' => ['baseline' => null],
                'wpBlockCounts' => ['paragraph' => 1],
            ]);
            file_put_contents($unbenchmarkedDir . '/php.html', '<p>Standalone entry</p>');
            $legacyDocRecord = [
                'format' => 'doc',
                'haskell' => ['ok' => false],
                'externalReference' => ['ok' => false],
                'phpHtml' => ['ok' => true, 'path' => 'php.html'],
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'wpBlockCounts' => ['paragraph' => 1],
            ];
            $legacyDocFaithfulness = showcase_record_faithfulness($unbenchmarkedDir, $legacyDocRecord);
            $legacyDocRecord['faithfulness'] = $legacyDocFaithfulness;
            $legacyDocWithoutReference = showcase_record_import_quality($unbenchmarkedDir, $legacyDocRecord);
            $pdfRecord = [
                'format' => 'pdf',
                'haskell' => ['ok' => false],
                'externalReference' => ['ok' => false],
                'phpHtml' => ['ok' => true, 'path' => 'php.html'],
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'wpBlockCounts' => ['paragraph' => 1],
            ];
            $pdfFaithfulness = showcase_record_faithfulness($unbenchmarkedDir, $pdfRecord);
            $pdfRecord['faithfulness'] = $pdfFaithfulness;
            $pdfWithoutReference = showcase_record_import_quality($unbenchmarkedDir, $pdfRecord);
            $xmlRecord = [
                'format' => 'xml',
                'haskell' => ['ok' => false],
                'externalReference' => ['ok' => false],
                'phpHtml' => ['ok' => true, 'path' => 'php.html'],
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'wpBlockCounts' => ['paragraph' => 1],
            ];
            $xmlFaithfulness = showcase_record_faithfulness($unbenchmarkedDir, $xmlRecord);
            $xmlRecord['faithfulness'] = $xmlFaithfulness;
            $xmlWithoutReference = showcase_record_import_quality($unbenchmarkedDir, $xmlRecord);
            $preservedRawHtml = showcase_record_import_quality($unbenchmarkedDir, [
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'faithfulness' => ['baseline' => null],
                'wpBlockCounts' => ['html' => 1, 'paragraph' => 1],
                'sourceRawHtmlBlockCount' => 1,
            ]);
            $unexpectedCustomHtml = showcase_record_import_quality($unbenchmarkedDir, [
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'faithfulness' => ['baseline' => null],
                'wpBlockCounts' => ['html' => 1, 'paragraph' => 1],
            ]);
            file_put_contents($unbenchmarkedDir . '/semantic-haskell.html', '<p>Reference body.</p>');
            file_put_contents(
                $unbenchmarkedDir . '/semantic-wordpress.html',
                '<div class="latex-title-block"><h1>Imported title</h1></div>'
                . '<div class="latex-abstract"><p>Imported abstract.</p></div>'
                . '<nav class="latex-table-of-contents"><ul><li>Introduction</li></ul></nav>'
                . '<section class="pandoc-csl-bibliography"><p>Imported reference.</p></section>'
                . '<p>Reference body.</p>'
            );
            $semanticRecord = [
                'format' => 'latex',
                'haskell' => ['ok' => true, 'path' => 'semantic-haskell.html'],
                'wpBlocks' => [
                    'ok' => true,
                    'path' => 'semantic-wordpress.html',
                    'mediaDiagnostics' => [],
                ],
                'wpBlockCounts' => ['paragraph' => 2, 'heading' => 1, 'list' => 1],
                'importSemantics' => [
                    'format' => 'latex',
                    'metadata' => [
                        'title' => ['Imported title'],
                        'abstract' => ['Imported abstract.'],
                    ],
                    'structures' => [
                        'latex-title-block',
                        'latex-abstract',
                        'latex-table-of-contents',
                        'pandoc-csl-bibliography',
                    ],
                    'comparisonExclusionClasses' => [
                        'latex-title-block',
                        'latex-abstract',
                        'latex-table-of-contents',
                        'pandoc-csl-bibliography',
                    ],
                ],
            ];
            $semanticFaithfulness = showcase_record_faithfulness($unbenchmarkedDir, $semanticRecord);
            $semanticRecord['faithfulness'] = $semanticFaithfulness;
            $semanticQuality = showcase_record_import_quality($unbenchmarkedDir, $semanticRecord);
        } finally {
            @unlink($unbenchmarkedDir . '/wordpress.html');
            @unlink($unbenchmarkedDir . '/php.html');
            @unlink($unbenchmarkedDir . '/semantic-haskell.html');
            @unlink($unbenchmarkedDir . '/semantic-wordpress.html');
            @rmdir($unbenchmarkedDir);
        }
    }
    $ok = $baseline === $expected
        && $wordpress === $expected
        && $score === 1.0
        && $unicodeText === 'Państwo E=mc²'
        && $imageParagraphSignature === ['img' => 1]
        && $imageFigureSignature === ['img' => 1]
        && ($unbenchmarked['status'] ?? null) === 'unbenchmarked'
        && (($unbenchmarked['gates']['text_completeness']['status'] ?? null) === 'unbenchmarked')
        && (($legacyDocFaithfulness['baseline'] ?? null) === null)
        && (($legacyDocWithoutReference['status'] ?? null) === 'unbenchmarked')
        && (($pdfFaithfulness['baseline'] ?? null) === null)
        && (($pdfWithoutReference['status'] ?? null) === 'unbenchmarked')
        && (($xmlFaithfulness['baseline'] ?? null) === null)
        && (($xmlWithoutReference['status'] ?? null) === 'unbenchmarked')
        && (($preservedRawHtml['gates']['custom_html_percentage']['status'] ?? null) === 'pass')
        && (($unexpectedCustomHtml['gates']['custom_html_percentage']['status'] ?? null) === 'fail')
        && (($nativeLatexSemantics['metadata']['title'][0] ?? null) === 'A Native LaTeX Import Study')
        && in_array('latex-title-block', $nativeLatexSemantics['structures'] ?? [], true)
        && in_array('latex-abstract', $nativeLatexSemantics['structures'] ?? [], true)
        && in_array('latex-table-of-contents', $nativeLatexSemantics['structures'] ?? [], true)
        && in_array('pandoc-csl-bibliography', $nativeLatexSemantics['structures'] ?? [], true)
        && (($semanticFaithfulness['comparisons']['wpBlocks']['textScore'] ?? 0.0) === 1.0)
        && (($semanticQuality['gates']['source_metadata_semantics']['status'] ?? null) === 'pass');
    fwrite(STDOUT, json_encode([
        'ok' => $ok,
        'baseline' => $baseline,
        'wordpress' => $wordpress,
        'score' => $score,
        'unicodeText' => $unicodeText,
        'imageParagraphSignature' => $imageParagraphSignature,
        'imageFigureSignature' => $imageFigureSignature,
        'unbenchmarkedStatus' => $unbenchmarked['status'] ?? null,
        'legacyDocWithoutReferenceStatus' => $legacyDocWithoutReference['status'] ?? null,
        'legacyDocWithoutReferenceBaseline' => $legacyDocFaithfulness['baseline'] ?? null,
        'pdfWithoutReferenceStatus' => $pdfWithoutReference['status'] ?? null,
        'pdfWithoutReferenceBaseline' => $pdfFaithfulness['baseline'] ?? null,
        'xmlWithoutReferenceStatus' => $xmlWithoutReference['status'] ?? null,
        'xmlWithoutReferenceBaseline' => $xmlFaithfulness['baseline'] ?? null,
        'preservedRawHtmlStatus' => $preservedRawHtml['gates']['custom_html_percentage']['status'] ?? null,
        'unexpectedCustomHtmlStatus' => $unexpectedCustomHtml['gates']['custom_html_percentage']['status'] ?? null,
        'nativeLatexSemanticStructures' => $nativeLatexSemantics['structures'] ?? [],
        'semanticComparisonTextScore' => $semanticFaithfulness['comparisons']['wpBlocks']['textScore'] ?? null,
        'semanticMetadataStatus' => $semanticQuality['gates']['source_metadata_semantics']['status'] ?? null,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($ok ? 0 : 1);
}

ensure_dir($siteDir);
ensure_dir($samplesDir);
ensure_dir($outputsDir);

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
    $resourceRoot = isset($sample['localResourceRoot']) ? $root . '/' . ltrim((string) $sample['localResourceRoot'], '/') : null;
    $target = is_string($resourceRoot)
        ? $samplesDir . '/' . $id . '/' . $filename
        : $samplesDir . '/' . $id . '-' . $filename;
    $downloadError = null;
    if (is_string($resourceRoot)) {
        if (!copy_local_resource_tree($resourceRoot, dirname($target))) {
            $downloadError = 'Unable to copy local resource tree ' . $sample['localResourceRoot'];
        }
    } elseif (isset($sample['content'])) {
        file_put_contents($target, (string) $sample['content']);
    } elseif (isset($sample['localPath'])) {
        $localPath = $root . '/' . ltrim((string) $sample['localPath'], '/');
        if (is_file($localPath)) {
            copy($localPath, $target);
        } else {
            @unlink($target);
            $downloadError = 'Unable to copy local sample ' . $sample['localPath'];
        }
    } elseif (isset($sample['url'])) {
        $hasCachedSource = is_file($target) && filesize($target) > 0;
        if (($refreshSources || !$hasCachedSource) && !download_file((string) $sample['url'], $target)) {
            if (!$hasCachedSource) {
                @unlink($target);
                $downloadError = 'Unable to download ' . $sample['url'];
            }
        }
    }
    if ($downloadError !== null) {
        file_put_contents($target . '.download-error.txt', $downloadError);
    } elseif (is_file($target)) {
        @unlink($target . '.download-error.txt');
    }
    $sourcePath = is_file($target) ? $target : $target . '.download-error.txt';
    $outDir = $outputsDir . '/' . $id;
    $cachedHaskell = is_file($target)
        ? cached_haskell_pandoc_reference($outDir, $target, $format)
        : null;
    ensure_clean_dir($outDir);
    $samplePath = rel($sourcePath, $siteDir);

    $haskell = is_array($cachedHaskell)
        ? restore_cached_haskell_pandoc_reference($outDir, $format, $cachedHaskell)
        : (is_file($target)
            ? run_haskell_pandoc($target, $format, $outDir)
            : ['ok' => false, 'error' => $downloadError ?? 'missing source file']);
    $externalReference = is_file($target) ? run_external_reference($target, $format, $outDir) : null;
    $phpHtml = is_file($target) ? write_output_from_process($outDir, 'php.html', $target, $format, 'html') : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $wpBlocks = is_file($target) ? write_output_from_process($outDir, 'wordpress-blocks.html', $target, $format, 'wordpress') : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $pdfFormRenders = is_file($target) ? showcase_pdf_form_render_plan($target, $format, $samplePath, $outDir) : null;
    $wpBlockCounts = (($wpBlocks['ok'] ?? false) === true && isset($wpBlocks['path']))
        ? wordpress_block_counts($siteDir . '/' . $wpBlocks['path'])
        : [];
    $sourceRawHtmlBlockCount = is_file($target) && (int) ($wpBlockCounts['html'] ?? 0) > 0
        ? showcase_source_raw_html_block_count($target, $format)
        : null;
    $bibliographySource = is_file($target) && showcase_bibliography_input_format($format)
        ? showcase_bibliography_source_summary($target, $format)
        : null;
    $importSemantics = is_file($target) ? showcase_source_import_semantics($target, $format) : null;
    $preview = is_file($target) ? sample_preview_html($target) : '';

    $record = [
        'id' => $id,
        'format' => $format,
        'label' => (string) $sample['label'],
        'description' => (string) ($sample['description'] ?? ''),
        'source' => (string) $sample['source'],
        'sourceUrl' => (string) ($sample['url'] ?? ''),
        'samplePath' => $samplePath,
        'sampleSize' => is_file($target) ? filesize($target) : 0,
        'preview' => $preview,
        'support' => $support[$format] ?? ['status' => 'partial', 'implementation' => 'unknown', 'notes' => ''],
        'haskell' => $haskell,
        'phpHtml' => $phpHtml,
        'wpBlocks' => $wpBlocks,
        'wpBlockCounts' => $wpBlockCounts,
        'sourceRawHtmlBlockCount' => $sourceRawHtmlBlockCount,
        'bibliographySource' => $bibliographySource,
        'importSemantics' => $importSemantics,
    ];
    if ($pdfFormRenders !== null) {
        $record['pdfFormRenders'] = $pdfFormRenders;
    }
    if ($externalReference !== null) {
        $record['externalReference'] = $externalReference;
    }
    $record['bibliographyComparison'] = showcase_record_bibliography_comparison($siteDir, $record);
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
$bibliographyComparisonSummary = showcase_bibliography_comparison_summary($records);
$importQualitySummary = showcase_import_quality_summary($records);
$importQualitySegmentSummary = showcase_import_quality_segment_summary($records);
$importQualityGate = showcase_import_quality_threshold_gate($importQualitySegmentSummary);
$generatedAt = gmdate('c');

file_put_contents($siteDir . '/manifest.json', json_encode([
    'generatedAt' => $generatedAt,
    'pandocVersion' => sanitize_generated_text(trim(run_process(['pandoc', '--version'], 10)['stdout'])),
    'mediaImageMode' => 'important',
    'formats' => $formats,
    'coveredFormats' => $coveredFormats,
    'missingFormats' => $missingFormats,
    'conversionSummary' => $conversionSummary,
    'faithfulnessSummary' => $faithfulnessSummary,
    'bibliographyComparisonSummary' => $bibliographyComparisonSummary,
    'importQualitySummary' => $importQualitySummary,
    'importQualitySegmentSummary' => $importQualitySegmentSummary,
    'importQualityGate' => $importQualityGate,
    'blockUsage' => $blockUsage,
    'records' => $records,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
showcase_write_examples_page($siteDir, $records, $generatedAt);

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
$html .= '<p class="lede">Real public documents and upstream fixtures are converted to PHP HTML and WordPress block markup, compared with Haskell Pandoc where that reader exists, and checked against format-native external references where Pandoc has no reader. The stress samples are intentionally messy enough to expose timeouts, memory limits, package complexity, and block coverage.</p>';
$html .= '<div class="stats">';
$html .= '<div class="stat"><strong>' . count($coveredFormats) . '</strong><span>covered input formats</span></div>';
$html .= '<div class="stat"><strong>' . count($records) . '</strong><span>source files</span></div>';
$html .= '<div class="stat"><strong>' . $successCount . '/' . $totalConversions . '</strong><span>successful conversions</span></div>';
$html .= '<div class="stat"><strong>' . gmdate('Y-m-d') . '</strong><span>generated</span></div>';
$html .= '</div><ul class="stress-samples" aria-label="Real-world stress samples">';
foreach ([
    'docx-oasis-kmip-spec' => 'OASIS KMIP DOCX',
    'docx-microsoft-excel-migration' => 'Microsoft migration DOCX',
    'epub-gutenberg-alice-illustrated' => 'Illustrated Alice EPUB',
    'epub-gutenberg-ulysses' => 'Ulysses full-book EPUB',
    'markdown-pandoc-manual' => 'Pandoc manual Markdown',
    'markdown-github-rendered-syntax' => 'Full GitHub Markdown',
    'odt-oasis-opendocument-schema' => 'OASIS OpenDocument ODT',
    'pdf-irs-w4' => 'IRS Form W-4 PDF',
    'pdf-quickbooks-invoice-template' => 'QuickBooks invoice PDF',
    'pptx-cdc-food-safety-slides' => 'CDC PPTX',
    'xlsx-census-tax-parameter-workbook' => 'Census XLSX',
] as $id => $label) {
    $html .= '<li><a href="#' . h($id) . '">' . h($label) . '</a></li>';
}
$html .= '</ul><div class="hero-actions"><a href="examples.html">One example at a time</a><a href="conversion-report.html">Conversion report</a><a href="playground-converter.html">Convert in WordPress Playground</a><a href="block-usage.html">WordPress block usage guide</a><a href="manifest.json">Manifest JSON</a></div></div></header><main class="layout">';
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
        $externalReference = is_array($record['externalReference'] ?? null) ? $record['externalReference'] : [];
        // PDFKit exposes useful native source evidence, but its page text is
        // deliberately raw: it has no document reading order or HTML semantics.
        // Keep it in the manifest and quality gates without presenting it as a
        // peer conversion preview.
        if (($externalReference['ok'] ?? false) === true
            && ($externalReference['kind'] ?? null) !== 'macos-pdfkit-text-geometry') {
            $tabs['externalReference'] = [
                'label' => (string) ($externalReference['label'] ?? 'External reference HTML'),
                'short' => 'External reference',
            ];
        }
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
    $bibliographyComparisonSummary,
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
$guide .= '</div><div class="hero-actions"><a href="examples.html">One example at a time</a><a href="index.html">Showcase</a><a href="manifest.json">Manifest JSON</a></div></div></header>';
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
