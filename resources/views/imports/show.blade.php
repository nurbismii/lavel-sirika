@extends('layouts.app')

@php
    $pageTitle = 'Preview Import';
    $pageDescription = 'Periksa hasil staging sebelum data izin ditulis permanen.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">{{ $batch->filename }}</h2>
            <p class="panel-subtitle">Status batch: <strong>{{ $batch->status }}</strong></p>

            @if ($batch->error_summary)
                <x-alert type="warning">{{ $batch->error_summary }}</x-alert>
            @endif

            <div class="stats-grid">
                <x-stat-card label="Total Row" :value="$batch->total_rows" />
                <x-stat-card label="Valid" :value="$batch->success_rows" />
                <x-stat-card label="Invalid" :value="$batch->failed_rows" />
                <x-stat-card label="Needs Review" :value="$batch->review_rows" />
            </div>

            @if ($batch->status === \App\Models\ImportBatch::STATUS_PREVIEWED && ($batch->success_rows + $batch->review_rows) > 0)
                <form method="POST" action="{{ route('imports.commit', $batch) }}" style="margin-top: 16px;">
                    @csrf
                    <button class="button button-primary" type="submit">Commit Data Aman</button>
                </form>
            @endif
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <div class="toolbar">
                <a class="button" href="{{ route('imports.show', $batch) }}">Semua</a>
                <a class="button" href="{{ route('imports.show', [$batch, 'status' => 'valid']) }}">Valid</a>
                <a class="button" href="{{ route('imports.show', [$batch, 'status' => 'needs_review']) }}">Needs Review</a>
                <a class="button" href="{{ route('imports.show', [$batch, 'status' => 'invalid']) }}">Invalid</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Rute</th>
                            <th>Warna</th>
                            <th>Status</th>
                            <th>Issue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $data = $row->normalized_data ?: [];
                                $issues = array_merge($row->errors ?: [], $row->warnings ?: []);
                            @endphp
                            <tr>
                                <td>{{ $row->row_number }}</td>
                                <td>{{ $data['nik'] ?? '-' }}</td>
                                <td>{{ $data['employee_name'] ?? '-' }}</td>
                                <td>{{ $data['plate_number'] ?? '-' }}</td>
                                <td>{{ $data['parking_location_code'] ?? '-' }}</td>
                                <td>{{ $data['route_raw'] ?? '-' }}</td>
                                <td>{{ $data['permit_color'] ?? '-' }}</td>
                                <td><span class="status-pill">{{ $row->status }}</span></td>
                                <td>{{ $issues ? implode('; ', $issues) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Tidak ada row untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $rows->links() }}
            </div>
        </div>
    </section>
@endsection
