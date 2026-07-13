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
    return cameras.length ? cameras[0].id : null;
}
