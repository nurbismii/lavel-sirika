@extends('layouts.app')

@php
    $pageTitle = 'Import Excel';
    $pageDescription = 'Persiapan modul import database izin masuk kendaraan.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Status Modul</h2>
            <p class="panel-subtitle">Upload Excel aktif pada fase berikutnya. Phase 1 hanya menyiapkan route, layout, role, dan tabel import batch.</p>

            <ul>
                <li>Validasi header Excel akan dibuat pada Phase 2.</li>
                <li>Preview row valid, invalid, dan needs_review akan dibuat pada Phase 2.</li>
                <li>Data lama tidak akan ditimpa tanpa aturan update eksplisit.</li>
            </ul>
        </div>
    </section>
@endsection
