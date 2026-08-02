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
        $excludedRealPaths = $this->resolveExcludes($excludes, $rootDir);
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
            fn (string $path): bool => !$this->isExcluded($path, $excludedRealPaths),
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
    private function resolveExcludes(array $excludes, string $rootDir): array
    {
        $resolved = [];

        foreach ($excludes as $exclude) {
            $realPath = realpath($this->resolvePath($exclude, $rootDir));

            if ($realPath !== false) {
                $resolved[] = $realPath;
            }
        }

        return $resolved;
    }

    /**
     * @param string[] $excludedRealPaths
     */
    private function isExcluded(string $realPath, array $excludedRealPaths): bool
    {
        foreach ($excludedRealPaths as $excluded) {
            if ($realPath === $excluded || str_starts_with($realPath, $excluded . '/')) {
                return true;
            }
        }

        return false;
    }
}
