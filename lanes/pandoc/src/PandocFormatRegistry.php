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
        'csv' => [
            'status' => 'partial',
            'implementation' => DelimitedTextReader::class,
            'notes' => 'Delimited text reader maps CSV rows into the shared table AST with quote, multiline field, row repair, control-character, and provenance diagnostics. Full Pandoc CSV option parity remains open.',
        ],
        'tsv' => [
            'status' => 'partial',
            'implementation' => DelimitedTextReader::class,
            'notes' => 'Delimited text reader maps TSV rows into the shared table AST with trailing empty field, ragged row, repair, control-character, and provenance diagnostics. Full Pandoc TSV option parity remains open.',
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
            'notes' => 'Bounded EPUB package reader resolves the OPF rootfile, extracts metadata, follows XHTML spine items, rewrites package-relative href/src resources, records image/resource references, parses EPUB3 nav and NCX table-of-contents resources into metadata, and maps spine content through the shared HTML-capable reader path. Full EPUB parity remains open.',
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
        'ipynb' => [
            'status' => 'partial',
            'implementation' => IpynbReader::class,
            'notes' => 'Bounded IPYNB reader maps markdown, code, and raw cells into the shared AST with notebook, metadata, attachment, source-shape, execution, and output diagnostics without executing notebooks or exposing embedded output bytes. Native IPYNB writer parity remains open.',
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
        'rtf' => [
            'status' => 'partial',
            'implementation' => RtfReader::class,
            'notes' => 'Bounded RTF reader maps paragraphs, escaped characters, unicode fallbacks, tabs, and core inline styles into the shared AST. Full RTF control-word, destination, table, image, and metadata parity remains open.',
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
