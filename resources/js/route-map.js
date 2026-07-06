import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function createBaseMap(element, mapConfig, options = {}) {
    if (!element || !mapConfig) {
        return null;
    }

    const bounds = [[0, 0], [Number(mapConfig.height), Number(mapConfig.width)]];
    const map = L.map(element, {
        crs: L.CRS.Simple,
        minZoom: options.minZoom || -2,
        maxZoom: options.maxZoom || 2,
        zoomControl: options.zoomControl !== false,
        attributionControl: false,
    });

    L.imageOverlay(mapConfig.image_url, bounds).addTo(map);
    map.fitBounds(bounds);
    map.setMaxBounds(bounds);

    return { leaflet: map, bounds };
}

function drawSegments(instance, segments, options = {}) {
    if (!instance || !Array.isArray(segments)) {
        return [];
    }

    const color = options.color || '#1e4fd6';

    return segments
        .filter((segment) => Array.isArray(segment.lat_lngs) && segment.lat_lngs.length >= 2)
        .map((segment) => {
            const line = L.polyline(segment.lat_lngs, {
                color,
                weight: options.weight || 4,
                opacity: options.opacity || 0.9,
            }).addTo(instance.leaflet);

            if (segment.code) {
                line.bindTooltip(segment.code, { permanent: false, direction: 'top' });
            }

            return line;
        });
}

window.sirikaRenderRouteMap = function (element, mapConfig, segments, options = {}) {
    const instance = createBaseMap(element, mapConfig, options);

    if (!instance) {
        return null;
    }

    drawSegments(instance, segments, options);

    return instance.leaflet;
};

window.sirikaRoutePreview = function ({ map, segments }) {
    return {
        map,
        segments,
        leaflet: null,

        init() {
            this.leaflet = window.sirikaRenderRouteMap(this.$refs.map, this.map, this.segments, {
                color: '#166534',
                weight: 4,
            });
        },
    };
};

window.sirikaRoadSegmentEditor = function ({ map, initialPoints, segmentCode }) {
    return {
        map,
        segmentCode,
        points: Array.isArray(initialPoints) ? initialPoints : [],
        leaflet: null,
        layerGroup: null,
        saveMode: 'draft',
        dirty: false,

        init() {
            const instance = createBaseMap(this.$refs.map, this.map, { maxZoom: 3 });
            this.leaflet = instance.leaflet;
            this.layerGroup = L.layerGroup().addTo(this.leaflet);
            this.leaflet.on('click', (event) => this.addPoint(event.latlng));
            this.redraw();

            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });
        },

        addPoint(latlng) {
            this.points.push({
                x: Number(latlng.lng.toFixed(2)),
                y: Number(latlng.lat.toFixed(2)),
            });
            this.dirty = true;
            this.redraw();
        },

        undoPoint() {
            if (!this.points.length) {
                return;
            }

            this.points.pop();
            this.dirty = true;
            this.redraw();
        },

        clearPoints() {
            this.points = [];
            this.dirty = true;
            this.redraw();
        },

        submit(mode) {
            this.saveMode = mode;
            this.dirty = false;
            this.$nextTick(() => this.$refs.form.submit());
        },

        latLngs() {
            return this.points.map((point) => [Number(point.y), Number(point.x)]);
        },

        pointsJson() {
            return JSON.stringify(this.points);
        },

        redraw() {
            this.layerGroup.clearLayers();

            const latLngs = this.latLngs();

            if (latLngs.length >= 2) {
                L.polyline(latLngs, {
                    color: '#1e4fd6',
                    weight: 5,
                    opacity: 0.95,
                }).addTo(this.layerGroup);
            }

            latLngs.forEach((latLng, index) => {
                L.circleMarker(latLng, {
                    radius: 6,
                    color: '#122033',
                    fillColor: '#ffffff',
                    fillOpacity: 1,
                    weight: 2,
                })
                    .bindTooltip(String(index + 1), { permanent: true, direction: 'center', className: 'route-point-label' })
                    .addTo(this.layerGroup);
            });
        },
    };
};
