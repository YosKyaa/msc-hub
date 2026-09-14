<?php

namespace Tests\Unit;

use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateRenderServiceTest extends TestCase
{
    public function test_it_generates_a_png_qr_data_uri_for_the_verification_url(): void
    {
        $certificate = new Certificate([
            'verification_token' => (string) Str::uuid(),
        ]);

        $qr = app(CertificateRenderService::class)->qrDataUri($certificate);

        $this->assertStringStartsWith('data:image/png;base64,', $qr);
        $this->assertGreaterThan(500, strlen($qr));
    }
}
