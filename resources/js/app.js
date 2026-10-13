import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = L;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('sectorMap', (config) => ({
        map: null,
        layer: null,
        data: { high: null, low: null, ultra: null },
        colors: config.colors,

        async init() {
            const [high, low, ultra] = await Promise.all([
                fetch(config.highUrl).then((r) => r.json()),
                fetch(config.lowUrl).then((r) => r.json()),
                fetch(config.ultraUrl).then((r) => r.json()).catch(() => null),
            ]);
            this.data.high = high;
            this.data.low = low;
            this.data.ultra = ultra;

            this.map = L.map(this.$refs.map, { center: [31, -82.233], zoom: 6 });
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);

            this.renderSplit();

            // Re-render / re-color when the Livewire component changes server-side.
            window.addEventListener('split-changed', () => this.renderSplit());
            window.addEventListener('sectors-updated', () => this.restyle());

            // Leaflet needs a size-settled container; nudge it once mounted.
            setTimeout(() => this.map.invalidateSize(), 100);
        },

        editable(feature) {
            const id = feature.properties?.id;
            return id != null && id < 100 && feature.geometry.type === 'Polygon';
        },

        colorFor(sectorId) {
            const sectors = this.$wire.sectors || {};
            const active = this.$wire.activeSectors || [];
            const activeId = sectors[sectorId];
            if (activeId === undefined || activeId === null) {
                return 'gray';
            }
            const idx = active.indexOf(activeId);
            return idx >= 0 ? this.colors[idx] || 'gray' : 'gray';
        },

        styleFor(feature) {
            if (this.editable(feature)) {
                return { color: this.colorFor(feature.properties.id), weight: 2, fillOpacity: 0.2 };
            }
            return { color: 'gray', weight: 1, fillOpacity: 0 };
        },

        renderSplit() {
            if (this.layer) {
                this.map.removeLayer(this.layer);
                this.layer = null;
            }

            const fc = this.data[this.$wire.split] || this.data.high;
            if (!fc) {
                return;
            }

            this.layer = L.geoJSON(fc, {
                style: (feature) => this.styleFor(feature),
                onEachFeature: (feature, layer) => {
                    if (this.editable(feature)) {
                        layer.on('click', () => {
                            if (this.$wire.editMode) {
                                this.$wire.assignSector(feature.properties.id);
                            }
                        });
                    }
                },
            }).addTo(this.map);

            // Frame the airspace within the square viewport.
            const bounds = this.layer.getBounds();
            if (bounds.isValid()) {
                this.map.fitBounds(bounds, { padding: [10, 10] });
            }
        },

        restyle() {
            if (this.layer) {
                this.layer.setStyle((feature) => this.styleFor(feature));
            }
        },
    }));
});
// Render any <time data-local datetime="..."> element in the viewer's own
// timezone and locale. The server-rendered content acts as a fallback.
function localizeTimes() {
    document.querySelectorAll('time[data-local]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!isNaN(date)) {
            el.textContent = date.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
        }
    });
}

document.addEventListener('DOMContentLoaded', localizeTimes);
