<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

$export = static fn (string $name, array $composes = []): array => [
    'name' => $name,
    'composes' => $composes,
    'isReferenced' => false,
];
$local = static fn (string $name): array => ['type' => 'local', 'name' => $name];
$global = static fn (string $name): array => ['type' => 'global', 'name' => $name];
$dependency = static fn (string $name, string $specifier): array => [
    'type' => 'dependency',
    'name' => $name,
    'specifier' => $specifier,
];
$referenced = static fn (string $name): array => [
    'name' => $name,
    'composes' => [],
    'isReferenced' => true,
];
$dashed = static fn (string $name, bool $isReferenced = false): array => [
    'name' => $name,
    'composes' => [],
    'isReferenced' => $isReferenced,
];

return [
    'css modules unwraps upstream local and global selector pseudos' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
:global(.foo) {
  color: red;
}

:local(.bar) {
  color: yellow;
}

.bar :global(.baz) {
  color: purple;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.foo{color:red}.EgL3uq_bar{color:#ff0}.EgL3uq_bar .baz{color:purple}', $result['code']);
        $t->same([
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules keeps nested local selectors global inside global scope' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
:global(.wp-block :local(.legacy)) .title {
  color: red;
}

.card :global(.wp-block :local(.legacy)) .title {
  color: yellow;
}

:global(:local(.utility)) {
  color: purple;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.wp-block .legacy .EgL3uq_title{color:red}.EgL3uq_card .wp-block .legacy .EgL3uq_title{color:#ff0}.utility{color:purple}', $result['code']);
        $t->same([
            'title' => $export('EgL3uq_title'),
            'card' => $export('EgL3uq_card'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules rejects local and global selector-list function arguments' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            '.x :global(.foo, .bar) { color: red }',
            ':global(.foo, .bar) .x { color: red }',
            ':local(.foo, .bar) { color: red }',
        ] as $css) {
            try {
                $transformer->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('Unexpected token Comma', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream selector-list comma exception');
        }

        foreach ([
            ':global() { color: red }',
            ':local() { color: red }',
        ] as $css) {
            try {
                $transformer->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('Invalid empty selector', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream empty selector exception');
        }
    },
    'css modules rejects upstream ambiguous bare local global pseudos before composing exports' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            ':global .foo { color: red }',
            ':local .foo { color: red }',
            '.foo:global { color: red }',
            '.foo:local { color: red }',
            '.button { composes: base; color: red } :global .legacy { color: blue } .base { color: white }',
        ] as $css) {
            try {
                $transformer->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('Ambiguous CSS module class not supported', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream ambiguous CSS module class exception');
        }
    },
    'css modules rejects bare global pseudos from upstream nested regression' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(<<<'CSS'
.blue {
  background: blue;

  :global {
    .red {
      background: red;
    }
  }
}
CSS));

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(<<<'CSS'
.blue {
  &:global {
    &.green {
      background: green;
    }
  }
}
CSS));
    },
    'css modules does not treat standard local-link pseudo as local mode syntax' => static function (TestRunner $t): void {
        $result = (new CssModulesTransformer())->transform(':local-link { color: red }');

        $t->same(':local-link{color:red}', $result['code']);
        $t->same([], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules treats double-colon local global as raw pseudo-elements before composing exports' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
::global(.wp-block) {
  color: red;
}

::local(.card) {
  color: yellow;
}

.card::global(.legacy) {
  color: blue;
}

::global .card {
  color: green;
}

.button {
  composes: card;
  color: white;
}

.card {
  color: black;
}
CSS);

        $t->same('::global(.wp-block){color:red}::local(.card){color:#ff0}.EgL3uq_card::global(.legacy){color:#00f}::global .EgL3uq_card{color:green}.EgL3uq_button{color:#fff}.EgL3uq_card{color:#000}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        $pure = (new CssModulesTransformer())->transform('::global .card { color: red }', [
            'pure' => true,
        ]);
        $t->same('::global .EgL3uq_card{color:red}', $pure['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $pure['exports']);

        foreach ([
            '::global(.card) { color: red }',
            '::local(.card) { color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css, [
                'pure' => true,
            ]));
        }

        foreach ([
            '.card::global(.legacy) { composes: base; color: red }',
            '::global .card { composes: base; color: red }',
            '::local(.card) { composes: base; color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules decodes escaped local and global pseudo names before composing exports' => static function (TestRunner $t) use ($export, $local, $global): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:lo\63 al(.card) {
  color: red;
}

:glo\62 al(.wp-block :lo\63 al(.legacy)) .card {
  color: yellow;
}

.button {
  composes: card;
  composes: wp-block-button from global;
  background: blue;
}
CSS);

        $t->same('.EgL3uq_card{color:red}.wp-block .legacy .EgL3uq_card{color:#ff0}.EgL3uq_button{background:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card'), $global('wp-block-button')]),
        ], $result['exports']);
        $t->same([], $result['references']);

        $pureLocal = (new CssModulesTransformer())->transform(':lo\63 al(.pure-card) { color: red }', [
            'pure' => true,
        ]);
        $t->same('.EgL3uq_pure-card{color:red}', $pureLocal['code']);
        $t->same([
            'pure-card' => $export('EgL3uq_pure-card'),
        ], $pureLocal['exports']);

        foreach ([
            ':glo\62 al { color: red }',
            ':lo\63 al { color: red }',
            ':glo\62 al(.wp-block) { color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css, [
                'pure' => str_contains($css, '('),
            ]));
        }
    },
    'css modules rejects upstream invalid escaped newlines in local global selectors before composing exports' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            ":local(.card\\\nTitle) { color: red }",
            ":global(.wp-block\\\nbutton) .card { color: red }",
            ".card { composes: base; color: red } :global(.legacy\\\r\nbutton) .card { color: blue } .base { color: yellow }",
            "@scope (:global(.wp-block\\\fbutton)) { .card { color: red } }",
        ] as $css) {
            try {
                $transformer->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('Invalid CSS escape in selector', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected invalid CSS selector escape exception');
        }
    },
    'css modules rejects escaped local global pseudo delimiters before composing exports' => static function (TestRunner $t) use ($export, $local): void {
        $valid = (new CssModulesTransformer())->transform(<<<'CSS'
:global(.wp\)button) .card {
  color: red;
}

:local(.card\)wide) {
  color: yellow;
}

.button {
  composes: card\)wide;
  color: white;
}
CSS);

        $t->same('.wp\)button .EgL3uq_card{color:red}.EgL3uq_card\)wide{color:#ff0}.EgL3uq_button{color:#fff}', $valid['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'card)wide' => $export('EgL3uq_card)wide'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card)wide')]),
        ], $valid['exports']);
        $t->same([], $valid['references']);

        foreach ([
            ':global\(.legacy) .card { color: red }',
            ':global\28 .legacy) .card { color: red }',
            '.card:global\(.legacy) { color: red }',
            ':local\(.card) { color: red }',
            '.button { composes: base; color: blue } :local\(.card) { color: red } .base { color: white }',
        ] as $css) {
            try {
                (new CssModulesTransformer())->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('Unexpected token CloseParenthesis', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream escaped CSS Modules pseudo delimiter exception');
        }
    },
    'css modules rejects upstream identifier-splitting selector comments before composing exports' => static function (TestRunner $t) use ($export, $local): void {
        $valid = (new CssModulesTransformer())->transform(<<<'CSS'
.card/* build marker */.is-wide {
  color: red;
}

:global(.wp-block/* build marker */.legacy) .card {
  color: yellow;
}

.button {
  composes: base;
  color: blue;
}

.base {
  color: green;
}
CSS);

        $t->same('.EgL3uq_card.EgL3uq_is-wide{color:red}.wp-block.legacy .EgL3uq_card{color:#ff0}.EgL3uq_button{color:#00f}.EgL3uq_base{color:green}', $valid['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'is-wide' => $export('EgL3uq_is-wide'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $valid['exports']);
        $t->same([], $valid['references']);

        foreach ([
            '.card/* build marker */Title { color: red }',
            ':local(.card/* build marker */Title) { color: red }',
            ':global(.wp-block/* build marker */button) .card { color: red }',
            '@media (min-width: 1px) { .card/* build marker */Title { composes: base; color: red } .base { color: blue } }',
        ] as $css) {
            try {
                (new CssModulesTransformer())->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('CSS comments cannot split selector identifiers', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected invalid selector comment boundary exception');
        }
    },
    'css modules enforces upstream pseudo-element boundaries around local global selectors' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:host(:global(.wp-block)) .card,
::slotted(.card),
.card::before:hover {
  color: red;
}

.card {
  composes: base;
  color: yellow;
}

.base {
  color: blue;
}
CSS);

        $t->same(':host(.wp-block) .EgL3uq_card,::slotted(.EgL3uq_card),.EgL3uq_card:before:hover{color:red}.EgL3uq_card{color:#ff0}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        foreach ([
            '::slotted(.card) .title { color: red }',
            '::slotted(:global(.wp-block)) .card { color: red }',
            '::slotted(.card):hover { color: red }',
            '.card::before .title { color: red }',
            ':global(.wp-block::before .title) .card { color: red }',
            ':local(.card::after .title) { color: red }',
            ':host(.card) { composes: base; color: red }',
            '::slotted(.card) { composes: base; color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules enforces upstream terminal pseudo-element boundaries while preserving local global composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card::selection,
:global(.wp-block-list)::marker,
.card::-webkit-input-placeholder,
.card::file-selector-button:hover,
.card::part(icon):hover,
.card::picker(select):open {
  color: red;
}

.button {
  composes: card;
  color: blue;
}

.card {
  color: green;
}
CSS);

        $t->same('.EgL3uq_card::selection,.wp-block-list::marker,.EgL3uq_card::-webkit-input-placeholder,.EgL3uq_card::file-selector-button:hover,.EgL3uq_card::part(icon):hover,.EgL3uq_card::picker(select):open{color:red}.EgL3uq_button{color:#00f}.EgL3uq_card{color:green}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        foreach ([
            '.card::selection .child { color: red }',
            ':global(.legacy::marker .child) .card { color: red }',
            ':local(.card::file-selector-button .child) { color: red }',
            '.card::placeholder::before { color: red }',
            '.card::-webkit-scrollbar-thumb .child { color: red }',
            '.card::view-transition .child { color: red }',
            '.card::part(icon) .child { color: red }',
            ':global(.legacy::part(icon) .child) .card { color: red }',
            ':local(.card::picker(select) .child) { color: red }',
            '.card::picker(select) .child { color: red }',
            '.card::part(icon) { composes: base; color: red }',
            '.card::picker(select) { composes: base; color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules filters selector functions after pseudo-elements without exporting dropped locals' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card::before:has(:hover, .child, :global(.legacy)) {
  color: red;
}

.card::selection:where(.draft, :local(.selected)) {
  color: yellow;
}

.card::part(icon):is(.active) {
  color: blue;
}

.card::cue(.caption):has(.cueChild) {
  color: green;
}

.button {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card:before:has(:hover){color:red}.EgL3uq_card::selection:where(){color:#ff0}.EgL3uq_card::part(icon):is(){color:#00f}.EgL3uq_card::cue(.EgL3uq_caption):has(){color:green}.EgL3uq_button{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'caption' => $export('EgL3uq_caption'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        $validPseudoOnly = (new CssModulesTransformer())->transform('.card::before:not(:active, :hover) { color: red }');
        $t->same('.EgL3uq_card:before:not(:active,:hover){color:red}', $validPseudoOnly['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $validPseudoOnly['exports']);

        $chainedPseudoTail = (new CssModulesTransformer())->transform('.card::before:is(.child, :active):hover { color: red }');
        $t->same('.EgL3uq_card:before:active:hover{color:red}', $chainedPseudoTail['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $chainedPseudoTail['exports']);

        foreach ([
            '.card::before:not(.child) { color: red }',
            '.card::before:not(:local(.child)) { color: red }',
            '.card::part(icon):not(:global(.legacy)) { color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules scopes upstream cue selectors while preserving local global composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card::cue(:global(.wp-caption) .captionTitle) {
  color: red;
}

.card::cue-region(.activeCue):hover {
  color: yellow;
}

.button {
  composes: card;
  color: white;
}

.card {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card::cue(.wp-caption .EgL3uq_captionTitle){color:red}.EgL3uq_card::cue-region(.EgL3uq_activeCue):hover{color:#ff0}.EgL3uq_button{color:#fff}.EgL3uq_card{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'captionTitle' => $export('EgL3uq_captionTitle'),
            'activeCue' => $export('EgL3uq_activeCue'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        foreach ([
            '.card::cue(:global(.wp-caption), .captionTitle) { color: red }',
            '.card::cue-region(.activeCue) .title { color: red }',
            '.card::cue(.activeCue) { composes: base; color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }

        $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform('::cue(.activeCue) { color: red }', ['pure' => true]));
    },
    'css modules scopes upstream state and highlight custom idents while preserving local global composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:local(.card:state(open)) {
  color: red;
}

:global(.legacy:state(public)) .card {
  color: yellow;
}

.card::highlight(focus-ring):hover {
  outline-color: blue;
}

:global(.legacy::highlight(public-ring)) .card {
  border-color: green;
}

.card {
  composes: base;
  color: white;
}

.base {
  color: black;
}
CSS);

        $t->same('.EgL3uq_card:state(EgL3uq_open){color:red}.legacy:state(public) .EgL3uq_card{color:#ff0}.EgL3uq_card::highlight(EgL3uq_focus-ring):hover{outline-color:#00f}.legacy::highlight(public-ring) .EgL3uq_card{border-color:green}.EgL3uq_card{color:#fff}.EgL3uq_base{color:#000}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'open' => $export('EgL3uq_open'),
            'focus-ring' => $export('EgL3uq_focus-ring'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        foreach ([
            '.card:state(initial) { color: red }',
            '.card:state(foo bar) { color: red }',
            '.card::highlight(.focus-ring) { color: red }',
            '.card::highlight(focus-ring) .title { color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules leaves upstream raw custom pseudo function tokens unscoped while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes: base;
  color: red;
}

.card:--theme-state(.legacy, :hover, #anchor) {
  color: yellow;
}

.card:is(.featured, :global(.wp-block-card)) {
  color: purple;
}

.item:nth-child(2n of .card, :global(.wp-block)) {
  color: blue;
}

.base {
  color: green;
}
CSS, [
            'pseudoClasses' => [
                'hover' => 'is-hovered',
            ],
        ]);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_card:--theme-state(.legacy,:hover,#anchor){color:#ff0}.EgL3uq_card:is(.EgL3uq_featured,.wp-block-card){color:purple}.EgL3uq_item:nth-child(2n of .EgL3uq_card,.wp-block){color:#00f}.EgL3uq_base{color:green}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'featured' => $export('EgL3uq_featured'),
            'item' => $export('EgL3uq_item'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $transformer = new CssModulesTransformer();
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(':--theme-state(.legacy) { color: red }', ['pure' => true]));

        $pureSelectorFunction = $transformer->transform(':is(:--theme-state(.legacy), .card) { color: red }', ['pure' => true]);
        $t->same(':is(:--theme-state(.legacy),.EgL3uq_card){color:red}', $pureSelectorFunction['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $pureSelectorFunction['exports']);
    },
    'css modules minifies upstream nth-child formulas after local global rewriting while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:nth-child(2n + 1 of :global(.wp-block-post) + .child) {
  color: red;
}

.card:nth-last-child(even of :local(.slot), :global(.legacy)) {
  color: blue;
}

.card:nth-child(0n + 3 of .item) {
  color: green;
}

.badge:nth-child(2n + 1) {
  color: yellow;
}

.button {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card:nth-child(odd of .wp-block-post+.EgL3uq_child){color:red}.EgL3uq_card:nth-last-child(2n of .EgL3uq_slot,.legacy){color:#00f}.EgL3uq_card:nth-child(3 of .EgL3uq_item){color:green}.EgL3uq_badge:nth-child(odd){color:#ff0}.EgL3uq_button{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'child' => $export('EgL3uq_child'),
            'slot' => $export('EgL3uq_slot'),
            'item' => $export('EgL3uq_item'),
            'badge' => $export('EgL3uq_badge'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));
    },
    'css modules filters upstream forgiving local global selector lists while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:is(:global(.legacy, .wp-button), .kept, .other) {
  color: red;
}

.card:where(:local(.drop, .also), .soft, :global(.public)) {
  color: yellow;
}

.card:has(:global(.bad, .worse), .child, .media) {
  color: blue;
}

.item:nth-child(2n of :global(.bad, .worse), .card, :global(.public)) {
  color: green;
}

.item:nth-last-child(odd of :local(.drop, .also), :global(.public), .card) {
  color: purple;
}

.panel {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card:is(.EgL3uq_kept,.EgL3uq_other){color:red}.EgL3uq_card:where(.EgL3uq_soft,.public){color:#ff0}.EgL3uq_card:has(.EgL3uq_child,.EgL3uq_media){color:#00f}.EgL3uq_item:nth-child(2n of .EgL3uq_card,.public){color:green}.EgL3uq_item:nth-last-child(odd of .public,.EgL3uq_card){color:purple}.EgL3uq_panel{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'kept' => $export('EgL3uq_kept'),
            'other' => $export('EgL3uq_other'),
            'soft' => $export('EgL3uq_soft'),
            'child' => $export('EgL3uq_child'),
            'media' => $export('EgL3uq_media'),
            'item' => $export('EgL3uq_item'),
            'panel' => $export('EgL3uq_panel', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);

        $emptyForgiving = (new CssModulesTransformer())->transform(<<<'CSS'
.card:has(:global(.legacy, .wp-button)) {
  color: red;
}

.item:nth-child(odd of :local(.drop, .also)) {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card:has(){color:red}.EgL3uq_item:nth-child(odd of ){color:#00f}', $emptyForgiving['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'item' => $export('EgL3uq_item'),
        ], $emptyForgiving['exports']);

        foreach ([
            '.card:not(:global(.legacy, .wp-button)) { color: red }',
            ':global(.legacy, .wp-button) .card { color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules unwraps upstream single is selector after local global rewriting while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:is(:local(.featured)) {
  color: red;
}

.card:is(:global(.wp-block-card)) {
  color: yellow;
}

.card:is([data-state="wide/layout"]) {
  border-color: blue;
}

.button {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card.EgL3uq_featured{color:red}.EgL3uq_card.wp-block-card{color:#ff0}.EgL3uq_card[data-state=wide\/layout]{border-color:#00f}.EgL3uq_button{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'featured' => $export('EgL3uq_featured'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        $guarded = (new CssModulesTransformer())->transform(<<<'CSS'
.card:is(article) {
  color: red;
}

.card:is(.wrapper .child) {
  color: yellow;
}
CSS);

        $t->same('.EgL3uq_card:is(article){color:red}.EgL3uq_card:is(.EgL3uq_wrapper .EgL3uq_child){color:#ff0}', $guarded['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'wrapper' => $export('EgL3uq_wrapper'),
            'child' => $export('EgL3uq_child'),
        ], $guarded['exports']);
    },
    'css modules canonicalizes selector-valued pseudo names while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:w\68 ere(:global(.legacy), .soft) {
  color: red;
}

.card:h\61 s(> .media, + :global(.wp-sibling)) {
  color: yellow;
}

.card:n\6f t(.disabled, :global(.is-preview)) {
  color: blue;
}

.card:-WEBKIT-ANY(:local(.wide)) {
  color: green;
}

.button {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card:where(.legacy,.EgL3uq_soft){color:red}.EgL3uq_card:has(>.EgL3uq_media,+.wp-sibling){color:#ff0}.EgL3uq_card:not(.EgL3uq_disabled,.is-preview){color:#00f}.EgL3uq_card:-webkit-any(.EgL3uq_wide){color:green}.EgL3uq_button{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'soft' => $export('EgL3uq_soft'),
            'media' => $export('EgL3uq_media'),
            'disabled' => $export('EgL3uq_disabled'),
            'wide' => $export('EgL3uq_wide'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform('.card:n\6f t(:global(.legacy, .wide), .kept) { color: red }'));
    },
    'css modules canonicalizes language direction pseudos and rejects local global args while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:D\49 R(ltr) {
  color: red;
}

.card:l\61 ng(en, fr) {
  color: yellow;
}

.button {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card:dir(ltr){color:red}.EgL3uq_card:lang(en,fr){color:#ff0}.EgL3uq_button{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        foreach ([
            '.card:dir(:global(ltr)) { color: red } .button { composes: card; color: white }',
            '.card:dir(:local(ltr)) { color: red } .button { composes: card; color: white }',
            '.card:lang(:global(en)) { color: red } .button { composes: card; color: white }',
            '.card:l\61 ng(en, :local(fr)) { color: red } .button { composes: card; color: white }',
        ] as $css) {
            try {
                (new CssModulesTransformer())->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('Unexpected token Colon', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream language selector function exception');
        }
    },
    'css modules canonicalizes no-argument pseudos while preserving local global composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:global(.wp-block:LOCAL-LINK) .card:READ-ONLY {
  color: red;
}

.card:l\6f cal-link,
.card:-WEBKIT-any-link,
.card:-moz-placeholder {
  color: yellow;
}

.button {
  composes: card;
  color: blue;
}
CSS);

        $t->same('.wp-block:local-link .EgL3uq_card:read-only{color:red}.EgL3uq_card:local-link,.EgL3uq_card:-webkit-any-link,.EgL3uq_card:-moz-placeholder-shown{color:#ff0}.EgL3uq_button{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_card', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        $replaced = (new CssModulesTransformer())->transform(<<<'CSS'
.card:h\6f ver {
  color: red;
}

.button {
  composes: card;
  color: blue;
}
CSS, [
            'pseudoClasses' => [
                'hover' => 'is-hovered',
            ],
        ]);

        $t->same('.EgL3uq_card.EgL3uq_is-hovered{color:red}.EgL3uq_button{color:#00f}', $replaced['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'is-hovered' => $export('EgL3uq_is-hovered'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $replaced['exports']);
        $t->same([], $replaced['references']);
    },
    'css modules leaves upstream host-context arguments public while preserving local composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:host-context(.public-theme) .card {
  color: red;
}

:host(.editor-theme) .cardHost {
  color: yellow;
}

::slotted(.media) {
  color: blue;
}

.card {
  composes: base;
  color: green;
}

.base {
  color: white;
}
CSS);

        $t->same(':host-context(.public-theme) .EgL3uq_card{color:red}:host(.EgL3uq_editor-theme) .EgL3uq_cardHost{color:#ff0}::slotted(.EgL3uq_media){color:#00f}.EgL3uq_card{color:green}.EgL3uq_base{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'editor-theme' => $export('EgL3uq_editor-theme'),
            'cardHost' => $export('EgL3uq_cardHost'),
            'media' => $export('EgL3uq_media'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $pure = (new CssModulesTransformer())->transform(':host-context(.public-theme) .card { color: red }', ['pure' => true]);
        $t->same(':host-context(.public-theme) .EgL3uq_card{color:red}', $pure['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $pure['exports']);
        $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform(':host-context(.public-theme) { color: red }', ['pure' => true]));
    },
    'css modules validates upstream host and slotted compound selector arguments while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:host(:global(.wp-block).card) .button {
  color: red;
}

::slotted(:local(.media).thumb) {
  color: yellow;
}

.button {
  composes: base;
  color: white;
}

.base {
  color: blue;
}
CSS);

        $t->same(':host(.wp-block.EgL3uq_card) .EgL3uq_button{color:red}::slotted(.EgL3uq_media.EgL3uq_thumb){color:#ff0}.EgL3uq_button{color:#fff}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_base')]),
            'media' => $export('EgL3uq_media'),
            'thumb' => $export('EgL3uq_thumb'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_button EgL3uq_base', CssModulesTransformer::exportClassList($result['exports'], 'button'));

        $descendantViaModePseudo = (new CssModulesTransformer())->transform(<<<'CSS'
:host(:global(.wp-block .is-selected)) .button {
  color: red;
}

::slotted(:local(.media .thumb)) {
  color: yellow;
}
CSS);

        $t->same(':host(.wp-block .is-selected) .EgL3uq_button{color:red}::slotted(.EgL3uq_media .EgL3uq_thumb){color:#ff0}', $descendantViaModePseudo['code']);
        $t->same([
            'button' => $export('EgL3uq_button'),
            'media' => $export('EgL3uq_media'),
            'thumb' => $export('EgL3uq_thumb'),
        ], $descendantViaModePseudo['exports']);

        $invalid = [
            ':host() { color: red }' => 'Invalid empty selector',
            '::slotted() { color: red }' => 'Invalid empty selector',
            ':host(.card, .legacy) { color: red }' => 'Unexpected token Comma',
            '::slotted(.media, .thumb) { color: red }' => 'Unexpected token Comma',
            ':host(.card .legacy) { color: red }' => 'Invalid state',
            '::slotted(.media > .thumb) { color: red }' => 'Invalid state',
            ':host(.card||.legacy) { color: red }' => "Unexpected token Delim('|')",
        ];

        foreach ($invalid as $css => $expectedMessage) {
            try {
                (new CssModulesTransformer())->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same($expectedMessage, $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream host/slotted compound selector exception');
        }
    },
    'css modules preserves raw host-context local global descendant selectors while composing exports' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
:host-context(.public-theme :global(.legacy-scope)) .card {
  color: red;
}

:host-context(.public-theme :local(.legacy-local)) .card {
  color: yellow;
}

.card {
  composes: base;
  color: green;
}

.base {
  color: white;
}
CSS);

        $t->same(':host-context(.public-theme :global(.legacy-scope)) .EgL3uq_card{color:red}:host-context(.public-theme :local(.legacy-local)) .EgL3uq_card{color:#ff0}.EgL3uq_card{color:green}.EgL3uq_base{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_card EgL3uq_base', CssModulesTransformer::exportClassList($result['exports'], 'card'));
    },
    'css modules scopes upstream pseudo replacement classes while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:hover {
  color: red;
}

.card:active {
  color: yellow;
}

.card:focus {
  color: blue;
}

:global(.wp-block-button:hover) .card:focus-visible {
  color: purple;
}

.card:focus-within {
  background: white;
}

.button {
  composes: card;
  color: green;
}
CSS, [
            'pseudoClasses' => [
                'hover' => 'is-hovered',
                'active' => 'is-active',
                'focus' => 'is-focused',
                'focusVisible' => 'focus-visible',
                'focusWithin' => 'has-focus-within',
            ],
        ]);

        $t->same('.EgL3uq_card.EgL3uq_is-hovered{color:red}.EgL3uq_card.EgL3uq_is-active{color:#ff0}.EgL3uq_card.EgL3uq_is-focused{color:#00f}.wp-block-button.is-hovered .EgL3uq_card.EgL3uq_focus-visible{color:purple}.EgL3uq_card.EgL3uq_has-focus-within{background:#fff}.EgL3uq_button{color:green}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'is-hovered' => $export('EgL3uq_is-hovered'),
            'is-active' => $export('EgL3uq_is-active'),
            'is-focused' => $export('EgL3uq_is-focused'),
            'focus-visible' => $export('EgL3uq_focus-visible'),
            'has-focus-within' => $export('EgL3uq_has-focus-within'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);

        $snakeCase = (new CssModulesTransformer())->transform('.foo:focus-visible, .foo:focus-within { color: red }', [
            'pseudo_classes' => [
                'focus_visible' => 'is-visible',
                'focus_within' => 'is-within',
            ],
        ]);

        $t->same('.EgL3uq_foo.EgL3uq_is-visible,.EgL3uq_foo.EgL3uq_is-within{color:red}', $snakeCase['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
            'is-visible' => $export('EgL3uq_is-visible'),
            'is-within' => $export('EgL3uq_is-within'),
        ], $snakeCase['exports']);
    },
    'css modules scopes escaped local selectors and composes idents' => static function (TestRunner $t) use ($export, $local, $global): void {
        $css = <<<'CSS'
.sm\:m-1 {
  composes: base\:one;
  color: red;
}

.hex\3a utility {
  composes: base\3a one;
  color: yellow;
}

.base\:one {
  color: blue;
}

.foo\@bar {
  composes: base\:one other from global;
  background: white;
}

:global(.wp\:block) .foo\@bar {
  border-color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_sm\:m-1{color:red}.EgL3uq_hex\:utility{color:#ff0}.EgL3uq_base\:one{color:#00f}.EgL3uq_foo\@bar{background:#fff}.wp\:block .EgL3uq_foo\@bar{border-color:red}', $result['code']);
        $t->same([
            'sm:m-1' => $export('EgL3uq_sm:m-1', [$local('EgL3uq_base:one')]),
            'hex:utility' => $export('EgL3uq_hex:utility', [$local('EgL3uq_base:one')]),
            'base:one' => $export('EgL3uq_base:one'),
            'foo@bar' => $export('EgL3uq_foo@bar', [$global('base:one'), $global('other')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules keeps escaped selector delimiters inside local global and composes selectors' => static function (TestRunner $t) use ($export, $local): void {
        $localResult = (new CssModulesTransformer())->transform(':local(.foo\,bar) { color: red }');
        $t->same('.EgL3uq_foo\,bar{color:red}', $localResult['code']);
        $t->same([
            'foo,bar' => $export('EgL3uq_foo,bar'),
        ], $localResult['exports']);
        $t->same([], $localResult['references']);

        $globalResult = (new CssModulesTransformer())->transform(':global(.wp\,button) .card { color: red }');
        $t->same('.wp\,button .EgL3uq_card{color:red}', $globalResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $globalResult['exports']);
        $t->same([], $globalResult['references']);

        $composeResult = (new CssModulesTransformer())->transform('.foo\,bar, .baz { composes: base; color: red } .base { color: blue }');
        $t->same('.EgL3uq_foo\,bar,.EgL3uq_baz{color:red}.EgL3uq_base{color:#00f}', $composeResult['code']);
        $t->same([
            'foo,bar' => $export('EgL3uq_foo,bar', [$local('EgL3uq_base')]),
            'baz' => $export('EgL3uq_baz', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $composeResult['exports']);
        $t->same([], $composeResult['references']);

        foreach ([
            ':global(.foo, .bar) .baz { color: red }',
            ':local(.foo, .bar) { color: red }',
            '.foo, :global(.bar) { composes: base; color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
    'css modules rejects upstream namespace delimiter class splits while preserving local global composes' => static function (TestRunner $t) use ($export, $local): void {
        $namespaceResult = (new CssModulesTransformer())->transform(<<<'CSS'
svg|a .card {
  color: red;
}

*|button .button {
  color: yellow;
}

|slot .title {
  color: blue;
}

.link {
  composes: card;
  color: white;
}

.card {
  color: green;
}
CSS);

        $t->same('svg|a .EgL3uq_card{color:red}*|button .EgL3uq_button{color:#ff0}|slot .EgL3uq_title{color:#00f}.EgL3uq_link{color:#fff}.EgL3uq_card{color:green}', $namespaceResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'button' => $export('EgL3uq_button'),
            'title' => $export('EgL3uq_title'),
            'link' => $export('EgL3uq_link', [$local('EgL3uq_card')]),
        ], $namespaceResult['exports']);
        $t->same([], $namespaceResult['references']);
        $t->same('EgL3uq_link EgL3uq_card', CssModulesTransformer::exportClassList($namespaceResult['exports'], 'link'));

        $forgivingResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card:has(.bad|.drop, .kept) {
  color: red;
}

.card:is(:local(.bad||.drop), .safe) {
  color: yellow;
}

.item:nth-child(odd of .bad|.drop, .row) {
  color: blue;
}

.panel {
  composes: card;
  color: white;
}
CSS);

        $t->same('.EgL3uq_card:has(.EgL3uq_kept){color:red}.EgL3uq_card.EgL3uq_safe{color:#ff0}.EgL3uq_item:nth-child(odd of .EgL3uq_row){color:#00f}.EgL3uq_panel{color:#fff}', $forgivingResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'kept' => $export('EgL3uq_kept'),
            'safe' => $export('EgL3uq_safe'),
            'item' => $export('EgL3uq_item'),
            'row' => $export('EgL3uq_row'),
            'panel' => $export('EgL3uq_panel', [$local('EgL3uq_card')]),
        ], $forgivingResult['exports']);
        $t->same([], $forgivingResult['references']);

        foreach ([
            '.foo|.bar { color: red }',
            '.foo|bar { color: red }',
            '#foo|bar { color: red }',
            'foo|.bar { color: red }',
            ':local(.foo|.bar) { color: red }',
            ':global(.foo|.bar) .card { color: red }',
            ':local(.foo||.bar) { color: red }',
            ':global(.foo) || .card { color: red }',
            '.card:not(.foo|.bar, .ok) { color: red }',
        ] as $css) {
            try {
                (new CssModulesTransformer())->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same("Unexpected token Delim('|')", $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected upstream namespace delimiter exception');
        }
    },
    'css modules preserves escaped numeric local selectors while applying composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.\31 23, .alpha {
  composes: \31 23-base;
  color: red;
}

:global(.\31 23) .alpha {
  border-color: yellow;
}

.\31 23-base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_123,.EgL3uq_alpha{color:red}.\31 23 .EgL3uq_alpha{border-color:#ff0}.EgL3uq_123-base{color:#00f}', $result['code']);
        $t->same([
            123 => $export('EgL3uq_123', [$local('EgL3uq_123-base')]),
            'alpha' => $export('EgL3uq_alpha', [$local('EgL3uq_123-base')]),
            '123-base' => $export('EgL3uq_123-base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_123 EgL3uq_123-base', CssModulesTransformer::exportClassList($result['exports'], '123'));
        $t->same('EgL3uq_alpha EgL3uq_123-base', CssModulesTransformer::exportClassList($result['exports'], 'alpha'));
    },
    'css modules serializes upstream attribute selectors inside local global and composed selectors' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card[data-state="wide/layout"] {
  color: red;
}

:global(.wp-block[data-kind="core/button"]) .card:is([data-tone="a.b"], .featured) {
  color: yellow;
}

.card[class~="is-wide"] {
  background: blue;
}

.card[data-space="bar baz"] {
  margin: 1px;
}

.card[data-label="Hello, world!"] {
  border-color: currentColor;
}

.button {
  composes: base;
  background: blue;
}

.base {
  color: green;
}
CSS);

        $t->same('.EgL3uq_card[data-state=wide\/layout]{color:red}.wp-block[data-kind=core\/button] .EgL3uq_card:is([data-tone=a\.b],.EgL3uq_featured){color:#ff0}.EgL3uq_card[class~=is-wide]{background:#00f}.EgL3uq_card[data-space=bar\ baz]{margin:1px}.EgL3uq_card[data-label="Hello, world!"]{border-color:currentColor}.EgL3uq_button{background:#00f}.EgL3uq_base{color:green}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'featured' => $export('EgL3uq_featured'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform('.card[data-state=.wide] { color: red }'));

        $unminified = (new CssModulesTransformer())->transform('.card[data-state=".wide"] { color: red }', [
            'minify' => false,
        ]);
        $t->same('.EgL3uq_card[data-state=".wide"]{ color: red }', $unminified['code']);
    },
    'css modules pure mode enforces upstream local selector boundaries' => static function (TestRunner $t) use ($export, $local): void {
        $transformer = new CssModulesTransformer();

        $passing = [
            ':local(.foo) { width: 20px }' => '.EgL3uq_foo{width:20px}',
            'div.my-class { color: red }' => 'div.EgL3uq_my-class{color:red}',
            '#id { color: red }' => '#EgL3uq_id{color:red}',
            'a .my-class { color: red }' => 'a .EgL3uq_my-class{color:red}',
            '.my-class a { color: red }' => '.EgL3uq_my-class a{color:red}',
            '.my-class:is(a) { color: red }' => '.EgL3uq_my-class:is(a){color:red}',
            'div:has(.my-class) { color: red }' => 'div:has(.EgL3uq_my-class){color:red}',
        ];

        foreach ($passing as $css => $expected) {
            $result = $transformer->transform($css, ['pure' => true]);
            $t->same($expected, $result['code']);
        }

        $noCheck = $transformer->transform('/* cssmodules-pure-no-check */ :global(.wp-block-button) { color: red }', ['pure' => true]);
        $t->same('.wp-block-button{color:red}', $noCheck['code']);
        $t->same([], $noCheck['exports']);

        $licenseNoCheck = $transformer->transform(<<<'CSS'
/*! Theme block license */
/* cssmodules-pure-no-check */ :global(.wp-block-button) {
  color: red;
}

.card {
  composes: base;
  color: yellow;
}

.base {
  color: blue;
}
CSS, [
            'pure' => true,
        ]);
        $t->same("/*! Theme block license */\n.wp-block-button{color:red}.EgL3uq_card{color:#ff0}.EgL3uq_base{color:#00f}", $licenseNoCheck['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $licenseNoCheck['exports']);

        $localResult = $transformer->transform('div:has(.my-class) { color: red }', ['pure' => true]);
        $t->same([
            'my-class' => $export('EgL3uq_my-class'),
        ], $localResult['exports']);
    },
    'css modules pure mode rejects upstream impure global selectors' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            'div { width: 20px }',
            ':global(.foo) { width: 20px }',
            '[foo=bar] { width: 20px }',
            'div, .foo { width: 20px }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css, ['pure' => true]));
        }
    },
    'css modules removes local composes declarations and exports composed local class' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.test {
  composes: foo;
  background: white;
}

.foo {
  color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo')]),
            'foo' => $export('EgL3uq_foo'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules applies local composes to each selector list export' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.a, .b {
  composes: foo;
  background: white;
}

.foo {
  color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_a,.EgL3uq_b{background:#fff}.EgL3uq_foo{color:red}', $result['code']);
        $t->same([
            'a' => $export('EgL3uq_a', [$local('EgL3uq_foo')]),
            'b' => $export('EgL3uq_b', [$local('EgL3uq_foo')]),
            'foo' => $export('EgL3uq_foo'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules preserves upstream order for multiple local composes names' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.test {
  composes: foo bar;
  background: white;
}

.foo {
  color: red;
}

.bar {
  color: yellow;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}.EgL3uq_bar{color:#ff0}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo'), $local('EgL3uq_bar')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records single global composes reference without localizing it' => static function (TestRunner $t) use ($export, $global): void {
        $css = <<<'CSS'
.test {
  composes: foo from global;
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records multiple global composes references in source order' => static function (TestRunner $t) use ($export, $global): void {
        $css = <<<'CSS'
.test {
  composes: foo bar from global;
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo'), $global('bar')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records dependency composes reference without rewriting dependency class' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo from "foo.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('foo', 'foo.css')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records multiple dependency composes references in source order' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo bar from "foo.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('foo', 'foo.css'), $dependency('bar', 'foo.css')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules preserves upstream empty rules for composes-only selectors' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $localResult = (new CssModulesTransformer())->transform(<<<'CSS'
.foo {
  composes: bar;
}

.bar {
  color: red;
}
CSS);

        $t->same('.EgL3uq_foo{}.EgL3uq_bar{color:red}', $localResult['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$local('EgL3uq_bar')]),
            'bar' => $export('EgL3uq_bar'),
        ], $localResult['exports']);
        $t->same([], $localResult['references']);

        $globalResult = (new CssModulesTransformer())->transform(<<<'CSS'
.foo {
  composes: bar from global;
}

.bar {
  color: red;
}
CSS);

        $t->same('.EgL3uq_foo{}.EgL3uq_bar{color:red}', $globalResult['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$global('bar')]),
            'bar' => $export('EgL3uq_bar'),
        ], $globalResult['exports']);
        $t->same([], $globalResult['references']);

        $dependencyResult = (new CssModulesTransformer())->transform(<<<'CSS'
.foo {
  composes: bar from "bar.css";
}
CSS);

        $t->same('.EgL3uq_foo{}', $dependencyResult['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$dependency('bar', 'bar.css')]),
        ], $dependencyResult['exports']);
        $t->same([], $dependencyResult['references']);

        $selectorListResult = (new CssModulesTransformer())->transform(<<<'CSS'
.foo, .baz {
  composes: bar;
}

.bar {
  color: red;
}
CSS);

        $t->same('.EgL3uq_foo,.EgL3uq_baz{}.EgL3uq_bar{color:red}', $selectorListResult['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$local('EgL3uq_bar')]),
            'baz' => $export('EgL3uq_baz', [$local('EgL3uq_bar')]),
            'bar' => $export('EgL3uq_bar'),
        ], $selectorListResult['exports']);
        $t->same([], $selectorListResult['references']);

        $unminified = (new CssModulesTransformer())->transform('.foo { composes: bar }', [
            'minify' => false,
        ]);

        $t->same('.EgL3uq_foo{}', $unminified['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$local('EgL3uq_bar')]),
        ], $unminified['exports']);
        $t->same([], $unminified['references']);
    },
    'css modules drops upstream empty declarations around removed composes' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  ;
  composes: base;
  ;
  color: red;
  ;;
  composes: wp-block-card from global;
  composes: token from "./tokens.css";
  ;
}

.base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [
                $local('EgL3uq_base'),
                $global('wp-block-card'),
                $dependency('token', './tokens.css'),
            ]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_card EgL3uq_base wp-block-card Theme_token', CssModulesTransformer::exportClassList(
            $result['exports'],
            'card',
            static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './tokens.css'
                ? 'Theme_token'
                : null
        ));
    },
    'css modules accepts upstream important priority on composes declarations' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $localResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo ! important;
  background: white;
}

.foo {
  color: red;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}', $localResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo')]),
            'foo' => $export('EgL3uq_foo'),
        ], $localResult['exports']);
        $t->same([], $localResult['references']);

        $globalResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo from global !IMPORTANT;
  background: white;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}', $globalResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo')]),
        ], $globalResult['exports']);
        $t->same([], $globalResult['references']);

        $dependencyResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo from "./foo.css"!important;
  background: white;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}', $dependencyResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('foo', './foo.css')]),
        ], $dependencyResult['exports']);
        $t->same([], $dependencyResult['references']);

        $escapedResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo\!important;
  background: white;
}

.foo\!important {
  color: green;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo\!important{color:green}', $escapedResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo!important')]),
            'foo!important' => $export('EgL3uq_foo!important'),
        ], $escapedResult['exports']);
        $t->same([], $escapedResult['references']);
    },
    'css modules decodes escaped composes property names before local global dependency exports' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  c\6f mposes: base;
  c\6f mposes: wp-block-card from g\6c obal;
  C\6f MPOSES: token from "./tokens.css";
  color: red;
}

.base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [
                $local('EgL3uq_base'),
                $global('wp-block-card'),
                $dependency('token', './tokens.css'),
            ]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_card EgL3uq_base wp-block-card Theme_token', CssModulesTransformer::exportClassList(
            $result['exports'],
            'card',
            static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './tokens.css'
                ? 'Theme_token'
                : null
        ));
    },
    'css modules treats comments as upstream token separators inside composes values' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $localResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo/* comment */bar;
  background: white;
}

