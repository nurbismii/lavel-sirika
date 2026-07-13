@extends('layouts.app')

@php
    $pageTitle = 'Master Segmen Rute';
    $pageDescription = 'Koordinat rute internal VDNI berdasarkan peta resmi.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="route-map-panel">
                <div>
                    <h2 class="panel-title">Ringkasan Koordinat</h2>
                    @if ($canEditMap)
                        <a class="button button-primary" href="{{ route('road-segments.create') }}">Tambah Segmen</a>
                    @endif
                </div>

                <div class="route-stat-grid">
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['total'] }}</span>
                        <span>Total</span>
                    </div>
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['complete'] }}</span>
                        <span>Lengkap</span>
                    </div>
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['draft'] }}</span>
                        <span>Draft</span>
                    </div>
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['empty'] }}</span>
                        <span>Belum Dibuat</span>
                    </div>
                </div>

                <div
                    x-data="sirikaRoutePreview({
                        map: @js($routeMap),
                        segments: @js($mapSegments)
                    })"
                >
                    <div x-ref="map" class="route-map-canvas route-map-canvas--compact"></div>
                </div>
            </div>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Lokasi Awal</th>
                            <th>Lokasi Akhir</th>
                            <th>Status Data</th>
                            <th>Status Koordinat</th>
                            <th>Titik</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($segments as $segment)
                            @php
                                $polyline = $segment->polyline_json;
                                $pointCount = isset($polyline['points']) && is_array($polyline['points']) ? count($polyline['points']) : 0;
                                $coordinateStatus = $pointCount === 0
                                    ? 'empty'
                                    : (($polyline['status'] ?? 'draft') === 'complete' && $pointCount >= 2 ? 'complete' : 'draft');
                            @endphp
                            <tr>
                                <td><strong>{{ $segment->code }}</strong></td>
                                <td>{{ $segment->name }}</td>
                                <td>{{ $segment->start_location }}</td>
                                <td>{{ $segment->end_location }}</td>
                                <td><span class="status-pill">{{ $segment->status }}</span></td>
                                <td><span class="status-pill">{{ $coordinateStatus }}</span></td>
                                <td>{{ $pointCount }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a class="button" href="{{ route('road-segments.map', $segment) }}">
                                            {{ $canEditMap ? 'Edit Peta' : 'Lihat Peta' }}
                                        </a>
                                        @if ($canEditMap && $pointCount > 0)
                                            <form method="POST" action="{{ route('road-segments.map.reset', $segment) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button" type="submit">Reset</button>
                                            </form>
                                        @endif
                                        @if ($canEditMap && $segment->status === \App\Models\RoadSegment::STATUS_DRAFT && $coordinateStatus === 'complete')
                                            <form method="POST" action="{{ route('road-segments.activate', $segment) }}">@csrf<button class="button" type="submit">Aktifkan</button></form>
                                        @endif
                                        @if ($canEditMap && $segment->status === \App\Models\RoadSegment::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('road-segments.deactivate', $segment) }}">@csrf<button class="button" type="submit">Nonaktifkan</button></form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada data segmen rute.</td>
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
