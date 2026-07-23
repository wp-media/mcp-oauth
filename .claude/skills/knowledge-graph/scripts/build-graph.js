#!/usr/bin/env node
/**
 * Generic Knowledge Graph Builder
 *
 * Scans source files (PHP, JS/TS, Python out of the box) and extracts symbol declarations
 * and import/dependency relationships into a structured JSON graph at
 * .claude/graph/dependency-graph.json.
 *
 * First run: full scan of all git-tracked source files.
 * Subsequent runs: incremental — only files changed since the last recorded commit.
 *
 * Usage:
 *   node .claude/skills/knowledge-graph/scripts/build-graph.js           # auto (incremental if possible)
 *   node .claude/skills/knowledge-graph/scripts/build-graph.js --full    # force full rebuild
 *   node .claude/skills/knowledge-graph/scripts/build-graph.js --dry-run # print stats without writing
 *
 * CUSTOMIZE:
 *   SCAN_DIRS  — top-level directories to scan (default: common source roots that exist).
 *   EXTENSIONS — file extensions per language.
 *   Add a parser for a new language by following parsePhp / parseJs / parsePy below and
 *   wiring it into languageOf() + processFile().
 */

'use strict';

const fs   = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

const ROOT       = process.cwd();
const GRAPH_PATH = path.join(ROOT, '.claude', 'graph', 'dependency-graph.json');

// Scan these top-level dirs if they exist. Override by editing this list for your project.
const CANDIDATE_DIRS = ['src', 'lib', 'app', 'inc', 'pkg', 'internal', 'packages'];
const SCAN_DIRS = CANDIDATE_DIRS.filter(d => fs.existsSync(path.join(ROOT, d)));

const EXTENSIONS = {
	php:    ['.php'],
	js:     ['.js', '.ts', '.tsx', '.jsx', '.mjs', '.cjs'],
	python: ['.py'],
};

const ARGS    = process.argv.slice(2);
const FORCE   = ARGS.includes('--full');
const DRY_RUN = ARGS.includes('--dry-run');

// ---------------------------------------------------------------------------
// Git helpers
// ---------------------------------------------------------------------------

function git(cmd) {
	try { return execSync(`git -C "${ROOT}" ${cmd}`, { encoding: 'utf8' }).trim(); }
	catch { return null; }
}
function headSha() { return git('rev-parse HEAD'); }
function changedFiles(fromSha, toSha = 'HEAD') {
	const out = git(`diff --name-only ${fromSha}..${toSha}`);
	return out ? out.split('\n').filter(Boolean) : [];
}
function allTrackedFiles() {
	const out = git('ls-files');
	return out ? out.split('\n').filter(Boolean) : [];
}

// ---------------------------------------------------------------------------
// PHP parser
// ---------------------------------------------------------------------------

function parsePhp(content) {
	const imports = [];
	const symbols = [];
	let namespace = null;

	const nsMatch = content.match(/^\s*namespace\s+([\w\\]+)\s*;/m);
	if (nsMatch) namespace = nsMatch[1];

	// use Foo\Bar; / use Foo\Bar as Baz; / use Foo\{A, B as C, D};
	const useRe = /^\s*use\s+([\w\\]+)(?:\\{([^}]+)})?\s*(?:as\s+\w+)?\s*;/gm;
	let m;
	while ((m = useRe.exec(content)) !== null) {
		const base = m[1], grouped = m[2];
		if (grouped) {
			for (const part of grouped.split(',')) {
				imports.push(`${base}\\${part.trim().replace(/\s+as\s+\w+$/, '')}`);
			}
		} else {
			imports.push(base.replace(/\s+as\s+\w+$/, '').trim());
		}
	}

	const declRe = /^\s*(abstract\s+|final\s+|readonly\s+)*(class|interface|trait|enum)\s+(\w+)(?:\s+extends\s+([\w\\,\s]+?))?(?:\s+implements\s+([\w\\,\s]+?))?\s*(?:\{|$)/gm;
	while ((m = declRe.exec(content)) !== null) {
		symbols.push({
			kind: m[2],
			name: m[3],
			extends: m[4] ? m[4].split(',').map(s => s.trim()).filter(Boolean) : [],
			implements: m[5] ? m[5].split(',').map(s => s.trim()).filter(Boolean) : [],
		});
	}

	return { namespace, symbols, imports: [...new Set(imports)] };
}

// ---------------------------------------------------------------------------
// JS / TS parser
// ---------------------------------------------------------------------------

