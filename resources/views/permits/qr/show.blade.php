@extends('layouts.app')

@php
    $pageTitle = 'QR Digital';
    $pageDescription = 'Status QR izin kendaraan.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">QR Digital</h2>
            <p class="panel-subtitle">Token mentah tidak disimpan. QR tampil saat generate atau renew; cetak ulang akan membuat token baru.</p>

            @if ($qrSvg)
                <div class="layout-gap">{!! $qrSvg !!}</div>
            @else
                <x-alert type="info" class="layout-gap">
                    QR lama tidak bisa ditampilkan ulang karena token mentah tidak disimpan. Gunakan renew untuk membuat QR baru.
                </x-alert>
            @endif

            <dl class="layout-gap">
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
                    <dd>{{ optional($permit->parkingLocation)->code ?? '-' }}</dd>
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

            <div class="quick-actions layout-gap">
                <a class="button" href="{{ route('permits.index') }}">Kembali</a>

                <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                    @csrf
                    <button class="button button-primary" type="submit">Renew QR 1 Tahun</button>
                </form>

                <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                    @csrf
                    <button class="button" type="submit">Renew &amp; Print Kartu</button>
                </form>
            </div>
        </div>
    </section>
@endsection
