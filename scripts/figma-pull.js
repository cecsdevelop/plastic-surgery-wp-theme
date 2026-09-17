#!/usr/bin/env node
/**
 * Baja el diseño del sitio desde la API REST de Figma a docs/figma/ (excluido
 * del zip del theme) para construir las plantillas sin depender del conector
 * MCP (limitado a 6 llamadas/mes con asiento View).
 *
 *   node scripts/figma-pull.js pages [--depth 3]        páginas y frames de primer nivel → docs/figma/index.md
 *   node scripts/figma-pull.js tree  <id> [--depth 4]   árbol resumido de un nodo (tipo, nombre, id, tamaño)
 *   node scripts/figma-pull.js spec  <id>...            spec compacto (layout, colores, tipografía, textos) → docs/figma/spec/
 *   node scripts/figma-pull.js png   <id>... [--scale 2] exporta PNG → docs/figma/png/
 *   node scripts/figma-pull.js tokens <id>...           tipografías, colores, radios y gaps de una pantalla, por frecuencia → docs/figma/spec/
 *   node scripts/figma-pull.js styles                   estilos locales del archivo (color, texto, efectos)
 *
 * Token: variable FIGMA_TOKEN o archivo ~/.config/figma_token (o --token-file
 * <ruta>). Solo viaja en la cabecera X-Figma-Token; nunca se imprime ni se
 * guarda en el repo. Archivo por defecto: Website - DS Intelindev (--file <key>
 * para otro). Los ids aceptan la forma de la URL (7759-13797) o de la API (7759:13797).
 */

const fs   = require('fs');
const path = require('path');
const os   = require('os');

const DEFAULT_FILE_KEY = 'soT78LzfqhZ72LVTGOs9xC';
const OUT_DIR = path.join(__dirname, '..', 'docs', 'figma');
const API = 'https://api.figma.com/v1';

/* ------------------------------------------------------------------ */
/* CLI                                                                  */
/* ------------------------------------------------------------------ */

const argv = process.argv.slice(2);
const opts = {};
const positional = [];
for (let i = 0; i < argv.length; i++) {
  const a = argv[i];
  if (a.startsWith('--')) opts[a.slice(2)] = argv[i + 1] !== undefined && !argv[i + 1].startsWith('--') ? argv[++i] : true;
  else positional.push(a);
}
const [command, ...ids] = positional;
const fileKey = opts.file || DEFAULT_FILE_KEY;

function readToken() {
  if (process.env.FIGMA_TOKEN) return process.env.FIGMA_TOKEN.trim();
  const file = opts['token-file'] || path.join(os.homedir(), '.config', 'figma_token');
  try {
    return fs.readFileSync(file, 'utf8').trim();
  } catch {
    console.error(`No encuentro el token: exporta FIGMA_TOKEN o guarda el token en ${file}\n(Figma → Settings → Security → Personal access tokens, con permiso File content: Read).`);
    process.exit(2);
  }
}

const toApiId = (id) => id.replace('-', ':');
const slug = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'node';
const fileId = (id) => id.replace(':', '-');

async function api(endpoint, attempt = 1) {
  const res = await fetch(API + endpoint, { headers: { 'X-Figma-Token': readToken() } });
  if (res.status === 429 && attempt <= 3) {
    const wait = Number(res.headers.get('retry-after') || 30);
    console.error(`  rate limit, reintento en ${wait}s…`);
    await new Promise((r) => setTimeout(r, wait * 1000));
    return api(endpoint, attempt + 1);
  }
  if (!res.ok) {
    let msg = '';
    try { msg = (await res.json()).err || ''; } catch { /* sin cuerpo */ }
    throw new Error(`Figma ${res.status} en ${endpoint.split('?')[0]}${msg ? ': ' + msg : ''}`);
  }
  return res.json();
}

function save(rel, data) {
  const file = path.join(OUT_DIR, rel);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, typeof data === 'string' || Buffer.isBuffer(data) ? data : JSON.stringify(data, null, 2));
  return path.relative(process.cwd(), file);
}

