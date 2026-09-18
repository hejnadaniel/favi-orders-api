<?php

declare(strict_types=1);

namespace App\Tests\Order\Domain;

use App\Order\Domain\DecimalAmount;
use App\Order\Domain\Exception\InvalidOrderException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalAmountTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideWellFormedAmounts(): iterable
    {
        yield 'integer gets two fractional digits' => ['100', '100.00'];
        yield 'one fractional digit is padded' => ['2490.5', '2490.50'];
        yield 'two fractional digits are kept' => ['2490.55', '2490.55'];
        yield 'zero is allowed' => ['0', '0.00'];
        yield 'max integer digits (12)' => ['999999999999.99', '999999999999.99'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideMalformedAmounts(): iterable
    {
        yield 'negative' => ['-1.00'];
        yield 'three fractional digits' => ['1.234'];
        yield 'comma separator' => ['1,50'];
        yield 'empty' => [''];
        yield 'text' => ['abc'];
        yield 'one past max integer digits (13)' => ['1234567890123'];
        yield 'trailing dot' => ['1.'];
        yield 'leading dot' => ['.5'];
        yield 'float notation' => ['1e3'];
    }

    #[DataProvider('provideWellFormedAmounts')]
    public function testNormalisesToTwoFractionalDigits(string $raw, string $expected): void
    {
        $amount = DecimalAmount::fromString($raw);

        self::assertSame($expected, $amount->value);
    }

    #[DataProvider('provideMalformedAmounts')]
    public function testRejectsMalformedInput(string $raw): void
    {
        $this->expectException(InvalidOrderException::class);

        DecimalAmount::fromString($raw);
    }
}
