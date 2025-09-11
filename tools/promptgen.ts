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
  return json.choices[0].message.content;
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

function writeOut(outputPath: string, content: string) {
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, content);
  console.log(`Wrote ${outputPath}`);
}

const file = process.argv[2];
if (!file) { console.error('Usage: promptgen <promptfile> [k=v ...]'); process.exit(1); }
const vars = process.argv.slice(3).reduce((acc, kv) => { const [k,v] = kv.split('='); acc[k]=v; return acc; }, {} as Record<string,string>);
const { prompt, outputPath } = renderPrompt(file, vars);
const out = callLLM(prompt);
writeOut(outputPath, out);
