<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibtexCslParser
{
    private int $offset = 0;
    private readonly int $length;

    /** @var array<string, string> */
    private array $strings;

    private function __construct(private readonly string $input)
    {
        $this->length = strlen($input);
        $this->strings = self::standardStrings();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parse(string $bibtex): array
    {
        return (new self($bibtex))->parseEntries();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseEntries(): array
    {
        $items = [];
        while (true) {
            $at = strpos($this->input, '@', $this->offset);
            if ($at === false) {
                break;
            }

            $this->offset = $at + 1;
            $this->skipWhitespace();
            $type = strtolower($this->readIdentifier());
            if ($type === '') {
                throw new \InvalidArgumentException('BibTeX entry missing entry type at byte ' . $at);
            }

            $this->skipWhitespace();
            $open = $this->peek();
            if ($open !== '{' && $open !== '(') {
                throw new \InvalidArgumentException('BibTeX entry ' . $type . ' must open with { or (');
            }

            $close = $open === '{' ? '}' : ')';
            $this->offset++;

            if ($type === 'comment') {
                $this->skipBalancedEntry($open, $close);
                continue;
            }

            if ($type === 'preamble') {
                $this->parseValue($close);
                $this->skipWhitespace();
                $this->expect($close);
                continue;
            }

            if ($type === 'string') {
                $this->parseStringEntry($close);
                continue;
            }

            $key = trim($this->readUntilTopLevel([',', $close]));
            if ($key === '') {
                throw new \InvalidArgumentException('BibTeX entry ' . $type . ' is missing a citation key');
            }

            $fields = [];
            if ($this->peek() === $close) {
                $this->offset++;
            } else {
                $this->expect(',');
                $fields = $this->parseFields($type, $key, $close);
            }

            $items[] = self::entryToCslItem($type, $key, $fields);
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $type, string $key, string $close): array
    {
        $fields = [];
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === $close) {
                $this->offset++;
                break;
            }

            $field = strtolower($this->readIdentifier());
            if ($field === '') {
                throw new \InvalidArgumentException('BibTeX entry ' . $key . ' has a malformed field name');
            }

            $this->skipWhitespace();
            $this->expect('=');
            $fields[$field] = $this->parseValue($close);
            $this->skipWhitespace();

            $next = $this->peek();
            if ($next === ',') {
                $this->offset++;
                continue;
            }

            if ($next === $close) {
                $this->offset++;
                break;
            }

            throw new \InvalidArgumentException('BibTeX entry ' . $type . ':' . $key . ' field ' . $field . ' must end with comma or ' . $close);
        }

        return $fields;
    }

    private function parseStringEntry(string $close): void
    {
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === $close) {
                $this->offset++;
                return;
            }

            $name = strtolower($this->readIdentifier());
            if ($name === '') {
                throw new \InvalidArgumentException('BibTeX @string entry has a malformed name');
            }

            $this->skipWhitespace();
            $this->expect('=');
            $this->strings[$name] = self::cleanBibtexText($this->parseValue($close));
            $this->skipWhitespace();

            $next = $this->peek();
            if ($next === ',') {
                $this->offset++;
                continue;
            }

            if ($next === $close) {
                $this->offset++;
                return;
            }

            throw new \InvalidArgumentException('BibTeX @string entry must end with comma or ' . $close);
        }
    }

    private function parseValue(string $entryClose): string
    {
        $this->skipWhitespace();
        $value = $this->parseValueAtom($entryClose);

        while (true) {
            $this->skipWhitespace();
            if ($this->peek() !== '#') {
                break;
            }

            $this->offset++;
            $this->skipWhitespace();
            $value .= $this->parseValueAtom($entryClose);
        }

        return trim($value);
    }

    private function parseValueAtom(string $entryClose): string
    {
        $char = $this->peek();
        if ($char === null) {
            throw new \InvalidArgumentException('Unexpected end of BibTeX value');
        }

        if ($char === '{') {
            return $this->readBracedValue();
        }

        if ($char === '"') {
            return $this->readQuotedValue();
        }

        $token = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if ($char === '#' || $char === ',' || $char === $entryClose || ctype_space($char)) {
                break;
            }

            $token .= $char;
            $this->offset++;
        }

        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Unexpected empty BibTeX value atom');
        }

        $lookup = strtolower($token);

        return $this->strings[$lookup] ?? $token;
    }

    private function readBracedValue(): string
    {
        $this->expect('{');
        $depth = 1;
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset++];
            if ($char === '{') {
                $depth++;
                $value .= $char;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $value;
                }

                $value .= $char;
                continue;
            }

            $value .= $char;
        }

        throw new \InvalidArgumentException('Unterminated BibTeX braced value');
    }

    private function readQuotedValue(): string
    {
        $this->expect('"');
        $depth = 0;
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $this->offset < $this->length) {
                $value .= $char . $this->input[$this->offset++];
                continue;
            }

            if ($char === '{') {
                $depth++;
                $value .= $char;
                continue;
            }

            if ($char === '}' && $depth > 0) {
                $depth--;
                $value .= $char;
                continue;
            }

            if ($char === '"' && $depth === 0) {
                return $value;
            }

            $value .= $char;
        }

        throw new \InvalidArgumentException('Unterminated BibTeX quoted value');
    }

    /**
     * @param list<string> $delimiters
     */
    private function readUntilTopLevel(array $delimiters): string
    {
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (in_array($char, $delimiters, true)) {
                return $value;
            }

            $value .= $char;
            $this->offset++;
        }

        return $value;
    }

    private function readIdentifier(): string
    {
        $identifier = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (!preg_match('/[A-Za-z0-9_:\\.-]/', $char)) {
                break;
            }

            $identifier .= $char;
            $this->offset++;
        }

        return $identifier;
    }

    private function skipWhitespace(): void
    {
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (ctype_space($char)) {
                $this->offset++;
                continue;
            }

            if ($char === '%') {
                while ($this->offset < $this->length && !in_array($this->input[$this->offset], ["\n", "\r"], true)) {
                    $this->offset++;
                }
                continue;
            }

            return;
        }
    }

    private function skipBalancedEntry(string $open, string $close): void
    {
        $depth = 1;
        $inQuote = false;
        while ($this->offset < $this->length && $depth > 0) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $inQuote && $this->offset < $this->length) {
                $this->offset++;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if ($inQuote) {
                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
            }
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unterminated BibTeX comment entry');
        }
    }

    private function expect(string $expected): void
    {
        if ($this->peek() !== $expected) {
            throw new \InvalidArgumentException('Expected BibTeX token ' . $expected . ' at byte ' . $this->offset);
        }

        $this->offset++;
    }

    private function peek(): ?string
    {
        return $this->offset < $this->length ? $this->input[$this->offset] : null;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private static function entryToCslItem(string $type, string $key, array $fields): array
    {
        $item = [
            'id' => $key,
            'type' => self::cslType($type),
            'title' => self::firstField($fields, ['title']),
            'container-title' => self::firstField($fields, ['journaltitle', 'journal', 'booktitle']),
            'publisher' => self::firstField($fields, ['publisher', 'institution', 'school', 'organization']),
            'page' => self::normalizePages(self::firstField($fields, ['pages', 'page'])),
            'DOI' => self::firstField($fields, ['doi']),
            'URL' => self::firstField($fields, ['url']),
            'rawBibtex' => [
                'type' => $type,
                'key' => $key,
                'fields' => $fields,
            ],
        ];

        $author = self::namesFromBibtex($fields['author'] ?? '');
        if ($author !== []) {
            $item['author'] = $author;
        }

        $editor = self::namesFromBibtex($fields['editor'] ?? '');
        if ($editor !== []) {
            $item['editor'] = $editor;
        }

        $issued = self::dateFromFields($fields, ['date'], ['year', 'month', 'day']);
        if ($issued !== null) {
            $item['issued'] = $issued;
        }

        $accessed = self::dateFromFields($fields, ['urldate', 'accessed', 'accessdate'], []);
        if ($accessed !== null) {
            $item['accessed'] = $accessed;
        }

        return $item;
    }

    private static function cslType(string $type): string
    {
        return match (strtolower($type)) {
            'article' => 'article-journal',
            'inproceedings', 'conference' => 'paper-conference',
            'inbook', 'incollection' => 'chapter',
            'phdthesis', 'mastersthesis' => 'thesis',
            'techreport' => 'report',
            'online', 'www', 'electronic' => 'webpage',
            'unpublished' => 'manuscript',
            default => strtolower($type),
        };
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private static function firstField(array $fields, array $names): string
    {
        foreach ($names as $name) {
            if (isset($fields[$name])) {
                $value = self::cleanBibtexText($fields[$name]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function namesFromBibtex(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $names = [];
        foreach (self::splitBibtexNames($value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $names[] = self::nameToCsl($name);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function splitBibtexNames(string $value): array
    {
        $names = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($depth === 0 && preg_match('/\G\s+and\s+/i', $value, $match, 0, $i) === 1) {
                $names[] = $buffer;
                $buffer = '';
                $i += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $char;
        }

        $names[] = $buffer;

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameToCsl(string $name): array
    {
        $literal = self::outerBraced($name);
        if ($literal !== null) {
            return [
                'literal' => self::cleanBibtexText($literal),
            ];
        }

        $parts = self::splitTopLevel($name, ',');
        if (count($parts) >= 2) {
            [$particle, $family] = self::splitLeadingParticle(self::cleanBibtexText($parts[0]));
            $name = [
                'family' => $family,
                'given' => self::cleanBibtexText($parts[1]),
            ];

            if ($particle !== '') {
                $name['non-dropping-particle'] = $particle;
            }

            if (isset($parts[2]) && self::cleanBibtexText($parts[2]) !== '') {
                $name['suffix'] = self::cleanBibtexText($parts[2]);
                $name['comma-suffix'] = true;
            }

            return $name;
        }

        $tokens = preg_split('/\s+/', self::cleanBibtexText($name)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return ['literal' => ''];
        }

        if (count($tokens) === 1) {
            return ['family' => $tokens[0]];
        }

        $family = array_pop($tokens);
        $particle = [];
        while ($tokens !== [] && self::isParticle($tokens[count($tokens) - 1])) {
            array_unshift($particle, array_pop($tokens));
        }

        $name = [
            'family' => $family,
            'given' => implode(' ', $tokens),
        ];
        if ($particle !== []) {
            $name['non-dropping-particle'] = implode(' ', $particle);
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return array_map('trim', $parts);
    }

    private static function outerBraced(string $value): ?string
    {
        $value = trim($value);
        if (!str_starts_with($value, '{') || !str_ends_with($value, '}')) {
            return null;
        }

        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] === '{') {
                $depth++;
            } elseif ($value[$i] === '}') {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return null;
                }
            }
        }

        return $depth === 0 ? substr($value, 1, -1) : null;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitLeadingParticle(string $family): array
    {
        $tokens = preg_split('/\s+/', trim($family)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if (count($tokens) < 2) {
            return ['', $family];
        }

        $particle = [];
        while (count($tokens) > 1 && self::isParticle($tokens[0])) {
            $particle[] = array_shift($tokens);
        }

        return [implode(' ', $particle), implode(' ', $tokens)];
    }

    private static function isParticle(string $token): bool
    {
        $token = trim($token, "{}~");
        $lower = strtolower($token);
        if (in_array($lower, ['da', 'de', 'del', 'della', 'der', 'di', 'dos', 'du', 'la', 'le', 'van', 'von', 'ten', 'ter'], true)) {
            return true;
        }

        return preg_match('/^[a-z][a-z\'.-]*$/', $token) === 1;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string> $partFields
     * @return array<string, mixed>|null
     */
    private static function dateFromFields(array $fields, array $dateFields, array $partFields): ?array
    {
        foreach ($dateFields as $field) {
            if (isset($fields[$field]) && trim($fields[$field]) !== '') {
                return self::dateFromText(self::cleanBibtexText($fields[$field]), $field);
            }
        }

        if ($partFields === [] || !isset($fields[$partFields[0]]) || trim($fields[$partFields[0]]) === '') {
            return null;
        }

        $year = self::cleanBibtexText($fields[$partFields[0]]);
        if (!preg_match('/^-?\d+$/', $year)) {
            return ['literal' => $year];
        }

        $parts = [(int) $year];
        if (isset($partFields[1], $fields[$partFields[1]]) && trim($fields[$partFields[1]]) !== '') {
            $parts[] = self::monthNumber(self::cleanBibtexText($fields[$partFields[1]]), $partFields[1]);
        }

        if (isset($partFields[2], $fields[$partFields[2]]) && trim($fields[$partFields[2]]) !== '') {
            $day = self::cleanBibtexText($fields[$partFields[2]]);
            if (!preg_match('/^\d+$/', $day)) {
                throw new \InvalidArgumentException('BibTeX day field must be numeric');
            }

            $parts[] = (int) $day;
        }

        return ['date-parts' => [$parts]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dateFromText(string $date, string $field): array
    {
        if (preg_match('/^(-?\d{1,6})(?:[-\/](\d{1,2})(?:[-\/](\d{1,2}))?)?$/', $date, $matches) !== 1) {
            return ['literal' => $date];
        }

        $parts = [(int) $matches[1]];
        if (isset($matches[2]) && $matches[2] !== '') {
            $month = (int) $matches[2];
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
            }

            $parts[] = $month;
        }

        if (isset($matches[3]) && $matches[3] !== '') {
            $day = (int) $matches[3];
            if ($day < 1 || $day > 31) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' day must be between 1 and 31');
            }

            $parts[] = $day;
        }

        return ['date-parts' => [$parts]];
    }

    private static function monthNumber(string $value, string $field): int
    {
        $lookup = strtolower(substr($value, 0, 3));
        $months = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12,
        ];

        if (preg_match('/^\d+$/', $value) === 1) {
            $month = (int) $value;
        } elseif (isset($months[$lookup])) {
            $month = $months[$lookup];
        } else {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' field must be a month name or number');
        }

        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
        }

        return $month;
    }

    private static function normalizePages(string $pages): string
    {
        return trim(preg_replace('/\s*--+\s*/', '-', $pages) ?? $pages);
    }

    private static function cleanBibtexText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n", '~'], ' ', $value);
        $value = preg_replace('/\\\\([&%$#_{}])/', '$1', $value) ?? $value;
        $value = preg_replace('/\\\\(?:emph|textit|textbf|enquote)\s*\{([^{}]*)\}/', '$1', $value) ?? $value;
        $value = preg_replace('/\\\\(?:textendash|textminus)\b/', '-', $value) ?? $value;
        $value = preg_replace('/[{}]/', '', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * @return array<string, string>
     */
    private static function standardStrings(): array
    {
        return [
            'jan' => 'January',
            'feb' => 'February',
            'mar' => 'March',
            'apr' => 'April',
            'may' => 'May',
            'jun' => 'June',
            'jul' => 'July',
            'aug' => 'August',
            'sep' => 'September',
            'oct' => 'October',
            'nov' => 'November',
            'dec' => 'December',
        ];
    }
}