/* ------------------------------------------------------------------ */
/* Helpers de nodos                                                     */
/* ------------------------------------------------------------------ */

const hex = (c) => '#' + ['r', 'g', 'b'].map((k) => Math.round(c[k] * 255).toString(16).padStart(2, '0')).join('');
const size = (n) => n.absoluteBoundingBox ? `${Math.round(n.absoluteBoundingBox.width)}×${Math.round(n.absoluteBoundingBox.height)}` : '';

function paint(fills) {
  return (fills || []).filter((f) => f.visible !== false).map((f) => {
    if (f.type === 'SOLID') return hex(f.color) + (f.opacity !== undefined && f.opacity < 1 ? ` ${Math.round(f.opacity * 100)}%` : '');
    if (f.type.startsWith('GRADIENT')) return f.type.toLowerCase() + '(' + (f.gradientStops || []).map((s) => hex(s.color)).join(' → ') + ')';
    if (f.type === 'IMAGE') return 'image';
    return f.type.toLowerCase();
  }).join(', ');
}

function textStyle(s) {
  if (!s) return '';
  const parts = [`${s.fontFamily || ''} ${s.fontWeight || ''}`.trim(), s.fontSize && `${s.fontSize}px`];
  if (s.lineHeightPx) parts.push(`lh ${Math.round(s.lineHeightPx)}px`);
  if (s.letterSpacing) parts.push(`ls ${s.letterSpacing}`);
  if (s.textCase && s.textCase !== 'ORIGINAL') parts.push(s.textCase.toLowerCase());
  if (s.textAlignHorizontal && s.textAlignHorizontal !== 'LEFT') parts.push(s.textAlignHorizontal.toLowerCase());
  return parts.filter(Boolean).join(' · ');
}

function layout(n) {
  if (!n.layoutMode || n.layoutMode === 'NONE') return '';
  const pad = [n.paddingTop, n.paddingRight, n.paddingBottom, n.paddingLeft].map((v) => v || 0);
  const parts = [n.layoutMode === 'HORIZONTAL' ? 'row' : 'column'];
  if (n.layoutWrap === 'WRAP') parts.push('wrap');
  if (n.itemSpacing) parts.push(`gap ${n.itemSpacing}`);
  if (pad.some(Boolean)) parts.push(`pad ${pad.join('/')}`);
  if (n.primaryAxisAlignItems && n.primaryAxisAlignItems !== 'MIN') parts.push(`main ${n.primaryAxisAlignItems.toLowerCase()}`);
  if (n.counterAxisAlignItems && n.counterAxisAlignItems !== 'MIN') parts.push(`cross ${n.counterAxisAlignItems.toLowerCase()}`);
  return parts.join(' ');
}

/** Línea compacta por nodo: lo que hace falta para escribir HTML/CSS sin abrir Figma. */
function describe(n) {
  const bits = [];
  const l = layout(n); if (l) bits.push(`[${l}]`);
  const f = paint(n.fills); if (f && n.type !== 'TEXT') bits.push(`bg ${f}`);
  const st = paint(n.strokes); if (st) bits.push(`border ${n.strokeWeight || 1}px ${st}`);
  if (n.cornerRadius) bits.push(`radius ${n.cornerRadius}`);
  else if (n.rectangleCornerRadii && n.rectangleCornerRadii.some(Boolean)) bits.push(`radius ${n.rectangleCornerRadii.join('/')}`);
  if (n.opacity !== undefined && n.opacity < 1) bits.push(`opacity ${Math.round(n.opacity * 100)}%`);
  if ((n.effects || []).some((e) => e.visible !== false && e.type.includes('SHADOW'))) bits.push('shadow');
  if (n.type === 'TEXT') {
    bits.push(`{${textStyle(n.style)}${f ? ' · ' + f : ''}}`);
    bits.push(JSON.stringify((n.characters || '').replace(/\s+/g, ' ').trim().slice(0, 160)));
  }
  if (n.type === 'INSTANCE' && n.componentId) bits.push(`<instance ${n.componentId}>`);
  if (n.visible === false) bits.push('(oculto)');
  return bits.join(' ');
}