.foo {
  color: red;
}

.bar {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}.EgL3uq_bar{color:#00f}', $localResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo'), $local('EgL3uq_bar')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
        ], $localResult['exports']);
        $t->same([], $localResult['references']);

        $globalResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo/* comment */from/* comment */global;
  background: white;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}', $globalResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo')]),
        ], $globalResult['exports']);
        $t->same([], $globalResult['references']);

        $dependencyResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo/* comment */bar/* comment */from/* comment */"./tokens.css";
  background: white;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}', $dependencyResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $dependency('foo', './tokens.css'),
                $dependency('bar', './tokens.css'),
            ]),
        ], $dependencyResult['exports']);
        $t->same([], $dependencyResult['references']);
    },
    'css modules treats comments as token separators after escaped composes properties' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $localResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  c\6f mposes: base/* comment */tone;
  color: red;
}

.base {
  color: blue;
}

.tone {
  color: green;
}
CSS);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_base{color:#00f}.EgL3uq_tone{color:green}', $localResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base'), $local('EgL3uq_tone')]),
            'base' => $export('EgL3uq_base'),
            'tone' => $export('EgL3uq_tone'),
        ], $localResult['exports']);
        $t->same([], $localResult['references']);

        $globalResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  c\6f mposes: wp-block/* comment */is-wide from g\6c obal;
  color: red;
}
CSS);

        $t->same('.EgL3uq_card{color:red}', $globalResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$global('wp-block'), $global('is-wide')]),
        ], $globalResult['exports']);
        $t->same([], $globalResult['references']);

        $dependencyResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  C\6f MPOSES: token/* comment */shadow from "./tokens.css";
  color: red;
}
CSS);

        $t->same('.EgL3uq_card{color:red}', $dependencyResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [
                $dependency('token', './tokens.css'),
                $dependency('shadow', './tokens.css'),
            ]),
        ], $dependencyResult['exports']);
        $t->same([], $dependencyResult['references']);
    },
    'css modules does not join commented composes property name fragments' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $invalidResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  comp/* property token separator */oses: base;
  c\6f/* escaped property token separator */mposes: utility from global;
  C/* uppercase property token separator */OMPOSES: token from "./tokens.css";
  color: red;
}

