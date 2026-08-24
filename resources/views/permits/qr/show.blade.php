@extends('layouts.app')

@php
    $pageTitle = 'QR Digital';
    $pageDescription = 'Status QR izin kendaraan.';
    $currentValidity = collect([$token->expires_at, $permit->valid_until])
        ->filter()
        ->sortBy(fn ($date) => $date->timestamp)
        ->last();
    $suggestedValidity = $currentValidity
        ? $currentValidity->copy()->addYear()->toDateString()
        : now()->addYear()->toDateString();
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">QR Digital</h2>
            <p class="panel-subtitle">QR aktif dapat ditampilkan kembali. Gunakan renew hanya saat QR lama perlu dicabut dan diganti.</p>

            @if ($qrSvg)
                <div class="layout-gap permit-card__qr">{!! $qrSvg !!}</div>
            @else
                <x-alert type="info" class="layout-gap">
                    QR ini dibuat sebelum fitur tampilan ulang tersedia. Gunakan renew untuk membuat QR baru.
                </x-alert>
            @endif

            <dl class="layout-gap detail-grid">
                <div>
                    <dt>Nama</dt>
                    <dd>{{ optional($permit->employee)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Lokasi Parkir</dt>
                    <dd>{{ $permit->parkingLocationCodes() ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Status Token</dt>
                    <dd>{{ $token->status }}</dd>
                </div>
                <div>
                    <dt>Berlaku Sampai</dt>
                    <dd>{{ optional($token->expires_at)->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>

            @if (auth()->user()->canAccessRoute('permits.qr.extend') && $permit->status === \App\Models\VehiclePermit::STATUS_ACTIVE && $token->expires_at)
                <form class="form-stack layout-gap no-print" method="POST" action="{{ route('permits.qr.extend', $permit) }}">
                    @csrf

                    <div class="form-field">
                        <label for="valid_until">Perpanjang Masa Berlaku Sampai</label>
                        <input
                            class="form-control"
                            id="valid_until"
                            name="valid_until"
                            type="date"
                            min="{{ now()->addDay()->toDateString() }}"
                            value="{{ old('valid_until', $suggestedValidity) }}"
                            required
                        >
                        <p class="muted-text">Perpanjangan mempertahankan kode QR saat ini. Gunakan Renew hanya untuk mengganti kode.</p>
                        @error('valid_until')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">Perpanjang Masa Berlaku</button>
                    </div>
                </form>
            @endif

            <div class="quick-actions layout-gap no-print">
                <a class="button" href="{{ route('permits.index') }}">Kembali</a>

                <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                    @csrf
                    <button class="button button-primary" type="submit">Renew QR 1 Tahun</button>
                </form>

                <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                    @csrf
                    <button class="button" type="submit">Print Kartu</button>
                </form>
            </div>
        </div>
    </section>
@endsection
