<section class="shipping-voyage" aria-label="Ilustrasi perjalanan cargo" x-data="shippingVoyage(@js($selected->origin_port), @js($selected->destination_port), state.progress)" x-effect="progress = state.progress" :class="{'shipping-voyage--delivered': state.status === 'delivered', 'shipping-voyage--stationary': ['scheduled', 'delivered'].includes(state.status)}">
    <header class="shipping-voyage__heading"><span class="shipping-voyage__mode"><x-ui.icon name="ship" :size="18" />OCEAN CARGO</span><span class="shipping-voyage__tag">Ilustrasi perjalanan</span></header>
    <div class="shipping-voyage__map">
        <svg viewBox="0 0 1100 500" x-bind:view-box.camel="viewBox" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Ilustrasi rute pengiriman dari {{ $selected->origin_port }} ke {{ $selected->destination_port }}">
            <g class="voyage-grid" aria-hidden="true"><path d="M0 100H1100M0 200H1100M0 300H1100M0 400H1100M100 0V550M200 0V550M300 0V550M400 0V550M500 0V550M600 0V550M700 0V550M800 0V550M900 0V550M1000 0V550"/></g>
            <g class="voyage-islands" aria-hidden="true">
                <path d="M72 64 105 83 124 121 150 143 157 180 191 213 206 250 240 276 249 313 229 321 202 292 181 279 169 249 140 223 132 192 106 167 96 129 78 104Z"/>
                <path d="M267 331 300 334 329 345 367 340 398 350 424 345 455 356 477 369 467 383 436 380 412 368 379 369 347 359 310 356 280 349Z"/>
                <path d="M397 140 426 129 447 144 480 137 506 156 525 192 517 223 532 252 513 278 489 288 466 276 437 285 415 269 406 239 383 218 389 184Z"/>
                <path d="M586 172 605 183 610 207 631 207 650 193 669 171 682 177 668 201 643 221 615 226 626 250 648 267 650 289 635 295 614 272 609 247 596 247 592 279 601 308 587 318 575 292 575 262 587 235 576 212Z"/>
                <path d="M748 167 760 175 760 197 774 208 768 221 780 243 772 255 757 238 750 220 738 232 733 216 745 200 740 184Z"/>
                <path d="M733 293 746 291 758 305 786 307 790 319 767 325 752 312 736 307Z M700 319 717 315 726 326 712 335 700 331Z"/>
                <path d="M833 220 855 214 875 232 886 224 913 235 933 224 960 246 986 256 1027 284 1049 318 1029 332 997 310 972 300 950 279 929 286 903 271 884 276 863 262 845 264 835 248 819 246Z"/>
                <path d="M496 377 513 378 522 388 508 394 494 388Z M534 385 557 388 563 400 542 399Z M574 393 608 393 625 403 607 412 577 408Z M636 411 666 407 685 417 661 427 640 425Z"/>
            </g>
            <g class="voyage-island-labels" aria-hidden="true"><text x="350" y="390">JAWA</text><text x="430" y="211">KALIMANTAN</text><text x="587" y="337">SULAWESI</text><text x="780" y="188">HALMAHERA</text><text x="904" y="307">PAPUA</text><text x="594" y="114" class="voyage-sea-label">LAUT MALUKU</text></g>
            <path :d="path" class="voyage-route-halo"/>
            <path :d="path" class="voyage-route-base"/>
            <path :d="completedPath" class="voyage-route-complete"/>
            <path :d="path" class="voyage-route-flow"/>
            <g :transform="'translate(' + start.x + ' ' + start.y + ')'" class="voyage-port"><circle r="9"/><circle r="3" class="voyage-port__center"/><text y="-19" text-anchor="middle">ASAL</text></g>
            <g :transform="'translate(' + end.x + ' ' + end.y + ')'" class="voyage-port voyage-port--destination"><circle r="17" class="voyage-port__halo"/><circle r="9"/><circle r="3" class="voyage-port__center"/><text y="-26" text-anchor="middle">TUJUAN</text></g>
            <g :transform="'translate(' + boat.x + ' ' + boat.y + ')'" class="voyage-boat" aria-hidden="true"><g class="voyage-boat__float"><circle r="24"/><path d="M-13 1 0-4 13 1 9 12H-9ZM-7-1v-10H7v10M0-11v-5M0-4v15"/><path d="M-17 17q5-4 9 0 4-4 8 0 4-4 8 0 4-4 9 0"/></g></g>
        </svg>
        <span class="voyage-compass" aria-hidden="true">N<span>↑</span></span>
    </div>
    <div class="shipping-voyage__route"><div><span class="voyage-route-dot"></span><div><small>KOTA ASAL</small><strong>{{ $selected->origin_port }}</strong></div></div><span class="shipping-voyage__connector" aria-hidden="true">→</span><div><span class="voyage-route-dot voyage-route-dot--destination"></span><div><small>TUJUAN CARGO</small><strong>{{ $selected->destination_port }}</strong></div></div></div>
    <footer class="shipping-voyage__footer"><span>Rute ilustratif · Posisi kapal mengikuti progres estimasi</span><span class="shipping-voyage__percent" x-text="state.progress + '% perjalanan'"></span></footer>
</section>
