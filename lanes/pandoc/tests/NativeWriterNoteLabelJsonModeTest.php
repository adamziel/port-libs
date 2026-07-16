<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'writes markdown note labels as native json sidecars without document provenance' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Tagged note[^editor-note] and inline note.^[Inline audit note.]',
            '',
            '[^editor-note]: Labelled source note.',
        ]));

        $native = (new NativeWriter())->write($document);
        $packet = json_decode($native, true, 512, JSON_THROW_ON_ERROR);
        $notes = array_values(array_filter(
            $packet['blocks'][0]['c'],
            static fn (mixed $inline): bool => is_array($inline) && ($inline['t'] ?? null) === 'Note'
        ));

        $t->same('editor-note', $notes[0]['noteLabel'] ?? null);
        $t->same(false, array_key_exists('noteLabel', $notes[1]));

        $roundTrip = (new NativeReader())->read($native);
        $roundTripNotes = [];
        $collectNotes = static function (AstNode $node) use (&$collectNotes, &$roundTripNotes): void {
            if ($node->type === 'note') {
                $roundTripNotes[] = $node;
            }

            foreach ($node->children as $child) {
                $collectNotes($child);
            }
        };
        $collectNotes($roundTrip);

        $t->same('editor-note', $roundTripNotes[0]->attr('label'));
        $t->same(null, $roundTripNotes[1]->attr('label'));
    },
];