.base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_base{color:#00f}', $invalidResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'base' => $export('EgL3uq_base'),
        ], $invalidResult['exports']);
        $t->same([], $invalidResult['references']);

        $validResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes/* property comment before colon */: base;
  c\6f mposes/* escaped property comment before colon */: utility from global;
  C\6f MPOSES/* dependency property comment before colon */: token from "./tokens.css";
  color: red;
}

.base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_base{color:#00f}', $validResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [
                $local('EgL3uq_base'),
                $global('utility'),
                $dependency('token', './tokens.css'),
            ]),
            'base' => $export('EgL3uq_base'),
        ], $validResult['exports']);
        $t->same([], $validResult['references']);
        $t->same('EgL3uq_card EgL3uq_base utility Theme_token', CssModulesTransformer::exportClassList(
            $validResult['exports'],
            'card',
            static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './tokens.css'
                ? 'Theme_token'
                : null
        ));
    },
    'css modules decodes escaped dependency specifiers in composes metadata' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo from "./theme\ components.css";
  composes: bar from "./theme\000020components.css";
  composes: icon from "./icons\2f arrow.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $dependency('foo', './theme components.css'),
                $dependency('bar', './theme components.css'),
                $dependency('icon', './icons/arrow.css'),
            ]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules parses upstream composes from delimiters strictly' => static function (TestRunner $t) use ($export, $local, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: global none;
  composes: foo from './foo bar.css';
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $local('EgL3uq_global'),
                $local('EgL3uq_none'),
                $dependency('foo', './foo bar.css'),
            ]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules preserves invalid upstream composes declarations without export references' => static function (TestRunner $t) use ($export): void {
        $cases = [
            '.test { composes: from global; color: red }' => '.EgL3uq_test{composes:from global;color:red}',
            '.test { composes: foo from; color: red }' => '.EgL3uq_test{composes:foo from;color:red}',
            '.test { composes: foo from bar; color: red }' => '.EgL3uq_test{composes:foo from bar;color:red}',
            '.test { composes: foo from global bar; color: red }' => '.EgL3uq_test{composes:foo from global bar;color:red}',
            '.test { composes: foo from "foo.css" bar; color: red }' => '.EgL3uq_test{composes:foo from "foo.css" bar;color:red}',
            '.test { composes: initial; color: red }' => '.EgL3uq_test{composes:initial;color:red}',
            '.test { composes: revert-layer; color: red }' => '.EgL3uq_test{composes:revert-layer;color:red}',
            '.test { composes: "foo"; color: red }' => '.EgL3uq_test{composes:"foo";color:red}',
            '.test { composes: foo url(bar); color: red }' => '.EgL3uq_test{composes:foo url(bar);color:red}',
        ];

        foreach ($cases as $css => $expectedCode) {
            $result = (new CssModulesTransformer())->transform($css);
            $t->same($expectedCode, $result['code']);
            $t->same([
                'test' => $export('EgL3uq_test'),
            ], $result['exports']);
            $t->same([], $result['references']);
        }
    },
    'css modules canonicalizes escaped idents in invalid composes fallback declarations' => static function (TestRunner $t) use ($export): void {
        $escapedKeyword = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo \66 rom;
  color: red;
}

