const labels = {
    environment: 'Kamera belakang',
    user: 'Kamera depan',
};

export function cameraConstraints(direction) {
    return { facingMode: { exact: direction } };
}

export function cameraDirectionLabel(direction) {
    return labels[direction] || labels.environment;
}

export function oppositeCameraDirection(direction) {
    return direction === 'user' ? 'environment' : 'user';
}

export function fallbackCameraId(cameras) {
    const rearCamera = cameras.find(({ label = '' }) => /back|rear|environment/i.test(label));

    return rearCamera ? rearCamera.id : (cameras.length ? cameras[0].id : null);
}

export function fallbackCameraIds(cameras) {
    const rearCameras = cameras.filter(({ label = '' }) => /back|rear|environment/i.test(label));
    const otherCameras = cameras.filter(({ label = '' }) => !/back|rear|environment/i.test(label));

    return [...new Set([...rearCameras, ...otherCameras].map(({ id }) => id).filter(Boolean))];
}

export function cameraPreflightError({ isSecureContext, hasMediaDevices }) {
    if (!isSecureContext) {
        return {
            name: 'SecurityError',
            message: 'Kamera hanya dapat digunakan melalui HTTPS atau localhost.',
        };
    }

    if (!hasMediaDevices) {
        return {
            name: 'NotSupportedError',
            message: 'Browser ini tidak mendukung akses kamera.',
        };
    }

    return null;
}

export function cameraErrorMessage(error) {
    const messages = {
        NotAllowedError: 'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser.',
        NotReadableError: 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi lain lalu coba lagi.',
        NotFoundError: 'Kamera tidak ditemukan di perangkat ini.',
        NotSupportedError: 'Browser ini tidak mendukung akses kamera. Gunakan browser versi terbaru.',
        SecurityError: 'Akses kamera tidak diizinkan pada halaman ini. Buka aplikasi melalui HTTPS atau localhost.',
    };

    return messages[error?.name] || 'Kamera gagal dimulai. Coba lagi.';
}
