<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstNodeOfType = static function (AstNode $document, string $type): AstNode {
    $queue = $document->children;
    while ($queue !== []) {
        $node = array_shift($queue);
        if ($node->type === $type) {
            return $node;
        }

        foreach ($node->children as $child) {
            $queue[] = $child;
        }
    }

    return new AstNode('missing');
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$referenceCases = [
    'latin umlaut entity shortcut' => ['source' => "[&Auml;]\n\n[ä]: /unicode/a-umlaut \"A &amp; title\"", 'text' => 'Ä', 'url' => '/unicode/a-umlaut', 'title' => 'A & title'],
    'latin acute shortcut' => ['source' => "[Éclair]\n\n[éclair]: /unicode/eclair \"Dessert\"", 'text' => 'Éclair', 'url' => '/unicode/eclair', 'title' => 'Dessert'],
    'latin spacing shortcut' => ['source' => "[CAFÉ   IMPORT]\n\n[café import]: /unicode/cafe-import \"Spacing\"", 'text' => 'CAFÉ   IMPORT', 'url' => '/unicode/cafe-import', 'title' => 'Spacing'],
    'greek omega shortcut' => ['source' => "[Ωmega]\n\n[ωmega]: /unicode/omega \"Greek\"", 'text' => 'Ωmega', 'url' => '/unicode/omega', 'title' => 'Greek'],
    'greek sigma shortcut' => ['source' => "[Σigma]\n\n[σigma]: /unicode/sigma \"Sigma\"", 'text' => 'Σigma', 'url' => '/unicode/sigma', 'title' => 'Sigma'],
    'cyrillic de shortcut' => ['source' => "[Данные]\n\n[данные]: /unicode/dannye \"Data\"", 'text' => 'Данные', 'url' => '/unicode/dannye', 'title' => 'Data'],
    'cyrillic zhe shortcut' => ['source' => "[Журнал]\n\n[журнал]: /unicode/journal \"Journal\"", 'text' => 'Журнал', 'url' => '/unicode/journal', 'title' => 'Journal'],
    'nordic ring shortcut' => ['source' => "[ÅLAND]\n\n[åland]: /unicode/aland \"Island\"", 'text' => 'ÅLAND', 'url' => '/unicode/aland', 'title' => 'Island'],
    'latin caron shortcut' => ['source' => "[Český]\n\n[český]: /unicode/cesky \"Czech\"", 'text' => 'Český', 'url' => '/unicode/cesky', 'title' => 'Czech'],
    'latin stroke shortcut' => ['source' => "[Łódź]\n\n[łódź]: /unicode/lodz \"City\"", 'text' => 'Łódź', 'url' => '/unicode/lodz', 'title' => 'City'],
    'latin o umlaut collapsed' => ['source' => "[Öresund][]\n\n[öresund]: /unicode/oresund \"Bridge\"", 'text' => 'Öresund', 'url' => '/unicode/oresund', 'title' => 'Bridge'],
    'latin u umlaut collapsed' => ['source' => "[ÜBER][]\n\n[über]: /unicode/uber \"Above\"", 'text' => 'ÜBER', 'url' => '/unicode/uber', 'title' => 'Above'],
    'latin grave collapsed' => ['source' => "[À LA CARTE][]\n\n[à la carte]: /unicode/a-la-carte \"Menu\"", 'text' => 'À LA CARTE', 'url' => '/unicode/a-la-carte', 'title' => 'Menu'],
    'latin diacritic spacing collapsed' => ['source' => "[CRÈME  BRÛLÉE][]\n\n[crème brûlée]: /unicode/creme-brulee \"Dessert\"", 'text' => 'CRÈME  BRÛLÉE', 'url' => '/unicode/creme-brulee', 'title' => 'Dessert'],
    'latin tilde collapsed' => ['source' => "[NIÑO][]\n\n[niño]: /unicode/nino \"Child\"", 'text' => 'NIÑO', 'url' => '/unicode/nino', 'title' => 'Child'],
    'latin slash collapsed' => ['source' => "[Øresund  Link][]\n\n[øresund link]: /unicode/oresund-link \"Link\"", 'text' => 'Øresund  Link', 'url' => '/unicode/oresund-link', 'title' => 'Link'],
    'latin macron collapsed' => ['source' => "[Ārti][]\n\n[ārti]: /unicode/arti \"Macron\"", 'text' => 'Ārti', 'url' => '/unicode/arti', 'title' => 'Macron'],
    'latin s caron collapsed' => ['source' => "[Škoda][]\n\n[škoda]: /unicode/skoda \"Caron\"", 'text' => 'Škoda', 'url' => '/unicode/skoda', 'title' => 'Caron'],
    'greek delta collapsed' => ['source' => "[Δelta][]\n\n[δelta]: /unicode/delta \"Delta\"", 'text' => 'Δelta', 'url' => '/unicode/delta', 'title' => 'Delta'],
    'cyrillic word collapsed' => ['source' => "[ПРИМЕР][]\n\n[пример]: /unicode/primer \"Example\"", 'text' => 'ПРИМЕР', 'url' => '/unicode/primer', 'title' => 'Example'],
    'latin cedilla full' => ['source' => "[visible latin][ÇEDILLE]\n\n[çedille]: /unicode/cedille \"Cedilla\"", 'text' => 'visible latin', 'url' => '/unicode/cedille', 'title' => 'Cedilla'],
    'latin tilde full' => ['source' => "[visible latin][ÑANDÚ]\n\n[ñandú]: /unicode/nandu \"Bird\"", 'text' => 'visible latin', 'url' => '/unicode/nandu', 'title' => 'Bird'],
    'omega entity full' => ['source' => "[visible entity][&#x3A9;]\n\n[ω]: /unicode/entity-omega \"Entity\"", 'text' => 'visible entity', 'url' => '/unicode/entity-omega', 'title' => 'Entity'],
    'delta decimal entity full' => ['source' => "[visible entity][&#916;elta]\n\n[δelta]: /unicode/entity-delta \"Entity\"", 'text' => 'visible entity', 'url' => '/unicode/entity-delta', 'title' => 'Entity'],
    'cyrillic ya full' => ['source' => "[visible cyrillic][ЯКОРЬ]\n\n[якорь]: /unicode/yakor \"Anchor\"", 'text' => 'visible cyrillic', 'url' => '/unicode/yakor', 'title' => 'Anchor'],
    'cyrillic be full' => ['source' => "[visible cyrillic][БЛОК]\n\n[блок]: /unicode/blok \"Block\"", 'text' => 'visible cyrillic', 'url' => '/unicode/blok', 'title' => 'Block'],
    'greek lambda full' => ['source' => "[visible greek][ΛINK]\n\n[λink]: /unicode/lambda-link \"Lambda\"", 'text' => 'visible greek', 'url' => '/unicode/lambda-link', 'title' => 'Lambda'],
    'greek pi full' => ['source' => "[visible greek][ΠAGE]\n\n[πage]: /unicode/pi-page \"Pi\"", 'text' => 'visible greek', 'url' => '/unicode/pi-page', 'title' => 'Pi'],
    'latin city full' => ['source' => "[visible accent][MÜNCHEN]\n\n[münchen]: /unicode/munchen \"City\"", 'text' => 'visible accent', 'url' => '/unicode/munchen', 'title' => 'City'],
    'latin mixed full' => ['source' => "[visible accent][SMÖRGÅS]\n\n[smörgås]: /unicode/smorgas \"Plate\"", 'text' => 'visible accent', 'url' => '/unicode/smorgas', 'title' => 'Plate'],
];

$imageCases = [
    'latin umlaut image shortcut' => ['source' => "![Ä image]\n\n[ä image]: /images/a.png \"Image A\"", 'alt' => 'Ä image', 'url' => '/images/a.png', 'title' => 'Image A'],
    'greek omega image collapsed' => ['source' => "![Ωmega image][]\n\n[ωmega image]: /images/omega.png \"Image Omega\"", 'alt' => 'Ωmega image', 'url' => '/images/omega.png', 'title' => 'Image Omega'],
    'greek sigma image full' => ['source' => "![sigma alt][Σ IMAGE]\n\n[σ image]: /images/sigma.png \"Image Sigma\"", 'alt' => 'sigma alt', 'url' => '/images/sigma.png', 'title' => 'Image Sigma'],
    'cyrillic image full' => ['source' => "![data alt][ДАННЫЕ]\n\n[данные]: /images/data.png \"Image Data\"", 'alt' => 'data alt', 'url' => '/images/data.png', 'title' => 'Image Data'],
    'latin acute image shortcut' => ['source' => "![Éclair]\n\n[éclair]: /images/eclair.png \"Image Dessert\"", 'alt' => 'Éclair', 'url' => '/images/eclair.png', 'title' => 'Image Dessert'],
    'latin ring image collapsed' => ['source' => "![ÅLAND map][]\n\n[åland map]: /images/aland.png \"Image Map\"", 'alt' => 'ÅLAND map', 'url' => '/images/aland.png', 'title' => 'Image Map'],
    'latin caron image full' => ['source' => "![review packet][ČESKÝ]\n\n[český]: /images/cesky.png \"Image Czech\"", 'alt' => 'review packet', 'url' => '/images/cesky.png', 'title' => 'Image Czech'],
    'latin tilde image shortcut' => ['source' => "![NIÑO]\n\n[niño]: /images/nino.png \"Image Nino\"", 'alt' => 'NIÑO', 'url' => '/images/nino.png', 'title' => 'Image Nino'],
    'cyrillic ya image collapsed' => ['source' => "![ЯКОРЬ icon][]\n\n[якорь icon]: /images/anchor.png \"Image Anchor\"", 'alt' => 'ЯКОРЬ icon', 'url' => '/images/anchor.png', 'title' => 'Image Anchor'],
    'greek lambda image full' => ['source' => "![lambda alt][ΛINK]\n\n[λink]: /images/lambda.png \"Image Lambda\"", 'alt' => 'lambda alt', 'url' => '/images/lambda.png', 'title' => 'Image Lambda'],
];

$implicitHeadingCases = [
    'latin ring heading' => ['source' => "# Åland\n\n[åland]", 'text' => 'åland', 'url' => '#åland'],
    'latin acute heading' => ['source' => "# Éclair Review\n\n[éclair review]", 'text' => 'éclair review', 'url' => '#éclair-review'],
    'greek omega heading' => ['source' => "# Ωmega Source\n\n[ωmega source]", 'text' => 'ωmega source', 'url' => '#ωmega-source'],
    'greek sigma heading' => ['source' => "# Σigma Source\n\n[σigma source]", 'text' => 'σigma source', 'url' => '#σigma-source'],
    'cyrillic heading' => ['source' => "# Данные Review\n\n[данные review]", 'text' => 'данные review', 'url' => '#данные-review'],
    'latin umlaut heading' => ['source' => "# München Packet\n\n[münchen packet]", 'text' => 'münchen packet', 'url' => '#münchen-packet'],
    'latin cedilla heading' => ['source' => "# Çedille Packet\n\n[çedille packet]", 'text' => 'çedille packet', 'url' => '#çedille-packet'],
    'latin slash heading' => ['source' => "# Øresund Link\n\n[øresund link]", 'text' => 'øresund link', 'url' => '#øresund-link'],
    'latin caron heading' => ['source' => "# Český Packet\n\n[český packet]", 'text' => 'český packet', 'url' => '#český-packet'],
    'greek lambda heading' => ['source' => "# Λink Packet\n\n[λink packet]", 'text' => 'λink packet', 'url' => '#λink-packet'],
];

$tests = [];

foreach ($referenceCases as $name => $case) {
    $tests["maps upstream unicode case-folded reference link {$name}"] =
        static function (TestRunner $t) use ($case, $firstNodeOfType, $html): void {
            $document = (new MarkdownReader())->read($case['source']);
            $link = $firstNodeOfType($document, 'link');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type, $case['source']);
            $t->same($case['url'], $link->attr('url'), $case['source']);
            $t->same($case['title'], $link->attr('title'), $case['source']);
            $t->same($case['text'], $link->children[0]->attr('text'), $case['source']);
            $t->contains(
                '<a href="' . $html($case['url']) . '" title="' . $html($case['title']) . '">' . $html($case['text']) . '</a>',
                $blocks,
                $case['source']
            );
        };
}

