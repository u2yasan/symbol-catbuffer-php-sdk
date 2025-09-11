#!/usr/bin/env ts-node
import fs from 'fs';
import path from 'path';
import matter from 'gray-matter';
import Mustache from 'mustache';
import { execSync } from 'child_process';

const MODEL = process.env.LLM_MODEL ?? 'gpt-4.1';
const API_KEY = process.env.OPENAI_API_KEY;

function callLLM(prompt: string): string {
  if (!API_KEY) throw new Error('OPENAI_API_KEY is not set');
  const payload = JSON.stringify({ model: MODEL, messages: [{ role: 'user', content: prompt }] });
  const cmd = `curl -s -H "Authorization: Bearer ${API_KEY}" -H "Content-Type: application/json" \
    -d '${payload.replace(/'/g, `'\\''`)}' https://api.openai.com/v1/chat/completions`;
  const resp = execSync(cmd).toString();
  const json = JSON.parse(resp);
  return json.choices[0].message.content as string;
}

function loadPartial(name: string) {
  const p = path.join(process.cwd(), 'prompts', '_partials', name);
  return fs.existsSync(p) ? fs.readFileSync(p, 'utf8') : '';
}

function renderPrompt(file: string, vars: Record<string, any>) {
  const raw = fs.readFileSync(file, 'utf8');
  const gm = matter(raw);
  const partials = { 'common-principles.md': loadPartial('common-principles.md') };
  const prompt = Mustache.render(gm.content, { ...gm.data, ...vars }, partials);
  return { prompt, outputPath: gm.data.output_path as string };
}

/**
 * PHPコード抽出＆サニタイズ
 * - 最初の "<?php" 以降のみ維持
 * - 末尾の ``` / ===END=== / ```php などを除去
 * - BOMや先頭空白を除去
 * - 必要なら末尾に改行を付与
 */
function extractPhp(content: string): string {
  // normalize line endings
  let out = content.replace(/\r\n/g, '\n');

  // drop everything before the first '<?php'
  const idx = out.indexOf('<?php');
  if (idx >= 0) {
    out = out.slice(idx);
  } else {
    // ファイルブロック形式（===FILE ...===）に対応：その中からPHPだけを拾う
    const fileBlock = /===FILE[^\n]*===\n([\s\S]*?)\n===END===/g;
    let m: RegExpExecArray | null;
    let picked = '';
    while ((m = fileBlock.exec(out)) !== null) {
      const block = m[1];
      const phpIdx = block.indexOf('<?php');
      if (phpIdx >= 0) {
        picked = block.slice(phpIdx);
        break;
      }
    }
    if (picked) {
      out = picked;
    } else {
      throw new Error('No "<?php" found in LLM output. Refusing to write non-PHP content.');
    }
  }

  // strip trailing fences/backticks
  // - remove trailing ``` blocks
  out = out.replace(/\n```+\s*$/g, '');
  // - remove trailing markdown code fence markers anywhere near the end
  out = out.replace(/```+(?:php)?\s*$/gi, '');
  // - remove trailing ===END=== if left
  out = out.replace(/\n?===END===\s*$/g, '');

  // strip any leading BOM/whitespace before '<?php' (should be none now)
  out = out.replace(/^\uFEFF/, ''); // BOM
  out = out.replace(/^\s*(?=<\?php)/, ''); // whitespace before php

  // ensure ends with a newline
  if (!out.endsWith('\n')) out += '\n';
  return out;
}

function writeOut(outputPath: string, content: string) {
  // 抽出・サニタイズ
  const phpOnly = extractPhp(content);

  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, phpOnly, { encoding: 'utf8' });
  console.log(`Wrote ${outputPath}`);
}

const file = process.argv[2];
if (!file) {
  console.error('Usage: promptgen <promptfile> [k=v ...]');
  process.exit(1);
}
const vars = process.argv.slice(3).reduce((acc, kv) => {
  const [k, v] = kv.split('=');
  acc[k] = v;
  return acc;
}, {} as Record<string, string>);

const { prompt, outputPath } = renderPrompt(file, vars);
const out = callLLM(prompt);
writeOut(outputPath, out);