<?php

namespace App\Services\Permits;

use App\Models\PermitToken;
use App\Models\VehiclePermit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PermitTokenService
{
    private const DUPLICATE_ACTIVE_TOKEN_MESSAGE = 'QR aktif sudah tersedia. Gunakan renew untuk membuat QR baru.';

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

    private function createTokenForPermit(VehiclePermit $permit): array
    {
        $plainToken = Str::random(64);

        $token = PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', $plainToken),
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
