<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitMailmap
{
    /**
     * @return list<array{newName:?string,newEmail:?string,oldName:?string,oldEmail:string}>
     */
    public static function parse(string $contents): array
    {
        $entries = [];
        foreach (self::parseResults($contents) as $result) {
            if ($result['error'] !== null) {
                throw $result['error'];
            }

            $entries[] = $result['entry'];
        }

        return $entries;
    }

    /**
     * @return list<array{newName:?string,newEmail:?string,oldName:?string,oldEmail:string}>
     */
    public static function parseIgnoringErrors(string $contents): array
    {
        $entries = [];
        foreach (self::parseResults($contents) as $result) {
            if ($result['entry'] !== null) {
                $entries[] = $result['entry'];
            }
        }

        return $entries;
    }

    /**
     * @return list<array{
     *     entry:?array{newName:?string,newEmail:?string,oldName:?string,oldEmail:string},
     *     error:?\InvalidArgumentException
     * }>
     */
    public static function parseResults(string $contents): array
    {
        $results = [];
        $lineNumber = 0;

        foreach (self::lines($contents) as $line) {
            $lineNumber++;
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $line = self::trimAsciiWhitespace($line);
            if ($line === '') {
                continue;
            }

            try {
                $results[] = [
                    'entry' => self::parseLine($line, $lineNumber),
                    'error' => null,
                ];
            } catch (\InvalidArgumentException $exception) {
                $results[] = [
                    'entry' => null,
                    'error' => $exception,
                ];
            }
        }

        return $results;
    }

    /**
     * @return array{newName:string,newEmail:null,oldName:null,oldEmail:string}
     */
    public static function changeNameByEmail(string $properName, string $commitEmail): array
    {
        return [
            'newName' => $properName,
            'newEmail' => null,
            'oldName' => null,
            'oldEmail' => $commitEmail,
        ];
    }

    /**
     * @return array{newName:null,newEmail:string,oldName:null,oldEmail:string}
     */
    public static function changeEmailByEmail(string $properEmail, string $commitEmail): array
    {
        return [
            'newName' => null,
            'newEmail' => $properEmail,
            'oldName' => null,
            'oldEmail' => $commitEmail,
        ];
    }

    /**
     * @return array{newName:null,newEmail:string,oldName:string,oldEmail:string}
     */
    public static function changeEmailByNameAndEmail(
        string $properEmail,
        string $commitName,
        string $commitEmail,
    ): array {
        return [
            'newName' => null,
            'newEmail' => $properEmail,
            'oldName' => $commitName,
            'oldEmail' => $commitEmail,
        ];
    }

    /**
     * @return array{newName:string,newEmail:string,oldName:null,oldEmail:string}
     */
    public static function changeNameAndEmailByEmail(
        string $properName,
        string $properEmail,
        string $commitEmail,
    ): array {
        return [
            'newName' => $properName,
            'newEmail' => $properEmail,
            'oldName' => null,
            'oldEmail' => $commitEmail,
        ];
    }

    /**
     * @return array{newName:string,newEmail:string,oldName:string,oldEmail:string}
     */
    public static function changeNameAndEmailByNameAndEmail(
        string $properName,
        string $properEmail,
        string $commitName,
        string $commitEmail,
    ): array {
        return [
            'newName' => $properName,
            'newEmail' => $properEmail,
            'oldName' => $commitName,
            'oldEmail' => $commitEmail,
        ];
    }

    /**
     * @return array{newName:?string,newEmail:?string,oldName:?string,oldEmail:string}
     */
    private static function parseLine(string $line, int $lineNumber): array
    {
        [$name1, $email1, $rest] = self::parseNameAndEmail($line, $lineNumber);
        [$name2, $email2, $rest] = self::parseNameAndEmail($rest, $lineNumber);

        if (self::trimAsciiWhitespace($rest) !== '') {
            throw new \InvalidArgumentException("Line {$lineNumber} has too many names or emails, or none at all");
        }

        if ($name1 !== null && $email1 !== null && $name2 === null && $email2 === null) {
            return self::changeNameByEmail($name1, $email1);
        }

        if ($name1 === null && $email1 !== null && $name2 === null && $email2 !== null) {
            return self::changeEmailByEmail($email1, $email2);
        }

        if ($name1 !== null && $email1 !== null && $name2 === null && $email2 !== null) {
            return self::changeNameAndEmailByEmail($name1, $email1, $email2);
        }

        if ($name1 !== null && $email1 !== null && $name2 !== null && $email2 !== null) {
            return self::changeNameAndEmailByNameAndEmail($name1, $email1, $name2, $email2);
        }

        if ($name1 === null && $email1 !== null && $name2 !== null && $email2 !== null) {
            return self::changeEmailByNameAndEmail($email1, $name2, $email2);
        }

        throw new \InvalidArgumentException("{$lineNumber}: Emails without a name or email to map to are invalid");
    }

    /**
     * @return array{?string, ?string, string}
     */
    private static function parseNameAndEmail(string $line, int $lineNumber): array
    {
        $startBracket = strpos($line, '<');
        if ($startBracket === false) {
            return [null, null, $line];
        }

        $afterStart = substr($line, $startBracket + 1);
        $closingBracket = strpos($afterStart, '>');
        if ($closingBracket === false) {
            throw new \InvalidArgumentException("{$lineNumber}: Missing closing bracket '>' in email");
        }

        $email = self::trimAsciiWhitespace(substr($afterStart, 0, $closingBracket));
        if ($email === '') {
            throw new \InvalidArgumentException("{$lineNumber}: Email must not be empty");
        }

        $name = self::trimAsciiWhitespace(substr($line, 0, $startBracket));
        $rest = substr($line, $startBracket + $closingBracket + 2);

        return [$name === '' ? null : $name, $email, $rest];
    }

    /**
     * @return \Generator<int, string>
     */
    private static function lines(string $contents): \Generator
    {
        $offset = 0;
        $length = strlen($contents);

        while (($end = strpos($contents, "\n", $offset)) !== false) {
            $line = substr($contents, $offset, $end - $offset);
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }

            yield $line;
            $offset = $end + 1;
        }

        if ($offset < $length) {
            yield substr($contents, $offset);
        }
    }

    private static function trimAsciiWhitespace(string $value): string
    {
        return trim($value, " \t\n\r\x0B\f");
    }
}
