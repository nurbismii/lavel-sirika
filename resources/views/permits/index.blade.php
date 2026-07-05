@extends('layouts.app')

@php
    $pageTitle = 'Izin Kendaraan';
    $pageDescription = 'Daftar izin kendaraan hasil import dan status review.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Daftar Izin</h2>
            <p class="panel-subtitle">Tampilan read-only untuk izin kendaraan yang sudah masuk ke tabel final.</p>

            <div class="quick-actions layout-gap">
                <form method="POST" action="{{ route('permits.qr.bulk-generate') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Bulk Generate QR Aktif</button>
                </form>
            </div>

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
                            <th>Aksi QR</th>
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
                                <td>{{ optional($permit->parkingLocation)->code ?? '-' }}</td>
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
                                    <div class="table-actions">
                                        @if (! $activeToken && $permit->status === \App\Models\VehiclePermit::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('permits.qr.generate', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Generate QR</button>
                                            </form>
                                        @endif

                                        @if ($activeToken)
                                            <a class="button" href="{{ route('permits.qr.show', $permit) }}">Lihat QR</a>
                                            <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Renew &amp; Print</button>
                                            </form>
                                            <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Renew</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Belum ada data izin kendaraan. Gunakan modul Import Excel untuk membuat data awal.</td>
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
