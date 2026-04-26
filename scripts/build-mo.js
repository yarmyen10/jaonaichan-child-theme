#!/usr/bin/env node
const fs   = require('fs');
const path = require('path');
const gettextParser = require('gettext-parser');

const LANG_DIR = path.join(__dirname, '..', 'src', 'inc', 'i18n', 'languages');

const files = fs.readdirSync(LANG_DIR)
  .filter(f => f.startsWith('jaonaichan-') && f.endsWith('.po'));

if (files.length === 0) {
  console.log('No .po files found in ' + LANG_DIR);
  process.exit(0);
}

let failed = 0;
for (const f of files) {
  const poPath = path.join(LANG_DIR, f);
  const moPath = path.join(LANG_DIR, f.replace(/\.po$/, '.mo'));
  try {
    const parsed = gettextParser.po.parse(fs.readFileSync(poPath));
    fs.writeFileSync(moPath, gettextParser.mo.compile(parsed));
    console.log(`  compiled  ${f} -> ${path.basename(moPath)}`);
  } catch (err) {
    failed++;
    console.error(`  failed    ${f}: ${err.message}`);
  }
}

if (failed > 0) process.exit(1);
