<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$readFirstNode = static function (string $markdown, string $type) use ($findFirstNode): AstNode {
    return $findFirstNode((new MarkdownReader())->read($markdown), $type);
};

$plainInlineText = null;
$plainInlineText = static function (array $nodes) use (&$plainInlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'raw_tex') {
            $text .= (string) $node->attr('tex', '');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainInlineText($node->children);
    }

    return $text;
};

$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'case';
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$unicodeFoldCases = [
    'latin acute cafe' => ["CAF\u{00C9}", "caf\u{00E9}"],
    'latin resume' => ["R\u{00C9}SUM\u{00C9}", "r\u{00E9}sum\u{00E9}"],
    'latin angstrom' => ["\u{00C5}NGSTR\u{00D6}M", "\u{00E5}ngstr\u{00F6}m"],
    'latin munchen' => ["M\u{00DC}NCHEN", "m\u{00FC}nchen"],
    'latin cesky' => ["\u{010C}ESK\u{00DD}", "\u{010D}esk\u{00FD}"],
    'latin lodz' => ["\u{0141}\u{00D3}D\u{0179}", "\u{0142}\u{00F3}d\u{017A}"],
    'latin seker' => ["\u{015E}EKER", "\u{015F}eker"],
    'latin oresund' => ["\u{00D8}RESUND", "\u{00F8}resund"],
    'latin thorn' => ["\u{00DE}ING", "\u{00FE}ing"],
    'latin aesir' => ["\u{00C6}SIR", "\u{00E6}sir"],
    'latin sharp s' => ["\u{1E9E}TRA\u{1E9E}E", "\u{00DF}tra\u{00DF}e"],
    'greek dokimi' => ["\u{0394}\u{039F}\u{039A}\u{0399}\u{039C}\u{0397}", "\u{03B4}\u{03BF}\u{03BA}\u{03B9}\u{03BC}\u{03B7}"],
    'greek keimeno' => ["\u{039A}\u{03B5}\u{03AF}\u{03BC}\u{03B5}\u{03BD}\u{03BF}", "\u{03BA}\u{03B5}\u{03AF}\u{03BC}\u{03B5}\u{03BD}\u{03BF}"],
    'greek mathima' => ["\u{039C}\u{03AC}\u{0398}\u{0397}\u{039C}\u{0391}", "\u{03BC}\u{03AC}\u{03B8}\u{03B7}\u{03BC}\u{03B1}"],
    'greek logo' => ["\u{039B}\u{03CC}\u{0393}\u{039F}", "\u{03BB}\u{03CC}\u{03B3}\u{03BF}"],
    'greek delta' => ["\u{0394}\u{03AD}\u{03BB}\u{03C4}\u{0391}", "\u{03B4}\u{03AD}\u{03BB}\u{03C4}\u{03B1}"],
    'cyrillic primer' => ["\u{041F}\u{0440}\u{0438}\u{043C}\u{0435}\u{0440}", "\u{043F}\u{0440}\u{0438}\u{043C}\u{0435}\u{0440}"],
    'cyrillic dokument' => ["\u{0414}\u{041E}\u{041A}\u{0423}\u{041C}\u{0415}\u{041D}\u{0422}", "\u{0434}\u{043E}\u{043A}\u{0443}\u{043C}\u{0435}\u{043D}\u{0442}"],
    'cyrillic tekst' => ["\u{0422}\u{0415}\u{041A}\u{0421}\u{0422}", "\u{0442}\u{0435}\u{043A}\u{0441}\u{0442}"],
    'cyrillic zagolovok' => ["\u{0417}\u{0430}\u{0433}\u{043E}\u{043B}\u{043E}\u{0432}\u{043E}\u{043A}", "\u{0437}\u{0430}\u{0433}\u{043E}\u{043B}\u{043E}\u{0432}\u{043E}\u{043A}"],
    'macron a' => ["\u{0100}NALYSIS", "\u{0101}nalysis"],
    'macron e' => ["\u{0112}VIDENCE", "\u{0113}vidence"],
    'macron i' => ["\u{012A}NDEX", "\u{012B}ndex"],
    'macron o' => ["\u{014C}VERVIEW", "\u{014D}verview"],
    'macron u' => ["\u{016A}NIT", "\u{016B}nit"],
    'caron z' => ["\u{017D}URNAL", "\u{017E}urnal"],
    'caron r' => ["\u{0158}EADER", "\u{0159}eader"],
    'acute c' => ["\u{0106}ITATION", "\u{0107}itation"],
    'acute n' => ["\u{0143}OTE", "\u{0144}ote"],
    'acute s' => ["\u{015A}OURCE", "\u{015B}ource"],
    'ogonek a' => ["\u{0104}SSET", "\u{0105}sset"],
    'ogonek e' => ["\u{0118}NTRY", "\u{0119}ntry"],
    'breve g' => ["\u{011E}UIDE", "\u{011F}uide"],
    'dot z' => ["\u{017B}ONE", "\u{017C}one"],
    'ring u' => ["\u{016E}MLAUT", "\u{016F}mlaut"],
    'double acute o' => ["\u{0150}PTION", "\u{0151}ption"],
    'double acute u' => ["\u{0170}RL", "\u{0171}rl"],
    'eth label' => ["\u{00D0}OCUMENT", "\u{00F0}ocument"],
    'ntilde label' => ["\u{00D1}AME", "\u{00F1}ame"],
    'ydieresis label' => ["\u{0178}IELD", "\u{00FF}ield"],
];

