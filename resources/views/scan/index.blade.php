@extends('layouts.app')

@php
    $pageTitle = 'Scan QR';
    $pageDescription = 'Validasi izin kendaraan melalui kamera atau input token manual.';
@endphp

@section('content')
    <section
        class="page-section panel"
        x-data="sirikaScan({
            verifyUrl: '{{ route('scan.verify') }}',
            csrfToken: '{{ csrf_token() }}'
        })"
    >
        <div class="panel-body">
            <div class="scan-layout">
                <div>
                    <h2 class="panel-title">Scanner Kamera</h2>
                    <p class="panel-subtitle">Gunakan kamera perangkat untuk membaca QR izin kendaraan.</p>
                    <p class="panel-subtitle">Kamera belakang digunakan sebagai default.</p>
                    <p class="panel-subtitle">Kamera aktif: <span x-text="cameraDirectionLabel"></span></p>

                    <div id="sirika-qr-reader" class="qr-reader layout-gap"></div>

                    <div class="quick-actions layout-gap">
                        <button class="button button-primary" type="button" x-on:click="startCamera" x-bind:disabled="cameraRunning || loading">
                            Mulai Kamera
                        </button>
                        <button class="button" type="button" x-on:click="stopCamera" x-bind:disabled="! cameraRunning">
                            Stop Kamera
                        </button>
                        <button class="button" type="button" x-on:click="switchCamera" x-bind:disabled="!cameraAvailable || loading">
                            Ganti Kamera
                        </button>
                    </div>
                </div>

                <div>
                    <h2 class="panel-title">Input Token Manual</h2>
                    <p class="panel-subtitle">Gunakan fallback ini jika kamera gagal membaca QR.</p>

                    <form class="form-stack layout-gap" x-on:submit.prevent="submitManual">
                        <div class="form-field">
                            <label for="manual-token">Token QR</label>
                            <input id="manual-token" type="text" x-model="manualToken" autocomplete="off">
                        </div>
                        <button class="button button-primary" type="submit" x-bind:disabled="loading">
                            Validasi Token
                        </button>
                    </form>

                    <div class="scan-result layout-gap" x-bind:class="'scan-result--' + (result ? result.result : 'empty')">
                        <template x-if="loading">
                            <p>Memvalidasi QR...</p>
                        </template>

                        <template x-if="! loading && ! result">
                            <p>Hasil scan akan tampil di sini.</p>
                        </template>

                        <template x-if="! loading && result">
                            <div>
                                <h3 x-text="result.message"></h3>
                                <template x-if="result.permit">
                                    <div class="scan-result__permit" data-scan-permit-result>
                                        <dl class="scan-result__details">
                                            <div x-show="result.permit.employee_name">
                                                <dt>Nama</dt>
                                                <dd x-text="result.permit.employee_name"></dd>
                                            </div>
                                            <div x-show="result.permit.plate_number">
                                                <dt>Plat</dt>
                                                <dd x-text="result.permit.plate_number"></dd>
                                            </div>
                                            <div x-show="result.permit.parking_code">
                                                <dt>Parkir</dt>
                                                <dd x-text="result.permit.parking_code"></dd>
                                            </div>
                                            <div x-show="result.permit.permit_color">
                                                <dt>Warna</dt>
                                                <dd x-text="result.permit.permit_color"></dd>
                                            </div>
                                            <div x-show="result.permit.route_raw">
                                                <dt>Rute</dt>
                                                <dd x-text="result.permit.route_raw"></dd>
                                            </div>
                                        </dl>

                                        <template x-if="result.permit.route_map">
                                            <div class="scan-route-map">
                                                <template x-if="result.permit.route_map.missing_segments && result.permit.route_map.missing_segments.length">
                                                    <div class="route-warning">
                                                        <span>Segmen belum dikurasi: </span>
                                                        <span x-text="result.permit.route_map.missing_segments.join(', ')"></span>
                                                    </div>
                                                </template>

                                                <div
                                                    x-data="sirikaRoutePreview({
                                                        map: result.permit.route_map.map,
                                                        segments: result.permit.route_map.segments
                                                    })"
                                                >
                                                    <div x-ref="map" class="route-map-canvas route-map-canvas--compact"></div>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="result.permit.route_map_warning">
                                            <div class="route-warning" x-text="result.permit.route_map_warning"></div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
