<?php

namespace Tests\Feature;

use App\Support\SafeRedirect;
use Tests\TestCase;

class GoogleAuthRedirectTest extends TestCase
{
    public function test_external_absolute_urls_are_rejected(): void
    {
        $fallback = 'http://localhost/request/content';

        $this->assertSame($fallback, SafeRedirect::sanitize('https://evil.com/steal', $fallback));
        $this->assertSame($fallback, SafeRedirect::sanitize('//evil.com', $fallback));
        $this->assertSame($fallback, SafeRedirect::sanitize('javascript:alert(1)', $fallback));
    }

    public function test_backslash_and_relative_traps_are_rejected(): void
    {
        $fallback = 'http://localhost/request/content';

        $this->assertSame($fallback, SafeRedirect::sanitize('/\\evil.com', $fallback));
        $this->assertSame($fallback, SafeRedirect::sanitize('evil.com', $fallback));
        $this->assertSame($fallback, SafeRedirect::sanitize('', $fallback));
        $this->assertSame($fallback, SafeRedirect::sanitize(null, $fallback));
    }

    public function test_internal_paths_and_same_host_urls_are_accepted(): void
    {
        config(['app.url' => 'http://localhost']);
        $fallback = 'http://localhost/request/content';

        $this->assertSame(url('/attend/abc'), SafeRedirect::sanitize('/attend/abc', $fallback));
        $this->assertSame('http://localhost/attend/abc', SafeRedirect::sanitize('http://localhost/attend/abc', $fallback));
    }

    public function test_the_oauth_redirect_route_never_stores_an_external_target(): void
    {
        $this->get(route('google.redirect', ['redirect' => 'https://evil.com']));

        $this->assertSame(route('request.content'), session('google_auth_redirect'));
    }
}