$entityFoldCases = [
    'entity cafe' => ['CAF&Eacute;', "caf\u{00E9}", "CAF\u{00C9}"],
    'entity resume' => ['R&Eacute;SUM&Eacute;', "r\u{00E9}sum\u{00E9}", "R\u{00C9}SUM\u{00C9}"],
    'entity apfel' => ['&Auml;PFEL', "\u{00E4}pfel", "\u{00C4}PFEL"],
    'entity oresund' => ['&Ouml;RESUND', "\u{00F6}resund", "\u{00D6}RESUND"],
    'entity uber' => ['&Uuml;BER', "\u{00FC}ber", "\u{00DC}BER"],
    'entity cedille' => ['&Ccedil;EDILLE', "\u{00E7}edille", "\u{00C7}EDILLE"],
    'numeric greek alpha' => ['&#x391;LPHA', "\u{03B1}lpha", "\u{0391}LPHA"],
    'numeric cyrillic de' => ['&#1044;OK', "\u{0434}ok", "\u{0414}OK"],
    'combining acute' => ["A&#x301; NOTE", "a\u{0301} note", "A\u{0301} NOTE"],
    'mixed amp entity' => ["M&amp;M \u{00C9}DITION", "m&amp;m \u{00E9}dition", "M&M \u{00C9}DITION"],
];

$referenceShapeCases = [
    'explicit greek label' => [
        "[Visible][\u{039A}\u{03B5}\u{03AF}\u{03BC}\u{03B5}\u{03BD}\u{03BF}]\n\n[\u{03BA}\u{03B5}\u{03AF}\u{03BC}\u{03B5}\u{03BD}\u{03BF}]: /shape-explicit-greek \"Greek title\"",
        'link',
        '/shape-explicit-greek',
        'Greek title',
        'Visible',
    ],
    'collapsed latin label' => [
        "[CAF\u{00C9}][]\n\n[caf\u{00E9}]: /shape-collapsed-latin \"Latin title\"",
        'link',
        '/shape-collapsed-latin',
        'Latin title',
        "CAF\u{00C9}",
    ],
    'shortcut cyrillic label' => [
        "[\u{0414}\u{041E}\u{041A}]\n\n[\u{0434}\u{043E}\u{043A}]: /shape-shortcut-cyrillic \"Cyrillic title\"",
        'link',
        '/shape-shortcut-cyrillic',
        'Cyrillic title',
        "\u{0414}\u{041E}\u{041A}",
    ],
    'entity explicit label' => [
        "[Entity][R&Eacute;SUM&Eacute;]\n\n[r\u{00E9}sum\u{00E9}]: /shape-entity-explicit \"Entity title\"",
        'link',
        '/shape-entity-explicit',
        'Entity title',
        'Entity',
    ],
    'image greek label' => [
        "![Alt \u{0394}\u{039F}\u{039A}][\u{0394}\u{039F}\u{039A}]\n\n[\u{03B4}\u{03BF}\u{03BA}]: media/greek.png \"Greek image\"",
        'image',
        'media/greek.png',
        'Greek image',
        "Alt \u{0394}\u{039F}\u{039A}",
    ],
    'image entity label' => [
        '![Alt Cafe][CAF&Eacute;]' . "\n\n" . "[caf\u{00E9}]: media/cafe.png \"Cafe image\"",
        'image',
        'media/cafe.png',
        'Cafe image',
        'Alt Cafe',
    ],
    'first definition wins with unicode fold' => [
        "[\u{00C5}NGSTR\u{00D6}M]\n\n[\u{00E5}ngstr\u{00F6}m]: /first-definition \"First\"\n[\u{00C5}NGSTR\u{00D6}M]: /second-definition \"Second\"",
        'link',
        '/first-definition',
        'First',
        "\u{00C5}NGSTR\u{00D6}M",
    ],
    'implicit heading reference folds unicode' => [
        "## R\u{00C9}SUM\u{00C9}\n\n[r\u{00E9}sum\u{00E9}]",
        'link',
        '#résumé',
        '',
        "r\u{00E9}sum\u{00E9}",
    ],
];

