<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocFormatRegistry;

require __DIR__ . '/bootstrap.php';

ini_set('display_errors', 'stderr');
error_reporting(E_ALL & ~E_DEPRECATED);

if (($argv[1] ?? '') === '--convert-local') {
    $path = $argv[2] ?? '';
    $format = $argv[3] ?? '';
    $to = $argv[4] ?? '';
    if ($path === '' || $format === '' || $to === '') {
        fwrite(STDERR, "Usage: php tools/build-pandoc-showcase.php --convert-local <path> <from> <to>\n");
        exit(2);
    }
    echo PandocConverter::convertFile($path, $format, $to);
    exit(0);
}

$root = dirname(__DIR__);
$siteDir = $root . '/pandoc-showcase';
$samplesDir = $siteDir . '/samples';
$outputsDir = $siteDir . '/outputs';
$rawBase = 'https://raw.githubusercontent.com/jgm/pandoc/' . PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT . '/';

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
 * @return list<array<string, mixed>>
 */
function showcase_samples(): array
{
    $markdownOne = <<<'MD'
# WordPress data liberation checklist

This note mirrors the kind of Markdown found in migration docs and issue
trackers.

- Preserve headings, paragraphs, lists, and inline emphasis.
- Keep [links](https://wordpress.org/) readable.
- Convert source content to clean WordPress blocks.

| Source | Expected handoff |
| --- | --- |
| Markdown | paragraphs and lists |
| CSV | table fallback |
| HTML | native structure |
MD;

    $markdownTwo = <<<'MD'
---
title: Release notes excerpt
---

## Highlights

GitHub-flavored Markdown commonly mixes task lists, fenced code, and tables.

- [x] Import document text
- [ ] Preserve richer layout

```php
echo "portable conversion";
```
MD;

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
            inline_sample('commonmark-notes', 'commonmark', 'commonmark-notes.md', 'CommonMark migration note', $markdownOne, 'Inline documentation-style Markdown sample.'),
            inline_sample('commonmark-release', 'commonmark', 'commonmark-release.md', 'CommonMark release note', $markdownTwo, 'Inline release-note Markdown sample.'),
        ],
        'commonmark_x' => [
            inline_sample('commonmarkx-release', 'commonmark_x', 'commonmarkx-release.md', 'CommonMark extensions sample', $markdownTwo, 'Inline release-note Markdown with extension-oriented constructs.'),
            inline_sample('commonmarkx-notes', 'commonmark_x', 'commonmarkx-notes.md', 'CommonMark extensions notes', $markdownOne, 'Inline documentation-style Markdown sample.'),
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
            upstream_sample('docx-headers', 'docx', 'test/docx/headers.docx', 'DOCX headers', 'WordprocessingML package from upstream Pandoc DOCX reader tests.'),
            upstream_sample('docx-tables', 'docx', 'test/docx/tables.docx', 'DOCX tables', 'DOCX table coverage from upstream Pandoc reader tests.'),
        ],
        'endnotexml' => [
            upstream_sample('endnotexml-reader', 'endnotexml', 'test/endnotexml-reader.xml', 'EndNote XML reader fixture', 'Bibliography XML fixture from upstream Pandoc tests.'),
            inline_sample('endnotexml-book', 'endnotexml', 'endnote-book.xml', 'EndNote XML book record', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<xml><records><record><ref-type name=\"Book\">6</ref-type><contributors><authors><author>Knuth, Donald E.</author></authors></contributors><titles><title>The TeXbook</title></titles><dates><year>1984</year></dates><publisher>Addison-Wesley</publisher></record></records></xml>\n", 'Inline EndNote XML record for a real published book.'),
        ],
        'epub' => [
            upstream_sample('epub-wasteland', 'epub', 'test/epub/wasteland.epub', 'The Waste Land EPUB', 'EPUB book fixture from upstream Pandoc tests.'),
            upstream_sample('epub-features', 'epub', 'test/epub/features.epub', 'EPUB feature coverage', 'EPUB feature fixture from upstream Pandoc tests.'),
        ],
        'fb2' => [
            upstream_sample('fb2-basic', 'fb2', 'test/fb2/basic.fb2', 'FB2 basic book', 'FictionBook XML sample from upstream Pandoc tests.'),
            upstream_sample('fb2-notes', 'fb2', 'test/fb2/reader/notes.fb2', 'FB2 notes', 'FictionBook notes fixture from upstream Pandoc tests.'),
        ],
        'gfm' => [
            inline_sample('gfm-release', 'gfm', 'gfm-release.md', 'GFM release notes', $markdownTwo, 'Inline GitHub-flavored Markdown sample.'),
            inline_sample('gfm-notes', 'gfm', 'gfm-notes.md', 'GFM migration note', $markdownOne, 'Inline documentation-style GitHub-flavored Markdown sample.'),
        ],
        'markdown_github' => [
            inline_sample('markdown-github-release', 'markdown_github', 'markdown-github-release.md', 'GitHub Markdown alias', $markdownTwo, 'Inline GitHub Markdown alias sample.'),
            inline_sample('markdown-github-notes', 'markdown_github', 'markdown-github-notes.md', 'GitHub Markdown notes', $markdownOne, 'Inline GitHub Markdown alias sample.'),
        ],
        'html' => [
            upstream_sample('html-reader', 'html', 'test/html-reader.html', 'HTML reader fixture', 'HTML fixture from upstream Pandoc tests.'),
            upstream_sample('html-template', 'html', 'data/templates/styles.html', 'Pandoc HTML template fragment', 'Real Pandoc project HTML template asset.'),
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
            inline_sample('markdown-notes', 'markdown', 'markdown-notes.md', 'Markdown migration note', $markdownOne, 'Inline documentation-style Markdown sample.'),
            inline_sample('markdown-release', 'markdown', 'markdown-release.md', 'Markdown release note', $markdownTwo, 'Inline release-note Markdown sample.'),
        ],
        'markdown_mmd' => [
            inline_sample('markdown-mmd-notes', 'markdown_mmd', 'markdown-mmd-notes.md', 'MultiMarkdown profile', $markdownOne, 'Inline Markdown read through the MultiMarkdown profile.'),
            inline_sample('markdown-mmd-release', 'markdown_mmd', 'markdown-mmd-release.md', 'MultiMarkdown release note', $markdownTwo, 'Inline Markdown read through the MultiMarkdown profile.'),
        ],
        'markdown_phpextra' => [
            inline_sample('markdown-phpextra-notes', 'markdown_phpextra', 'markdown-phpextra-notes.md', 'PHP Markdown Extra profile', $markdownOne, 'Inline Markdown read through the PHP Markdown Extra profile.'),
            inline_sample('markdown-phpextra-release', 'markdown_phpextra', 'markdown-phpextra-release.md', 'PHP Markdown Extra release note', $markdownTwo, 'Inline Markdown read through the PHP Markdown Extra profile.'),
        ],
        'markdown_strict' => [
            inline_sample('markdown-strict-notes', 'markdown_strict', 'markdown-strict-notes.md', 'Strict Markdown profile', $markdownOne, 'Inline Markdown read through the strict profile.'),
            inline_sample('markdown-strict-release', 'markdown_strict', 'markdown-strict-release.md', 'Strict Markdown release note', $markdownTwo, 'Inline Markdown read through the strict profile.'),
        ],
        'native' => [
            inline_sample('native-basic', 'native', 'native-basic.native', 'Pandoc native AST', $native, 'Inline Pandoc native AST fixture.'),
            upstream_sample('native-markdown-more', 'native', 'test/markdown-reader-more.native', 'Upstream native Markdown reader output', 'Pandoc native fixture from upstream tests.'),
        ],
        'odt' => [
            upstream_sample('odt-headers', 'odt', 'test/odt/odt/headers.odt', 'ODT headers', 'OpenDocument text fixture from upstream Pandoc tests.'),
            upstream_sample('odt-table-spans', 'odt', 'test/odt/odt/tableWithSpans.odt', 'ODT table spans', 'ODT table fixture from upstream Pandoc tests.'),
        ],
        'opml' => [
            upstream_sample('opml-reader', 'opml', 'test/opml-reader.opml', 'OPML reader fixture', 'OPML outline fixture from upstream Pandoc tests.'),
            inline_sample('opml-outline', 'opml', 'research-outline.opml', 'Research outline', $opml, 'Inline OPML outline.'),
        ],
        'pptx' => [
            upstream_sample('pptx-basic', 'pptx', 'test/pptx-reader/basic.pptx', 'Basic PPTX', 'Presentation fixture from upstream Pandoc tests.'),
            upstream_sample('pptx-tables', 'pptx', 'test/pptx/tables/output.pptx', 'PPTX tables', 'Generated presentation fixture from upstream Pandoc tests.'),
        ],
        'ris' => [
            inline_sample('ris-texbook', 'ris', 'texbook.ris', 'RIS book record', $ris, 'Inline RIS record for a real published book.'),
            inline_sample('ris-web', 'ris', 'wordpress.ris', 'RIS website record', "TY  - ELEC\nAU  - WordPress Contributors\nTI  - WordPress\nPY  - 2026\nUR  - https://wordpress.org/\nER  -\n", 'Inline RIS record assembled from real WordPress project metadata.'),
        ],
        'rtf' => [
            upstream_sample('rtf-template', 'rtf', 'data/templates/default.rtf', 'Pandoc default RTF template', 'Real RTF template from Pandoc project data.'),
            inline_sample('rtf-simple', 'rtf', 'simple.rtf', 'Simple RTF document', "{\\rtf1\\ansi\\deff0 {\\fonttbl {\\f0 Arial;}}\\f0\\fs24 RTF migration sample\\par This is a second RTF input.\\par}\n", 'Inline RTF document sample.'),
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
        ],
        'xml' => [
            inline_sample('xml-docbook-generic', 'xml', 'generic-docbook.xml', 'Generic XML document', $docbook, 'Inline XML document read through the generic XML path.'),
            inline_sample('xml-outline-generic', 'xml', 'generic-outline.xml', 'Generic XML outline', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<document><title>Generic XML outline</title><section><heading>Inventory</heading><p>Plain XML read through the generic XML path.</p></section></document>\n", 'Inline generic XML document.'),
        ],
        'pdf' => [
            local_sample('pdf-wrapped-content', 'pdf', 'lanes/markerpdf/fixtures/wordpress-wrapped-content.pdf', 'Wrapped-content PDF fixture', 'Small PDF fixture from the local markerpdf lane used to exercise PDF text handoff.'),
            local_sample('pdf-import-content', 'pdf', 'lanes/markerpdf/fixtures/wordpress-import-content.pdf', 'Import-content PDF fixture', 'Second small PDF fixture from the local markerpdf lane.'),
        ],
        'doc' => [
            inline_sample('doc-legacy-placeholder', 'doc', 'legacy-word.doc', "Legacy Word binary placeholder", "This fallback is intentionally plain text with a .doc extension because no stable public binary .doc sample is bundled with Pandoc. It still exercises the local legacy-doc path and records any conversion failure.\n", 'Inline fallback pending a stable public legacy Word binary sample.'),
            inline_sample('doc-legacy-placeholder-2', 'doc', 'legacy-word-2.doc', "Legacy Word second placeholder", "Second .doc placeholder pending a stable public binary .doc sample.\n", 'Inline fallback pending a stable public legacy Word binary sample.'),
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
 * @return array{ok:bool, path?:string, error?:string}
 */
function write_output_from_process(string $dir, string $name, string $sourcePath, string $from, string $to): array
{
    $result = run_process([PHP_BINARY, __FILE__, '--convert-local', $sourcePath, $from, $to], 25);
    if ($result['exitCode'] === 0) {
        file_put_contents($dir . '/' . $name, $result['stdout']);

        return ['ok' => true, 'path' => 'outputs/' . basename($dir) . '/' . $name];
    }

    $message = sanitize_generated_text(trim($result['stderr'] . "\n" . $result['stdout']));
    if ($message === '') {
        $message = 'Local converter exited with code ' . $result['exitCode'];
    }
    file_put_contents($dir . '/' . $name . '.error.txt', $message);

    return ['ok' => false, 'error' => $message, 'path' => 'outputs/' . basename($dir) . '/' . $name . '.error.txt'];
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
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

ensure_clean_dir($siteDir);
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
            $downloadError = 'Unable to download ' . $sample['url'];
            file_put_contents($target . '.download-error.txt', $downloadError);
        }
    }
    $sourcePath = is_file($target) ? $target : $target . '.download-error.txt';
    $outDir = $outputsDir . '/' . $id;
    ensure_dir($outDir);

    $haskell = is_file($target) ? run_haskell_pandoc($target, $format, $outDir) : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $phpHtml = is_file($target) ? write_output_from_process($outDir, 'php.html', $target, $format, 'html') : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $wpBlocks = is_file($target) ? write_output_from_process($outDir, 'wordpress-blocks.html', $target, $format, 'wordpress') : ['ok' => false, 'error' => $downloadError ?? 'missing source file'];
    $preview = is_file($target) ? sample_preview_html($target) : '';

    $records[] = [
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
    ];
}

$coveredFormats = array_values(array_unique(array_map(fn (array $record): string => $record['format'], $records)));
sort($coveredFormats);
$missingFormats = array_values(array_diff($formats, $coveredFormats));

file_put_contents($siteDir . '/manifest.json', json_encode([
    'generatedAt' => gmdate('c'),
    'pandocVersion' => sanitize_generated_text(trim(run_process(['pandoc', '--version'], 10)['stdout'])),
    'formats' => $formats,
    'coveredFormats' => $coveredFormats,
    'missingFormats' => $missingFormats,
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
.layout {
  width: min(1180px, calc(100% - 32px));
  margin: 24px auto 64px;
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
  grid-template-columns: minmax(260px, 0.9fr) minmax(360px, 1.4fr);
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
.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  padding: 10px;
  border-bottom: 1px solid var(--line);
}
.tab {
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
}
.tab[aria-selected="true"] {
  background: var(--accent);
  color: var(--accent-ink);
  border-color: var(--accent);
}
.view-source {
  margin-left: auto;
}
.status-ok { color: var(--ok); }
.status-fail { color: var(--bad); }
.status-warn { color: var(--warn); }
.tab-panel {
  display: none;
}
.tab-panel.active {
  display: block;
}
.render-frame {
  width: 100%;
  height: 430px;
  border: 0;
  background: #fff;
}
.error-box,
.source-box {
  margin: 0;
  height: 430px;
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
  .comparison {
    grid-template-columns: 1fr;
  }
  h1 {
    font-size: 38px;
  }
  .view-source {
    margin-left: 0;
  }
}
CSS;

$js = <<<'JS'
document.addEventListener('click', (event) => {
  const tab = event.target.closest('[data-tab-target]');
  if (tab) {
    const box = tab.closest('.converted');
    const target = tab.getAttribute('data-tab-target');
    box.querySelectorAll('[data-tab-target]').forEach((button) => {
      button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
    });
    box.querySelectorAll('.tab-panel').forEach((panel) => {
      panel.classList.toggle('active', panel.id === target);
    });
    box.classList.remove('source-mode');
  }

  const source = event.target.closest('[data-source-toggle]');
  if (source) {
    const box = source.closest('.converted');
    const enabled = box.classList.toggle('source-mode');
    source.textContent = enabled ? 'Rendered view' : 'View source';
  }
});
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
$html .= '<p class="lede">Real and upstream fixture files are converted three ways: Haskell Pandoc to HTML, the local PHP port to HTML, and the local PHP port to WordPress block markup. The WordPress block render is the default tab for each sample.</p>';
$html .= '<div class="stats">';
$html .= '<div class="stat"><strong>' . count($coveredFormats) . '</strong><span>covered input formats</span></div>';
$html .= '<div class="stat"><strong>' . count($records) . '</strong><span>source files</span></div>';
$html .= '<div class="stat"><strong>' . $successCount . '/' . $totalConversions . '</strong><span>successful conversions</span></div>';
$html .= '<div class="stat"><strong>' . gmdate('Y-m-d') . '</strong><span>generated</span></div>';
$html .= '</div></div></header><main class="layout">';
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
        $html .= '<section class="converted" data-sample="' . h($record['id']) . '"><div class="tabs" role="tablist">';
        $tabs = [
            'wpBlocks' => ['label' => 'PHP WordPress blocks', 'short' => 'WP blocks'],
            'phpHtml' => ['label' => 'PHP HTML', 'short' => 'PHP HTML'],
            'haskell' => ['label' => 'Haskell Pandoc HTML', 'short' => 'Haskell HTML'],
        ];
        $first = true;
        foreach ($tabs as $key => $tabInfo) {
            $state = ($record[$key]['ok'] ?? false) ? 'status-ok' : 'status-fail';
            $html .= '<button class="tab ' . $state . '" type="button" role="tab" aria-selected="' . ($first ? 'true' : 'false') . '" data-tab-target="' . h($record['id'] . '-' . $key) . '">' . h($tabInfo['short']) . '</button>';
            $first = false;
        }
        $html .= '<button class="tab view-source" type="button" data-source-toggle>View source</button></div>';
        $first = true;
        foreach ($tabs as $key => $tabInfo) {
            $panelId = $record['id'] . '-' . $key;
            $result = $record[$key];
            $html .= '<div class="tab-panel' . ($first ? ' active' : '') . '" id="' . h($panelId) . '" role="tabpanel">';
            if (($result['ok'] ?? false) === true) {
                $path = (string) $result['path'];
                $source = file_get_contents($siteDir . '/' . $path);
                $html .= '<iframe class="render-frame" title="' . h($record['label'] . ' ' . $tabInfo['label']) . '" src="' . h($path) . '"></iframe>';
                $html .= '<pre class="source-box">' . h(is_string($source) ? $source : '') . '</pre>';
            } else {
                $error = (string) ($result['error'] ?? 'Conversion failed.');
                $errorPath = isset($result['path']) ? $siteDir . '/' . $result['path'] : '';
                $source = is_file($errorPath) ? file_get_contents($errorPath) : $error;
                $html .= '<pre class="error-box">' . h($error) . '</pre>';
                $html .= '<pre class="source-box">' . h(is_string($source) ? $source : $error) . '</pre>';
            }
            $html .= '</div>';
            $first = false;
        }
        $notes = is_array($record['support']) ? (string) ($record['support']['notes'] ?? '') : '';
        $html .= '<div class="note">' . h($notes) . '</div></section>';
        $html .= '</article>';
    }
    $html .= '</section>';
}
$html .= '</main><script src="showcase.js"></script></body></html>';

file_put_contents($siteDir . '/index.html', $html);

echo 'Generated pandoc-showcase with ' . count($records) . ' samples across ' . count($coveredFormats) . " formats.\n";
if ($missingFormats !== []) {
    echo 'Missing sample formats: ' . implode(', ', $missingFormats) . "\n";
}
