@extends('layouts.app')

@php
    $pageTitle = 'Edit Izin';
    $pageDescription = 'Perbarui data identitas, parkir, dan urutan rute izin kendaraan.';
    $selectedParkingLocationIds = array_map('strval', (array) old('parking_location_ids', $permit->parkingLocations->pluck('id')->all()));
    $selectedRoadSegmentIds = array_map('strval', (array) old('road_segment_ids', $permit->routeSegments->pluck('id')->all()));
    $orderedRoadSegments = $roadSegments->sortBy(function ($roadSegment) use ($selectedRoadSegmentIds) {
        $position = array_search((string) $roadSegment->id, $selectedRoadSegmentIds, true);

        return $position === false ? 100000 + $roadSegment->id : $position;
    });
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Edit Izin</h2>
                    <p class="panel-subtitle">{{ optional($permit->employee)->name ?? '-' }} - {{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
                </div>

                <div class="quick-actions">
                    <a class="button" href="{{ route('permits.show', $permit) }}">Batal</a>
                    <a class="button" href="{{ route('permits.index') }}">Daftar Izin</a>
                </div>
            </div>

            <form class="form-stack layout-gap" method="POST" action="{{ route('permits.update', $permit) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-field">
                        <label for="nik">NIK</label>
                        <input class="form-control" id="nik" name="nik" value="{{ old('nik', optional($permit->employee)->nik) }}" required>
                        @error('nik') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label for="name">Nama</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', optional($permit->employee)->name) }}" required>
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label for="plate_number">Nomor Plat</label>
                        <input class="form-control" id="plate_number" name="plate_number" value="{{ old('plate_number', optional($permit->vehicle)->plate_number) }}" required>
                        @error('plate_number') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label for="parking_location_ids">Lokasi Parkir</label>
                        <select class="form-control" id="parking_location_ids" name="parking_location_ids[]" multiple required>
                            @foreach ($parkingLocations as $parkingLocation)
                                <option value="{{ $parkingLocation->id }}" {{ in_array((string) $parkingLocation->id, $selectedParkingLocationIds, true) ? 'selected' : '' }}>
                                    {{ $parkingLocation->code }} - {{ $parkingLocation->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parking_location_ids') <span class="field-error">{{ $message }}</span> @enderror
                        @error('parking_location_ids.*') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-field">
                    <label for="road_segment_ids">Segmen Rute</label>
                    <p class="muted-text">Pilih segmen rute, lalu gunakan Naik/Turun untuk menentukan urutan perjalanan.</p>
                    <select class="form-control" id="road_segment_ids" name="road_segment_ids[]" multiple required size="8">
                        @foreach ($orderedRoadSegments as $roadSegment)
                            <option value="{{ $roadSegment->id }}" {{ in_array((string) $roadSegment->id, $selectedRoadSegmentIds, true) ? 'selected' : '' }}>
                                {{ $roadSegment->code }} - {{ $roadSegment->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-actions layout-gap">
                        <button class="button" type="button" data-route-order="up">Naik</button>
                        <button class="button" type="button" data-route-order="down">Turun</button>
                    </div>
                    @error('road_segment_ids') <span class="field-error">{{ $message }}</span> @enderror
                    @error('road_segment_ids.*') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">Simpan Perubahan</button>
                    <a class="button" href="{{ route('permits.show', $permit) }}">Batal</a>
                </div>
            </form>
        </div>
    </section>
@endsection

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var routeSelect = document.getElementById('road_segment_ids');

            document.querySelectorAll('[data-route-order]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var selectedOptions = Array.prototype.slice.call(routeSelect.selectedOptions);
                    var direction = button.dataset.routeOrder;

                    if (direction === 'up') {
                        selectedOptions.forEach(function (option) {
                            var previous = option.previousElementSibling;
                            if (previous && !previous.selected) {
                                routeSelect.insertBefore(option, previous);
                            }
                        });
                    } else {
                        selectedOptions.reverse().forEach(function (option) {
                            var next = option.nextElementSibling;
                            if (next && !next.selected) {
                                routeSelect.insertBefore(next, option);
                            }
                        });
                    }
                });
            });
        });
    </script>
