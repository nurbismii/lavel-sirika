@extends('layouts.app')

@php
    $pageTitle = 'Review Izin';
    $pageDescription = 'Koreksi data izin sebelum aktivasi dan generate QR.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Review Izin</h2>
                    <p class="panel-subtitle">{{ optional($permit->employee)->name ?? '-' }} - {{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
                </div>

                <div class="quick-actions">
                    <a class="button" href="{{ route('permits.show', $permit) }}">Detail</a>
                    <a class="button" href="{{ route('permits.index') }}">Daftar Izin</a>
                </div>
            </div>

            @if ($errors->has('review'))
                <x-alert type="danger" class="layout-gap">{{ $errors->first('review') }}</x-alert>
            @endif

            @if ($errors->has('activation'))
                <x-alert type="danger" class="layout-gap">{{ $errors->first('activation') }}</x-alert>
            @endif

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
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><span class="status-pill">{{ $permit->status }}</span></dd>
                </div>
            </dl>

            <form class="form-stack layout-gap" method="POST" action="{{ route('permits.review.update', $permit) }}">
                @csrf

                <div class="form-grid">
                    <div class="form-field">
                        <label for="parking_location_id">Lokasi Parkir</label>
                        <select class="form-control" id="parking_location_id" name="parking_location_id">
                            <option value="">Pilih lokasi parkir</option>
                            @foreach ($parkingLocations as $parkingLocation)
                                <option value="{{ $parkingLocation->id }}" {{ (string) old('parking_location_id', $permit->parking_location_id) === (string) $parkingLocation->id ? 'selected' : '' }}>
                                    {{ $parkingLocation->code }}
                                </option>
                            @endforeach
                        </select>
                        @error('parking_location_id')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="form-field">
                    <label for="route_raw">Rute</label>
                    <textarea class="form-control" id="route_raw" name="route_raw" rows="4">{{ old('route_raw', $permit->route_raw) }}</textarea>
                    @error('route_raw')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="review_note">Catatan Review</label>
                    <textarea class="form-control" id="review_note" name="review_note" rows="4">{{ old('review_note', $permit->review_note) }}</textarea>
                    @error('review_note')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Simpan Review</button>
                    <button
                        class="button button-primary"
                        type="submit"
                        formaction="{{ route('permits.review.activate', $permit) }}"
                        formmethod="POST"
                    >
                        Aktifkan Izin
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