foreach ($imageCases as $name => $case) {
    $tests["maps upstream unicode case-folded reference image {$name}"] =
        static function (TestRunner $t) use ($case, $firstNodeOfType, $html): void {
            $document = (new MarkdownReader())->read($case['source']);
            $image = $firstNodeOfType($document, 'image');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('image', $image->type, $case['source']);
            $t->same($case['url'], $image->attr('url'), $case['source']);
            $t->same($case['title'], $image->attr('title'), $case['source']);
            $t->same($case['alt'], $image->attr('alt'), $case['source']);
            $t->same($case['alt'], $image->children[0]->attr('text'), $case['source']);
            $t->contains(
                '<img src="' . $html($case['url']) . '" alt="' . $html($case['alt']) . '" title="' . $html($case['title']) . '"',
                $blocks,
                $case['source']
            );
        };
}

foreach ($implicitHeadingCases as $name => $case) {
    $tests["maps upstream unicode case-folded implicit heading reference {$name}"] =
        static function (TestRunner $t) use ($case, $firstNodeOfType, $html): void {
            $document = (new MarkdownReader())->read($case['source']);
            $link = $firstNodeOfType($document, 'link');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type, $case['source']);
            $t->same($case['url'], $link->attr('url'), $case['source']);
            $t->same($case['text'], $link->children[0]->attr('text'), $case['source']);
            $t->contains(
                '<a href="' . $html($case['url']) . '">' . $html($case['text']) . '</a>',
                $blocks,
                $case['source']
            );
        };
}

$tests['records unicode case-folded reference label surge mapped-case count'] =
    static function (TestRunner $t) use ($referenceCases, $imageCases, $implicitHeadingCases): void {
        $t->same(50, count($referenceCases) + count($imageCases) + count($implicitHeadingCases));
    };

return $tests;
