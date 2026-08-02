# copyright-checker

CLI tool that scans a project's source files and reports (or fixes) files whose copyright header block doesn't match one of the configured templates.

## Installation

```
composer require --dev sergiynezbritskiy/copyright-checker
```

## Usage

```
vendor/bin/copyright [options] [<file|directory> ...]
```

`<file|directory>` arguments are optional: if omitted, the `locations` array from `.copyright.dist` is used instead (see below). If neither is given, the command errors.

By default, only violations, fixes, and fix failures are printed per-file; passing Symfony Console's standard `-v` flag also prints an `[OK]` line for every file that already has a correct header. The summary line's counts always include all files regardless of verbosity.

### Options

| Option                     | Description                                                                                                                                                                                                                              |
|----------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `--exclude=<path>`         | Path to exclude from scanning (repeatable).                                                                                                                                                                                              |
| `--extension=<ext>`        | File extension to check, e.g. `php`, `xml`, `css`, `js` (repeatable). At least one is required.                                                                                                                                          |
| `--template=<text>`        | Literal copyright template text to check for (repeatable). At least one is required. Matching is exact — include whatever comment syntax (`/* */`, `<!-- -->`, `#`, ...) is appropriate for the file type directly in the template text. |
| `--colors` / `--no-colors` | Force ANSI colored output on/off. Default: `--no-colors`.                                                                                                                                                                                |
| `--fix`                    | Fix files whose header doesn't match. If more than one `--template` is configured, you'll be asked interactively which one to apply to every fixed file in the run (or pass `--fix-apply`).                                              |
| `--fix-apply=<N>`          | 1-based index into the merged `--template` list to apply non-interactively when fixing.                                                                                                                                                  |
| `--root-dir=<path>`        | Project root: where `.copyright.dist` is looked for and the base for relative paths. Defaults to the consuming project's root when installed via Composer.                                                                               |

### `.copyright.dist`

Any of the above (except `--root-dir`) can also be configured in a `.copyright.dist` file at the project root — a plain PHP file returning an array:

```php
<?php

return [
    'exclude' => ['vendor', 'var'],
    'extension' => ['php'],
    'template' => [
        <<<'TEMPLATE'
        /*
         * Copyright (c) 2026 My Company
         */
        TEMPLATE,
    ],
    'colors' => true,
    'fix' => false,
    'locations' => ['src', 'tests'],
];
```

CLI options take precedence over `.copyright.dist`: for array options (`exclude`, `extension`, `template`), passing the option on the CLI at all wholly replaces the file's array for that option (values are not merged/unioned).

`locations` is different: it's only a fallback for the `<file|directory>` arguments, used exactly when the command is invoked with none. Passing any path argument on the CLI ignores `locations` entirely (paths aren't merged with it).

### Standalone usage (without Composer)

Cloning this repository and running `bin/copyright` directly (rather than via `vendor/bin/copyright` as an installed dependency) requires `--root-dir` to be passed explicitly, since there's no consuming project to infer it from:

```
php bin/copyright src --root-dir=/path/to/project --extension=php --template="..."
```

## Known limitations

- `--fix` normalizes the whole file to LF line endings on write-back (not just the header block), so files authored with CRLF line endings will show a full-file line-ending diff after a fix.
- `--fix` replaces an outdated header in place by using the template's own first and last line as delimiters. For **single-line templates** (where the first and last line are the same line), there's no separate stable delimiter to anchor on — the whole line is the variable content. An old, differently-worded single-line header is therefore not detected as "outdated" and replaced; the new line is prepended above the untouched old one instead. Multi-line templates that wrap the variable content (year, company name, ...) in generic delimiter lines like `/*` / `*/` don't have this limitation.

## Development

```
docker compose build
docker compose run --rm php composer install
docker compose run --rm php composer run all
```
