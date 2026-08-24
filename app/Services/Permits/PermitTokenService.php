<?php

namespace App\Services\Permits;

use App\Models\PermitToken;
use App\Models\VehiclePermit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PermitTokenService
{
    private const DUPLICATE_ACTIVE_TOKEN_MESSAGE = 'QR aktif sudah tersedia. Gunakan renew untuk membuat QR baru.';

    private const MISSING_ACTIVE_TOKEN_MESSAGE = 'QR aktif belum tersedia. Buat QR terlebih dahulu.';

    public function generateForPermit(VehiclePermit $permit): array
    {
        return DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            $this->ensurePermitCanHaveQr($lockedPermit);

            if ($lockedPermit->activeToken) {
                throw new InvalidArgumentException(self::DUPLICATE_ACTIVE_TOKEN_MESSAGE);
            }

            return $this->createTokenForPermit($lockedPermit);
        });
    }

    public function renewForPermit(VehiclePermit $permit): array
    {
        return DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            $this->ensurePermitCanHaveQr($lockedPermit);

            PermitToken::where('vehicle_permit_id', $lockedPermit->id)
                ->where('status', PermitToken::STATUS_ACTIVE)
                ->update([
                    'status' => PermitToken::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

            return $this->createTokenForPermit($lockedPermit);
        });
    }

    public function activeForPermit(VehiclePermit $permit): array
    {
        return DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            $this->ensurePermitCanHaveQr($lockedPermit);

            $token = $lockedPermit->activeToken;

            if (! $token) {
                throw new InvalidArgumentException(self::MISSING_ACTIVE_TOKEN_MESSAGE);
            }

            $plainToken = $this->plainTokenForDisplay($token);

            if ($plainToken === null) {
                throw new InvalidArgumentException('QR aktif tidak dapat dibaca. Lakukan renew untuk membuat QR baru.');
            }

            return [
                'plain_token' => $plainToken,
                'permit_token' => $token,
                'qr_svg' => $this->renderSvg($plainToken),
            ];
        });
    }

    public function extendValidityForPermit(VehiclePermit $permit, string $validUntil): array
    {
        return DB::transaction(function () use ($permit, $validUntil) {
            $lockedPermit = VehiclePermit::query()
                ->whereKey($permit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePermitCanHaveQr($lockedPermit);

            $token = PermitToken::query()
                ->where('vehicle_permit_id', $lockedPermit->id)
                ->where('status', PermitToken::STATUS_ACTIVE)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $token) {
                throw new InvalidArgumentException(self::MISSING_ACTIVE_TOKEN_MESSAGE);
            }

            if ($token->expires_at === null) {
                throw new InvalidArgumentException('QR aktif tidak memiliki batas masa berlaku sehingga tidak perlu diperpanjang.');
            }

            $newExpiry = Carbon::createFromFormat('Y-m-d', $validUntil, config('app.timezone'))
                ->endOfDay();
            $currentLatestExpiry = collect([
                $token->expires_at,
                $lockedPermit->valid_until,
            ])->filter()->sortBy(function ($date) {
                return $date->timestamp;
            })->last();

            if ($currentLatestExpiry && ! $newExpiry->copy()->startOfDay()->gt($currentLatestExpiry->copy()->startOfDay())) {
                throw new InvalidArgumentException(
                    'Tanggal masa berlaku baru harus setelah ' . $currentLatestExpiry->format('d M Y') . '.'
                );
            }

            $oldTokenExpiry = $token->expires_at->copy();
            $oldPermitExpiry = $lockedPermit->valid_until ? $lockedPermit->valid_until->copy() : null;

            $lockedPermit->update([
                'valid_until' => $newExpiry->toDateString(),
            ]);
            $token->update([
                'expires_at' => $newExpiry,
            ]);

            return [
                'permit' => $lockedPermit->fresh(),
                'permit_token' => $token->fresh(),
                'old_permit_expiry' => $oldPermitExpiry,
                'old_token_expiry' => $oldTokenExpiry,
                'new_expiry' => $newExpiry,
            ];
        });
    }

    public function bulkGenerateForActivePermits(): array
    {
        $created = 0;
        $skipped = 0;

        VehiclePermit::with('activeToken')
            ->where('status', VehiclePermit::STATUS_ACTIVE)
            ->orderBy('id')
            ->chunkById(100, function ($permits) use (&$created, &$skipped) {
                foreach ($permits as $permit) {
                    try {
                        $this->generateForPermit($permit);
                        $created++;
                    } catch (InvalidArgumentException $exception) {
                        if ($exception->getMessage() !== self::DUPLICATE_ACTIVE_TOKEN_MESSAGE) {
                            throw $exception;
                        }

                        $skipped++;
                    }
                }
            });

        $skipped += VehiclePermit::where('status', '!=', VehiclePermit::STATUS_ACTIVE)->count();

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function renderSvg(string $plainToken): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(280),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($plainToken);
    }

    public function plainTokenForDisplay(PermitToken $token): ?string
    {
        if (empty($token->token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($token->token_encrypted);
        } catch (DecryptException $exception) {
            Log::warning('Permit QR token could not be decrypted for display.', [
                'permit_token_id' => $token->id,
            ]);

            return null;
        }
    }

    private function createTokenForPermit(VehiclePermit $permit): array
    {
        $plainToken = Str::random(64);

        $token = PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', $plainToken),
            'token_encrypted' => Crypt::encryptString($plainToken),
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        return [
            'plain_token' => $plainToken,
            'permit_token' => $token,
            'qr_svg' => $this->renderSvg($plainToken),
        ];
    }

    private function ensurePermitCanHaveQr(VehiclePermit $permit): void
    {
        if ($permit->status !== VehiclePermit::STATUS_ACTIVE) {
            throw new InvalidArgumentException('QR hanya dapat dibuat untuk izin aktif.');
        }
    }

    private function lockPermit(VehiclePermit $permit): VehiclePermit
    {
        return VehiclePermit::whereKey($permit->id)
            ->lockForUpdate()
            ->firstOrFail()
            ->load('activeToken');
    }
}
