<?php

namespace App\Http\Middleware;

// Jika Anda menggunakan Laravel versi < 9, baris ini benar.
// use Fideloper\Proxy\TrustProxies as Middleware;
// Jika Anda menggunakan Laravel versi >= 9, seharusnya:
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request; // Tidak ada baris kosong di atas baris ini

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB; // atau Request::HEADER_X_FORWARDED_PREFIX untuk Laravel 9+
}
