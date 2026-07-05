@extends('layouts.app')

@php
    $pageTitle = 'Print Kartu QR';
    $pageDescription = 'Kartu kecil per izin kendaraan.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <p>SIRIKA VDNI</p>
            <p>Plat</p>
            <p>{{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
            <p>Nama</p>
            <p>{{ optional($permit->employee)->name ?? '-' }}</p>
            <p>Parkir</p>
            <p>{{ optional($permit->parkingLocation)->code ?? '-' }}</p>
            <p>Berlaku sampai</p>
            <p>{{ optional($token->expires_at)->format('d M Y') ?? '-' }}</p>

            <div class="layout-gap">
                {!! $qrSvg !!}
            </div>

            <div class="quick-actions layout-gap">
                <button class="button button-primary" type="button" onclick="window.print()">Print</button>
                <a class="button" href="{{ route('permits.qr.show', $permit) }}">Kembali</a>
            </div>
        </div>
    </section>
@endsection
