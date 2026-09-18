# Grid y utilidades del theme (`assets/css/grid.css`)

Generado por `node scripts/build-grid.js` (no editar el CSS a mano; la
configuración está en `CONFIG` dentro del script). Estilo Bootstrap 5,
mobile-first, sin JS. Modelo de caja `border-box` global.

## Grid

- `container` (540 / 720 / 960 / 1140 / 1560 px según breakpoint; en xxl deja 1536 px de contenido, el ancho interior del diseño aprobado de Figma) · `container-fluid`
- `row` · `row g-0` (sin gutter) · gutter por variable: `style="--grid-gap:40px"`
- `col-{1..12}` = móvil (alias `col-xs-*`), `col-sm-*` ≥576, `col-md-*` ≥768,
  `col-lg-*` ≥992, `col-xl-*` ≥1200, `col-xxl-*` ≥1400 · `col`/`col-auto`
- `offset-{bp}-{0..11}` · `order-{bp}-{first,last,0..5}`

```html
<div class="container">
  <div class="row">
    <div class="col-12 col-md-6 col-lg-4">…</div>
    <div class="col-12 col-md-6 col-lg-8">…</div>
  </div>
</div>
```

## Utilidades (el breakpoint va entre propiedad y valor: `justify-content-md-between`)

- Display: `d-none` `d-block` `d-inline` `d-inline-block` `d-flex` `d-inline-flex` `d-grid`
- Flex: `flex-row|column|row-reverse|column-reverse` `flex-wrap|nowrap` `flex-fill` `flex-grow-0|1`
  `justify-content-start|end|center|between|around|evenly` `align-items-*` `align-self-*`
  `gap-{0,5,10,20,30,40}`
- Texto: `text-start|center|end`
- Espaciado en **px** (variantes solo `md` y `lg`): `p|m` + lado `t b s e x y` + valor
  `{0,5,10,20,30,40,50,60,70,80,90,100,120}` → `pt-20`, `py-md-60`, `mb-lg-40`, `mx-auto`
- Tipografía: `fs-{10,12,14,16,18,20,25,30,35,40,45,50,55,60,65}` (variantes `md`/`lg`),
  `fw-{300,400,500,600,700,800}` (sin variantes)
