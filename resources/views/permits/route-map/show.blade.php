@extends('layouts.app')

@php
    $pageTitle = 'Peta Rute Izin';
    $pageDescription = trim((optional($permit->vehicle)->plate_number ?? '-') . ' - ' . (optional($permit->employee)->name ?? '-'));
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body route-map-panel">
            <div class="quick-actions">
                <a class="button" href="{{ route('permits.index') }}">Kembali</a>
            </div>

            <dl class="scan-result__details">
                <div>
                    <dt>Nama</dt>
                    <dd>{{ optional($permit->employee)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Parkir</dt>
                    <dd>{{ $permit->parkingLocationCodes() ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Rute</dt>
                    <dd>{{ $routeMapData['route_label'] ?: ($permit->route_raw ?? '-') }}</dd>
                </div>
            </dl>

            @if (! $routeMapData['has_route'])
                <x-alert type="warning" class="layout-gap">
                    Rute belum tersedia atau perlu review.
                </x-alert>
            @elseif ($routeMapData['missing_segments'])
                <x-alert type="warning" class="layout-gap">
                    Segmen belum dikurasi: {{ implode(', ', $routeMapData['missing_segments']) }}
                </x-alert>
            @endif

            <div
                x-data="sirikaRoutePreview({
                    map: @js($routeMapData['map']),
                    segments: @js($routeMapData['segments'])
                })"
            >
                <div x-ref="map" class="route-map-canvas"></div>
            </div>
        </div>
    </section>
@endsection
