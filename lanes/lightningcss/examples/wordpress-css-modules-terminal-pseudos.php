<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.block {
  composes: reset;
  color: red;
}

.block::selection {
  background: var(--wp--preset--color--contrast);
  color: white;
}

.block::part(media):hover,
.block::part(media):current,
.block::selection:focus-within {
  color: var(--wp--preset--color--contrast);
}

.block::before:has(:hover, .is-selected, :global(.legacy)),
.block::part(media):is(:current, .active),
.block::selection:where(.draft) {
  outline-color: var(--wp--preset--color--accent);
}

:global(.wp-block-list)::marker {
  color: currentColor;
}

.reset {
  margin: 0;
}
CSS;

$transformer = new CssModulesTransformer();
$result = $transformer->transform($css, [
    'hash' => 'BlockA',
]);

$invalid = [];
foreach ([
    '.block::selection .child { color: red }',
    '.block::part(media) .child { color: red }',
    '.block::picker(select) .child { color: red }',
    '.block::picker(select):open { color: red }',
    '.block::before:current { color: red }',
    '.block::before:target-current { color: red }',
    '.block::before:not(.is-selected) { color: red }',
    ':global(.wp-block-list::marker .child) .block { color: red }',
    ':global(.wp-block-navigation::part(label) .child) .block { color: red }',
] as $source) {
    try {
        $transformer->transform($source, [
            'hash' => 'BlockA',
        ]);
        $invalid[$source] = 'accepted';
    } catch (InvalidArgumentException $exception) {
        $invalid[$source] = $exception->getMessage();
    }
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'blockClassList' => CssModulesTransformer::exportClassList($result['exports'], 'block'),
    'invalid' => $invalid,
];

$expected = [
    'code' => '.BlockA_block{color:red}.BlockA_block::selection{background:var(--wp--preset--color--contrast);color:#fff}.BlockA_block::part(media):hover,.BlockA_block::part(media):current,.BlockA_block::selection:focus-within{color:var(--wp--preset--color--contrast)}.BlockA_block:before:has(:hover),.BlockA_block::part(media):current,.BlockA_block::selection:where(){outline-color:var(--wp--preset--color--accent)}.wp-block-list::marker{color:currentColor}.BlockA_reset{margin:0}',
    'exports' => [
        'block' => [
            'name' => 'BlockA_block',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_reset',
                ],
            ],
            'isReferenced' => false,
        ],
        'reset' => [
            'name' => 'BlockA_reset',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'blockClassList' => 'BlockA_block BlockA_reset',
    'invalid' => [
        '.block::selection .child { color: red }' => 'CSS pseudo-elements cannot be followed by selectors',
        '.block::part(media) .child { color: red }' => 'CSS pseudo-elements cannot be followed by selectors',
        '.block::picker(select) .child { color: red }' => 'CSS pseudo-elements cannot be followed by selectors',
        '.block::picker(select):open { color: red }' => 'Invalid pseudo class after pseudo element, only user action pseudo classes (e.g. :hover, :active) are allowed',
        '.block::before:current { color: red }' => 'Invalid pseudo class after pseudo element, only user action pseudo classes (e.g. :hover, :active) are allowed',
        '.block::before:target-current { color: red }' => 'Invalid pseudo class after pseudo element, only user action pseudo classes (e.g. :hover, :active) are allowed',
        '.block::before:not(.is-selected) { color: red }' => 'Invalid pseudo class after pseudo element, only user action pseudo classes (e.g. :hover, :active) are allowed',
        ':global(.wp-block-list::marker .child) .block { color: red }' => 'CSS pseudo-elements cannot be followed by selectors',
        ':global(.wp-block-navigation::part(label) .child) .block { color: red }' => 'CSS pseudo-elements cannot be followed by selectors',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules terminal pseudo output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
