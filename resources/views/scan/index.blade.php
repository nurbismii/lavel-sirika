@extends('layouts.app')

@php
    $pageTitle = 'Scan QR';
    $pageDescription = 'Persiapan halaman validasi izin oleh petugas security.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Status Modul</h2>
            <p class="panel-subtitle">Scanner kamera aktif pada fase berikutnya. Phase 1 menyiapkan akses security dan tabel scan_logs.</p>

            <ul>
                <li>QR invalid, expired, revoked, dan izin nonaktif akan ditolak pada fase scan.</li>
                <li>Setiap scan akan dicatat ke scan_logs.</li>
                <li>Peta rute akan ditampilkan setelah modul Leaflet route overlay aktif.</li>
            </ul>
        </div>
    </section>
@endsection
