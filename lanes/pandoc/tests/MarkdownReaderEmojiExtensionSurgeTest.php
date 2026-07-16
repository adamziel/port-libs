<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$emojiExtensionSurgeCases = [
    'angry' => "\u{1F620}",
    'anguished' => "\u{1F627}",
    'astonished' => "\u{1F632}",
    'bar_chart' => "\u{1F4CA}",
    'blush' => "\u{1F60A}",
    'book' => "\u{1F4D6}",
    'bookmark' => "\u{1F516}",
    'books' => "\u{1F4DA}",
    'bulb' => "\u{1F4A1}",
    'calendar' => "\u{1F4C6}",
    'camera' => "\u{1F4F7}",
    'chart_with_downwards_trend' => "\u{1F4C9}",
    'chart_with_upwards_trend' => "\u{1F4C8}",
    'clap' => "\u{1F44F}",
    'clipboard' => "\u{1F4CB}",
    'cold_sweat' => "\u{1F630}",
    'computer' => "\u{1F4BB}",
    'confounded' => "\u{1F616}",
    'confused' => "\u{1F615}",
    'cowboy_hat_face' => "\u{1F920}",
    'cry' => "\u{1F622}",
    'cursing_face' => "\u{1F92C}",
    'date' => "\u{1F4C5}",
    'disappointed' => "\u{1F61E}",
    'disappointed_relieved' => "\u{1F625}",
    'dizzy_face' => "\u{1F635}",
    'face_with_head_bandage' => "\u{1F915}",
    'face_with_thermometer' => "\u{1F912}",
    'fearful' => "\u{1F628}",
    'flushed' => "\u{1F633}",
    'frowning' => "\u{1F626}",
    'frowning_face' => "\u{2639}\u{FE0F}",
    'gear' => "\u{2699}\u{FE0F}",
    'grin' => "\u{1F601}",
    'grinning' => "\u{1F600}",
    'hammer' => "\u{1F528}",
    'heart_eyes' => "\u{1F60D}",
    'hugs' => "\u{1F917}",
    'hushed' => "\u{1F62F}",
    'innocent' => "\u{1F607}",
    'key' => "\u{1F511}",
    'keyboard' => "\u{2328}\u{FE0F}",
    'kissing' => "\u{1F617}",
    'kissing_closed_eyes' => "\u{1F61A}",
    'kissing_heart' => "\u{1F618}",
    'kissing_smiling_eyes' => "\u{1F619}",
    'laughing' => "\u{1F606}",
    'link' => "\u{1F517}",
    'lock' => "\u{1F512}",
    'mag' => "\u{1F50D}",
    'mag_right' => "\u{1F50E}",
    'mask' => "\u{1F637}",
    'money_mouth_face' => "\u{1F911}",
    'muscle' => "\u{1F4AA}",
    'nauseated_face' => "\u{1F922}",
    'nerd_face' => "\u{1F913}",
    'ok_hand' => "\u{1F44C}",
    'open_mouth' => "\u{1F62E}",
    'package' => "\u{1F4E6}",
    'page_facing_up' => "\u{1F4C4}",
    'paperclip' => "\u{1F4CE}",
    'partying_face' => "\u{1F973}",
    'pensive' => "\u{1F614}",
    'persevere' => "\u{1F623}",
    'pleading_face' => "\u{1F97A}",
    'point_down' => "\u{1F447}",
    'point_left' => "\u{1F448}",
    'point_right' => "\u{1F449}",
    'point_up' => "\u{261D}\u{FE0F}",
    'pray' => "\u{1F64F}",
    'printer' => "\u{1F5A8}\u{FE0F}",
    'pushpin' => "\u{1F4CC}",
    'rage' => "\u{1F621}",
    'raised_hands' => "\u{1F64C}",
    'relaxed' => "\u{263A}\u{FE0F}",
    'relieved' => "\u{1F60C}",
    'rofl' => "\u{1F923}",
    'scream' => "\u{1F631}",
    'sleeping' => "\u{1F634}",
    'sleepy' => "\u{1F62A}",
    'slightly_frowning_face' => "\u{1F641}",
    'slightly_smiling_face' => "\u{1F642}",
    'smirk' => "\u{1F60F}",
    'sneezing_face' => "\u{1F927}",
    'sob' => "\u{1F62D}",
    'star_struck' => "\u{1F929}",
    'stuck_out_tongue' => "\u{1F61B}",
    'stuck_out_tongue_closed_eyes' => "\u{1F61D}",
    'stuck_out_tongue_winking_eye' => "\u{1F61C}",
    'sunglasses' => "\u{1F60E}",
    'sweat_smile' => "\u{1F605}",
    'tired_face' => "\u{1F62B}",
    'triumph' => "\u{1F624}",
    'unamused' => "\u{1F612}",
    'unlock' => "\u{1F513}",
    'wave' => "\u{1F44B}",
    'weary' => "\u{1F629}",
    'wink' => "\u{1F609}",
    'worried' => "\u{1F61F}",
    'wrench' => "\u{1F527}",
    'writing_hand' => "\u{270D}\u{FE0F}",
    'yum' => "\u{1F60B}",
];

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tests = [];

foreach ($emojiExtensionSurgeCases as $alias => $glyph) {
    $tests['maps upstream markdown emoji extension alias ' . $alias] =
        static function (TestRunner $t) use ($alias, $glyph, $html): void {
            $markdown = 'Emoji :' . $alias . ': extension ready.';
            $document = (new MarkdownReader())->read($markdown);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $emoji = $paragraph->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('paragraph', $paragraph->type);
            $t->same(['text', 'span', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same(['emoji'], $emoji->attr('classes'));
            $t->same(['data-emoji' => $alias], $emoji->attr('attributes'));
            $t->same($glyph, $emoji->children[0]->attr('text'));
            $t->contains(
                '<span class="emoji" data-emoji="' . $alias . '">' . $html($glyph) . '</span>',
                $blocks
            );
        };
}

$tests['records upstream markdown emoji extension surge mapped-case count'] =
    static function (TestRunner $t) use ($emojiExtensionSurgeCases): void {
        $t->same(102, count($emojiExtensionSurgeCases));
    };

return $tests;