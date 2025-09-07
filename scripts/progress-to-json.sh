#!/usr/bin/env bash
set -euo pipefail
node - <<'NODE'
const fs=require('fs');
const path=require('path');
const yaml=require('js-yaml');
const src=path.join(process.cwd(),'tools','progress.yaml');
const out=path.join(process.cwd(),'public','endpoints.json');
const y=fs.readFileSync(src,'utf8');
const o=yaml.load(y)||{};
const payload={endpoints:o.endpoints||[], ci_endpoints:o.ci_endpoints||{}};
fs.mkdirSync(path.dirname(out),{recursive:true});
fs.writeFileSync(out, JSON.stringify(payload,null,2));
console.log('Wrote '+out);
NODE
