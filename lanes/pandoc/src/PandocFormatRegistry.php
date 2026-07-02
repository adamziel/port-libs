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

    /** @var list<string> */
    private const ROFF_MANUAL_INPUT_FORMATS = [
        'man',
        'mdoc',
    ];

    /** @var list<string> */
    private const ROFF_MANUAL_OUTPUT_FORMATS = [
        'man',
        'ms',
    ];

    /** @var array<string, string> */
    private const ROFF_MANUAL_FORMAT_LABELS = [
        'man' => 'roff man manual page',
        'mdoc' => 'mdoc manual page',
        'ms' => 'roff ms document',
    ];

    /** @var array<string, string> */
    private const ROFF_MANUAL_EXTENSION_INFERENCE = [
        '.ms' => 'ms',
        '.roff' => 'ms',
        '.[1-9]' => 'man',
        '.[1-9][a-z]+' => 'man',
    ];

    /** @var array<string, array{format:string, kind:string, manualSection:bool}> */
    private const ROFF_MANUAL_EXTENSION_PATTERN_METADATA = [
        '.ms' => [
            'format' => 'ms',
            'kind' => 'ms-macro-package',
            'manualSection' => false,
        ],
        '.roff' => [
            'format' => 'ms',
            'kind' => 'generic-roff-source',
            'manualSection' => false,
        ],
        '.[1-9]' => [
            'format' => 'man',
            'kind' => 'manual-section',
            'manualSection' => true,
        ],
        '.[1-9][a-z]+' => [
            'format' => 'man',
            'kind' => 'manual-section-suffix',
            'manualSection' => true,
        ],
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
        'man' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'Roff manual registry evidence tracks the upstream man reader source semantics; no native PHP man reader is registered yet.',
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
        'mdoc' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'Roff manual registry evidence tracks upstream mdoc as a manual-family input; no native PHP mdoc reader is registered yet.',
        ],
        'native' => [
            'status' => 'partial',
            'implementation' => NativeReader::class,
            'notes' => 'NativeReader parses a large upstream .native fixture set, but complete constructor parity is still audited as partial.',
        ],
        'odt' => [
            'status' => 'partial',
            'implementation' => OdtReader::class,
            'notes' => 'Bounded ODT package reader parses content.xml, meta.xml, optional META-INF/manifest.xml package metadata, text/list styles, headings, paragraphs, ordered and bullet lists, tables, links, styled spans, line breaks, images, and image/resource package references into the shared AST. Full ODT parity remains open.',
        ],
        'opml' => [
            'status' => 'partial',
            'implementation' => OpmlReader::class,
            'notes' => 'Bounded OPML reader maps document title, owner, modified date, nested outlines, link outlines, and Markdown notes into the shared AST, with canonical native semantic parity for the pinned upstream opml-reader fixture. Full option and edge-case parity remains open.',
        ],
        'pptx' => [
            'status' => 'partial',
            'implementation' => PptxReader::class,
            'notes' => 'Bounded PPTX OpenXML package reader maps presentation slide order, title placeholders, text boxes, Wingdings/explicit bullet groups, simple tables, image references, SmartArt hierarchy with placeholder/layout sidecars, slide comments, shape z-order/layout metadata, and internal rich-media references into the shared AST with pinned upstream pptx-reader/basic fixture parity. PPTX writing remains unsupported.',
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
        'man' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'Roff manual registry evidence tracks the upstream man writer source semantics; no native PHP man writer is registered yet.',
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
        'ms' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'Roff manual registry evidence tracks the upstream ms writer and .ms/.roff extension inference; no native PHP ms writer is registered yet.',
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
     * @return list<string>
     */
    public static function roffManualInputFormats(): array
    {
        return self::ROFF_MANUAL_INPUT_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function roffManualOutputFormats(): array
    {
        return self::ROFF_MANUAL_OUTPUT_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function roffInputFormats(): array
    {
        return self::roffManualInputFormats();
    }

    /**
     * @return list<string>
     */
    public static function roffOutputFormats(): array
    {
        return self::roffManualOutputFormats();
    }

    /**
     * @return array<string, string>
     */
    public static function roffManualExtensionInference(): array
    {
        return self::ROFF_MANUAL_EXTENSION_INFERENCE;
    }

    /**
     * @return array<string, string>
     */
    public static function roffExtensionInference(): array
    {
        return self::roffManualExtensionInference();
    }

    public static function inferRoffManualFormatFromExtension(string $extension): ?string
    {
        return self::classifyRoffManualExtension($extension)['format'];
    }

    public static function inferRoffFormatFromExtension(string $extension): ?string
    {
        return self::inferRoffManualFormatFromExtension($extension);
    }

    /**
     * @return array<string, array{format:string, kind:string, manualSection:bool}>
     */
    public static function roffManualExtensionPatternMetadata(): array
    {
        return self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA;
    }

    /**
     * @return array{
     *     format:string|null,
     *     normalizedExtension:string,
     *     pattern:string|null,
     *     kind:string,
     *     manualSection:string|null,
     *     manualSectionNumber:string|null,
     *     manualSectionSuffix:string|null
     * }
     */
    public static function classifyRoffManualExtension(string $extension): array
    {
        $normalized = strtolower(trim($extension));
        if ($normalized === '') {
            return self::roffManualExtensionClassification(null, '', null, null, null);
        }
        if ($normalized[0] !== '.') {
            $normalized = '.' . $normalized;
        }

        if (preg_match('/^\.([1-9])([a-z]*)$/', $normalized, $matches) === 1) {
            $suffix = $matches[2];
            $pattern = $suffix === '' ? '.[1-9]' : '.[1-9][a-z]+';

            return self::roffManualExtensionClassification('man', $normalized, $pattern, $matches[1], $suffix);
        }

        $metadata = self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA[$normalized] ?? null;
        if ($metadata !== null) {
            return self::roffManualExtensionClassification($metadata['format'], $normalized, $normalized, null, null);
        }

        return self::roffManualExtensionClassification(null, $normalized, null, null, null);
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

    /**
     * @return array{
     *     inputFormats:list<string>,
     *     outputFormats:list<string>,
     *     uniqueFormats:list<string>,
     *     directionBuckets:array<string, list<string>>,
     *     extensionInference:array<string, string>,
     *     unsupportedInputs:list<string>,
     *     unsupportedOutputs:list<string>,
     *     partialInputs:list<string>,
     *     partialOutputs:list<string>,
     *     directReaderParityClaimed:bool,
     *     directWriterParityClaimed:bool,
     *     formats:array<string, array{
     *         label:string,
     *         direction:string,
     *         inputStatus:string,
     *         inputImplementation:string,
     *         outputStatus:string,
     *         outputImplementation:string,
     *         extensionInferences:list<string>,
     *         directReaderParityClaimed:bool,
     *         directWriterParityClaimed:bool
     *     }>
     * }
     */
    public static function wikiFormatReviewPacket(): array
    {
        $registry = self::wikiFormatRegistry();
        $summary = self::wikiFormatRegistrySummary();
        $unsupportedInputs = [];
        $unsupportedOutputs = [];
        $partialInputs = [];
        $partialOutputs = [];
        $formats = [];

        foreach ($registry as $format => $entry) {
            $inputStatus = $entry['input']['status'];
            $outputStatus = $entry['output']['status'];

            if ($inputStatus === 'unsupported') {
                $unsupportedInputs[] = $format;
            }
            if ($outputStatus === 'unsupported') {
                $unsupportedOutputs[] = $format;
            }
            if ($inputStatus === 'partial') {
                $partialInputs[] = $format;
            }
            if ($outputStatus === 'partial') {
                $partialOutputs[] = $format;
            }

            $formats[$format] = [
                'label' => $entry['label'],
                'direction' => $entry['direction'],
                'inputStatus' => $inputStatus,
                'inputImplementation' => $entry['input']['implementation'],
                'outputStatus' => $outputStatus,
                'outputImplementation' => $entry['output']['implementation'],
                'extensionInferences' => $entry['extensionInferences'],
                'directReaderParityClaimed' => $entry['directReaderParityClaimed'],
                'directWriterParityClaimed' => $entry['directWriterParityClaimed'],
            ];
        }

        return [
            'inputFormats' => $summary['inputFormats'],
            'outputFormats' => $summary['outputFormats'],
            'uniqueFormats' => $summary['uniqueFormats'],
            'directionBuckets' => $summary['directionBuckets'],
            'extensionInference' => $summary['extensionInference'],
            'unsupportedInputs' => $unsupportedInputs,
            'unsupportedOutputs' => $unsupportedOutputs,
            'partialInputs' => $partialInputs,
            'partialOutputs' => $partialOutputs,
            'directReaderParityClaimed' => $summary['directReaderParityClaimed'],
            'directWriterParityClaimed' => $summary['directWriterParityClaimed'],
            'formats' => $formats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function richPackageUnsupportedFormatSummary(): array
    {
        return RichPackageUnsupportedFormatRegistry::unsupportedFormatSummary();
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function roffManualInputSupport(): array
    {
        return self::onlyFormats(self::phpInputSupport(), self::ROFF_MANUAL_INPUT_FORMATS);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function roffManualOutputSupport(): array
    {
        return self::onlyFormats(self::phpOutputSupport(), self::ROFF_MANUAL_OUTPUT_FORMATS);
    }

    /**
     * @return list<string>
     */
    public static function unsupportedRoffManualInputFormats(): array
    {
        return self::formatsWithStatus(self::roffManualInputSupport(), 'unsupported');
    }

    /**
     * @return list<string>
     */
    public static function unsupportedRoffManualOutputFormats(): array
    {
        return self::formatsWithStatus(self::roffManualOutputSupport(), 'unsupported');
    }

    /**
     * @return array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}>
     */
    public static function roffManualFormatDirections(): array
    {
        return self::formatDirections(
            self::roffManualInputSupport(),
            self::roffManualOutputSupport(),
            self::ROFF_MANUAL_INPUT_FORMATS,
            self::ROFF_MANUAL_OUTPUT_FORMATS
        );
    }

    /**
     * @return array<string, array{format:string, input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}>
     */
    public static function roffManualExtensionDirections(): array
    {
        $formatDirections = self::roffManualFormatDirections();
        $extensionDirections = [];

        foreach (self::ROFF_MANUAL_EXTENSION_INFERENCE as $extension => $format) {
            $direction = $formatDirections[$format] ?? [
                'input' => false,
                'output' => false,
                'direction' => 'unknown',
                'inputStatus' => 'not-applicable',
                'outputStatus' => 'not-applicable',
            ];
            $extensionDirections[$extension] = [
                'format' => $format,
                'input' => $direction['input'],
                'output' => $direction['output'],
                'direction' => $direction['direction'],
                'inputStatus' => $direction['inputStatus'],
                'outputStatus' => $direction['outputStatus'],
            ];
        }

        return $extensionDirections;
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
    public static function roffManualFormatRegistry(): array
    {
        $inputSupport = self::phpInputSupport();
        $outputSupport = self::phpOutputSupport();
        $formats = array_values(array_unique(array_merge(self::ROFF_MANUAL_INPUT_FORMATS, self::ROFF_MANUAL_OUTPUT_FORMATS)));
        $registry = [];

        foreach ($formats as $format) {
            $hasInput = in_array($format, self::ROFF_MANUAL_INPUT_FORMATS, true);
            $hasOutput = in_array($format, self::ROFF_MANUAL_OUTPUT_FORMATS, true);
            $input = $hasInput ? $inputSupport[$format] : self::notApplicableSupport('Not an upstream Pandoc roff/manual reader format.');
            $output = $hasOutput ? $outputSupport[$format] : self::notApplicableSupport('Not an upstream Pandoc roff/manual writer format.');

            $registry[$format] = [
                'label' => self::ROFF_MANUAL_FORMAT_LABELS[$format],
                'direction' => self::formatDirection($hasInput, $hasOutput),
                'input' => $input,
                'output' => $output,
                'extensionInferences' => self::extensionInferencesForFormat($format, self::ROFF_MANUAL_EXTENSION_INFERENCE),
                'directReaderParityClaimed' => $input['status'] === 'complete',
                'directWriterParityClaimed' => $output['status'] === 'complete',
            ];
        }

        return $registry;
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
    public static function roffFormatRegistry(): array
    {
        return self::roffManualFormatRegistry();
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
    public static function roffManualFormatRegistrySummary(): array
    {
        $registry = self::roffManualFormatRegistry();
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
            'inputFormats' => self::ROFF_MANUAL_INPUT_FORMATS,
            'outputFormats' => self::ROFF_MANUAL_OUTPUT_FORMATS,
            'uniqueFormats' => array_keys($registry),
            'directionBuckets' => $directionBuckets,
            'inputStatusBuckets' => $inputStatusBuckets,
            'outputStatusBuckets' => $outputStatusBuckets,
            'extensionInference' => self::ROFF_MANUAL_EXTENSION_INFERENCE,
            'directReaderParityClaimed' => $directReaderParityClaimed,
            'directWriterParityClaimed' => $directWriterParityClaimed,
        ];
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
    public static function roffFormatRegistrySummary(): array
    {
        return self::roffManualFormatRegistrySummary();
    }

    /**
     * @return list<string>
     */
    public static function roffManualFormatsWithExtensionInference(): array
    {
        return array_values(array_unique(array_values(self::ROFF_MANUAL_EXTENSION_INFERENCE)));
    }

    /**
     * @return list<string>
     */
    public static function roffManualFormatsWithoutExtensionInference(): array
    {
        $inferred = array_flip(self::roffManualFormatsWithExtensionInference());
        $formats = array_values(array_unique(array_merge(self::ROFF_MANUAL_INPUT_FORMATS, self::ROFF_MANUAL_OUTPUT_FORMATS)));
        $withoutInference = [];

        foreach ($formats as $format) {
            if (!array_key_exists($format, $inferred)) {
                $withoutInference[] = $format;
            }
        }

        return $withoutInference;
    }

    /**
     * @return array{extension:string, format:string, pattern:string|null, kind:string, manualSection:string|null, manualSectionNumber:string|null, manualSectionSuffix:string|null, input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, unsupportedInput:bool, unsupportedOutput:bool, inputImplementation:string, outputImplementation:string}|null
     */
    public static function roffManualUnsupportedFormatForExtension(string $extension): ?array
    {
        $classification = self::classifyRoffManualExtension($extension);
        $format = $classification['format'];
        if ($format === null) {
            return null;
        }

        $directions = self::roffManualFormatDirections();
        $inputSupport = self::roffManualInputSupport();
        $outputSupport = self::roffManualOutputSupport();
        $direction = $directions[$format];
        $hasInput = $direction['input'];
        $hasOutput = $direction['output'];

        return [
            'extension' => $classification['normalizedExtension'],
            'format' => $format,
            'pattern' => $classification['pattern'],
            'kind' => $classification['kind'],
            'manualSection' => $classification['manualSection'],
            'manualSectionNumber' => $classification['manualSectionNumber'],
            'manualSectionSuffix' => $classification['manualSectionSuffix'],
            'input' => $hasInput,
            'output' => $hasOutput,
            'direction' => $direction['direction'],
            'inputStatus' => $direction['inputStatus'],
            'outputStatus' => $direction['outputStatus'],
            'unsupportedInput' => $direction['inputStatus'] === 'unsupported',
            'unsupportedOutput' => $direction['outputStatus'] === 'unsupported',
            'inputImplementation' => $hasInput ? $inputSupport[$format]['implementation'] : '',
            'outputImplementation' => $hasOutput ? $outputSupport[$format]['implementation'] : '',
        ];
    }

    /**
     * @return array<string, array{extension:string, format:string, pattern:string|null, kind:string, manualSection:string|null, manualSectionNumber:string|null, manualSectionSuffix:string|null, input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, unsupportedInput:bool, unsupportedOutput:bool, inputImplementation:string, outputImplementation:string}>
     */
    public static function roffManualUnsupportedExtensionSurfaces(): array
    {
        $surfaces = [];

        foreach (array_keys(self::ROFF_MANUAL_EXTENSION_INFERENCE) as $extension) {
            $surface = self::roffManualUnsupportedFormatForExtension($extension);
            if ($surface !== null) {
                $surfaces[$extension] = $surface;
            }
        }

        return $surfaces;
    }

    /**
     * @return array{
     *     anyUnsupported:list<string>,
     *     unsupportedBoth:list<string>,
     *     unsupportedInputOnly:list<string>,
     *     unsupportedOutputOnly:list<string>,
     *     noNativeReader:list<string>,
     *     noNativeWriter:list<string>
     * }
     */
    public static function roffManualUnsupportedFormatSummary(): array
    {
        $directions = self::roffManualFormatDirections();
        $anyUnsupported = [];
        $unsupportedBoth = [];
        $unsupportedInputOnly = [];
        $unsupportedOutputOnly = [];

        foreach ($directions as $format => $direction) {
            $inputUnsupported = $direction['inputStatus'] === 'unsupported';
            $outputUnsupported = $direction['outputStatus'] === 'unsupported';

            if ($inputUnsupported || $outputUnsupported) {
                $anyUnsupported[] = $format;
            }
            if ($inputUnsupported && $outputUnsupported) {
                $unsupportedBoth[] = $format;
            }
            if ($direction['direction'] === 'input-only' && $inputUnsupported) {
                $unsupportedInputOnly[] = $format;
            }
            if ($direction['direction'] === 'output-only' && $outputUnsupported) {
                $unsupportedOutputOnly[] = $format;
            }
        }

        return [
            'anyUnsupported' => $anyUnsupported,
            'unsupportedBoth' => $unsupportedBoth,
            'unsupportedInputOnly' => $unsupportedInputOnly,
            'unsupportedOutputOnly' => $unsupportedOutputOnly,
            'noNativeReader' => self::unsupportedRoffManualInputFormats(),
            'noNativeWriter' => self::unsupportedRoffManualOutputFormats(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function richPackageFormatReviewPacket(): array
    {
        return RichPackageUnsupportedFormatRegistry::reviewPacket();
    }

    /**
     * @return array<string, mixed>
     */
    public static function roffManualFormatParitySummary(): array
    {
        $directions = self::roffManualFormatDirections();
        $inputSupport = self::roffManualInputSupport();
        $outputSupport = self::roffManualOutputSupport();
        $inputFormatCount = count(self::ROFF_MANUAL_INPUT_FORMATS);
        $outputFormatCount = count(self::ROFF_MANUAL_OUTPUT_FORMATS);
        $inputOutputFormats = count(self::roffManualFormatsWithDirection('input-output'));
        $inputOnlyFormats = count(self::roffManualFormatsWithDirection('input-only'));
        $outputOnlyFormats = count(self::roffManualFormatsWithDirection('output-only'));
        $unsupportedInputFormats = self::formatsWithStatus($inputSupport, 'unsupported');
        $unsupportedOutputFormats = self::formatsWithStatus($outputSupport, 'unsupported');
        $manualSectionExtensionMappings = 0;
        $registeredInputImplementations = 0;
        $registeredOutputImplementations = 0;
        $directParityClaimed = false;
        $directReaderParitySupported = $unsupportedInputFormats === [];
        $directWriterParitySupported = $unsupportedOutputFormats === [];

        foreach (self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA as $metadata) {
            if ($metadata['manualSection']) {
                ++$manualSectionExtensionMappings;
            }
        }

        foreach ($directions as $format => $direction) {
            $inputImplementation = $direction['input'] ? $inputSupport[$format]['implementation'] : '';
            $outputImplementation = $direction['output'] ? $outputSupport[$format]['implementation'] : '';

            if ($inputImplementation !== '') {
                ++$registeredInputImplementations;
            }
            if ($outputImplementation !== '') {
                ++$registeredOutputImplementations;
            }
            if (
                $inputImplementation !== ''
                || $outputImplementation !== ''
                || !in_array($direction['inputStatus'], ['unsupported', 'not-applicable'], true)
                || !in_array($direction['outputStatus'], ['unsupported', 'not-applicable'], true)
            ) {
                $directParityClaimed = true;
            }
        }

        $extensionInferredFormatCount = count(self::roffManualFormatsWithExtensionInference());
        $nonExtensionInferredFormatCount = count(self::roffManualFormatsWithoutExtensionInference());

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'totalFormats' => count($directions),
            'uniqueFormatCount' => count($directions),
            'inputFormats' => $inputFormatCount,
            'inputFormatCount' => $inputFormatCount,
            'outputFormats' => $outputFormatCount,
            'outputFormatCount' => $outputFormatCount,
            'inputOutputFormats' => $inputOutputFormats,
            'inputOnlyFormats' => $inputOnlyFormats,
            'outputOnlyFormats' => $outputOnlyFormats,
            'directionCounts' => [
                'inputOutput' => $inputOutputFormats,
                'inputOnly' => $inputOnlyFormats,
                'outputOnly' => $outputOnlyFormats,
            ],
            'inputSupportStatusCounts' => self::supportStatusCounts($inputSupport),
            'outputSupportStatusCounts' => self::supportStatusCounts($outputSupport),
            'extensionInferenceMappings' => count(self::ROFF_MANUAL_EXTENSION_INFERENCE),
            'extensionInferredFormats' => $extensionInferredFormatCount,
            'extensionInferredFormatCount' => $extensionInferredFormatCount,
            'nonExtensionInferredFormats' => $nonExtensionInferredFormatCount,
            'nonExtensionInferredFormatCount' => $nonExtensionInferredFormatCount,
            'manualSectionExtensionMappings' => $manualSectionExtensionMappings,
            'literalExtensionMappings' => count(self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA) - $manualSectionExtensionMappings,
            'sectionSuffixExtensionInference' => array_key_exists('.[1-9][a-z]+', self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA),
            'unsupportedExtensionSurfaceMappings' => count(self::roffManualUnsupportedExtensionSurfaces()),
            'unsupportedInputFormats' => count($unsupportedInputFormats),
            'unsupportedInputCount' => count($unsupportedInputFormats),
            'unsupportedOutputFormats' => count($unsupportedOutputFormats),
            'unsupportedOutputCount' => count($unsupportedOutputFormats),
            'registeredInputImplementations' => $registeredInputImplementations,
            'registeredOutputImplementations' => $registeredOutputImplementations,
            'directReaderParitySupported' => $directReaderParitySupported,
            'directWriterParitySupported' => $directWriterParitySupported,
            'directParityClaimed' => $directParityClaimed,
            'directParityStatus' => $directReaderParitySupported && $directWriterParitySupported ? 'supported' : 'unsupported',
            'reviewNote' => 'Pandoc roff/manual formats are tracked for registry review only; no native PHP roff/manual reader or writer is registered.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function roffManualFormatReviewPacket(): array
    {
        $directions = self::roffManualFormatDirections();
        $inputSupport = self::roffManualInputSupport();
        $outputSupport = self::roffManualOutputSupport();
        $extensionsByFormat = [];

        foreach (self::ROFF_MANUAL_EXTENSION_INFERENCE as $extension => $format) {
            $extensionsByFormat[$format][] = $extension;
        }

        $formats = [];
        foreach ($directions as $format => $direction) {
            $hasInput = $direction['input'];
            $hasOutput = $direction['output'];

            $formats[$format] = [
                'input' => $hasInput,
                'output' => $hasOutput,
                'direction' => $direction['direction'],
                'inputStatus' => $direction['inputStatus'],
                'outputStatus' => $direction['outputStatus'],
                'extensionInferred' => array_key_exists($format, $extensionsByFormat),
                'extensions' => $extensionsByFormat[$format] ?? [],
                'inputImplementation' => $hasInput ? $inputSupport[$format]['implementation'] : '',
                'outputImplementation' => $hasOutput ? $outputSupport[$format]['implementation'] : '',
            ];
        }

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamManualUrl' => self::UPSTREAM_MANUAL_URL,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'inputFormats' => self::ROFF_MANUAL_INPUT_FORMATS,
            'outputFormats' => self::ROFF_MANUAL_OUTPUT_FORMATS,
            'directionBuckets' => [
                'inputOutput' => self::roffManualFormatsWithDirection('input-output'),
                'inputOnly' => self::roffManualFormatsWithDirection('input-only'),
                'outputOnly' => self::roffManualFormatsWithDirection('output-only'),
            ],
            'extensionInference' => self::ROFF_MANUAL_EXTENSION_INFERENCE,
            'extensionPatternMetadata' => self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA,
            'extensionInferredFormats' => self::roffManualFormatsWithExtensionInference(),
            'nonExtensionInferredFormats' => self::roffManualFormatsWithoutExtensionInference(),
            'paritySummary' => self::roffManualFormatParitySummary(),
            'unsupportedExtensionSurfaces' => self::roffManualUnsupportedExtensionSurfaces(),
            'unsupportedInputFormats' => self::formatsWithStatus($inputSupport, 'unsupported'),
            'unsupportedOutputFormats' => self::formatsWithStatus($outputSupport, 'unsupported'),
            'unsupportedFormatSummary' => self::roffManualUnsupportedFormatSummary(),
            'formats' => $formats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function roffFormatReviewPacket(): array
    {
        return self::roffManualFormatReviewPacket();
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
     * @param array<string, array{status:string, implementation:string, notes:string}> $support
     * @param list<string> $formats
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    private static function onlyFormats(array $support, array $formats): array
    {
        $filtered = [];
        foreach ($formats as $format) {
            $filtered[$format] = $support[$format];
        }

        return $filtered;
    }

    /**
     * @param array<string, array{status:string, implementation:string, notes:string}> $support
     * @return array<string, int>
     */
    private static function supportStatusCounts(array $support): array
    {
        $counts = [];
        foreach ($support as $entry) {
            $status = $entry['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, array{status:string, implementation:string, notes:string}> $inputSupport
     * @param array<string, array{status:string, implementation:string, notes:string}> $outputSupport
     * @param list<string> $inputFormats
     * @param list<string> $outputFormats
     * @return array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}>
     */
    private static function formatDirections(array $inputSupport, array $outputSupport, array $inputFormats, array $outputFormats): array
    {
        $formats = array_values(array_unique(array_merge($inputFormats, $outputFormats)));
        $directions = [];

        foreach ($formats as $format) {
            $hasInput = array_key_exists($format, $inputSupport);
            $hasOutput = array_key_exists($format, $outputSupport);
            $directions[$format] = [
                'input' => $hasInput,
                'output' => $hasOutput,
                'direction' => self::formatDirection($hasInput, $hasOutput),
                'inputStatus' => $hasInput ? $inputSupport[$format]['status'] : 'not-applicable',
                'outputStatus' => $hasOutput ? $outputSupport[$format]['status'] : 'not-applicable',
            ];
        }

        return $directions;
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
    private static function extensionInferencesForFormat(string $format, ?array $extensionInference = null): array
    {
        $extensionInference ??= self::WIKI_EXTENSION_INFERENCE;
        $extensions = [];
        foreach ($extensionInference as $extension => $inferredFormat) {
            if ($inferredFormat === $format) {
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }

    /**
     * @return array{
     *     format:string|null,
     *     normalizedExtension:string,
     *     pattern:string|null,
     *     kind:string,
     *     manualSection:string|null,
     *     manualSectionNumber:string|null,
     *     manualSectionSuffix:string|null
     * }
     */
    private static function roffManualExtensionClassification(
        ?string $format,
        string $normalizedExtension,
        ?string $pattern,
        ?string $manualSectionNumber,
        ?string $manualSectionSuffix
    ): array {
        $metadata = $pattern === null ? null : self::ROFF_MANUAL_EXTENSION_PATTERN_METADATA[$pattern];
        $manualSection = null;
        if ($manualSectionNumber !== null) {
            $manualSection = $manualSectionNumber . ($manualSectionSuffix ?? '');
        }

        return [
            'format' => $format,
            'normalizedExtension' => $normalizedExtension,
            'pattern' => $pattern,
            'kind' => $metadata['kind'] ?? 'unknown',
            'manualSection' => $manualSection,
            'manualSectionNumber' => $manualSectionNumber,
            'manualSectionSuffix' => $manualSectionSuffix,
        ];
    }

    /**
     * @return list<string>
     */
    private static function roffManualFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::roffManualFormatDirections(), $direction);
    }

    /**
     * @param array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}> $directions
     * @return list<string>
     */
    private static function formatsWithDirection(array $directions, string $direction): array
    {
        $formats = [];
        foreach ($directions as $format => $entry) {
            if ($entry['direction'] === $direction) {
                $formats[] = $format;
            }
        }

        return $formats;
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
