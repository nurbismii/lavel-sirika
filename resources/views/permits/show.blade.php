@extends('layouts.app')

@php
    $pageTitle = 'Detail Izin';
    $pageDescription = 'Detail izin kendaraan dan status review.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Detail Izin</h2>
                    <p class="panel-subtitle">{{ optional($permit->employee)->name ?? '-' }} - {{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
                </div>

                <div class="quick-actions">
                    <a class="button" href="{{ route('permits.index') }}">Kembali</a>
                    <a class="button" href="{{ route('permits.route-map.show', $permit) }}">Lihat Rute</a>
                    @if (auth()->user()->canAccessRoute('permits.review.edit') && $permit->status === \App\Models\VehiclePermit::STATUS_NEEDS_REVIEW)
                        <a class="button button-primary" href="{{ route('permits.review.edit', $permit) }}">Review</a>
                    @endif
                </div>
            </div>

            <dl class="detail-grid layout-gap">
                <div>
                    <dt>NIK</dt>
                    <dd>{{ optional($permit->employee)->nik ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Nama</dt>
                    <dd>{{ optional($permit->employee)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Departemen</dt>
                    <dd>{{ optional($permit->employee)->department ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Parkir</dt>
                    <dd>{{ optional($permit->parkingLocation)->code ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Warna</dt>
                    <dd>{{ $permit->permit_color ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Status Izin</dt>
                    <dd><span class="status-pill">{{ $permit->status }}</span></dd>
                </div>
                <div>
                    <dt>Sumber</dt>
                    <dd>{{ $permit->source ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Reviewer</dt>
                    <dd>{{ optional($permit->reviewer)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Waktu Review</dt>
                    <dd>{{ optional($permit->reviewed_at)->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>

            <div class="detail-section layout-gap">
                <h3 class="panel-title">Rute</h3>
                <p class="panel-subtitle">{{ $permit->route_raw ?? '-' }}</p>
                <p class="muted-text">{{ $permit->routeSegments->count() }} segmen tersimpan</p>
            </div>

            <div class="detail-section layout-gap">
                <h3 class="panel-title">Segmen Rute</h3>
                @if ($permit->routeSegments->isNotEmpty())
                    <div class="route-segment-list">
                        @foreach ($permit->routeSegments as $segment)
                            <span class="status-pill">{{ $segment->code }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="panel-subtitle">Belum ada segmen rute tersimpan.</p>
                @endif
            </div>

            <div class="detail-section layout-gap">
                <h3 class="panel-title">Catatan Review</h3>
                <p class="panel-subtitle">{{ $permit->review_note ?? '-' }}</p>
            </div>
        </div>
    </section>
@endsection
