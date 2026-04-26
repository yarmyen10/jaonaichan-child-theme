#!/usr/bin/env node
require('dotenv').config();

const ftp          = require('basic-ftp');
const path         = require('path');
const fs           = require('fs');
const { execSync } = require('child_process');

const DRY_RUN = process.argv.includes('--dry-run');
const CHANGED = process.argv.includes('--changed');
const ROOT    = __dirname;

const SKIP = new Set([
  'node_modules',
  '.git',
  '.env',
  '.env.example',
  'deploy.js',
  'package.json',
  'package-lock.json',
  '.gitignore',
  'readme.md',
  'CLAUDE.md',
  'composer.lock',
]);

const {
  FTP_HOST,
  FTP_USER,
  FTP_PASSWORD,
  FTP_PORT        = '21',
  FTP_SECURE      = 'false',
  FTP_REMOTE_PATH,
} = process.env;

const required = { FTP_HOST, FTP_USER, FTP_PASSWORD, FTP_REMOTE_PATH };
const missing  = Object.entries(required).filter(([, v]) => !v).map(([k]) => k);
if (missing.length) {
  console.error('Missing required env vars: ' + missing.join(', '));
  console.error('Copy .env.example to .env and fill in the values.');
  process.exit(1);
}

// ---------- full-tree mode ----------

function listLocal(localDir) {
  return fs.readdirSync(localDir, { withFileTypes: true })
    .filter(e => !SKIP.has(e.name))
    .sort((a, b) => a.name.localeCompare(b.name));
}

async function uploadDir(client, localDir, remoteDir) {
  await client.ensureDir(remoteDir);

  const entries = listLocal(localDir);

  for (const e of entries) {
    if (!e.isFile()) continue;
    const localPath = path.join(localDir, e.name);
    await client.uploadFrom(localPath, e.name);
    console.log(`  upload  ${remoteDir}/${e.name}`);
  }

  for (const e of entries) {
    if (!e.isDirectory()) continue;
    const localPath  = path.join(localDir, e.name);
    const remotePath = `${remoteDir}/${e.name}`;
    await uploadDir(client, localPath, remotePath);
  }
}

function walkDry(localDir, remoteDir) {
  for (const e of listLocal(localDir)) {
    const localPath  = path.join(localDir, e.name);
    const remotePath = `${remoteDir}/${e.name}`;
    if (e.isDirectory()) {
      console.log(`  mkdir   ${remotePath}`);
      walkDry(localPath, remotePath);
    } else {
      console.log(`  upload  ${remotePath}`);
    }
  }
}

// ---------- changed-files mode ----------

function getModifiedFiles() {
  return execSync('git diff --name-only', { cwd: ROOT, encoding: 'utf8' })
    .trim()
    .split('\n')
    .filter(Boolean);
}

function isSkipped(relPath) {
  return relPath.split('/').some(seg => SKIP.has(seg));
}

async function uploadOneFile(client, relPath) {
  const localPath  = path.join(ROOT, relPath);
  const posixRel   = relPath.replace(/\\/g, '/');
  const remotePath = `${FTP_REMOTE_PATH}/${posixRel}`;
  const remoteDir  = path.posix.dirname(remotePath);
  const remoteName = path.posix.basename(remotePath);

  await client.ensureDir(remoteDir);
  await client.uploadFrom(localPath, remoteName);
  console.log(`  upload  ${remotePath}`);
}

// ---------- main ----------

async function main() {
  const modeLabel = CHANGED ? 'CHANGED' : 'FULL';
  console.log(`Mode:        ${DRY_RUN ? `DRY-RUN ${modeLabel} (no FTP connection)` : `LIVE ${modeLabel}`}`);
  console.log(`Host:        ${FTP_HOST}:${FTP_PORT} (secure=${FTP_SECURE})`);
  console.log(`User:        ${FTP_USER}`);
  console.log(`Remote path: ${FTP_REMOTE_PATH}`);
  console.log(`Skipping:    ${[...SKIP].join(', ')}`);
  console.log('');

  if (CHANGED) {
    let files;
    try {
      files = getModifiedFiles();
    } catch (err) {
      console.error('Failed to query git: ' + err.message);
      console.error("--changed mode requires a git repository. Use 'npm run deploy' for a full deploy.");
      process.exit(1);
    }

    files = files
      .filter(f => !isSkipped(f))
      .filter(f => fs.existsSync(path.join(ROOT, f)))
      .sort();

    if (files.length === 0) {
      console.log('No modified files. Nothing to deploy.');
      return;
    }

    console.log(`Uploading ${files.length} modified file(s):`);
    for (const f of files) console.log(`  - ${f}`);
    console.log('');

    if (DRY_RUN) {
      console.log('Dry run complete. No files were uploaded.');
      return;
    }

    const client = new ftp.Client();
    client.ftp.verbose = false;

    try {
      await client.access({
        host:     FTP_HOST,
        port:     Number(FTP_PORT),
        user:     FTP_USER,
        password: FTP_PASSWORD,
        secure:   FTP_SECURE === 'true',
      });
      for (const f of files) {
        await uploadOneFile(client, f);
      }
      console.log('\nDeploy complete.');
    } catch (err) {
      console.error('Deploy failed:', err.message);
      process.exitCode = 1;
    } finally {
      client.close();
    }
    return;
  }

  // -- full deploy (default) --

  if (DRY_RUN) {
    walkDry(ROOT, FTP_REMOTE_PATH);
    console.log('\nDry run complete. No files were uploaded.');
    return;
  }

  const client = new ftp.Client();
  client.ftp.verbose = false;

  try {
    await client.access({
      host:     FTP_HOST,
      port:     Number(FTP_PORT),
      user:     FTP_USER,
      password: FTP_PASSWORD,
      secure:   FTP_SECURE === 'true',
    });
    await uploadDir(client, ROOT, FTP_REMOTE_PATH);
    console.log('\nDeploy complete.');
  } catch (err) {
    console.error('Deploy failed:', err.message);
    process.exitCode = 1;
  } finally {
    client.close();
  }
}

main();
