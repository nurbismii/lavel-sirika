@extends('layouts.app')

@php
    $pageTitle = 'Print Kartu QR';
    $pageDescription = 'Kartu kecil per izin kendaraan.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body permit-card-print">
            <p class="panel-subtitle">Cetak ulang selalu membuat token QR baru. Simpan kartu terbaru dan abaikan kartu lama.</p>

            <div class="layout-gap permit-card">
                <div>
                    <p class="permit-card__brand">SIRIKA VDNI</p>

                    <p class="permit-card__label">Plat</p>
                    <p class="permit-card__value">{{ optional($permit->vehicle)->plate_number ?? '-' }}</p>

                    <p class="permit-card__label">Nama</p>
                    <p class="permit-card__value">{{ optional($permit->employee)->name ?? '-' }}</p>

                    <p class="permit-card__label">Parkir</p>
                    <p class="permit-card__value">{{ optional($permit->parkingLocation)->code ?? '-' }}</p>

                    <p class="permit-card__label">Berlaku sampai</p>
                    <p class="permit-card__value">{{ optional($token->expires_at)->format('d M Y') ?? '-' }}</p>
                </div>

                <div class="permit-card__qr">
                    {!! $qrSvg !!}
                </div>
            </div>

            <div class="quick-actions layout-gap no-print">
                <button class="button button-primary" type="button" onclick="window.print()">Print</button>
                <a class="button" href="{{ route('permits.qr.show', $permit) }}">Kembali</a>
            </div>
        </div>
    </section>
@endsection
