<?php

declare(strict_types=1);

$main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
$installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';

return [
    'capabilities' => implode("\n", [
        'version 2',
        'ls-refs=unborn',
        'fetch=shallow filter ref-in-want sideband-all packfile-uris',
        'object-format=sha1',
        'agent=git/2.44.0',
        '',
    ]),
    'targetRef' => 'refs/heads/main',
    'mainObject' => $main,
    'installedObject' => $installed,
    'filter' => 'blob:none',
    'depth' => 1,
    'wordpressUse' => 'A PHP deployment tool can request the active WordPress branch by ref, limit history depth, and apply a blobless filter before downloading pack data.',
];
