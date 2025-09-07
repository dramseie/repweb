const fs = require('fs');
const cp = require('child_process');
const path = require('path');
const yaml = require('js-yaml');

const ROOT = process.cwd();
const SRC = path.join(ROOT, 'tools', 'progress.yaml');
const OUT = path.join(ROOT, 'public', 'progress-summary.md');

function pct(a, b) { return b === 0 ? 0 : Math.round((a / b) * 100); }

function loadProgress() {
  const y = fs.readFileSync(SRC, 'utf8');
  return yaml.load(y);
}

function gatherGitStats() {
  try {
    const since = '7 days ago';
    const log = cp.execSync(`git log --since='${since}' --pretty=format:"- %h %ad %s" --date=short`, { encoding: 'utf8' });
    const authors = cp.execSync(`git shortlog -sn --since='${since}'`, { encoding: 'utf8' });
    return { log, authors };
  } catch {
    return { log: '', authors: '' };
  }
}

function renderMarkdown(state) {
  const { project = 'project', milestones = [] } = state;
  const tasks = milestones.flatMap(m => m.tasks || []);
  const done = tasks.filter(t => t.status === 'done').length;
  const doing = tasks.filter(t => t.status === 'doing').length;
  const todo = tasks.filter(t => t.status === 'todo').length;
  const overall = pct(done, tasks.length);

  const { log, authors } = gatherGitStats();

  const lines = [];
  lines.push(`# ${project} — Progress Summary`);
  lines.push(`_Generated: ${new Date().toISOString().replace('T',' ').slice(0,19)}_`);
  lines.push('');
  lines.push(`**Overall:** ${overall}% complete  •  ✅ ${done}  ▶️ ${doing}  ⏳ ${todo}`);
  lines.push('');
  for (const m of milestones) {
    const msTasks = m.tasks || [];
    const d = msTasks.filter(t => t.status === 'done').length;
    const prog = pct(d, msTasks.length);
    lines.push(`## ${m.key}: ${m.title} — ${prog}% (due ${m.due || 'n/a'})`);
    for (const t of msTasks) {
      const icon = t.status === 'done' ? '✅' : t.status === 'doing' ? '▶️' : '⏳';
      lines.push(`- ${icon} **${t.id}** ${t.title}${t.owner ? ` _(owner: ${t.owner})_` : ''}`);
    }
    lines.push('');
  }
  lines.push('---');
  lines.push('### Last 7 days — contributors');
  lines.push('```');
  lines.push(authors.trim() || '(no recent commits)');
  lines.push('```');
  lines.push('');
  lines.push('### Last 7 days — commits');
  lines.push('```');
  lines.push(log.trim() || '(no recent commits)');
  lines.push('```');
  return lines.join('\n');
}

(function main() {
  const state = loadProgress();
  const md = renderMarkdown(state);
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, md, 'utf8');
  console.log(`Wrote ${OUT}`);
})();
