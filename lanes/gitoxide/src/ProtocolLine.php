<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ProtocolLine
{
    private function __construct()
    {
    }

    public static function trimEnd(string $line): string
    {
        $trimmed = preg_replace('/\s+\z/u', '', $line);

        return $trimmed ?? rtrim($line, " \t\n\r\v\f");
    }
}
