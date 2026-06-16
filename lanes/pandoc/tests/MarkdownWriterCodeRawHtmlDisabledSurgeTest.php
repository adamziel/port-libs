<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$codeBlock = static fn (string $text, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $text], $attrs)
);

$profiles = [
    'commonmark raw html disabled' => ['format' => 'commonmark-raw_html'],
    'gfm raw html disabled' => ['format' => 'gfm-raw_html'],
    'markdown strict raw html disabled' => ['format' => 'markdown_strict-raw_html'],
    'markdown phpextra raw html disabled' => ['format' => 'markdown_phpextra-raw_html'],
    'markdown mmd raw html disabled' => ['format' => 'markdown_mmd-raw_html'],
];

$cases = [
    '01 id and language class keeps portable fence' => [
        $codeBlock('echo alpha;', ['id' => 'snippet', 'classes' => ['php']]),
        "```php\necho alpha;\n```",
    ],
    '02 multi class keeps first portable info token' => [
        $codeBlock('echo beta;', ['classes' => ['php', 'numberLines']]),
        "```php\necho beta;\n```",
    ],
    '03 id only degrades to indented code' => [
        $codeBlock('echo gamma;', ['id' => 'id-only']),
        '    echo gamma;',
    ],
    '04 data attribute only degrades to indented code' => [
        $codeBlock('wp post list', ['attributes' => ['data-kind' => 'fixture']]),
        '    wp post list',
    ],
    '05 invalid info class degrades to indented code' => [
        $codeBlock('echo delta;', ['classes' => ['language php']]),
        '    echo delta;',
    ],
    '06 safe attribute keeps language fence without html' => [
        $codeBlock('echo epsilon;', ['classes' => ['bash'], 'attributes' => ['data-startfrom' => '7']]),
        "```bash\necho epsilon;\n```",
    ],
    '07 legacy info survives id only attributes' => [
        $codeBlock('echo zeta;', ['id' => 'legacy-id', 'info' => 'php startFrom=7']),
        "``` php startFrom=7\necho zeta;\n```",
    ],
    '08 legacy info survives invalid class attributes' => [
        $codeBlock('echo eta;', ['classes' => ['language php'], 'info' => 'php numberLines']),
        "``` php numberLines\necho eta;\n```",
    ],
    '09 punctuation language class stays portable' => [
        $codeBlock('int main() {}', ['classes' => ['c++#snippet'], 'attributes' => ['title' => 'Demo']]),
        "```c++#snippet\nint main() {}\n```",
    ],
    '10 slash info survives when class is not portable' => [
        $codeBlock('payload', ['classes' => ['custom/raw'], 'info' => 'custom/raw', 'id' => 'raw']),
        "``` custom/raw\npayload\n```",
    ],
    '11 empty attributed code keeps fenced shape' => [
        $codeBlock('', ['id' => 'empty', 'classes' => ['text']]),
        "```text\n\n```",
    ],
    '12 backtick payload lengthens portable fence' => [
        $codeBlock("```\nbody", ['id' => 'ticks', 'classes' => ['text']]),
        "````text\n```\nbody\n````",
    ],
    '13 tilde style option uses tilde portable fence' => [
        $codeBlock('echo theta;', ['id' => 'tilde', 'classes' => ['php']]),
        "~~~php\necho theta;\n~~~",
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    '14 fenced code option keeps code fenced without attributes' => [
        $codeBlock('echo iota;', ['attributes' => ['data-kind' => 'fixture']]),
        "```\necho iota;\n```",
        ['fencedCodeBlocks' => true],
    ],
    '15 explicit html code format request still respects raw html disabled' => [
        $codeBlock('echo kappa;', [
            'markdownCodeBlockFormat' => 'html',
            'id' => 'html-request',
            'classes' => ['php'],
            'attributes' => ['data-kind' => 'fixture'],
        ]),
        "```php\necho kappa;\n```",
    ],
];

$tests = [];
$caseCount = 0;

foreach ($profiles as $profileLabel => $profileOptions) {
    foreach ($cases as $caseLabel => $case) {
        $caseCount++;
        $tests['maps upstream markdown writer code raw html disabled surge '
            . str_pad((string) $caseCount, 2, '0', STR_PAD_LEFT)
            . ' ' . $profileLabel . ' ' . $caseLabel] =
            static function (TestRunner $t) use ($document, $case, $profileOptions): void {
                [$node, $expected, $caseOptions] = [$case[0], $case[1], $case[2] ?? []];
                $markdown = (new MarkdownWriter(array_replace($profileOptions, $caseOptions)))->write($document([$node]));

                $t->same($expected, $markdown);
                $t->true(!str_contains($markdown, '<pre><code'), 'Raw HTML code fallback must not leak when raw_html is disabled');
                $t->true(!str_contains($markdown, '{#'), 'Pandoc code attributes must not leak when fenced attributes are disabled');
            };
    }
}

$tests['records markdown writer code raw html disabled surge mapped case count'] =
    static function (TestRunner $t) use ($caseCount): void {
        $t->same(75, $caseCount);
    };

return $tests;
