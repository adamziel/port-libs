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
    private const WIKI_EXTENSION_INFERENCE = [
        '.dokuwiki' => 'dokuwiki',
        '.wiki' => 'mediawiki',
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
            'formats' => $formats,
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
     * @return string|null
     */
    public static function inferRoffManualFormatFromExtension(string $extension): ?string
    {
        $normalized = strtolower($extension);
        if ($normalized === '') {
            return null;
        }
        if ($normalized[0] !== '.') {
            $normalized = '.' . $normalized;
        }

        if (preg_match('/^\.[1-9]$/', $normalized) === 1) {
            return 'man';
        }

        foreach (self::ROFF_MANUAL_EXTENSION_INFERENCE as $candidate => $format) {
            if ($candidate === '.[1-9]') {
                continue;
            }
            if ($normalized === $candidate) {
                return $format;
            }
        }

        return null;
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
     *     extensionInferredFormats:list<string>,
     *     nonExtensionInferredFormats:list<string>,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedOutputFormats:list<string>,
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
            'extensionInferredFormats' => self::roffManualFormatsWithExtensionInference(),
            'nonExtensionInferredFormats' => self::roffManualFormatsWithoutExtensionInference(),
            'unsupportedInputFormats' => self::formatsWithStatus($inputSupport, 'unsupported'),
            'unsupportedOutputFormats' => self::formatsWithStatus($outputSupport, 'unsupported'),
            'formats' => $formats,
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
     *     partialInputFormats:list<string>,
     *     partialOutputFormats:list<string>,
     *     unsupportedInputFormats:list<string>,
     *     unsupportedOutputFormats:list<string>,
     *     formats:array<string, array{input:bool, output:bool, direction:string, inputStatus:string, outputStatus:string, inputImplementation:string, outputImplementation:string}>
     * }
     */
    public static function richPackageFormatReviewPacket(): array
    {
        $directions = self::richPackageFormatDirections();
        $inputSupport = self::richPackageInputSupport();
        $outputSupport = self::richPackageOutputSupport();
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
            'partialInputFormats' => self::formatsWithStatus($inputSupport, 'partial'),
            'partialOutputFormats' => self::formatsWithStatus($outputSupport, 'partial'),
            'unsupportedInputFormats' => self::formatsWithStatus($inputSupport, 'unsupported'),
            'unsupportedOutputFormats' => self::formatsWithStatus($outputSupport, 'unsupported'),
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
     * @return list<string>
     */
    private static function wikiFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::wikiFormatDirections(), $direction);
    }

    /**
     * @return list<string>
     */
    private static function roffManualFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::roffManualFormatDirections(), $direction);
    }

    /**
     * @return list<string>
     */
    private static function richPackageFormatsWithDirection(string $direction): array
    {
        return self::formatsWithDirection(self::richPackageFormatDirections(), $direction);
    }
}
