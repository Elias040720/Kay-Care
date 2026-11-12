<template>
  <!-- Ilustración vectorial original: mostrador de farmacia con estantería.
       Sin marcas, logos ni fotografías de terceros. -->
  <svg viewBox="0 0 480 400" class="h-auto w-full" role="img" aria-label="Ilustración de una farmacia moderna con estantes de productos">
    <defs>
      <linearGradient id="hero-bg" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#ECF8F2" />
        <stop offset="1" stop-color="#E3EEF8" />
      </linearGradient>
      <linearGradient id="hero-counter" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#14966A" />
        <stop offset="1" stop-color="#0E7A55" />
      </linearGradient>
    </defs>
    <rect x="20" y="20" width="440" height="360" rx="36" fill="url(#hero-bg)" />
    <circle cx="400" cy="70" r="46" fill="#D3EEEB" />
    <circle cx="70" cy="330" r="34" fill="#D2F0E1" />

    <!-- Letrero con cruz -->
    <g class="motion-safe:animate-float">
      <rect x="196" y="44" width="88" height="56" rx="16" fill="#FFFFFF" />
      <path d="M233 56h14v11h11v14h-11v11h-14V81h-11V67h11z" fill="#14966A" />
    </g>

    <!-- Estantería -->
    <rect x="70" y="118" width="340" height="150" rx="18" fill="#FFFFFF" />
    <rect x="86" y="182" width="308" height="6" rx="3" fill="#C1D8C8" />
    <rect x="86" y="248" width="308" height="6" rx="3" fill="#C1D8C8" />
    <!-- Fila superior -->
    <rect x="98" y="140" width="26" height="42" rx="7" fill="#7CC6BF" />
    <rect x="102" y="134" width="18" height="10" rx="3" fill="#3A8F89" />
    <rect x="134" y="150" width="40" height="32" rx="6" fill="#A6E0C4" />
    <rect x="184" y="136" width="22" height="46" rx="11" fill="#C7DDF0" />
    <rect x="214" y="146" width="44" height="36" rx="8" fill="#FBF1D9" />
    <rect x="268" y="138" width="26" height="44" rx="7" fill="#5E8C70" />
    <rect x="272" y="132" width="18" height="10" rx="3" fill="#2E4638" />
    <rect x="304" y="152" width="38" height="30" rx="6" fill="#D3EEEB" />
    <rect x="350" y="140" width="30" height="42" rx="9" fill="#9DC0A8" />
    <!-- Fila inferior -->
    <rect x="98" y="210" width="44" height="38" rx="8" fill="#D2F0E1" />
    <rect x="152" y="202" width="24" height="46" rx="12" fill="#3D6E9E" opacity="0.8" />
    <rect x="186" y="214" width="40" height="34" rx="6" fill="#FFF1EE" />
    <rect x="236" y="204" width="28" height="44" rx="8" fill="#7AA78A" />
    <rect x="274" y="212" width="46" height="36" rx="8" fill="#E3EEF8" />
    <rect x="330" y="206" width="22" height="42" rx="11" fill="#52ABA4" />
    <rect x="360" y="216" width="22" height="32" rx="6" fill="#A9DDD7" />

    <!-- Mostrador -->
    <rect x="52" y="282" width="376" height="74" rx="18" fill="url(#hero-counter)" />
    <rect x="52" y="282" width="376" height="14" rx="7" fill="#A6E0C4" />
    <rect x="96" y="310" width="120" height="10" rx="5" fill="#FFFFFF" opacity="0.35" />
    <rect x="96" y="328" width="80" height="10" rx="5" fill="#FFFFFF" opacity="0.25" />

    <!-- Bolsa de compra -->
    <g>
      <path d="M330 248h66l-6 46h-54z" fill="#FFFFFF" />
      <path d="M346 250c0-14 8-22 17-22s17 8 17 22" fill="none" stroke="#0E7A55" stroke-width="5" stroke-linecap="round" />
      <path d="M356 268h14v-7h7v14h-7v7h-14v-7h-7v-14h7z" fill="#14966A" transform="translate(0 2) scale(1)" />
    </g>

    <!-- Hoja -->
    <path d="M118 296c26-30 56-36 84-26-18 26-48 38-84 26z" fill="#D2F0E1" opacity="0.9" />
  </svg>
</template>
