<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PhoneNormalizer();
    }

    #[DataProvider('validPhones')]
    public function testNormalizesToE164(string $raw, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($raw));
    }

    /** @return iterable<string, array{string, string}> */
    public static function validPhones(): iterable
    {
        yield 'formatted' => ['+7 (900) 123-45-67', '+79001234567'];
        yield 'eight' => ['89001112233', '+79001112233'];
        yield 'plus' => ['+79005557788', '+79005557788'];
        yield 'spaces' => ['8 900 444 33 22', '+79004443322'];
        yield 'ten digits' => ['9001234567', '+79001234567'];
    }

    public function testRejectsShortNumber(): void
    {
        self::assertNull($this->normalizer->normalize('123'));
    }
}
