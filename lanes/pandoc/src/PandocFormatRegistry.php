<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocFormatRegistry
{
    public const UPSTREAM_MANUAL_DATE = '2026-06-03';
    public const UPSTREAM_MANUAL_URL = 'https://pandoc.org/demo/example2.html';
    public const UPSTREAM_SOURCE_COMMIT = '912bfa5e2e3f5c74eb125dfc19404f67c61ca58b';

    /** @var list<string> */
    private const UPSTREAM_INPUT_FORMATS = [
        'asciidoc',
        'bibtex',
        'biblatex',
        'bits',
        'commonmark',
        'commonmark_x',
        'creole',
        'csljson',
        'csv',
        'tsv',
        'djot',
        'docbook',
        'docx',
        'dokuwiki',
        'endnotexml',
        'epub',
        'fb2',
        'gfm',
        'markdown_github',
        'haddock',
        'html',
        'ipynb',
        'jats',
        'jira',
        'json',
        'latex',
        'markdown',
        'markdown_mmd',
        'markdown_phpextra',
        'markdown_strict',
        'mediawiki',
        'man',
        'mdoc',
        'muse',
        'native',
        'odt',
        'opml',
        'org',
        'pod',
        'pptx',
        'ris',
        'rtf',
        'rst',
        't2t',
        'textile',
        'tikiwiki',
        'twiki',
        'typst',
        'vimwiki',
        'xlsx',
        'xml',
    ];

    /** @var list<string> */
    private const UPSTREAM_OUTPUT_FORMATS = [
        'ansi',
        'asciidoc',
        'asciidoc_legacy',
        'asciidoctor',
        'bbcode',
        'bbcode_fluxbb',
        'bbcode_phpbb',
        'bbcode_steam',
        'bbcode_hubzilla',
        'bbcode_xenforo',
        'beamer',
        'bibtex',
        'biblatex',
        'chunkedhtml',
        'commonmark',
        'commonmark_x',
        'context',
        'csljson',
        'djot',
        'docbook',
        'docbook4',
        'docbook5',
        'docx',
        'dokuwiki',
        'epub',
        'epub3',
        'epub2',
        'fb2',
        'gfm',
        'markdown_github',
        'haddock',
        'html',
        'html5',
        'html4',
        'icml',
        'ipynb',
        'jats_archiving',
        'jats_articleauthoring',
        'jats_publishing',
        'jats',
        'jira',
        'json',
        'latex',
        'man',
        'markdown',
        'markdown_mmd',
        'markdown_phpextra',
        'markdown_strict',
        'markua',
        'mediawiki',
        'ms',
        'muse',
        'native',
        'odt',
        'opml',
        'opendocument',
        'org',
        'pdf',
        'plain',
        'pptx',
        'rst',
        'rtf',
        'texinfo',
        'textile',
        'slideous',
        'slidy',
        'dzslides',
        'revealjs',
        's5',
        'tei',
        'typst',
        'vimdoc',
        'xml',
        'xwiki',
        'zimwiki',
    ];

    /** @var array<string, string> */
    private const INPUT_ALIASES = [
        'bits' => 'jats',
        'markdown_github' => 'gfm',
    ];

    /** @var list<string> */
    private const LOCAL_INPUT_FORMATS = [
        'pdf',
    ];

    /** @var array<string, string> */
    private const OUTPUT_ALIASES = [
        'asciidoctor' => 'asciidoc',
        'docbook' => 'docbook5',
        'epub' => 'epub3',
        'html5' => 'html',
        'jats' => 'jats_archiving',
        'markdown_github' => 'gfm',
    ];

    /**
     * @var array<string, array{status:string, implementation:string, notes:string}>
     */
    private const PHP_INPUT_SUPPORT = [
        'commonmark' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Uses the shared Markdown reader with CommonMark-oriented slices; full extension parity remains open.',
        ],
        'commonmark_x' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Uses the shared Markdown reader with raw attribute and extension slices; full extension parity remains open.',
        ],
        'docbook' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Bounded DocBook table command fixtures are mapped; full DocBook XML reader is not implemented.',
        ],
        'docx' => [
            'status' => 'partial',
            'implementation' => DocxReader::class,
            'notes' => 'Bounded DOCX package reader parses document.xml, styles, numbering levels/start/style/delimiter attributes, relationships, notes/comments, headers/footers, media references, bookmarks, simple reference fields, OMML equations, and core properties into the shared AST. Full DOCX parity remains open.',
        ],
        'epub' => [
            'status' => 'partial',
            'implementation' => EpubReader::class,
            'notes' => 'Bounded EPUB package reader resolves the OPF rootfile, records OPF package attributes, bindings, collections, manifest fallback metadata, and selected unique identifiers, extracts core Dublin Core metadata, follows linear XHTML spine items, skips non-linear spine resources from body content while recording them, rewrites package-relative href/src/poster resources, records image/resource references, catalogs manifest asset resources, can extract bounded base64 resource payload metadata, parses hierarchical EPUB3 toc/landmark/page-list nav and NCX toc/page-list resources into metadata, records OPF media-overlay links with SMIL text/audio target metadata, reads EPUB/ARIA footnote semantics through the shared HTML-capable reader path, and maps spine content into the shared AST. Full EPUB parity remains open.',
        ],
        'gfm' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'GitHub-Flavored Markdown behavior is partially mapped through MarkdownReader and MarkdownWriter tests.',
        ],
        'html' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'HTML reader slices cover many DOM and raw HTML branches; full HTML5 tree construction remains open.',
        ],
        'json' => [
            'status' => 'partial',
            'implementation' => JsonReader::class,
            'notes' => 'Reads the current Pandoc JSON AST encoding for the constructors covered by the shared PHP AST; complete constructor coverage remains open.',
        ],
        'latex' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Raw TeX and bounded LaTeX table/math behavior are mapped; full LaTeX reader parity remains open.',
        ],
        'markdown' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Primary native PHP reader with broad Pandoc Markdown behavior slices.',
        ],
        'markdown_github' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Deprecated upstream token handled as a GFM-family Markdown variant.',
        ],
        'markdown_mmd' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'MultiMarkdown-adjacent slices exist, but full MultiMarkdown parity remains open.',
        ],
        'markdown_phpextra' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'PHP Markdown Extra-adjacent slices exist, but full variant parity remains open.',
        ],
        'markdown_strict' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Strict Markdown uses the shared reader without a complete extension disabling matrix.',
        ],
        'native' => [
            'status' => 'partial',
            'implementation' => NativeReader::class,
            'notes' => 'NativeReader parses a large upstream .native fixture set, but complete constructor parity is still audited as partial.',
        ],
        'odt' => [
            'status' => 'partial',
            'implementation' => OdtReader::class,
            'notes' => 'Bounded ODT package reader parses content.xml, meta.xml, text/list styles, headings, paragraphs, ordered and bullet lists, tables, links, styled spans, line breaks, images, and image/resource package references into the shared AST. Full ODT parity remains open.',
        ],
    ];

    /**
     * Project-local inputs needed by WordPress ingestion but not listed as
     * upstream Pandoc reader formats.
     *
     * @var array<string, array{status:string, implementation:string, notes:string}>
     */
    private const PHP_LOCAL_INPUT_SUPPORT = [
        'pdf' => [
            'status' => 'partial',
            'implementation' => PdfReader::class,
            'notes' => 'Project-local markerPDF bridge extracts searchable PDF text lines, records basic PDF structural provenance, and maps simple headings, paragraphs, lists, links, and aligned text-table rows into the shared AST. PDF is not an upstream Pandoc input format.',
        ],
    ];

    /**
     * @var array<string, array{status:string, implementation:string, notes:string}>
     */
    private const PHP_OUTPUT_SUPPORT = [
        'commonmark' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'CommonMark writer branches are partially mapped through the MarkdownWriter variant option.',
        ],
        'commonmark_x' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'CommonMark with extensions is partially mapped through raw inline/block branch tests.',
        ],
        'epub' => [
            'status' => 'partial',
            'implementation' => EpubWriter::class,
            'notes' => 'Alias-compatible EPUB output uses the bounded EPUB3 writer for native PHP package generation.',
        ],
        'epub3' => [
            'status' => 'partial',
            'implementation' => EpubWriter::class,
            'notes' => 'Bounded EPUB3 writer emits a native PHP EPUB package with stored mimetype, META-INF/container.xml, OPF package attributes, Dublin Core metadata, manifest/spine, bindings, collections, manifest fallback/fallback-style metadata, fixed-layout viewport metadata in XHTML spine heads, hierarchical nav.xhtml TOC/landmarks/page-list navigation, one or more XHTML spine documents rendered through HtmlWriter, EPUB-specific normalization for raw HTML fragments so common void elements, boolean attributes, HTML named entities, and raw script/style text remain XML-valid, optional or metadata-carried packaged resources with preserved manifest ids, OPF guide references, cover-image manifest properties, CSS links, chapter-relative resource rewriting including poster attributes, OPF media-overlay links to packaged SMIL resources, packaged HTML5 media resources, EPUB/ARIA footnote/pagebreak spine semantics, and OPF mathml/svg/scripted/remote-resources manifest properties for matching XHTML spine content. Full EPUB writer parity and generic TeX-to-MathML conversion remain open.',
        ],
        'epub2' => [
            'status' => 'partial',
            'implementation' => EpubWriter::class,
            'notes' => 'Bounded EPUB2 writer mode emits an OPF 2.0 package with NCX navigation, XHTML spine documents, Dublin Core metadata, guide references, and native PHP OCF packaging. Deeper EPUB2 parity remains open.',
        ],
        'gfm' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'GFM details/list/raw HTML writer behavior is partially mapped.',
        ],
        'html' => [
            'status' => 'partial',
            'implementation' => HtmlWriter::class,
            'notes' => 'HTML writer covers core block/inline output and many upstream slices, including explicit MathML payload preservation when the mathml writer method is selected. Standalone/template parity and generic TeX-to-MathML conversion remain open.',
        ],
        'html5' => [
            'status' => 'partial',
            'implementation' => HtmlWriter::class,
            'notes' => 'HTML5 is treated as the same current HTML writer target for now.',
        ],
        'json' => [
            'status' => 'partial',
            'implementation' => JsonWriter::class,
            'notes' => 'Writes the current Pandoc JSON AST encoding for the constructors covered by the shared PHP AST; complete constructor coverage remains open.',
        ],
        'latex' => [
            'status' => 'partial',
            'implementation' => LatexWriter::class,
            'notes' => 'LaTeX writer covers bounded block, inline, math, and raw TeX slices; full writer parity remains open.',
        ],
        'markdown' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'Primary Markdown writer target with broad Pandoc Markdown behavior slices.',
        ],
        'markdown_github' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'Deprecated upstream token handled as a GFM-family Markdown writer variant.',
        ],
        'markdown_mmd' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'MultiMarkdown output is partially mapped through MarkdownWriter variants.',
        ],
        'markdown_phpextra' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'PHP Markdown Extra output is partially mapped through MarkdownWriter variants.',
        ],
        'markdown_strict' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'Strict Markdown output lacks a complete extension disabling matrix.',
        ],
        'native' => [
            'status' => 'partial',
            'implementation' => NativeWriter::class,
            'notes' => 'NativeWriter can render the current AST subset and round-trip many fixtures; complete constructor parity remains open.',
        ],
        'plain' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'Plain text output uses MarkdownWriter variant=plain with template slices.',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function upstreamInputFormats(): array
    {
        return self::UPSTREAM_INPUT_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function upstreamOutputFormats(): array
    {
        return self::UPSTREAM_OUTPUT_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function localInputFormats(): array
    {
        return self::LOCAL_INPUT_FORMATS;
    }

    /**
     * @return array<string, string>
     */
    public static function inputAliases(): array
    {
        return self::INPUT_ALIASES;
    }

    /**
     * @return array<string, string>
     */
    public static function outputAliases(): array
    {
        return self::OUTPUT_ALIASES;
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function phpInputSupport(): array
    {
        return self::supportMap(self::UPSTREAM_INPUT_FORMATS, self::PHP_INPUT_SUPPORT);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function phpLocalInputSupport(): array
    {
        return self::supportMap(self::LOCAL_INPUT_FORMATS, self::PHP_LOCAL_INPUT_SUPPORT);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function phpOutputSupport(): array
    {
        return self::supportMap(self::UPSTREAM_OUTPUT_FORMATS, self::PHP_OUTPUT_SUPPORT);
    }

    /**
     * @return list<string>
     */
    public static function unsupportedInputFormats(): array
    {
        return self::formatsWithStatus(self::phpInputSupport(), 'unsupported');
    }

    /**
     * @return list<string>
     */
    public static function unsupportedOutputFormats(): array
    {
        return self::formatsWithStatus(self::phpOutputSupport(), 'unsupported');
    }

    /**
     * @param list<string> $formats
     * @param array<string, array{status:string, implementation:string, notes:string}> $implemented
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    private static function supportMap(array $formats, array $implemented): array
    {
        $support = [];
        foreach ($formats as $format) {
            $support[$format] = $implemented[$format] ?? [
                'status' => 'unsupported',
                'implementation' => '',
                'notes' => 'No native PHP reader or writer is registered for this upstream Pandoc format yet.',
            ];
        }

        return $support;
    }

    /**
     * @param array<string, array{status:string, implementation:string, notes:string}> $support
     * @return list<string>
     */
    private static function formatsWithStatus(array $support, string $status): array
    {
        $formats = [];
        foreach ($support as $format => $entry) {
            if ($entry['status'] === $status) {
                $formats[] = $format;
            }
        }

        return $formats;
    }
}
