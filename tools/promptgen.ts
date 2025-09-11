#!/usr/bin/env ts-node
import fs from 'fs';
import path from 'path';
import matter from 'gray-matter';
import Mustache from 'mustache';
import { execSync } from 'child_process';

const MODEL = process.env.LLM_MODEL ?? 'gpt-4.1';
const API_KEY = process.env.OPENAI_API_KEY;
const OPENAI_BASE_URL = (process.env.OPENAI_BASE_URL ?? 'https://api.openai.com').replace(/\/+$/, '');
const CHAT_COMPLETIONS_URL = `${OPENAI_BASE_URL}/v1/chat/completions`;

/** ---- LLM 呼び出し ---- */
function callLLM(prompt: string): string {
  if (!API_KEY) throw new Error('OPENAI_API_KEY is not set');

  const payload = {
    model: MODEL,
    messages: [{ role: 'user', content: prompt }],
    temperature: 0,
  };
  const jsonStr = JSON.stringify(payload);

  // curl 経由（シェル安全化：単一引用内の ' をエスケープ）
  const safeData = jsonStr.replace(/'/g, `'\\''`);
  const cmd = `curl -s -H "Authorization: Bearer ${API_KEY}" -H "Content-Type: application/json" -d '${safeData}' ${CHAT_COMPLETIONS_URL}`;

  const resp = execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'] }).toString();
  let parsed: any;
  try {
    parsed = JSON.parse(resp);
  } catch (e) {
    throw new Error(`LLM response is not JSON:\n${resp}`);
  }
  if (parsed.error) {
    throw new Error(`LLM error: ${parsed.error.message ?? JSON.stringify(parsed.error)}`);
  }
  const content = parsed?.choices?.[0]?.message?.content;
  if (typeof content !== 'string' || !content.trim()) {
    throw new Error(`LLM returned empty content:\n${resp}`);
  }
  return content;
}

/** ---- partials ローダ（prompts/_partials と prompts/partials の両方） ---- */
function loadAllPartials(): Record<string, string> {
  const roots = [
    path.join(process.cwd(), 'prompts', '_partials'),
    path.join(process.cwd(), 'prompts', 'partials'),
  ];
  const partials: Record<string, string> = {};

  for (const dir of roots) {
    if (!fs.existsSync(dir)) continue;
    for (const name of fs.readdirSync(dir)) {
      const full = path.join(dir, name);
      if (fs.statSync(full).isFile()) {
        // ファイル名そのままをキーに登録（例: common-php-guardrails.md）
        partials[name] = fs.readFileSync(full, 'utf8');
      }
    }
  }
  return partials;
}

/** ---- フロントマター + CLI 変数でプロンプトを描画 ---- */
function renderPrompt(file: string, vars: Record<string, any>) {
  const raw = fs.readFileSync(file, 'utf8');
  const gm = matter(raw);

  const partials = loadAllPartials();

  // front-matter と CLI 変数をマージ（CLI が優先）
  const fmData = gm.data ?? {};
  const merged = { ...fmData, ...vars };

  // Mustache レンダリング
  const prompt = Mustache.render(gm.content, merged, partials);

  // 出力先の決定（優先度: CLI > front-matter > 自動推定）
  const className: string | undefined = merged.class_name ?? fmData.class_name;
  const namespaceStr: string | undefined = merged.namespace ?? fmData.namespace;
  let outputPath: string | undefined = merged.output_path ?? fmData.output_path;

  if (!outputPath) {
    // 自動推定: namespace が SymbolSdk\\Foo\\Bar なら src/Foo/Bar/<Class>.php
    // namespace 未指定なら src/<Class>.php
    const parts = (namespaceStr ?? '').split('\\').filter(Boolean);
    let subdirs: string[] = [];
    if (parts.length > 0) {
      // 先頭はベンダールート（SymbolSdk想定）。それ以降をディレクトリに。
      subdirs = parts.slice(1);
    }
    const fileName = (className ?? 'Output') + '.php';
    outputPath = path.join('src', ...subdirs, fileName);
  }

  return { prompt, outputPath, context: merged };
}

/** ---- PHPコード抽出＆サニタイズ ----
 * - 最初の "<?php" 以降のみ維持
 * - 末尾の ``` / ===END=== / ```php などを除去
 * - BOMや先頭空白を除去
 * - 最終行に改行を付与
 */
function extractPhp(content: string): string {
  // normalize line endings
  let out = content.replace(/\r\n/g, '\n');

  // drop everything before the first '<?php'
  const idx = out.indexOf('<?php');
  if (idx >= 0) {
    out = out.slice(idx);
  } else {
    // ===FILE ブロックから抽出 fallback
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

  // strip trailing fences/back-matter
  out = out.replace(/\n```+\s*$/g, '');
  out = out.replace(/```+(?:php)?\s*$/gi, '');
  out = out.replace(/\n?===END===\s*$/g, '');

  // strip any leading BOM/whitespace before '<?php'
  out = out.replace(/^\uFEFF/, ''); // BOM
  out = out.replace(/^\s*(?=<\?php)/, ''); // whitespace before php

  // ensure ends with a newline
  if (!out.endsWith('\n')) out += '\n';
  return out;
}

/** ---- 書き出し ---- */
function writeOut(outputPath: string, content: string) {
  const phpOnly = extractPhp(content);
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, phpOnly, { encoding: 'utf8' });
  console.log(`Wrote ${outputPath}`);
}

/** ---- CLI メイン ---- */
const file = process.argv[2];
if (!file) {
  console.error('Usage: promptgen <promptfile> [k=v ...]');
  process.exit(1);
}
const vars = process.argv.slice(3).reduce((acc, kv) => {
  const eq = kv.indexOf('=');
  if (eq <= 0) return acc;
  const k = kv.slice(0, eq);
  const v = kv.slice(eq + 1);
  acc[k] = v;
  return acc;
}, {} as Record<string, string>);

const { prompt, outputPath } = renderPrompt(file, vars);
const out = callLLM(prompt);
writeOut(outputPath, out);