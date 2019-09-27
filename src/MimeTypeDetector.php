<?php

namespace Rikudou\MimeTypeDetector;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Rikudou\MimeTypeDetector\Config\ConfigNormalizer;
use Rikudou\MimeTypeDetector\Config\ConfigNormalizerInterface;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

final class MimeTypeDetector
{
    /**
     * @var array
     */
    private $config = [];

    public function __construct(
        ?array $config = null,
        ?ConfigNormalizerInterface $configNormalizer = null
    )
    {
        if ($configNormalizer === null) {
            $configNormalizer = new ConfigNormalizer();
        }
        if ($config === null) {
            $config = $configNormalizer->normalizeFile(__DIR__ . '/../config/mime.yaml');
        } else {
            $config = $configNormalizer->normalizeArray($config);
        }

        $this->config = $config;
    }

    /**
     * @param string|object|SplFileInfo $file
     *
     * @return string
     */
    public function getMimeType($file): string
    {
        if ($file instanceof SplFileInfo) {
            $file = $file->getPathname();
        } elseif (is_object($file) && method_exists($file, '__toString')) {
            $file = (string)$file;
        }

        if (!is_string($file)) {
            throw new MimeTypeException('The file must be a string, instance of SplFileInfo or object implementing __toString() method');
        }

        if (!is_file($file)) {
            throw new MimeTypeException("The file '{$file}' either does not exist or is not a file");
        }

        foreach ($this->config as $mimeType => $configurations) {
            foreach ($configurations as $configuration) {
                $offset = $configuration['offset'];
                $length = $configuration['length'];
                $byteSequences = $configuration['bytes'];

                if (
                    $configuration['binary'] !== null
                    && $configuration['binary'] !== $this->isBinary(
                        $this->getBytes($file, 512, 0, true)
                    )
                ) {
                    continue;
                }

                foreach ($byteSequences as $byteSequence) {
                    if (fnmatch($byteSequence, $this->getBytes($file, $length, $offset))) {
                        if (
                            $configuration['archive'] === false
                            || (
                                $configuration['archive'] === true
                                && $this->matchesFiles($configuration, $file)
                            )
                        ) {
                            return $mimeType;
                        }
                    }
                }
            }
        }

        if ($this->isBinary($this->getBytes($file, 512, 0, true))) {
            return 'application/octet-stream';
        } else {
            return 'text/plain';
        }
    }

    /**
     * @param string $filePath
     * @param int $length
     * @param int $offset
     * @param bool $raw
     *
     * @return string
     */
    private function getBytes(
        string $filePath,
        int $length,
        int $offset = 0,
        bool $raw = false
    ): string
    {
        try {
            if ($offset < 0) {
                throw new MimeTypeException("The offset cannot be less than zero, {$offset} given");
            }
            if ($length < 0) {
                throw new MimeTypeException("The length cannot be less than zero, {$length} given");
            }
            $fp = fopen($filePath, 'r');
            if (!is_resource($fp)) {
                throw new MimeTypeException("The file '{$filePath}' could not be opened for writing");
            }

            if (fseek($fp, $offset) === -1) {
                throw new MimeTypeException("Could not seek to offset {$offset}");
            }

            $rawBytes = fread($fp, $length);
            if ($rawBytes === false) {
                throw new MimeTypeException("Could not read {$length} bytes at offset {$offset}");
            }

            if ($raw) {
                return $rawBytes;
            }

            $hex = bin2hex($rawBytes);

            return $hex;
        } finally {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
        }
    }

    private function matchesFiles(array $configuration, string $archiveFile): bool
    {
        $files = $configuration['files'];

        if (!count($files)) {
            throw new MimeTypeException('The files array cannot be empty');
        }

        if (!class_exists('ZipArchive')) {
            throw new MimeTypeException('The zip extension is not enabled, cannot check zip based files');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($archiveFile) === true) {
                foreach ($files as $file) {
                    if ($file['dir'] || $file['pattern']) {
                        $tmp = tempnam(sys_get_temp_dir(), 'php-mime');
                        if (!is_string($tmp)) {
                            throw new MimeTypeException('Could not create temporary directory to extract archive');
                        }
                        unlink($tmp);
                        mkdir($tmp);
                        $zip->extractTo($tmp);

                        $allFiles = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator(
                                $tmp,
                                RecursiveDirectoryIterator::SKIP_DOTS
                            )
                        );
                        $filtered = array_filter(
                            iterator_to_array($allFiles),
                            function (SplFileInfo $item) use ($file, $tmp) {
                                return fnmatch("{$tmp}/{$file['name']}", $item->getPathname());
                            }
                        );

                        (new Filesystem())->remove($tmp);
                        if (!count($filtered)) {
                            return false;
                        }
                    } else {
                        if ($zip->locateName($file['name']) === false) {
                            return false;
                        }
                    }

                    if ($file['content'] !== null) {
                        if ($zip->getFromName($file['name']) !== $file['content']) {
                            return false;
                        }
                    }

                    if ($file['binary'] !== null) {
                        $content = $zip->getFromName($file['name']);
                        if ($content === false) {
                            throw new MimeTypeException("Could not read the content of file '{$file['name']}' from archive");
                        }
                        if ($this->isBinary($content) !== $file['binary']) {
                            return false;
                        }
                    }
                }

                return true;
            }
        } finally {
            $zip->close();
        }

        return false;
    }

    private function isBinary(string $content): bool
    {
        $content = substr($content, 0, 512);

        return substr_count($content, '^ -~') / 512 > 0.3
            || substr_count($content, "\x00") > 0;
    }
}
