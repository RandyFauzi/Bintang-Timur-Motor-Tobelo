import geography from './voyage-geography.json' with { type: 'json' };
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


const project = ([longitude, latitude]) => ({x: (longitude - geography.bounds[0]) * geography.scale, y: (geography.bounds[3] - latitude) * geography.scale});

function findPort(name) {
    const normalized = String(name ?? '').toLocaleLowerCase('id-ID').replace(/[^a-z0-9]+/g, ' ').trim();
    return geography.ports.find(port => port.names.some(alias => (' ' + normalized + ' ').includes(' ' + alias + ' '))) ?? null;
}

export function shippingVoyage(origin, destination, progress = 0) {
    const originPort = findPort(origin);
    const destinationPort = findPort(destination);
    let coordinates = [];
    if (originPort && destinationPort) {
        const key = originPort.id + '|' + destinationPort.id;
        const reverseKey = destinationPort.id + '|' + originPort.id;
        coordinates = geography.routes[key] ?? [...(geography.routes[reverseKey] ?? [])].reverse();
        if (originPort.id === destinationPort.id) coordinates = [[originPort.longitude, originPort.latitude]];
    }
    const seaPoints = coordinates.map(project);
    const points = seaPoints.length && originPort.id !== destinationPort.id
        ? [project([originPort.longitude, originPort.latitude]), ...seaPoints, project([destinationPort.longitude, destinationPort.latitude])]
        : seaPoints;
    const lengths = points.slice(1).map((point, i) => Math.hypot(point.x - points[i].x, point.y - points[i].y));
    const totalLength = lengths.reduce((sum, length) => sum + length, 0);
    const minX = points.length ? Math.min(...points.map(point => point.x)) - 60 : 0;
    const minY = points.length ? Math.min(...points.map(point => point.y)) - 60 : 0;
    const height = points.length ? Math.max(210, Math.max(...points.map(point => point.y)) - minY + 60) : 528;
    const width = points.length ? Math.max(420, height * 1.85, Math.max(...points.map(point => point.x)) - minX + 60) : 1176;
    const centerX = points.length ? (Math.min(...points.map(point => point.x)) + Math.max(...points.map(point => point.x))) / 2 : 588;
    return {
        origin, destination, progress, originPort, destinationPort, points,
        known: points.length > 0,
        start: points[0] ?? {x:0,y:0}, end: points.at(-1) ?? {x:0,y:0},
        viewBox: `${centerX - width / 2} ${minY} ${width} ${height}`,
        markerScale: width / 1000,
        labels: [
            {name:'SUMATRA', at:project([101,-.2])}, {name:'JAWA',at:project([110,-7.5])},
            {name:'KALIMANTAN',at:project([114.1,.2])}, {name:'SULAWESI',at:project([121.0,-1.8])},
            {name:'HALMAHERA',at:project([128.8,1.1])}, {name:'PAPUA',at:project([136,-4])},
            {name:'LAUT MALUKU',at:project([125.6,1.0]),sea:true}, {name:'LAUT BANDA',at:project([128,-5.4]),sea:true},
            {name:'LAUT JAWA',at:project([111,-5.3]),sea:true}, {name:'LAUT SULAWESI',at:project([121.5,3]),sea:true},
        ],
        get fraction() { return Math.max(0, Math.min(100, Number(this.progress) || 0)) / 100; },
        get path() { return points.map((point,i) => `${i ? 'L' : 'M'} ${point.x} ${point.y}`).join(' '); },
        get position() {
            let remaining = totalLength * this.fraction;
            for (let i=0;i<lengths.length;i++) {
                if (remaining <= lengths[i] || i === lengths.length-1) {
                    const t = lengths[i] ? Math.min(1,remaining/lengths[i]) : 0;
                    return {index:i, point:{x:points[i].x+(points[i+1].x-points[i].x)*t,y:points[i].y+(points[i+1].y-points[i].y)*t}};
                }
                remaining -= lengths[i];
            }
            return {index:0,point:this.start};
        },
        get boat() { return this.fraction === 1 ? this.end : this.position.point; },
        get heading() {
            const i=this.position.index;
            return points.length > 1 ? Math.atan2(points[i+1].y-points[i].y,points[i+1].x-points[i].x)*180/Math.PI : 0;
        },
        get completedPath() {
            if (!points.length) return '';
            const visited=points.slice(0,this.position.index+1);
            return visited.map((point,i)=>`${i ? 'L' : 'M'} ${point.x} ${point.y}`).join(' ')+` L ${this.boat.x} ${this.boat.y}`;
        },
    };
}
