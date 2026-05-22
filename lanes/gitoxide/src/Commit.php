<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class Commit
{
    /**
     * @param list<string> $parents
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly string $tree,
        public readonly array $parents,
        public readonly string $author,
        public readonly string $committer,
        public readonly string $message,
        public readonly array $headers,
    ) {
    }

    public static function parse(string $body): self
    {
        [$headerBlock, $message] = array_pad(explode("\n\n", $body, 2), 2, '');
        $headers = [];
        $current = null;

        foreach (explode("\n", $headerBlock) as $line) {
            if ($line === '') {
                continue;
            }
            if ($line[0] === ' ') {
                if ($current === null) {
                    throw new \InvalidArgumentException('Commit continuation line without a header');
                }
                $last = array_key_last($headers[$current]);
                $headers[$current][$last] .= "\n" . substr($line, 1);
                continue;
            }

            $space = strpos($line, ' ');
            if ($space === false) {
                throw new \InvalidArgumentException('Invalid commit header line: ' . $line);
            }
            $current = substr($line, 0, $space);
            $headers[$current][] = substr($line, $space + 1);
        }

        foreach (['tree', 'author', 'committer'] as $required) {
            if (($headers[$required] ?? []) === []) {
                throw new \InvalidArgumentException("Commit is missing required {$required} header");
            }
        }

        $tree = $headers['tree'][0];
        if (!preg_match('/^[0-9a-f]{40}$/', $tree)) {
            throw new \InvalidArgumentException('Commit tree must be a 40-character SHA-1 hex object id');
        }

        $parents = $headers['parent'] ?? [];
        foreach ($parents as $parent) {
            if (!preg_match('/^[0-9a-f]{40}$/', $parent)) {
                throw new \InvalidArgumentException('Commit parent must be a 40-character SHA-1 hex object id');
            }
        }

        return new self(
            $tree,
            $parents,
            $headers['author'][0],
            $headers['committer'][0],
            $message,
            $headers,
        );
    }
}

