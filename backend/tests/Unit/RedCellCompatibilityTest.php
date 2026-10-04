<?php

namespace Tests\Unit;

use App\Services\RedCellCompatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedCellCompatibilityTest extends TestCase
{
    #[DataProvider('compatibilityCases')]
    public function test_red_cell_compatibility(string $donor, string $recipient, bool $expected): void
    {
        self::assertSame($expected, (new RedCellCompatibility)->isCompatible($donor, $recipient));
    }

    public static function compatibilityCases(): array
    {
        return [
            'O negative to AB positive' => ['O-', 'AB+', true],
            'O positive to A positive' => ['O+', 'A+', true],
            'O positive to A negative' => ['O+', 'A-', false],
            'A negative to AB negative' => ['A-', 'AB-', true],
            'B positive to AB positive' => ['B+', 'AB+', true],
            'AB positive to A positive' => ['AB+', 'A+', false],
            'same type' => ['A+', 'A+', true],
            'case and whitespace normalization' => [' o- ', 'ab+', true],
        ];
    }
}
