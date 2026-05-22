<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PortLibs\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $top = array_shift($parts);

    $lanes = [
        'Difftastic' => 'difftastic',
        'Dolt' => 'dolt',
        'Esbuild' => 'esbuild',
        'Gitoxide' => 'gitoxide',
        'LibSqlite' => 'libsqlite',
        'LightningCSS' => 'lightningcss',
        'MarkerPDF' => 'markerpdf',
        'Pandoc' => 'pandoc',
        'Quadrable' => 'quadrable',
        'Rclone' => 'rclone',
        'Readability' => 'readability',
        'Syncthing' => 'syncthing',
    ];

    if (!isset($lanes[$top])) {
        return;
    }

    $path = dirname(__DIR__) . '/lanes/' . $lanes[$top] . '/src';
    if ($parts !== []) {
        $path .= '/' . implode('/', $parts);
    } else {
        $path .= '/' . $top;
    }
    $path .= '.php';

    if (is_file($path)) {
        require $path;
    }
});