.foo {
  color: blue;
}

.\66 rom {
  color: green;
}
CSS);

        $t->same('.EgL3uq_test{composes:foo from;color:red}.EgL3uq_foo{color:#00f}.EgL3uq_from{color:green}', $escapedKeyword['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
            'foo' => $export('EgL3uq_foo'),
            'from' => $export('EgL3uq_from'),
        ], $escapedKeyword['exports']);
        $t->same([], $escapedKeyword['references']);

        $escapedProperty = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  c\6f mposes: foo \66 rom;
  color: red;
}

.foo {
  color: blue;
}

.\66 rom {
  color: green;
}
CSS);

        $t->same('.EgL3uq_test{composes:foo from;color:red}.EgL3uq_foo{color:#00f}.EgL3uq_from{color:green}', $escapedProperty['code']);
        $t->same($escapedKeyword['exports'], $escapedProperty['exports']);
        $t->same([], $escapedProperty['references']);

        $escapedFunction = (new CssModulesTransformer())->transform('.test { composes: foo \75rl(bar); color: red }');
        $t->same('.EgL3uq_test{composes:foo url(bar);color:red}', $escapedFunction['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $escapedFunction['exports']);
        $t->same([], $escapedFunction['references']);
    },
    'css modules rejects upstream deprecated value rules before composing exports' => static function (TestRunner $t): void {
        foreach ([
            '@value compact: (max-width: 37.4375em);',
            '/* migrated CSS Modules alias */ @value compact: (max-width: 37.4375em);',
            '.card { composes: base; color: red } @value compact: (max-width:37em); .base { color: blue }',
            '@media (min-width: 1px) { @value compact: (min-width: 1px); .card { composes: base; color: red } }',
            '@value compact { .card { color: red } }',
        ] as $css) {
            try {
                (new CssModulesTransformer())->transform($css);
            } catch (InvalidArgumentException $exception) {
                $t->same('The @value rule is deprecated', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected deprecated CSS Modules @value exception');
        }
    },
    'css modules merges repeated composes declarations across local and dependency references' => static function (TestRunner $t) use ($export, $local, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo;
  composes: foo from "foo.css";
  composes: bar from "bar.css";
  background: white;
}

.foo {
  color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $local('EgL3uq_foo'),
                $dependency('foo', 'foo.css'),
                $dependency('bar', 'bar.css'),
            ]),
            'foo' => $export('EgL3uq_foo'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules flattens upstream local global and dependency compose class lists' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $css = <<<'CSS'
.button {
  composes: reset;
  composes: wp-block-button from global;
  composes: tone shadow from "./theme.css";
  color: red;
}

.reset {
  color: blue;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);
        $resolver = static fn (string $name, string $specifier) => $specifier === './theme.css'
            ? [
                'tone' => 'Theme_tone',
                'shadow' => 'Theme_shadow Theme_depth',
            ][$name] ?? null
            : null;

        $t->same('.EgL3uq_button{color:red}.EgL3uq_reset{color:#00f}', $result['code']);
        $t->same([
            'button' => $export('EgL3uq_button', [
                $local('EgL3uq_reset'),
                $global('wp-block-button'),
                $dependency('tone', './theme.css'),
                $dependency('shadow', './theme.css'),
            ]),
            'reset' => $export('EgL3uq_reset'),
        ], $result['exports']);
        $t->same('EgL3uq_button EgL3uq_reset wp-block-button Theme_tone Theme_shadow Theme_depth', CssModulesTransformer::exportClassList($result['exports'], 'button', $resolver));
        $t->same('EgL3uq_reset', CssModulesTransformer::exportClassList($result['exports'], 'reset'));
        $t->same(null, CssModulesTransformer::exportClassList($result['exports'], 'missing'));
        $t->same([
            'button' => 'EgL3uq_button EgL3uq_reset wp-block-button Theme_tone Theme_shadow Theme_depth',
            'reset' => 'EgL3uq_reset',
        ], CssModulesTransformer::exportClassLists($result['exports'], $resolver));
        $t->throws(InvalidArgumentException::class, static fn () => CssModulesTransformer::exportClassList($result['exports'], 'button'));
    },
    'css modules export class lists flatten transitive local global and dependency composes' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.button {
  composes: card;
  color: red;
}

.card {
  composes: reset;
  composes: wp-block-card from global;
  color: blue;
}

.reset {
  composes: token from "./tokens.css";
  color: green;
}
CSS);
        $resolver = static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './tokens.css'
            ? 'Theme_token Theme_depth'
            : null;

        $t->same('.EgL3uq_button{color:red}.EgL3uq_card{color:#00f}.EgL3uq_reset{color:green}', $result['code']);
        $t->same([
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
            'card' => $export('EgL3uq_card', [
                $local('EgL3uq_reset'),
                $global('wp-block-card'),
            ]),
            'reset' => $export('EgL3uq_reset', [$dependency('token', './tokens.css')]),
        ], $result['exports']);
        $t->same('EgL3uq_button EgL3uq_card EgL3uq_reset Theme_token Theme_depth wp-block-card', CssModulesTransformer::exportClassList($result['exports'], 'button', $resolver));
        $t->same('EgL3uq_card EgL3uq_reset Theme_token Theme_depth wp-block-card', CssModulesTransformer::exportClassList($result['exports'], 'card', $resolver));
        $t->same([
            'button' => 'EgL3uq_button EgL3uq_card EgL3uq_reset Theme_token Theme_depth wp-block-card',
            'card' => 'EgL3uq_card EgL3uq_reset Theme_token Theme_depth wp-block-card',
            'reset' => 'EgL3uq_reset Theme_token Theme_depth',
        ], CssModulesTransformer::exportClassLists($result['exports'], $resolver));
    },
    'css modules maps upstream hash and content-hash patterns through composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $patterned = (new CssModulesTransformer())->transform('.foo { color: red }', [
            'pattern' => 'test-[hash]-[local]',
        ]);

        $t->same('.test-EgL3uq-foo{color:red}', $patterned['code']);
        $t->same([
            'foo' => $export('test-EgL3uq-foo'),
        ], $patterned['exports']);

        $projectRootSameFile = (new CssModulesTransformer())->transform('.foo { color: red }', [
            'filename' => '/foo/bar/test.css',
            'projectRoot' => '/foo/bar',
        ]);

        $t->same('.EgL3uq_foo{color:red}', $projectRootSameFile['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
        ], $projectRootSameFile['exports']);

        $projectRoot = (new CssModulesTransformer())->transform('.foo { color: red }', [
            'filename' => '/foo/bar/baz/test.css',
            'projectRoot' => '/foo/bar',
        ]);

        $t->same('.xLEkNW_foo{color:red}', $projectRoot['code']);
        $t->same([
            'foo' => $export('xLEkNW_foo'),
        ], $projectRoot['exports']);

        $snakeCaseProjectRoot = (new CssModulesTransformer())->transform(<<<'CSS'
.button {
  composes: base;
  composes: utility from global;
  color: red;
}

.base {
  color: blue;
}
CSS, [
            'filename' => '/sites/a/theme/blocks/card.module.css',
            'project_root' => '/sites/a/theme',
            'pattern' => '[name]__[hash]__[local]',
        ]);

        $t->same('.card-module__VKU3mq__button{color:red}.card-module__VKU3mq__base{color:#00f}', $snakeCaseProjectRoot['code']);
        $t->same([
            'button' => $export('card-module__VKU3mq__button', [
                ['type' => 'local', 'name' => 'card-module__VKU3mq__base'],
                ['type' => 'global', 'name' => 'utility'],
            ]),
            'base' => $export('card-module__VKU3mq__base'),
        ], $snakeCaseProjectRoot['exports']);
        $t->same('card-module__VKU3mq__button card-module__VKU3mq__base utility', CssModulesTransformer::exportClassList($snakeCaseProjectRoot['exports'], 'button'));

        $contentSource = "\n      .test {\n        composes: foo bar from \"foo.css\";\n        background: white;\n      }\n    ";
        $contentHash = (new CssModulesTransformer())->transform($contentSource, [
            'pattern' => '[content-hash]-[local]',
        ]);

        $t->same('._5h2kwG-test{background:#fff}', $contentHash['code']);
        $t->same([
            'test' => $export('_5h2kwG-test', [
                $dependency('foo', 'foo.css'),
                $dependency('bar', 'foo.css'),
            ]),
        ], $contentHash['exports']);
        $t->same([], $contentHash['references']);
    },
    'css modules rejects upstream invalid patterns before local global and composes output' => static function (TestRunner $t): void {
        $cases = [
            [
                '.test { composes: foo; color: red } .foo { color: blue }',
                '[oops]-[local]',
                'Error parsing CSS modules pattern: unknown placeholder "[oops]" at index 0',
            ],
            [
                ':local(.test) { color: red }',
                'theme-[oops]-[local]',
                'Error parsing CSS modules pattern: unknown placeholder "[oops]" at index 6',
            ],
            [
                ':global(.legacy) .test { color: red }',
                '[hash',
                'Error parsing CSS modules pattern: unclosed brackets at index 0',
            ],
            [
                '.test { composes: foo from global; color: red }',
                'theme-[name]-[bad]',
                'Error parsing CSS modules pattern: unknown placeholder "[bad]" at index 13',
            ],
        ];

        foreach ($cases as [$css, $pattern, $message]) {
            try {
                (new CssModulesTransformer())->transform($css, [
                    'filename' => '/theme/card.module.css',
                    'pattern' => $pattern,
                ]);
            } catch (InvalidArgumentException $exception) {
                $t->same($message, $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected invalid CSS Modules pattern exception');
        }
    },
    'css modules deduplicates repeated composes references from simple class selectors' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo;
  composes: foo;
  composes: foo from global;
  composes: foo from global;
  composes: bar from "bar.css";
  composes: bar from "bar.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $local('EgL3uq_foo'),
                $global('foo'),
                $dependency('bar', 'bar.css'),
            ]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules rejects composes from functional local selectors like upstream' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            ':local(.test) { composes: foo; color: red }',
            ':local(.test), .fallback { composes: foo; color: red }',
            '@media (min-width: 1px) { :local(.test) { composes: foo; color: red } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css));
        }
    },
    'css modules rejects composes outside simple local class selectors' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.ancestor .test { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.test:hover { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.test.foo { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('#test { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(':global(.test) { composes: foo; color: red }'));
    },
    'css modules rejects composes inside nested local rules' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.foo { .bar { composes: baz; color: red } }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.foo { @media (min-width: 1px) { .bar { composes: baz; color: red } } }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.foo { @media (min-width: 1px) { composes: baz; color: red } }'));
    },
    'css modules rejects composes inside top-level at-rule blocks like upstream' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            '@media (min-width: 1px) { .foo { composes: bar; color: red } .bar { color: blue } }',
            '@supports (display: grid) { .foo { composes: bar; color: red } .bar { color: blue } }',
            '@layer theme { .foo { composes: bar; color: red } .bar { color: blue } }',
            '@container (min-width: 1px) { .foo { composes: bar; color: red } .bar { color: blue } }',
            '@scope (.root) { .foo { composes: bar; color: red } .bar { color: blue } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css));
        }
    },
    'css modules still scopes top-level at-rule blocks without composes' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
