<section class="shipping-voyage" aria-label="Ilustrasi perjalanan cargo" x-data="shippingVoyage(@js($selected->origin_port), @js($selected->destination_port), state.progress)" x-effect="progress = state.progress" :class="{'shipping-voyage--delivered': state.status === 'delivered', 'shipping-voyage--stationary': ['scheduled', 'delivered'].includes(state.status)}">
    <header class="shipping-voyage__heading"><span class="shipping-voyage__mode"><x-ui.icon name="ship" :size="18" />OCEAN CARGO</span><span class="shipping-voyage__tag">Peta geografis · Animasi 3D</span></header>
    <div class="shipping-voyage__map">
        <svg viewBox="0 0 1176 528" x-bind:view-box.camel="viewBox" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Ilustrasi rute pengiriman dari {{ $selected->origin_port }} ke {{ $selected->destination_port }}">
            <defs><pattern id="voyage-grid" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0V48" fill="none" stroke="#80abc5" stroke-width=".4" opacity=".35" /></pattern></defs>
            <rect x="-100" y="-100" width="1400" height="800" fill="url(#voyage-grid)" aria-hidden="true" />
            <image href="{{ Vite::asset('resources/images/maps/indonesia-coast.svg') }}" x="0" y="0" width="1176" height="528" aria-hidden="true" />
            <g class="voyage-island-labels" aria-hidden="true"><template x-for="label in labels" :key="label.name"><text :x="label.at.x" :y="label.at.y" :class="{'voyage-sea-label': label.sea}" x-text="label.name"></text></template></g>
            <path :d="path" class="voyage-route-halo"/>
            <path :d="path" class="voyage-route-base"/>
            <path :d="completedPath" class="voyage-route-complete"/>
            <path :d="path" class="voyage-route-flow"/>
            <g :transform="'translate(' + start.x + ' ' + start.y + ')'" class="voyage-port" x-show="known"><circle r="9"/><circle r="3" class="voyage-port__center"/><text y="-19" text-anchor="middle">ASAL</text></g>
            <g :transform="'translate(' + end.x + ' ' + end.y + ')'" class="voyage-port voyage-port--destination" x-show="known"><circle r="17" class="voyage-port__halo"/><circle r="9"/><circle r="3" class="voyage-port__center"/><text y="-26" text-anchor="middle">TUJUAN</text></g>
            <g x-show="known" :transform="'translate(' + boat.x + ' ' + boat.y + ') scale(' + markerScale + ')'" class="voyage-boat" aria-hidden="true"><g class="voyage-boat__float"><g :transform="'rotate(' + heading + ')'"><path class="voyage-wake" d="M-33 -5Q-49 -9 -65 -5M-33 5Q-49 9 -65 5"/><image href="{{ Vite::asset('resources/images/maps/cargo-vessel.svg') }}" x="-34" y="-24" width="68" height="44" /></g></g></g>

        </svg>
        <span class="voyage-compass" aria-hidden="true">N<span>↑</span></span>
    </div>
    <p x-cloak x-show="!known" class="shipping-voyage__unmapped" role="status">Lokasi pelabuhan belum dikenali. Rute dan posisi kapal ditampilkan setelah nama pelabuhan dikenali.</p>
    <div class="shipping-voyage__route"><div><span class="voyage-route-dot"></span><div><small>KOTA ASAL</small><strong>{{ $selected->origin_port }}</strong></div></div><span class="shipping-voyage__connector" aria-hidden="true">→</span><div><span class="voyage-route-dot voyage-route-dot--destination"></span><div><small>TUJUAN CARGO</small><strong>{{ $selected->destination_port }}</strong></div></div></div>
    <footer class="shipping-voyage__footer"><span>Jalur laut estimasi · Posisi kapal berdasarkan progres, bukan GPS</span><span class="shipping-voyage__percent" x-text="state.progress + '% perjalanan'"></span></footer>
    <p class="shipping-voyage__credit">Peta: <a href="https://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-land/" target="_blank" rel="noopener noreferrer">Natural Earth 1:10m</a> · Public domain</p>
</section>
