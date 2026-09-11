#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
THEME_NAME="$(basename "${THEME_DIR}")"
OUTPUT_DIR="${1:-$(cd "${THEME_DIR}/.." && pwd)}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
ZIP_NAME="${THEME_NAME}-wp-import-${TIMESTAMP}.zip"
ZIP_PATH="${OUTPUT_DIR}/${ZIP_NAME}"

mkdir -p "${OUTPUT_DIR}"

STAGE_ROOT="$(mktemp -d)"
trap 'rm -rf "${STAGE_ROOT}"' EXIT
STAGE_THEME_DIR="${STAGE_ROOT}/${THEME_NAME}"

rsync -a "${THEME_DIR}/" "${STAGE_THEME_DIR}/" \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.vscode/' \
  --exclude '.idea/' \
  --exclude '.codex/' \
  --exclude 'node_modules/' \
  --exclude 'src/' \
  --exclude 'docs/' \
  --exclude 'graphify-out/' \
  --exclude 'tmp/' \
  --exclude 'test/' \
  --exclude 'tests/' \
  --exclude 'releases/' \
  --exclude '.DS_Store' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'pnpm-lock.yaml' \
  --exclude 'yarn.lock' \
  --exclude 'postcss.config.js' \
  --exclude 'tailwind.config.js' \
  --exclude 'tsconfig.json' \
  --exclude 'webpack.config.js' \
  --exclude 'vite.config.js' \
  --exclude 'vite.config.ts' \
  --exclude '.eslintrc' \
  --exclude '.eslintrc.js' \
  --exclude '.eslintrc.cjs' \
  --exclude '.prettierrc' \
  --exclude '.prettierrc.js' \
  --exclude 'README.md' \
  --exclude 'AGENTS.md' \
  --exclude 'CLAUDE.md'

(
  cd "${STAGE_ROOT}"
  zip -rq "${ZIP_PATH}" "${THEME_NAME}"
)

echo "ZIP creado: ${ZIP_PATH}"
