<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$aliases = [
    '100' => "\u{1F4AF}",
    '1234' => "\u{1F522}",
    '8ball' => "\u{1F3B1}",
    'airplane' => "\u{2708}\u{FE0F}",
    'alarm_clock' => "\u{23F0}",
    'anchor' => "\u{2693}",
    'art' => "\u{1F3A8}",
    'atm' => "\u{1F3E7}",
    'ballot_box_with_check' => "\u{2611}\u{FE0F}",
    'bank' => "\u{1F3E6}",
    'bar_chart' => "\u{1F4CA}",
    'battery' => "\u{1F50B}",
    'bell' => "\u{1F514}",
    'blue_book' => "\u{1F4D8}",
    'blue_heart' => "\u{1F499}",
    'boom' => "\u{1F4A5}",
    'briefcase' => "\u{1F4BC}",
    'bulb' => "\u{1F4A1}",
    'calendar' => "\u{1F4C6}",
    'calling' => "\u{1F4F2}",
    'camera' => "\u{1F4F7}",
    'chart_with_upwards_trend' => "\u{1F4C8}",
    'clap' => "\u{1F44F}",
    'clipboard' => "\u{1F4CB}",
    'clock1' => "\u{1F550}",
    'coffee' => "\u{2615}",
    'computer' => "\u{1F4BB}",
    'construction' => "\u{1F6A7}",
    'email' => "\u{1F4E7}",
    'envelope' => "\u{2709}\u{FE0F}",
    'exclamation' => "\u{2757}",
    'file_folder' => "\u{1F4C1}",
    'gear' => "\u{2699}\u{FE0F}",
    'green_book' => "\u{1F4D7}",
    'green_heart' => "\u{1F49A}",
    'grey_question' => "\u{2754}",
    'hammer' => "\u{1F528}",
    'hourglass' => "\u{231B}",
    'hourglass_flowing_sand' => "\u{23F3}",
    'key' => "\u{1F511}",
    'keyboard' => "\u{2328}\u{FE0F}",
    'label' => "\u{1F3F7}\u{FE0F}",
    'large_blue_circle' => "\u{1F535}",
    'link' => "\u{1F517}",
    'lock' => "\u{1F512}",
    'mag' => "\u{1F50D}",
    'microphone' => "\u{1F3A4}",
    'mobile_phone_off' => "\u{1F4F4}",
    'new' => "\u{1F195}",
    'no_entry' => "\u{26D4}",
    'notebook' => "\u{1F4D3}",
    'office' => "\u{1F3E2}",
    'ok_hand' => "\u{1F44C}",
    'open_file_folder' => "\u{1F4C2}",
    'orange_book' => "\u{1F4D9}",
    'paperclip' => "\u{1F4CE}",
    'pencil2' => "\u{270F}\u{FE0F}",
    'phone' => "\u{260E}\u{FE0F}",
    'pushpin' => "\u{1F4CC}",
    'question' => "\u{2753}",
];

$tests = [];

foreach ($aliases as $alias => $glyph) {
    $tests['maps upstream markdown emoji alias ' . $alias] = static function (TestRunner $t) use ($alias, $glyph): void {
        $alias = (string) $alias;
        $markdown = 'Status :' . $alias . ': ready.';
        $document = (new MarkdownReader())->read($markdown);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $emoji = $paragraph->children[1] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('span', $emoji->type);
        $t->same(['emoji'], $emoji->attr('classes'));
        $t->same(['data-emoji' => $alias], $emoji->attr('attributes'));
        $t->same($glyph, $emoji->children[0]->attr('text'));
        $t->same($markdown, (new MarkdownWriter())->write($document));
        $t->contains('<span class="emoji" data-emoji="' . $alias . '">' . $glyph . '</span>', $blocks);
    };
}

$tests['keeps unknown emoji aliases literal around mapped surge aliases'] = static function (TestRunner $t): void {
    $document = (new MarkdownReader())->read('Status :calendar: :not-a-known-import-alias: :question:.');
    $paragraph = $document->children[0] ?? new AstNode('missing');
    $calendar = $paragraph->children[1] ?? new AstNode('missing');
    $unknown = $paragraph->children[2] ?? new AstNode('missing');
    $question = $paragraph->children[3] ?? new AstNode('missing');
    $blocks = (new WordPressBlockWriter())->write($document);

    $t->same(['data-emoji' => 'calendar'], $calendar->attr('attributes'));
    $t->same(' :not-a-known-import-alias: ', $unknown->attr('text'));
    $t->same(['data-emoji' => 'question'], $question->attr('attributes'));
    $t->same('Status :calendar: :not-a-known-import-alias: :question:.', (new MarkdownWriter())->write($document));
    $t->contains(':not-a-known-import-alias:', $blocks);
};

return $tests;
