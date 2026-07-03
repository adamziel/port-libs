<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-markdown-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-markdown-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-markdown-root';
    public const CHECKED_IN_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures';
    public const EXPECTED_SELECTED_FIXTURE_COUNT = 29;

    private const SOURCE_FILES = [
        'test/Tests/Readers/Markdown.hs',
        'src/Text/Pandoc/Readers/Markdown.hs',
    ];

    private const CHECKED_IN_MARKDOWN_FIXTURES = [
        'upstream-command-parse-raw.md' => [
            'role' => 'command-parse-raw-reader-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/parse-raw.md',
            'formatProfile' => 'markdown raw_attribute/raw_html/raw_tex',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-parse-raw.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderParseRawFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownWriterParseRawFixtureCompletionTest.php',
            ],
            'sha256' => 'e3b50f56f86883e3e323cf97d52cd07a3c3797fb7d5f89bbb422392e8008f72b',
            'bytes' => 379,
        ],
        'upstream-command-md-abbrevs.md' => [
            'role' => 'command-markdown-abbreviation-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/md-abbrevs.md',
            'formatProfile' => 'markdown',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-md-abbrevs.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAbbreviationCommandFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => 'e27c636cb9c5663201d84c2558a8f99320704b408c47bfa458eccebe73998d14',
            'bytes' => 398,
        ],
        'upstream-command-details-summary.md' => [
            'role' => 'command-details-summary-raw-html-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/details-summary.md',
            'formatProfile' => 'markdown raw_html/gfm',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-details-summary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTest.php',
                'lanes/pandoc/tests/MarkdownWriterDetailsSummaryFixtureCompletionTest.php',
            ],
            'sha256' => 'bd279e57d0cad59c8c7b9651f58fee3e763cb822af97ec34323144ea4fa0955c',
            'bytes' => 188,
        ],
        'upstream-command-gfm-details-list.md' => [
            'role' => 'command-gfm-details-list-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/gfm-details-list.md',
            'formatProfile' => 'gfm',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-gfm-details-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmDetailsListFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => 'ac68a5f4067d14d96e92bb45b950d171a808e25f848c33cc67cd8ef55e73ed9d',
            'bytes' => 107,
        ],
        'upstream-markdown-angle-autolinks.md' => [
            'role' => 'markdown-angle-autolink-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected angle autolink coverage',
            'formatProfile' => 'markdown bare_uris/autolink_bare_uris',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-angle-autolinks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAngleAutolinkFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownWriterAngleAutolinkFixtureCompletionTest.php',
            ],
            'sha256' => '203f0f64b99105e28b37ebd229ba6d8a6284aa41a1a9f17bdaf5aa3acfbb5836',
            'bytes' => 164,
        ],
        'upstream-markdown-inline-note-citations.md' => [
            'role' => 'markdown-inline-note-citation-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected inline note citation coverage',
            'formatProfile' => 'markdown citations/footnotes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-note-citations.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php',
            ],
            'sha256' => '041671cee998c4a9e27d3fb6af07f5db13ed8ee73ce8cc3112274dd6cc46fbcd',
            'bytes' => 142,
        ],
        'upstream-markdown-citation-span-boundary.md' => [
            'role' => 'markdown-citation-span-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected citation/span boundary coverage',
            'formatProfile' => 'markdown citations/bracketed_spans/native_spans',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-citation-span-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php',
                'lanes/pandoc/tests/MarkdownWriterCitationSpanBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => '4a9c744c4eef5597fcd1c178fd756b18ee78e70e57230689c564d2f695bef6d1',
            'bytes' => 84,
        ],
        'upstream-markdown-line-blocks.md' => [
            'role' => 'markdown-line-block-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected line block extension coverage',
            'formatProfile' => 'markdown line_blocks/commonmark_x/gfm profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-line-blocks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLineBlockProfileSurgeTest.php',
            ],
            'sha256' => '7a175df8a9934d4e50567ba25b1736df404f704740dc8671ea455e8910d4681c',
            'bytes' => 38,
        ],
        'upstream-markdown-task-list.md' => [
            'role' => 'markdown-task-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected task list extension coverage',
            'formatProfile' => 'markdown task_lists/commonmark_x/gfm profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-task-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTaskListProfileSurgeTest.php',
            ],
            'sha256' => '2631c0b4e1bbaa22fe4e13f8da163f37feb68c6a0c4fb4d8185402b43407611d',
            'bytes' => 108,
        ],
        'upstream-command-empty-paragraphs.md' => [
            'role' => 'command-empty-paragraphs-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/empty-paragraphs.md',
            'formatProfile' => 'native/html/docx empty_paragraphs',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-empty-paragraphs.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => '3cec1e6ab0a690ebe90035bd1a71453c755b6ff198283b8651c8161c20983314',
            'bytes' => 1800,
        ],
        'upstream-markdown-definition-lists.md' => [
            'role' => 'markdown-definition-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/no blank space',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-lists.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '233fac188307a5ed3eeaa321c45322e221637c4deaf8c52626af41161c2aaec0',
            'bytes' => 38,
        ],
        'upstream-markdown-definition-list-blank-first.md' => [
            'role' => 'markdown-definition-list-blank-first-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/blank space before first def',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-blank-first.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '2df49ee09f7e0538c7f2c3fff6ccea40a7a5d7caeeca954aa67a87f785be2970',
            'bytes' => 40,
        ],
        'upstream-markdown-definition-list-blank-second.md' => [
            'role' => 'markdown-definition-list-blank-second-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/blank space before second def',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-blank-second.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '41f9f906efbc3be2cd34fdf1ff854dc4e94ac257e3a467a0f466cf387a9142d2',
            'bytes' => 39,
        ],
        'upstream-markdown-github-wikilinks.md' => [
            'role' => 'markdown-github-wikilink-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown Github wiki links',
            'formatProfile' => 'markdown_github wikilinks_title_before_pipe',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-github-wikilinks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGithubWikiLinkFixtureCompletionTest.php',
            ],
            'sha256' => '6f0ec576210ab97db42c4e7facdde2a34edfdb34eabc7198fc19367729892b3f',
            'bytes' => 151,
        ],
        'upstream-markdown-inline-code-list-markers.md' => [
            'role' => 'markdown-inline-code-list-marker-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code in lists (#6284) selected marker literal cases',
            'formatProfile' => 'markdown inline code/list markers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-list-markers.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php',
            ],
            'sha256' => '66bbbbf31d775879a2df0a391f044b95241598d42a48a583148224664c50fcef',
            'bytes' => 132,
        ],
        'upstream-markdown-backslash-escaped-links.md' => [
            'role' => 'markdown-backslash-escaped-link-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown backslash escapes/in URL, in title, in reference link title, and in reference link URL',
            'formatProfile' => 'markdown escaped link destinations/titles',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-backslash-escaped-links.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBackslashEscapedLinkFixtureCompletionTest.php',
            ],
            'sha256' => '6ad9984a92f484095874487a5fd713e18489f8216829e1e6f7c9137beb2e5216',
            'bytes' => 110,
        ],
        'upstream-markdown-definition-list-nested-list.md' => [
            'role' => 'markdown-definition-list-nested-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/list in definition',
            'formatProfile' => 'markdown definition_lists nested list body',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-nested-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => 'fec7e3095c4cd2c98514e86f9dd6ab35106ee9fc9fffdfdb80116bc60bd7f8e7',
            'bytes' => 14,
        ],
        'upstream-markdown-definition-list-html-div.md' => [
            'role' => 'markdown-definition-list-html-div-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/definition list inside html div',
            'formatProfile' => 'markdown definition_lists raw_html html block body',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-html-div.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '8addb5b1c8253a8c5d4019e5d86c16ce335e8bd7409cd3ea54bfddd42dd2c4af',
            'bytes' => 26,
        ],
        'upstream-markdown-link-label-boundaries.md' => [
            'role' => 'markdown-link-label-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown links/no autolink inside link, no inline link inside link, and no bare URI inside link',
            'formatProfile' => 'markdown link label boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-link-label-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLinkLabelBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => '261c0f00e439b4cdf7cdac0748ec16fa55760d79eb5feeef29baf0e373b36e86',
            'bytes' => 76,
        ],
        'upstream-markdown-unbalanced-brackets.md' => [
            'role' => 'markdown-unbalanced-bracket-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown unbalanced brackets',
            'formatProfile' => 'markdown bracket literal boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-unbalanced-brackets.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderUnbalancedBracketFixtureCompletionTest.php',
            ],
            'sha256' => 'ad87edba0a8dc59fc7fb9f90885cd2203e1e4fae65e6a2feaa20cdb059d3ce5c',
            'bytes' => 15,
        ],
        'upstream-markdown-link-title-entities.md' => [
            'role' => 'markdown-link-title-entity-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown entities/in link title',
            'formatProfile' => 'markdown link title entity decoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-link-title-entities.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLinkTitleEntityFixtureTest.php',
            ],
            'sha256' => 'c9dc6a34cf99ca078667ee8f6c860a3fd0fc533cc6bef09982871ff7c52dc186',
            'bytes' => 41,
        ],
        'upstream-markdown-inline-code-attribute.md' => [
            'role' => 'markdown-inline-code-attribute-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code/with attribute',
            'formatProfile' => 'markdown inline_code_attributes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-attribute.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeFixtureTest.php',
            ],
            'sha256' => 'c66ef2aad2940a3c5dd08b69f66f2fb3418dd8e13403904ff625c9a9ed721033',
            'bytes' => 40,
        ],
        'upstream-markdown-inline-code-attribute-space.md' => [
            'role' => 'markdown-inline-code-attribute-space-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code/with attribute space',
            'formatProfile' => 'markdown inline_code_attributes spacing boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-attribute-space.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeSpaceFixtureTest.php',
            ],
            'sha256' => '9ebec08cb14463ce3612095b9e3be58869b95cd111cd4a1ab43fd462894aef58',
            'bytes' => 30,
        ],
        'upstream-markdown-character-references.md' => [
            'role' => 'markdown-character-reference-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown entities/character references',
            'formatProfile' => 'markdown entity decoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-character-references.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCharacterReferenceFixtureTest.php',
            ],
            'sha256' => '21c98a8e50f0dc8b4ee6fe323df335c944aca0b5c452c2db0809b47e2fd6aa6d',
            'bytes' => 14,
        ],
        'upstream-markdown-strikeout.md' => [
            'role' => 'markdown-strikeout-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown strikeout inline markup',
            'formatProfile' => 'markdown strikeout nested emph',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-strikeout.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderStrikeoutFixtureTest.php',
            ],
            'sha256' => '30c0bc85dc189577486880cb6f9a135d77b420b091bf6396f31d4a1f985cddcb',
            'bytes' => 25,
        ],
        'upstream-markdown-emoji-symbols.md' => [
            'role' => 'markdown-emoji-symbol-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown emoji/emoji symbols',
            'formatProfile' => 'markdown_github emoji shortcodes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-emoji-symbols.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderEmojiFixtureTest.php',
            ],
            'sha256' => '90eb7db9d0f39fb87c9caa96f9f02d6facd390d39e3e82f59724883e2cc1c32b',
            'bytes' => 17,
        ],
        'upstream-markdown-superscript-subscript.md' => [
            'role' => 'markdown-superscript-subscript-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected superscript/subscript inline markup coverage',
            'formatProfile' => 'markdown superscript/subscript escaped-space boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-superscript-subscript.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderScriptFixtureTest.php',
            ],
            'sha256' => 'bf1a4d320f780ab971cfa70deff5beaf835d738f3f5aab9f7c33def0c2e24efe',
            'bytes' => 199,
        ],
        'upstream-markdown-smart-punctuation.md' => [
            'role' => 'markdown-smart-punctuation-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected smart punctuation coverage',
            'formatProfile' => 'markdown smart quotes/apostrophes/ellipsis',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-smart-punctuation.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php',
            ],
            'sha256' => '4bb6eabecef549ae4b8e3f29c7a7f956d7d1e2a24f157cb42e7c10a97fbb0fb3',
            'bytes' => 135,
        ],
        'upstream-markdown-pipe-table-escaped-cell.md' => [
            'role' => 'markdown-pipe-table-escaped-cell-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected pipe table alignment and escaped pipe cell coverage',
            'formatProfile' => 'markdown pipe_tables escaped cell delimiter',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-pipe-table-escaped-cell.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderPipeTableFixtureCompletionTest.php',
            ],
            'sha256' => '11abcb5c5ff0e2815bc3abcfabbc5fcd38e19fb2be4ac32104b3e4c554d13516',
            'bytes' => 76,
        ],
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        $staticEvidence = self::checkedInCurrentEvidence($this->repoRoot);
        if (!is_dir($root)) {
            return [
                'schemaVersion' => 1,
                'tool' => self::TOOL_NAME,
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'root' => $this->displayPath($root),
                    'commit' => null,
                    'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                ],
                'denominator' => $this->emptyDenominator(),
                'sourceInventory' => $this->emptySourceInventory(),
                'staticCurrentEvidence' => $staticEvidence,
                'runnerEvidence' => self::runnerNotRunEvidence(),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $sourceInventory = $this->sourceInventory($root);
        $validationIssues = $this->validationIssues($sourceInventory, $staticEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => self::selectedFixtureDenominator(),
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => $staticEvidence,
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-markdown-reader-evidence' : 'invalid-upstream-markdown-reader-evidence',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtures = [];
        $issues = [];

        foreach (self::CHECKED_IN_MARKDOWN_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $coverageTests = array_values(array_map('strval', $snapshot['coverageTests']));
            $testReferences = self::localTestReferences($root, (string) $name);
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sourceKind' => (string) $snapshot['sourceKind'],
                'sourceReference' => (string) $snapshot['sourceReference'],
                'formatProfile' => (string) $snapshot['formatProfile'],
                'coverageTests' => $coverageTests,
                'localTestReferenceCount' => count($testReferences),
                'localTestReferences' => $testReferences,
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-markdown-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-markdown-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-markdown-fixture-byte-count-mismatch';
            }

            foreach ($coverageTests as $testPath) {
                if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $testPath))) {
                    $issues[] = 'missing-markdown-fixture-coverage-test';
                    break;
                }
            }
            if ($testReferences === []) {
                $issues[] = 'missing-markdown-fixture-local-test-reference';
            }
        }

        return [
            'kind' => 'static-checked-in-current-upstream-markdown-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => self::selectedFixtureDenominator(),
            'checkedInFixtureDirectory' => self::CHECKED_IN_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-markdown-reader-evidence' : 'invalid-checked-in-current-markdown-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding selected current upstream-derived Markdown reader fixtures to checked-in SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the twenty-nine selected checked-in Markdown fixture snapshots match the expected SHA-256 hashes and byte counts',
                    'each selected fixture has at least one local PHP test reference',
                    'the fixture set covers selected command, raw-attribute, abbreviation, details/summary, GFM, autolink, footnote/citation, citation/span boundary, empty-paragraph, definition-list spacing, nested-list body and html-div body, GitHub wiki-link, inline-code list-marker, attribute, and spaced-attribute literal behavior, backslash-escaped link, link-label boundary, unbalanced-bracket literal, link-title entity decoding, plain character-reference decoding, strikeout-with-nested-emphasis, GitHub emoji-shortcode, superscript/subscript escaped-space boundary behavior, smart punctuation quotes/apostrophes/ellipsis behavior, and pipe-table alignment with escaped-pipe cell behavior',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that the selected fixture set is the full upstream Markdown reader corpus',
                    'full Markdown dialect parity across every extension combination',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $selectedFixtureCount = (int) (
            ($denominator['selectedFixtureCount'] ?? 0) !== 0
                ? $denominator['selectedFixtureCount']
                : ($staticDenominator['selectedFixtureCount'] ?? 0)
        );

        return implode(PHP_EOL, [
            'Pandoc Markdown reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Selected checked-in fixtures: ' . $selectedFixtureCount,
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result or full Markdown dialect parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredSelectedFixtureCount(array $report, int $requiredCount): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $denominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];

        return (int) ($denominator['selectedFixtureCount'] ?? -1) === $requiredCount
            && (int) ($staticEvidence['checkedInFixtureCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-markdown-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && self::hasRequiredSelectedFixtureCount($report, self::EXPECTED_SELECTED_FIXTURE_COUNT);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerNotRunEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-markdown-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function selectedFixtureDenominator(): array
    {
        $fixtures = [];
        $sourceKinds = [];
        $formatProfiles = [];
        foreach (self::CHECKED_IN_MARKDOWN_FIXTURES as $name => $snapshot) {
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sourceKind' => (string) $snapshot['sourceKind'],
                'sourceReference' => (string) $snapshot['sourceReference'],
                'formatProfile' => (string) $snapshot['formatProfile'],
            ];
            $sourceKinds[(string) $snapshot['sourceKind']] = true;
            $formatProfiles[(string) $snapshot['formatProfile']] = true;
        }

        $sourceKindNames = array_keys($sourceKinds);
        sort($sourceKindNames, SORT_STRING);
        $formatProfileNames = array_keys($formatProfiles);
        sort($formatProfileNames, SORT_STRING);

        return [
            'selectedFixtureCount' => count($fixtures),
            'fixtureScope' => 'selected checked-in upstream-derived Markdown reader fixtures',
            'selectedFixtures' => $fixtures,
            'sourceKinds' => $sourceKindNames,
            'formatProfiles' => $formatProfileNames,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc Markdown reader suite',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'reason' => 'This native PHP evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    private static function claim(): string
    {
        return 'Tracks selected checked-in current upstream-derived Markdown reader fixtures and their local coverage as evidence for Markdown dialect reader progress.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the identity and count of twenty-nine selected checked-in upstream-derived Markdown fixtures',
                'that focused local tests cover those selected fixture files',
                'that the upstream Markdown reader source inventory is present when a hydrated upstream checkout is inspected',
                'that upstream Haskell runner evidence is explicitly not-run',
            ],
            'doesNotAssert' => [
                'full upstream Tests.Readers.Markdown runner parity',
                'complete Markdown dialect parity across every Pandoc extension profile',
                'writer parity beyond the local tests that happen to round-trip selected fixtures',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'selectedFixtureCount' => 0,
            'fixtureScope' => 'selected checked-in upstream-derived Markdown reader fixtures',
            'selectedFixtures' => [],
            'sourceKinds' => [],
            'formatProfiles' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySourceInventory(): array
    {
        return [
            'files' => [],
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'presentLineCount' => 0,
        ];
    }

    /**
     * @return array{files: list<array{path: string, present: bool, bytes: ?int, lineCount: ?int}>, presentFileCount: int, missingFileCount: int, presentLineCount: int}
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach (self::SOURCE_FILES as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $present = is_file($absolute);
            $contents = $present ? file_get_contents($absolute) : false;
            $bytes = $present ? filesize($absolute) : false;
            $files[] = [
                'path' => $path,
                'present' => $present,
                'bytes' => is_int($bytes) ? $bytes : null,
                'lineCount' => is_string($contents) ? substr_count($contents, "\n") + ($contents === '' || str_ends_with($contents, "\n") ? 0 : 1) : null,
            ];
        }

        $present = array_values(array_filter($files, static fn (array $file): bool => ($file['present'] ?? false) === true));

        return [
            'files' => $files,
            'presentFileCount' => count($present),
            'missingFileCount' => count($files) - count($present),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) ($file['lineCount'] ?? 0), $present)),
        ];
    }

    /**
     * @param array<string, mixed> $sourceInventory
     * @param array<string, mixed> $staticEvidence
     * @return list<string>
     */
    private function validationIssues(array $sourceInventory, array $staticEvidence): array
    {
        $issues = [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        if (($staticValidation['status'] ?? null) !== 'valid-checked-in-current-markdown-reader-evidence') {
            $issues[] = 'invalid-checked-in-current-markdown-reader-evidence';
        }
        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-markdown-reader-source';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private static function snapshotFileEvidence(string $root, string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'expectedSha256' => $expectedSha256,
            'bytes' => is_int($bytes) ? $bytes : null,
            'expectedBytes' => $expectedBytes,
        ];
    }

    /**
     * @return list<string>
     */
    private static function localTestReferences(string $root, string $fixtureName): array
    {
        $testRoot = rtrim($root, DIRECTORY_SEPARATOR) . '/lanes/pandoc/tests';
        if (!is_dir($testRoot)) {
            return [];
        }

        $references = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if ($file->getFilename() === 'MarkdownUpstreamReaderEvidenceTest.php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents) || !str_contains($contents, $fixtureName)) {
                continue;
            }

            $references[] = substr($file->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
        }
        sort($references, SORT_STRING);

        return $references;
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->upstreamRoot);
    }

    private function displayPath(string $path): string
    {
        if (str_starts_with($path, $this->repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->repoRoot) + 1);
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $head = $root . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $contents = trim((string) file_get_contents($head));
        if (str_starts_with($contents, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($contents, 5);
            return is_file($refPath) ? trim((string) file_get_contents($refPath)) : null;
        }

        return preg_match('/^[0-9a-f]{40}$/', $contents) === 1 ? $contents : null;
    }
}
