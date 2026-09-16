<?php
/**
 * Verifica assets/css/grid.css (generado por scripts/build-grid.js) y su
 * encolado antes de styles.css.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-grid.php
 */
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }

$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/grid.css');
$has = fn(string $rule) => strpos($css, $rule) !== false;

echo "1) generado y al día\n";
$regen = trim((string) shell_exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && node -e ' . escapeshellarg('const fs=require("fs");const before=fs.readFileSync("assets/css/grid.css","utf8");require("./scripts/build-grid.js");const after=fs.readFileSync("assets/css/grid.css","utf8");console.log(before===after?"same":"changed")') . ' 2>/dev/null | tail -1'));
check('grid.css coincide con la salida del generador', $regen === 'same');
check('tamaño razonable (< 60 KB)', strlen($css) < 60 * 1024);

echo "2) reglas clave\n";
check('border-box global', $has('*,*::before,*::after{box-sizing:border-box}'));
check('container + gap por variable', $has(':root{--grid-gap:24px}') && $has('.container,.container-fluid{width:100%;margin-right:auto;margin-left:auto;padding-right:calc(var(--grid-gap) * .5)'));
check('col base + alias xs', $has('.col-6,.col-xs-6{flex:0 0 auto;width:50%}') && $has('.col,.col-xs{flex:1 0 0%}'));
check('breakpoints y containers Bootstrap 5', $has('@media (min-width:768px){') && $has('.container{max-width:720px}') && $has('@media (min-width:1400px){') && $has('.container{max-width:1320px}'));
check('col-lg-4, offset-lg-3, order-md-first', $has('.col-lg-4{flex:0 0 auto;width:33.333333%}') && $has('.offset-lg-3{margin-left:25%}') && $has('.order-md-first{order:-1}'));
check('display y flex responsive', $has('.d-md-none{display:none}') && $has('.d-xxl-flex{display:flex}') && $has('.justify-content-lg-between{justify-content:space-between}') && $has('.align-items-center{align-items:center}'));
check('espaciado px: py-20, mt-md-60, mx-auto, ms/me', $has('.py-20{padding-top:20px;padding-bottom:20px}') && $has('.mt-md-60{margin-top:60px}') && $has('.mx-auto{margin-left:auto;margin-right:auto}') && $has('.ms-10{margin-left:10px}') && $has('.pe-lg-40{padding-right:40px}'));
check('fs-*/fw-* y gap-*', $has('.fs-16{font-size:16px}') && $has('.fs-lg-50{font-size:50px}') && $has('.fw-700{font-weight:700}') && $has('.gap-20{gap:20px}'));
check('sin variantes sm/xl/xxl de espaciado y fs (por diseño)', !preg_match('/\.(p|m)[tbsexy]?-(sm|xl|xxl)-\d/', $css) && !preg_match('/\.fs-(sm|xl|xxl)-\d/', $css));
check('sin escala rem de Bootstrap (pt-1..pt-4 no existen)', !preg_match('/\.pt-[1-4]\{/', $css));

echo "3) encolado\n";
$home = (string) shell_exec('curl -s http://localhost:8888/Intelindev/');
$grid = strpos($home, "id='intelindev-grid-css'"); $styles = strpos($home, "id='intelindev-styles-css'");
check('grid.css encolado antes de styles.css', $grid !== false && $styles !== false && $grid < $styles);

echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
