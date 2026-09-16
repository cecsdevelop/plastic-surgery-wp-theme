#!/usr/bin/env node
/**
 * Genera assets/css/grid.css: grid estilo Bootstrap 5 (mobile-first, con alias
 * xs) + utilidades mínimas en px. Toda la configuración está en CONFIG; el
 * CSS de salida no se edita a mano.
 *
 *   node scripts/build-grid.js
 *
 * Convenciones:
 *  - Breakpoints y anchos de .container = Bootstrap 5.
 *  - Columnas: flexbox + porcentajes; gutter por variable CSS --grid-gap
 *    (cambiable desde un panel o por sección con style="--grid-gap:0").
 *  - Espaciado en px (una sola escala, no la de rem de Bootstrap).
 *  - Responsive: grid/display/flex en todos los breakpoints; espaciado y fs-*
 *    solo en md y lg; fw-* sin variantes.
 */
const fs = require('fs');
const path = require('path');

const CONFIG = {
  columns: 12,
  gap: '24px',
  breakpoints: { sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 },
  containers:  { sm: 540, md: 720, lg: 960, xl: 1140, xxl: 1320 },
  spacing: [0, 5, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 120],
  spacingResponsive: ['md', 'lg'],
  fontSizes: [10, 12, 14, 16, 18, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65],
  fontSizesResponsive: ['md', 'lg'],
  fontWeights: [300, 400, 500, 600, 700, 800],
  gaps: [0, 5, 10, 20, 30, 40],
};

const out = [];
const rule = (selector, decl) => out.push(`${selector}{${decl}}`);
const pct = (n) => `${+((n / CONFIG.columns) * 100).toFixed(6)}%`;

// ---- Prefijos de columna: '' (base) y 'xs-' (alias) comparten reglas ----
const colSel = (infix, n) => infix === '' ? `.col-${n},.col-xs-${n}` : `.col-${infix}-${n}`;
// Bootstrap pone el breakpoint entre la propiedad y el valor: justify-content-md-between.
const util   = (prefix, value, infix) => infix === '' ? `.${prefix}-${value}` : `.${prefix}-${infix}-${value}`;

// ---- Bloques que se repiten por breakpoint ----
function gridBlock(infix) {
  const c = (n) => colSel(infix, n);
  rule(infix === '' ? '.col,.col-xs' : `.col-${infix}`, 'flex:1 0 0%');
  rule(infix === '' ? '.col-auto,.col-xs-auto' : `.col-${infix}-auto`, 'flex:0 0 auto;width:auto');
  for (let n = 1; n <= CONFIG.columns; n++) rule(c(n), `flex:0 0 auto;width:${pct(n)}`);
  for (let n = 0; n < CONFIG.columns; n++) rule(infix === '' ? `.offset-${n},.offset-xs-${n}` : `.offset-${infix}-${n}`, `margin-left:${pct(n)}`);
  rule(util('order', 'first', infix), 'order:-1');
  rule(util('order', 'last', infix), `order:${CONFIG.columns + 1}`);
  for (let n = 0; n <= 5; n++) rule(util('order', n, infix), `order:${n}`);
}

function displayFlexBlock(infix) {
  ['none', 'block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid'].forEach((d) => rule(util('d', d, infix), `display:${d}`));
  ['row', 'row-reverse', 'column', 'column-reverse'].forEach((v) => rule(util('flex', v, infix), `flex-direction:${v}`));
  rule(util('flex', 'wrap', infix), 'flex-wrap:wrap');
  rule(util('flex', 'nowrap', infix), 'flex-wrap:nowrap');
  rule(util('flex', 'fill', infix), 'flex:1 1 auto');
  rule(util('flex', 'grow-0', infix), 'flex-grow:0');
  rule(util('flex', 'grow-1', infix), 'flex-grow:1');
  const jc = { start: 'flex-start', end: 'flex-end', center: 'center', between: 'space-between', around: 'space-around', evenly: 'space-evenly' };
  Object.entries(jc).forEach(([k, v]) => rule(util('justify-content', k, infix), `justify-content:${v}`));
  const ai = { start: 'flex-start', end: 'flex-end', center: 'center', baseline: 'baseline', stretch: 'stretch' };
  Object.entries(ai).forEach(([k, v]) => rule(util('align-items', k, infix), `align-items:${v}`));
  Object.entries(ai).forEach(([k, v]) => rule(util('align-self', k, infix), `align-self:${v}`));
  ['start', 'center', 'end'].forEach((t) => rule(util('text', t, infix), `text-align:${t === 'start' ? 'left' : t === 'end' ? 'right' : 'center'}`));
  CONFIG.gaps.forEach((g) => rule(util('gap', g, infix), `gap:${g}px`));
}

