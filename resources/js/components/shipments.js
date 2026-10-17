export function shipmentForm(durations, serviceId = '', departedOn = '') {
    return {
        durations, serviceId: String(serviceId), departedOn,
        get estimatedArrival() {
            const duration = Number(this.durations[this.serviceId]);
            if (!this.departedOn || !Number.isInteger(duration) || duration <= 0) return 'Pilih jasa dan tanggal berangkat';
            const date = new Date(this.departedOn + 'T00:00:00Z');
            if (Number.isNaN(date.getTime())) return 'Periksa tanggal berangkat';
            date.setUTCDate(date.getUTCDate() + duration);
            return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'UTC' }).format(date);
        },
    };
}


const illustratedPorts = [
    { names: ['surabaya', 'tanjung perak'], x: 463, y: 362 },
    { names: ['jakarta', 'tanjung priok'], x: 283, y: 338 },
    { names: ['semarang', 'tanjung emas'], x: 379, y: 349 },
    { names: ['makassar', 'soekarno hatta'], x: 591, y: 291 },
    { names: ['manado'], x: 647, y: 174 },
    { names: ['bitung'], x: 666, y: 180 },
    { names: ['ternate'], x: 720, y: 224 },
    { names: ['tobelo', 'gamsungi'], x: 754, y: 188 },
    { names: ['ambon'], x: 714, y: 325 },
    { names: ['sorong'], x: 841, y: 246 },
    { names: ['jayapura'], x: 973, y: 275 },
    { names: ['balikpapan'], x: 520, y: 270 },
    { names: ['pontianak'], x: 407, y: 220 },
    { names: ['medan', 'belawan'], x: 123, y: 135 },
    { names: ['padang'], x: 168, y: 239 },
    { names: ['denpasar', 'benoa', 'bali'], x: 510, y: 383 },
];

export function shippingVoyage(origin, destination, progress = 0) {
    const find = (name, fallback) => illustratedPorts.find((port) => port.names.some((alias) => String(name).toLocaleLowerCase('id-ID').includes(alias))) ?? fallback;
    return {
        origin, destination, progress,
        start: find(origin, { x: 310, y: 340 }),
        end: find(destination, { x: 754, y: 188 }),
        get fraction() { return Math.max(0, Math.min(100, Number(this.progress) || 0)) / 100; },
        get controls() {
            const distance = Math.max(70, Math.abs(this.end.x - this.start.x) * 0.38);
            const direction = this.end.x >= this.start.x ? 1 : -1;
            return [{ x: this.start.x + distance * direction, y: this.start.y + 70 }, { x: this.end.x - distance * direction, y: this.end.y + 95 }];
        },
        get path() {
            const [first, second] = this.controls;
            return `M ${this.start.x} ${this.start.y} C ${first.x} ${first.y}, ${second.x} ${second.y}, ${this.end.x} ${this.end.y}`;
        },
        get boat() {
            const t = this.fraction;
            const u = 1 - t;
            const [first, second] = this.controls;
            return {
                x: u ** 3 * this.start.x + 3 * u ** 2 * t * first.x + 3 * u * t ** 2 * second.x + t ** 3 * this.end.x,
                y: u ** 3 * this.start.y + 3 * u ** 2 * t * first.y + 3 * u * t ** 2 * second.y + t ** 3 * this.end.y,
            };
        },
        get completedPath() {
            const t = this.fraction;
            const lerp = (a, b) => ({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });
            const [first, second] = this.controls;
            const a = lerp(this.start, first);
            const b = lerp(first, second);
            const d = lerp(a, b);
            return `M ${this.start.x} ${this.start.y} C ${a.x} ${a.y}, ${d.x} ${d.y}, ${this.boat.x} ${this.boat.y}`;
        },
        get viewBox() {
            const left = Math.max(0, Math.min(this.start.x, this.end.x) - 110);
            const top = Math.max(0, Math.min(this.start.y, this.end.y) - 95);
            const width = Math.max(300, Math.max(this.start.x, this.end.x) - left + 110);
            const height = Math.max(230, Math.max(this.start.y, this.end.y) - top + 115);
            return `${left} ${top} ${width} ${height}`;
        },
    };
}