@media (min-width: 1px) {
  :global(.legacy) .foo {
    color: red;
  }
}

@supports (display: grid) {
  .bar {
    color: blue;
  }
}

@layer theme {
  .baz {
    color: yellow;
  }
}

@container (min-width: 1px) {
  .wide {
    color: green;
  }
}

@scope (.root) to (:global(.stop)) {
  .scoped {
    color: purple;
  }
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('@media (width>=1px){.legacy .EgL3uq_foo{color:red}}@supports (display:grid){.EgL3uq_bar{color:#00f}}@layer theme{.EgL3uq_baz{color:#ff0}}@container (width>=1px){.EgL3uq_wide{color:green}}@scope(.EgL3uq_root) to (.stop){:scope .EgL3uq_scoped{color:purple}}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
            'baz' => $export('EgL3uq_baz'),
            'wide' => $export('EgL3uq_wide'),
            'root' => $export('EgL3uq_root'),
            'scoped' => $export('EgL3uq_scoped'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules keeps parent composes exports while lowering nested local selectors' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.foo {
  color: red;

  .bar {
    color: green;
  }

  composes: test from "foo.css";
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_foo{color:red}.EgL3uq_foo .EgL3uq_bar{color:green}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$dependency('test', 'foo.css')]),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules preserves upstream empty parent rules for composes before nested blocks' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $css = <<<'CSS'
.card {
  ;
  composes: base;
  composes: wp-block-card from global;
  composes: token from "./theme.css";
  ;

  .title {
    color: red;
  }

  @media (min-width: 1px) {
    color: blue;
  }

  color: green;
}

.late {
  .label {
    color: yellow;
  }

  composes: base;
}

.base {
  color: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_card{}.EgL3uq_card .EgL3uq_title{color:red}@media (width>=1px){.EgL3uq_card{color:#00f}}.EgL3uq_card{color:green}.EgL3uq_late .EgL3uq_label{color:#ff0}.EgL3uq_base{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [
                $local('EgL3uq_base'),
                $global('wp-block-card'),
                $dependency('token', './theme.css'),
            ]),
            'title' => $export('EgL3uq_title'),
            'late' => $export('EgL3uq_late', [$local('EgL3uq_base')]),
            'label' => $export('EgL3uq_label'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_card EgL3uq_base wp-block-card Theme_token', CssModulesTransformer::exportClassList(
            $result['exports'],
            'card',
            static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './theme.css'
                ? 'Theme_token'
                : null
        ));
        $t->same('EgL3uq_late EgL3uq_base', CssModulesTransformer::exportClassList($result['exports'], 'late'));
    },
    'css modules rewrites upstream nest preludes before lowering local global composes selectors' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.card {
  color: red;

  @nest :global(.wp-block-group) & {
    color: blue;
  }

  @nest :local(.theme) & {
    color: yellow;
  }

  @nest &:where(:global(.is-wide), .featured) {
    color: green;
  }

  composes: base;
}

