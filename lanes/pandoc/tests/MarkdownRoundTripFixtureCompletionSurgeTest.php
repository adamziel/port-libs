<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

/**
 * @return array<string, mixed>
 */
function markdown_roundtrip_fixture_signature(AstNode $node): array
{
    $tracked = [
        'text',
        'level',
        'id',
        'classes',
        'attributes',
        'url',
        'title',
        'alt',
        'caption',
    ];
    $attrs = [];
    foreach ($tracked as $name) {
        $value = $node->attr($name, null);
        if ($value !== null) {
            $attrs[$name] = markdown_roundtrip_fixture_normalize_value($value);
        }
    }

    return [
        'type' => $node->type,
        'attrs' => $attrs,
        'children' => array_map(
            static fn (AstNode $child): array => markdown_roundtrip_fixture_signature($child),
            $node->children
        ),
    ];
}

function markdown_roundtrip_fixture_normalize_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map(
            static fn (mixed $child): mixed => markdown_roundtrip_fixture_normalize_value($child),
            $value
        );
    }

    ksort($value);
    foreach ($value as $key => $child) {
        $value[$key] = markdown_roundtrip_fixture_normalize_value($child);
    }

    return $value;
}

/**
 * @return array<string, array{source:string, expectedContains?:list<string>}>
 */
