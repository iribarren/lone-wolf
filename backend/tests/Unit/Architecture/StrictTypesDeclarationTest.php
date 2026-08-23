<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Constitution III gate: every project source file MUST declare strict_types.
 * Pure reflection/filesystem test — boots no framework.
 */
final class StrictTypesDeclarationTest extends TestCase
{
    public function testEverySourceFileDeclaresStrictTypes(): void
    {
        $violations = [];

        foreach ($this->projectPhpFiles() as $file) {
            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents);

            if (!str_contains($contents, 'declare(strict_types=1);')) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame(
            [],
            $violations,
            sprintf("Files missing declare(strict_types=1);\n%s", implode("\n", $violations)),
        );
    }

    /** @return list<SplFileInfo> */
    private function projectPhpFiles(): array
    {
        $root = dirname(__DIR__, 3) . '/src';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }
}
