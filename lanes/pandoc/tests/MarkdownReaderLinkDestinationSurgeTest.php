<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$hasNodeType = null;
$hasNodeType = static function (AstNode $node, string $type) use (&$hasNodeType): bool {
    if ($node->type === $type) {
        return true;
    }

    foreach ($node->children as $child) {
        if ($hasNodeType($child, $type)) {
            return true;
        }
    }

    return false;
};

$firstNodeType = null;
$firstNodeType = static function (AstNode $node, string $type) use (&$firstNodeType): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $firstNodeType($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    $text = '';
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        $text .= (string) $node->attr('text', '');
    } elseif ($node->type === 'raw_tex') {
        $text .= (string) $node->attr('tex', '');
    }

    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'case';
};

$invalidBareDestinationCases = [];

foreach ([
    'alpha', 'bravo', 'charlie', 'delta', 'echo',
    'foxtrot', 'golf', 'hotel', 'india', 'juliet',
    'kilo', 'lima', 'mike', 'november', 'oscar',
    'papa', 'quebec', 'romeo', 'sierra', 'tango',
] as $name) {
    $invalidBareDestinationCases['inline link ' . $name] = [
        'markdown' => '[' . $name . '](/imports/' . $name . '<raw-' . $name . ')',
        'type' => 'link',
        'needle' => '[' . $name . '](/imports/' . $name . '<raw-' . $name . ')',
        'target' => '/imports/' . $name . '<raw-' . $name,
    ];
}

foreach ([
    'uniform', 'victor', 'whiskey', 'xray', 'yankee',
    'zulu', 'amber', 'cobalt', 'denim', 'ember',
] as $name) {
    $invalidBareDestinationCases['inline image ' . $name] = [
        'markdown' => '![' . $name . ' image](media/' . $name . '<raw-' . $name . '.png "Image ' . $name . '")',
        'type' => 'image',
        'needle' => '![' . $name . ' image](media/' . $name . '<raw-' . $name . '.png Image ' . $name . ')',
        'target' => 'media/' . $name . '<raw-' . $name . '.png',
    ];
}

foreach ([
    'fable', 'grove', 'harbor', 'islet', 'juno',
    'kepler', 'lagoon', 'meridian', 'north', 'onyx',
] as $name) {
    $invalidBareDestinationCases['shortcut reference ' . $name] = [
        'markdown' => '[' . $name . ']' . "\n\n" . '[' . $name . ']: /refs/' . $name . '<raw-' . $name . ' "Reference ' . $name . '"',
        'type' => 'link',
        'needle' => '[' . $name . ']' . '[' . $name . ']: /refs/' . $name . '<raw-' . $name . ' Reference ' . $name,
        'target' => '/refs/' . $name . '<raw-' . $name,
    ];
}

foreach ([
    'opal', 'pearl', 'quartz', 'river', 'summit',
    'thicket', 'umbra', 'valley', 'willow', 'zenith',
] as $name) {
    $label = 'packet ' . $name;
    $reference = 'raw ' . $slug($name);
    $invalidBareDestinationCases['full reference ' . $name] = [
        'markdown' => '[' . $label . '][' . $reference . ']' . "\n\n" . '[' . $reference . ']: /refs/' . $name . '<raw-' . $name . ' "Packet ' . $name . '"',
        'type' => 'link',
        'needle' => '[' . $label . '][' . $reference . ']' . '[' . $reference . ']: /refs/' . $name . '<raw-' . $name . ' Packet ' . $name,
        'target' => '/refs/' . $name . '<raw-' . $name,
    ];
}

$validEscapedDestinationCases = [
    'inline escaped less-than' => [
        'markdown' => '[escaped](/imports/escaped\<raw "Escaped title")',
        'type' => 'link',
        'url' => '/imports/escaped<raw',
        'title' => 'Escaped title',
    ],
    'inline angle escaped less-than' => [
        'markdown' => '[angle](</imports/angle\<raw> "Angle title")',
        'type' => 'link',
        'url' => '/imports/angle<raw',
        'title' => 'Angle title',
    ],
    'image escaped less-than' => [
        'markdown' => '![escaped image](media/escaped\<raw.png "Image title")',
        'type' => 'image',
        'url' => 'media/escaped<raw.png',
        'title' => 'Image title',
    ],
    'reference escaped less-than' => [
        'markdown' => '[reference]' . "\n\n" . '[reference]: /refs/reference\<raw "Reference title"',
        'type' => 'link',
        'url' => '/refs/reference<raw',
        'title' => 'Reference title',
    ],
    'reference angle escaped less-than' => [
        'markdown' => '[angle reference]' . "\n\n" . '[angle reference]: </refs/angle-reference\<raw> "Angle reference title"',
        'type' => 'link',
        'url' => '/refs/angle-reference<raw',
        'title' => 'Angle reference title',
    ],
];

return [
    'rejects upstream raw less-than bare link destinations without dropping literal text' => static function (TestRunner $t) use ($invalidBareDestinationCases, $hasNodeType, $plainText): void {
        $t->same(50, count($invalidBareDestinationCases));

        foreach ($invalidBareDestinationCases as $name => $case) {
            $document = (new MarkdownReader())->read($case['markdown']);
            $blocks = (new WordPressBlockWriter())->write($document);
            $text = preg_replace('/\s+/', '', $plainText($document)) ?? '';
            $needle = preg_replace('/\s+/', '', $case['needle']) ?? '';

            $t->same(false, $hasNodeType($document, $case['type']), $name . ' should not create ' . $case['type']);
            $t->contains($needle, $text, $name . ' literal markdown survives');
            $t->same(false, str_contains($blocks, 'href="' . $case['target'] . '"'), $name . ' should not render href');
            $t->same(false, str_contains($blocks, 'src="' . $case['target'] . '"'), $name . ' should not render image src');
        }
    },

    'keeps escaped and angle-wrapped less-than link destinations valid' => static function (TestRunner $t) use ($validEscapedDestinationCases, $firstNodeType): void {
        foreach ($validEscapedDestinationCases as $name => $case) {
            $document = (new MarkdownReader())->read($case['markdown']);
            $node = $firstNodeType($document, $case['type']);

            $t->same($case['type'], $node->type, $name);
            $t->same($case['url'], $node->attr('url'), $name . ' url');
            $t->same($case['title'], $node->attr('title'), $name . ' title');
        }
    },
];