.base {
  color: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_card{color:red}.wp-block-group .EgL3uq_card{color:#00f}.EgL3uq_theme .EgL3uq_card{color:#ff0}.EgL3uq_card:where(.is-wide,.EgL3uq_featured){color:green}.EgL3uq_base{color:#fff}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'theme' => $export('EgL3uq_theme'),
            'featured' => $export('EgL3uq_featured'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_card EgL3uq_base', CssModulesTransformer::exportClassList($result['exports'], 'card'));
    },
    'css modules scopes upstream animation custom idents while preserving composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  animation: rotate var(--duration) linear infinite;
  composes: token from "tokens.css";
}

@keyframes rotate {
  from { opacity: 0 }
  to { opacity: 1 }
}
CSS);

        $t->same('.EgL3uq_test{animation:EgL3uq_rotate var(--duration) linear infinite}@keyframes EgL3uq_rotate{0%{opacity:0}to{opacity:1}}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('token', 'tokens.css')]),
            'rotate' => [
                'name' => 'EgL3uq_rotate',
                'composes' => [],
                'isReferenced' => true,
            ],
        ], $result['exports']);
        $t->same([], $result['references']);

        $none = (new CssModulesTransformer())->transform('.test { animation: none var(--duration); }');
        $t->same('.EgL3uq_test{animation:none var(--duration)}', $none['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $none['exports']);

        $variable = (new CssModulesTransformer())->transform('.test { animation: var(--animation); }');
        $t->same('.EgL3uq_test{animation:var(--animation)}', $variable['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $variable['exports']);

        $disabled = (new CssModulesTransformer())->transform('.test { animation: rotate var(--duration); }', ['animation' => false]);
        $t->same('.EgL3uq_test{animation:rotate var(--duration)}', $disabled['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $disabled['exports']);

        $quoted = (new CssModulesTransformer())->transform('.test { animation: "rotate" var(--duration); }');
        $t->same('.EgL3uq_test{animation:EgL3uq_rotate var(--duration)}', $quoted['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
            'rotate' => [
                'name' => 'EgL3uq_rotate',
                'composes' => [],
                'isReferenced' => true,
            ],
        ], $quoted['exports']);
    },
    'css modules scopes upstream counter styles and list-style references with composes exports' => static function (TestRunner $t) use ($export, $referenced, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
@counter-style circles {
  symbols: A B C;
}

.list {
  list-style: circles outside;
  composes: base from "tokens.css";
}

.item {
  list-style-type: circles;
}

.builtin {
  list-style-type: disc;
}

.none {
  list-style: none;
}
CSS);

        $t->same('@counter-style EgL3uq_circles{symbols:A B C}.EgL3uq_list{list-style:EgL3uq_circles}.EgL3uq_item{list-style-type:EgL3uq_circles}.EgL3uq_builtin{list-style-type:disc}.EgL3uq_none{list-style:none}', $result['code']);
        $t->same([
            'circles' => $referenced('EgL3uq_circles'),
            'list' => $export('EgL3uq_list', [$dependency('base', 'tokens.css')]),
            'item' => $export('EgL3uq_item'),
            'builtin' => $export('EgL3uq_builtin'),
            'none' => $export('EgL3uq_none'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $customIdentsDisabled = (new CssModulesTransformer())->transform(<<<'CSS'
@counter-style circles {
  symbols: A B C;
}

.list {
  list-style-type: circles;
}
CSS, [
            'customIdents' => false,
        ]);

        $t->same('@counter-style circles{symbols:A B C}.EgL3uq_list{list-style-type:circles}', $customIdentsDisabled['code']);
        $t->same([
            'list' => $export('EgL3uq_list'),
            'circles' => $referenced('EgL3uq_circles'),
        ], $customIdentsDisabled['exports']);

        $builtInTypeWins = (new CssModulesTransformer())->transform(<<<'CSS'
@counter-style circles {
  symbols: A B C;
}

.list {
  list-style: square circles;
}
CSS);

        $t->same('@counter-style EgL3uq_circles{symbols:A B C}.EgL3uq_list{list-style:square circles}', $builtInTypeWins['code']);
        $t->same([
            'circles' => $export('EgL3uq_circles'),
            'list' => $export('EgL3uq_list'),
        ], $builtInTypeWins['exports']);
    },
    'css modules scopes upstream grid template names' => static function (TestRunner $t) use ($export): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
body {
  grid: [header-top] "a a a" [header-bottom]
        [main-top] "b b b" 1fr [main-bottom]
        / auto 1fr auto;
}

header {
  grid-area: a;
}

main {
  grid-row: main-top / main-bottom;
}
CSS);

        $t->same('body{grid:[EgL3uq_header-top]"EgL3uq_a EgL3uq_a EgL3uq_a"[EgL3uq_header-bottom EgL3uq_main-top]"EgL3uq_b EgL3uq_b EgL3uq_b"1fr[EgL3uq_main-bottom]/auto 1fr auto}header{grid-area:EgL3uq_a}main{grid-row:EgL3uq_main-top/EgL3uq_main-bottom}', $result['code']);
        $t->same([
            'header-top' => $export('EgL3uq_header-top'),
            'a' => $export('EgL3uq_a'),
            'header-bottom' => $export('EgL3uq_header-bottom'),
            'main-top' => $export('EgL3uq_main-top'),
            'b' => $export('EgL3uq_b'),
            'main-bottom' => $export('EgL3uq_main-bottom'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream grid areas while preserving composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.grid {
  composes: utility from "tokens.css";
  grid-template-areas: "foo";
}

.foo {
  grid-area: foo;
}

.bar {
  grid-column-start: foo-start;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_grid{grid-template-areas:"EgL3uq_foo"}.EgL3uq_foo{grid-area:EgL3uq_foo}.EgL3uq_bar{grid-column-start:EgL3uq_foo-start}', $result['code']);
        $t->same([
            'grid' => $export('EgL3uq_grid', [$dependency('utility', 'tokens.css')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
            'foo-start' => $export('EgL3uq_foo-start'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $disabled = (new CssModulesTransformer())->transform($css, [
            'grid' => false,
        ]);

        $t->same('.EgL3uq_grid{grid-template-areas:"foo"}.EgL3uq_foo{grid-area:foo}.EgL3uq_bar{grid-column-start:foo-start}', $disabled['code']);
        $t->same([
            'grid' => $export('EgL3uq_grid', [$dependency('utility', 'tokens.css')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
        ], $disabled['exports']);

        try {
            (new CssModulesTransformer())->transform('.grid { grid-template-areas: "foo"; }', [
                'pattern' => 'test-[local]-[hash]',
            ]);
        } catch (InvalidArgumentException $exception) {
            $t->same('The CSS modules `pattern` config must end with `[local]` for use in CSS grid line names.', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected invalid CSS Modules grid pattern exception');
    },
    'css modules scopes upstream container query names while preserving composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.box2 {
  @container main (width >= 0) {
    background-color: #90ee90;
  }

  composes: card from "card.css";
}
CSS);

        $t->same('@container EgL3uq_main (width>=0){.EgL3uq_box2{background-color:#90ee90}}', $result['code']);
        $t->same([
            'box2' => $export('EgL3uq_box2', [$dependency('card', 'card.css')]),
            'main' => $export('EgL3uq_main'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $disabled = (new CssModulesTransformer())->transform(<<<'CSS'
.box2 {
  @container main (width >= 0) {
    background-color: #90ee90;
  }
}
CSS, [
            'container' => false,
        ]);

        $t->same('@container main (width>=0){.EgL3uq_box2{background-color:#90ee90}}', $disabled['code']);
        $t->same([
            'box2' => $export('EgL3uq_box2'),
        ], $disabled['exports']);

        $topLevel = (new CssModulesTransformer())->transform(<<<'CSS'
@container layout (inline-size > 45em) {
  .wide {
    color: red;
  }
}

@container style(--responsive: true) {
  .styleQuery {
    color: yellow;
  }
}

@container not (width > 500px) {
  .negated {
    color: blue;
  }
}
CSS);

        $t->same('@container EgL3uq_layout (inline-size>45em){.EgL3uq_wide{color:red}}@container style(--responsive:true){.EgL3uq_styleQuery{color:#ff0}}@container not (width>500px){.EgL3uq_negated{color:#00f}}', $topLevel['code']);
        $t->same([
            'layout' => $export('EgL3uq_layout'),
            'wide' => $export('EgL3uq_wide'),
            'styleQuery' => $export('EgL3uq_styleQuery'),
            'negated' => $export('EgL3uq_negated'),
        ], $topLevel['exports']);
    },
    'css modules scopes upstream scope rule preludes without nested composes exports' => static function (TestRunner $t) use ($export): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
@scope (.scopeRoot) to (:global(.legacy-stop), .scopeLimit) {
  .card {
    color: red;
  }

  .base {
    color: blue;
  }
}
CSS);

        $t->same('@scope(.EgL3uq_scopeRoot) to (.legacy-stop,.EgL3uq_scopeLimit){:scope .EgL3uq_card{color:red}:scope .EgL3uq_base{color:#00f}}', $result['code']);
        $t->same([
            'scopeRoot' => $export('EgL3uq_scopeRoot'),
            'scopeLimit' => $export('EgL3uq_scopeLimit'),
            'card' => $export('EgL3uq_card'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $globalLocal = (new CssModulesTransformer())->transform(<<<'CSS'
@scope (:global(.wp-block) :local(.card-scope)) to (:global(.stop)) {
  .card {
    color: yellow;
  }
}
CSS);

        $t->same('@scope(.wp-block .EgL3uq_card-scope) to (.stop){:scope .EgL3uq_card{color:#ff0}}', $globalLocal['code']);
        $t->same([
            'card-scope' => $export('EgL3uq_card-scope'),
            'card' => $export('EgL3uq_card'),
        ], $globalLocal['exports']);
        $t->same([], $globalLocal['references']);
    },
    'css modules pure mode validates upstream scope rule selector boundaries' => static function (TestRunner $t) use ($export): void {
        $transformer = new CssModulesTransformer();

        $accepted = $transformer->transform('@scope (.a) to (.b) { .foo { color: red } }', ['pure' => true]);
        $t->same('@scope(.EgL3uq_a) to (.EgL3uq_b){:scope .EgL3uq_foo{color:red}}', $accepted['code']);
        $t->same([
            'a' => $export('EgL3uq_a'),
            'b' => $export('EgL3uq_b'),
            'foo' => $export('EgL3uq_foo'),
        ], $accepted['exports']);

        foreach ([
            '@scope (div) { .foo { color: red } }',
            '@scope (.a) to (div) { .foo { color: red } }',
            '@scope (.a) to (.b) { div { color: red } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css, ['pure' => true]));
        }
    },
    'css modules scopes upstream dashed idents and records dependency references' => static function (TestRunner $t) use ($export, $dashed): void {
        $css = <<<'CSS'
.foo {
  --accent: red;
  color: var(--accent);
}

.bar {
  color: var(--color from "./tokens.css");
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
        ]);
        $placeholder = array_key_first($result['references']);

        if (!is_string($placeholder)) {
            throw new RuntimeException('Expected a dashed-ident dependency placeholder');
        }

        $t->contains('.EgL3uq_foo{--EgL3uq_accent:red;color:var(--EgL3uq_accent)}', $result['code']);
        $t->contains('.EgL3uq_bar{color:var(' . $placeholder . ')}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
            '--accent' => $dashed('--EgL3uq_accent', true),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([
            $placeholder => [
                'type' => 'dependency',
                'name' => '--color',
                'specifier' => './tokens.css',
            ],
        ], $result['references']);

        try {
            (new CssModulesTransformer())->transform('.card { margin: env(--gap from "./tokens.css", 1rem); color: red }', [
                'dashedIdents' => true,
            ]);
        } catch (InvalidArgumentException $exception) {
            $t->same('Unexpected token Ident("from")', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected env() from syntax to be rejected before CSS Modules references are recorded');
    },
    'css modules scopes upstream media env dashed idents without nested composes' => static function (TestRunner $t) use ($export, $dashed): void {
        $css = <<<'CSS'
@media (max-width: env(--branding-small)) {
  .foo {
    color: env(--brand-color);
  }
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
        ]);

        $t->same('@media (width<=env(--EgL3uq_branding-small)){.EgL3uq_foo{color:env(--EgL3uq_brand-color)}}', $result['code']);
        $t->same([
            '--branding-small' => $dashed('--EgL3uq_branding-small', true),
            'foo' => $export('EgL3uq_foo'),
            '--brand-color' => $dashed('--EgL3uq_brand-color', true),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream dashed property and font palette idents while preserving composes' => static function (TestRunner $t) use ($export, $dashed, $dependency): void {
        $css = <<<'CSS'
@property --foo {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}

@font-palette-values --Cooler {
  font-family: Bixa;
  base-palette: 1;
  override-colors: 1 #7EB7E4;
}

.foo {
  --foo: red;
  font-palette: --Cooler;
  composes: base from "tokens.css";
  color: var(--foo);
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
        ]);

        $t->same('@property --EgL3uq_foo{syntax:"<color>";inherits:false;initial-value:#ff0}@font-palette-values --EgL3uq_Cooler{font-family:Bixa;base-palette:1;override-colors:1 #7eb7e4}.EgL3uq_foo{--EgL3uq_foo:red;font-palette:--EgL3uq_Cooler;color:var(--EgL3uq_foo)}', $result['code']);
        $t->same([
            '--foo' => $dashed('--EgL3uq_foo', true),
            '--Cooler' => $dashed('--EgL3uq_Cooler', true),
            'foo' => $export('EgL3uq_foo', [$dependency('base', 'tokens.css')]),
        ], $result['exports']);
        $t->same([], $result['references']);

        $disabled = (new CssModulesTransformer())->transform($css);
        $t->contains('@property --foo', $disabled['code']);
        $t->contains('@font-palette-values --Cooler', $disabled['code']);
        $t->contains('font-palette:--Cooler', $disabled['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$dependency('base', 'tokens.css')]),
        ], $disabled['exports']);
    },
    'css modules scopes upstream position try dashed idents while preserving composes' => static function (TestRunner $t) use ($export, $dashed, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
@position-try --flyout {
  left: anchor(left);
}

.card {
  position-try-fallbacks: --flyout;
  composes: base;
  color: red;
}

.base {
  color: blue;
}
CSS, [
            'dashedIdents' => true,
        ]);

        $t->same('@position-try --EgL3uq_flyout{left:anchor(left)}.EgL3uq_card{position-try-fallbacks:--EgL3uq_flyout;color:red}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            '--flyout' => $dashed('--EgL3uq_flyout'),
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $fallbackList = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  position-try-fallbacks: --primary, --secondary flip-block, flip-inline --tertiary;
  color: red;
}
CSS, [
            'dashedIdents' => true,
        ]);

        $t->same('.EgL3uq_card{position-try-fallbacks:--EgL3uq_primary,--EgL3uq_secondary flip-block,flip-inline --EgL3uq_tertiary;color:red}', $fallbackList['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            '--primary' => $dashed('--EgL3uq_primary'),
            '--secondary' => $dashed('--EgL3uq_secondary'),
            '--tertiary' => $dashed('--EgL3uq_tertiary'),
        ], $fallbackList['exports']);

        $supports = (new CssModulesTransformer())->transform(<<<'CSS'
@supports (anchor-name: --menu-anchor) {
  @position-try --menu {
    top: anchor(bottom);
  }

  .card {
    position-try-fallbacks: --menu;
    color: red;
  }
}
CSS, [
            'dashedIdents' => true,
        ]);

        $t->same('@supports (anchor-name:--menu-anchor){@position-try --EgL3uq_menu{top:anchor(bottom)}.EgL3uq_card{position-try-fallbacks:--EgL3uq_menu;color:red}}', $supports['code']);
        $t->same([
            '--menu' => $dashed('--EgL3uq_menu'),
            'card' => $export('EgL3uq_card'),
        ], $supports['exports']);

        $varFallback = (new CssModulesTransformer())->transform('.card { position-try-fallbacks: var(--flyout); color: red }', [
            'dashedIdents' => true,
        ]);

        $t->same('.EgL3uq_card{position-try-fallbacks:var(--EgL3uq_flyout);color:red}', $varFallback['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            '--flyout' => $dashed('--EgL3uq_flyout', true),
        ], $varFallback['exports']);

        $disabled = (new CssModulesTransformer())->transform('@position-try --flyout { left: anchor(left); } .card { position-try-fallbacks: --flyout; color: red }', [
            'dashedIdents' => false,
        ]);

        $t->same('@position-try --flyout{left:anchor(left)}.EgL3uq_card{position-try-fallbacks:--flyout;color:red}', $disabled['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $disabled['exports']);
    },
    'css modules prunes upstream unused symbols while preserving surviving composes exports' => static function (TestRunner $t) use ($export, $local, $dependency): void {
        $css = <<<'CSS'
@property --unused-accent {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}

@font-palette-values --unused-palette {
  font-family: Bixa;
  base-palette: 1;
  override-colors: 1 #7EB7E4;
}

.keep {
  --unused-accent: red;
  composes: base;
  color: red;
}

.base {
  color: blue;
}

.drop {
  composes: utility from global;
  color: green;
}

#drop-id {
  color: yellow;
}

@keyframes fade {
  from { opacity: 0 }
  to { opacity: 1 }
}

@media (min-width: 1px) {
  .drop {
    color: purple;
  }

  .keepMedia {
    color: yellow;
  }
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
            'unusedSymbols' => ['drop', 'drop-id', 'fade', '--unused-accent', '--unused-palette'],
        ]);

        $t->same('.EgL3uq_keep{color:red}.EgL3uq_base{color:#00f}@media (width>=1px){.EgL3uq_keepMedia{color:#ff0}}', $result['code']);
        $t->same([
            'keep' => $export('EgL3uq_keep', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
            'keepMedia' => $export('EgL3uq_keepMedia'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $snakeCaseOption = (new CssModulesTransformer())->transform('.kept { color: red } .gone { color: blue }', [
            'unused_symbols' => ['gone'],
        ]);

        $t->same('.EgL3uq_kept{color:red}', $snakeCaseOption['code']);
        $t->same([
            'kept' => $export('EgL3uq_kept'),
        ], $snakeCaseOption['exports']);
    },
    'css modules unused symbols do not prune matching global selectors before composes exports' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
:global(.legacy) .card {
  color: red;
}

.legacy {
  color: blue;
}

.card {
  composes: reset;
  color: green;
}

.reset {
  color: yellow;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'unusedSymbols' => ['legacy'],
        ]);

        $t->same('.legacy .EgL3uq_card{color:red}.EgL3uq_card{color:green}.EgL3uq_reset{color:#ff0}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_reset')]),
            'reset' => $export('EgL3uq_reset'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $cardPruned = (new CssModulesTransformer())->transform(':global(.legacy) .card { color: red } .card { color: green }', [
            'unusedSymbols' => ['card'],
        ]);

        $t->same('', $cardPruned['code']);
        $t->same([], $cardPruned['exports']);
    },
    'css modules prunes stale nested unused exports while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.foo {
  color: red;

  &.bar {
    color: purple;
  }

  @nest &.bar {
    color: orange;
  }

  @nest :not(&) {
    color: green;
  }
}

.x {
  color: purple;

  &.y {
    color: green;
  }
}

.survivor {
  composes: reset;
  color: yellow;
}

.reset {
  color: blue;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'unusedSymbols' => ['foo', 'x'],
        ]);

        $t->same(':not(.EgL3uq_foo){color:green}.EgL3uq_survivor{color:#ff0}.EgL3uq_reset{color:#00f}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
            'survivor' => $export('EgL3uq_survivor', [$local('EgL3uq_reset')]),
            'reset' => $export('EgL3uq_reset'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream view transition declaration idents' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
.card {
  view-transition-name: card-enter;
  view-transition-class: page nav-menu;
  view-transition-group: contain;
}

.panel {
  view-transition-group: modal;
}

@view-transition {
  types: page nav-menu;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_card{view-transition-name:EgL3uq_card-enter;view-transition-class:EgL3uq_page EgL3uq_nav-menu;view-transition-group:contain}.EgL3uq_panel{view-transition-group:EgL3uq_modal}@view-transition{types:EgL3uq_page EgL3uq_nav-menu}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'card-enter' => $export('EgL3uq_card-enter'),
            'page' => $export('EgL3uq_page'),
            'nav-menu' => $export('EgL3uq_nav-menu'),
            'panel' => $export('EgL3uq_panel'),
            'modal' => $export('EgL3uq_modal'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream view transition selector function idents' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
:root:active-view-transition-type(page, nav-menu) {
  color: red;
}

:root::view-transition-group(hero.card.featured) {
  position: fixed;
}

:root::view-transition-new(.thumb) {
  position: fixed;
}

:root::view-transition-image-pair(card) {
  opacity: 1;
}

:root::view-transition-old(.card) {
  opacity: 0;
}

:global(:root::view-transition-group(public-card)) {
  opacity: .5;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same(':root:active-view-transition-type(EgL3uq_page,EgL3uq_nav-menu){color:red}:root::view-transition-group(EgL3uq_hero.EgL3uq_card.EgL3uq_featured){position:fixed}:root::view-transition-new(.EgL3uq_thumb){position:fixed}:root::view-transition-image-pair(EgL3uq_card){opacity:1}:root::view-transition-old(.EgL3uq_card){opacity:0}:root::view-transition-group(public-card){opacity:.5}', $result['code']);
        $t->same([
            'page' => $export('EgL3uq_page'),
            'nav-menu' => $export('EgL3uq_nav-menu'),
            'hero' => $export('EgL3uq_hero'),
            'card' => $export('EgL3uq_card'),
            'featured' => $export('EgL3uq_featured'),
            'thumb' => $export('EgL3uq_thumb'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules decodes escaped custom idents before animation and view transition scoping while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  animation: c\61 rd-pop 1s;
  view-transition-name: c\61 rd-enter;
  view-transition-class: nav\2d menu;
  view-transition-group: n\65 arest;
  composes: base;
  color: red;
}

:root:active-view-transition-type(c\61 rd-enter, nav\2d menu) {
  color: yellow;
}

:root::view-transition-group(c\61 rd-enter.t\68 umb) {
  opacity: .5;
}

:root::view-transition-new(.t\68 umb) {
  opacity: .25;
}

@keyframes c\61 rd-pop {
  from { opacity: 0 }
  to { opacity: 1 }
}

.base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{animation:1s EgL3uq_card-pop;view-transition-name:EgL3uq_card-enter;view-transition-class:EgL3uq_nav-menu;view-transition-group:nearest;color:red}:root:active-view-transition-type(EgL3uq_card-enter,EgL3uq_nav-menu){color:#ff0}:root::view-transition-group(EgL3uq_card-enter.EgL3uq_thumb){opacity:.5}:root::view-transition-new(.EgL3uq_thumb){opacity:.25}@keyframes EgL3uq_card-pop{0%{opacity:0}to{opacity:1}}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'card-pop' => [
                'name' => 'EgL3uq_card-pop',
                'composes' => [],
                'isReferenced' => true,
            ],
            'card-enter' => $export('EgL3uq_card-enter'),
            'nav-menu' => $export('EgL3uq_nav-menu'),
            'thumb' => $export('EgL3uq_thumb'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);
        $t->same('EgL3uq_card EgL3uq_base', CssModulesTransformer::exportClassList($result['exports'], 'card'));

        $keywordResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  view-transition-name: a\75 to;
  view-transition-class: n\6f ne;
  view-transition-group: n\6f rmal;
  color: red;
}

.panel {
  view-transition-group: c\6f ntain;
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{view-transition-name:auto;view-transition-class:none;view-transition-group:normal;color:red}.EgL3uq_panel{view-transition-group:contain;color:#00f}', $keywordResult['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'panel' => $export('EgL3uq_panel'),
        ], $keywordResult['exports']);
    },
    'css modules rejects local global pseudos inside upstream view transition selector functions' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes: base;
  color: red;
}

:root::view-transition-group(card) {
  opacity: .5;
}

:global(:root::view-transition-old(public-card)) {
  opacity: .25;
}

.base {
  color: blue;
}
CSS);

        $t->same('.EgL3uq_card{color:red}:root::view-transition-group(EgL3uq_card){opacity:.5}:root::view-transition-old(public-card){opacity:.25}.EgL3uq_base{color:#00f}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        foreach ([
            ':root::view-transition-group(:global(public-card)) { opacity: .5 }',
            ':root::view-transition-new(:local(card)) { opacity: .5 }',
            ':root:active-view-transition-type(:global(public-card), card) { opacity: .5 }',
            ':global(:root::view-transition-group(:local(card))) { opacity: .5 }',
            ':global(:root::view-transition-group(:global(card))) { opacity: .5 }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => (new CssModulesTransformer())->transform($css));
        }
    },
];
