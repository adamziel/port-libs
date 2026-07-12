<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocHtmlAttributeMapPool;

return [
    'interns repeated html attribute maps without sharing later mutations' => static function (TestRunner $t): void {
        $pool = new PandocHtmlAttributeMapPool();
        $first = $pool->intern(['style' => 'color:red', 'data-kind' => 'notice']);
        $second = $pool->intern(['style' => 'color:red', 'data-kind' => 'notice']);
        $first['style'] = 'color:blue';

        $t->same('color:blue', $first['style']);
        $t->same('color:red', $second['style']);
        $t->same(
            ['style' => 'color:red', 'data-kind' => 'notice'],
            $pool->intern(['style' => 'color:red', 'data-kind' => 'notice'])
        );
    },
];
