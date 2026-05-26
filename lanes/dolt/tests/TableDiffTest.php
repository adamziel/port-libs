<?php

declare(strict_types=1);

use PortLibs\Dolt\TableDiff;

return [
    'table diff classifies added removed and modified rows by primary key' => static function (TestRunner $t): void {
        $old = [
            ['id' => 1, 'title' => 'Draft'],
            ['id' => 2, 'title' => 'Remove me'],
        ];
        $new = [
            ['id' => 1, 'title' => 'Published'],
            ['id' => 3, 'title' => 'New'],
        ];
        $diff = (new TableDiff())->diff($old, $new, 'id');
        $t->same(1, count($diff['added']));
        $t->same(1, count($diff['removed']));
        $t->same('Published', $diff['modified'][0]['new']['title']);
    },
];