function markdown_roundtrip_fixture_completion_cases(): array
{
    return [
        'image attrs id class width' => [
            'source' => '![Cover](media/cover.png){#cover .hero width=640}',
            'expectedContains' => ['![Cover](media/cover.png){#cover .hero width="640"}'],
        ],
        'image attrs multiple classes' => [
            'source' => '![Diagram](media/diagram.svg){.wide .bordered data-kind=diagram}',
            'expectedContains' => ['![Diagram](media/diagram.svg){.wide .bordered data-kind="diagram"}'],
        ],
        'image attrs alt override' => [
            'source' => '![Visible caption](media/review.jpg){alt="Editorial alt"}',
            'expectedContains' => ['![Visible caption](media/review.jpg){alt="Editorial alt"}'],
        ],
        'image attrs title and dimensions' => [
            'source' => '![Frame](frame.jpg "Frame title"){width=320 height=180}',
            'expectedContains' => ['![Frame](frame.jpg "Frame title"){width="320" height="180"}'],
        ],
        'image attrs data source' => [
            'source' => '![Source](source.png){data-source=batch-17 data-id=9}',
            'expectedContains' => ['![Source](source.png){data-source="batch-17" data-id="9"}'],
        ],
        'image attrs loading decoding' => [
            'source' => '![Lazy](lazy.png){loading=lazy decoding=async}',
            'expectedContains' => ['![Lazy](lazy.png){loading="lazy" decoding="async"}'],
        ],
        'image attrs role aria' => [
            'source' => '![Chart](chart.png){role=img aria-label="Quarterly chart"}',
            'expectedContains' => ['![Chart](chart.png){role="img" aria-label="Quarterly chart"}'],
        ],
        'image attrs language title attr' => [
            'source' => '![Mapa](mapa.png){lang=es title="Mapa fuente"}',
            'expectedContains' => ['![Mapa](mapa.png){lang="es" title="Mapa fuente"}'],
        ],
        'image attrs spaced destination' => [
            'source' => '![Review image](<media/review image.png>){#review-image .asset}',
            'expectedContains' => ['![Review image](media/review%20image.png){#review-image .asset}'],
        ],
        'image attrs parenthesized destination' => [
            'source' => '![Frame](<media/frame(1).png>){data-frame=1}',
            'expectedContains' => ['![Frame](<media/frame(1).png>){data-frame="1"}'],
        ],
        'image attrs reference full' => [
            'source' => "![Reference image][imgref]{#ref-img .from-ref width=200}\n\n[imgref]: media/ref.png \"Reference title\"",
            'expectedContains' => ['![Reference image](media/ref.png "Reference title"){#ref-img .from-ref width="200"}'],
        ],
        'image attrs reference shortcut' => [
            'source' => "![Shortcut image]{data-ref=shortcut}\n\n[Shortcut image]: media/shortcut.png",
            'expectedContains' => ['![Shortcut image](media/shortcut.png){data-ref="shortcut"}'],
        ],
        'image attrs empty destination' => [
            'source' => '![Empty](<>){data-empty=yes}',
            'expectedContains' => ['![Empty](<>)'],
        ],
        'image attrs quoted values' => [
            'source' => '![Quote](quote.png){title="A \"quoted\" source" data-note="a b"}',
            'expectedContains' => ['![Quote](quote.png){title="A \"quoted\" source" data-note="a b"}'],
        ],
        'image attrs single quoted values' => [
            'source' => "![Single](single.png){title='Single title' data-note='single note'}",
            'expectedContains' => ['![Single](single.png){title="Single title" data-note="single note"}'],
        ],
        'image attrs id only' => [
            'source' => '![Id](id.png){#image-id}',
            'expectedContains' => ['![Id](id.png){#image-id}'],
        ],
        'image attrs class only' => [
            'source' => '![Class](class.png){.image-class}',
            'expectedContains' => ['![Class](class.png){.image-class}'],
        ],
        'image attrs boolean-ish hidden' => [
            'source' => '![Hidden](hidden.png){hidden=true data-hidden=1}',
            'expectedContains' => ['![Hidden](hidden.png){hidden="true" data-hidden="1"}'],
        ],
        'image attrs srcset review' => [
            'source' => '![Responsive](responsive.png){srcset="small.png 480w, large.png 960w"}',
            'expectedContains' => ['![Responsive](responsive.png){srcset="small.png 480w, large.png 960w"}'],
        ],
        'image attrs usemap crossorigin' => [
            'source' => '![Map](map.png){usemap="#m" crossorigin=anonymous}',
            'expectedContains' => ['![Map](map.png){usemap="#m" crossorigin="anonymous"}'],
        ],
        'image attrs fetch priority' => [
            'source' => '![Hero](hero.png){fetchpriority=high}',
            'expectedContains' => ['![Hero](hero.png){fetchpriority="high"}'],
        ],
        'image attrs figure-ish source' => [
            'source' => '![Figure source](figure.png){data-pandoc-figure=source data-caption=figure}',
            'expectedContains' => ['![Figure source](figure.png){data-pandoc-figure="source" data-caption="figure"}'],
        ],
        'image attrs inline sentence' => [
            'source' => 'Before ![Inline](inline.png){#inline-img .inline width=20} after.',
            'expectedContains' => ['Before ![Inline](inline.png){#inline-img .inline width="20"} after.'],
        ],
        'image attrs adjacent link' => [
            'source' => '[Packet](/packet){#packet-link} ![Packet](packet.png){#packet-img}',
            'expectedContains' => ['[Packet](/packet){#packet-link} ![Packet](packet.png){#packet-img}'],
        ],
        'image attrs escaped label' => [
            'source' => '![A bracket label](bracket.png){data-label=bracket}',
            'expectedContains' => ['![A bracket label](bracket.png){data-label="bracket"}'],
        ],
        'line block smart apostrophe' => [
            'source' => "| It's a source line\n| that's normalized",
        ],
        'line block smart ellipsis' => [
            'source' => "| Wait...\n| continue...",
        ],
        'line block en dash' => [
            'source' => "| range 5--7\n| range 8--10",
        ],
        'line block em dash' => [
            'source' => "| alpha---beta\n| gamma---delta",
        ],
        'line block quoted text' => [
            'source' => "| &quot;Quoted&quot; source\n| escaped \\\"quote\\\" source",
        ],
        'line block entity text' => [
            'source' => "| Copyright &copy; source\n| AT&amp;T source",
        ],
        'line block escaped punctuation' => [
            'source' => "| \\*literal star\\*\n| \\[literal link\\]",
        ],
        'line block emphasis' => [
            'source' => "| *emph* and **strong**\n| ~~gone~~ text",
        ],
        'line block code' => [
            'source' => "| `code` and `` tick ` code ``\n| final",
        ],
        'line block link' => [
            'source' => "| [link](/u) and <https://example.test>\n| done",
        ],
        'line block image inline' => [
            'source' => "| ![Alt](img.png){width=20}\n| caption line",
        ],
        'line block math' => [
            'source' => '| $x + y$ and $z$',
        ],
        'line block raw html' => [
            'source' => '| before <span data-x=1>raw</span> after',
        ],
        'line block indentation four' => [
            'source' => "| plain\n|     indented four\n| done",
        ],
        'line block indentation eight' => [
            'source' => "| plain\n|         indented eight\n| done",
        ],
        'line block empty line' => [
            'source' => "| alpha\n|\n| omega",
        ],
        'line block continuation' => [
            'source' => "| Continuation\n line\n|   and\n       another",
        ],
        'line block bracketed span' => [
            'source' => '| [marked]{.mark} and [span]{#s .tracked}',
        ],
        'line block superscript subscript' => [
            'source' => '| H~2~O and x^2^',
        ],
        'line block emoji aliases' => [
            'source' => '| Smile :smile: and heart :heart:',
        ],
        'line block citation' => [
            'source' => '| Cite @doe2026 and [@roe2025, p. 7]',
        ],
        'line block note' => [
            'source' => '| Note^[inline note] in line',
        ],
        'line block nested quotes' => [
            'source' => '| He said it is fine and left...',
        ],
        'line block hard break literal' => [
            'source' => "| first\\\\\n| second",
        ],
        'line block mixed fixture stanza' => [
            'source' => "| The limerick packs laughs anatomical\n|    But the good ones I've seen\n| And the clean ones so seldom are comical",
        ],
    ];
}

$tests = [];
foreach (markdown_roundtrip_fixture_completion_cases() as $name => $case) {
    $tests['maps upstream markdown round trip fixture completion ' . $name] =
        static function (TestRunner $t) use ($case, $name): void {
            $reader = new MarkdownReader();
            $writer = new MarkdownWriter();
            $first = $reader->read($case['source']);
            $markdown = $writer->write($first);
            $second = $reader->read($markdown);

            $t->same(
                markdown_roundtrip_fixture_signature($first),
                markdown_roundtrip_fixture_signature($second),
                $name . ' should preserve reader signature after Markdown writer round trip'
            );
            foreach ($case['expectedContains'] ?? [] as $needle) {
                $t->contains($needle, $markdown, $name . ' should preserve expected Markdown output');
            }
        };
}

return $tests;
