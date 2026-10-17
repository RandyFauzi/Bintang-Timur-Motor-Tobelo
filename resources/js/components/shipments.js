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
