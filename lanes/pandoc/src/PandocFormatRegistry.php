<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocFormatRegistry
{
    public const UPSTREAM_MANUAL_DATE = '2026-06-03';
    public const UPSTREAM_MANUAL_URL = 'https://pandoc.org/demo/example2.html';
    public const UPSTREAM_SOURCE_COMMIT = UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT;

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
        'creole' => 'Creole',
        'dokuwiki' => 'DokuWiki',
        'jira' => 'Jira wiki',
        'mediawiki' => 'MediaWiki',
        'tikiwiki' => 'TikiWiki',
        'twiki' => 'TWiki',
        'vimwiki' => 'Vimwiki',
        'xwiki' => 'XWiki',
        'zimwiki' => 'ZimWiki',
    ];

    /** @var array<string, string> */
    private const WIKI_EXTENSION_INFERENCE = [
        '.dokuwiki' => 'dokuwiki',
        '.wiki' => 'mediawiki',
    ];

    /** @var array<string, list<string>> */
    private const WIKI_READER_FIXTURE_SOURCES = [
        'creole' => [
            'test/creole-reader.txt',
        ],
        'dokuwiki' => [
            'test/dokuwiki_inline_formatting.dokuwiki',
            'test/dokuwiki_external_images.dokuwiki',
            'test/dokuwiki_multiblock_table.dokuwiki',
        ],
        'jira' => [
            'test/jira-reader.jira',
        ],
        'mediawiki' => [
            'test/mediawiki-reader.wiki',
        ],
        'tikiwiki' => [
            'test/tikiwiki-reader.tikiwiki',
        ],
        'twiki' => [
            'test/twiki-reader.twiki',
        ],
        'vimwiki' => [
            'test/vimwiki-reader.wiki',
        ],
    ];

    /** @var array<string, list<string>> */
    private const WIKI_WRITER_FIXTURE_SOURCES = [
        'dokuwiki' => [
            'test/tables.dokuwiki',
            'test/writer.dokuwiki',
        ],
        'jira' => [
            'test/tables.jira',
            'test/writer.jira',
        ],
        'mediawiki' => [
            'test/tables.mediawiki',
            'test/tables/*.mediawiki',
            'test/writer.mediawiki',
        ],
        'xwiki' => [
            'test/tables.xwiki',
            'test/writer.xwiki',
        ],
        'zimwiki' => [
            'test/tables.zimwiki',
            'test/writer.zimwiki',
        ],
    ];

    /**
     * @var array<string, array{module:string, function:string, registry:string}>
     */
    private const WIKI_INPUT_SOURCE_PROVENANCE = [
        'creole' => [
            'module' => 'Text.Pandoc.Readers.Creole',
            'function' => 'readCreole',
            'registry' => '("creole"       , TextReader readCreole)',
        ],
        'dokuwiki' => [
            'module' => 'Text.Pandoc.Readers.DokuWiki',
            'function' => 'readDokuWiki',
            'registry' => '("dokuwiki"     , TextReader readDokuWiki)',
        ],
        'jira' => [
            'module' => 'Text.Pandoc.Readers.Jira',
            'function' => 'readJira',
            'registry' => '("jira"         , TextReader readJira)',
        ],
        'mediawiki' => [
            'module' => 'Text.Pandoc.Readers.MediaWiki',
            'function' => 'readMediaWiki',
            'registry' => '("mediawiki"    , TextReader readMediaWiki)',
        ],
        'tikiwiki' => [
            'module' => 'Text.Pandoc.Readers.TikiWiki',
            'function' => 'readTikiWiki',
            'registry' => '("tikiwiki"     , TextReader readTikiWiki)',
        ],
        'twiki' => [
            'module' => 'Text.Pandoc.Readers.TWiki',
            'function' => 'readTWiki',
            'registry' => '("twiki"        , TextReader readTWiki)',
        ],
        'vimwiki' => [
            'module' => 'Text.Pandoc.Readers.Vimwiki',
            'function' => 'readVimwiki',
            'registry' => '("vimwiki"      , TextReader readVimwiki)',
        ],
    ];

    /**
     * @var array<string, array{module:string, function:string, registry:string}>
     */
    private const WIKI_OUTPUT_SOURCE_PROVENANCE = [
        'dokuwiki' => [
            'module' => 'Text.Pandoc.Writers.DokuWiki',
            'function' => 'writeDokuWiki',
            'registry' => '("dokuwiki"     , TextWriter writeDokuWiki)',
        ],
        'jira' => [
            'module' => 'Text.Pandoc.Writers.Jira',
            'function' => 'writeJira',
            'registry' => '("jira"         , TextWriter writeJira)',
        ],
        'mediawiki' => [
            'module' => 'Text.Pandoc.Writers.MediaWiki',
            'function' => 'writeMediaWiki',
            'registry' => '("mediawiki"    , TextWriter writeMediaWiki)',
        ],
        'xwiki' => [
            'module' => 'Text.Pandoc.Writers.XWiki',
            'function' => 'writeXWiki',
            'registry' => '("xwiki"        , TextWriter writeXWiki)',
        ],
        'zimwiki' => [
            'module' => 'Text.Pandoc.Writers.ZimWiki',
            'function' => 'writeZimWiki',
            'registry' => '("zimwiki"      , TextWriter writeZimWiki)',
        ],
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

    /** @var list<string> */
    private const TEXT_MARKUP_READER_FORMATS = [
        'asciidoc',
        'creole',
        'djot',
        'dokuwiki',
        'fb2',
        'haddock',
        'jira',
        'man',
        'mdoc',
        'mediawiki',
        'muse',
        'opml',
        'org',
        'pod',
        'rst',
        't2t',
        'textile',
        'tikiwiki',
        'twiki',
        'vimwiki',
    ];

    /** @var array<string, string> */
    private const TEXT_MARKUP_FORMAT_FAMILIES = [
        'asciidoc' => 'lightweight-markup',
        'creole' => 'wiki',
        'djot' => 'lightweight-markup',
        'dokuwiki' => 'wiki',
        'fb2' => 'lightweight-markup',
        'haddock' => 'lightweight-markup',
        'jira' => 'wiki',
        'man' => 'roff-manual',
        'mdoc' => 'roff-manual',
        'mediawiki' => 'wiki',
        'muse' => 'lightweight-markup',
        'opml' => 'lightweight-markup',
        'org' => 'lightweight-markup',
        'pod' => 'lightweight-markup',
        'rst' => 'lightweight-markup',
        't2t' => 'lightweight-markup',
        'textile' => 'lightweight-markup',
        'tikiwiki' => 'wiki',
        'twiki' => 'wiki',
        'vimwiki' => 'wiki',
    ];

    /** @var array<string, string> */
    private const TEXT_MARKUP_UNSUPPORTED_REASON_CODES = [
        'lightweight-markup' => 'text-markup-reader-not-ported',
        'wiki' => 'wiki-reader-not-ported',
        'roff-manual' => 'roff-manual-reader-not-ported',
    ];

    /** @var array<string, string> */
    private const TEXT_MARKUP_UNSUPPORTED_REASONS = [
        'lightweight-markup' => 'Upstream lightweight text reader token is inventoried, but no native PHP reader is registered for this format.',
        'wiki' => 'Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format.',
        'roff-manual' => 'Upstream roff/manual reader coverage is inventoried, but no native PHP roff/manual reader is registered for this format.',
    ];

    /**
     * @var array<string, array{family:string, status:string, readerParityStatus:string, reviewPolicy:string, externalToolFree:bool, message:string}>
     */
    private const TEXT_MARKUP_READER_UNSUPPORTED_REASON_TAXONOMY = [
        'roff-manual-native-reader-not-implemented' => [
            'family' => 'roff-manual',
            'status' => 'unsupported',
            'readerParityStatus' => 'not-implemented',
            'reviewPolicy' => 'registry-diagnostics-only',
            'externalToolFree' => true,
            'message' => 'Pandoc roff/manual input is registered upstream, but no native PHP roff/manual reader parity is implemented.',
        ],
    ];

    /** @var list<string> */
    private const RICH_PACKAGE_INPUT_FORMATS = [
        'docx',
        'epub',
        'ipynb',
        'odt',
        'pptx',
        'xlsx',
    ];

    /** @var list<string> */
    private const RICH_PACKAGE_OUTPUT_FORMATS = [
        'chunkedhtml',
        'docx',
        'epub',
        'epub2',
        'epub3',
        'icml',
        'ipynb',
        'odt',
        'opendocument',
        'pdf',
        'pptx',
    ];

    /** @var array<string, string> */
    private const RICH_PACKAGE_EXTENSION_INFERENCE = [
        '.docx' => 'docx',
        '.epub' => 'epub',
        '.fodt' => 'opendocument',
        '.icml' => 'icml',
        '.ipynb' => 'ipynb',
        '.odt' => 'odt',
        '.pdf' => 'pdf',
        '.pptx' => 'pptx',
        '.xlsx' => 'xlsx',
    ];

    /** @var array<string, array{primaryFormat:string, formats:list<string>, kind:string}> */
    private const RICH_PACKAGE_EXTENSION_METADATA = [
        '.docx' => [
            'primaryFormat' => 'docx',
            'formats' => ['docx'],
            'kind' => 'office-open-xml-wordprocessing-package',
        ],
        '.epub' => [
            'primaryFormat' => 'epub',
            'formats' => ['epub', 'epub2', 'epub3'],
            'kind' => 'epub-publication-package',
        ],
        '.fodt' => [
            'primaryFormat' => 'opendocument',
            'formats' => ['opendocument'],
            'kind' => 'flat-open-document-text',
        ],
        '.icml' => [
            'primaryFormat' => 'icml',
            'formats' => ['icml'],
            'kind' => 'indesign-markup-file',
        ],
        '.ipynb' => [
            'primaryFormat' => 'ipynb',
            'formats' => ['ipynb'],
            'kind' => 'notebook-json-package',
        ],
        '.odt' => [
            'primaryFormat' => 'odt',
            'formats' => ['odt'],
            'kind' => 'open-document-text-package',
        ],
        '.pdf' => [
            'primaryFormat' => 'pdf',
            'formats' => ['pdf'],
            'kind' => 'pdf-rendered-artifact',
        ],
        '.pptx' => [
            'primaryFormat' => 'pptx',
            'formats' => ['pptx'],
            'kind' => 'office-open-xml-presentation-package',
        ],
        '.xlsx' => [
            'primaryFormat' => 'xlsx',
            'formats' => ['xlsx'],
            'kind' => 'office-open-xml-spreadsheet-package',
        ],
    ];

    /** @var list<string> */
    private const XML_JATS_BITS_INPUT_FORMATS = [
        'xml',
        'jats',
        'bits',
    ];

    /**
     * @var array<string, array{diagnosticImplementation:string, reviewMethod:string, reviewPolicy:string, boundedDiagnostics:list<string>, remainingReaderGaps:list<string>}>
     */
    private const XML_JATS_BITS_DIAGNOSTIC_SURFACES = [
        'xml' => [
            'diagnosticImplementation' => XmlHtmlDom::class,
            'reviewMethod' => 'loadXmlDocument',
            'reviewPolicy' => 'safe-xml-dom-primitives-only',
            'boundedDiagnostics' => [
                'safe XML loading with external entity and processing-instruction rejection',
                'namespace-aware element and attribute queries for package-reader handoff',
            ],
            'remainingReaderGaps' => [
                'full Pandoc XML input mapping into the shared AST',
                'reader-level body, block, inline, table, figure, citation, and metadata parity',
            ],
        ],
        'jats' => [
            'diagnosticImplementation' => XmlHtmlDom::class,
            'reviewMethod' => 'summarizeJatsFrontMatter',
            'reviewPolicy' => 'jats-bits-front-matter-review-only',
            'boundedDiagnostics' => [
                'article front-matter identifiers, titles, abstracts, keywords, contributors, dates, and cross-reference targets',
                'bounded body, reference, figure, and table-wrap inventories',
            ],
            'remainingReaderGaps' => [
                'full JATS body and back-matter mapping into the shared AST',
                'tables, figures, references, and citation-reader parity',
            ],
        ],
        'bits' => [
            'diagnosticImplementation' => XmlHtmlDom::class,
            'reviewMethod' => 'summarizeJatsFrontMatter',
            'reviewPolicy' => 'jats-bits-front-matter-review-only',
            'boundedDiagnostics' => [
                'book and book-part metadata identifiers, titles, contributors, dates, and part counts',
                'bounded body, reference, figure, and table-wrap inventories',
            ],
            'remainingReaderGaps' => [
                'full BITS book body and book-part mapping into the shared AST',
                'tables, figures, references, and citation-reader parity',
            ],
        ],
    ];

    /** @var array<string, string> */
    private const INPUT_ALIASES = [
        'bits' => 'jats',
        'markdown_github' => 'gfm',
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
        'bits' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'BITS front-matter review packets are available through XmlHtmlDom diagnostics, but no full native PHP BITS direct reader is registered yet.',
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
        'csv' => [
            'status' => 'partial',
            'implementation' => DelimitedTextReader::class,
            'notes' => 'Bounded native PHP CSV reader maps simple delimited text into the shared table AST with geometry review packets; full Pandoc CSV reader parity remains open.',
        ],
        'docbook' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'Bounded DocBook table command fixtures are mapped; full DocBook XML reader parity remains open.',
        ],
        'docx' => [
            'status' => 'partial',
            'implementation' => DocxReader::class,
            'notes' => 'DOCX package import slices map WordprocessingML into the shared AST; full DOCX reader parity remains open.',
        ],
        'epub' => [
            'status' => 'partial',
            'implementation' => EpubReader::class,
            'notes' => 'EPUB3 package handoff slices expose OPF/spine/nav content; full EPUB reader parity remains open.',
        ],
        'gfm' => [
            'status' => 'partial',
            'implementation' => MarkdownReader::class,
            'notes' => 'GitHub-Flavored Markdown behavior is partially mapped through MarkdownReader and MarkdownWriter tests.',
        ],
        'html' => [
            'status' => 'partial',
            'implementation' => Html5Dom::class,
            'notes' => 'HTML reader slices cover many DOM and raw HTML branches; full HTML5 tree construction remains open.',
        ],
        'ipynb' => [
            'status' => 'partial',
            'implementation' => IpynbReader::class,
            'notes' => 'Bounded native PHP notebook reader maps Markdown/code/raw cells into reviewable AST blocks while preserving execution/output metadata; full Jupyter notebook parity remains open.',
        ],
        'jats' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'JATS front-matter review packets are available through XmlHtmlDom diagnostics, but no full native PHP JATS direct reader is registered yet.',
        ],
        'json' => [
            'status' => 'partial',
            'implementation' => PandocJsonReader::class,
            'notes' => 'Reads the current Pandoc JSON AST encoding for the constructors covered by the shared PHP AST.',
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
            'notes' => 'NativeReader parses a large upstream .native fixture set, but complete constructor parity remains audited as partial.',
        ],
        'odt' => [
            'status' => 'partial',
            'implementation' => OdtReader::class,
            'notes' => 'ODT package import slices map OpenDocument text content into the shared AST; full ODT reader parity remains open.',
        ],
        'rtf' => [
            'status' => 'partial',
            'implementation' => RtfReader::class,
            'notes' => 'RTF reader slices map bounded control-word and text cases into the shared AST; full RTF reader parity remains open.',
        ],
        'tsv' => [
            'status' => 'partial',
            'implementation' => DelimitedTextReader::class,
            'notes' => 'Bounded native PHP TSV reader maps tab-delimited text into the shared table AST with geometry review packets; full Pandoc TSV reader parity remains open.',
        ],
        'xml' => [
            'status' => 'unsupported',
            'implementation' => '',
            'notes' => 'Safe XML DOM primitives exist for bounded review diagnostics, but no full native PHP XML direct reader is registered yet.',
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
            'implementation' => WordPressBlockWriter::class,
            'notes' => 'HTML output is covered by WordPress block handoff and DOM serialization slices; standalone Pandoc HTML writer parity remains open.',
        ],
        'html5' => [
            'status' => 'partial',
            'implementation' => WordPressBlockWriter::class,
            'notes' => 'HTML5 output currently shares the native WordPress/HTML handoff path.',
        ],
        'json' => [
            'status' => 'partial',
            'implementation' => PandocJsonWriter::class,
            'notes' => 'Writes the current Pandoc JSON AST encoding for the constructors covered by the shared PHP AST.',
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
        'plain' => [
            'status' => 'partial',
            'implementation' => PlainWriter::class,
            'notes' => 'PlainWriter maps core shared AST text extraction with bounded display-column wrapping diagnostics; full plain writer parity remains open.',
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

    /**
     * @return string|null
     */
    public static function inferWikiFormatFromExtension(string $extension): ?string
    {
        $normalized = strtolower($extension);
        if ($normalized === '') {
            return null;
        }
        if ($normalized[0] !== '.') {
            $normalized = '.' . $normalized;
        }

        return self::WIKI_EXTENSION_INFERENCE[$normalized] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function wikiFormatsWithExtensionInference(): array
    {
        return array_values(array_unique(array_values(self::WIKI_EXTENSION_INFERENCE)));
    }

    /**
     * @return list<string>
     */
    public static function wikiFormatsWithoutExtensionInference(): array
    {
        $inferred = array_flip(self::wikiFormatsWithExtensionInference());
        $formats = array_values(array_unique(array_merge(self::WIKI_INPUT_FORMATS, self::WIKI_OUTPUT_FORMATS)));
        $withoutInference = [];

        foreach ($formats as $format) {
            if (!array_key_exists($format, $inferred)) {
                $withoutInference[] = $format;
            }
        }

        return $withoutInference;
    }

    /**
     * @return array<string, string>
     */
    public static function wikiTemplateResources(): array
    {
        $dataFiles = array_flip(UpstreamRunnerDependencyAudit::expectedPackageDataFiles()['pandoc.cabal'] ?? []);
        $resources = [];

        foreach (self::WIKI_OUTPUT_FORMATS as $format) {
            $resource = 'data/templates/default.' . $format;
            if (array_key_exists($resource, $dataFiles)) {
                $resources[$format] = $resource;
            }
        }

        return $resources;
    }

    /**
     * @return array<string, string>
     */
    public static function wikiOutputTemplateResources(): array
    {
        return self::wikiTemplateResources();
    }

    public static function templateResourceForWikiOutputFormat(string $format): ?string
    {
        $baseFormat = self::wikiBaseFormat($format);
        $templates = self::wikiTemplateResources();

        return $templates[$baseFormat] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function wikiOutputFormatsWithDefaultTemplates(): array
    {
        $templates = self::wikiTemplateResources();

        return array_values(array_filter(
            self::WIKI_OUTPUT_FORMATS,
            static fn (string $format): bool => array_key_exists($format, $templates)
        ));
    }

    /**
     * @return list<string>
     */
    public static function wikiOutputFormatsWithoutDefaultTemplates(): array
    {
        $templated = array_flip(self::wikiOutputFormatsWithDefaultTemplates());

        return array_values(array_filter(
            self::WIKI_OUTPUT_FORMATS,
            static fn (string $format): bool => !array_key_exists($format, $templated)
        ));
    }

    /**
     * @return array<string, array{reader:list<string>, writer:list<string>}>
     */
    public static function wikiFixtureSources(): array
    {
        $sourceFiles = array_flip(UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc.cabal'] ?? []);
        $formats = array_values(array_unique(array_merge(self::WIKI_INPUT_FORMATS, self::WIKI_OUTPUT_FORMATS)));
        $sources = [];

        foreach ($formats as $format) {
            $sources[$format] = [
                'reader' => self::auditedFixtureSources(self::WIKI_READER_FIXTURE_SOURCES[$format] ?? [], $sourceFiles),
                'writer' => self::auditedFixtureSources(self::WIKI_WRITER_FIXTURE_SOURCES[$format] ?? [], $sourceFiles),
            ];
        }

        return $sources;
    }

    /**
     * @return array<string, array{format:string, label:string, input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, readerFixturePaths:list<string>, writerFixturePaths:list<string>, upstreamFixturePaths:list<string>, upstreamTemplatePath:?string}>
     */
    public static function wikiFormatRegistryMetadata(): array
    {
        $fixtures = self::wikiFixtureSources();
        $templates = self::wikiTemplateResources();
        $metadata = [];

        foreach (self::wikiFormatDirections() as $format => $direction) {
            $formatFixtures = $fixtures[$format] ?? ['reader' => [], 'writer' => []];
            $readerFixtures = $direction['input'] ? $formatFixtures['reader'] : [];
            $writerFixtures = $direction['output'] ? $formatFixtures['writer'] : [];

            $metadata[$format] = [
                'format' => $format,
                'label' => self::WIKI_FORMAT_LABELS[$format] ?? $format,
                'input' => $direction['input'],
                'output' => $direction['output'],
                'direction' => $direction['direction'],
                'inputStatus' => $direction['inputStatus'],
                'outputStatus' => $direction['outputStatus'],
                'readerFixturePaths' => $readerFixtures,
                'writerFixturePaths' => $writerFixtures,
                'upstreamFixturePaths' => array_values(array_merge($readerFixtures, $writerFixtures)),
                'upstreamTemplatePath' => $templates[$format] ?? null,
            ];
        }

        return $metadata;
    }

    /**
     * @return array<string, array{module:string, function:string, registry:string}>
     */
    public static function wikiInputSourceProvenance(): array
    {
        return self::WIKI_INPUT_SOURCE_PROVENANCE;
    }

    /**
     * @return array<string, array{module:string, function:string, registry:string}>
     */
    public static function wikiOutputSourceProvenance(): array
    {
        return self::WIKI_OUTPUT_SOURCE_PROVENANCE;
    }

    /**
     * @return array<string, array{input:array{module:string, function:string, registry:string}|null, output:array{module:string, function:string, registry:string}|null}>
     */
    public static function wikiFormatSourceProvenance(): array
    {
        $formats = array_values(array_unique(array_merge(self::WIKI_INPUT_FORMATS, self::WIKI_OUTPUT_FORMATS)));
        $provenance = [];

        foreach ($formats as $format) {
            $provenance[$format] = [
                'input' => self::WIKI_INPUT_SOURCE_PROVENANCE[$format] ?? null,
                'output' => self::WIKI_OUTPUT_SOURCE_PROVENANCE[$format] ?? null,
            ];
        }

        return $provenance;
    }

    /**
     * @return array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}>
     */
    public static function wikiFormatDirections(): array
    {
        return self::formatDirections(
            self::wikiInputSupport(),
            self::wikiOutputSupport(),
            self::WIKI_INPUT_FORMATS,
            self::WIKI_OUTPUT_FORMATS
        );
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     inputFormats:list<string>,
     *     outputFormats:list<string>,
     *     directionBuckets:array{inputOutput:list<string>, inputOnly:list<string>, outputOnly:list<string>},
     *     extensionInference:array<string, string>,
     *     extensionInferredFormats:list<string>,
     *     nonExtensionInferredFormats:list<string>,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedOutputFormats:list<string>,
     *     unsupportedFormatSummary:array{
     *         anyUnsupported:list<string>,
     *         unsupportedBoth:list<string>,
     *         unsupportedInputOnly:list<string>,
     *         unsupportedOutputOnly:list<string>,
     *         noNativeReader:list<string>,
     *         noNativeWriter:list<string>
     *     },
     *     formats:array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, extensionInferred:bool, extensions:list<string>, inputImplementation:string, outputImplementation:string}>
     * }
     */
    public static function wikiFormatReviewPacket(): array
    {
        $directions = self::wikiFormatDirections();
        $inputSupport = self::wikiInputSupport();
        $outputSupport = self::wikiOutputSupport();
        $extensionsByFormat = [];

        foreach (self::WIKI_EXTENSION_INFERENCE as $extension => $format) {
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
            'inputFormats' => self::WIKI_INPUT_FORMATS,
            'outputFormats' => self::WIKI_OUTPUT_FORMATS,
            'directionBuckets' => [
                'inputOutput' => self::wikiBidirectionalFormats(),
                'inputOnly' => self::wikiInputOnlyFormats(),
                'outputOnly' => self::wikiOutputOnlyFormats(),
            ],
            'extensionInference' => self::WIKI_EXTENSION_INFERENCE,
            'extensionInferredFormats' => self::wikiFormatsWithExtensionInference(),
            'nonExtensionInferredFormats' => self::wikiFormatsWithoutExtensionInference(),
            'unsupportedInputFormats' => self::formatsWithStatus($inputSupport, 'unsupported'),
            'unsupportedOutputFormats' => self::formatsWithStatus($outputSupport, 'unsupported'),
            'unsupportedFormatSummary' => self::wikiUnsupportedFormatSummary(),
            'formats' => $formats,
        ];
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     templateResources:array<string, string>,
     *     fixtureSources:array<string, array{reader:list<string>, writer:list<string>}>,
     *     formats:array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, readerFixtures:list<string>, writerFixtures:list<string>, templateResource:string, hasTemplateResource:bool, inputImplementation:string, outputImplementation:string}>
     * }
     */
    public static function wikiFormatEvidencePacket(): array
    {
        $directions = self::wikiFormatDirections();
        $inputSupport = self::wikiInputSupport();
        $outputSupport = self::wikiOutputSupport();
        $templates = self::wikiTemplateResources();
        $fixtureSources = self::wikiFixtureSources();
        $formats = [];

        foreach ($directions as $format => $direction) {
            $hasInput = $direction['input'];
            $hasOutput = $direction['output'];
            $formatFixtures = $fixtureSources[$format] ?? ['reader' => [], 'writer' => []];

            $formats[$format] = [
                'input' => $hasInput,
                'output' => $hasOutput,
                'direction' => $direction['direction'],
                'inputStatus' => $direction['inputStatus'],
                'outputStatus' => $direction['outputStatus'],
                'readerFixtures' => $hasInput ? $formatFixtures['reader'] : [],
                'writerFixtures' => $hasOutput ? $formatFixtures['writer'] : [],
                'templateResource' => $templates[$format] ?? '',
                'hasTemplateResource' => array_key_exists($format, $templates),
                'inputImplementation' => $hasInput ? $inputSupport[$format]['implementation'] : '',
                'outputImplementation' => $hasOutput ? $outputSupport[$format]['implementation'] : '',
            ];
        }

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamManualUrl' => self::UPSTREAM_MANUAL_URL,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'templateResources' => $templates,
            'fixtureSources' => $fixtureSources,
            'formats' => $formats,
        ];
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamSourceCommit:string,
     *     totalFormats:int,
     *     uniqueFormatCount:int,
     *     inputFormats:int,
     *     inputFormatCount:int,
     *     outputFormats:int,
     *     outputFormatCount:int,
     *     inputOutputFormats:int,
     *     inputOnlyFormats:int,
     *     outputOnlyFormats:int,
     *     directionCounts:array{inputOutput:int, inputOnly:int, outputOnly:int},
     *     inputSupportStatusCounts:array<string, int>,
     *     outputSupportStatusCounts:array<string, int>,
     *     extensionInferenceMappings:int,
     *     extensionInferredFormats:int,
     *     extensionInferredFormatCount:int,
     *     nonExtensionInferredFormats:int,
     *     nonExtensionInferredFormatCount:int,
     *     unsupportedInputFormats:int,
     *     unsupportedInputCount:int,
     *     unsupportedOutputFormats:int,
     *     unsupportedOutputCount:int,
     *     registeredInputImplementations:int,
     *     registeredOutputImplementations:int,
     *     directReaderParitySupported:bool,
     *     directWriterParitySupported:bool,
     *     directParityClaimed:bool,
     *     directParityStatus:string,
     *     reviewNote:string
     * }
     */
    public static function wikiFormatParitySummary(): array
    {
        $directions = self::wikiFormatDirections();
        $inputSupport = self::wikiInputSupport();
        $outputSupport = self::wikiOutputSupport();
        $inputFormatCount = count(self::WIKI_INPUT_FORMATS);
        $outputFormatCount = count(self::WIKI_OUTPUT_FORMATS);
        $inputOutputFormats = count(self::wikiBidirectionalFormats());
        $inputOnlyFormats = count(self::wikiInputOnlyFormats());
        $outputOnlyFormats = count(self::wikiOutputOnlyFormats());
        $extensionInferredFormatCount = count(self::wikiFormatsWithExtensionInference());
        $nonExtensionInferredFormatCount = count(self::wikiFormatsWithoutExtensionInference());
        $unsupportedInputFormats = self::formatsWithStatus($inputSupport, 'unsupported');
        $unsupportedOutputFormats = self::formatsWithStatus($outputSupport, 'unsupported');
        $registeredInputImplementations = 0;
        $registeredOutputImplementations = 0;
        $directParityClaimed = false;
        $directReaderParitySupported = $unsupportedInputFormats === [];
        $directWriterParitySupported = $unsupportedOutputFormats === [];

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
            'extensionInferenceMappings' => count(self::WIKI_EXTENSION_INFERENCE),
            'extensionInferredFormats' => $extensionInferredFormatCount,
            'extensionInferredFormatCount' => $extensionInferredFormatCount,
            'nonExtensionInferredFormats' => $nonExtensionInferredFormatCount,
            'nonExtensionInferredFormatCount' => $nonExtensionInferredFormatCount,
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
            'reviewNote' => 'Pandoc wiki formats are tracked for registry review only; no native PHP wiki reader or writer is registered.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function wikiBidirectionalFormats(): array
    {
        return self::wikiFormatsWithDirection('input-output');
    }

    /**
     * @return list<string>
     */
    public static function wikiInputOnlyFormats(): array
    {
        return self::wikiFormatsWithDirection('input-only');
    }

    /**
     * @return list<string>
     */
    public static function wikiOutputOnlyFormats(): array
    {
        return self::wikiFormatsWithDirection('output-only');
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
    public static function wikiUnsupportedFormatSummary(): array
    {
        $directions = self::wikiFormatDirections();
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
            'noNativeReader' => self::unsupportedWikiInputFormats(),
            'noNativeWriter' => self::unsupportedWikiOutputFormats(),
        ];
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
     * @return array<string, string>
     */
    public static function roffManualExtensionInference(): array
    {
        return self::ROFF_MANUAL_EXTENSION_INFERENCE;
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
     * @return string|null
     */
    public static function inferRoffManualFormatFromExtension(string $extension): ?string
    {
        return self::classifyRoffManualExtension($extension)['format'];
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
        $normalized = strtolower($extension);
        if ($normalized === '') {
            return self::roffManualExtensionClassification(null, '', null, null, null);
        }
        if ($normalized[0] !== '.') {
            $normalized = '.' . $normalized;
        }

        if (preg_match('/^\.([1-9])([a-z]+)?$/', $normalized, $matches) === 1) {
            $suffix = $matches[2] ?? '';
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
            'unsupportedInput' => $hasInput && $direction['inputStatus'] === 'unsupported',
            'unsupportedOutput' => $hasOutput && $direction['outputStatus'] === 'unsupported',
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
     * @return list<string>
     */
    public static function roffManualBidirectionalFormats(): array
    {
        return self::roffManualFormatsWithDirection('input-output');
    }

    /**
     * @return list<string>
     */
    public static function roffManualInputOnlyFormats(): array
    {
        return self::roffManualFormatsWithDirection('input-only');
    }

    /**
     * @return list<string>
     */
    public static function roffManualOutputOnlyFormats(): array
    {
        return self::roffManualFormatsWithDirection('output-only');
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     inputFormats:list<string>,
     *     outputFormats:list<string>,
     *     directionBuckets:array{inputOutput:list<string>, inputOnly:list<string>, outputOnly:list<string>},
     *     extensionInference:array<string, string>,
     *     extensionPatternMetadata:array<string, array{format:string, kind:string, manualSection:bool}>,
     *     extensionInferredFormats:list<string>,
     *     nonExtensionInferredFormats:list<string>,
     *     paritySummary:array<string, mixed>,
     *     unsupportedExtensionSurfaces:array<string, array{extension:string, format:string, pattern:string|null, kind:string, manualSection:string|null, manualSectionNumber:string|null, manualSectionSuffix:string|null, input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, unsupportedInput:bool, unsupportedOutput:bool, inputImplementation:string, outputImplementation:string}>,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedOutputFormats:list<string>,
     *     unsupportedFormatSummary:array{
     *         anyUnsupported:list<string>,
     *         unsupportedBoth:list<string>,
     *         unsupportedInputOnly:list<string>,
     *         unsupportedOutputOnly:list<string>,
     *         noNativeReader:list<string>,
     *         noNativeWriter:list<string>
     *     },
     *     formats:array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, extensionInferred:bool, extensions:list<string>, inputImplementation:string, outputImplementation:string}>
     * }
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
                'inputOutput' => self::roffManualBidirectionalFormats(),
                'inputOnly' => self::roffManualInputOnlyFormats(),
                'outputOnly' => self::roffManualOutputOnlyFormats(),
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
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamSourceCommit:string,
     *     totalFormats:int,
     *     uniqueFormatCount:int,
     *     inputFormats:int,
     *     inputFormatCount:int,
     *     outputFormats:int,
     *     outputFormatCount:int,
     *     inputOutputFormats:int,
     *     inputOnlyFormats:int,
     *     outputOnlyFormats:int,
     *     directionCounts:array{inputOutput:int, inputOnly:int, outputOnly:int},
     *     inputSupportStatusCounts:array<string, int>,
     *     outputSupportStatusCounts:array<string, int>,
     *     extensionInferenceMappings:int,
     *     extensionInferredFormats:int,
     *     extensionInferredFormatCount:int,
     *     nonExtensionInferredFormats:int,
     *     nonExtensionInferredFormatCount:int,
     *     manualSectionExtensionMappings:int,
     *     literalExtensionMappings:int,
     *     sectionSuffixExtensionInference:bool,
     *     unsupportedExtensionSurfaceMappings:int,
     *     unsupportedInputFormats:int,
     *     unsupportedInputCount:int,
     *     unsupportedOutputFormats:int,
     *     unsupportedOutputCount:int,
     *     registeredInputImplementations:int,
     *     registeredOutputImplementations:int,
     *     directReaderParitySupported:bool,
     *     directWriterParitySupported:bool,
     *     directParityClaimed:bool,
     *     directParityStatus:string,
     *     reviewNote:string
     * }
     */
    public static function roffManualFormatParitySummary(): array
    {
        $directions = self::roffManualFormatDirections();
        $inputSupport = self::roffManualInputSupport();
        $outputSupport = self::roffManualOutputSupport();
        $inputFormatCount = count(self::ROFF_MANUAL_INPUT_FORMATS);
        $outputFormatCount = count(self::ROFF_MANUAL_OUTPUT_FORMATS);
        $inputOutputFormats = count(self::roffManualBidirectionalFormats());
        $inputOnlyFormats = count(self::roffManualInputOnlyFormats());
        $outputOnlyFormats = count(self::roffManualOutputOnlyFormats());
        $extensionInferredFormatCount = count(self::roffManualFormatsWithExtensionInference());
        $nonExtensionInferredFormatCount = count(self::roffManualFormatsWithoutExtensionInference());
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
     * @return list<string>
     */
    public static function textMarkupReaderFormats(): array
    {
        return self::TEXT_MARKUP_READER_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function textMarkupInputFormats(): array
    {
        return self::textMarkupReaderFormats();
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function textMarkupReaderSupport(): array
    {
        return self::onlyFormats(self::phpInputSupport(), self::TEXT_MARKUP_READER_FORMATS);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function textMarkupInputSupport(): array
    {
        return self::textMarkupReaderSupport();
    }

    /**
     * @return list<string>
     */
    public static function unsupportedTextMarkupReaderFormats(): array
    {
        return self::formatsWithStatus(self::textMarkupReaderSupport(), 'unsupported');
    }

    /**
     * @return list<string>
     */
    public static function unsupportedTextMarkupInputFormats(): array
    {
        return self::unsupportedTextMarkupReaderFormats();
    }

    /**
     * @return array<string, array{format:string, family:string, reasonCode:string, reason:string, inputStatus:string, outputStatus:string, unsupportedDirections:list<string>, readerCapable:bool, writerCapable:bool, inputImplementation:string, outputImplementation:string, inputNotes:string, outputNotes:string, externalToolFree:bool}>
     */
    public static function textMarkupUnsupportedFormatDiagnostics(): array
    {
        $inputSupport = self::textMarkupInputSupport();
        $outputSupport = self::phpOutputSupport();
        $diagnostics = [];

        foreach (self::TEXT_MARKUP_READER_FORMATS as $format) {
            $input = $inputSupport[$format];
            $output = $outputSupport[$format] ?? [
                'status' => 'not-applicable',
                'implementation' => '',
                'notes' => 'No upstream Pandoc writer token is registered for this text input format.',
            ];
            $family = self::TEXT_MARKUP_FORMAT_FAMILIES[$format] ?? 'lightweight-markup';
            $unsupportedDirections = [];

            if ($input['status'] === 'unsupported') {
                $unsupportedDirections[] = 'input';
            }
            if ($output['status'] === 'unsupported') {
                $unsupportedDirections[] = 'output';
            }

            $diagnostics[$format] = [
                'format' => $format,
                'family' => $family,
                'reasonCode' => self::TEXT_MARKUP_UNSUPPORTED_REASON_CODES[$family],
                'reason' => self::TEXT_MARKUP_UNSUPPORTED_REASONS[$family],
                'inputStatus' => $input['status'],
                'outputStatus' => $output['status'],
                'unsupportedDirections' => $unsupportedDirections,
                'readerCapable' => $input['implementation'] !== '' && $input['status'] !== 'unsupported',
                'writerCapable' => $output['implementation'] !== '' && $output['status'] !== 'unsupported',
                'inputImplementation' => $input['implementation'],
                'outputImplementation' => $output['implementation'],
                'inputNotes' => $input['notes'],
                'outputNotes' => $output['notes'],
                'externalToolFree' => true,
            ];
        }

        return $diagnostics;
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     family:string,
     *     inputFormats:list<string>,
     *     upstreamDenominator:int,
     *     localPassingNumerator:int,
     *     unsupportedVerdict:string,
     *     unsupportedFormats:list<string>,
     *     unsupportedCount:int,
     *     partialFormats:list<string>,
     *     implementedFormats:list<string>,
     *     familyCounts:array<string, int>,
     *     supportStatusCounts:array<string, int>,
     *     unsupportedReasonTaxonomy:array<string, array{family:string, status:string, readerParityStatus:string, reviewPolicy:string, externalToolFree:bool, message:string, formats:list<string>, formatCount:int}>,
     *     unsupportedReasonCounts:array<string, int>,
     *     directReaderParitySupported:bool,
     *     externalToolFree:bool,
     *     formats:array<string, array{family:string, inputStatus:string, inputImplementation:string, inputNotes:string, unsupported:bool, unsupportedReason:array{code:string, family:string, status:string, readerParityStatus:string, reviewPolicy:string, directReaderParity:bool, externalToolFree:bool, message:string}|null}>
     * }
     */
    public static function textMarkupReaderShipGate(): array
    {
        $support = self::textMarkupReaderSupport();
        $unsupportedFormats = self::formatsWithStatus($support, 'unsupported');
        $partialFormats = self::formatsWithStatus($support, 'partial');
        $implementedFormats = [];
        $formats = [];
        $familyCounts = [];
        $unsupportedReasonTaxonomy = [];
        $unsupportedReasonCounts = [];

        foreach (self::TEXT_MARKUP_READER_FORMATS as $format) {
            $entry = $support[$format];
            $family = self::textMarkupReaderFamily($format);
            $familyCounts[$family] = ($familyCounts[$family] ?? 0) + 1;

            if ($entry['implementation'] !== '') {
                $implementedFormats[] = $format;
            }

            $unsupportedReason = self::textMarkupReaderUnsupportedReason($format, $entry['status'], $family);
            if ($unsupportedReason !== null) {
                $code = $unsupportedReason['code'];
                $unsupportedReasonCounts[$code] = ($unsupportedReasonCounts[$code] ?? 0) + 1;
                $unsupportedReasonTaxonomy[$code] ??= self::textMarkupReaderUnsupportedReasonTaxonomyEntry($code);
                $unsupportedReasonTaxonomy[$code]['formats'][] = $format;
            }

            $formats[$format] = [
                'family' => $family,
                'inputStatus' => $entry['status'],
                'inputImplementation' => $entry['implementation'],
                'inputNotes' => $entry['notes'],
                'unsupported' => $entry['status'] === 'unsupported',
                'unsupportedReason' => $unsupportedReason,
            ];
        }
        ksort($familyCounts);
        ksort($unsupportedReasonCounts);
        ksort($unsupportedReasonTaxonomy);
        foreach ($unsupportedReasonTaxonomy as $code => $entry) {
            $unsupportedReasonTaxonomy[$code]['formatCount'] = count($entry['formats']);
        }

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamManualUrl' => self::UPSTREAM_MANUAL_URL,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'family' => 'wiki-roff-man-text-markup-readers',
            'inputFormats' => self::TEXT_MARKUP_READER_FORMATS,
            'upstreamDenominator' => count(self::TEXT_MARKUP_READER_FORMATS),
            'localPassingNumerator' => count($implementedFormats),
            'unsupportedVerdict' => $unsupportedFormats === [] ? 'supported' : 'unsupported',
            'unsupportedFormats' => $unsupportedFormats,
            'unsupportedCount' => count($unsupportedFormats),
            'partialFormats' => $partialFormats,
            'implementedFormats' => $implementedFormats,
            'familyCounts' => $familyCounts,
            'supportStatusCounts' => self::supportStatusCounts($support),
            'unsupportedReasonTaxonomy' => $unsupportedReasonTaxonomy,
            'unsupportedReasonCounts' => $unsupportedReasonCounts,
            'directReaderParitySupported' => $unsupportedFormats === [],
            'externalToolFree' => true,
            'formats' => $formats,
        ];
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     inputFormats:list<string>,
     *     upstreamInputDenominator:int,
     *     localNativeReaderPasses:int,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedInputCount:int,
     *     allInputUnsupported:bool,
     *     familyBuckets:array<string, list<string>>,
     *     reasonCodeCounts:array<string, int>,
     *     readerCapableFormats:list<string>,
     *     writerCapableFormats:list<string>,
     *     diagnostics:array<string, array{format:string, family:string, reasonCode:string, reason:string, inputStatus:string, outputStatus:string, unsupportedDirections:list<string>, readerCapable:bool, writerCapable:bool, inputImplementation:string, outputImplementation:string, inputNotes:string, outputNotes:string, externalToolFree:bool}>
     * }
     */
    public static function textMarkupUnsupportedFormatReviewPacket(): array
    {
        $diagnostics = self::textMarkupUnsupportedFormatDiagnostics();
        $unsupportedInputFormats = self::unsupportedTextMarkupInputFormats();
        $familyBuckets = [
            'lightweight-markup' => [],
            'wiki' => [],
            'roff-manual' => [],
        ];
        $reasonCodeCounts = [];
        $readerCapableFormats = [];
        $writerCapableFormats = [];

        foreach ($diagnostics as $format => $diagnostic) {
            $familyBuckets[$diagnostic['family']][] = $format;
            $reasonCode = $diagnostic['reasonCode'];
            $reasonCodeCounts[$reasonCode] = ($reasonCodeCounts[$reasonCode] ?? 0) + 1;

            if ($diagnostic['readerCapable']) {
                $readerCapableFormats[] = $format;
            }
            if ($diagnostic['writerCapable']) {
                $writerCapableFormats[] = $format;
            }
        }
        ksort($reasonCodeCounts);

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamManualUrl' => self::UPSTREAM_MANUAL_URL,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'inputFormats' => self::TEXT_MARKUP_READER_FORMATS,
            'upstreamInputDenominator' => count(self::TEXT_MARKUP_READER_FORMATS),
            'localNativeReaderPasses' => count($readerCapableFormats),
            'unsupportedInputFormats' => $unsupportedInputFormats,
            'unsupportedInputCount' => count($unsupportedInputFormats),
            'allInputUnsupported' => count($unsupportedInputFormats) === count(self::TEXT_MARKUP_READER_FORMATS),
            'familyBuckets' => $familyBuckets,
            'reasonCodeCounts' => $reasonCodeCounts,
            'readerCapableFormats' => $readerCapableFormats,
            'writerCapableFormats' => $writerCapableFormats,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<string>
     */
    public static function richPackageInputFormats(): array
    {
        return self::RICH_PACKAGE_INPUT_FORMATS;
    }

    /**
     * @return list<string>
     */
    public static function richPackageOutputFormats(): array
    {
        return self::RICH_PACKAGE_OUTPUT_FORMATS;
    }

    /**
     * @return array<string, string>
     */
    public static function richPackageExtensionInference(): array
    {
        return self::RICH_PACKAGE_EXTENSION_INFERENCE;
    }

    /**
     * @return string|null
     */
    public static function inferRichPackageFormatFromExtension(string $extension): ?string
    {
        return self::classifyRichPackageExtension($extension)['format'];
    }

    /**
     * @return array<string, array{primaryFormat:string, formats:list<string>, kind:string}>
     */
    public static function richPackageExtensionMetadata(): array
    {
        return self::RICH_PACKAGE_EXTENSION_METADATA;
    }

    /**
     * @return array{
     *     format:string|null,
     *     normalizedExtension:string,
     *     kind:string,
     *     formats:list<string>,
     *     inputFormats:list<string>,
     *     outputFormats:list<string>
     * }
     */
    public static function classifyRichPackageExtension(string $extension): array
    {
        $normalized = strtolower($extension);
        if ($normalized === '') {
            return self::richPackageExtensionClassification(null, '', 'unknown', []);
        }
        if ($normalized[0] !== '.') {
            $normalized = '.' . $normalized;
        }

        $metadata = self::RICH_PACKAGE_EXTENSION_METADATA[$normalized] ?? null;
        if ($metadata === null) {
            return self::richPackageExtensionClassification(null, $normalized, 'unknown', []);
        }

        return self::richPackageExtensionClassification(
            $metadata['primaryFormat'],
            $normalized,
            $metadata['kind'],
            $metadata['formats']
        );
    }

    /**
     * @return array{extension:string, format:string, input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, unsupportedInput:bool, unsupportedOutput:bool, partialInput:bool, partialOutput:bool, inputImplementation:string, outputImplementation:string}|null
     */
    public static function richPackageUnsupportedFormatForExtension(string $extension): ?array
    {
        $classification = self::classifyRichPackageExtension($extension);
        $format = $classification['format'];
        if ($format === null) {
            return null;
        }

        $directions = self::richPackageFormatDirections();
        $inputSupport = self::richPackageInputSupport();
        $outputSupport = self::richPackageOutputSupport();
        $direction = $directions[$format];
        $hasInput = $direction['input'];
        $hasOutput = $direction['output'];

        return [
            'extension' => $classification['normalizedExtension'],
            'format' => $format,
            'input' => $hasInput,
            'output' => $hasOutput,
            'direction' => $direction['direction'],
            'inputStatus' => $direction['inputStatus'],
            'outputStatus' => $direction['outputStatus'],
            'unsupportedInput' => $hasInput && $direction['inputStatus'] === 'unsupported',
            'unsupportedOutput' => $hasOutput && $direction['outputStatus'] === 'unsupported',
            'partialInput' => $hasInput && $direction['inputStatus'] === 'partial',
            'partialOutput' => $hasOutput && $direction['outputStatus'] === 'partial',
            'inputImplementation' => $hasInput ? $inputSupport[$format]['implementation'] : '',
            'outputImplementation' => $hasOutput ? $outputSupport[$format]['implementation'] : '',
        ];
    }

    /**
     * @return list<string>
     */
    public static function richPackageFormatsWithExtensionInference(): array
    {
        $formats = [];
        foreach (self::RICH_PACKAGE_EXTENSION_METADATA as $metadata) {
            foreach ($metadata['formats'] as $format) {
                $formats[] = $format;
            }
        }

        return array_values(array_unique($formats));
    }

    /**
     * @return list<string>
     */
    public static function richPackageFormatsWithoutExtensionInference(): array
    {
        $inferred = array_flip(self::richPackageFormatsWithExtensionInference());
        $formats = array_values(array_unique(array_merge(self::RICH_PACKAGE_INPUT_FORMATS, self::RICH_PACKAGE_OUTPUT_FORMATS)));
        $withoutInference = [];

        foreach ($formats as $format) {
            if (!array_key_exists($format, $inferred)) {
                $withoutInference[] = $format;
            }
        }

        return $withoutInference;
    }

    /**
     * @return array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}>
     */
    public static function richPackageFormatDirections(): array
    {
        return self::formatDirections(
            self::richPackageInputSupport(),
            self::richPackageOutputSupport(),
            self::RICH_PACKAGE_INPUT_FORMATS,
            self::RICH_PACKAGE_OUTPUT_FORMATS
        );
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     inputFormats:list<string>,
     *     outputFormats:list<string>,
     *     directionBuckets:array{inputOutput:list<string>, inputOnly:list<string>, outputOnly:list<string>},
     *     extensionInference:array<string, string>,
     *     extensionMetadata:array<string, array{primaryFormat:string, formats:list<string>, kind:string}>,
     *     extensionInferredFormats:list<string>,
     *     nonExtensionInferredFormats:list<string>,
     *     partialInputFormats:list<string>,
     *     partialOutputFormats:list<string>,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedOutputFormats:list<string>,
     *     unsupportedFormatSummary:array{
     *         anyUnsupported:list<string>,
     *         unsupportedBoth:list<string>,
     *         partialInputUnsupportedOutput:list<string>,
     *         unsupportedInputOnly:list<string>,
     *         unsupportedOutputOnly:list<string>,
     *         noNativeReader:list<string>,
     *         noNativeWriter:list<string>
     *     },
     *     formats:array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, extensionInferred:bool, extensions:list<string>, inputImplementation:string, outputImplementation:string}>
     * }
     */
    public static function richPackageFormatReviewPacket(): array
    {
        $directions = self::richPackageFormatDirections();
        $inputSupport = self::richPackageInputSupport();
        $outputSupport = self::richPackageOutputSupport();
        $extensionsByFormat = [];

        foreach (self::RICH_PACKAGE_EXTENSION_METADATA as $extension => $metadata) {
            foreach ($metadata['formats'] as $candidateFormat) {
                $extensionsByFormat[$candidateFormat][] = $extension;
            }
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
            'inputFormats' => self::RICH_PACKAGE_INPUT_FORMATS,
            'outputFormats' => self::RICH_PACKAGE_OUTPUT_FORMATS,
            'directionBuckets' => [
                'inputOutput' => self::richPackageBidirectionalFormats(),
                'inputOnly' => self::richPackageInputOnlyFormats(),
                'outputOnly' => self::richPackageOutputOnlyFormats(),
            ],
            'extensionInference' => self::RICH_PACKAGE_EXTENSION_INFERENCE,
            'extensionMetadata' => self::RICH_PACKAGE_EXTENSION_METADATA,
            'extensionInferredFormats' => self::richPackageFormatsWithExtensionInference(),
            'nonExtensionInferredFormats' => self::richPackageFormatsWithoutExtensionInference(),
            'partialInputFormats' => self::formatsWithStatus($inputSupport, 'partial'),
            'partialOutputFormats' => self::formatsWithStatus($outputSupport, 'partial'),
            'unsupportedInputFormats' => self::formatsWithStatus($inputSupport, 'unsupported'),
            'unsupportedOutputFormats' => self::formatsWithStatus($outputSupport, 'unsupported'),
            'unsupportedFormatSummary' => self::richPackageUnsupportedFormatSummary(),
            'formats' => $formats,
        ];
    }

    /**
     * @return array{
     *     anyUnsupported:list<string>,
     *     unsupportedBoth:list<string>,
     *     partialInputUnsupportedOutput:list<string>,
     *     unsupportedInputOnly:list<string>,
     *     unsupportedOutputOnly:list<string>,
     *     noNativeReader:list<string>,
     *     noNativeWriter:list<string>
     * }
     */
    public static function richPackageUnsupportedFormatSummary(): array
    {
        $directions = self::richPackageFormatDirections();
        $anyUnsupported = [];
        $unsupportedBoth = [];
        $partialInputUnsupportedOutput = [];
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
            if ($direction['inputStatus'] === 'partial' && $outputUnsupported) {
                $partialInputUnsupportedOutput[] = $format;
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
            'partialInputUnsupportedOutput' => $partialInputUnsupportedOutput,
            'unsupportedInputOnly' => $unsupportedInputOnly,
            'unsupportedOutputOnly' => $unsupportedOutputOnly,
            'noNativeReader' => self::unsupportedRichPackageInputFormats(),
            'noNativeWriter' => self::unsupportedRichPackageOutputFormats(),
        ];
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedOutputFormats:list<string>,
     *     unsupportedFormats:list<string>,
     *     unsupportedFormatCount:int,
     *     inputUnsupportedCount:int,
     *     outputUnsupportedCount:int,
     *     unsupportedFormatSummary:array{
     *         anyUnsupported:list<string>,
     *         unsupportedBoth:list<string>,
     *         partialInputUnsupportedOutput:list<string>,
     *         unsupportedInputOnly:list<string>,
     *         unsupportedOutputOnly:list<string>,
     *         noNativeReader:list<string>,
     *         noNativeWriter:list<string>
     *     },
     *     formats:array<string, array{input:bool, output:bool, direction:string, unsupportedDirections:list<string>, inputStatus:string, outputStatus:string, inputImplementation:string, outputImplementation:string, inputNotes:string, outputNotes:string, externalToolFree:bool}>
     * }
     */
    public static function richPackageUnsupportedFormatReviewPacket(): array
    {
        $directions = self::richPackageFormatDirections();
        $inputSupport = self::richPackageInputSupport();
        $outputSupport = self::richPackageOutputSupport();
        $formats = [];

        foreach ($directions as $format => $direction) {
            $hasInput = $direction['input'];
            $hasOutput = $direction['output'];
            $unsupportedDirections = [];

            if ($hasInput && $direction['inputStatus'] === 'unsupported') {
                $unsupportedDirections[] = 'input';
            }
            if ($hasOutput && $direction['outputStatus'] === 'unsupported') {
                $unsupportedDirections[] = 'output';
            }
            if ($unsupportedDirections === []) {
                continue;
            }

            $formats[$format] = [
                'input' => $hasInput,
                'output' => $hasOutput,
                'direction' => $direction['direction'],
                'unsupportedDirections' => $unsupportedDirections,
                'inputStatus' => $direction['inputStatus'],
                'outputStatus' => $direction['outputStatus'],
                'inputImplementation' => $hasInput ? $inputSupport[$format]['implementation'] : '',
                'outputImplementation' => $hasOutput ? $outputSupport[$format]['implementation'] : '',
                'inputNotes' => $hasInput ? $inputSupport[$format]['notes'] : '',
                'outputNotes' => $hasOutput ? $outputSupport[$format]['notes'] : '',
                'externalToolFree' => true,
            ];
        }

        $unsupportedInputFormats = self::formatsWithStatus($inputSupport, 'unsupported');
        $unsupportedOutputFormats = self::formatsWithStatus($outputSupport, 'unsupported');

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamManualUrl' => self::UPSTREAM_MANUAL_URL,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'unsupportedInputFormats' => $unsupportedInputFormats,
            'unsupportedOutputFormats' => $unsupportedOutputFormats,
            'unsupportedFormats' => array_keys($formats),
            'unsupportedFormatCount' => count($formats),
            'inputUnsupportedCount' => count($unsupportedInputFormats),
            'outputUnsupportedCount' => count($unsupportedOutputFormats),
            'unsupportedFormatSummary' => self::richPackageUnsupportedFormatSummary(),
            'formats' => $formats,
        ];
    }

    /**
     * @return list<string>
     */
    public static function xmlJatsBitsInputFormats(): array
    {
        return self::XML_JATS_BITS_INPUT_FORMATS;
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function xmlJatsBitsInputSupport(): array
    {
        return self::onlyFormats(self::phpInputSupport(), self::XML_JATS_BITS_INPUT_FORMATS);
    }

    /**
     * @return list<string>
     */
    public static function unsupportedXmlJatsBitsInputFormats(): array
    {
        return self::formatsWithStatus(self::xmlJatsBitsInputSupport(), 'unsupported');
    }

    /**
     * @return array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string}>
     */
    public static function xmlJatsBitsFormatDirections(): array
    {
        return self::formatDirections(
            self::xmlJatsBitsInputSupport(),
            [],
            self::XML_JATS_BITS_INPUT_FORMATS,
            []
        );
    }

    /**
     * @return array{
     *     upstreamManualDate:string,
     *     upstreamManualUrl:string,
     *     upstreamSourceCommit:string,
     *     inputFormats:list<string>,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedInputCount:int,
     *     inputSupportStatusCounts:array<string, int>,
     *     directReaderParitySupported:bool,
     *     registeredDirectReaderImplementations:int,
     *     boundedDiagnosticSurfaceCount:int,
     *     explicitUnsupportedVerdict:bool,
     *     reviewNote:string,
     *     formats:array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, inputImplementation:string, inputNotes:string, diagnosticImplementation:string, reviewMethod:string, reviewPolicy:string, directReaderParity:bool, aliasedTo:string|null, boundedDiagnostics:list<string>, remainingReaderGaps:list<string>}>
     * }
     */
    public static function xmlJatsBitsDirectReaderCapabilityPacket(): array
    {
        $directions = self::xmlJatsBitsFormatDirections();
        $inputSupport = self::xmlJatsBitsInputSupport();
        $unsupportedInputFormats = self::unsupportedXmlJatsBitsInputFormats();
        $formats = [];
        $registeredDirectReaderImplementations = 0;
        $boundedDiagnosticSurfaceCount = 0;

        foreach ($directions as $format => $direction) {
            $support = $inputSupport[$format];
            $diagnosticSurface = self::XML_JATS_BITS_DIAGNOSTIC_SURFACES[$format];

            if ($support['implementation'] !== '') {
                ++$registeredDirectReaderImplementations;
            }
            if ($diagnosticSurface['diagnosticImplementation'] !== '') {
                ++$boundedDiagnosticSurfaceCount;
            }

            $formats[$format] = [
                'input' => $direction['input'],
                'output' => $direction['output'],
                'direction' => $direction['direction'],
                'inputStatus' => $direction['inputStatus'],
                'outputStatus' => $direction['outputStatus'],
                'inputImplementation' => $support['implementation'],
                'inputNotes' => $support['notes'],
                'diagnosticImplementation' => $diagnosticSurface['diagnosticImplementation'],
                'reviewMethod' => $diagnosticSurface['reviewMethod'],
                'reviewPolicy' => $diagnosticSurface['reviewPolicy'],
                'directReaderParity' => $support['status'] !== 'unsupported' && $support['implementation'] !== '',
                'aliasedTo' => self::INPUT_ALIASES[$format] ?? null,
                'boundedDiagnostics' => $diagnosticSurface['boundedDiagnostics'],
                'remainingReaderGaps' => $diagnosticSurface['remainingReaderGaps'],
            ];
        }

        return [
            'upstreamManualDate' => self::UPSTREAM_MANUAL_DATE,
            'upstreamManualUrl' => self::UPSTREAM_MANUAL_URL,
            'upstreamSourceCommit' => self::UPSTREAM_SOURCE_COMMIT,
            'inputFormats' => self::XML_JATS_BITS_INPUT_FORMATS,
            'unsupportedInputFormats' => $unsupportedInputFormats,
            'unsupportedInputCount' => count($unsupportedInputFormats),
            'inputSupportStatusCounts' => self::supportStatusCounts($inputSupport),
            'directReaderParitySupported' => $unsupportedInputFormats === [],
            'registeredDirectReaderImplementations' => $registeredDirectReaderImplementations,
            'boundedDiagnosticSurfaceCount' => $boundedDiagnosticSurfaceCount,
            'explicitUnsupportedVerdict' => $unsupportedInputFormats === self::XML_JATS_BITS_INPUT_FORMATS,
            'reviewNote' => 'XML, JATS, and BITS have bounded native PHP diagnostics, but no full direct reader parity is registered.',
            'formats' => $formats,
        ];
    }

    /**
     * @return list<string>
     */
    public static function richPackageBidirectionalFormats(): array
    {
        return self::richPackageFormatsWithDirection('input-output');
    }

    /**
     * @return list<string>
     */
    public static function richPackageInputOnlyFormats(): array
    {
        return self::richPackageFormatsWithDirection('input-only');
    }

    /**
     * @return list<string>
     */
    public static function richPackageOutputOnlyFormats(): array
    {
        return self::richPackageFormatsWithDirection('output-only');
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
    public static function phpOutputSupport(): array
    {
        return self::supportMap(self::UPSTREAM_OUTPUT_FORMATS, self::PHP_OUTPUT_SUPPORT);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function wikiInputSupport(): array
    {
        return self::onlyFormats(self::phpInputSupport(), self::WIKI_INPUT_FORMATS);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function wikiOutputSupport(): array
    {
        return self::onlyFormats(self::phpOutputSupport(), self::WIKI_OUTPUT_FORMATS);
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
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function richPackageInputSupport(): array
    {
        return self::onlyFormats(self::phpInputSupport(), self::RICH_PACKAGE_INPUT_FORMATS);
    }

    /**
     * @return array<string, array{status:string, implementation:string, notes:string}>
     */
    public static function richPackageOutputSupport(): array
    {
        return self::onlyFormats(self::phpOutputSupport(), self::RICH_PACKAGE_OUTPUT_FORMATS);
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
     * @return list<string>
     */
    public static function unsupportedWikiInputFormats(): array
    {
        return self::formatsWithStatus(self::wikiInputSupport(), 'unsupported');
    }

    /**
     * @return list<string>
     */
    public static function unsupportedWikiOutputFormats(): array
    {
        return self::formatsWithStatus(self::wikiOutputSupport(), 'unsupported');
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
     * @return list<string>
     */
    public static function unsupportedRichPackageInputFormats(): array
    {
        return self::formatsWithStatus(self::richPackageInputSupport(), 'unsupported');
    }

    /**
     * @return list<string>
     */
    public static function unsupportedRichPackageOutputFormats(): array
    {
        return self::formatsWithStatus(self::richPackageOutputSupport(), 'unsupported');
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
     * @param list<string> $candidates
     * @param array<string, int> $sourceFiles
     * @return list<string>
     */
    private static function auditedFixtureSources(array $candidates, array $sourceFiles): array
    {
        $fixtures = [];
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $sourceFiles)) {
                $fixtures[] = $candidate;
            }
        }

        return $fixtures;
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
                'direction' => $hasInput && $hasOutput ? 'input-output' : ($hasInput ? 'input-only' : 'output-only'),
                'inputStatus' => $hasInput ? $inputSupport[$format]['status'] : 'not-applicable',
                'outputStatus' => $hasOutput ? $outputSupport[$format]['status'] : 'not-applicable',
            ];
        }

        return $directions;
    }

    /**
     * @param list<string> $formats
     * @return array{
     *     format:string|null,
     *     normalizedExtension:string,
     *     kind:string,
     *     formats:list<string>,
     *     inputFormats:list<string>,
     *     outputFormats:list<string>
     * }
     */
    private static function richPackageExtensionClassification(
        ?string $format,
        string $normalizedExtension,
        string $kind,
        array $formats
    ): array {
        $inputFormatSet = array_flip(self::RICH_PACKAGE_INPUT_FORMATS);
        $outputFormatSet = array_flip(self::RICH_PACKAGE_OUTPUT_FORMATS);

        return [
            'format' => $format,
            'normalizedExtension' => $normalizedExtension,
            'kind' => $kind,
            'formats' => $formats,
            'inputFormats' => array_values(array_filter(
                $formats,
                static fn (string $candidateFormat): bool => array_key_exists($candidateFormat, $inputFormatSet)
            )),
            'outputFormats' => array_values(array_filter(
                $formats,
                static fn (string $candidateFormat): bool => array_key_exists($candidateFormat, $outputFormatSet)
            )),
        ];
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
    private static function wikiFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::wikiFormatDirections(), $direction);
    }

    private static function wikiBaseFormat(string $format): string
    {
        $normalized = strtolower($format);
        $extensionOffset = strcspn($normalized, '+-');

        return substr($normalized, 0, $extensionOffset);
    }

    /**
     * @return list<string>
     */
    private static function roffManualFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::roffManualFormatDirections(), $direction);
    }

    private static function textMarkupReaderFamily(string $format): string
    {
        if (in_array($format, self::WIKI_INPUT_FORMATS, true)) {
            return 'wiki';
        }
        if (in_array($format, self::ROFF_MANUAL_INPUT_FORMATS, true)) {
            return 'roff-manual';
        }

        return 'text-markup';
    }

    /**
     * @return array{code:string, family:string, status:string, readerParityStatus:string, reviewPolicy:string, directReaderParity:bool, externalToolFree:bool, message:string}|null
     */
    private static function textMarkupReaderUnsupportedReason(string $format, string $status, string $family): ?array
    {
        if ($status !== 'unsupported' || $family !== 'roff-manual') {
            return null;
        }

        $code = 'roff-manual-native-reader-not-implemented';
        $entry = self::TEXT_MARKUP_READER_UNSUPPORTED_REASON_TAXONOMY[$code];

        return [
            'code' => $code,
            'family' => $entry['family'],
            'status' => $entry['status'],
            'readerParityStatus' => $entry['readerParityStatus'],
            'reviewPolicy' => $entry['reviewPolicy'],
            'directReaderParity' => false,
            'externalToolFree' => $entry['externalToolFree'],
            'message' => $format . ': ' . $entry['message'],
        ];
    }

    /**
     * @return array{family:string, status:string, readerParityStatus:string, reviewPolicy:string, externalToolFree:bool, message:string, formats:list<string>, formatCount:int}
     */
    private static function textMarkupReaderUnsupportedReasonTaxonomyEntry(string $code): array
    {
        $entry = self::TEXT_MARKUP_READER_UNSUPPORTED_REASON_TAXONOMY[$code];

        return [
            'family' => $entry['family'],
            'status' => $entry['status'],
            'readerParityStatus' => $entry['readerParityStatus'],
            'reviewPolicy' => $entry['reviewPolicy'],
            'externalToolFree' => $entry['externalToolFree'],
            'message' => $entry['message'],
            'formats' => [],
            'formatCount' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    private static function richPackageFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::richPackageFormatDirections(), $direction);
    }
}
