@extends('layouts.app')

@php
    $pageTitle = 'Izin Kendaraan';
    $pageDescription = 'Persiapan manajemen izin masuk kendaraan.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Status Modul</h2>
            <p class="panel-subtitle">Manajemen izin aktif pada fase berikutnya. Phase 1 menyiapkan tabel vehicle_permits, permit_route_segments, dan permit_tokens.</p>

            <ul>
                <li>Status izin yang disiapkan: draft, needs_review, active, suspended, expired, revoked.</li>
                <li>QR hanya akan dibuat untuk izin active pada fase QR.</li>
                <li>Rute mentah dari Excel akan disimpan di route_raw pada fase import.</li>
            </ul>
        </div>
    </section>
@endsection
