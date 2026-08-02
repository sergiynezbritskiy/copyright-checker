<?php

declare(strict_types=1);

namespace CopyrightChecker\Scan;

use RuntimeException;
use Symfony\Component\Finder\Finder;

final class FileScanner
{
    /**
     * @param string[] $paths
     * @param string[] $extensions
     * @param string[] $excludes
     * @return string[] Absolute, deduplicated, sorted file paths.
     */
    public function scan(array $paths, array $extensions, array $excludes, string $rootDir): array
    {
        $rootRealPath = realpath($rootDir);
        $rootRealPath = $rootRealPath === false ? rtrim($rootDir, '/') : $rootRealPath;

        $normalizedExcludes = $this->normalizeExcludes($excludes);
        $extensionSet = array_values(array_unique(array_map(
            static fn (string $ext): string => strtolower(ltrim($ext, '.')),
            $extensions,
        )));

        $files = [];

        foreach ($paths as $path) {
            $resolved = $this->resolvePath($path, $rootDir);

            if (!file_exists($resolved)) {
                throw new RuntimeException(sprintf('Path "%s" does not exist.', $path));
            }

            if (is_dir($resolved)) {
                foreach ($this->scanDirectory($resolved, $extensionSet) as $file) {
                    $files[$file] = true;
                }

                continue;
            }

            $realPath = (string) realpath($resolved);
            $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

            if (in_array($extension, $extensionSet, true)) {
                $files[$realPath] = true;
            }
        }

        $result = array_keys(array_filter(
            $files,
            fn (string $path): bool => !$this->isExcluded($path, $rootRealPath, $normalizedExcludes),
            ARRAY_FILTER_USE_KEY,
        ));

        sort($result);

        return $result;
    }

    /**
     * @param string[] $extensionSet
     * @return string[]
     */
    private function scanDirectory(string $dir, array $extensionSet): array
    {
        if ($extensionSet === []) {
            return [];
        }

        $finder = new Finder();
        $finder->in($dir)->files();

        foreach ($extensionSet as $extension) {
            $finder->name('*.' . $extension);
        }

        $files = [];

        foreach ($finder as $fileInfo) {
            $realPath = $fileInfo->getRealPath();

            if ($realPath !== false) {
                $files[] = $realPath;
            }
        }

        return $files;
    }

    private function resolvePath(string $path, string $rootDir): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return rtrim($rootDir, '/') . '/' . $path;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * @param string[] $excludes
     * @return string[]
     */
    private function normalizeExcludes(array $excludes): array
    {
        $normalized = array_map(
            static fn (string $exclude): string => trim(str_replace('\\', '/', $exclude), '/'),
            $excludes,
        );

        return array_values(array_filter($normalized, static fn (string $exclude): bool => $exclude !== ''));
    }

    /**
     * An exclude value containing a "/" is matched as a path relative to the project root
     * (e.g. "app/code/Vendor/Module/Test"). A bare name with no "/" (e.g. "vendor", "Test")
     * is matched as a path segment at any depth, mirroring how tools like .gitignore treat
     * bare names -- this lets a single "Test" entry exclude every directory named "Test"
     * anywhere in the tree, not just one sitting directly under the project root. Neither
     * form requires the excluded path to actually exist on disk.
     *
     * @param string[] $excludes
     */
    private function isExcluded(string $realPath, string $rootRealPath, array $excludes): bool
    {
        $relativePath = str_starts_with($realPath, $rootRealPath . '/')
            ? substr($realPath, strlen($rootRealPath) + 1)
            : $realPath;
        $segments = explode('/', $relativePath);

        foreach ($excludes as $exclude) {
            if (str_contains($exclude, '/')) {
                if ($relativePath === $exclude || str_starts_with($relativePath, $exclude . '/')) {
                    return true;
                }

                continue;
            }

            if (in_array($exclude, $segments, true)) {
                return true;
            }
        }

        return false;
    }
}