function parseJs(content) {
	const imports = [];
	let m;
	// import ... from 'source'
	const staticRe = /\bimport\s+(?:[\w*{][^'"]*from\s+)?['"]([^'"]+)['"]/g;
	while ((m = staticRe.exec(content)) !== null) imports.push(m[1]);
	// import('source') / require('source')
	const dynRe = /(?:\bimport|\brequire)\s*\(\s*['"]([^'"]+)['"]\s*\)/g;
	while ((m = dynRe.exec(content)) !== null) imports.push(m[1]);

	// exported classes/functions as symbols (best-effort, no namespace in JS)
	const symbols = [];
	const declRe = /^\s*(?:export\s+(?:default\s+)?)?(class|function)\s+(\w+)(?:\s+extends\s+([\w.]+))?/gm;
	while ((m = declRe.exec(content)) !== null) {
		symbols.push({ kind: m[1], name: m[2], extends: m[3] ? [m[3]] : [], implements: [] });
	}

	return { symbols, imports: [...new Set(imports)] };
}

// ---------------------------------------------------------------------------
// Python parser
// ---------------------------------------------------------------------------

function parsePy(content) {
	const imports = [];
	let m;
	// import a.b.c  /  import a as b
	const importRe = /^\s*import\s+([\w.]+)(?:\s+as\s+\w+)?/gm;
	while ((m = importRe.exec(content)) !== null) imports.push(m[1]);
	// from a.b import c, d
	const fromRe = /^\s*from\s+([\w.]+)\s+import\s+(.+)$/gm;
	while ((m = fromRe.exec(content)) !== null) {
		for (const part of m[2].split(',')) {
			const name = part.trim().replace(/\s+as\s+\w+$/, '').replace(/[()]/g, '').trim();
			if (name && name !== '*') imports.push(`${m[1]}.${name}`);
		}
	}

	const symbols = [];
	const declRe = /^\s*(class|def)\s+(\w+)(?:\s*\(([^)]*)\))?/gm;
	while ((m = declRe.exec(content)) !== null) {
		const bases = m[1] === 'class' && m[3]
			? m[3].split(',').map(s => s.trim()).filter(Boolean)
			: [];
		symbols.push({ kind: m[1], name: m[2], extends: bases, implements: [] });
	}

	return { symbols, imports: [...new Set(imports)] };
}

// ---------------------------------------------------------------------------
// File scanner
// ---------------------------------------------------------------------------

function isSourceFile(relPath) {
	if (SCAN_DIRS.length === 0) return languageOf(relPath) !== null; // no dirs configured → scan by extension
	return SCAN_DIRS.some(d => relPath.startsWith(d + '/') || relPath.startsWith(d + '\\'));
}

function languageOf(relPath) {
	const ext = path.extname(relPath).toLowerCase();
	for (const [lang, exts] of Object.entries(EXTENSIONS)) {
		if (exts.includes(ext)) return lang;
	}
	return null;
}

function processFile(relPath) {
	const lang = languageOf(relPath);
	if (!lang || !isSourceFile(relPath)) return null;

	const absPath = path.join(ROOT, relPath);
	if (!fs.existsSync(absPath)) return null;

	const content = fs.readFileSync(absPath, 'utf8');
	if (lang === 'php')    { const r = parsePhp(content); return { language: 'php', namespace: r.namespace, symbols: r.symbols, imports: r.imports }; }
	if (lang === 'python') { const r = parsePy(content);  return { language: 'python', namespace: null, symbols: r.symbols, imports: r.imports }; }
	const r = parseJs(content);
	return { language: 'js', namespace: null, symbols: r.symbols, imports: r.imports };
}

// ---------------------------------------------------------------------------
// Symbol index builder
// ---------------------------------------------------------------------------

function buildSymbolIndex(nodes) {
	const index = {};
	for (const [filePath, node] of Object.entries(nodes)) {
		if (!node.symbols) continue;
		for (const sym of node.symbols) {
			const fqn = node.namespace ? `${node.namespace}\\${sym.name}` : sym.name;
			// First declaration wins; namespaced symbols are globally unique, bare names may collide.
			if (!(fqn in index)) index[fqn] = filePath;
		}
	}
	return index;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

function loadGraph() {
	if (fs.existsSync(GRAPH_PATH)) {
		try { return JSON.parse(fs.readFileSync(GRAPH_PATH, 'utf8')); } catch { /* corrupted — start fresh */ }
	}
	return { generated_at: null, base_commit: null, nodes: {}, symbol_index: {} };
}

function saveGraph(graph) {
	fs.mkdirSync(path.dirname(GRAPH_PATH), { recursive: true });
	fs.writeFileSync(GRAPH_PATH, JSON.stringify(graph, null, 2) + '\n', 'utf8');
}

function run() {
	const currentSha = headSha();
	const existing   = loadGraph();

	const isIncremental = !FORCE && existing.base_commit && currentSha && existing.base_commit !== currentSha;
	const isUpToDate    = !FORCE && existing.base_commit === currentSha && currentSha !== null;

	if (isUpToDate) {
		console.log(`✓ Knowledge graph already up to date (${currentSha.slice(0, 8)}).`);
		return;
	}

	let filesToProcess, mode;
	if (isIncremental) {
		filesToProcess = changedFiles(existing.base_commit, 'HEAD');
		mode = `incremental (${filesToProcess.length} changed files since ${existing.base_commit.slice(0, 8)})`;
	} else {
		filesToProcess = allTrackedFiles();
		mode = `full scan (${filesToProcess.length} tracked files)`;
	}

	console.log(`⚙  Building knowledge graph — ${mode} …`);
	if (SCAN_DIRS.length) console.log(`   Scan dirs: ${SCAN_DIRS.join(', ')}`);

	const nodes = isIncremental ? { ...existing.nodes } : {};
	let processed = 0, removed = 0;

	for (const relPath of filesToProcess) {
		const lang = languageOf(relPath);
		if (!lang || !isSourceFile(relPath)) continue;

		const absPath = path.join(ROOT, relPath);
		if (!fs.existsSync(absPath)) { if (nodes[relPath]) { delete nodes[relPath]; removed++; } continue; }

		const node = processFile(relPath);
		if (node) { nodes[relPath] = node; processed++; }
	}

	const symbol_index = buildSymbolIndex(nodes);
	const graph = {
		generated_at: new Date().toISOString(),
		base_commit:  currentSha,
		node_count:   Object.keys(nodes).length,
		nodes,
		symbol_index,
	};

	console.log(`   Processed : ${processed}`);
	if (removed) console.log(`   Removed   : ${removed}`);
	console.log(`   Total nodes: ${graph.node_count}`);
	console.log(`   Symbols indexed: ${Object.keys(symbol_index).length}`);

	if (DRY_RUN) { console.log('ℹ  Dry-run — graph not written.'); return; }

	saveGraph(graph);
	console.log('✓  Graph saved → .claude/graph/dependency-graph.json');
}

run();
