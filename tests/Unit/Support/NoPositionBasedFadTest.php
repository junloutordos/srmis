<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard: "FAD" must never be inferred from the free-text position
 * column — it matched FAD Staff/Clerk and leaked emails and approval rights.
 */
class NoPositionBasedFadTest extends TestCase
{
    public function test_app_code_does_not_infer_fad_from_position(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app'));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (preg_match("/position['\"]?\s*(\?\?\s*['\"]{2})?\s*,\s*['\"]FAD|'position',\s*'like',\s*'%FAD/", $src)) {
                $offenders[] = str_replace(dirname(__DIR__, 3).'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Position-based FAD checks remain in: '.implode(', ', $offenders));
    }
}
