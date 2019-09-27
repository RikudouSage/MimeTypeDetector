# Mime Type Detector for PHP

Uses magic numbers (aka magic bytes) to detect the
content media type.

## Installation

`composer require rikudou/mime-type-detector`

## Usage

Simply construct a new object and call the
`getMimeType()` method:

```php
<?php

use Rikudou\MimeTypeDetector\MimeTypeDetector;

$mimeTypeDetector = new MimeTypeDetector();
var_dump($mimeTypeDetector->getMimeType('/path/to/file.jpeg'));
// will print image/jpeg
```

This can be kind of slow, because all definitions
are checked, even those like `apk` and `jar`
which need looking into the zip archive to determine
the type of content.

If you don't need checking for those, you can disable
looking into archives (see below in advanced usage).

## Advanced usage

### Custom definitions

You can supply custom mime type definitions as the
first argument:

```php
<?php

use Rikudou\MimeTypeDetector\MimeTypeDetector;

/** @var array $myDefinitions */

$detector = new MimeTypeDetector($myDefinitions);
```

The definitions format is described in 
`ConfigNormalizerInterface` and basically goes as this:

```
[
'mime_types' => [
    'mimeType' => [
        0 => [
            'length' => 1,
            'offset' => 0,
            'binary' => null,
            'archive' => false,
            'parent' => null,
            'files' => [
                0 => [
                    'name' => 'path/to/file/in/archive'
                    'dir' => false,
                    'pattern' => false,
                    'binary' => null,
                    'content' => null
                ]
            ],
            'bytes' => [
                0 => 'ff',
            ]
        ]
    ]
]
```

#### List of properties

- `mime_types` - This is the root array key and must
be present.
- `mediaType` - This is the media/mime type, e.g. 
`image/jpeg` which is also a key of the array definitions.
The value may either be array of definitions or a 
definition itself.
- `parent` - The parent definition, all properties of 
the parent will be merged with the child definition.
All the properties below that specify *required* are
not required if the parent has them set. Null means
no parent. The parent must exist and can (and should)
be defined after the child.
- `length` **required** - The length of the bytes to get
from the file.
- `offset` - It's the offset in bytes
from beginning of file.
- `bytes` **required** - The bytes that should be at
given offset with given length. Can be a string or an
array of strings. The bytes are checked in an `or`
relation, e.g. if any one matches, it's a match.
Can include * and ? for any characters and any single
character respectively (shell patterns).
- `binary` - Whether the file should be binary or not.
Can be null which means no check.
- `archive` - Whether the file is an archive, which
implies that files inside the archive should be checked
for existence/content etc.
- `files` **required if archive is true** - Array of
files inside the archive that should be checked. Can
be a string or array of values:
    - `name` - the name of the file that should
    be present in the archive,
    - `dir` - set to true if the `name` is a path to
    directory, not a file. The archive must be
    extracted for this to work, so use with caution,
    - `pattern` - set to true if the name contains
    a shell pattern (`*`, `?`). The archive must
    be extracted for this to work, so use with caution,
    - `binary` - whether the file in `name` should be
    binary or not. Null means no check,
    - `content` - If not null, check if the file
    content equals to given string

### Turning off archive traversing

As archive traversing can be kind of slow, you can
disable it, if you don't need any of these types:

- `application/vnd.android.package-archive` -
Android `apk` file
- `application/java-archive` - Java `jar` archive
- `application/x-xpinstall` - Mozilla `xpi` files
- `application/epub+zip` - `epub` books
- `application/x-itunes-ipa` - iOS `ipa` files
(these are particularly resource extensive to find)
- `application/vnd.google-earth.kmz` - Google Earth
`kmz` files

All of them will be reported as `application/zip`.

To turn these off simply set a custom `ConfigNormalizer`
with `$advancedDetection` set to false:

```php
<?php

use Rikudou\MimeTypeDetector\Config\ConfigNormalizer;
use Rikudou\MimeTypeDetector\MimeTypeDetector;

$config = new ConfigNormalizer(false);
$detector = new MimeTypeDetector(null, $config);

```

### Turning off individual mime types

If you don't want to check for certain mime types
(for example because you need to detect e.g. `apk`
files but don't want to suffer the resource penalty
for all other zip-based files), you can turn them off
using custom `ConfigNormalizer`:

```php
```php
<?php

use Rikudou\MimeTypeDetector\Config\ConfigNormalizer;
use Rikudou\MimeTypeDetector\MimeTypeDetector;

$config = new ConfigNormalizer(true, [
    'application/x-itunes-ipa'
]); // this will check all types but ipa files
$detector = new MimeTypeDetector(null, $config);

$config = new ConfigNormalizer(false, [
    'image/jpeg'
]); // this won't check any zip-based types and jpeg files
```

List of all types and their detections is in
[mime.yaml](config/mime.yaml).
