const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export function revenueChart(points) {
    let observer;
    let canvas;

    return {
        points,
        activeIndex: null,
        init() {
            this.$nextTick(() => {
                canvas = this.$refs.canvas;
                observer = new ResizeObserver(() => this.draw());
                observer.observe(canvas);
                this.draw();
            });
        },
        destroy() { observer?.disconnect(); },
        get maximum() {
            const value = Math.max(...this.points.map((point) => point.value), 0);
            if (!value) return 100000;
            const magnitude = 10 ** Math.floor(Math.log10(value));
            return Math.ceil(value / magnitude / 5) * magnitude * 5;
        },
        get ticks() { return Array.from({ length: 6 }, (_, index) => this.maximum * (5 - index) / 5); },
        get dateTicks() {
            const count = Math.min(6, this.points.length);
            return Array.from({ length: count }, (_, index) => {
                const pointIndex = count === 1 ? 0 : Math.round(index * (this.points.length - 1) / (count - 1));
                return { ...this.points[pointIndex], position: count === 1 ? 50 : index / (count - 1) * 100 };
            });
        },
        get activePoint() { return this.points[this.activeIndex] ?? null; },
        get tooltipPosition() {
            const position = this.points.length === 1 ? 50 : this.activeIndex / (this.points.length - 1) * 100;
            return Math.max(15, Math.min(85, position));
        },
        currency(value) { return rupiah.format(value); },
        compactCurrency(value) {
            if (!value) return '0';
            for (const [unit, divisor] of [['M', 1e9], ['jt', 1e6], ['rb', 1e3]]) {
                if (value >= divisor) return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value / divisor) + unit;
            }
            return 'Rp ' + Math.round(value);
        },
        select(index) {
            this.activeIndex = Math.max(0, Math.min(this.points.length - 1, index));
            this.draw();
        },
        hover(event) {
            const bounds = canvas.getBoundingClientRect();
            this.select(Math.round(Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width)) * (this.points.length - 1)));
        },
        draw() {
            if (!canvas) return;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            if (!width || !height) return;
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const context = canvas.getContext('2d');
            context.scale(ratio, ratio);
            const top = 7;
            const bottom = height - 7;
            const plotHeight = bottom - top;
            context.strokeStyle = '#d9dce1';
            context.lineWidth = 1;
            context.setLineDash([2, 3]);
            for (let row = 0; row <= 5; row++) {
                const y = top + row / 5 * plotHeight;
                context.beginPath(); context.moveTo(0, y); context.lineTo(width, y); context.stroke();
            }
            const count = Math.min(6, this.points.length);
            for (let col = 0; col < count; col++) {
                const x = count === 1 ? width / 2 : col / (count - 1) * width;
                context.beginPath(); context.moveTo(x, top); context.lineTo(x, bottom); context.stroke();
            }
            const coordinates = this.points.map((point, index) => ({
                x: this.points.length === 1 ? width / 2 : 4 + index / (this.points.length - 1) * (width - 8),
                y: bottom - point.value / this.maximum * plotHeight,
            }));
            const gradient = context.createLinearGradient(0, top, 0, bottom);
            gradient.addColorStop(0, 'rgba(73, 123, 181, .28)');
            gradient.addColorStop(1, 'rgba(73, 123, 181, .02)');
            context.setLineDash([]);
            context.beginPath();
            context.moveTo(coordinates[0].x, bottom);
            for (const point of coordinates) context.lineTo(point.x, point.y);
            context.lineTo(coordinates.at(-1).x, bottom);
            context.closePath(); context.fillStyle = gradient; context.fill();
            context.beginPath();
            coordinates.forEach((point, index) => index ? context.lineTo(point.x, point.y) : context.moveTo(point.x, point.y));
            context.strokeStyle = '#497bb5'; context.lineWidth = 1.8; context.stroke();
            if (this.points.length <= 31) {
                for (const point of coordinates) this.drawPoint(context, point, 3);
            }
            if (this.activeIndex !== null) {
                const point = coordinates[this.activeIndex];
                context.beginPath(); context.moveTo(point.x, top); context.lineTo(point.x, bottom);
                context.setLineDash([3, 3]); context.lineWidth = 1; context.strokeStyle = '#686e67'; context.stroke();
                context.setLineDash([]); this.drawPoint(context, point, 5);
            }
        },
        drawPoint(context, point, radius) {
            context.beginPath(); context.arc(point.x, point.y, radius, 0, Math.PI * 2);
            context.fillStyle = '#fff'; context.fill(); context.strokeStyle = '#497bb5'; context.lineWidth = 1; context.stroke();
        },
    };
}
