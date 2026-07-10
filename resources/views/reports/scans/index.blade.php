@extends('layouts.app')

@php
    $exportFilters = array_filter($filters, function ($value) {
        return $value !== null && $value !== '';
    });
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Laporan Scan</h2>
                    <p class="panel-subtitle">Pantau aktivitas scan QR berdasarkan tanggal, hasil, scanner, dan kendaraan.</p>
                </div>

                <a class="button button-primary" href="{{ route('reports.scans.export', $exportFilters) }}">Export Excel</a>
            </div>

            @if ($errors->any())
                <x-alert type="danger" class="layout-gap">
                    {{ $errors->first() }}
                </x-alert>
            @endif

            <form class="filter-panel layout-gap" method="GET" action="{{ route('reports.scans.index') }}">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="date_from">Tanggal Awal</label>
                        <input class="form-control" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}">
                    </div>

                    <div class="form-field">
                        <label for="date_to">Tanggal Akhir</label>
                        <input class="form-control" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}">
                    </div>

                    <div class="form-field">
                        <label for="result">Hasil Scan</label>
                        <select class="form-control" id="result" name="result">
                            <option value="">Semua hasil</option>
                            @foreach ($resultOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['result'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="scanner_id">Scanner</label>
                        <select class="form-control" id="scanner_id" name="scanner_id">
                            <option value="">Semua scanner</option>
                            @foreach ($scannerOptions as $scanner)
                                <option value="{{ $scanner->id }}" {{ (string) ($filters['scanner_id'] ?? '') === (string) $scanner->id ? 'selected' : '' }}>
                                    {{ $scanner->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-field layout-gap">
                    <label for="search">Cari NIK, Nama, atau Plat</label>
                    <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: 15090187 atau DT 6899 SA">
                </div>

                <div class="form-actions layout-gap">
                    <button class="button button-primary" type="submit">Terapkan Filter</button>
                    <a class="button" href="{{ route('reports.scans.index') }}">Reset</a>
                </div>
            </form>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Hasil</th>
                            <th>Scanner</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Status Izin</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scanLogs as $scanLog)
                            @php
                                $permit = $scanLog->permit;
                            @endphp
                            <tr>
                                <td>{{ optional($scanLog->scanned_at)->format('d M Y H:i') ?? '-' }}</td>
                                <td><span class="status-pill">{{ $reports->resultLabel($scanLog->result) }}</span></td>
                                <td>{{ optional($scanLog->scanner)->name ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->employee)->nik ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->employee)->name ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->parkingLocation)->code ?? '-' }}</td>
                                <td>{{ optional($permit)->status ?? '-' }}</td>
                                <td>{{ $scanLog->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Tidak ada scan yang sesuai dengan filter laporan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $scanLogs->links() }}
            </div>
        </div>
    </section>
@endsection
