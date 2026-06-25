<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocConverter
{
    /** @var array<string, string> */
    private const EXTRA_INPUT_ALIASES = [
        'md' => 'markdown',
        'htm' => 'html',
    ];

    /** @var array<string, string> */
    private const EXTRA_OUTPUT_ALIASES = [
        'blocks' => 'wordpress',
        'wp' => 'wordpress',
        'md' => 'markdown',
        'htm' => 'html',
    ];

    /**
     * @param array<string, mixed> $options
     */
    public static function read(string $bytes, string $format, array $options = []): AstNode
    {
        $canonical = self::canonicalInputFormat($format);
        $entry = self::inputSupport($canonical);
        $reader = self::reader($entry['implementation'], $canonical, $options);

        return $reader->read($bytes);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function readFile(string $path, string $format, array $options = []): AstNode
    {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return self::read($bytes, $format, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function write(AstNode $document, string $format, array $options = []): string
    {
        $canonical = self::canonicalOutputFormat($format);
        if ($canonical === 'wordpress') {
            return (new WordPressBlockWriter($options))->write($document);
        }

        $entry = self::outputSupport($canonical);
        $writer = self::writer($entry['implementation'], $canonical, $options);

        return $writer->write($document);
    }

    /**
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>} $options
     */
    public static function convert(string $bytes, string $from, string $to, array $options = []): string
    {
        $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
        $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];

        return self::write(self::read($bytes, $from, $readerOptions), $to, $writerOptions);
    }

    /**
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>} $options
     */
    public static function convertFile(string $path, string $from, string $to, array $options = []): string
    {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return self::convert($bytes, $from, $to, $options);
    }

    public static function canRead(string $format): bool
    {
        $canonical = self::canonicalInputFormat($format);
        $support = array_replace(PandocFormatRegistry::phpInputSupport(), PandocFormatRegistry::phpLocalInputSupport());

        return isset($support[$canonical]) && $support[$canonical]['status'] !== 'unsupported';
    }

    public static function canWrite(string $format): bool
    {
        $canonical = self::canonicalOutputFormat($format);
        if ($canonical === 'wordpress') {
            return true;
        }

        $support = PandocFormatRegistry::phpOutputSupport();

        return isset($support[$canonical]) && $support[$canonical]['status'] !== 'unsupported';
    }

    public static function canonicalInputFormat(string $format): string
    {
        $format = self::normalizeFormat($format);
        $aliases = array_replace(PandocFormatRegistry::inputAliases(), self::EXTRA_INPUT_ALIASES);

        return $aliases[$format] ?? $format;
    }

    public static function canonicalOutputFormat(string $format): string
    {
        $format = self::normalizeFormat($format);
        $aliases = array_replace(PandocFormatRegistry::outputAliases(), self::EXTRA_OUTPUT_ALIASES);

        return $aliases[$format] ?? $format;
    }

    private static function normalizeFormat(string $format): string
    {
        return strtolower(str_replace('-', '_', trim($format)));
    }

    /**
     * @return array{status:string, implementation:string, notes:string}
     */
    private static function inputSupport(string $format): array
    {
        $support = array_replace(PandocFormatRegistry::phpInputSupport(), PandocFormatRegistry::phpLocalInputSupport());
        if (!isset($support[$format])) {
            throw new \InvalidArgumentException("Unknown Pandoc input format '{$format}'.");
        }
        if ($support[$format]['status'] === 'unsupported') {
            throw new \InvalidArgumentException("No native PHP Pandoc reader is registered for input format '{$format}'.");
        }

        return $support[$format];
    }

    /**
     * @return array{status:string, implementation:string, notes:string}
     */
    private static function outputSupport(string $format): array
    {
        $support = PandocFormatRegistry::phpOutputSupport();
        if (!isset($support[$format])) {
            throw new \InvalidArgumentException("Unknown Pandoc output format '{$format}'.");
        }
        if ($support[$format]['status'] === 'unsupported') {
            throw new \InvalidArgumentException("No native PHP Pandoc writer is registered for output format '{$format}'.");
        }

        return $support[$format];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function reader(string $implementation, string $format, array $options): object
    {
        return match ($implementation) {
            BibTexReader::class => new BibTexReader($format),
            CsvReader::class => new CsvReader($format),
            DocxReader::class => new DocxReader(),
            EpubReader::class => new EpubReader($options),
            MarkdownReader::class => new MarkdownReader(self::markdownReaderOptions($format, $options)),
            JsonReader::class => new JsonReader(),
            MediaWikiReader::class => new MediaWikiReader(),
            NativeReader::class => new NativeReader(),
            OdtReader::class => new OdtReader(),
            PdfReader::class => new PdfReader($options),
            PptxReader::class => new PptxReader($options),
            RisReader::class => new RisReader(),
            RstReader::class => new RstReader(),
            XmlReader::class => new XmlReader(),
            XlsxReader::class => new XlsxReader(),
            default => throw new \InvalidArgumentException("Unsupported Pandoc reader implementation '{$implementation}'."),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function writer(string $implementation, string $format, array $options): object
    {
        return match ($implementation) {
            EpubWriter::class => new EpubWriter(array_replace(['writerFormat' => $format], $options)),
            HtmlWriter::class => new HtmlWriter($options),
            JsonWriter::class => new JsonWriter(),
            LatexWriter::class => new LatexWriter($options),
            MarkdownWriter::class => new MarkdownWriter(self::markdownWriterOptions($format, $options)),
            NativeWriter::class => new NativeWriter($options),
            default => throw new \InvalidArgumentException("Unsupported Pandoc writer implementation '{$implementation}'."),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function markdownReaderOptions(string $format, array $options): array
    {
        if ($format === 'html') {
            return array_replace(['htmlNativeDivs' => true], $options);
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function markdownWriterOptions(string $format, array $options): array
    {
        if (!isset($options['variant']) && $format !== 'markdown') {
            $options['variant'] = $format;
        }

        return $options;
    }
}
