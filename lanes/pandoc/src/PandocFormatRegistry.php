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
        'doc',
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

    /** @var list<string> */
    private const WIKI_INPUT_FORMATS = [
        'creole',
        'dokuwiki',
        'jira',
        'mediawiki',
        'tikiwiki',
        'twiki',
        'vimwiki',
    ];

    /** @var list<string> */
    private const WIKI_OUTPUT_FORMATS = [
        'dokuwiki',
        'jira',
        'mediawiki',
        'xwiki',
        'zimwiki',
    ];

    /** @var array<string, string> */
    private const WIKI_FORMAT_LABELS = [
        'creole' => 'Creole wiki',
        'dokuwiki' => 'DokuWiki',
        'jira' => 'Jira wiki markup',
        'mediawiki' => 'MediaWiki',
        'tikiwiki' => 'TikiWiki',
        'twiki' => 'TWiki',
        'vimwiki' => 'Vimwiki',
        'xwiki' => 'XWiki',
        'zimwiki' => 'ZimWiki',
    ];

    /** @var array<string, string> */
    private const WIKI_EXTENSION_INFERENCE = [
        'dokuwiki' => 'dokuwiki',
        'wiki' => 'mediawiki',
    ];

    /**
     * @var array<string, array{status:string, implementation:string, notes:string}>
     */
    private const PHP_INPUT_SUPPORT = [
        'bibtex' => [
            'status' => 'partial',
            'implementation' => BibliographyReader::class,
            'notes' => 'Bibliography reader parses BibTeX entries into CSL item metadata and renders a shared AST bibliography definition list. Full Pandoc BibTeX reader parity remains open.',
        ],
        'biblatex' => [
            'status' => 'partial',
            'implementation' => BibliographyReader::class,
            'notes' => 'Bibliography reader parses BibLaTeX-oriented entries, aliases, xdata/crossref metadata, source-file diagnostics, and custom fields into CSL item metadata. Full Pandoc BibLaTeX reader parity remains open.',
        ],
        'bits' => [
            'status' => 'partial',
            'implementation' => XmlReader::class,
            'notes' => 'Bounded XML-family reader maps BITS/JATS book roots, titles, abstracts, body sections, lists, links, and tables into the shared AST with existing JATS/BITS diagnostic packets. Full Pandoc BITS parity remains open.',
        ],
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
        'csljson' => [
            'status' => 'partial',
            'implementation' => BibliographyReader::class,
            'notes' => 'Bibliography reader validates CSL JSON item lists, normalizes them through the CSL processor, and renders a shared AST bibliography definition list. Full Pandoc CSL JSON parity remains open.',
        ],
        'csv' => [
            'status' => 'partial',
            'implementation' => DelimitedTextReader::class,
            'notes' => 'Delimited text reader maps CSV rows into the shared table AST with Pandoc-compatible delimiter, quote, escape, keep-space, multiline-cell, row repair, control-character, and provenance diagnostics. The pinned direct CSV reader and shared CSV parser option fixtures are covered; RST csv-table integration is tracked with the RST reader gap.',
        ],
        'tsv' => [
            'status' => 'partial',
            'implementation' => DelimitedTextReader::class,
            'notes' => 'Delimited text reader maps TSV rows into the shared table AST with Pandoc-compatible tab delimiter, literal quote handling, post-delimiter space skipping, trailing empty field, ragged row, repair, control-character, and provenance diagnostics.',
        ],
        'docbook' => [
            'status' => 'partial',
            'implementation' => DocBookReader::class,
            'notes' => 'Bounded DocBook XML reader maps document metadata, sections, paragraphs, lists, links, CALS tables, media references, admonitions, code blocks, variable lists, and bibliography entries into the shared AST while preserving DocBook review packets. Full DocBook reader parity remains open.',
        ],
        'docx' => [
            'status' => 'partial',
            'implementation' => DocxReader::class,
            'notes' => 'Bounded DOCX package reader parses document.xml, styles, numbering levels/start/style/delimiter attributes, relationships, notes/comments, headers/footers, media references, bookmarks, simple reference fields, OMML equations, and core properties into the shared AST. Full DOCX parity remains open.',
        ],
        'epub' => [
            'status' => 'partial',
            'implementation' => EpubReader::class,
            'notes' => 'Bounded EPUB package reader resolves the OPF rootfile, extracts metadata, follows XHTML spine items, rewrites package-relative href/src resources, records image/resource references, records EPUB3 nav and NCX table-of-contents resources while preferring EPUB3 nav entries over duplicate NCX fallback entries, and maps spine content through the shared HTML-capable reader path. Full EPUB parity remains open.',
        ],
        'fb2' => [
            'status' => 'partial',
            'implementation' => Fb2Reader::class,
            'notes' => 'Bounded FB2 reader maps FictionBook XML sections, titles, epigraphs, poems, metadata, notes, and inline formatting into the shared AST for the pinned upstream FB2 reader golden fixtures.',
        ],
        'endnotexml' => [
            'status' => 'partial',
            'implementation' => BibliographyReader::class,
            'notes' => 'Bibliography reader parses bounded EndNote XML records into CSL item metadata with name, title, date, publication, attachment, and unsupported-field diagnostics. Full Pandoc EndNote XML reader parity remains open.',
        ],
        'gfm' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'GitHub-Flavored Markdown behavior is partially mapped through MarkdownReader and MarkdownWriter tests.',
        ],
        'html' => [
            'status' => 'partial',
            'implementation' => HtmlReader::class,
            'notes' => 'Dedicated HTML reader dispatch preserves existing DOM/raw-HTML behavior through the current HTML-capable reader bridge while full HTML5 tree construction remains open.',
        ],
        'ipynb' => [
            'status' => 'partial',
            'implementation' => IpynbReader::class,
            'notes' => 'Bounded IPYNB reader maps markdown, code, and raw cells into the shared AST with notebook, metadata, attachment, source-shape, execution, and output diagnostics without executing notebooks or exposing embedded output bytes. Native IPYNB writer parity remains open.',
        ],
        'jats' => [
            'status' => 'partial',
            'implementation' => XmlReader::class,
            'notes' => 'Bounded XML-family reader maps JATS article/book roots, titles, abstracts, body sections, lists, links, and tables into the shared AST while preserving the existing JATS/BITS diagnostic packet metadata. Full Pandoc JATS parity remains open.',
        ],
        'json' => [
            'status' => 'partial',
            'implementation' => JsonReader::class,
            'notes' => 'Reads the current Pandoc JSON AST encoding for the constructors covered by the shared PHP AST; complete constructor coverage remains open.',
        ],
        'jira' => [
            'status' => 'partial',
            'implementation' => JiraReader::class,
            'notes' => 'Bounded Jira wiki reader maps the pinned upstream Tests.Readers.Jira unit semantics for paragraphs, headings, lists, block quotes, tables, panels, core inline styles, links, images, and entities into the shared AST. Full jira-reader fixture parity remains open.',
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
        'opml' => [
            'status' => 'partial',
            'implementation' => OpmlReader::class,
            'notes' => 'Bounded OPML reader maps document title, owner, modified date, nested outlines, link outlines, and Markdown notes into the shared AST, with canonical native semantic parity for the pinned upstream opml-reader fixture. Full option and edge-case parity remains open.',
        ],
        'pptx' => [
            'status' => 'partial',
            'implementation' => PptxReader::class,
            'notes' => 'Bounded PPTX OpenXML package reader maps presentation slide order, title placeholders, text boxes, Wingdings/explicit bullet groups, simple tables, image references, SmartArt hierarchy with placeholder/layout sidecars, slide comments, shape z-order/layout metadata, and internal rich-media references into the shared AST with pinned upstream pptx-reader/basic fixture parity.',
        ],
        'ris' => [
            'status' => 'partial',
            'implementation' => BibliographyReader::class,
            'notes' => 'Bibliography reader parses bounded RIS records into CSL item metadata with type aliases, field provenance, attachments, user fields, and custom diagnostics. Full Pandoc RIS reader parity remains open.',
        ],
        'rtf' => [
            'status' => 'partial',
            'implementation' => RtfReader::class,
            'notes' => 'Bounded RTF reader maps paragraphs, escaped characters, unicode fallbacks, tabs, and core inline styles into the shared AST. Full RTF control-word, destination, table, image, and metadata parity remains open.',
        ],
        'xml' => [
            'status' => 'partial',
            'implementation' => XmlReader::class,
            'notes' => 'Bounded XML-family reader safely parses XML, records namespace/root provenance, and maps title/paragraph/list/link/table structures into the shared AST. Full Pandoc XML reader parity remains open.',
        ],
        'xlsx' => [
            'status' => 'partial',
            'implementation' => XlsxReader::class,
            'notes' => 'Bounded XLSX OpenXML package reader maps workbook sheets, shared strings, direct font bold/italic style indexes, dense sheet grids, first-row table headers, numeric cells, empty cells, and trailing empty-row trimming into the shared AST with pinned upstream xlsx-reader/basic fixture parity.',
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
        'doc' => [
            'status' => 'partial',
            'implementation' => LegacyDocReader::class,
            'notes' => 'Project-local legacy binary Word reader parses Compound File Binary containers, WordDocument text, OLE properties, metadata, fields, lists, notes, comments, sections, bookmarks, and review provenance into the shared AST. Legacy .doc is not an upstream Pandoc input format token.',
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
        'docx' => [
            'status' => 'partial',
            'implementation' => DocxWriter::class,
            'notes' => 'Bounded DOCX output emits a deterministic OPC ZIP package with [Content_Types].xml, root and document relationships, docProps core/app/custom properties, word document relationships, comments, footnotes, font table, theme, web settings, styles, numbering, settings, and local image media parts for core paragraph, heading, block quote, list, table, inline formatting, metadata, hyperlink, and image slices. Full upstream Docx writer golden package parity remains open.',
        ],
        'epub' => [
            'status' => 'partial',
            'implementation' => EpubWriter::class,
            'notes' => 'Bounded EPUB output emits an EPUB3 OCF ZIP package with stored mimetype, container.xml, OPF metadata/manifest/spine, nav.xhtml, toc.ncx, stylesheet, default title page with landmarks, media resources, cover-image manifest properties, and writerSplitLevel XHTML spine splitting rendered through the shared HTML writer. EPUB2 output and full media/template parity remain open.',
        ],
        'epub3' => [
            'status' => 'partial',
            'implementation' => EpubWriter::class,
            'notes' => 'Bounded EPUB3 output emits an OCF ZIP package with stored mimetype, container.xml, OPF metadata/manifest/spine, nav.xhtml, toc.ncx, stylesheet, default title page with landmarks, media resources, cover-image manifest properties, and writerSplitLevel XHTML spine splitting rendered through the shared HTML writer. Full Pandoc EPUB3 writer options, EPUB2, template customization, and advanced media parity remain open.',
        ],
        'gfm' => [
            'status' => 'partial',
            'implementation' => MarkdownWriter::class,
            'notes' => 'GFM details/list/raw HTML writer behavior is partially mapped.',
        ],
        'html' => [
            'status' => 'partial',
            'implementation' => HtmlWriter::class,
            'notes' => 'HTML writer covers core block/inline output and many upstream slices; standalone/template parity remains open.',
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
        'opml' => [
            'status' => 'partial',
            'implementation' => OpmlWriter::class,
            'notes' => 'Bounded OPML writer maps section headings to nested outline elements, heading inlines to escaped HTML text attributes, body content to Markdown _note attributes, and document metadata to the default OPML header, with byte-for-byte parity for the pinned upstream writer fixture. Full template and option parity remains open.',
        ],
        'plain' => [
            'status' => 'partial',
            'implementation' => PlainWriter::class,
            'notes' => 'Plain text writer covers bounded wrapping, table, inline, unicode width, and diagnostic slices. Full Pandoc plain writer parity remains open.',
        ],
        'pptx' => [
            'status' => 'partial',
            'implementation' => PptxWriter::class,
            'notes' => 'Bounded PPTX output emits a deterministic PresentationML OPC ZIP package with content types, root/presentation/slide relationships, core, extended, and custom document properties from Pandoc metadata, a slide master/layout/theme, table styles, title/content slides, standalone image-only and content-with-caption slide grouping with figure captions, bullet and ordered list paragraphs preserving inline runs, DrawingML tables, hyperlink relationships, local image media parts, Courier code inline/block runs, small-caps and adjacent-text inline run properties, background images from heading attributes, horizontal-rule slide splits, slide-level-0 first-heading title promotion, speaker-note notesSlides/notesMaster parts from upstream-style notes Divs and metadata notes, public endnote slides from inline Note nodes, raw OpenXML inline/block passthrough, and upstream-style dropping of non-OpenXML raw inline plus empty-alt unresolved image content. Full upstream PowerPoint writer template/layout/media parity remains open.',
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
     * @return list<string>
     */
    public static function wikiInputFormats(): array
    {
        return self::WIKI_INPUT_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function wikiOutputFormats(): array
    {
        return self::WIKI_OUTPUT_FORMATS;
    }

    /**
     * @return array<string, string>
     */
    public static function wikiExtensionInference(): array
    {
        return self::WIKI_EXTENSION_INFERENCE;
    }

    public static function inferWikiFormatFromExtension(string $extension): ?string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));

        return self::WIKI_EXTENSION_INFERENCE[$extension] ?? null;
    }

    /**
     * @return array<string, array{
     *     label:string,
     *     direction:string,
     *     input:array{status:string, implementation:string, notes:string},
     *     output:array{status:string, implementation:string, notes:string},
     *     extensionInferences:list<string>,
     *     directReaderParityClaimed:bool,
     *     directWriterParityClaimed:bool
     * }>
     */
    public static function wikiFormatRegistry(): array
    {
        $inputSupport = self::phpInputSupport();
        $outputSupport = self::phpOutputSupport();
        $formats = array_values(array_unique(array_merge(self::WIKI_INPUT_FORMATS, self::WIKI_OUTPUT_FORMATS)));
        $registry = [];

        foreach ($formats as $format) {
            $hasInput = in_array($format, self::WIKI_INPUT_FORMATS, true);
            $hasOutput = in_array($format, self::WIKI_OUTPUT_FORMATS, true);
            $input = $hasInput ? $inputSupport[$format] : self::notApplicableSupport('Not an upstream Pandoc wiki reader format.');
            $output = $hasOutput ? $outputSupport[$format] : self::notApplicableSupport('Not an upstream Pandoc wiki writer format.');

            $registry[$format] = [
                'label' => self::WIKI_FORMAT_LABELS[$format],
                'direction' => self::formatDirection($hasInput, $hasOutput),
                'input' => $input,
                'output' => $output,
                'extensionInferences' => self::extensionInferencesForFormat($format),
                'directReaderParityClaimed' => $input['status'] === 'complete',
                'directWriterParityClaimed' => $output['status'] === 'complete',
            ];
        }

        return $registry;
    }

    /**
     * @return array{
     *     inputFormats:list<string>,
     *     outputFormats:list<string>,
     *     uniqueFormats:list<string>,
     *     directionBuckets:array<string, list<string>>,
     *     inputStatusBuckets:array<string, list<string>>,
     *     outputStatusBuckets:array<string, list<string>>,
     *     extensionInference:array<string, string>,
     *     directReaderParityClaimed:bool,
     *     directWriterParityClaimed:bool
     * }
     */
    public static function wikiFormatRegistrySummary(): array
    {
        $registry = self::wikiFormatRegistry();
        $directionBuckets = [
            'input-output' => [],
            'input-only' => [],
            'output-only' => [],
        ];
        $inputStatusBuckets = [];
        $outputStatusBuckets = [];
        $directReaderParityClaimed = true;
        $directWriterParityClaimed = true;

        foreach ($registry as $format => $entry) {
            $directionBuckets[$entry['direction']][] = $format;

            if ($entry['input']['status'] !== 'not-applicable') {
                $inputStatusBuckets[$entry['input']['status']][] = $format;
                $directReaderParityClaimed = $directReaderParityClaimed && $entry['directReaderParityClaimed'];
            }

            if ($entry['output']['status'] !== 'not-applicable') {
                $outputStatusBuckets[$entry['output']['status']][] = $format;
                $directWriterParityClaimed = $directWriterParityClaimed && $entry['directWriterParityClaimed'];
            }
        }

        return [
            'inputFormats' => self::WIKI_INPUT_FORMATS,
            'outputFormats' => self::WIKI_OUTPUT_FORMATS,
            'uniqueFormats' => array_keys($registry),
            'directionBuckets' => $directionBuckets,
            'inputStatusBuckets' => $inputStatusBuckets,
            'outputStatusBuckets' => $outputStatusBuckets,
            'extensionInference' => self::WIKI_EXTENSION_INFERENCE,
            'directReaderParityClaimed' => $directReaderParityClaimed,
            'directWriterParityClaimed' => $directWriterParityClaimed,
        ];
    }

    public static function inferTabularDataFormatFromExtension(string $extension): ?string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));

        return match ($extension) {
            'csv' => 'csv',
            'tab', 'tsv' => 'tsv',
            default => null,
        };
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
     * @return array{status:string, implementation:string, notes:string}
     */
    private static function notApplicableSupport(string $notes): array
    {
        return [
            'status' => 'not-applicable',
            'implementation' => '',
            'notes' => $notes,
        ];
    }

    private static function formatDirection(bool $hasInput, bool $hasOutput): string
    {
        if ($hasInput && $hasOutput) {
            return 'input-output';
        }

        if ($hasInput) {
            return 'input-only';
        }

        return 'output-only';
    }

    /**
     * @return list<string>
     */
    private static function extensionInferencesForFormat(string $format): array
    {
        $extensions = [];
        foreach (self::WIKI_EXTENSION_INFERENCE as $extension => $inferredFormat) {
            if ($inferredFormat === $format) {
                $extensions[] = $extension;
            }
        }

        return $extensions;
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
