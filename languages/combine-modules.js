// Script Node.js para combinar módulos de traducción en un solo ui.json
const fs = require('fs');
const path = require('path');

const modulesDir = path.join(__dirname, 'modules');
const outputFile = path.join(__dirname, 'ui.json');

// 'ui.json' no debe existir dentro de modules/ (es el archivo de salida, que vive
// un nivel arriba); si alguien lo guarda ahi por error, tratarlo como un modulo
// mas lo mezclaria al final y pisaria en silencio las claves de otros modulos
// (paso real: un ui.json viejo en modules/ pisaba ediciones frescas de intelindev.json).
const files = fs.readdirSync(modulesDir).filter(f => f.endsWith('.json') && f !== 'ui.json');

// Función para aplanar objetos anidados en claves planas (ej: hero.title)
function flatten(obj, prefix = '', res = {}) {
  for (const key in obj) {
    if (!Object.prototype.hasOwnProperty.call(obj, key)) continue;
    const value = obj[key];
    const newKey = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'object' && value !== null && !Array.isArray(value) && !('es' in value || 'en' in value || 'pt' in value)) {
      flatten(value, newKey, res);
    } else {
      res[newKey] = value;
    }
  }
  return res;
}

let result = {};
files.forEach(file => {
  const data = JSON.parse(fs.readFileSync(path.join(modulesDir, file), 'utf8'));
  const flat = flatten(data);
  result = { ...result, ...flat };
});

fs.writeFileSync(outputFile, JSON.stringify(result, null, 2));
console.log('Traducciones combinadas y aplanadas en', outputFile);