function spacingBlock(infix) {
  const sides = { '': ['top', 'right', 'bottom', 'left'], t: ['top'], b: ['bottom'], s: ['left'], e: ['right'], x: ['left', 'right'], y: ['top', 'bottom'] };
  for (const [prop, letter] of [['padding', 'p'], ['margin', 'm']]) {
    for (const [suffix, dirs] of Object.entries(sides)) {
      CONFIG.spacing.forEach((v) => rule(util(`${letter}${suffix}`, v, infix), dirs.map((d) => `${prop}-${d}:${v}px`).join(';')));
      if (letter === 'm') rule(util(`m${suffix}`, 'auto', infix), dirs.map((d) => `margin-${d}:auto`).join(';'));
    }
  }
}

function fontSizeBlock(infix) {
  CONFIG.fontSizes.forEach((s) => rule(util('fs', s, infix), `font-size:${s}px`));
}

// ---- Salida ----
out.push(`/* Generado por scripts/build-grid.js — NO editar a mano. Grid estilo Bootstrap 5 (mobile-first, alias xs) + utilidades en px. */`);
// Modelo de caja border-box global (como el reboot de Bootstrap): el HTML escrito
// "a la Bootstrap" lo asume y los anchos en % + padding solo cierran así.
rule('*,*::before,*::after', 'box-sizing:border-box');
// El atributo hidden debe ganar a cualquier display de utilidad (.row, .d-flex…).
rule('[hidden]', 'display:none !important');
out.push(`:root{--grid-gap:${CONFIG.gap}}`);
rule('.container,.container-fluid', 'width:100%;margin-right:auto;margin-left:auto;padding-right:calc(var(--grid-gap) * .5);padding-left:calc(var(--grid-gap) * .5)');
rule('.row', 'display:flex;flex-wrap:wrap;margin-right:calc(var(--grid-gap) * -.5);margin-left:calc(var(--grid-gap) * -.5)');
rule('.row > *', 'flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--grid-gap) * .5);padding-left:calc(var(--grid-gap) * .5);box-sizing:border-box');
rule('.row.g-0', '--grid-gap:0');

gridBlock('');
displayFlexBlock('');
spacingBlock('');
fontSizeBlock('');
CONFIG.fontWeights.forEach((w) => rule(`.fw-${w}`, `font-weight:${w}`));

for (const [bp, min] of Object.entries(CONFIG.breakpoints)) {
  out.push(`@media (min-width:${min}px){`);
  rule('.container', `max-width:${CONFIG.containers[bp]}px`);
  gridBlock(bp);
  displayFlexBlock(bp);
  if (CONFIG.spacingResponsive.includes(bp)) spacingBlock(bp);
  if (CONFIG.fontSizesResponsive.includes(bp)) fontSizeBlock(bp);
  out.push('}');
}

const target = path.join(__dirname, '..', 'assets', 'css', 'grid.css');
fs.writeFileSync(target, out.join('\n') + '\n');
const bytes = fs.statSync(target).size;
console.log(`grid.css: ${out.length} líneas, ${(bytes / 1024).toFixed(1)} KB → ${path.relative(process.cwd(), target)}`);
