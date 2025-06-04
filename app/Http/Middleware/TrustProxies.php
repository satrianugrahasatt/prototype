<?php

namespace App\Http\Middleware;

// Jika Anda menggunakan Laravel versi < 9, baris ini benar.
// use Fideloper\Proxy\TrustProxies as Middleware;
// Jika Anda menggunakan Laravel versi >= 9, seharusnya:
use Illuminate\Http\Middleware\TrustProxies as Middleware;

use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    // Modifikasi di sini:
    protected $proxies = '*'; // Ini mengizinkan semua proxy, aman untuk Cloud Run

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO | // Pastikan ini ada
        Request::HEADER_X_FORWARDED_AWS_ELB; // Untuk Laravel < 9. Di Laravel >= 9, ini diganti Request::HEADER_X_FORWARDED_PREFIX
}
