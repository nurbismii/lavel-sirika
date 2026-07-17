@extends('layouts.app')

@php
    $pageTitle = 'Izin Kendaraan';
    $pageDescription = 'Daftar izin kendaraan hasil import dan status review.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Daftar Izin</h2>
                    <p class="panel-subtitle">Kelola izin kendaraan, status review, rute, dan QR.</p>
                </div>

                @if (auth()->user()->canAccessRoute('permits.qr.bulk-generate'))
                    <form method="POST" action="{{ route('permits.qr.bulk-generate') }}">
                        @csrf
                        <button class="button button-primary" type="submit">Bulk Generate QR Aktif</button>
                    </form>
                @endif
            </div>

            <div class="status-summary layout-gap">
                @foreach ([\App\Models\VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review', \App\Models\VehiclePermit::STATUS_ACTIVE => 'Aktif', \App\Models\VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa', \App\Models\VehiclePermit::STATUS_REVOKED => 'Dicabut'] as $status => $label)
                    <div class="status-summary__item">
                        <span class="status-summary__label">{{ $label }}</span>
                        <strong class="status-summary__value">{{ $statusSummary[$status] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>

            <form class="filter-panel layout-gap" method="GET" action="{{ route('permits.index') }}">
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
                        <label for="permit_color">Warna</label>
                        <select class="form-control" id="permit_color" name="permit_color">
                            <option value="">Semua warna</option>
                            @foreach ($colorOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['permit_color'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
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
                </div>

                <div class="form-field layout-gap">
                    <label for="search">Cari NIK, Nama, atau Plat</label>
                    <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: 15090187 atau DT 6899 SA">
                </div>

                <div class="form-actions layout-gap">
                    <button class="button button-primary" type="submit">Filter</button>
                    <a class="button" href="{{ route('permits.index') }}">Reset</a>
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
                            <th>Warna</th>
                            <th>Status</th>
                            <th>Status QR</th>
                            <th>Sumber</th>
                            <th>Rute</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            @php
                                $activeToken = $permit->activeToken;
                                $latestToken = $permit->latestToken;
                                $qrLabel = 'Belum dibuat';

                                if ($activeToken && $activeToken->expires_at && $activeToken->expires_at->isPast()) {
                                    $qrLabel = 'QR Kadaluwarsa';
                                } elseif ($activeToken) {
                                    $qrLabel = 'QR Aktif';
                                } elseif ($latestToken && $latestToken->status === \App\Models\PermitToken::STATUS_REVOKED) {
                                    $qrLabel = 'QR Dicabut';
                                }
                            @endphp
                            <tr>
                                <td>{{ optional($permit->employee)->nik ?? '-' }}</td>
                                <td>{{ optional($permit->employee)->name ?? '-' }}</td>
                                <td>{{ optional($permit->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ $permit->parkingLocationCodes() ?: '-' }}</td>
                                <td>{{ $permit->permit_color ?? '-' }}</td>
                                <td><span class="status-pill">{{ $permit->status ?? '-' }}</span></td>
                                <td>
                                    <span class="status-pill">{{ $qrLabel }}</span>
                                    @if ($activeToken)
                                        <div class="muted-text">{{ optional($activeToken->expires_at)->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td>{{ $permit->source ?? '-' }}</td>
                                <td>
                                    <div>{{ $permit->route_raw ?? '-' }}</div>
                                    <div class="muted-text">{{ $permit->routeSegments->count() }} segmen</div>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a class="button" href="{{ route('permits.show', $permit) }}">Detail</a>

                                        @if (auth()->user()->canAccessRoute('permits.edit'))
                                            <a class="button" href="{{ route('permits.edit', $permit) }}">Edit</a>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.review.edit') && $permit->status === \App\Models\VehiclePermit::STATUS_NEEDS_REVIEW)
                                            <a class="button button-primary" href="{{ route('permits.review.edit', $permit) }}">Review</a>
                                        @endif

                                        <a class="button" href="{{ route('permits.route-map.show', $permit) }}">Lihat Rute</a>

                                        @if (auth()->user()->canAccessRoute('permits.qr.generate') && ! $activeToken && $permit->status === \App\Models\VehiclePermit::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('permits.qr.generate', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Generate QR</button>
                                            </form>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.qr.show') && $activeToken)
                                            <a class="button" href="{{ route('permits.qr.show', $permit) }}">Lihat QR</a>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.qr.print') && $activeToken)
                                            <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Renew &amp; Print</button>
                                            </form>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.qr.renew') && $activeToken)
                                            <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Renew</button>
                                            </form>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.deactivate') && $permit->status === \App\Models\VehiclePermit::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('permits.deactivate', $permit) }}" onsubmit="return confirm('Cabut izin ini? Semua QR aktif untuk izin ini akan dinonaktifkan.');">
                                                @csrf
                                                <button class="button" type="submit">Cabut Izin</button>
                                            </form>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.destroy') && $permit->status !== \App\Models\VehiclePermit::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('permits.destroy', $permit) }}" onsubmit="return confirm('Hapus izin ini secara permanen? Riwayat scan tetap disimpan tanpa referensi izin.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button" type="submit">Hapus Permanen</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">Belum ada data izin kendaraan. Gunakan modul Import Excel untuk membuat data awal.</td>
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
