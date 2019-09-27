<?php

namespace Rikudou\MimeTypeDetector\Config;

final class ConfigBuilder
{
    private const IMAGES = [
        'image/jpeg',
        'image/x-icon',
        'image/gif',
        'image/x-canon-cr2',
        'image/tiff',
        'image/png',
        'image/x-dpx',
        'image/x-exr',
        'image/x-bpg',
        'image/vnd.adobe.photoshop',
        'image/bmp',
        'image/fits',
        'image/heic',
        'image/vnd.djvu',
        'image/webp',
    ];

    private const AUDIO = [
        'audio/8svx',
        'audio/aiff',
        'audio/wav',
        'audio/mpeg',
        'audio/flac',
        'audio/midi',
    ];

    private const VIDEO = [
        'video/3gpp',
        'video/x-ms-asf',
        'video/avi',
        'video/x-matroska',
        'video/mpeg',
        'application/ogg',
    ];

    private const OFFICE = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/rtf',
    ];

    private const ARCHIVE = [
        'application/x-compress',
        'application/x-bzip2',
        'application/vnd.rar',
        'application/x-lzip',
        'application/x-xar',
        'application/x-tar',
        'application/x-gzip',
        'application/x-xz',
        'application/x-7z-compressed',
        'application/zlib',
        'application/zip',
    ];

    private const OTHER = [
        'application/x-iso9660-image',
        'application/vnd.tcpdump.pcap',
        'application/x-pcapng',
        'application/x-rpm',
        'application/x-sqlite3',
        'application/vnd.palm',
        'application/java-vm',
        'application/postscript',
        'application/vnd.microsoft.portable-executable',
        'application/x-apple-diskimage',
        'application/x-nes-rom',
        'application/vnd.ms-cab-compressed',
        'application/x-x509-user-cert',
        'application/dicom',
        'application/font-woff',
        'text/xml',
        'application/wasm',
        'application/vnd.adobe.flash-movie',
        'application/vnd.debian.binary-package',
    ];

    private const ZIP_BASED = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.android.package-archive',
        'application/java-archive',
        'application/x-xpinstall',
        'application/epub+zip',
        'application/x-itunes-ipa',
        'application/vnd.google-earth.kmz',
    ];

    private const EXTENSIVE = [
        'application/x-itunes-ipa',
    ];

    private $disabled = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        $instance = new self();

        return $instance;
    }

    public static function createSaneDefaults(): self
    {
        return self::create()
            ->withoutZipBased()
            ->withOffice()
            ->withoutExtensive()
            ->with([
                'application/vnd.android.package-archive',
                'application/java-archive',
                'application/epub+zip',
            ]);
    }

    public function withImages(): self
    {
        return $this->with(self::IMAGES);
    }

    public function withoutImages(): self
    {
        return $this->without(self::IMAGES);
    }

    public function withAudio(): self
    {
        return $this->with(self::AUDIO);
    }

    public function withoutAudio(): self
    {
        return $this->without(self::AUDIO);
    }

    public function withVideo(): self
    {
        return $this->with(self::VIDEO);
    }

    public function withoutVideo(): self
    {
        return $this->without(self::VIDEO);
    }

    public function withOffice(): self
    {
        return $this->with(self::OFFICE);
    }

    public function withoutOffice(): self
    {
        return $this->without(self::OFFICE);
    }

    public function withArchives(): self
    {
        return $this->with(self::ARCHIVE);
    }

    public function withoutArchives(): self
    {
        return $this->without(self::ARCHIVE);
    }

    public function withOther(): self
    {
        return $this->with(self::OTHER);
    }

    public function withoutOther(): self
    {
        return $this->without(self::OTHER);
    }

    public function withZipBased(): self
    {
        return $this->with(self::ZIP_BASED);
    }

    public function withoutZipBased(): self
    {
        return $this->without(self::ZIP_BASED);
    }

    public function withExtensive(): self
    {
        return $this->with(self::EXTENSIVE);
    }

    public function withoutExtensive(): self
    {
        return $this->without(self::EXTENSIVE);
    }

    public function with(array $types): self
    {
        $instance = clone $this;
        $instance->enable($types);

        return $instance;
    }

    public function without(array $types): self
    {
        $instance = clone $this;
        $instance->disable($types);

        return $instance;
    }

    public function build(): ConfigNormalizerInterface
    {
        return new ConfigNormalizer(true, $this->disabled);
    }

    private function disable(array $types)
    {
        foreach ($types as $type) {
            if (!in_array($type, $this->disabled, true)) {
                $this->disabled[] = $type;
            }
        }
    }

    private function enable(array $types)
    {
        foreach ($types as $type) {
            if (in_array($type, $this->disabled, true)) {
                unset($this->disabled[array_search($type, $this->disabled)]);
            }
        }
    }
}