$tests = [];

foreach ($unicodeFoldCases as $name => [$sourceLabel, $definitionLabel]) {
    $tests["maps upstream unicode case-folded reference label {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $plainInlineText, $slug, $name, $sourceLabel, $definitionLabel): void {
            $url = '/unicode-reference-' . $slug($name);
            $markdown = "[{$sourceLabel}]\n\n[{$definitionLabel}]: {$url} \"Unicode {$name}\"";
            $link = $readFirstNode($markdown, 'link');

            $t->same('link', $link->type, $name);
            $t->same($url, $link->attr('url'), $name);
            $t->same("Unicode {$name}", $link->attr('title'), $name);
            $t->same($sourceLabel, $plainInlineText($link->children), $name);
        };
}

foreach ($entityFoldCases as $name => [$sourceLabel, $definitionLabel, $expectedText]) {
    $tests["maps upstream entity decoded unicode reference label {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $plainInlineText, $slug, $name, $sourceLabel, $definitionLabel, $expectedText): void {
            $url = '/entity-unicode-reference-' . $slug($name);
            $markdown = "[{$sourceLabel}]\n\n[{$definitionLabel}]: {$url} \"Entity {$name}\"";
            $link = $readFirstNode($markdown, 'link');

            $t->same('link', $link->type, $name);
            $t->same($url, $link->attr('url'), $name);
            $t->same("Entity {$name}", $link->attr('title'), $name);
            $t->same($expectedText, $plainInlineText($link->children), $name);
        };
}

foreach ($referenceShapeCases as $name => [$markdown, $type, $url, $title, $expectedText]) {
    $tests["maps upstream unicode folded reference shape {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $plainInlineText, $name, $markdown, $type, $url, $title, $expectedText): void {
            $node = $readFirstNode($markdown, $type);

            $t->same($type, $node->type, $name);
            $t->same($url, $node->attr('url'), $name);
            if ($title !== '') {
                $t->same($title, $node->attr('title'), $name);
            }
            if ($type === 'image') {
                $t->same($expectedText, $node->attr('alt'), $name);
            } else {
                $t->same($expectedText, $plainInlineText($node->children), $name);
            }
        };
}

$tests['maps upstream unicode folded references through wordpress handoff'] = static function (TestRunner $t) use ($html): void {
    $markdown = implode("\n", [
        "[CAF\u{00C9}] and ![Greek image][\u{0394}\u{039F}\u{039A}].",
        '',
        "[caf\u{00E9}]: /wp-unicode-cafe \"Cafe title\"",
        "[\u{03B4}\u{03BF}\u{03BA}]: media/wp-greek.png \"Greek image\"",
    ]);
    $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

    $t->contains('<a href="/wp-unicode-cafe" title="Cafe title">' . $html("CAF\u{00C9}") . '</a>', $blocks);
    $t->contains('<img src="media/wp-greek.png" alt="Greek image" title="Greek image"/>', $blocks);
};

$tests['maps upstream 999-character reference label boundary'] = static function (TestRunner $t) use ($readFirstNode): void {
    $label = str_repeat('a', 999);
    $link = $readFirstNode("[{$label}]\n\n[{$label}]: /label-999 \"Allowed\"", 'link');

    $t->same('link', $link->type);
    $t->same('/label-999', $link->attr('url'));
    $t->same('Allowed', $link->attr('title'));
};

$tests['rejects upstream overlong shortcut reference label boundary'] = static function (TestRunner $t) use ($readFirstNode): void {
    $label = str_repeat('a', 1000);
    $link = $readFirstNode("[{$label}]\n\n[{$label}]: /label-1000 \"Rejected\"", 'link');

    $t->same('missing', $link->type);
};

$tests['rejects upstream overlong explicit reference label boundary'] = static function (TestRunner $t) use ($readFirstNode): void {
    $label = str_repeat('b', 1000);
    $link = $readFirstNode("[Visible][{$label}]\n\n[{$label}]: /explicit-label-1000 \"Rejected\"", 'link');

    $t->same('missing', $link->type);
};

$tests['rejects upstream overlong image reference label boundary'] = static function (TestRunner $t) use ($readFirstNode): void {
    $label = str_repeat('c', 1000);
    $image = $readFirstNode("![Visible][{$label}]\n\n[{$label}]: media/too-long.png \"Rejected\"", 'image');

    $t->same('missing', $image->type);
};

$tests['records markdown reference label normalization surge mapped-case count'] =
    static function (TestRunner $t) use ($unicodeFoldCases, $entityFoldCases, $referenceShapeCases): void {
        $t->same(62, count($unicodeFoldCases) + count($entityFoldCases) + count($referenceShapeCases) + 4);
    };

return $tests;