function walk(n, depth, maxDepth, out, mode) {
  const line = `${'  '.repeat(depth)}- ${n.type} "${n.name}" ${n.id} ${size(n)}${mode === 'spec' ? ' ' + describe(n) : ''}`.trimEnd();
  out.push(line);
  if (depth < maxDepth) (n.children || []).forEach((c) => walk(c, depth + 1, maxDepth, out, mode));
  else if ((n.children || []).length) out.push(`${'  '.repeat(depth + 1)}… ${n.children.length} hijos`);
}

/* ------------------------------------------------------------------ */
/* Comandos                                                             */
/* ------------------------------------------------------------------ */

const commands = {
  async pages() {
    const depth = Number(opts.depth || 3);
    const file = await api(`/files/${fileKey}?depth=${depth}`);
    save('raw/file.json', file);
    const md = [`# ${file.name}`, '', `Archivo \`${fileKey}\` · última modificación ${file.lastModified} · bajado ${new Date().toISOString()}`, ''];
    for (const page of file.document.children) {
      md.push(`## ${page.name} (\`${page.id}\`)`, '');
      for (const n of page.children || []) {
        md.push(`- ${n.type} **${n.name}** \`${n.id}\` ${size(n)}`);
        for (const c of n.children || []) md.push(`  - ${c.type} ${c.name} \`${c.id}\` ${size(c)}`);
      }
      md.push('');
    }
    const out = save('index.md', md.join('\n'));
    console.log(md.join('\n'));
    console.log(`\n→ ${out}`);
  },

  async tree() {
    if (!ids.length) throw new Error('falta el id del nodo');
    const depth = Number(opts.depth || 4);
    const data = await api(`/files/${fileKey}/nodes?ids=${ids.map(toApiId).join(',')}&depth=${depth}`);
    for (const id of ids.map(toApiId)) {
      const doc = data.nodes[id]?.document;
      if (!doc) { console.log(`${id}: no encontrado`); continue; }
      const out = []; walk(doc, 0, depth, out, 'tree');
      console.log(out.join('\n'));
    }
  },

  async spec() {
    if (!ids.length) throw new Error('falta el id del nodo');
    const data = await api(`/files/${fileKey}/nodes?ids=${ids.map(toApiId).join(',')}${opts.depth ? '&depth=' + Number(opts.depth) : ''}`); // sin --depth baja el subárbol completo (pantallas enteras tardan)
    for (const id of ids.map(toApiId)) {
      const doc = data.nodes[id]?.document;
      if (!doc) { console.log(`${id}: no encontrado`); continue; }
      const base = `${slug(doc.name)}-${fileId(id)}`;
      save(`raw/${base}.json`, data.nodes[id]);
      const out = [`# ${doc.name} (${id}) ${size(doc)}`, '', 'Formato: TYPE "nombre" id ancho×alto [autolayout] bg/border/radius {tipografía · color} "texto"', ''];
      walk(doc, 0, Number(opts.depth || 99), out, 'spec');
      const md = save(`spec/${base}.md`, out.join('\n'));
      console.log(`${doc.name}: ${out.length - 4} nodos → ${md}`);
    }
  },

  async png() {
    if (!ids.length) throw new Error('falta el id del nodo');
    const scale = Number(opts.scale || 1);
    const apiIds = ids.map(toApiId);
    const [images, meta] = await Promise.all([
      api(`/images/${fileKey}?ids=${apiIds.join(',')}&format=png&scale=${scale}`),
      api(`/files/${fileKey}/nodes?ids=${apiIds.join(',')}&depth=1`),
    ]);
    for (const id of apiIds) {
      const url = images.images[id];
      if (!url) { console.log(`${id}: sin imagen (${images.err || 'nodo no exportable'})`); continue; }
      const name = meta.nodes[id]?.document?.name || 'node';
      const rel = `png/${slug(name)}-${fileId(id)}${scale !== 1 ? '@' + scale + 'x' : ''}.png`;
      const buf = Buffer.from(await (await fetch(url)).arrayBuffer());
      const out = save(rel, buf);
      console.log(`${name} ${id} → ${out} (${Math.round(buf.length / 1024)} KB)`);
    }
  },

  /** Inventario de una pantalla: qué familias/tamaños, colores, radios y gaps usa y cuántas veces. Base para las variables CSS. */
  async tokens() {
    if (!ids.length) throw new Error('falta el id del nodo');
    const data = await api(`/files/${fileKey}/nodes?ids=${ids.map(toApiId).join(',')}`);
    const tally = (map, key, example) => { const e = map.get(key) || { n: 0, ex: example }; e.n++; map.set(key, e); };
    const fonts = new Map(), colors = new Map(), radii = new Map(), gaps = new Map(), pads = new Map();
    const visit = (n) => {
      if (n.visible === false) return;
      if (n.type === 'TEXT' && n.style) tally(fonts, textStyle(n.style), (n.characters || '').replace(/\s+/g, ' ').trim().slice(0, 50));
      for (const p of (n.fills || []).filter((x) => x.visible !== false && x.type === 'SOLID')) tally(colors, hex(p.color) + (p.opacity !== undefined && p.opacity < 1 ? ` ${Math.round(p.opacity * 100)}%` : ''), `${n.type === 'TEXT' ? 'texto' : 'fondo'} "${n.name}"`);
      for (const p of (n.strokes || []).filter((x) => x.visible !== false && x.type === 'SOLID')) tally(colors, hex(p.color), `borde "${n.name}"`);
      for (const p of (n.fills || []).filter((x) => x.visible !== false && x.type.startsWith('GRADIENT'))) tally(colors, paint([p]), `gradiente "${n.name}"`);
      if (n.cornerRadius) tally(radii, String(n.cornerRadius), n.name);
      if (n.layoutMode && n.layoutMode !== 'NONE') {
        if (n.itemSpacing) tally(gaps, String(n.itemSpacing), n.name);
        const pad = [n.paddingTop, n.paddingRight, n.paddingBottom, n.paddingLeft].map((v) => v || 0);
        if (pad.some(Boolean)) tally(pads, pad.join('/'), n.name);
      }
      (n.children || []).forEach(visit);
    };
    for (const id of ids.map(toApiId)) {
      const doc = data.nodes[id]?.document;
      if (!doc) { console.log(`${id}: no encontrado`); continue; }
      [fonts, colors, radii, gaps, pads].forEach((m) => m.clear());
      visit(doc);
      const out = [`# Tokens de ${doc.name} (${id}) ${size(doc)}`, ''];
      const section = (title, map, unit = '') => {
        out.push(`## ${title}`, '');
        [...map.entries()].sort((a, b) => b[1].n - a[1].n).forEach(([k, v]) => out.push(`- ${k}${unit} × ${v.n} — ej. ${v.ex}`));
        out.push('');
      };
      section('Tipografía (familia peso · tamaño · interlineado)', fonts);
      section('Colores', colors);
      section('Radios', radii, 'px');
      section('Gaps de auto-layout', gaps, 'px');
      section('Paddings (arriba/der/abajo/izq)', pads);
      const md = save(`spec/tokens-${slug(doc.name)}-${fileId(id)}.md`, out.join('\n'));
      console.log(out.join('\n'));
      console.log(`→ ${md}`);
    }
  },

  async styles() {
    const file = await api(`/files/${fileKey}?depth=1`);
    const rows = Object.entries(file.styles || {}).map(([id, s]) => `- ${s.styleType} **${s.name}** \`${id}\`${s.description ? ' — ' + s.description : ''}`).sort();
    console.log(rows.length ? rows.join('\n') : 'El archivo no tiene estilos locales.');
    console.log(`\n(${rows.length} estilos; los valores salen con "spec" en los nodos que los usan)`);
  },
};

(async () => {
  if (!command || !commands[command]) {
    console.error('Uso: node scripts/figma-pull.js <pages|tree|spec|png|tokens|styles> [ids…] [--depth N] [--scale N] [--file KEY] [--token-file RUTA]');
    process.exit(1);
  }
  try {
    await commands[command]();
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
})();
