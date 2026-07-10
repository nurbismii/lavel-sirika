@extends('layouts.app')

@php
    $quickActions = [
        ['label' => 'Import Excel', 'route' => 'imports.index', 'primary' => true],
        ['label' => 'Kelola Izin', 'route' => 'permits.index'],
        ['label' => 'Laporan Izin', 'route' => 'reports.permits.index'],
        ['label' => 'Laporan Scan', 'route' => 'reports.scans.index'],
        ['label' => 'Master Rute', 'route' => 'road-segments.index'],
        ['label' => 'Scan QR', 'route' => 'scan.index'],
    ];
@endphp

@section('content')
    <section class="page-section">
        <div class="grid stats-grid">
            <x-stat-card label="Segmen Rute Aktif" :value="$activeRoadSegments" note="Master rute resmi dari PDF VDNI" />
            <x-stat-card label="Izin Aktif" :value="$activePermits" note="Izin yang sudah disetujui dan berlaku" />
            <x-stat-card label="Perlu Review" :value="$reviewPermits" note="Izin yang perlu verifikasi lanjutan" />
            <x-stat-card label="QR Aktif" :value="$activeQrTokens" note="Token QR valid untuk discan" />
            <x-stat-card label="QR Kadaluwarsa" :value="$expiredQrTokens" note="Token aktif yang melewati masa berlaku" />
            <x-stat-card label="Scan Hari Ini" :value="$todayScans" note="Semua hasil scan hari ini" />
            <x-stat-card label="Scan Invalid Hari Ini" :value="$todayInvalidScans" note="Scan dengan token tidak valid" />
            <x-stat-card label="User Aktif" :value="$activeUsers" note="Akun yang dapat login" />
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Quick Actions</h2>
            <p class="panel-subtitle">Akses cepat ke modul import, daftar izin, referensi rute, dan scan.</p>

            <div class="quick-actions layout-gap">
                @foreach ($quickActions as $index => $action)
                    @if (auth()->user()->canAccessRoute($action['route']))
                        <a class="button {{ ! empty($action['primary']) ? 'button-primary' : '' }}" href="{{ route($action['route']) }}">
                            {{ $action['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-section grid dashboard-grid">
        <div class="panel">
            <div class="panel-body">
                <h2 class="panel-title">Ringkasan Status Izin</h2>
                <p class="panel-subtitle">Komposisi status izin kendaraan pada data final.</p>

                <ul class="summary-list">
                    @foreach ($permitStatusSummary as $summary)
                        <li class="summary-list__item">
                            <span class="summary-list__label">{{ $summary['label'] }}</span>
                            <span class="summary-list__value">{{ number_format($summary['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <h2 class="panel-title">Hasil Scan 7 Hari</h2>
                <p class="panel-subtitle">Distribusi hasil scan QR dalam tujuh hari terakhir.</p>

                <ul class="summary-list">
                    @foreach ($scanResultSummary as $summary)
                        <li class="summary-list__item">
                            <span class="summary-list__label">{{ $summary['label'] }}</span>
                            <span class="summary-list__value">{{ number_format($summary['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Aktivitas Terbaru</h2>
            <p class="panel-subtitle">Review izin, pembuatan QR, dan scan terakhir yang tercatat.</p>

            <ul class="activity-list">
                @forelse ($activityFeed as $activity)
                    <li class="activity-list__item">
                        <div>
                            <p class="activity-list__type">{{ $activity['type'] }}</p>
                            <p class="activity-list__title">{{ $activity['title'] }}</p>
                            <p class="activity-list__description">{{ $activity['description'] }}</p>
                            <p class="activity-list__meta">{{ $activity['meta'] }}</p>
                        </div>

                        @if ($activity['occurred_at'])
                            <time class="activity-list__time" datetime="{{ $activity['occurred_at']->toIso8601String() }}">
                                {{ $activity['occurred_at']->format('d M Y H:i') }}
                            </time>
                        @endif
                    </li>
                @empty
                    <li class="activity-list__empty">Belum ada aktivitas operasional.</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
