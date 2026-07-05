@extends('layouts.app')

@php
    $pageTitle = 'Master Segmen Rute';
    $pageDescription = 'Daftar 26 segmen jalan resmi dari peta VDNI.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Daftar Segmen</h2>
            <p class="panel-subtitle">Tampilan read-only untuk data referensi rute yang sudah disiapkan pada fondasi fase ini.</p>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Lokasi Awal</th>
                            <th>Lokasi Akhir</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($segments as $segment)
                            <tr>
                                <td><strong>{{ $segment->code }}</strong></td>
                                <td>{{ $segment->name }}</td>
                                <td>{{ $segment->start_location }}</td>
                                <td>{{ $segment->end_location }}</td>
                                <td><span class="status-pill">{{ $segment->status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">Belum ada data segmen rute.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="layout-gap">
                {{ $segments->links() }}
            </div>
        </div>
    </section>
@endsection
