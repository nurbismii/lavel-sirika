@extends('layouts.app')

@php
    $pageTitle = 'Editor Koordinat Rute';
    $pageDescription = $segment->code . ' - ' . $segment->name;
@endphp

@section('content')
    <section
        class="page-section panel"
        x-data="sirikaRoadSegmentEditor({
            map: @js($routeMap),
            initialPoints: @js($segmentMap['points']),
            segmentCode: @js($segment->code)
        })"
    >
        <div class="panel-body route-editor-layout">
            <div class="route-map-panel">
                <div x-ref="map" class="route-map-canvas"></div>
            </div>

            <aside class="route-editor-side">
                <div>
                    <h2 class="panel-title">{{ $segment->code }}</h2>
                    <p class="panel-subtitle">{{ $segment->name }}</p>
                </div>

                <dl class="scan-result__details">
                    <div>
                        <dt>Awal</dt>
                        <dd>{{ $segment->start_location ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Akhir</dt>
                        <dd>{{ $segment->end_location ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $segmentMap['coordinate_status'] }}</dd>
                    </div>
                    <div>
                        <dt>Titik</dt>
                        <dd x-text="points.length"></dd>
                    </div>
                </dl>

                @if ($errors->any())
                    <x-alert type="danger" class="layout-gap">
                        {{ $errors->first() }}
                    </x-alert>
                @endif

                @if ($canEditMap)
                    <form
                        x-ref="form"
                        method="POST"
                        action="{{ route('road-segments.map.update', $segment) }}"
                        class="form-stack"
                    >
                        @csrf
                        <input type="hidden" name="save_mode" x-bind:value="saveMode">
                        <input type="hidden" name="points_json" x-bind:value="pointsJson()">

                        <div class="quick-actions">
                            <button class="button button-primary" type="button" x-on:click="submit('complete')">Simpan Complete</button>
                            <button class="button" type="button" x-on:click="submit('draft')">Simpan Draft</button>
                            <button class="button" type="button" x-on:click="undoPoint" x-bind:disabled="points.length === 0">Undo</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('road-segments.map.reset', $segment) }}">
                        @csrf
                        @method('DELETE')
                        <button class="button" type="submit">Reset Koordinat</button>
                    </form>
                @endif

                <a class="button" href="{{ route('road-segments.index') }}">Kembali</a>
            </aside>
        </div>
    </section>
@endsection
