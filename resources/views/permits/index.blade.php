@extends('layouts.app')

@php
    $pageTitle = 'Izin Kendaraan';
    $pageDescription = 'Daftar izin kendaraan hasil import dan status review.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Daftar Izin</h2>
            <p class="panel-subtitle">Tampilan read-only untuk izin kendaraan yang sudah masuk ke tabel final.</p>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Warna</th>
                            <th>Status</th>
                            <th>Sumber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            <tr>
                                <td>{{ optional($permit->employee)->nik ?? '-' }}</td>
                                <td>{{ optional($permit->employee)->name ?? '-' }}</td>
                                <td>{{ optional($permit->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional($permit->parkingLocation)->code ?? '-' }}</td>
                                <td>{{ $permit->permit_color ?? '-' }}</td>
                                <td><span class="status-pill">{{ $permit->status ?? '-' }}</span></td>
                                <td>{{ $permit->source ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">Belum ada data izin kendaraan. Gunakan modul Import Excel untuk membuat data awal.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $permits->links() }}
            </div>
        </div>
    </section>
@endsection
