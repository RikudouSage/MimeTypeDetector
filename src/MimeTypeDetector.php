<?php

namespace Rikudou\MimeTypeDetector;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

final class MimeTypeDetector
{
    private $config = [];
    /**
     * @var bool
     */
    private $advancedDetection;

    public function __construct(
        ?array $config = null,
        bool $advancedDetection = true
    )
    {
        if ($config === null) {
            $config = Yaml::parseFile(__DIR__ . '/../config/mime.yaml');
        }

        if (!isset($config['mime_types'])) {
            throw new MimeTypeException("The config array must contain a 'mime_types' root key");
        }

        $this->config = $config['mime_types'];
        $this->advancedDetection = $advancedDetection;
    }

    /**
     * @param string|SplFileInfo $file
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
            throw new MimeTypeException("The file must be a string, instance of SplFileInfo or object implementing __toString() method");
        }

        if (!is_file($file)) {
            throw new MimeTypeException("The file '{$file}' either does not exist or is not a file");
        }

        foreach ($this->config as $mimeType => $configurations) {
            if (isset($configurations['length']) || isset($configurations['parent'])) {
                $configurations = [$configurations];
            }

            foreach ($configurations as $configuration) {
                if (!$this->advancedDetection && isset($configuration['archive']) && $configuration['archive']) {
                    continue;
                }
                if (isset($configuration['parent'])) {
                    $configuration = $this->mergeWithParent($configuration);
                }
                $offset = $configuration['offset'] ?? 0;
                $length = $configuration['length'];
                $byteSequences = $configuration['bytes'];
                if (!is_array($byteSequences)) {
                    $byteSequences = [$byteSequences];
                }

                foreach ($byteSequences as $byteSequence) {
                    if (fnmatch(strtolower($byteSequence), $this->getBytes($file, $length, $offset))) {
                        if (
                            (!isset($configuration['archive']) || $configuration['archive'] === false)
                            || (
                                isset($configuration['archive'])
                                && $configuration['archive'] === true
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
            if ($hex === false) {
                throw new MimeTypeException("Could not convert the raw bytes to hexadecimal");
            }

            return $hex;
        } finally {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
        }
    }

    private function mergeWithParent(array $configuration): array
    {
        $parent = $configuration['parent'];
        if (!isset($this->config[$parent])) {
            throw new MimeTypeException("Invalid parent: '{$parent}'");
        }
        $parent = $this->config[$parent];
        if (!isset($configuration['length']) && isset($parent['length'])) {
            $configuration['length'] = $parent['length'];
        }
        if (!isset($configuration['offset']) && isset($parent['offset'])) {
            $configuration['offset'] = $parent['offset'];
        }

        $childBytes = $configuration['bytes'] ?? [];
        if (!is_array($childBytes)) {
            $childBytes = [$childBytes];
        }

        $parentBytes = $parent['bytes'] ?? [];
        if (!is_array($parentBytes)) {
            $parentBytes = [$parentBytes];
        }

        $configuration['bytes'] = array_merge($parentBytes, $childBytes);

        return $configuration;
    }

    private function matchesFiles(array $configuration, string $archiveFile): bool
    {
        $files = $configuration['files'] ?? [];
        $files = array_map(function ($item) {
            if (is_string($item)) {
                return [
                    'name' => $item,
                ];
            }
            return $item;
        }, $files);

        if (!count($files)) {
            throw new MimeTypeException('The files array cannot be empty');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($archiveFile) === true) {
                foreach ($files as $file) {
                    if (
                        (isset($file['dir']) && $file['dir'])
                        || (isset($file['pattern']) && $file['pattern'])
                    ) {
                        $tmp = tempnam(sys_get_temp_dir(), 'php-mime');
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

                    if (isset($file['content'])) {
                        if ($zip->getFromName($file['name']) !== $file['content']) {
                            return false;
                        }
                    }

                    if (isset($file['binary']) && $file['binary']) {
                        $content = $zip->getFromName($file['name']);
                        if (!$this->isBinary($content)) {
                            return false;
                        }
                    }
                }

                return true;
            }
        } finally {
            if (isset($zip)) {
                $zip->close();
            }
        }

        return false;
    }

    private function isBinary(string $content): bool
    {
        $content = substr($content, 0, 512);
        return substr_count($content, "^ -~") / 512 > 0.3
            || substr_count($content, "\x00") > 0;
    }
}
