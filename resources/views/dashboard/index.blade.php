@extends('layouts.app')

@php
    $quickActions = [
        ['label' => 'Import Excel', 'route' => 'imports.index'],
        ['label' => 'Kelola Izin', 'route' => 'permits.index'],
        ['label' => 'Master Rute', 'route' => 'road-segments.index'],
        ['label' => 'Scan QR', 'route' => 'scan.index'],
    ];
@endphp

@section('content')
    <section class="page-section">
        <div class="grid stats-grid">
            <x-stat-card label="Segmen Rute Aktif" :value="$activeRoadSegments" note="Master rute resmi dari PDF VDNI" />
            <x-stat-card label="User Aktif" :value="$activeUsers" note="Akun yang dapat login" />
            <x-stat-card label="Izin Aktif" :value="$activePermits" note="Belum ada data izin" />
            <x-stat-card label="Perlu Review" :value="$reviewPermits" note="Import belum dijalankan" />
            <x-stat-card label="Scan Hari Ini" :value="$todayScans" note="Scanner belum aktif" />
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Quick Actions</h2>
            <p class="panel-subtitle">Akses cepat ke alur kerja yang tersedia pada fondasi fase ini.</p>

            <div class="quick-actions layout-gap">
                @foreach ($quickActions as $index => $action)
                    @if (Route::has($action['route']))
                        <a class="button {{ $index === 0 ? 'button-primary' : '' }}" href="{{ route($action['route']) }}">
                            {{ $action['label'] }}
                        </a>
                    @else
                        <span class="button button--disabled" aria-disabled="true">
                            <span class="button__label">{{ $action['label'] }}</span>
                            <span class="button__status">Tersedia di Task 7</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-section">
        <x-alert type="warning">
            Phase 1 menyiapkan fondasi. Import Excel, QR code, scanner kamera, dan peta highlight rute akan diaktifkan pada fase berikutnya.
        </x-alert>
    </section>
@endsection
