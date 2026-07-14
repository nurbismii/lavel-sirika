@extends('layouts.app')

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Laporan Izin</h2>
                    <p class="panel-subtitle">Filter izin kendaraan, review, QR, dan rute untuk kebutuhan operasional.</p>
                </div>

                <div class="form-actions">
                    <a class="button button-primary" href="{{ route('reports.permits.export', request()->query()) }}">Export Excel</a>
                    <a class="button button-primary" href="{{ route('reports.permits.needs-review.export', request()->query()) }}">Export Perlu Review</a>
                </div>
            </div>

            @if ($errors->any())
                <x-alert type="danger" class="layout-gap">
                    {{ $errors->first() }}
                </x-alert>
            @endif

            <div class="status-summary layout-gap">
                @foreach ([\App\Models\VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review', \App\Models\VehiclePermit::STATUS_ACTIVE => 'Aktif', \App\Models\VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa', \App\Models\VehiclePermit::STATUS_REVOKED => 'Dicabut'] as $status => $label)
                    <div class="status-summary__item">
                        <span class="status-summary__label">{{ $label }}</span>
                        <strong class="status-summary__value">{{ $statusSummary[$status] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>

            <form class="filter-panel layout-gap" method="GET" action="{{ route('reports.permits.index') }}">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="status">Status Izin</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Semua status</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="qr_status">Status QR</label>
                        <select class="form-control" id="qr_status" name="qr_status">
                            <option value="">Semua QR</option>
                            @foreach ($qrStatusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['qr_status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="review_status">Status Review</label>
                        <select class="form-control" id="review_status" name="review_status">
                            <option value="">Semua review</option>
                            @foreach ($reviewStatusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['review_status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="parking_location_id">Parkir</label>
                        <select class="form-control" id="parking_location_id" name="parking_location_id">
                            <option value="">Semua parkir</option>
                            @foreach ($parkingLocations as $parkingLocation)
                                <option value="{{ $parkingLocation->id }}" {{ (string) ($filters['parking_location_id'] ?? '') === (string) $parkingLocation->id ? 'selected' : '' }}>
                                    {{ $parkingLocation->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="permit_color">Warna</label>
                        <select class="form-control" id="permit_color" name="permit_color">
                            <option value="">Semua warna</option>
                            @foreach ($colorOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['permit_color'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="source">Sumber</label>
                        <select class="form-control" id="source" name="source">
                            <option value="">Semua sumber</option>
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['source'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
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
                    <a class="button" href="{{ route('reports.permits.index') }}">Reset</a>
                </div>
            </form>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Status</th>
                            <th>Status QR</th>
                            <th>Review</th>
                            <th>Sumber</th>
                            <th>Segmen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            <tr>
                                <td>{{ optional($permit->employee)->nik ?? '-' }}</td>
                                <td>{{ optional($permit->employee)->name ?? '-' }}</td>
                                <td>{{ optional($permit->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional($permit->parkingLocation)->code ?? '-' }}</td>
                                <td><span class="status-pill">{{ $permit->status }}</span></td>
                                <td>
                                    <span class="status-pill">{{ $reports->qrStatusLabel($permit) }}</span>
                                    @if ($permit->activeToken && $permit->activeToken->expires_at)
                                        <div class="muted-text">{{ $permit->activeToken->expires_at->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $permit->reviewed_at ? 'Sudah direview' : 'Belum direview' }}
                                    @if ($permit->reviewer)
                                        <div class="muted-text">{{ $permit->reviewer->name }}</div>
                                    @endif
                                </td>
                                <td>{{ $permit->source ?? '-' }}</td>
                                <td>{{ (int) ($permit->route_segments_count ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Tidak ada izin yang sesuai dengan filter laporan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $permits->links() }}
            </div>
        </div>
    </section>
@endsection
