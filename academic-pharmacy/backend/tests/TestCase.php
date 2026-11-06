<?php

namespace Tests;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected const API = '/api/v1/store';

    protected function actingAsAdmin(?AdminUser $admin = null): AdminUser
    {
        $admin ??= AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    protected function idempotencyKey(): string
    {
        return 'test-'.Str::lower(Str::random(24));
    }

    /**
     * PNG válido generado sin la extensión GD (no disponible en todos los
     * entornos): cabecera + IHDR + IDAT comprimido + IEND.
     */
    protected function pngBytes(int $width = 80, int $height = 80): string
    {
        $chunk = function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00".str_repeat("\x8F\xBC\xA5", $width); // verde salvia
        }

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw))
            .$chunk('IEND', '');
    }
}
