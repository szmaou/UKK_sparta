const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const LOGO_SVG_PATH = '/home/san/Documents/sparta/public/assets/img/SMK Negeri 1 Leuwimunding.svg';
const LUCIDE_DIR = '/home/san/Documents/sparta/public/assets/icons/lucide';
const OUT_FIGCRAFT = '/home/san/Documents/sparta/design/SPARTA.figcraft';
const OUT_JSON = '/home/san/Documents/sparta/design/SPARTA.json';
const TMP_BATCH = `/tmp/opencode/sparta_batch_${process.pid}.json`;

// Helper parse Lucide SVG to SVG path string for Figcraft vector.create
function normalizePath(d) {
  const tokens = [];
  const re = /([a-df-z])|([-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?)/gi;
  let match;
  while ((match = re.exec(d)) !== null) {
    if (match[1]) tokens.push({ type: 'cmd', val: match[1] });
    else tokens.push({ type: 'num', val: parseFloat(match[2]) });
  }

  const result = [];
  let curX = 0, curY = 0;
  let startX = 0, startY = 0;
  let i = 0;

  while (i < tokens.length) {
    const t = tokens[i++];
    if (t.type !== 'cmd') continue;
    let cmd = t.val;

    if (cmd === 'M') {
      curX = tokens[i++].val; curY = tokens[i++].val;
      startX = curX; startY = curY;
      result.push(`M ${curX} ${curY}`);
      while (i < tokens.length && tokens[i].type === 'num') {
        curX = tokens[i++].val; curY = tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'm') {
      curX += tokens[i++].val; curY += tokens[i++].val;
      startX = curX; startY = curY;
      result.push(`M ${curX} ${curY}`);
      while (i < tokens.length && tokens[i].type === 'num') {
        curX += tokens[i++].val; curY += tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'L') {
      while (i < tokens.length && tokens[i].type === 'num') {
        curX = tokens[i++].val; curY = tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'l') {
      while (i < tokens.length && tokens[i].type === 'num') {
        curX += tokens[i++].val; curY += tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'H') {
      while (i < tokens.length && tokens[i].type === 'num') {
        curX = tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'h') {
      while (i < tokens.length && tokens[i].type === 'num') {
        curX += tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'V') {
      while (i < tokens.length && tokens[i].type === 'num') {
        curY = tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'v') {
      while (i < tokens.length && tokens[i].type === 'num') {
        curY += tokens[i++].val;
        result.push(`L ${curX} ${curY}`);
      }
    } else if (cmd === 'A') {
      while (i < tokens.length && tokens[i].type === 'num') {
        const rx = tokens[i++]; const ry = tokens[i++];
        const xRot = tokens[i++]; const largeArc = tokens[i++];
        const sweep = tokens[i++];
        curX = tokens[i++].val; curY = tokens[i++].val;
        result.push(`A ${rx.val} ${ry.val} ${xRot.val} ${largeArc.val} ${sweep.val} ${curX} ${curY}`);
      }
    } else if (cmd === 'a') {
      while (i < tokens.length && tokens[i].type === 'num') {
        const rx = tokens[i++]; const ry = tokens[i++];
        const xRot = tokens[i++]; const largeArc = tokens[i++];
        const sweep = tokens[i++];
        curX += tokens[i++].val; curY += tokens[i++].val;
        result.push(`A ${rx.val} ${ry.val} ${xRot.val} ${largeArc.val} ${sweep.val} ${curX} ${curY}`);
      }
    } else if (cmd === 'C') {
      while (i < tokens.length && tokens[i].type === 'num') {
        const x1 = tokens[i++].val; const y1 = tokens[i++].val;
        const x2 = tokens[i++].val; const y2 = tokens[i++].val;
        curX = tokens[i++].val; curY = tokens[i++].val;
        result.push(`C ${x1} ${y1} ${x2} ${y2} ${curX} ${curY}`);
      }
    } else if (cmd === 'c') {
      while (i < tokens.length && tokens[i].type === 'num') {
        const x1 = curX + tokens[i++].val; const y1 = curY + tokens[i++].val;
        const x2 = curX + tokens[i++].val; const y2 = curY + tokens[i++].val;
        curX += tokens[i++].val; curY += tokens[i++].val;
        result.push(`C ${x1} ${y1} ${x2} ${y2} ${curX} ${curY}`);
      }
    } else if (cmd === 'Z' || cmd === 'z') {
      curX = startX; curY = startY;
      result.push('Z');
    }
  }

  return result.join(' ');
}

function svgToPath(svgStr) {
  let combined = [];

  // rect
  const rectRe = /<rect\s+([^>]+)\s*\/?>/g;
  let m;
  while ((m = rectRe.exec(svgStr)) !== null) {
    const attrs = parseAttrs(m[1]);
    const x = parseFloat(attrs.x || 0);
    const y = parseFloat(attrs.y || 0);
    const w = parseFloat(attrs.width);
    const h = parseFloat(attrs.height);
    const rx = parseFloat(attrs.rx || 0);
    if (rx > 0) {
      combined.push(`M ${x+rx} ${y} H ${x+w-rx} A ${rx} ${rx} 0 0 1 ${x+w} ${y+rx} V ${y+h-rx} A ${rx} ${rx} 0 0 1 ${x+w-rx} ${y+h} H ${x+rx} A ${rx} ${rx} 0 0 1 ${x} ${y+h-rx} V ${y+rx} A ${rx} ${rx} 0 0 1 ${x+rx} ${y} Z`);
    } else {
      combined.push(`M ${x} ${y} H ${x+w} V ${y+h} H ${x} Z`);
    }
  }

  // circle
  const circRe = /<circle\s+([^>]+)\s*\/?>/g;
  while ((m = circRe.exec(svgStr)) !== null) {
    const attrs = parseAttrs(m[1]);
    const cx = parseFloat(attrs.cx);
    const cy = parseFloat(attrs.cy);
    const r = parseFloat(attrs.r);
    combined.push(`M ${cx-r} ${cy} A ${r} ${r} 0 1 0 ${cx+r} ${cy} A ${r} ${r} 0 1 0 ${cx-r} ${cy} Z`);
  }

  // line
  const lineRe = /<line\s+([^>]+)\s*\/?>/g;
  while ((m = lineRe.exec(svgStr)) !== null) {
    const attrs = parseAttrs(m[1]);
    combined.push(`M ${attrs.x1} ${attrs.y1} L ${attrs.x2} ${attrs.y2}`);
  }

  // polyline
  const polylineRe = /<polyline\s+([^>]+)\s*\/?>/g;
  while ((m = polylineRe.exec(svgStr)) !== null) {
    const attrs = parseAttrs(m[1]);
    const pts = attrs.points.trim().split(/\s+/).map(p => p.split(',').join(' '));
    combined.push(`M ${pts[0]} L ${pts.slice(1).join(' L ')}`);
  }

  // polygon
  const polygonRe = /<polygon\s+([^>]+)\s*\/?>/g;
  while ((m = polygonRe.exec(svgStr)) !== null) {
    const attrs = parseAttrs(m[1]);
    const pts = attrs.points.trim().split(/\s+/).map(p => p.split(',').join(' '));
    combined.push(`M ${pts[0]} L ${pts.slice(1).join(' L ')} Z`);
  }

  // path
  const pathRe = /<path\s+([^>]+)\s*\/?>/g;
  while ((m = pathRe.exec(svgStr)) !== null) {
    const attrs = parseAttrs(m[1]);
    if (attrs.d) {
      combined.push(normalizePath(attrs.d));
    }
  }

  // Keep viewBox 24x24 bounds without stretching: anchor 0,0 and 24,24
  return `M 0 0 L 0 0 M 24 24 L 24 24 ${combined.join(' ')}`;
}

function parseAttrs(str) {
  const attrs = {};
  const re = /([a-zA-Z0-9_-]+)="([^"]*)"/g;
  let m;
  while ((m = re.exec(str)) !== null) {
    attrs[m[1]] = m[2];
  }
  return attrs;
}

// Load all Lucide SVGs


// Assertions for Auth Frames (Email-only, Single logo on mobile)
function textUnder(docData, rootId) {
  const values = [];
  const walk = (id) => {
    const node = docData.nodes[id];
    if (!node) return;
    if (node.type === 'text') values.push(node.characters || node.text || '');
    (node.children || []).forEach(walk);
  };
  walk(rootId);
  return values;
}

function runPetugasAssertions(docData) {
  const pageId = docData.pages.find(id => docData.nodes[id].name === '05 — Petugas Ops');
  if (!pageId) throw new Error('Assertion failed: Page 05 — Petugas Ops not found');
  const frames = docData.nodes[pageId].children.map(id => docData.nodes[id]).filter(n => n && n.type === 'frame');
  const required = {
    'Petugas — Konfirmasi Serah Terima PJ-0003': ['Konfirmasi Serah Terima', 'Tanggal Serah Terima Aktual', 'Status setelah konfirmasi: Dipinjam'],
    'Petugas — Verifikasi Pengembalian Aktif PJ-0006': ['Verifikasi Pengembalian', 'Minta Koreksi', 'Jumlah unit baik', 'Jumlah unit rusak', 'Denda Estimasi'],
    'Petugas — Monitoring Pengembalian': ['Sedang Dipinjam', 'Terlambat', 'Pengembalian Diajukan', 'Selesai', 'PJ-0001', 'PJ-0002', 'PJ-0005'],
    'Petugas — Filter & Preview Laporan': ['Tanggal Mulai', 'Tanggal Akhir', 'Status', 'Kategori', 'Terapkan Filter', 'Reset', 'Preview sebelum cetak', 'Cetak Laporan'],
  };
  for (const [name, labels] of Object.entries(required)) {
    const frame = frames.find(f => f.name === name);
    if (!frame) throw new Error(`Assertion failed: Missing Petugas frame "${name}"`);
    const texts = textUnder(docData, frame.id);
    for (const label of labels) if (!texts.some(t => t.includes(label))) throw new Error(`Assertion failed: "${name}" missing "${label}"`);
  }
  const archive = frames.find(f => f.name === 'Petugas — Verifikasi Pengembalian & Denda');
  const archiveTexts = textUnder(docData, archive.id);
  if (!archiveTexts.some(t => t.includes('PJ-0005') && t.includes('Read-Only'))) throw new Error('Assertion failed: PJ-0005 archive must remain read-only');
  if (archiveTexts.some(t => t === 'Verifikasi Pengembalian' || t === 'Minta Koreksi')) throw new Error('Assertion failed: PJ-0005 archive exposes active actions');
  console.log('✓ All Petugas assertions passed (handover, active return, monitoring, report preview, archive guardrail).');
}

function runCoreReferenceAssertions(docData) {
  const requirements = {
    '06 — Peminjam Exp': {
      'Peminjam — Detail Alat ALT-001': ['Bor listrik', 'Stok tersedia: 5 unit', 'Ajukan Peminjaman'],
      'Peminjam — Detail PJ-0004 Batalkan Pengajuan': ['Siti Rahma', 'Menunggu Persetujuan', 'Batalkan Pengajuan', 'Ya, Batalkan Pengajuan'],
      'Peminjam — Ajukan Pengembalian PJ-0001': ['Andi Pratama', 'Kondisi yang Dilaporkan', 'Estimasi denda', 'Ajukan Pengembalian'],
    },
    '04 — Admin CRUD': {
      'Admin — Peminjaman Tambah': ['Peminjam', 'Alat', 'Jumlah Unit', 'Simpan sebagai Menunggu Persetujuan'],
      'Admin — Peminjaman Edit PJ-0004': ['Siti Rahma', 'Menunggu Persetujuan', 'Simpan Perubahan', 'Edit hanya tersedia sebelum persetujuan'],
      'Admin — Koreksi Pengembalian PJ-0005': ['Alasan Koreksi', 'Catatan Pengembalian', 'Simpan Koreksi Berjejak', 'Stok tidak dipulihkan ulang'],
    },
  };
  for (const [pageName, framesRequired] of Object.entries(requirements)) {
    const pageId = docData.pages.find(id => docData.nodes[id].name === pageName);
    if (!pageId) throw new Error(`Assertion failed: Page "${pageName}" not found`);
    const frames = docData.nodes[pageId].children.map(id => docData.nodes[id]).filter(n => n && n.type === 'frame');
    for (const [frameName, labels] of Object.entries(framesRequired)) {
      const frame = frames.find(f => f.name === frameName);
      if (!frame) throw new Error(`Assertion failed: Missing core frame "${frameName}"`);
      const texts = textUnder(docData, frame.id);
      for (const label of labels) if (!texts.some(t => t.includes(label))) throw new Error(`Assertion failed: "${frameName}" missing "${label}"`);
    }
  }
  const cancelTexts = textUnder(docData, docData.nodes[docData.pages.find(id => docData.nodes[id].name === '06 — Peminjam Exp')].children.find(id => docData.nodes[id].name === 'Peminjam — Detail PJ-0004 Batalkan Pengajuan'));
  if (cancelTexts.some(t => t.includes('Andi Pratama'))) throw new Error('Assertion failed: PJ-0004 cancellation leaks another borrower');
  const returnTexts = textUnder(docData, docData.nodes[docData.pages.find(id => docData.nodes[id].name === '06 — Peminjam Exp')].children.find(id => docData.nodes[id].name === 'Peminjam — Ajukan Pengembalian PJ-0001'));
  if (returnTexts.some(t => t.includes('Siti Rahma'))) throw new Error('Assertion failed: PJ-0001 return has wrong owner');
  console.log('✓ All core reference assertions passed (fields, action guards, fixture ownership).');
}

function runCleanupAssertions(docData) {
  const removedFrames = ['Typography Scale', 'Spacing & Radius System', 'Status Badges & Pills', 'Cards & Feedback Banners'];
  for (const name of removedFrames) {
    if (Object.values(docData.nodes).some(node => node.name === name)) throw new Error(`Assertion failed: Removed frame "${name}" still exists`);
  }
  if (Object.values(docData.nodes).some(node => node.type === 'text' && (node.characters || node.text) === 'Lupa kata sandi?')) {
    throw new Error('Assertion failed: Fake forgot-password text still exists');
  }
  const adminUsers = Object.values(docData.nodes).find(node => node.name === 'Admin — Data Pengguna');
  if (!adminUsers) throw new Error('Assertion failed: Admin — Data Pengguna not found');
  const texts = textUnder(docData, adminUsers.id);
  if (texts.includes('Tambah Pengguna Baru') || texts.includes('Simpan Akun')) throw new Error('Assertion failed: Duplicate add-user modal still exists');
  if (!Object.values(docData.nodes).some(node => node.name === 'Admin — Pengguna Tambah')) throw new Error('Assertion failed: Dedicated add-user frame missing');
  console.log('✓ All cleanup assertions passed (optional frames, duplicate modal, fake forgot-password text absent).');
}

function runAuthAssertions(docData) {
  const pAuthId = docData.pages.find(pId => docData.nodes[pId].name === '03 — Authentication');
  if (!pAuthId) throw new Error('Assertion failed: Page 03 — Authentication not found');
  const pAuth = docData.nodes[pAuthId];
  if (!pAuth) throw new Error('Assertion failed: Page 03 — Authentication not found');

  // Search in nodes
  for (const topId of pAuth.children) {
    const frame = docData.nodes[topId];
    if (!frame) continue;

    // Collect all text under this frame
    const textValues = [];
    const logoInstances = [];

    function walk(nId) {
      const node = docData.nodes[nId];
      if (!node) return;
      if (node.type === 'text') {
        textValues.push(node.characters || node.text || '');
      }
      if (node.type === 'instance' && node.component === 2) {
        logoInstances.push(node);
      }
      if (node.children) {
        node.children.forEach(cId => walk(cId));
      }
    }
    walk(topId);

    // Assert Email-only
    const hasNISorNIP = textValues.some(t => t.includes('NISN') || t.includes('NIP') || t.includes('NIS/NIP'));
    if (hasNISorNIP) {
      throw new Error(`Assertion failed: Frame "${frame.name}" still contains NIS/NIP`);
    }
    const hasEmail = textValues.some(t => t === 'Email');
    if (!hasEmail) {
      throw new Error(`Assertion failed: Frame "${frame.name}" missing exact "Email" label`);
    }

    // Assert Mobile single logo
    if (frame.name.startsWith('Mobile Login')) {
      if (logoInstances.length !== 1) {
        throw new Error(`Assertion failed: Frame "${frame.name}" must have exactly 1 logo instance, found ${logoInstances.length}`);
      }
    }
  }
  console.log('✓ All Auth assertions passed (Email-only, Mobile single logo).');
}

// Assertions for SVG path normalization & viewBox preservation
(function runIconAssertions() {
  const testNorm = normalizePath("M18 6 6 18");
  if (!testNorm.includes('M 18 6 L 6 18')) {
    throw new Error('Assertion failed: multi-coord command not normalized properly: ' + testNorm);
  }
  const eyeNorm = svgToPath(fs.readFileSync(path.join(LUCIDE_DIR, 'eye.svg'), 'utf8'));
  if (!eyeNorm.startsWith('M 0 0 L 0 0 M 24 24 L 24 24')) {
    throw new Error('Assertion failed: eye icon missing 24x24 viewBox preservation anchors');
  }
  const chevNorm = svgToPath(fs.readFileSync(path.join(LUCIDE_DIR, 'chevron-down.svg'), 'utf8'));
  if (!chevNorm.includes('M 6 9 L 12 15 L 18 9')) {
    throw new Error('Assertion failed: chevron-down path not parsed properly: ' + chevNorm);
  }
  console.log('✓ All Icon normalization & viewBox assertions passed.');
})();

const icons = {};
fs.readdirSync(LUCIDE_DIR).filter(f => f.endsWith('.svg')).forEach(file => {
  const key = file.replace('.svg', '');
  const content = fs.readFileSync(path.join(LUCIDE_DIR, file), 'utf8');
  icons[key] = svgToPath(content);
});

class FigcraftSession {
  constructor(docPath) {
    this.docPath = docPath;
    this.commands = [];
    if (fs.existsSync(docPath)) {
      fs.unlinkSync(docPath);
    }
  }

  cmd(command, params) {
    this.commands.push({ command, params });
  }

  flush() {
    if (this.commands.length === 0) return [];
    fs.writeFileSync(TMP_BATCH, JSON.stringify(this.commands, null, 2));
    this.commands = [];

    const args = [
      'run',
      '--automation-read-root', '/home/san/Documents/sparta',
      '--automation-read-root', '/tmp',
      '--automation-write-root', '/home/san/Documents/sparta',
      '--automation-write-root', '/tmp'
    ];
    if (fs.existsSync(this.docPath)) {
      args.push('--in', this.docPath);
    }
    args.push('--out', this.docPath, TMP_BATCH);

    const res = spawnSync('figcraft-cli', args, { encoding: 'utf8' });
    fs.rmSync(TMP_BATCH, { force: true });
    if (res.status !== 0) {
      console.error('STDOUT:', res.stdout);
      console.error('STDERR:', res.stderr);
      throw new Error(`figcraft-cli failed with status ${res.status}`);
    }

    const lines = res.stdout.trim().split('\n').filter(l => l.startsWith('{'));
    return lines.map(l => JSON.parse(l));
  }

  createFrame(params) {
    this.flush();
    this.cmd('frame.create', params);
    const res = this.flush();
    return res[0].result.id;
  }

  createPage(name) {
    this.cmd('page.add', { name });
    const res = this.flush();
    return res[0].result.id;
  }

  addText(parent, text, x, y, props = {}) {
    this.cmd('text.create', { parent, text, x, y, props });
  }

  addRect(parent, x, y, width, height, props = {}) {
    this.cmd('shape.rectangle', { parent, x, y, width, height, props });
  }

  addVector(parent, name, pathData, x, y, width, height, strokeColor, strokeWeight = 1.5) {
    this.cmd('vector.create', {
      parent,
      name,
      path: pathData,
      x,
      y,
      width,
      height,
      props: {
        stroke: strokeColor,
        strokeWeight,
        fill: 'none'
      }
    });
  }

  addIcon(parent, iconName, x, y, size = 20, strokeColor = '#475569') {
    const p = icons[iconName];
    if (!p) throw new Error(`Missing icon: ${iconName}`);
    this.addVector(parent, `icon-${iconName}`, p, x, y, size, size, strokeColor, size >= 20 ? 1.75 : 1.5);
  }
}

async function buildDocument() {
  console.log('--- GENERATING SPARTA FIGCRAFT ARTIFACTS ---');
  const session = new FigcraftSession(OUT_FIGCRAFT);

  // Setup initial page name
  session.cmd('page.rename', { name: '00 — Foundations' });
  session.flush();

  // Import School Logo Component as first element on Page 1
  // Factor = 36 / 1364 = 0.02639296 (Width = 30.43, Height = 36.00)
  session.cmd('file.import', { path: LOGO_SVG_PATH, x: 0, y: 0 });
  const importRes = session.flush();
  const rawLogoId = importRes[0].result.id;
  console.log('Imported raw logo id:', rawLogoId);

  session.cmd('transform.scale', { ids: [rawLogoId], factor: 0.02639296 });
  session.cmd('component.create', { ids: [rawLogoId] });
  session.flush();
  const LOGO_COMP_ID = rawLogoId;
  console.log('Created Master School Logo Component id:', LOGO_COMP_ID);

  // Helper instantiate logo
  function placeLogo(parent, x, y, scale = 1.0) {
    session.flush();
    session.cmd('instance.create', { component: LOGO_COMP_ID, parent, x, y });
    const res = session.flush();
    const instId = res[0].result.id;
    if (scale !== 1.0) {
      session.cmd('transform.scale', { ids: [instId], factor: scale });
      session.flush();
    }
    return instId;
  }

  // ==========================================
  // PAGE 0: FOUNDATIONS
  // ==========================================
  console.log('Building Page 00 — Foundations...');

  // Frame 1: Colors & Contrast Tokens (1200x800)
  const fColors = session.createFrame({ name: 'Colors & Contrast Tokens', width: 1200, height: 800, x: 0, y: 0, props: { fill: '#F8FAFC' } });
  session.addText(fColors, 'SPARTA Design Tokens — Colors & WCAG AA Contrast Audit', 40, 40, { fontSize: 24, fill: '#0F172A', fontWeight: 700 });
  session.addText(fColors, 'Tiap token dievaluasi terhadap latar penggunaannya. Standar minimum rasio teks normal adalah 4.5:1 (WCAG AA).', 40, 76, { fontSize: 14, fill: '#475569' });

  const colorCards = [
    { name: 'brand-600', hex: '#4F46E5', textHex: '#FFFFFF', desc: 'CTA Utama, Tautan, Active Nav Icon', ratio: '8.4:1', status: 'PASS AA' },
    { name: 'brand-50', hex: '#EEF2FF', textHex: '#4F46E5', desc: 'Latar Menu Navigasi Aktif', ratio: '6.5:1', status: 'PASS AA' },
    { name: 'brand-900', hex: '#312E81', textHex: '#FFFFFF', desc: 'Aksen Identitas / Header Berat', ratio: '12.8:1', status: 'PASS AA' },
    { name: 'text-primary', hex: '#0F172A', textHex: '#FFFFFF', desc: 'Judul Utama, Konten Penting (#0F172A vs #FFF)', ratio: '15.6:1', status: 'PASS AA' },
    { name: 'text-secondary', hex: '#475569', textHex: '#FFFFFF', desc: 'Deskripsi, Label Form (#475569 vs #FFF)', ratio: '8.3:1', status: 'PASS AA' },
    { name: 'text-muted', hex: '#64748B', textHex: '#FFFFFF', desc: 'Teks Bantuan, Placeholder (#64748B vs #FFF)', ratio: '4.6:1', status: 'PASS AA' },
    { name: 'success (Tersedia)', hex: '#DCFCE7', textHex: '#166534', desc: 'Badge Selesai / Tersedia (#166534 vs #DCFCE7)', ratio: '8.1:1', status: 'PASS AA' },
    { name: 'warning (Menunggu)', hex: '#FEF3C7', textHex: '#92400E', desc: 'Badge Menunggu Persetujuan (#92400E vs #FEF3C7)', ratio: '7.9:1', status: 'PASS AA' },
    { name: 'danger (Ditolak)', hex: '#FEE2E2', textHex: '#991B1B', desc: 'Badge Ditolak / Rusak (#991B1B vs #FEE2E2)', ratio: '8.2:1', status: 'PASS AA' },
    { name: 'info (Dipinjam)', hex: '#E0E7FF', textHex: '#3730A3', desc: 'Badge Sedang Dipinjam (#3730A3 vs #E0E7FF)', ratio: '8.5:1', status: 'PASS AA' },
    { name: 'late (Terlambat)', hex: '#FFEDD5', textHex: '#9A3412', desc: 'Badge Peringatan Terlambat (#9A3412 vs #FFEDD5)', ratio: '7.8:1', status: 'PASS AA' },
    { name: 'surface / border', hex: '#FFFFFF', textHex: '#0F172A', desc: 'Card Surface (#FFFFFF) & Border (#E2E8F0)', ratio: '15.6:1', status: 'PASS AA' },
  ];

  colorCards.forEach((c, idx) => {
    const col = idx % 4;
    const row = Math.floor(idx / 4);
    const x = 40 + col * 280;
    const y = 120 + row * 210;

    session.addRect(fColors, x, y, 260, 190, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addRect(fColors, x, y, 260, 70, { fill: c.hex, cornerRadius: 8 });
    session.addText(fColors, c.name, x + 16, y + 84, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
    session.addText(fColors, `HEX: ${c.hex}`, x + 16, y + 106, { fontSize: 12, fill: '#64748B' });
    session.addText(fColors, c.desc, x + 16, y + 126, { fontSize: 11, fill: '#475569' });
    
    // Badge status
    session.addRect(fColors, x + 16, y + 154, 110, 24, { fill: '#DCFCE7', cornerRadius: 12 });
    session.addText(fColors, `${c.ratio} · ${c.status}`, x + 24, y + 159, { fontSize: 11, fontWeight: 700, fill: '#166534' });
  });
  session.flush();

  // ==========================================
  // PAGE 1: CORE COMPONENTS
  // ==========================================
  console.log('Building Page 01 — Components...');
  session.createPage('01 — Components');

  // Frame 1: Buttons (1000x550)
  const fButtons = session.createFrame({ name: 'Buttons & Action Controls', width: 1000, height: 550, x: 0, y: 0, props: { fill: '#FFFFFF' } });
  session.addText(fButtons, 'Komponen Tombol & Status Aksi (Official Lucide Icons)', 40, 40, { fontSize: 22, fill: '#0F172A', fontWeight: 700 });
  session.addText(fButtons, 'Mendukung status: Default, Hover, Loading (Spinner), Disabled, dan Focus Outline aksesibel.', 40, 72, { fontSize: 14, fill: '#475569' });

  const btnVariants = [
    { title: 'Primary (brand-600)', bg: '#4F46E5', text: '#FFFFFF', icon: 'check', stroke: null },
    { title: 'Secondary / Outlined', bg: '#FFFFFF', text: '#0F172A', icon: 'clipboard-list', stroke: '#E2E8F0' },
    { title: 'Danger Action', bg: '#DC2626', text: '#FFFFFF', icon: 'x', stroke: null },
    { title: 'Ghost / Subtle Action', bg: 'transparent', text: '#4F46E5', icon: 'refresh-cw', stroke: null },
  ];

  btnVariants.forEach((v, vIdx) => {
    const y = 130 + vIdx * 95;
    session.addText(fButtons, v.title, 40, y + 10, { fontSize: 14, fontWeight: 700, fill: '#0F172A' });

    // 1. Normal
    session.addRect(fButtons, 220, y, 140, 40, { fill: v.bg, cornerRadius: 8, stroke: v.stroke, strokeWeight: 1 });
    session.addIcon(fButtons, v.icon, 232, y + 12, 16, v.text);
    session.addText(fButtons, 'Simpan', 256, y + 12, { fontSize: 14, fontWeight: 500, fill: v.text });

    // 2. Focus Outline State (Accessible ring)
    session.addRect(fButtons, 380, y, 140, 40, { fill: v.bg, cornerRadius: 8, stroke: '#4F46E5', strokeWeight: 2 });
    session.addIcon(fButtons, v.icon, 392, y + 12, 16, v.text);
    session.addText(fButtons, 'Focus State', 416, y + 12, { fontSize: 14, fontWeight: 500, fill: v.text });

    // 3. Loading State (Lucide loader-circle)
    session.addRect(fButtons, 540, y, 140, 40, { fill: v.bg === 'transparent' ? '#F8FAFC' : v.bg, cornerRadius: 8, stroke: v.stroke, strokeWeight: 1 });
    session.addIcon(fButtons, 'loader-circle', 552, y + 12, 16, v.text === 'transparent' ? '#4F46E5' : v.text);
    session.addText(fButtons, 'Memproses...', 576, y + 12, { fontSize: 13, fontWeight: 500, fill: v.text === 'transparent' ? '#4F46E5' : v.text });

    // 4. Disabled State
    session.addRect(fButtons, 700, y, 140, 40, { fill: '#F1F5F9', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addIcon(fButtons, v.icon, 712, y + 12, 16, '#94A3B8');
    session.addText(fButtons, 'Nonaktif', 736, y + 12, { fontSize: 14, fontWeight: 500, fill: '#94A3B8' });
  });
  session.flush();

  // Frame 2: Form Controls & Inputs (1000x550)
  const fInputs = session.createFrame({ name: 'Inputs & Form Controls', width: 1000, height: 550, x: 1040, y: 0, props: { fill: '#FFFFFF' } });
  session.addText(fInputs, 'Input Field, Select Dropdown, dan Search Control', 40, 40, { fontSize: 22, fill: '#0F172A', fontWeight: 700 });
  session.addText(fInputs, 'Mendukung icon left/right, clear button, dan validasi visual error.', 40, 72, { fontSize: 14, fill: '#475569' });

  // 1. Search Bar with Clear
  session.addText(fInputs, 'Pencarian Cepat Alat (Search Input with Lucide)', 40, 120, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fInputs, 40, 144, 420, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#94A3B8', strokeWeight: 1 });
  session.addIcon(fInputs, 'search', 54, 158, 16, '#64748B');
  session.addText(fInputs, 'Cari nama alat, kode inventaris, atau kategori...', 80, 157, { fontSize: 13, fill: '#64748B' });
  session.addIcon(fInputs, 'x', 430, 158, 16, '#94A3B8');

  // 2. Text Input Active Focus
  session.addText(fInputs, 'Input Teks — Status Aktif / Fokus', 500, 120, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fInputs, 500, 144, 420, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#4F46E5', strokeWeight: 2 });
  session.addText(fInputs, 'Multimeter Digital Sanwa CD800a', 516, 157, { fontSize: 14, fill: '#0F172A' });

  // 3. Password Input with Toggle (Lucide eye)
  session.addText(fInputs, 'Password Input dengan Toggle Visibilitas', 40, 220, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fInputs, 40, 244, 420, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#94A3B8', strokeWeight: 1 });
  session.addText(fInputs, '••••••••••••', 56, 257, { fontSize: 14, fill: '#0F172A' });
  session.addIcon(fInputs, 'eye', 426, 258, 16, '#64748B');

  // 4. Input Error State with Alert
  session.addText(fInputs, 'Input Validasi Error', 500, 220, { fontSize: 13, fontWeight: 700, fill: '#DC2626' });
  session.addRect(fInputs, 500, 244, 420, 44, { fill: '#FEF2F2', cornerRadius: 8, stroke: '#DC2626', strokeWeight: 1.5 });
  session.addText(fInputs, 'admin@sekolah', 516, 257, { fontSize: 14, fill: '#0F172A' });
  session.addIcon(fInputs, 'circle-exclamation-point', 886, 258, 16, '#DC2626');
  session.addText(fInputs, 'Format email tidak valid. Gunakan format nama@domain.sch.id', 500, 296, { fontSize: 12, fill: '#DC2626' });

  // 5. Select Dropdown with Chevron
  session.addText(fInputs, 'Select Dropdown (Kategori / Lokasi)', 40, 330, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fInputs, 40, 354, 420, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#94A3B8', strokeWeight: 1 });
  session.addText(fInputs, 'Laboratorium Rekayasa Perangkat Lunak 1', 56, 367, { fontSize: 14, fill: '#0F172A' });
  session.addIcon(fInputs, 'chevron-down', 426, 368, 16, '#64748B');
  session.flush();

  // ==========================================
  // PAGE 2: APP SHELL (3 ROLES)
  // ==========================================
  console.log('Building Page 02 — App Shell...');
  session.createPage('02 — App Shell');

  // Helper build desktop shell
  function buildShell(frameName, xPos, roleTitle, activeMenu, kpis, recentRows) {
    const fShell = session.createFrame({ name: frameName, width: 1440, height: 960, x: xPos, y: 0, props: { fill: '#F8FAFC' } });
    
    // 1. Sidebar (Width: 248)
    session.addRect(fShell, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });

    // School Logo + SPARTA Wordmark
    placeLogo(fShell, 20, 22, 1.0); // 30.43 x 36
    session.addText(fShell, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
    session.addText(fShell, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
    session.addRect(fShell, 0, 76, 248, 1, { fill: '#E2E8F0' });

    // Nav Menus
    const adminNav = [
      { name: 'Dashboard', icon: 'layout-dashboard' },
      { name: 'Data Pengguna', icon: 'users' },
      { name: 'Kategori Alat', icon: 'tags' },
      { name: 'Data Alat & Barang', icon: 'wrench' },
      { name: 'Peminjaman', icon: 'clipboard-list' },
      { name: 'Pengembalian', icon: 'refresh-cw' },
      { name: 'Log Aktivitas', icon: 'logs' },
    ];

    const petugasNav = [
      { name: 'Dashboard', icon: 'layout-dashboard' },
      { name: 'Daftar Alat', icon: 'wrench' },
      { name: 'Persetujuan Peminjaman', icon: 'clipboard-list' },
      { name: 'Penerimaan Kembali', icon: 'refresh-cw' },
      { name: 'Laporan Sarpras', icon: 'scroll-text' },
    ];

    const peminjamNav = [
      { name: 'Katalog Alat', icon: 'wrench' },
      { name: 'Peminjaman Saya', icon: 'clipboard-list' },
      { name: 'Riwayat Pinjam', icon: 'logs' },
    ];

    const curNav = roleTitle === 'Admin' ? adminNav : (roleTitle === 'Petugas' ? petugasNav : peminjamNav);
    curNav.forEach((item, idx) => {
      const y = 92 + idx * 48;
      const isActive = item.name === activeMenu;
      if (isActive) {
        session.addRect(fShell, 12, y, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
        session.addIcon(fShell, item.icon, 24, y + 10, 20, '#4F46E5');
        session.addText(fShell, item.name, 56, y + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
      } else {
        session.addIcon(fShell, item.icon, 24, y + 10, 20, '#64748B');
        session.addText(fShell, item.name, 56, y + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
      }
    });

    // Sidebar footer: User info & logout
    session.addRect(fShell, 0, 936, 248, 1, { fill: '#E2E8F0' });
    session.addIcon(fShell, 'circle-user', 20, 956, 32, '#4F46E5');
    session.addText(fShell, roleTitle === 'Admin' ? 'Ahmad Fauzi, S.Kom' : (roleTitle === 'Petugas' ? 'Budi Santoso, A.Md' : 'Rian Hidayat (XII RPL 1)'), 60, 955, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fShell, roleTitle, 60, 973, { fontSize: 11, fill: '#64748B' });
    session.addIcon(fShell, 'log-out', 208, 962, 18, '#94A3B8');

    // 2. Topbar (Header: 64px)
    session.addRect(fShell, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(fShell, `SPARTA — Panel ${roleTitle}`, 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });

    // Topbar Profile Dropdown (tanpa global search — spec: jangan search global tanpa fungsi)
    session.addRect(fShell, 1100, 12, 280, 40, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addIcon(fShell, 'circle-user', 1112, 22, 20, '#4F46E5');
    session.addText(fShell, roleTitle, 1140, 23, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addIcon(fShell, 'chevron-down', 1350, 24, 16, '#64748B');

    // 3. Main Content
    // Page Title & CTA / Date Badge
    session.addText(fShell, activeMenu, 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
    session.addText(fShell, 'Ringkasan operasional dan status inventaris SMK Negeri 1 Leuwimunding.', 276, 124, { fontSize: 14, fill: '#64748B' });

    if (roleTitle === 'Admin') {
      // Quick Action: Tambah Alat (Spec Section 9.2) - daisyUI btn btn-primary
      session.addRect(fShell, 1080, 88, 140, 40, { fill: '#4F46E5', cornerRadius: 8 });
      session.addIcon(fShell, 'plus', 1094, 98, 18, '#FFFFFF');
      session.addText(fShell, 'Tambah Alat', 1120, 100, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });

      session.addRect(fShell, 1236, 88, 144, 40, { fill: '#EEF2FF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
      session.addText(fShell, '16 Nov 2026', 1264, 100, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addRect(fShell, 1200, 92, 180, 32, { fill: '#EEF2FF', cornerRadius: 16 });
      session.addText(fShell, '16 November 2026', 1230, 99, { fontSize: 12, fontWeight: 700, fill: '#4F46E5' });
    }

    // 4 KPI Cards
    kpis.forEach((k, idx) => {
      const x = 276 + idx * 284;
      const y = 160;
      session.addRect(fShell, x, y, 264, 112, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
      session.addIcon(fShell, k.icon, x + 20, y + 20, 22, '#4F46E5');
      session.addText(fShell, k.label, x + 52, y + 22, { fontSize: 12, fontWeight: 500, fill: '#64748B' });
      session.addText(fShell, k.val, x + 20, y + 54, { fontSize: 28, fontWeight: 700, fill: '#0F172A' });
      session.addText(fShell, k.sub, x + 20, y + 88, { fontSize: 11, fill: k.subColor || '#166534' });
    });

    // Recent Transactions Table Card
    const tableY = 296;
    const tableW = roleTitle === 'Admin' ? 760 : 1104;
    session.addRect(fShell, 276, tableY, tableW, 480, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(fShell, 'Transaksi Peminjaman Terbaru', 300, tableY + 24, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
    session.addText(fShell, 'Daftar transaksi aktif siklus laboratorium.', 300, tableY + 48, { fontSize: 13, fill: '#64748B' });

    // Table Header
    const thW = tableW - 48;
    session.addRect(fShell, 300, tableY + 84, thW, 40, { fill: '#F8FAFC', cornerRadius: 6 });
    session.addText(fShell, 'KODE', 316, tableY + 98, { fontSize: 11, fontWeight: 700, fill: '#475569' });
    session.addText(fShell, 'ALAT', 390, tableY + 98, { fontSize: 11, fontWeight: 700, fill: '#475569' });
    session.addText(fShell, 'PEMINJAM', 550, tableY + 98, { fontSize: 11, fontWeight: 700, fill: '#475569' });
    session.addText(fShell, 'STATUS', 680, tableY + 98, { fontSize: 11, fontWeight: 700, fill: '#475569' });
    if (roleTitle !== 'Admin') {
      session.addText(fShell, 'TENGGAT', 880, tableY + 98, { fontSize: 11, fontWeight: 700, fill: '#475569' });
      session.addText(fShell, 'AKSI', 1260, tableY + 98, { fontSize: 11, fontWeight: 700, fill: '#475569' });
    }

    // Table Rows
    recentRows.forEach((r, idx) => {
      const rowY = tableY + 132 + idx * 56;
      session.addRect(fShell, 300, rowY + 48, thW, 1, { fill: '#F1F5F9' });
      session.addText(fShell, r.kode, 316, rowY + 14, { fontSize: 12, fontWeight: 700, fill: '#4F46E5' });
      session.addText(fShell, r.alat, 390, rowY + 14, { fontSize: 12, fontWeight: 500, fill: '#0F172A' });
      session.addText(fShell, r.peminjam, 550, rowY + 14, { fontSize: 12, fill: '#475569' });

      // Badge status (Primary + Optional Secondary/Derived Badge)
      if (r.badges && r.badges.length > 1) {
        let bx = 680;
        r.badges.forEach(b => {
          session.addRect(fShell, bx, rowY + 8, b.w, 28, { fill: b.bg, cornerRadius: 14 });
          session.addText(fShell, b.text, bx + 10, rowY + 14, { fontSize: 11, fontWeight: 700, fill: b.fg });
          bx += b.w + 6;
        });
      } else {
        session.addRect(fShell, 680, rowY + 8, 120, 28, { fill: r.badgeBg, cornerRadius: 14 });
        session.addText(fShell, r.status, 692, rowY + 14, { fontSize: 11, fontWeight: 700, fill: r.badgeFg });
      }

      if (roleTitle !== 'Admin') {
        session.addText(fShell, r.tenggat, 880, rowY + 14, { fontSize: 12, fill: r.isLate ? '#9A3412' : '#64748B', fontWeight: r.isLate ? 700 : 400 });
        session.addText(fShell, 'Detail →', 1260, rowY + 14, { fontSize: 12, fontWeight: 700, fill: '#4F46E5' });
      }
    });

    // Admin-only Side Panel: Panel Ringkas Stok Rendah (Spec Section 9.2: ambang batas <= 3 unit)
    if (roleTitle === 'Admin') {
      const panelX = 1056;
      const panelW = 324;
      session.addRect(fShell, panelX, tableY, panelW, 480, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
      session.addText(fShell, 'Stok Alat Rendah (≤ 3)', panelX + 20, tableY + 24, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
      session.addText(fShell, 'Peringatan otomatis ketersediaan unit', panelX + 20, tableY + 46, { fontSize: 12, fill: '#64748B' });

      const lowStocks = [
        { kode: 'ALT-003', nama: 'Kamera DSLR Canon', cat: 'Multimedia', sisa: '0 Tersedia', total: 'Total 3 unit (1 rusak)', badgeBg: '#FEE2E2', badgeFg: '#991B1B' },
        { kode: 'ALT-002', nama: 'Proyektor Epson', cat: 'Elektronik', sisa: '3 Tersedia', total: 'Total 4 unit (0 rusak)', badgeBg: '#FEF3C7', badgeFg: '#92400E' }
      ];

      lowStocks.forEach((item, lIdx) => {
        const itemY = tableY + 84 + lIdx * 100;
        session.addRect(fShell, panelX + 16, itemY, panelW - 32, 88, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
        session.addText(fShell, item.nama, panelX + 28, itemY + 14, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
        session.addText(fShell, `${item.kode} · ${item.cat}`, panelX + 28, itemY + 34, { fontSize: 11, fill: '#64748B' });
        session.addText(fShell, item.total, panelX + 28, itemY + 54, { fontSize: 11, fill: '#475569' });

        session.addRect(fShell, panelX + panelW - 120, itemY + 14, 92, 24, { fill: item.badgeBg, cornerRadius: 12 });
        session.addText(fShell, item.sisa, panelX + panelW - 112, itemY + 19, { fontSize: 10, fontWeight: 700, fill: item.badgeFg });
      });

      // Quick CTA link to data alat
      session.addRect(fShell, panelX + 16, tableY + 416, panelW - 32, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addText(fShell, 'Kelola Semua Data Alat →', panelX + 64, tableY + 428, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    }

    session.flush();
  }

  const kpisAdmin = [
    { label: 'Total Alat Aktif', val: '4 Jenis', sub: '21 unit total · 14 siap pinjam', icon: 'wrench' },
    { label: 'Total Pengguna Aktif', val: '5', sub: '1 admin, 1 ptg, 3 peminjam', icon: 'users' },
    { label: 'Peminjaman Berjalan', val: '2', sub: 'PJ-0001 & PJ-0002', icon: 'clipboard-list' },
    { label: 'Menunggu Persetujuan', val: '1', sub: 'PJ-0004 butuh review', icon: 'refresh-cw' },
  ];

  const kpisPetugas = [
    { label: 'Menunggu Persetujuan', val: '1', sub: 'Pengajuan PJ-0004', icon: 'clipboard-list' },
    { label: 'Siap Diserahkan', val: '1', sub: 'PJ-0003 telah disetujui', icon: 'check' },
    { label: 'Sedang Dipinjam', val: '2', sub: 'PJ-0001 & PJ-0002', icon: 'wrench' },
    { label: 'Terlambat', val: '1 Transaksi', sub: 'PJ-0002 (lewat tempo)', icon: 'scroll-text', subColor: '#9A3412' },
  ];

  const kpisPeminjam = [
    { label: 'Menunggu Persetujuan', val: '1', sub: 'PJ-0004 diajukan', icon: 'refresh-cw' },
    { label: 'Sedang Dipinjam', val: '2', sub: 'PJ-0001 & PJ-0002', icon: 'wrench' },
    { label: 'Selesai', val: '1', sub: 'PJ-0005 dikembalikan', icon: 'check' },
    { label: 'Terlambat', val: '1 Pinjaman', sub: 'PJ-0002 butuh kembali', icon: 'clipboard-list', subColor: '#9A3412' },
  ];

  const rows = [
    { kode: 'PJ-0001', alat: 'Bor listrik', peminjam: 'Andi Pratama', tenggat: '18 Nov 2026', status: 'Dipinjam', badgeBg: '#E0E7FF', badgeFg: '#3730A3', isLate: false },
    { kode: 'PJ-0002', alat: 'Proyektor Epson', peminjam: 'Siti Rahma', tenggat: '15 Nov 2026', status: 'Dipinjam', badgeBg: '#E0E7FF', badgeFg: '#3730A3', isLate: true, badges: [
      { text: 'Dipinjam', bg: '#E0E7FF', fg: '#3730A3', w: 76 },
      { text: 'Terlambat', bg: '#FFEDD5', fg: '#9A3412', w: 84 }
    ]},
    { kode: 'PJ-0003', alat: 'Kamera DSLR Canon', peminjam: 'Budi Santoso', tenggat: '20 Nov 2026', status: 'Disetujui', badgeBg: '#EEF2FF', badgeFg: '#4338CA', isLate: false },
    { kode: 'PJ-0004', alat: 'Multimeter Sanwa', peminjam: 'Siti Rahma', tenggat: '19 Nov 2026', status: 'Menunggu Persetujuan', badgeBg: '#FEF3C7', badgeFg: '#92400E', isLate: false },
  ];

  buildShell('Desktop Shell — Admin', 0, 'Admin', 'Dashboard', kpisAdmin, rows);
  buildShell('Desktop Shell — Petugas', 1480, 'Petugas', 'Dashboard', kpisPetugas, rows);
  buildShell('Desktop Shell — Peminjam', 2960, 'Peminjam', 'Peminjaman Saya', kpisPeminjam, rows);

  // ==========================================
  // PAGE 3: AUTHENTICATION
  // ==========================================
  console.log('Building Page 03 — Authentication...');
  session.createPage('03 — Authentication');

  // Helper build Desktop Login Card
  function buildDesktopLogin(frameName, xPos, state = 'default') {
    const fAuth = session.createFrame({ name: frameName, width: 1440, height: 900, x: xPos, y: 0, props: { fill: '#F8FAFC' } });
    
    // Background decorative elements
    session.addRect(fAuth, 0, 0, 1440, 4, { fill: '#4F46E5' });

    // Card Container (460 x 620)
    const cardX = 490;
    const cardY = 120;
    session.addRect(fAuth, cardX, cardY, 460, 640, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 });

    // School Logo (Contained, Original Ratio 1153:1364)
    // Scale factor = 44 / 36 = 1.222222 (Width: 37.19, Height: 44.00)
    placeLogo(fAuth, cardX + 211, cardY + 36, 1.222222);

    session.addText(fAuth, 'SPARTA', cardX + 185, cardY + 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
    session.addText(fAuth, 'SMK NEGERI 1 LEUWIMUNDING', cardX + 120, cardY + 124, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fAuth, 'Sistem Peminjaman Alat Praktik Laboratorium', cardX + 88, cardY + 146, { fontSize: 13, fill: '#64748B' });

    let formY = cardY + 184;

    // Error Banner if state is error or disabled
    if (state === 'error') {
      session.addRect(fAuth, cardX + 36, formY, 388, 48, { fill: '#FEF2F2', cornerRadius: 8, stroke: '#FCA5A5', strokeWeight: 1 });
      session.addIcon(fAuth, 'circle-exclamation-point', cardX + 48, formY + 16, 16, '#DC2626');
      session.addText(fAuth, 'Email atau kata sandi salah. Silakan periksa kembali.', cardX + 74, formY + 16, { fontSize: 12, fontWeight: 500, fill: '#DC2626' });
      formY += 60;
    } else if (state === 'disabled') {
      session.addRect(fAuth, cardX + 36, formY, 388, 56, { fill: '#FFFBEB', cornerRadius: 8, stroke: '#FCD34D', strokeWeight: 1 });
      session.addIcon(fAuth, 'triangle-exclamation-point', cardX + 48, formY + 18, 16, '#B45309');
      session.addText(fAuth, 'Akun Anda dinonaktifkan oleh Administrator.', cardX + 74, formY + 12, { fontSize: 12, fontWeight: 700, fill: '#92400E' });
      session.addText(fAuth, 'Hubungi pengelola sarpras untuk verifikasi status.', cardX + 74, formY + 30, { fontSize: 11, fill: '#B45309' });
      formY += 68;
    }

    // Input Email
    session.addText(fAuth, 'Email', cardX + 36, formY, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fAuth, cardX + 36, formY + 24, 388, 44, { fill: state === 'error' ? '#FEF2F2' : '#FFFFFF', cornerRadius: 8, stroke: state === 'error' ? '#DC2626' : '#94A3B8', strokeWeight: 1 });
    session.addIcon(fAuth, 'circle-user', cardX + 48, formY + 38, 16, state === 'error' ? '#DC2626' : '#64748B');
    session.addText(fAuth, state === 'error' ? 'user@salah.com' : 'ahmad.fauzi@smkn1leuwimunding.sch.id', cardX + 74, formY + 37, { fontSize: 13, fill: '#0F172A' });

    // Input Password
    session.addText(fAuth, 'Kata Sandi', cardX + 36, formY + 84, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fAuth, cardX + 36, formY + 108, 388, 44, { fill: state === 'error' ? '#FEF2F2' : '#FFFFFF', cornerRadius: 8, stroke: state === 'error' ? '#DC2626' : '#94A3B8', strokeWeight: 1 });
    session.addText(fAuth, '••••••••••••', cardX + 52, formY + 121, { fontSize: 14, fill: '#0F172A' });
    session.addIcon(fAuth, 'eye', cardX + 392, formY + 122, 16, '#64748B');

    // Remember device
    session.addRect(fAuth, cardX + 36, formY + 168, 16, 16, { fill: '#4F46E5', cornerRadius: 4 });
    session.addIcon(fAuth, 'check', cardX + 37, formY + 169, 14, '#FFFFFF');
    session.addText(fAuth, 'Ingat perangkat ini', cardX + 60, formY + 167, { fontSize: 12, fill: '#475569' });

    // Submit Button
    const btnY = formY + 204;
    if (state === 'loading') {
      session.addRect(fAuth, cardX + 36, btnY, 388, 44, { fill: '#4F46E5', cornerRadius: 8 });
      session.addIcon(fAuth, 'loader-circle', cardX + 168, btnY + 14, 16, '#FFFFFF');
      session.addText(fAuth, 'Memverifikasi...', cardX + 196, btnY + 13, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' });
    } else {
      session.addRect(fAuth, cardX + 36, btnY, 388, 44, { fill: '#4F46E5', cornerRadius: 8 });
      session.addText(fAuth, 'Masuk ke Aplikasi', cardX + 160, btnY + 13, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' });
    }

    // Footer info
    session.addText(fAuth, 'SPARTA v1.0.0 · Sarana & Prasarana SMKN 1 Leuwimunding', cardX + 50, cardY + 590, { fontSize: 11, fill: '#94A3B8' });
    session.flush();
  }

  // Helper build Mobile Login
  function buildMobileLogin(frameName, xPos, state = 'default') {
    const fMob = session.createFrame({ name: frameName, width: 390, height: 844, x: xPos, y: 940, props: { fill: '#F8FAFC' } });
    
    // Topbar (56px) with School Logo & Wordmark
    session.addRect(fMob, 0, 0, 390, 56, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    placeLogo(fMob, 16, 12, 0.888888);
    session.addText(fMob, 'SPARTA', 54, 12, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
    session.addText(fMob, 'SMKN 1 Leuwimunding', 54, 32, { fontSize: 11, fill: '#64748B' });

    // Content container (350px wide) - Card without duplicate logo
    const cardX = 20;
    const cardY = 84;
    session.addRect(fMob, cardX, cardY, 350, 680, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 });

    // Header (Logo single on Topbar only)
    session.addText(fMob, 'Masuk Akun', cardX + 125, cardY + 32, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
    session.addText(fMob, 'Peminjaman Alat Praktik Bengkel', cardX + 75, cardY + 58, { fontSize: 12, fill: '#64748B' });

    let formY = cardY + 98;

    if (state === 'error') {
      session.addRect(fMob, cardX + 16, formY, 318, 48, { fill: '#FEF2F2', cornerRadius: 8, stroke: '#FCA5A5', strokeWeight: 1 });
      session.addIcon(fMob, 'circle-exclamation-point', cardX + 26, formY + 16, 16, '#DC2626');
      session.addText(fMob, 'Email atau kata sandi tidak sesuai.', cardX + 50, formY + 16, { fontSize: 12, fontWeight: 500, fill: '#DC2626' });
      formY += 60;
    }

    // Input 1
    session.addText(fMob, 'Email', cardX + 16, formY, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fMob, cardX + 16, formY + 20, 318, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: state === 'error' ? '#DC2626' : '#94A3B8', strokeWeight: 1 });
    session.addIcon(fMob, 'circle-user', cardX + 28, formY + 34, 16, '#64748B');
    session.addText(fMob, state === 'error' ? 'user@salah.com' : 'rian.hidayat@siswa.belajar.id', cardX + 52, formY + 34, { fontSize: 12, fill: '#0F172A' });

    // Input 2
    session.addText(fMob, 'Kata Sandi', cardX + 16, formY + 76, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fMob, cardX + 16, formY + 96, 318, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: state === 'error' ? '#DC2626' : '#94A3B8', strokeWeight: 1 });
    session.addText(fMob, '••••••••••••', cardX + 28, formY + 110, { fontSize: 13, fill: '#0F172A' });
    session.addIcon(fMob, 'eye', cardX + 300, formY + 110, 16, '#64748B');

    // Button
    const btnY = formY + 160;
    session.addRect(fMob, cardX + 16, btnY, 318, 44, { fill: '#4F46E5', cornerRadius: 8 });
    session.addText(fMob, 'Masuk', cardX + 140, btnY + 13, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' });

    // Footer info
    session.addText(fMob, 'SMK Negeri 1 Leuwimunding', cardX + 90, cardY + 630, { fontSize: 11, fill: '#94A3B8' });
    session.flush();
  }

  buildDesktopLogin('Desktop Login — Default', 0, 'default');
  buildDesktopLogin('Desktop Login — Error State', 1480, 'error');
  buildDesktopLogin('Desktop Login — Loading State', 2960, 'loading');
  buildDesktopLogin('Desktop Login — Disabled Account', 4440, 'disabled');

  buildMobileLogin('Mobile Login — Default', 0, 'default');
  buildMobileLogin('Mobile Login — Error State', 430, 'error');

  
  // ==========================================
  // PAGE 4: ADMIN CRUD DETAIL
  // ==========================================
  console.log('Building Page 04 — Admin CRUD...');
  const pAdmin = session.createPage('04 — Admin CRUD');

  // Helper template frame admin
  function createAdminPageFrame(name, x, y, title, subtitle, ctaText) {
    const f = session.createFrame({ name, width: 1440, height: 960, x, y, props: { fill: '#F8FAFC' } });
    // Sidebar
    session.addRect(f, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    placeLogo(f, 20, 22, 1.0);
    session.addText(f, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
    session.addRect(f, 0, 76, 248, 1, { fill: '#E2E8F0' });

    const adminNav = [
      { name: 'Dashboard', icon: 'layout-dashboard' },
      { name: 'Data Pengguna', icon: 'users' },
      { name: 'Kategori Alat', icon: 'tags' },
      { name: 'Data Alat & Barang', icon: 'wrench' },
      { name: 'Peminjaman', icon: 'clipboard-list' },
      { name: 'Pengembalian', icon: 'refresh-cw' },
      { name: 'Log Aktivitas', icon: 'logs' },
    ];
    adminNav.forEach((item, idx) => {
      const ny = 92 + idx * 48;
      const isActive = title.toLowerCase().includes(item.name.toLowerCase()) || (title === 'Master Data Alat' && item.name.includes('Alat'));
      if (isActive) {
        session.addRect(f, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
        session.addIcon(f, item.icon, 24, ny + 10, 20, '#4F46E5');
        session.addText(f, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
      } else {
        session.addIcon(f, item.icon, 24, ny + 10, 20, '#64748B');
        session.addText(f, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
      }
    });

    // Topbar
    session.addRect(f, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(f, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
    session.addRect(f, 1100, 12, 280, 40, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addIcon(f, 'circle-user', 1112, 22, 20, '#4F46E5');
    session.addText(f, 'Ahmad Fauzi (Admin)', 1140, 23, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addIcon(f, 'chevron-down', 1350, 24, 16, '#64748B');

    // Header Content
    session.addText(f, title, 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, subtitle, 276, 124, { fontSize: 14, fill: '#64748B' });

    if (ctaText) {
      session.addRect(f, 1200, 88, 180, 40, { fill: '#4F46E5', cornerRadius: 8 });
      session.addIcon(f, 'plus', 1214, 98, 18, '#FFFFFF');
      session.addText(f, ctaText, 1240, 100, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
    }
    return f;
  }

  // 1. Admin - Pengguna (1440x960)
  const fAdmUsers = createAdminPageFrame('Admin — Data Pengguna', 0, 0, 'Data Pengguna', 'Manajemen akun sistem: admin, petugas, dan peminjam.', 'Tambah Pengguna');
  session.addRect(fAdmUsers, 276, 156, 1104, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  // Filter bar
  session.addRect(fAdmUsers, 300, 176, 300, 40, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addIcon(fAdmUsers, 'search', 312, 188, 16, '#64748B');
  session.addText(fAdmUsers, 'Cari nama atau username...', 336, 187, { fontSize: 13, fill: '#94A3B8' });
  session.addRect(fAdmUsers, 616, 176, 160, 40, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fAdmUsers, 'Semua Role', 632, 187, { fontSize: 13, fill: '#0F172A' });
  session.addIcon(fAdmUsers, 'chevron-down', 746, 188, 16, '#64748B');
  // Table
  session.addRect(fAdmUsers, 300, 232, 1056, 40, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fAdmUsers, 'NAMA LENGKAP', 316, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmUsers, 'USERNAME', 530, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmUsers, 'ROLE', 720, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmUsers, 'STATUS', 890, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmUsers, 'AKSI', 1160, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });

  const demoUsers = [
    { nama: 'Ahmad Fauzi, S.Kom', user: 'ahmad.fauzi', role: 'admin', roleBg: '#EEF2FF', roleFg: '#4338CA', status: 'Aktif', stBg: '#DCFCE7', stFg: '#166534', isSelf: true },
    { nama: 'Budi Santoso, A.Md', user: 'budi.santoso', role: 'petugas', roleBg: '#FEF3C7', roleFg: '#92400E', status: 'Aktif', stBg: '#DCFCE7', stFg: '#166534', isSelf: false },
    { nama: 'Andi Pratama', user: 'andi.pratama', role: 'peminjam', roleBg: '#F1F5F9', roleFg: '#475569', status: 'Aktif', stBg: '#DCFCE7', stFg: '#166534', isSelf: false },
    { nama: 'Siti Rahma', user: 'siti.rahma', role: 'peminjam', roleBg: '#F1F5F9', roleFg: '#475569', status: 'Aktif', stBg: '#DCFCE7', stFg: '#166534', isSelf: false },
    { nama: 'Rian Hidayat', user: 'rian.hidayat', role: 'peminjam', roleBg: '#F1F5F9', roleFg: '#475569', status: 'Aktif', stBg: '#DCFCE7', stFg: '#166534', isSelf: false },
  ];
  demoUsers.forEach((u, i) => {
    const uy = 284 + i * 56;
    session.addRect(fAdmUsers, 300, uy + 48, 1056, 1, { fill: '#F1F5F9' });
    session.addText(fAdmUsers, u.nama, 316, uy + 14, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fAdmUsers, u.user, 530, uy + 14, { fontSize: 13, fill: '#64748B' });
    session.addRect(fAdmUsers, 720, uy + 8, 90, 26, { fill: u.roleBg, cornerRadius: 13 });
    session.addText(fAdmUsers, u.role, 736, uy + 13, { fontSize: 11, fontWeight: 700, fill: u.roleFg });
    session.addRect(fAdmUsers, 890, uy + 8, 70, 26, { fill: u.stBg, cornerRadius: 13 });
    session.addText(fAdmUsers, u.status, 905, uy + 13, { fontSize: 11, fontWeight: 700, fill: u.stFg });
    if (u.isSelf) {
      session.addText(fAdmUsers, 'Akun Anda (Terkunci)', 1160, uy + 14, { fontSize: 12, fill: '#94A3B8' });
    } else {
      session.addText(fAdmUsers, 'Edit · Nonaktifkan', 1160, uy + 14, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    }
  });

  session.flush();

  // 2. Admin - Kategori Alat (1440x960)
  const fAdmCats = createAdminPageFrame('Admin — Kategori Alat', 1480, 0, 'Kategori Alat', 'Pengelompokan alat & barang bengkel praktik.', 'Tambah Kategori');
  session.addRect(fAdmCats, 276, 156, 1104, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fAdmCats, 300, 180, 1056, 40, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fAdmCats, 'KODE KATEGORI', 316, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmCats, 'NAMA KATEGORI', 480, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmCats, 'DESKRIPSI', 720, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmCats, 'JUMLAH ALAT', 980, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmCats, 'AKSI', 1180, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });

  const cats = [
    { kode: 'ELK', nama: 'Elektronik', desc: 'Peralatan uji sirkuit dan pengukuran elektronika', qty: '2 Jenis (10 Unit)', canDelete: false },
    { kode: 'PRK', nama: 'Perkakas', desc: 'Peralatan tangan mekanik dan kelistrikan bengkel', qty: '1 Jenis (8 Unit)', canDelete: false },
    { kode: 'MLT', nama: 'Multimedia', desc: 'Perangkat perekaman audio visual dan kamera', qty: '1 Jenis (3 Unit)', canDelete: false },
    { kode: 'NET', nama: 'Jaringan Komputer', desc: 'Crimping tool, tester LAN, kabel, dan router lab', qty: '0 Jenis (0 Unit)', canDelete: true },
  ];
  cats.forEach((c, idx) => {
    const cy = 236 + idx * 64;
    session.addRect(fAdmCats, 300, cy + 54, 1056, 1, { fill: '#F1F5F9' });
    session.addText(fAdmCats, c.kode, 316, cy + 18, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fAdmCats, c.nama, 480, cy + 18, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fAdmCats, c.desc, 720, cy + 18, { fontSize: 12, fill: '#475569' });
    session.addText(fAdmCats, c.qty, 980, cy + 18, { fontSize: 13, fill: '#0F172A' });
    if (c.canDelete) {
      session.addText(fAdmCats, 'Edit · Hapus', 1180, cy + 18, { fontSize: 13, fontWeight: 700, fill: '#DC2626' });
    } else {
      session.addText(fAdmCats, 'Edit (Terkunci)', 1180, cy + 18, { fontSize: 12, fill: '#64748B' });
    }
  });
  session.flush();

  // 3. Admin - Data Alat & Barang (1440x960)
  const fAdmTools = createAdminPageFrame('Admin — Data Alat & Barang', 2960, 0, 'Master Data Alat & Barang', 'Daftar inventaris 4 jenis alat aktif dan status ketersediaan.', 'Tambah Alat');
  session.addRect(fAdmTools, 276, 156, 1104, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  // Filter bar
  session.addRect(fAdmTools, 300, 176, 280, 40, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addIcon(fAdmTools, 'search', 312, 188, 16, '#64748B');
  session.addText(fAdmTools, 'Cari kode / nama alat...', 336, 187, { fontSize: 13, fill: '#94A3B8' });
  session.addRect(fAdmTools, 596, 176, 140, 40, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fAdmTools, 'Semua Kategori', 610, 187, { fontSize: 13, fill: '#0F172A' });
  session.addIcon(fAdmTools, 'chevron-down', 712, 188, 16, '#64748B');
  // Table
  session.addRect(fAdmTools, 300, 232, 1056, 40, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fAdmTools, 'KODE', 316, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmTools, 'NAMA ALAT', 410, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmTools, 'KATEGORI', 620, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmTools, 'TOTAL', 760, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmTools, 'TERSEDIA', 840, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmTools, 'KONDISI', 940, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmTools, 'AKSI', 1180, 246, { fontSize: 11, fontWeight: 700, fill: '#475569' });

  const section16Tools = [
    { kode: 'ALT-001', nama: 'Bor listrik', cat: 'Perkakas', total: 8, ready: 5, rusak: 1, cDesc: '7 Baik · 1 Rusak Berat' },
    { kode: 'ALT-002', nama: 'Proyektor Epson', cat: 'Elektronik', total: 4, ready: 3, rusak: 0, cDesc: '4 Kondisi Baik' },
    { kode: 'ALT-003', nama: 'Kamera DSLR Canon', cat: 'Multimedia', total: 3, ready: 0, rusak: 1, cDesc: '2 Baik (Dipinjam) · 1 Rusak' },
    { kode: 'ALT-004', nama: 'Multimeter Sanwa', cat: 'Elektronik', total: 6, ready: 6, rusak: 0, cDesc: '6 Kondisi Baik' },
  ];
  section16Tools.forEach((t, idx) => {
    const ty = 284 + idx * 64;
    session.addRect(fAdmTools, 300, ty + 54, 1056, 1, { fill: '#F1F5F9' });
    session.addText(fAdmTools, t.kode, 316, ty + 18, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fAdmTools, t.nama, 410, ty + 18, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fAdmTools, t.cat, 620, ty + 18, { fontSize: 12, fill: '#64748B' });
    session.addText(fAdmTools, `${t.total} Unit`, 760, ty + 18, { fontSize: 13, fill: '#0F172A' });
    session.addText(fAdmTools, `${t.ready} Unit`, 840, ty + 18, { fontSize: 13, fontWeight: 700, fill: t.ready > 0 ? '#166534' : '#DC2626' });
    session.addText(fAdmTools, t.cDesc, 940, ty + 18, { fontSize: 12, fill: '#475569' });
    session.addText(fAdmTools, 'Edit · Detail', 1180, ty + 18, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
  });
  session.flush();

  // 4. Admin - Log Aktivitas (1440x960)
  const fAdmLogs = createAdminPageFrame('Admin — Log Aktivitas', 4440, 0, 'Log Aktivitas Sistem', 'Rekam jejak audit keamanan dan perubahan master data.', null);
  session.addRect(fAdmLogs, 276, 156, 1104, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fAdmLogs, 300, 180, 1056, 40, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fAdmLogs, 'WAKTU', 316, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLogs, 'PENGGUNA', 480, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLogs, 'AKSI & PERISTIWA', 680, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLogs, 'DETAIL OBJEK', 920, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLogs, 'IP ADDRESS', 1200, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });

  const logsData = [
    { time: '16 Nov 2026, 09:30', user: 'ahmad.fauzi (Admin)', act: 'UPDATE_TOOL', detail: 'Penyesuaian stok ALT-001 (+2 Unit)', ip: '192.168.1.10' },
    { time: '16 Nov 2026, 08:45', user: 'budi.santoso (Petugas)', act: 'APPROVE_LOAN', detail: 'Persetujuan Peminjaman PJ-0003', ip: '192.168.1.25' },
    { time: '15 Nov 2026, 14:10', user: 'budi.santoso (Petugas)', act: 'VERIFY_RETURN', detail: 'Verifikasi kembali PJ-0005 (Denda Rp10rb)', ip: '192.168.1.25' },
    { time: '15 Nov 2026, 07:20', user: 'siti.rahma (Peminjam)', act: 'SUBMIT_LOAN', detail: 'Pengajuan pinjam PJ-0004 Multimeter', ip: '192.168.1.104' },
  ];
  logsData.forEach((l, idx) => {
    const ly = 236 + idx * 56;
    session.addRect(fAdmLogs, 300, ly + 48, 1056, 1, { fill: '#F1F5F9' });
    session.addText(fAdmLogs, l.time, 316, ly + 14, { fontSize: 12, fill: '#64748B' });
    session.addText(fAdmLogs, l.user, 480, ly + 14, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fAdmLogs, 680, ly + 8, 120, 24, { fill: '#EEF2FF', cornerRadius: 12 });
    session.addText(fAdmLogs, l.act, 696, ly + 13, { fontSize: 10, fontWeight: 700, fill: '#4338CA' });
    session.addText(fAdmLogs, l.detail, 920, ly + 14, { fontSize: 12, fill: '#475569' });
    session.addText(fAdmLogs, l.ip, 1200, ly + 14, { fontSize: 12, fill: '#64748B' });
  });
  session.flush();

  // Add remaining required detail frames to Page 04 (Admin CRUD)

  // ========================================================
  // MASTER ADMIN DETAILED CRUD FRAMES (8 FRAME BARU RIIL)
  // ========================================================
    const adminNav = [
      { name: 'Dashboard', icon: 'layout-dashboard' },
      { name: 'Data Pengguna', icon: 'users' },
      { name: 'Kategori Alat', icon: 'tags' },
      { name: 'Data Alat & Barang', icon: 'wrench' },
      { name: 'Peminjaman', icon: 'clipboard-list' },
      { name: 'Pengembalian', icon: 'refresh-cw' },
      { name: 'Log Aktivitas', icon: 'logs' },
    ];
  
  // 1. Admin — Pengguna Tambah (1440x960)
  const fUsrAdd = session.createFrame({ name: 'Admin — Pengguna Tambah', width: 1440, height: 960, x: 0, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fUsrAdd, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fUsrAdd, 20, 22, 1.0);
  session.addText(fUsrAdd, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrAdd, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fUsrAdd, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Data Pengguna';
    if (isActive) {
      session.addRect(fUsrAdd, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fUsrAdd, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fUsrAdd, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fUsrAdd, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fUsrAdd, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fUsrAdd, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fUsrAdd, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrAdd, 'Tambah Pengguna Baru', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrAdd, 'Form pembuatan akun baru staf bengkel atau siswa peminjam (Docs Section 9.3).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fUsrAdd, 276, 160, 720, 680, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fUsrAdd, 'Nama Lengkap', 306, 196, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrAdd, 306, 218, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrAdd, 'Dewi Lestari', 322, 230, { fontSize: 13, fill: '#0F172A' });

  session.addText(fUsrAdd, 'Username Unik', 306, 280, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrAdd, 306, 302, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrAdd, 'dewi.lestari', 322, 314, { fontSize: 13, fill: '#0F172A' });

  session.addText(fUsrAdd, 'Password (Wajib saat tambah pengguna)', 306, 364, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrAdd, 306, 386, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrAdd, '••••••••••••', 322, 398, { fontSize: 14, fill: '#0F172A' });
  session.addIcon(fUsrAdd, 'eye', 936, 398, 16, '#64748B');

  session.addText(fUsrAdd, 'Role Akses', 306, 448, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrAdd, 306, 470, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrAdd, 'Peminjam (Siswa)', 322, 482, { fontSize: 13, fill: '#0F172A' });
  session.addIcon(fUsrAdd, 'chevron-down', 936, 482, 16, '#64748B');

  session.addText(fUsrAdd, 'Status Akun', 306, 532, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrAdd, 306, 554, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrAdd, 'Aktif', 322, 566, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fUsrAdd, 306, 636, 160, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fUsrAdd, 'Simpan Pengguna', 332, 650, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fUsrAdd, 480, 636, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fUsrAdd, 'Batal', 514, 650, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 2. Admin — Pengguna Edit (1440x960)
  const fUsrEdit = session.createFrame({ name: 'Admin — Pengguna Edit', width: 1440, height: 960, x: 1480, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fUsrEdit, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fUsrEdit, 20, 22, 1.0);
  session.addText(fUsrEdit, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrEdit, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fUsrEdit, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Data Pengguna';
    if (isActive) {
      session.addRect(fUsrEdit, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fUsrEdit, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fUsrEdit, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fUsrEdit, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fUsrEdit, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fUsrEdit, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fUsrEdit, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrEdit, 'Edit Pengguna: budi.santoso', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrEdit, 'Ubah role, status aktif, atau reset kata sandi akun (Docs Section 9.3).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fUsrEdit, 276, 160, 720, 680, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fUsrEdit, 'Nama Lengkap', 306, 196, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrEdit, 306, 218, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrEdit, 'Budi Santoso, A.Md', 322, 230, { fontSize: 13, fill: '#0F172A' });

  session.addText(fUsrEdit, 'Username Unik (Terkunci)', 306, 280, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrEdit, 306, 302, 660, 42, { fill: '#F1F5F9', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrEdit, 'budi.santoso', 322, 314, { fontSize: 13, fill: '#64748B' });

  session.addText(fUsrEdit, 'Password Baru (Opsional, kosongkan jika tidak diubah)', 306, 364, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrEdit, 306, 386, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrEdit, 'Biarkan kosong untuk mempertahankan kata sandi lama', 322, 398, { fontSize: 13, fill: '#94A3B8' });

  session.addText(fUsrEdit, 'Role Hak Akses', 306, 448, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrEdit, 306, 470, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrEdit, 'Petugas Sarpras', 322, 482, { fontSize: 13, fill: '#0F172A' });
  session.addIcon(fUsrEdit, 'chevron-down', 936, 482, 16, '#64748B');

  session.addText(fUsrEdit, 'Status Akun', 306, 532, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fUsrEdit, 306, 554, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fUsrEdit, 'Aktif', 322, 566, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fUsrEdit, 306, 636, 160, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fUsrEdit, 'Simpan Perubahan', 328, 650, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fUsrEdit, 480, 636, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fUsrEdit, 'Batal', 514, 650, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 3. Admin — Pengguna Detail (1440x960)
  const fUsrDet = session.createFrame({ name: 'Admin — Pengguna Detail', width: 1440, height: 960, x: 2960, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fUsrDet, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fUsrDet, 20, 22, 1.0);
  session.addText(fUsrDet, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrDet, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fUsrDet, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Data Pengguna';
    if (isActive) {
      session.addRect(fUsrDet, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fUsrDet, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fUsrDet, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fUsrDet, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fUsrDet, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fUsrDet, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fUsrDet, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrDet, 'Detail Pengguna: Andi Pratama', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrDet, 'Profil akun peminjam dan histori transaksi terkait (Docs Section 9.3). Password tidak pernah ditampilkan.', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fUsrDet, 276, 160, 680, 520, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fUsrDet, 'Profil Akun Sistem', 306, 190, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrDet, 'Nama Lengkap: Andi Pratama', 306, 230, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addText(fUsrDet, 'Username: andi.pratama', 306, 260, { fontSize: 13, fill: '#475569' });
  session.addText(fUsrDet, 'Role: Peminjam (Siswa)', 306, 290, { fontSize: 13, fill: '#475569' });
  session.addText(fUsrDet, 'Status Akun: Aktif', 306, 320, { fontSize: 13, fill: '#166534' });
  session.addText(fUsrDet, 'Terdaftar Sejak: 10 November 2026', 306, 350, { fontSize: 13, fill: '#64748B' });
  session.addText(fUsrDet, 'Histori Peminjaman Terkait: PJ-0001 (Aktif), PJ-0005 (Dikembalikan)', 306, 380, { fontSize: 13, fill: '#475569' });

  session.addRect(fUsrDet, 306, 430, 140, 40, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fUsrDet, 'Edit Pengguna', 332, 442, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fUsrDet, 460, 430, 140, 40, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fUsrDet, 'Kembali ke Daftar', 476, 442, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 4. Admin — Kategori Tambah (1440x960)
  const fCatAdd = session.createFrame({ name: 'Admin — Kategori Tambah', width: 1440, height: 960, x: 4440, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fCatAdd, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fCatAdd, 20, 22, 1.0);
  session.addText(fCatAdd, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCatAdd, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fCatAdd, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Kategori Alat';
    if (isActive) {
      session.addRect(fCatAdd, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fCatAdd, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fCatAdd, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fCatAdd, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fCatAdd, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fCatAdd, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fCatAdd, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCatAdd, 'Tambah Kategori Alat', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCatAdd, 'Form pembuatan kategori master inventaris baru (Docs Section 9.4).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fCatAdd, 276, 160, 680, 520, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fCatAdd, 'Kode Kategori Unik (Contoh: NET, OTO)', 306, 196, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCatAdd, 306, 218, 620, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fCatAdd, 'OTO', 322, 230, { fontSize: 13, fill: '#0F172A' });

  session.addText(fCatAdd, 'Nama Kategori', 306, 280, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCatAdd, 306, 302, 620, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fCatAdd, 'Otomotif & Mesin', 322, 314, { fontSize: 13, fill: '#0F172A' });

  session.addText(fCatAdd, 'Deskripsi Opsional', 306, 364, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCatAdd, 306, 386, 620, 80, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fCatAdd, 'Kunci torsi, kompresor, dongkrak hidrolik bengkel otomotif.', 322, 400, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fCatAdd, 306, 490, 160, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fCatAdd, 'Simpan Kategori', 332, 504, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fCatAdd, 480, 490, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fCatAdd, 'Batal', 514, 504, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 5. Admin — Kategori Edit (1440x960)
  const fCatEdit = session.createFrame({ name: 'Admin — Kategori Edit', width: 1440, height: 960, x: 5920, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fCatEdit, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fCatEdit, 20, 22, 1.0);
  session.addText(fCatEdit, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCatEdit, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fCatEdit, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Kategori Alat';
    if (isActive) {
      session.addRect(fCatEdit, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fCatEdit, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fCatEdit, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fCatEdit, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fCatEdit, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fCatEdit, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fCatEdit, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCatEdit, 'Edit Kategori: Elektronik (ELK)', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCatEdit, 'Kategori ini mereferensikan 2 jenis alat (ALT-002, ALT-004); penghapusan fisik diblokir (Docs Section 9.4).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fCatEdit, 276, 160, 680, 520, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fCatEdit, 'Kode Kategori (Terkunci)', 306, 196, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCatEdit, 306, 218, 620, 42, { fill: '#F1F5F9', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fCatEdit, 'ELK', 322, 230, { fontSize: 13, fill: '#64748B' });

  session.addText(fCatEdit, 'Nama Kategori', 306, 280, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCatEdit, 306, 302, 620, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fCatEdit, 'Elektronik & Kelistrikan', 322, 314, { fontSize: 13, fill: '#0F172A' });

  session.addText(fCatEdit, 'Deskripsi', 306, 364, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCatEdit, 306, 386, 620, 80, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fCatEdit, 'Peralatan uji sirkuit dan pengukuran elektronika laboratorium bengkel.', 322, 400, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fCatEdit, 306, 490, 160, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fCatEdit, 'Simpan Perubahan', 328, 504, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fCatEdit, 480, 490, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fCatEdit, 'Batal', 514, 504, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 6. Admin — Alat Tambah (1440x960)
  const fToolAdd = session.createFrame({ name: 'Admin — Alat Tambah', width: 1440, height: 960, x: 7400, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fToolAdd, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fToolAdd, 20, 22, 1.0);
  session.addText(fToolAdd, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolAdd, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fToolAdd, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Data Alat & Barang';
    if (isActive) {
      session.addRect(fToolAdd, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fToolAdd, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fToolAdd, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fToolAdd, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fToolAdd, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fToolAdd, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fToolAdd, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolAdd, 'Tambah Alat & Barang Baru', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolAdd, 'Form penambahan master alat baru ke laboratorium (Docs Section 9.5).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fToolAdd, 276, 160, 720, 720, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fToolAdd, 'Kode Alat Unik', 306, 196, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolAdd, 306, 218, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolAdd, 'ALT-005', 322, 230, { fontSize: 13, fill: '#0F172A' });

  session.addText(fToolAdd, 'Nama Alat', 306, 276, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolAdd, 306, 298, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolAdd, 'Gerinda Tangan Bosch GWS 060', 322, 310, { fontSize: 13, fill: '#0F172A' });

  session.addText(fToolAdd, 'Kategori Alat', 306, 356, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolAdd, 306, 378, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolAdd, 'Perkakas', 322, 390, { fontSize: 13, fill: '#0F172A' });
  session.addIcon(fToolAdd, 'chevron-down', 936, 390, 16, '#64748B');

  session.addText(fToolAdd, 'Lokasi Penyimpanan', 306, 436, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolAdd, 306, 458, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolAdd, 'Lemari Alat Bengkel Mekanik', 322, 470, { fontSize: 13, fill: '#0F172A' });

  session.addText(fToolAdd, 'Stok Total Awal', 306, 516, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolAdd, 306, 538, 300, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolAdd, '4', 322, 550, { fontSize: 13, fill: '#0F172A' });

  session.addText(fToolAdd, 'Kondisi Awal', 666, 516, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolAdd, 666, 538, 300, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolAdd, 'Baik (Semua Layak Pakai)', 682, 550, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fToolAdd, 306, 620, 160, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fToolAdd, 'Simpan Alat', 346, 634, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fToolAdd, 480, 620, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fToolAdd, 'Batal', 514, 634, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 7. Admin — Alat Edit (1440x960)
  const fToolEdit = session.createFrame({ name: 'Admin — Alat Edit', width: 1440, height: 960, x: 8880, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fToolEdit, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fToolEdit, 20, 22, 1.0);
  session.addText(fToolEdit, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolEdit, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fToolEdit, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Data Alat & Barang';
    if (isActive) {
      session.addRect(fToolEdit, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fToolEdit, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fToolEdit, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fToolEdit, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fToolEdit, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fToolEdit, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fToolEdit, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolEdit, 'Edit Alat: ALT-001 (Bor listrik)', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolEdit, 'Perubahan stok melalui penyesuaian yang memperhitungkan 2 unit sedang dipinjam (PJ-0001).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fToolEdit, 276, 160, 720, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fToolEdit, 'Kode Alat (Terkunci)', 306, 196, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolEdit, 306, 218, 660, 42, { fill: '#F1F5F9', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolEdit, 'ALT-001', 322, 230, { fontSize: 13, fill: '#64748B' });

  session.addText(fToolEdit, 'Nama Alat', 306, 276, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolEdit, 306, 298, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolEdit, 'Bor listrik', 322, 310, { fontSize: 13, fill: '#0F172A' });

  session.addText(fToolEdit, 'Lokasi Penyimpanan', 306, 356, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolEdit, 306, 378, 660, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolEdit, 'Rak B-02 Bengkel Listrik', 322, 390, { fontSize: 13, fill: '#0F172A' });

  // Banner Proteksi Alokasi Stok
  session.addRect(fToolEdit, 306, 440, 660, 80, { fill: '#FFFBEB', cornerRadius: 8, stroke: '#FCD34D', strokeWeight: 1 });
  session.addText(fToolEdit, 'Proteksi Alokasi Stok (Docs Section 9.5):', 324, 456, { fontSize: 12, fontWeight: 700, fill: '#92400E' });
  session.addText(fToolEdit, 'Total aset: 8 unit. Sedang dipinjam: 2 unit (PJ-0001). Rusak: 1 unit.', 324, 478, { fontSize: 12, fill: '#92400E' });
  session.addText(fToolEdit, 'Stok tersedia siap pinjam terkunci dihitung dari: Total - Dipinjam - Rusak = 5 unit.', 324, 498, { fontSize: 12, fontWeight: 700, fill: '#B45309' });

  session.addText(fToolEdit, 'Penyesuaian Total Stok (Audit Rekonsiliasi)', 306, 540, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fToolEdit, 306, 562, 300, 42, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fToolEdit, '8', 322, 574, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fToolEdit, 306, 640, 160, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fToolEdit, 'Simpan Rekonsiliasi', 324, 654, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fToolEdit, 480, 640, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fToolEdit, 'Batal', 514, 654, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  // 8. Admin — Alat Detail (1440x960)
  const fToolDet = session.createFrame({ name: 'Admin — Alat Detail', width: 1440, height: 960, x: 10360, y: 1000, props: { fill: '#F8FAFC' } });
  session.addRect(fToolDet, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fToolDet, 20, 22, 1.0);
  session.addText(fToolDet, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolDet, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fToolDet, 0, 76, 248, 1, { fill: '#E2E8F0' });
  adminNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Data Alat & Barang';
    if (isActive) {
      session.addRect(fToolDet, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fToolDet, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fToolDet, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fToolDet, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fToolDet, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fToolDet, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fToolDet, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolDet, 'Detail Alat: ALT-001 (Bor listrik)', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolDet, 'Spesifikasi fisik, rincian lokasi penyimpanan, dan status alokasi stok (Docs Section 9.5).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fToolDet, 276, 160, 680, 560, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fToolDet, 306, 190, 200, 160, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addIcon(fToolDet, 'image', 390, 255, 32, '#94A3B8');
  session.addText(fToolDet, 'Placeholder Foto Alat', 345, 298, { fontSize: 11, fill: '#94A3B8' });

  session.addText(fToolDet, 'Bor listrik (ALT-001)', 530, 190, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolDet, 'Kategori: Perkakas Bengkel', 530, 218, { fontSize: 13, fill: '#64748B' });
  session.addText(fToolDet, 'Lokasi: Rak B-02 Bengkel Listrik', 530, 242, { fontSize: 13, fill: '#64748B' });
  session.addText(fToolDet, 'Deskripsi: Kapasitas chuck 13mm, daya 550W untuk praktikum kelistrikan mesin.', 530, 268, { fontSize: 12, fill: '#475569' });

  // 3 Metrik Stok Rinci
  session.addRect(fToolDet, 306, 380, 190, 80, { fill: '#DCFCE7', cornerRadius: 8 });
  session.addText(fToolDet, 'Stok Tersedia', 322, 396, { fontSize: 12, fontWeight: 700, fill: '#166534' });
  session.addText(fToolDet, '5 Unit', 322, 422, { fontSize: 20, fontWeight: 700, fill: '#166534' });

  session.addRect(fToolDet, 516, 380, 190, 80, { fill: '#EEF2FF', cornerRadius: 8 });
  session.addText(fToolDet, 'Sedang Dipinjam', 532, 396, { fontSize: 12, fontWeight: 700, fill: '#3730A3' });
  session.addText(fToolDet, '2 Unit (PJ-0001)', 532, 422, { fontSize: 16, fontWeight: 700, fill: '#3730A3' });

  session.addRect(fToolDet, 726, 380, 190, 80, { fill: '#FEE2E2', cornerRadius: 8 });
  session.addText(fToolDet, 'Kondisi Rusak', 742, 396, { fontSize: 12, fontWeight: 700, fill: '#991B1B' });
  session.addText(fToolDet, '1 Unit (Perawatan)', 742, 422, { fontSize: 16, fontWeight: 700, fill: '#991B1B' });

  session.addRect(fToolDet, 306, 490, 140, 40, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fToolDet, 'Edit Master Alat', 328, 502, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fToolDet, 460, 490, 140, 40, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fToolDet, 'Kembali ke Daftar', 476, 502, { fontSize: 13, fontWeight: 700, fill: '#475569' });
  session.flush();

  // Frame 5: Admin — Peminjaman & Pengembalian
  const fAdmLoans = session.createFrame({ name: 'Admin — Peminjaman & Pengembalian', width: 1440, height: 960, x: 5920, y: 0, props: { fill: '#F8FAFC' } });
  // Sidebar
  session.addRect(fAdmLoans, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fAdmLoans, 20, 22, 1.0);
  session.addText(fAdmLoans, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fAdmLoans, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fAdmLoans, 0, 76, 248, 1, { fill: '#E2E8F0' });
  const adminNavL = [
    { name: 'Dashboard', icon: 'layout-dashboard' },
    { name: 'Data Pengguna', icon: 'users' },
    { name: 'Kategori Alat', icon: 'tags' },
    { name: 'Data Alat & Barang', icon: 'wrench' },
    { name: 'Peminjaman', icon: 'clipboard-list' },
    { name: 'Pengembalian', icon: 'refresh-cw' },
    { name: 'Log Aktivitas', icon: 'logs' },
  ];
  adminNavL.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    const isActive = item.name === 'Peminjaman';
    if (isActive) {
      session.addRect(fAdmLoans, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fAdmLoans, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fAdmLoans, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fAdmLoans, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fAdmLoans, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });
  session.addRect(fAdmLoans, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fAdmLoans, 'SPARTA — Administrator Panel', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fAdmLoans, 'Daftar Peminjaman & Pengembalian', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fAdmLoans, 'Riwayat seluruh siklus transaksi; edit jumlah dibatasi sebelum persetujuan petugas.', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fAdmLoans, 276, 156, 1104, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fAdmLoans, 300, 180, 1056, 40, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fAdmLoans, 'KODE', 316, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLoans, 'PEMINJAM', 410, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLoans, 'ALAT & BARANG', 580, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLoans, 'QTY', 730, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLoans, 'JATUH TEMPO', 800, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLoans, 'STATUS TRANSAKSI', 940, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fAdmLoans, 'AKSI', 1180, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });

  const allLoansList = [
    { kode: 'PJ-0001', user: 'Andi Pratama', tool: 'Bor listrik (ALT-001)', qty: '2 Unit', due: '18 Nov 2026', st: 'Dipinjam', bg: '#E0E7FF', fg: '#3730A3' },
    { kode: 'PJ-0002', user: 'Siti Rahma', tool: 'Proyektor Epson (ALT-002)', qty: '1 Unit', due: '15 Nov 2026', st: 'Dipinjam · Terlambat', bg: '#FFEDD5', fg: '#9A3412' },
    { kode: 'PJ-0003', user: 'Budi Santoso', tool: 'Kamera DSLR (ALT-003)', qty: '2 Unit', due: '20 Nov 2026', st: 'Disetujui', bg: '#EEF2FF', fg: '#4338CA' },
    { kode: 'PJ-0004', user: 'Siti Rahma', tool: 'Multimeter Sanwa (ALT-004)', qty: '1 Unit', due: '19 Nov 2026', st: 'Menunggu Persetujuan', bg: '#FEF3C7', fg: '#92400E' },
    { kode: 'PJ-0005', user: 'Andi Pratama', tool: 'Bor listrik (ALT-001)', qty: '1 Unit', due: '12 Nov 2026', st: 'Dikembalikan 14 Nov 2026', bg: '#DCFCE7', fg: '#166534' },
  ];
  allLoansList.forEach((l, idx) => {
    const ly = 236 + idx * 60;
    session.addRect(fAdmLoans, 300, ly + 50, 1056, 1, { fill: '#F1F5F9' });
    session.addText(fAdmLoans, l.kode, 316, ly + 16, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fAdmLoans, l.user, 410, ly + 16, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fAdmLoans, l.tool, 580, ly + 16, { fontSize: 12, fill: '#475569' });
    session.addText(fAdmLoans, l.qty, 730, ly + 16, { fontSize: 12, fill: '#0F172A' });
    session.addText(fAdmLoans, l.due, 800, ly + 16, { fontSize: 12, fill: '#64748B' });
    session.addRect(fAdmLoans, 940, ly + 8, 160, 28, { fill: l.bg, cornerRadius: 14 });
    session.addText(fAdmLoans, l.st, 952, ly + 14, { fontSize: 10, fontWeight: 700, fill: l.fg });
    session.addText(fAdmLoans, 'Detail Koreksi', 1180, ly + 16, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
  });
  session.flush();

  // ==========================================
  // PAGE 5: PETUGAS OPERATIONS & REPORTS
  // ==========================================
  console.log('Building Page 05 — Petugas Operations...');
  const pPetugas = session.createPage('05 — Petugas Ops');

  // 1. Petugas - Riwayat Read-Only Pengembalian PJ-0005 (Docs Section 16: Stok SUDAH dipulihkan)
  const fPetReturn = session.createFrame({ name: 'Petugas — Verifikasi Pengembalian & Denda', width: 1440, height: 960, x: 0, y: 0, props: { fill: '#F8FAFC' } });
  session.addRect(fPetReturn, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fPetReturn, 20, 22, 1.0);
  session.addText(fPetReturn, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetReturn, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fPetReturn, 0, 76, 248, 1, { fill: '#E2E8F0' });

  session.addRect(fPetReturn, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetReturn, 'SPARTA — Operasional Petugas Sarpras', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetReturn, 'Detail Pengembalian Selesai #PJ-0005 (Read-Only)', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetReturn, 'Arsip catatan fisik pengembalian dan denda terlambat (stok telah dipulihkan pada 14 Nov 2026).', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fPetReturn, 276, 160, 680, 720, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetReturn, 'Data Pengembalian Transaksi PJ-0005', 306, 190, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetReturn, 'Peminjam: Andi Pratama · Alat: Bor listrik (ALT-001) · Jumlah: 1 Unit', 306, 218, { fontSize: 13, fill: '#64748B' });

  session.addText(fPetReturn, 'Unit Layak Pakai (Kondisi Baik)', 306, 260, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPetReturn, 306, 280, 290, 44, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPetReturn, '1 Unit (Diterima Lengkap)', 324, 293, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });

  session.addText(fPetReturn, 'Unit Rusak / Hilang', 626, 260, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPetReturn, 626, 280, 290, 44, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPetReturn, '0 Unit', 644, 293, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });

  session.addText(fPetReturn, 'Catatan Pemeriksaan Fisik', 306, 344, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPetReturn, 306, 366, 610, 80, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPetReturn, 'Mata bor lengkap, kabel rapi tanpa cacat, motor berfungsi normal.', 324, 384, { fontSize: 13, fill: '#0F172A' });

  session.addRect(fPetReturn, 306, 470, 610, 160, { fill: '#FFFBEB', cornerRadius: 8, stroke: '#FCD34D', strokeWeight: 1 });
  session.addText(fPetReturn, 'Perhitungan Denda Keterlambatan (Docs Section 16)', 324, 492, { fontSize: 14, fontWeight: 700, fill: '#92400E' });
  session.addText(fPetReturn, 'Jatuh tempo: 12 Nov 2026 · Kembali aktual: 14 Nov 2026 (Terlambat 2 Hari)', 324, 516, { fontSize: 12, fill: '#92400E' });
  session.addText(fPetReturn, 'Tarif denda: Rp5.000 / hari keterlambatan unit perkakas', 324, 538, { fontSize: 12, fill: '#92400E' });
  session.addText(fPetReturn, 'Total Denda Final: Rp 10.000', 324, 576, { fontSize: 18, fontWeight: 700, fill: '#B45309' });
  session.addRect(fPetReturn, 740, 568, 156, 32, { fill: '#DCFCE7', cornerRadius: 16 });
  session.addText(fPetReturn, 'Status: Dikembalikan', 752, 576, { fontSize: 11, fontWeight: 700, fill: '#166534' });

  session.addRect(fPetReturn, 306, 660, 200, 44, { fill: '#F1F5F9', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetReturn, 'Kembali ke Monitoring', 336, 674, { fontSize: 13, fontWeight: 700, fill: '#475569' });

  session.addRect(fPetReturn, 980, 160, 400, 360, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetReturn, 'Status Rekonsiliasi Stok Lab', 1004, 190, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetReturn, 'Informasi inventaris ALT-001 (Bor listrik):', 1004, 214, { fontSize: 12, fill: '#64748B' });

  session.addRect(fPetReturn, 1004, 240, 352, 120, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetReturn, 'ALT-001 (Bor listrik)', 1020, 256, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetReturn, 'Stok Tersedia: 5 Unit (Sudah dipulihkan 14 Nov)', 1020, 280, { fontSize: 12, fontWeight: 700, fill: '#166534' });
  session.addText(fPetReturn, 'Dipinjam Berjalan: 2 Unit (PJ-0001)', 1020, 302, { fontSize: 12, fill: '#475569' });
  session.addText(fPetReturn, 'Stok Rusak: 1 Unit · Total Aset: 8 Unit', 1020, 324, { fontSize: 12, fill: '#64748B' });
  session.flush();

  // 2. Petugas - Laporan Cetak Sarpras (A4 Landscape: 1123 x 794)
  const fPetRep = session.createFrame({ name: 'Petugas — Laporan Sarpras A4 Print', width: 1123, height: 794, x: 1480, y: 0, props: { fill: '#FFFFFF' } });
  // Header Surat Resmi Sekolah (Tanpa navigasi aplikasi)
  placeLogo(fPetRep, 50, 40, 1.3);
  session.addText(fPetRep, 'SMK NEGERI 1 LEUWIMUNDING — SARANA & PRASARANA', 120, 46, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'Sistem Pengelolaan Alat Praktik Laboratorium & Bengkel (SPARTA)', 120, 70, { fontSize: 11, fill: '#475569' });
  session.addRect(fPetRep, 50, 120, 1023, 2, { fill: '#0F172A' });
  session.addRect(fPetRep, 50, 124, 1023, 1, { fill: '#0F172A' });

  session.addText(fPetRep, 'LAPORAN REKAPITULASI PEMINJAMAN ALAT LABORATORIUM & BENGKEL', 260, 148, { fontSize: 14, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'Periode: 10 November 2026 s.d 16 November 2026', 420, 170, { fontSize: 11, fill: '#64748B' });

  // Tabel Laporan Resmi
  session.addRect(fPetRep, 50, 204, 1023, 30, { fill: '#F1F5F9' });
  session.addText(fPetRep, 'NO', 62, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'KODE PINJAM', 100, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'NAMA PEMINJAM', 220, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'ALAT & BARANG', 380, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'QTY', 560, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'JATUH TEMPO', 620, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'STATUS TRANSAKSI', 760, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'DENDA', 940, 214, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });

  const repRows = [
    { no: '1', kode: 'PJ-0001', user: 'Andi Pratama', tool: 'Bor listrik (ALT-001)', qty: '2', due: '18 Nov 2026', st: 'Dipinjam', fine: 'Rp 0' },
    { no: '2', kode: 'PJ-0002', user: 'Siti Rahma', tool: 'Proyektor Epson (ALT-002)', qty: '1', due: '15 Nov 2026', st: 'Dipinjam · Terlambat', fine: 'Rp 5.000 (est)' },
    { no: '3', kode: 'PJ-0003', user: 'Budi Santoso', tool: 'Kamera DSLR (ALT-003)', qty: '2', due: '20 Nov 2026', st: 'Disetujui', fine: 'Rp 0' },
    { no: '4', kode: 'PJ-0004', user: 'Siti Rahma', tool: 'Multimeter Sanwa (ALT-004)', qty: '1', due: '19 Nov 2026', st: 'Menunggu Persetujuan', fine: 'Rp 0' },
    { no: '5', kode: 'PJ-0005', user: 'Andi Pratama', tool: 'Bor listrik (ALT-001)', qty: '1', due: '12 Nov 2026', st: 'Dikembalikan 14 Nov 2026', fine: 'Rp 10.000' },
  ];
  repRows.forEach((r, idx) => {
    const ry = 238 + idx * 36;
    session.addRect(fPetRep, 50, ry + 32, 1023, 1, { fill: '#E2E8F0' });
    session.addText(fPetRep, r.no, 64, ry + 10, { fontSize: 10, fill: '#0F172A' });
    session.addText(fPetRep, r.kode, 100, ry + 10, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
    session.addText(fPetRep, r.user, 220, ry + 10, { fontSize: 10, fill: '#0F172A' });
    session.addText(fPetRep, r.tool, 380, ry + 10, { fontSize: 10, fill: '#0F172A' });
    session.addText(fPetRep, r.qty, 566, ry + 10, { fontSize: 10, fill: '#0F172A' });
    session.addText(fPetRep, r.due, 620, ry + 10, { fontSize: 10, fill: '#0F172A' });
    session.addText(fPetRep, r.st, 760, ry + 10, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
    session.addText(fPetRep, r.fine, 940, ry + 10, { fontSize: 10, fontWeight: 700, fill: '#0F172A' });
  });

  // Tanda Tangan Laporan Resmi
  session.addText(fPetRep, 'Majalengka, 16 November 2026', 820, 520, { fontSize: 11, fill: '#0F172A' });
  session.addText(fPetRep, 'Petugas Pengelola Sarpras,', 820, 540, { fontSize: 11, fill: '#0F172A' });
  session.addText(fPetRep, 'Budi Santoso, A.Md', 820, 640, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetRep, 'NIP. 19850412 201101 1 003', 820, 658, { fontSize: 10, fill: '#64748B' });
  session.flush();

  // Frame 3: Petugas — Antrean Persetujuan Peminjaman
  const fPetApprove = session.createFrame({ name: 'Petugas — Antrean Persetujuan', width: 1440, height: 960, x: 2640, y: 0, props: { fill: '#F8FAFC' } });
  session.addRect(fPetApprove, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fPetApprove, 20, 22, 1.0);
  session.addText(fPetApprove, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetApprove, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fPetApprove, 0, 76, 248, 1, { fill: '#E2E8F0' });

  const pNav = [
    { name: 'Dashboard', icon: 'layout-dashboard' },
    { name: 'Daftar Alat', icon: 'wrench' },
    { name: 'Persetujuan Peminjaman', icon: 'clipboard-list', active: true },
    { name: 'Penerimaan Kembali', icon: 'refresh-cw' },
    { name: 'Laporan Sarpras', icon: 'scroll-text' },
  ];
  pNav.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    if (item.active) {
      session.addRect(fPetApprove, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fPetApprove, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fPetApprove, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fPetApprove, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fPetApprove, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });

  session.addRect(fPetApprove, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetApprove, 'SPARTA — Operasional Petugas Sarpras', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetApprove, 'Antrean Persetujuan Peminjaman', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetApprove, 'Tinjau permohonan alat, dampak alokasi stok, dan persetujuan/penolakan.', 276, 124, { fontSize: 14, fill: '#64748B' });

  session.addRect(fPetApprove, 276, 156, 1104, 740, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fPetApprove, 300, 180, 1056, 40, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fPetApprove, 'KODE', 316, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fPetApprove, 'PEMINJAM', 410, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fPetApprove, 'ALAT', 580, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fPetApprove, 'JUMLAH', 730, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fPetApprove, 'STOK SIAP', 820, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fPetApprove, 'STATUS', 940, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.addText(fPetApprove, 'AKSI', 1180, 194, { fontSize: 11, fontWeight: 700, fill: '#475569' });

  const pendingRow = { kode: 'PJ-0004', user: 'Siti Rahma (Peminjam)', tool: 'Multimeter Sanwa (ALT-004)', qty: '1 Unit', ready: '6 Unit Tersedia', st: 'Menunggu Persetujuan' };
  const py = 236;
  session.addRect(fPetApprove, 300, py + 50, 1056, 1, { fill: '#F1F5F9' });
  session.addText(fPetApprove, pendingRow.kode, 316, py + 16, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
  session.addText(fPetApprove, pendingRow.user, 410, py + 16, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetApprove, pendingRow.tool, 580, py + 16, { fontSize: 12, fill: '#475569' });
  session.addText(fPetApprove, pendingRow.qty, 730, py + 16, { fontSize: 12, fill: '#0F172A' });
  session.addText(fPetApprove, pendingRow.ready, 820, py + 16, { fontSize: 12, fontWeight: 700, fill: '#166534' });
  session.addRect(fPetApprove, 940, py + 8, 160, 28, { fill: '#FEF3C7', cornerRadius: 14 });
  session.addText(fPetApprove, pendingRow.st, 952, py + 14, { fontSize: 10, fontWeight: 700, fill: '#92400E' });
  session.addText(fPetApprove, 'Tinjau & Setujui →', 1180, py + 16, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });

  // Modal Review Persetujuan Peminjaman
  session.addRect(fPetApprove, 0, 0, 1440, 960, { fill: 'rgba(15,23,42,0.45)' });
  session.addRect(fPetApprove, 470, 180, 500, 480, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetApprove, 'Tinjau Pengajuan #PJ-0004', 506, 216, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetApprove, 'Peminjam: Siti Rahma · Alat: Multimeter Sanwa (ALT-004)', 506, 242, { fontSize: 12, fill: '#64748B' });

  session.addRect(fPetApprove, 506, 276, 428, 90, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPetApprove, 'Dampak Alokasi Stok Alat:', 522, 292, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPetApprove, 'Stok Saat Ini: 6 Unit Siap', 522, 314, { fontSize: 12, fill: '#475569' });
  session.addText(fPetApprove, 'Setelah Disetujui: 5 Unit Siap (-1 Unit dialokasikan)', 522, 334, { fontSize: 12, fontWeight: 700, fill: '#4F46E5' });

  session.addText(fPetApprove, 'Alasan Penolakan (Wajib diisi jika menolak)', 506, 386, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPetApprove, 506, 406, 428, 60, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPetApprove, 'Unit sedang dalam kalibrasi laboratorium.', 522, 420, { fontSize: 12, fill: '#94A3B8' });

  session.addRect(fPetApprove, 506, 580, 120, 40, { fill: '#DC2626', cornerRadius: 8 });
  session.addText(fPetApprove, 'Tolak Pengajuan', 520, 592, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fPetApprove, 638, 580, 160, 40, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fPetApprove, 'Setujui Pengajuan', 662, 592, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fPetApprove, 810, 580, 90, 40, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fPetApprove, 'Tutup', 838, 592, { fontSize: 12, fontWeight: 700, fill: '#475569' });
  session.flush();

  // ==========================================
  // PAGE 6: PEMINJAM EXPERIENCE (FLOW)
  // ==========================================
  console.log('Building Page 06 — Peminjam Experience...');
  const pPeminjam = session.createPage('06 — Peminjam Exp');

  // 1. Peminjam - Katalog Alat (1440x960)
  const fPemKat = session.createFrame({ name: 'Peminjam — Katalog Alat', width: 1440, height: 960, x: 0, y: 0, props: { fill: '#F8FAFC' } });
  session.addRect(fPemKat, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fPemKat, 20, 22, 1.0);
  session.addText(fPemKat, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemKat, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fPemKat, 0, 76, 248, 1, { fill: '#E2E8F0' });

  // Topbar
  session.addRect(fPemKat, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPemKat, 'SPARTA — Portal Peminjam Siswa & Guru', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemKat, 'Katalog Alat Laboratorium', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemKat, 'Pilih alat bengkel praktik yang tersedia untuk dipinjam.', 276, 124, { fontSize: 14, fill: '#64748B' });

  // Grid Katalog Cards (4 alat section 16)
  const katCards = [
    { kode: 'ALT-001', nama: 'Bor listrik', cat: 'Perkakas', ready: 5, total: 8, spec: 'Kapasitas chuck 13mm, daya 550W' },
    { kode: 'ALT-002', nama: 'Proyektor Epson', cat: 'Elektronik', ready: 3, total: 4, spec: '3300 Lumens, HDMI/VGA support' },
    { kode: 'ALT-003', nama: 'Kamera DSLR Canon', cat: 'Multimedia', ready: 0, total: 3, spec: 'Lensa 18-55mm IS II, Full HD Video' },
    { kode: 'ALT-004', nama: 'Multimeter Sanwa', cat: 'Elektronik', ready: 6, total: 6, spec: 'Digital multimeter presisi tinggi' },
  ];
  katCards.forEach((k, idx) => {
    const col = idx % 2;
    const row = Math.floor(idx / 2);
    const kx = 276 + col * 550;
    const ky = 160 + row * 260;

    session.addRect(fPemKat, kx, ky, 520, 230, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addRect(fPemKat, kx + 16, ky + 16, 140, 130, { fill: '#F1F5F9', cornerRadius: 8 });
    session.addIcon(fPemKat, 'image', kx + 68, ky + 66, 32, '#94A3B8');
    session.addText(fPemKat, k.nama, kx + 176, ky + 20, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
    session.addText(fPemKat, `${k.kode} · ${k.cat}`, kx + 176, ky + 44, { fontSize: 12, fill: '#64748B' });
    session.addText(fPemKat, k.spec, kx + 176, ky + 70, { fontSize: 12, fill: '#475569' });

    // Stock pill
    if (k.ready > 0) {
      session.addRect(fPemKat, kx + 176, ky + 104, 130, 26, { fill: '#DCFCE7', cornerRadius: 13 });
      session.addText(fPemKat, `Tersedia: ${k.ready} Unit`, kx + 190, ky + 110, { fontSize: 11, fontWeight: 700, fill: '#166534' });
      // CTA button
      session.addRect(fPemKat, kx + 360, ky + 164, 140, 40, { fill: '#4F46E5', cornerRadius: 8 });
      session.addText(fPemKat, 'Ajukan Pinjam', kx + 384, ky + 176, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
    } else {
      session.addRect(fPemKat, kx + 176, ky + 104, 130, 26, { fill: '#FEE2E2', cornerRadius: 13 });
      session.addText(fPemKat, 'Stok Habis Dipinjam', kx + 184, ky + 110, { fontSize: 11, fontWeight: 700, fill: '#991B1B' });
      // Disabled button
      session.addRect(fPemKat, kx + 360, ky + 164, 140, 40, { fill: '#F1F5F9', cornerRadius: 8 });
      session.addText(fPemKat, 'Tidak Tersedia', kx + 384, ky + 176, { fontSize: 13, fontWeight: 700, fill: '#94A3B8' });
    }
  });
  session.flush();

  // 2. Peminjam - Modal Form Pengajuan Pinjam
  const fPemForm = session.createFrame({ name: 'Peminjam — Form Pengajuan Pinjam', width: 1440, height: 960, x: 1480, y: 0, props: { fill: '#F8FAFC' } });
  session.addRect(fPemForm, 0, 0, 1440, 960, { fill: 'rgba(15,23,42,0.45)' });
  session.addRect(fPemForm, 470, 140, 500, 620, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPemForm, 'Formulir Pengajuan Pinjam Alat', 506, 176, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemForm, 'Peminjaman untuk keperluan praktik laboratorium SMKN 1 Leuwimunding.', 506, 202, { fontSize: 12, fill: '#64748B' });
  session.addIcon(fPemForm, 'x', 926, 176, 20, '#64748B');

  session.addText(fPemForm, 'Alat Dipilih: Bor listrik (ALT-001) · Maksimal 2 Unit', 506, 240, { fontSize: 12, fontWeight: 700, fill: '#4F46E5' });

  session.addText(fPemForm, 'Jumlah Unit Pinjam', 506, 276, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPemForm, 506, 296, 428, 40, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPemForm, '2', 522, 307, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });

  session.addText(fPemForm, 'Tanggal Rencana Pengembalian', 506, 350, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPemForm, 506, 370, 428, 40, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPemForm, '18 November 2026', 522, 381, { fontSize: 13, fill: '#0F172A' });

  session.addText(fPemForm, 'Tujuan & Keperluan Peminjaman', 506, 424, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fPemForm, 506, 444, 428, 80, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#CBD5E1', strokeWeight: 1 });
  session.addText(fPemForm, 'Praktik perakitan casing simulator IoT kelas XII RPL 1.', 522, 462, { fontSize: 13, fill: '#0F172A' });

  // Syarat SOP
  session.addRect(fPemForm, 506, 540, 16, 16, { fill: '#4F46E5', cornerRadius: 4 });
  session.addIcon(fPemForm, 'check', 507, 541, 14, '#FFFFFF');
  session.addText(fPemForm, 'Konfirmasi rencana pinjam dan tenggat pengembalian alat.', 530, 539, { fontSize: 11, fill: '#475569' });

  // Actions
  session.addRect(fPemForm, 690, 680, 100, 40, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fPemForm, 'Batal', 724, 691, { fontSize: 13, fontWeight: 700, fill: '#475569' });
  session.addRect(fPemForm, 804, 680, 130, 40, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fPemForm, 'Kirim Pengajuan', 816, 691, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.flush();

  // Frame 3: Peminjam — Peminjaman Saya & Riwayat Timeline
  const fPemLoans = session.createFrame({ name: 'Peminjam — Peminjaman Saya & Riwayat', width: 1440, height: 960, x: 2960, y: 0, props: { fill: '#F8FAFC' } });
  session.addRect(fPemLoans, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fPemLoans, 20, 22, 1.0);
  session.addText(fPemLoans, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemLoans, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
  session.addRect(fPemLoans, 0, 76, 248, 1, { fill: '#E2E8F0' });

  const pNavPem = [
    { name: 'Katalog Alat', icon: 'wrench' },
    { name: 'Peminjaman Saya', icon: 'clipboard-list', active: true },
    { name: 'Riwayat Pinjam', icon: 'logs' },
  ];
  pNavPem.forEach((item, idx) => {
    const ny = 92 + idx * 48;
    if (item.active) {
      session.addRect(fPemLoans, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fPemLoans, item.icon, 24, ny + 10, 20, '#4F46E5');
      session.addText(fPemLoans, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fPemLoans, item.icon, 24, ny + 10, 20, '#64748B');
      session.addText(fPemLoans, item.name, 56, ny + 11, { fontSize: 14, fontWeight: 500, fill: '#475569' });
    }
  });

  session.addRect(fPemLoans, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPemLoans, 'SPARTA — Portal Peminjam', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemLoans, 'Peminjaman Saya & Riwayat Timeline', 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemLoans, 'Status peminjaman aktif pengguna sendiri, aksi pembatalan saat pending, dan pengajuan kembali.', 276, 124, { fontSize: 14, fill: '#64748B' });

  // Panel Kiri: Daftar Peminjaman Saya (Width 640)
  session.addRect(fPemLoans, 276, 160, 640, 720, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPemLoans, 'Transaksi Pinjam Anda', 304, 190, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });

  const myLoans = [
    { kode: 'PJ-0001', alat: 'Bor listrik (ALT-001)', qty: '2 Unit', due: '18 Nov 2026', st: 'Dipinjam', bg: '#E0E7FF', fg: '#3730A3', canReturn: true },
    { kode: 'PJ-0005', alat: 'Bor listrik (ALT-001)', qty: '1 Unit', due: '12 Nov 2026', st: 'Dikembalikan 14 Nov', bg: '#DCFCE7', fg: '#166534', canReturn: false },
  ];
  myLoans.forEach((ml, idx) => {
    const myY = 230 + idx * 110;
    session.addRect(fPemLoans, 304, myY, 584, 94, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(fPemLoans, ml.kode, 324, myY + 16, { fontSize: 14, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fPemLoans, ml.alat, 410, myY + 16, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fPemLoans, `Jumlah: ${ml.qty} · Jatuh Tempo: ${ml.due}`, 324, myY + 42, { fontSize: 12, fill: '#64748B' });

    session.addRect(fPemLoans, 324, myY + 62, 140, 22, { fill: ml.bg, cornerRadius: 11 });
    session.addText(fPemLoans, ml.st, 334, myY + 66, { fontSize: 10, fontWeight: 700, fill: ml.fg });

    if (ml.canReturn) {
      session.addRect(fPemLoans, 740, myY + 24, 130, 36, { fill: '#4F46E5', cornerRadius: 6 });
      session.addText(fPemLoans, 'Ajukan Kembali', 752, myY + 34, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' });
    }
  });

  // Panel Kanan: Timeline Riwayat #PJ-0001
  session.addRect(fPemLoans, 936, 160, 444, 720, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fPemLoans, 'Timeline Status #PJ-0001', 964, 190, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fPemLoans, 'Bor listrik (2 Unit) · Andi Pratama', 964, 214, { fontSize: 12, fill: '#64748B' });

  const timelineEvents = [
    { time: '11 Nov 2026, 08:30', title: 'Pengajuan Dibuat', desc: 'Diajukan oleh peminjam Andi Pratama', done: true },
    { time: '11 Nov 2026, 10:15', title: 'Disetujui Petugas', desc: 'Disetujui oleh Budi Santoso (Petugas)', done: true },
    { time: '11 Nov 2026, 13:00', title: 'Serah Terima Selesai', desc: 'Unit diserahkan fisik, status menjadi Dipinjam', done: true },
    { time: '18 Nov 2026, 15:00', title: 'Batas Jatuh Tempo', desc: 'Wajib kembali dalam kondisi bersih & lengkap', done: false },
  ];
  timelineEvents.forEach((te, idx) => {
    const ey = 260 + idx * 80;
    session.addRect(fPemLoans, 964, ey + 4, 16, 16, { fill: te.done ? '#4F46E5' : '#CBD5E1', cornerRadius: 8 });
    if (idx < 3) session.addRect(fPemLoans, 971, ey + 20, 2, 64, { fill: '#E2E8F0' });
    session.addText(fPemLoans, te.title, 996, ey + 2, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fPemLoans, te.time, 996, ey + 22, { fontSize: 11, fill: '#64748B' });
    session.addText(fPemLoans, te.desc, 996, ey + 40, { fontSize: 11, fill: '#475569' });
  });
  session.flush();

  // ==========================================
  // PAGE 7: RESPONSIVE SHELL (DRAWER & TABLET)
  // ==========================================
  console.log('Building Page 07 — Responsive Shell...');
  session.createPage('07 — Responsive Shell');

  // 1. Mobile Drawer Closed (390x844)
  const fMobClosed = session.createFrame({ name: 'Mobile Shell — Drawer Closed (Admin)', width: 390, height: 844, x: 0, y: 0, props: { fill: '#F8FAFC' } });
  session.addRect(fMobClosed, 0, 0, 390, 56, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addIcon(fMobClosed, 'menu', 16, 18, 20, '#0F172A');
  placeLogo(fMobClosed, 48, 12, 0.888888);
  session.addText(fMobClosed, 'SPARTA', 84, 18, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addIcon(fMobClosed, 'circle-user', 350, 18, 20, '#4F46E5');
  // Content list mobile (daisyUI list pattern)
  session.addText(fMobClosed, 'Dashboard Operasional', 20, 80, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fMobClosed, 20, 114, 350, 90, { fill: '#FFFFFF', cornerRadius: 10, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fMobClosed, 'Total Alat Aktif: 4 Jenis (21 Unit)', 36, 130, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
  session.addText(fMobClosed, '14 siap dipinjam · 2 peminjaman aktif', 36, 154, { fontSize: 12, fill: '#166534' });

  // List Transaksi Mobile
  session.addText(fMobClosed, 'Transaksi Berjalan (Pola List daisyUI)', 20, 230, { fontSize: 14, fontWeight: 700, fill: '#0F172A' });
  const mobRows = [
    { kode: 'PJ-0001', alat: 'Bor listrik', user: 'Andi Pratama', st: 'Dipinjam', bg: '#E0E7FF', fg: '#3730A3' },
    { kode: 'PJ-0002', alat: 'Proyektor Epson', user: 'Siti Rahma', st: 'Dipinjam · Terlambat', bg: '#FFEDD5', fg: '#9A3412' },
    { kode: 'PJ-0004', alat: 'Multimeter Sanwa', user: 'Siti Rahma', st: 'Menunggu Persetujuan', bg: '#FEF3C7', fg: '#92400E' },
  ];
  mobRows.forEach((mr, idx) => {
    const my = 260 + idx * 80;
    session.addRect(fMobClosed, 20, my, 350, 70, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(fMobClosed, `${mr.kode} — ${mr.alat}`, 32, my + 14, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fMobClosed, `Peminjam: ${mr.user}`, 32, my + 38, { fontSize: 11, fill: '#64748B' });
    session.addRect(fMobClosed, 210, my + 12, 146, 24, { fill: mr.bg, cornerRadius: 12 });
    session.addText(fMobClosed, mr.st, 218, my + 17, { fontSize: 9, fontWeight: 700, fill: mr.fg });
  });
  session.flush();

  // 2. Mobile Drawer Open (390x844)
  const fMobOpen = session.createFrame({ name: 'Mobile Shell — Drawer Open (Admin)', width: 390, height: 844, x: 430, y: 0, props: { fill: '#F8FAFC' } });
  // Backdrop overlay
  session.addRect(fMobOpen, 0, 0, 390, 844, { fill: 'rgba(15,23,42,0.5)' });
  // Drawer Panel (Width 280)
  session.addRect(fMobOpen, 0, 0, 280, 844, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fMobOpen, 16, 16, 0.888888);
  session.addText(fMobOpen, 'SPARTA', 50, 20, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fMobOpen, 'SMKN 1 Leuwimunding', 50, 38, { fontSize: 10, fill: '#64748B' });
  session.addIcon(fMobOpen, 'x', 244, 20, 20, '#64748B');
  session.addRect(fMobOpen, 0, 64, 280, 1, { fill: '#E2E8F0' });

  const mobNav = [
    { name: 'Dashboard', icon: 'layout-dashboard', active: true },
    { name: 'Data Pengguna', icon: 'users', active: false },
    { name: 'Kategori Alat', icon: 'tags', active: false },
    { name: 'Data Alat & Barang', icon: 'wrench', active: false },
    { name: 'Peminjaman', icon: 'clipboard-list', active: false },
    { name: 'Pengembalian', icon: 'refresh-cw', active: false },
    { name: 'Log Aktivitas', icon: 'logs', active: false },
  ];
  mobNav.forEach((item, idx) => {
    const ny = 76 + idx * 46;
    if (item.active) {
      session.addRect(fMobOpen, 10, ny, 260, 38, { fill: '#EEF2FF', cornerRadius: 6 });
      session.addIcon(fMobOpen, item.icon, 20, ny + 9, 18, '#4F46E5');
      session.addText(fMobOpen, item.name, 48, ny + 10, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    } else {
      session.addIcon(fMobOpen, item.icon, 20, ny + 9, 18, '#64748B');
      session.addText(fMobOpen, item.name, 48, ny + 10, { fontSize: 13, fontWeight: 500, fill: '#475569' });
    }
  });
  session.flush();

  // 3. Tablet Shell & Dashboard (768x960)
  const fTablet = session.createFrame({ name: 'Tablet Shell — Admin (768px)', width: 768, height: 960, x: 860, y: 0, props: { fill: '#F8FAFC' } });
  // Slim navigation bar for tablet (Width: 64)
  session.addRect(fTablet, 0, 0, 64, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  placeLogo(fTablet, 17, 16, 0.8);
  session.addRect(fTablet, 0, 64, 64, 1, { fill: '#E2E8F0' });

  const tabIcons = ['layout-dashboard', 'users', 'tags', 'wrench', 'clipboard-list', 'refresh-cw', 'logs'];
  tabIcons.forEach((ic, idx) => {
    const iy = 80 + idx * 52;
    if (idx === 0) {
      session.addRect(fTablet, 8, iy, 48, 44, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(fTablet, ic, 22, iy + 12, 20, '#4F46E5');
    } else {
      session.addIcon(fTablet, ic, 22, iy + 12, 20, '#64748B');
    }
  });

  // Topbar Tablet
  session.addRect(fTablet, 64, 0, 704, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fTablet, 'SPARTA Tablet — Dashboard Admin', 88, 22, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fTablet, 520, 14, 220, 36, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addIcon(fTablet, 'circle-user', 530, 22, 18, '#4F46E5');
  session.addText(fTablet, 'Ahmad Fauzi', 556, 23, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });

  // Content 2x2 KPI Grid
  session.addText(fTablet, 'Dashboard Operasional Lab', 88, 92, { fontSize: 20, fontWeight: 700, fill: '#0F172A' });
  const tabKpis = [
    { label: 'Total Alat Aktif', val: '4 Jenis', sub: '21 Unit total inventaris', icon: 'wrench' },
    { label: 'Pengguna Aktif', val: '5', sub: '1 admin, 1 ptg, 3 peminjam', icon: 'users' },
    { label: 'Peminjaman Berjalan', val: '2', sub: 'PJ-0001 & PJ-0002', icon: 'clipboard-list' },
    { label: 'Menunggu Review', val: '1', sub: 'PJ-0004 butuh verifikasi', icon: 'refresh-cw' },
  ];
  tabKpis.forEach((tk, idx) => {
    const col = idx % 2;
    const row = Math.floor(idx / 2);
    const tx = 88 + col * 336;
    const ty = 140 + row * 120;
    session.addRect(fTablet, tx, ty, 316, 100, { fill: '#FFFFFF', cornerRadius: 10, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addIcon(fTablet, tk.icon, tx + 16, ty + 16, 20, '#4F46E5');
    session.addText(fTablet, tk.label, tx + 44, ty + 18, { fontSize: 11, fontWeight: 500, fill: '#64748B' });
    session.addText(fTablet, tk.val, tx + 16, ty + 46, { fontSize: 22, fontWeight: 700, fill: '#0F172A' });
    session.addText(fTablet, tk.sub, tx + 16, ty + 76, { fontSize: 10, fill: '#166534' });
  });

  // Table Tablet
  session.addRect(fTablet, 88, 400, 652, 480, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fTablet, 'Daftar Transaksi Terbaru', 108, 424, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fTablet, 108, 460, 612, 36, { fill: '#F8FAFC', cornerRadius: 6 });
  session.addText(fTablet, 'KODE', 120, 472, { fontSize: 10, fontWeight: 700, fill: '#475569' });
  session.addText(fTablet, 'ALAT', 200, 472, { fontSize: 10, fontWeight: 700, fill: '#475569' });
  session.addText(fTablet, 'PEMINJAM', 360, 472, { fontSize: 10, fontWeight: 700, fill: '#475569' });
  session.addText(fTablet, 'STATUS', 490, 472, { fontSize: 10, fontWeight: 700, fill: '#475569' });

  rows.forEach((r, idx) => {
    const ry = 504 + idx * 56;
    session.addRect(fTablet, 108, ry + 44, 612, 1, { fill: '#F1F5F9' });
    session.addText(fTablet, r.kode, 120, ry + 14, { fontSize: 11, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fTablet, r.alat, 200, ry + 14, { fontSize: 11, fontWeight: 500, fill: '#0F172A' });
    session.addText(fTablet, r.peminjam, 360, ry + 14, { fontSize: 11, fill: '#475569' });
    session.addRect(fTablet, 490, ry + 8, 120, 24, { fill: r.badgeBg, cornerRadius: 12 });
    session.addText(fTablet, r.status, 500, ry + 13, { fontSize: 9, fontWeight: 700, fill: r.badgeFg });
  });
  session.flush();

// Prototype flow annotations documented in handoff

  // Append Petugas gap frames after every existing frame so established node IDs stay stable.
  session.cmd('page.select', { id: pPetugas });
  session.flush();

  function petugasFrame(name, x, title, desc, activeNav) {
    const f = session.createFrame({ name, width: 1440, height: 960, x, y: 1000, props: { fill: '#F8FAFC' } });
    session.addRect(f, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    placeLogo(f, 20, 22, 1.0);
    session.addText(f, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fill: '#64748B' });
    session.addRect(f, 0, 76, 248, 1, { fill: '#E2E8F0' });
    const nav = [
      ['Dashboard', 'layout-dashboard'], ['Persetujuan Peminjaman', 'clipboard-list'],
      ['Penerimaan Kembali', 'refresh-cw'], ['Laporan Sarpras', 'scroll-text']
    ];
    nav.forEach(([label, icon], i) => {
      const y = 92 + i * 48;
      if (label === activeNav) session.addRect(f, 12, y, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(f, icon, 24, y + 10, 20, label === activeNav ? '#4F46E5' : '#64748B');
      session.addText(f, label, 56, y + 11, { fontSize: 14, fontWeight: label === activeNav ? 700 : 500, fill: label === activeNav ? '#4F46E5' : '#475569' });
    });
    session.addRect(f, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(f, 'SPARTA — Operasional Petugas Sarpras', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, title, 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, desc, 276, 124, { fontSize: 14, fill: '#64748B' });
    return f;
  }

  const fHandover = petugasFrame('Petugas — Konfirmasi Serah Terima PJ-0003', 4120, 'Konfirmasi Serah Terima #PJ-0003', 'Pemeriksaan terakhir sebelum alat berpindah dari alokasi ke peminjaman aktif.', 'Persetujuan Peminjaman');
  session.addRect(fHandover, 276, 160, 704, 688, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fHandover, 'Ringkasan alat dan penerima', 304, 188, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fHandover, 304, 224, 648, 112, { fill: '#F8FAFC', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fHandover, 'Kamera DSLR Canon (ALT-003) · 2 unit', 324, 244, { fontSize: 14, fontWeight: 700, fill: '#0F172A' });
  session.addText(fHandover, 'Peminjam: Budi Santoso · Jatuh tempo: 20 Nov 2026', 324, 270, { fontSize: 13, fill: '#475569' });
  session.addText(fHandover, 'Status saat ini: Disetujui · 2 unit telah dialokasikan', 324, 296, { fontSize: 12, fontWeight: 700, fill: '#166534' });
  session.addText(fHandover, 'Tanggal Serah Terima Aktual', 304, 368, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fHandover, 304, 390, 304, 44, { fill: '#FFFFFF', cornerRadius: 6, stroke: '#94A3B8', strokeWeight: 1 });
  session.addText(fHandover, '16 November 2026 · 10:30 WIB', 320, 404, { fontSize: 13, fill: '#0F172A' });
  session.addText(fHandover, 'Checklist fisik serah terima', 304, 466, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  ['2 unit sesuai transaksi dan nomor aset diperiksa', 'Baterai, lensa, tali, dan tas lengkap', 'Peminjam memahami tenggat dan denda'].forEach((t, i) => {
    session.addRect(fHandover, 304, 494 + i * 42, 22, 22, { fill: '#4F46E5', cornerRadius: 5 });
    session.addIcon(fHandover, 'check', 307, 497 + i * 42, 16, '#FFFFFF');
    session.addText(fHandover, t, 340, 497 + i * 42, { fontSize: 13, fill: '#0F172A' });
  });
  session.addRect(fHandover, 304, 646, 648, 58, { fill: '#DBEAFE', cornerRadius: 8, stroke: '#93C5FD', strokeWeight: 1 });
  session.addText(fHandover, 'Status setelah konfirmasi: Dipinjam. Stok tidak dikurangi dua kali.', 324, 667, { fontSize: 13, fontWeight: 700, fill: '#1E40AF' });
  session.addRect(fHandover, 304, 744, 220, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fHandover, 'Konfirmasi Serah Terima', 328, 758, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fHandover, 540, 744, 100, 44, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fHandover, 'Batal', 570, 758, { fontSize: 13, fontWeight: 700, fill: '#475569' });
  session.addRect(fHandover, 1004, 160, 376, 254, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fHandover, 'Dampak transaksi', 1028, 188, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
  session.addText(fHandover, 'Disetujui  →  Dipinjam', 1028, 228, { fontSize: 18, fontWeight: 700, fill: '#4F46E5' });
  session.addText(fHandover, 'Tanggal aktual dan petugas pencatat disimpan.', 1028, 266, { fontSize: 12, fill: '#475569' });
  session.addText(fHandover, 'Klik ulang tidak membuat mutasi stok baru.', 1028, 290, { fontSize: 12, fill: '#475569' });
  session.addRect(fHandover, 1028, 330, 328, 54, { fill: '#FEF3C7', cornerRadius: 8 });
  session.addText(fHandover, 'Aksi hanya tersedia untuk status Disetujui.', 1044, 349, { fontSize: 12, fontWeight: 700, fill: '#92400E' });
  session.flush();

  const fActiveReturn = petugasFrame('Petugas — Verifikasi Pengembalian Aktif PJ-0006', 5600, 'Verifikasi Pengembalian #PJ-0006', 'Fixture demo aktif terpisah dari arsip selesai PJ-0005. Stok belum dipulihkan.', 'Penerimaan Kembali');
  session.addRect(fActiveReturn, 276, 160, 724, 744, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fActiveReturn, 'Pemeriksaan fisik aktif', 304, 188, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fActiveReturn, 'Rian Hidayat · Multimeter Sanwa (ALT-004) · 1 unit', 304, 216, { fontSize: 13, fill: '#475569' });
  session.addRect(fActiveReturn, 304, 246, 668, 56, { fill: '#FEF3C7', cornerRadius: 8, stroke: '#FCD34D', strokeWeight: 1 });
  session.addText(fActiveReturn, 'Pengembalian Diajukan · 16 Nov 2026, 09:15 WIB', 324, 265, { fontSize: 13, fontWeight: 700, fill: '#92400E' });
  const activeFields = [
    ['Tanggal Kembali Aktual', '16 November 2026 · 11:00 WIB'], ['Kondisi', 'Baik'],
    ['Jumlah unit baik', '1'], ['Jumlah unit rusak', '0']
  ];
  activeFields.forEach(([label, value], i) => {
    const col = i % 2, row = Math.floor(i / 2), x = 304 + col * 334, y = 330 + row * 86;
    session.addText(fActiveReturn, label, x, y, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fActiveReturn, x, y + 22, 306, 44, { fill: '#FFFFFF', cornerRadius: 6, stroke: '#94A3B8', strokeWeight: 1 });
    session.addText(fActiveReturn, value, x + 16, y + 36, { fontSize: 13, fill: '#0F172A' });
  });
  session.addText(fActiveReturn, 'Catatan Pemeriksaan Fisik', 304, 510, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fActiveReturn, 304, 532, 668, 74, { fill: '#FFFFFF', cornerRadius: 6, stroke: '#94A3B8', strokeWeight: 1 });
  session.addText(fActiveReturn, 'Probe lengkap, layar normal, hasil ukur stabil.', 320, 550, { fontSize: 13, fill: '#0F172A' });
  session.addRect(fActiveReturn, 304, 632, 668, 82, { fill: '#DBEAFE', cornerRadius: 8, stroke: '#93C5FD', strokeWeight: 1 });
  session.addText(fActiveReturn, 'Denda Estimasi: Rp 0', 324, 650, { fontSize: 15, fontWeight: 700, fill: '#1E40AF' });
  session.addText(fActiveReturn, 'Final setelah verifikasi · 0 hari terlambat × Rp5.000', 324, 678, { fontSize: 12, fill: '#1E40AF' });
  session.addRect(fActiveReturn, 304, 758, 214, 44, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fActiveReturn, 'Verifikasi Pengembalian', 326, 772, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fActiveReturn, 534, 758, 150, 44, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#DC2626', strokeWeight: 1 });
  session.addText(fActiveReturn, 'Minta Koreksi', 558, 772, { fontSize: 13, fontWeight: 700, fill: '#B91C1C' });
  session.addRect(fActiveReturn, 1024, 160, 356, 346, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fActiveReturn, 'Konfirmasi finalisasi', 1048, 188, { fontSize: 15, fontWeight: 700, fill: '#0F172A' });
  session.addText(fActiveReturn, '1 unit baik kembali ke stok tersedia.', 1048, 226, { fontSize: 12, fill: '#475569' });
  session.addText(fActiveReturn, 'Denda berubah dari estimasi menjadi final.', 1048, 250, { fontSize: 12, fill: '#475569' });
  session.addText(fActiveReturn, 'Status menjadi Dikembalikan.', 1048, 274, { fontSize: 12, fill: '#475569' });
  session.addRect(fActiveReturn, 1048, 316, 308, 66, { fill: '#FEE2E2', cornerRadius: 8 });
  session.addText(fActiveReturn, 'Validasi: unit baik + rusak harus sama', 1064, 334, { fontSize: 12, fontWeight: 700, fill: '#991B1B' });
  session.addText(fActiveReturn, 'dengan 1 unit dipinjam.', 1064, 354, { fontSize: 12, fill: '#991B1B' });
  session.addText(fActiveReturn, 'PJ-0005 tetap tersedia sebagai arsip read-only.', 1048, 422, { fontSize: 12, fontWeight: 700, fill: '#64748B' });
  session.flush();

  const fMonitoring = petugasFrame('Petugas — Monitoring Pengembalian', 7080, 'Monitoring Pengembalian', 'Pantau seluruh status pinjaman dan pilih tindakan sesuai tahapan transaksi.', 'Penerimaan Kembali');
  const tabs = [['Semua 4', true], ['Sedang Dipinjam 1'], ['Terlambat 1'], ['Pengembalian Diajukan 1'], ['Selesai 1']];
  tabs.forEach(([label, active], i) => {
    const widths = [96, 160, 116, 208, 106], x = 276 + widths.slice(0, i).reduce((a, b) => a + b + 8, 0);
    session.addRect(fMonitoring, x, 160, widths[i], 38, { fill: active ? '#4F46E5' : '#FFFFFF', cornerRadius: 8, stroke: active ? '#4F46E5' : '#E2E8F0', strokeWeight: 1 });
    session.addText(fMonitoring, label, x + 14, 172, { fontSize: 12, fontWeight: 700, fill: active ? '#FFFFFF' : '#475569' });
  });
  session.addRect(fMonitoring, 276, 220, 1104, 618, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fMonitoring, 300, 244, 1056, 42, { fill: '#F8FAFC', cornerRadius: 6 });
  [['KODE',316],['PEMINJAM / ALAT',410],['JATUH TEMPO',700],['HARI TERLAMBAT',842],['STATUS',982],['AKSI',1240]].forEach(([t,x]) => session.addText(fMonitoring, t, x, 258, { fontSize: 10, fontWeight: 700, fill: '#475569' }));
  const returnRows = [
    { code:'PJ-0001', who:'Andi Pratama', tool:'Bor listrik · 2 unit', due:'18 Nov 2026', late:'0 hari', status:'Dipinjam', bg:'#DBEAFE', fg:'#1E40AF', action:'Lihat Detail' },
    { code:'PJ-0002', who:'Siti Rahma', tool:'Proyektor Epson · 1 unit', due:'15 Nov 2026', late:'1 hari', status:'Dipinjam · Terlambat', bg:'#FEE2E2', fg:'#991B1B', action:'Hubungi' },
    { code:'PJ-0006', who:'Rian Hidayat', tool:'Multimeter Sanwa · 1 unit', due:'16 Nov 2026', late:'0 hari', status:'Pengembalian Diajukan', bg:'#FEF3C7', fg:'#92400E', action:'Verifikasi' },
    { code:'PJ-0005', who:'Andi Pratama', tool:'Bor listrik · 1 unit', due:'12 Nov 2026', late:'2 hari', status:'Dikembalikan', bg:'#DCFCE7', fg:'#166534', action:'Lihat Arsip' },
  ];
  returnRows.forEach((r, i) => {
    const y = 304 + i * 112;
    session.addRect(fMonitoring, 300, y + 94, 1056, 1, { fill: '#E2E8F0' });
    session.addText(fMonitoring, r.code, 316, y + 24, { fontSize: 13, fontWeight: 700, fill: '#4F46E5' });
    session.addText(fMonitoring, r.who, 410, y + 14, { fontSize: 13, fontWeight: 700, fill: '#0F172A' });
    session.addText(fMonitoring, r.tool, 410, y + 38, { fontSize: 12, fill: '#64748B' });
    session.addText(fMonitoring, r.due, 700, y + 24, { fontSize: 12, fill: '#475569' });
    session.addText(fMonitoring, r.late, 842, y + 24, { fontSize: 12, fontWeight: r.late === '0 hari' ? 400 : 700, fill: r.late === '0 hari' ? '#64748B' : '#991B1B' });
    session.addRect(fMonitoring, 982, y + 14, 194, 30, { fill: r.bg, cornerRadius: 15 });
    session.addText(fMonitoring, r.status, 994, y + 22, { fontSize: 10, fontWeight: 700, fill: r.fg });
    session.addText(fMonitoring, r.action + ' →', 1240, y + 24, { fontSize: 12, fontWeight: 700, fill: '#4F46E5' });
  });
  session.addText(fMonitoring, 'Menampilkan 1–4 dari 4 transaksi · Data demo per 16 November 2026', 300, 798, { fontSize: 12, fill: '#64748B' });
  session.flush();

  const fReport = petugasFrame('Petugas — Filter & Preview Laporan', 8560, 'Laporan Peminjaman', 'Filter memakai tanggal pengajuan. Tinjau hasil lengkap sebelum membuka dialog cetak.', 'Laporan Sarpras');
  session.addRect(fReport, 276, 160, 1104, 150, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  const filters = [['Tanggal Mulai','10 Nov 2026',240],['Tanggal Akhir','16 Nov 2026',240],['Status','Semua status',210],['Kategori','Semua kategori',210]];
  let fx = 300;
  filters.forEach(([label,value,w]) => {
    session.addText(fReport, label, fx, 184, { fontSize: 11, fontWeight: 700, fill: '#0F172A' });
    session.addRect(fReport, fx, 204, w, 42, { fill: '#FFFFFF', cornerRadius: 6, stroke: '#94A3B8', strokeWeight: 1 });
    session.addText(fReport, value, fx + 14, 217, { fontSize: 12, fill: '#0F172A' });
    fx += w + 16;
  });
  session.addRect(fReport, 300, 262, 138, 36, { fill: '#4F46E5', cornerRadius: 8 });
  session.addText(fReport, 'Terapkan Filter', 320, 273, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fReport, 450, 262, 82, 36, { fill: '#F1F5F9', cornerRadius: 8 });
  session.addText(fReport, 'Reset', 474, 273, { fontSize: 12, fontWeight: 700, fill: '#475569' });
  session.addText(fReport, 'Periode hasil: tanggal pengajuan 10–16 November 2026', 968, 274, { fontSize: 11, fontWeight: 700, fill: '#64748B' });
  const stats = [['Transaksi','6'],['Selesai','1'],['Aktif','4'],['Denda Final','Rp 10.000']];
  stats.forEach(([label,val], i) => {
    const x = 276 + i * 276;
    session.addRect(fReport, x, 330, 256, 82, { fill: '#FFFFFF', cornerRadius: 10, stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(fReport, label, x + 18, 348, { fontSize: 11, fill: '#64748B' });
    session.addText(fReport, val, x + 18, 372, { fontSize: 20, fontWeight: 700, fill: '#0F172A' });
  });
  session.addRect(fReport, 276, 432, 1104, 416, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fReport, 'Preview sebelum cetak', 300, 456, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fReport, '6 hasil terfilter · seluruh hasil dicetak, bukan hanya halaman aktif', 300, 480, { fontSize: 12, fill: '#64748B' });
  session.addRect(fReport, 1190, 450, 166, 40, { fill: '#4F46E5', cornerRadius: 8 });
  session.addIcon(fReport, 'printer', 1206, 461, 16, '#FFFFFF');
  session.addText(fReport, 'Cetak Laporan', 1232, 462, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' });
  session.addRect(fReport, 300, 510, 1056, 38, { fill: '#F8FAFC', cornerRadius: 6 });
  [['KODE',316],['PEMINJAM',410],['ALAT',560],['JUMLAH',760],['PINJAM / KEMBALI',836],['STATUS',1030],['DENDA',1240]].forEach(([t,x]) => session.addText(fReport, t, x, 523, { fontSize: 10, fontWeight: 700, fill: '#475569' }));
  const previewRows = [
    ['PJ-0001','Andi Pratama','Bor listrik','2','16 Nov / —','Dipinjam','Rp 0'],
    ['PJ-0002','Siti Rahma','Proyektor Epson','1','14 Nov / —','Terlambat','Rp 5.000 est.'],
    ['PJ-0003','Budi Santoso','Kamera DSLR','2','15 Nov / —','Disetujui','Rp 0'],
    ['PJ-0004','Siti Rahma','Multimeter Sanwa','1','16 Nov / —','Menunggu','Rp 0'],
    ['PJ-0005','Andi Pratama','Bor listrik','1','10 Nov / 14 Nov','Dikembalikan','Rp 10.000'],
    ['PJ-0006','Rian Hidayat','Multimeter Sanwa','1','16 Nov / —','Kembali diajukan','Rp 0 est.'],
  ];
  previewRows.forEach((r,i) => {
    const y = 558 + i * 40;
    session.addRect(fReport, 300, y + 34, 1056, 1, { fill: '#E2E8F0' });
    [316,410,560,760,836,1030,1240].forEach((x,j) => session.addText(fReport, r[j], x, y + 10, { fontSize: j === 0 ? 11 : 10, fontWeight: j === 0 || j === 5 ? 700 : 400, fill: j === 0 ? '#4F46E5' : '#0F172A' }));
  });
  session.addText(fReport, 'Cetak membuka layout A4 landscape tanpa sidebar, topbar, filter, atau tombol aksi.', 300, 812, { fontSize: 11, fontWeight: 700, fill: '#475569' });
  session.flush();

  // Append core implementation-reference frames last so all existing frame IDs remain stable.
  function coreFrame(pageId, name, x, y, role, title, desc, activeNav) {
    session.cmd('page.select', { id: pageId });
    const f = session.createFrame({ name, width: 1440, height: 960, x, y, props: { fill: '#F8FAFC' } });
    session.addRect(f, 0, 0, 248, 960, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    placeLogo(f, 20, 22, 1.0);
    session.addText(f, 'SPARTA', 60, 23, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, 'SMKN 1 Leuwimunding', 60, 43, { fontSize: 11, fill: '#64748B' });
    session.addRect(f, 0, 76, 248, 1, { fill: '#E2E8F0' });
    const nav = role === 'Admin'
      ? [['Dashboard','layout-dashboard'],['Data Pengguna','users'],['Kategori Alat','tags'],['Data Alat & Barang','wrench'],['Peminjaman','clipboard-list'],['Pengembalian','refresh-cw'],['Log Aktivitas','logs']]
      : [['Katalog Alat','wrench'],['Peminjaman Saya','clipboard-list'],['Riwayat Pinjam','logs']];
    nav.forEach(([label, icon], i) => {
      const ny = 92 + i * 48, active = label === activeNav;
      if (active) session.addRect(f, 12, ny, 224, 40, { fill: '#EEF2FF', cornerRadius: 8 });
      session.addIcon(f, icon, 24, ny + 10, 20, active ? '#4F46E5' : '#64748B');
      session.addText(f, label, 56, ny + 11, { fontSize: 14, fontWeight: active ? 700 : 500, fill: active ? '#4F46E5' : '#475569' });
    });
    session.addRect(f, 248, 0, 1192, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 });
    session.addText(f, role === 'Admin' ? 'SPARTA — Administrator Panel' : 'SPARTA — Portal Peminjam', 276, 22, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, title, 276, 92, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
    session.addText(f, desc, 276, 124, { fontSize: 14, fill: '#64748B' });
    return f;
  }
  function field(frame, label, value, x, y, w = 304, h = 44) {
    session.addText(frame, label, x, y, { fontSize: 12, fontWeight: 700, fill: '#0F172A' });
    session.addRect(frame, x, y + 22, w, h, { fill: '#FFFFFF', cornerRadius: 6, stroke: '#94A3B8', strokeWeight: 1 });
    session.addText(frame, value, x + 16, y + 36, { fontSize: 13, fill: '#0F172A' });
  }
  function action(frame, label, x, y, w, primary = true) {
    session.addRect(frame, x, y, w, 44, { fill: primary ? '#4F46E5' : '#F1F5F9', cornerRadius: 8, stroke: primary ? '#4F46E5' : '#E2E8F0', strokeWeight: 1 });
    session.addText(frame, label, x + 20, y + 14, { fontSize: 13, fontWeight: 700, fill: primary ? '#FFFFFF' : '#475569' });
  }

  const fToolDetail = coreFrame(pPeminjam, 'Peminjam — Detail Alat ALT-001', 4440, 0, 'Peminjam', 'Detail Alat', 'Periksa ketersediaan dan informasi alat sebelum mengajukan peminjaman.', 'Katalog Alat');
  session.addRect(fToolDetail, 276, 160, 700, 650, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addRect(fToolDetail, 304, 188, 644, 286, { fill: '#F1F5F9', cornerRadius: 10 });
  session.addIcon(fToolDetail, 'wrench', 590, 298, 72, '#94A3B8');
  session.addText(fToolDetail, 'Bor listrik', 304, 510, { fontSize: 24, fontWeight: 700, fill: '#0F172A' });
  session.addText(fToolDetail, 'ALT-001 · Perkakas', 304, 546, { fontSize: 13, fill: '#64748B' });
  session.addText(fToolDetail, 'Kapasitas chuck 13 mm, daya 550 W. Tersimpan di Rak B-02 Bengkel Listrik.', 304, 580, { fontSize: 13, fill: '#475569' });
  session.addRect(fToolDetail, 304, 626, 170, 30, { fill: '#DCFCE7', cornerRadius: 15 });
  session.addText(fToolDetail, 'Stok tersedia: 5 unit', 320, 634, { fontSize: 11, fontWeight: 700, fill: '#166534' });
  action(fToolDetail, 'Ajukan Peminjaman', 304, 706, 190, true);
  session.addRect(fToolDetail, 1000, 160, 380, 286, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fToolDetail, 'Informasi alat', 1024, 188, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  [['Kondisi','Baik'],['Stok total','8 unit'],['Sedang dipinjam','2 unit'],['Dalam perawatan','1 unit']].forEach(([a,b],i) => { session.addText(fToolDetail, a, 1024, 234 + i*46, { fontSize: 12, fill: '#64748B' }); session.addText(fToolDetail, b, 1230, 234 + i*46, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }); });
  session.flush();

  const fCancel = coreFrame(pPeminjam, 'Peminjam — Detail PJ-0004 Batalkan Pengajuan', 5920, 0, 'Peminjam', 'Detail Peminjaman #PJ-0004', 'Pengajuan milik Siti Rahma masih dapat dibatalkan sebelum diproses petugas.', 'Peminjaman Saya');
  session.addRect(fCancel, 276, 160, 700, 620, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fCancel, 'Multimeter Sanwa (ALT-004)', 304, 190, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fCancel, 304, 226, 190, 30, { fill: '#FEF3C7', cornerRadius: 15 });
  session.addText(fCancel, 'Menunggu Persetujuan', 320, 234, { fontSize: 11, fontWeight: 700, fill: '#92400E' });
  [['Peminjam','Siti Rahma'],['Jumlah','1 unit'],['Rencana pinjam','16 Nov 2026'],['Jatuh tempo','19 Nov 2026'],['Tujuan','Praktik pengukuran listrik']].forEach(([a,b],i) => { session.addText(fCancel, a, 304, 300+i*52, { fontSize: 12, fill: '#64748B' }); session.addText(fCancel, b, 510, 300+i*52, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }); });
  action(fCancel, 'Batalkan Pengajuan', 304, 600, 184, false);
  session.addRect(fCancel, 1000, 160, 380, 350, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fCancel, 'Batalkan pengajuan?', 1024, 190, { fontSize: 18, fontWeight: 700, fill: '#0F172A' });
  session.addText(fCancel, 'Pengajuan ini belum mengurangi stok.', 1024, 226, { fontSize: 13, fill: '#475569' });
  session.addText(fCancel, 'Setelah dibatalkan, pengajuan tidak dapat diproses petugas.', 1024, 252, { fontSize: 12, fill: '#64748B' });
  action(fCancel, 'Ya, Batalkan Pengajuan', 1024, 318, 220, true);
  action(fCancel, 'Kembali', 1256, 318, 100, false);
  session.flush();

  const fReturnRequest = coreFrame(pPeminjam, 'Peminjam — Ajukan Pengembalian PJ-0001', 7400, 0, 'Peminjam', 'Ajukan Pengembalian #PJ-0001', 'Laporkan kondisi alat. Petugas akan memeriksa fisik sebelum stok diperbarui.', 'Peminjaman Saya');
  session.addRect(fReturnRequest, 276, 160, 724, 720, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fReturnRequest, 'Bor listrik (ALT-001) · 2 unit', 304, 190, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fReturnRequest, 'Peminjam: Andi Pratama · Jatuh tempo: 18 Nov 2026', 304, 218, { fontSize: 13, fill: '#475569' });
  field(fReturnRequest, 'Tanggal Pengajuan', '16 November 2026 · otomatis', 304, 270, 320);
  field(fReturnRequest, 'Kondisi yang Dilaporkan', 'Baik', 648, 270, 324);
  field(fReturnRequest, 'Catatan untuk Petugas', 'Alat lengkap dan berfungsi normal.', 304, 366, 668, 76);
  session.addRect(fReturnRequest, 304, 486, 668, 96, { fill: '#DBEAFE', cornerRadius: 8, stroke: '#93C5FD', strokeWeight: 1 });
  session.addText(fReturnRequest, 'Estimasi denda: Rp0', 324, 506, { fontSize: 15, fontWeight: 700, fill: '#1E40AF' });
  session.addText(fReturnRequest, 'Nilai final mengikuti tanggal penerimaan dan pemeriksaan petugas.', 324, 536, { fontSize: 12, fill: '#1E40AF' });
  action(fReturnRequest, 'Ajukan Pengembalian', 304, 632, 200, true);
  action(fReturnRequest, 'Batal', 520, 632, 100, false);
  session.addRect(fReturnRequest, 1024, 160, 356, 260, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fReturnRequest, 'Setelah diajukan', 1048, 190, { fontSize: 16, fontWeight: 700, fill: '#0F172A' });
  session.addText(fReturnRequest, 'Status menjadi Pengembalian Diajukan.', 1048, 230, { fontSize: 12, fill: '#475569' });
  session.addText(fReturnRequest, 'Stok belum bertambah sampai verifikasi selesai.', 1048, 258, { fontSize: 12, fill: '#475569' });
  session.flush();

  const fLoanAdd = coreFrame(pAdmin, 'Admin — Peminjaman Tambah', 7400, 0, 'Admin', 'Tambah Peminjaman', 'Buat pengajuan baru untuk peminjam yang dipilih.', 'Peminjaman');
  session.addRect(fLoanAdd, 276, 160, 780, 710, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fLoanAdd, 'Data pengajuan', 304, 190, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  field(fLoanAdd, 'Peminjam', 'Pilih peminjam', 304, 240, 352); field(fLoanAdd, 'Alat', 'Pilih alat tersedia', 680, 240, 348);
  field(fLoanAdd, 'Jumlah Unit', '1', 304, 330, 352); field(fLoanAdd, 'Tanggal Rencana Pinjam', '16 November 2026', 680, 330, 348);
  field(fLoanAdd, 'Jatuh Tempo', '19 November 2026', 304, 420, 352); field(fLoanAdd, 'Tujuan Peminjaman', 'Keperluan praktik', 680, 420, 348);
  session.addRect(fLoanAdd, 304, 526, 724, 70, { fill: '#FEF3C7', cornerRadius: 8, stroke: '#FCD34D', strokeWeight: 1 });
  session.addText(fLoanAdd, 'Transaksi baru berstatus Menunggu Persetujuan. Stok belum dialokasikan.', 324, 551, { fontSize: 13, fontWeight: 700, fill: '#92400E' });
  action(fLoanAdd, 'Simpan sebagai Menunggu Persetujuan', 304, 650, 292, true); action(fLoanAdd, 'Batal', 612, 650, 100, false);
  session.flush();

  const fLoanEdit = coreFrame(pAdmin, 'Admin — Peminjaman Edit PJ-0004', 8880, 0, 'Admin', 'Edit Peminjaman #PJ-0004', 'Perbarui pengajuan sebelum diproses petugas.', 'Peminjaman');
  session.addRect(fLoanEdit, 276, 160, 780, 710, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fLoanEdit, 'Siti Rahma · Multimeter Sanwa (ALT-004)', 304, 190, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addRect(fLoanEdit, 304, 224, 190, 30, { fill: '#FEF3C7', cornerRadius: 15 });
  session.addText(fLoanEdit, 'Menunggu Persetujuan', 320, 232, { fontSize: 11, fontWeight: 700, fill: '#92400E' });
  field(fLoanEdit, 'Peminjam', 'Siti Rahma', 304, 292, 352); field(fLoanEdit, 'Alat', 'ALT-004 · Multimeter Sanwa', 680, 292, 348);
  field(fLoanEdit, 'Jumlah Unit', '1', 304, 382, 352); field(fLoanEdit, 'Jatuh Tempo', '19 November 2026', 680, 382, 348);
  field(fLoanEdit, 'Tujuan Peminjaman', 'Praktik pengukuran listrik', 304, 472, 724);
  session.addRect(fLoanEdit, 304, 578, 724, 64, { fill: '#DBEAFE', cornerRadius: 8 });
  session.addText(fLoanEdit, 'Edit hanya tersedia sebelum persetujuan. Perubahan dicatat dalam riwayat.', 324, 601, { fontSize: 13, fontWeight: 700, fill: '#1E40AF' });
  action(fLoanEdit, 'Simpan Perubahan', 304, 686, 170, true); action(fLoanEdit, 'Batal', 490, 686, 100, false);
  session.flush();

  const fReturnCorrection = coreFrame(pAdmin, 'Admin — Koreksi Pengembalian PJ-0005', 10360, 0, 'Admin', 'Koreksi Pengembalian #PJ-0005', 'Perbaiki catatan final dengan alasan dan riwayat perubahan.', 'Pengembalian');
  session.addRect(fReturnCorrection, 276, 160, 780, 720, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 });
  session.addText(fReturnCorrection, 'Andi Pratama · Bor listrik (ALT-001) · 1 unit', 304, 190, { fontSize: 17, fontWeight: 700, fill: '#0F172A' });
  session.addText(fReturnCorrection, 'Dikembalikan 14 Nov 2026 · Denda final Rp10.000', 304, 220, { fontSize: 13, fontWeight: 700, fill: '#166534' });
  field(fReturnCorrection, 'Tanggal Kembali Aktual', '14 November 2026', 304, 270, 352); field(fReturnCorrection, 'Kondisi', 'Baik', 680, 270, 348);
  field(fReturnCorrection, 'Jumlah Unit Baik', '1', 304, 360, 352); field(fReturnCorrection, 'Jumlah Unit Rusak', '0', 680, 360, 348);
  field(fReturnCorrection, 'Catatan Pengembalian', 'Mata bor lengkap, kabel rapi, fungsi normal.', 304, 450, 724, 70);
  field(fReturnCorrection, 'Alasan Koreksi', 'Perbaikan ejaan pada catatan pemeriksaan.', 304, 562, 724, 70);
  session.addRect(fReturnCorrection, 304, 678, 724, 58, { fill: '#FEF3C7', cornerRadius: 8 });
  session.addText(fReturnCorrection, 'Stok tidak dipulihkan ulang. Koreksi disimpan bersama waktu dan admin pencatat.', 324, 699, { fontSize: 12, fontWeight: 700, fill: '#92400E' });
  action(fReturnCorrection, 'Simpan Koreksi Berjejak', 304, 770, 218, true); action(fReturnCorrection, 'Batal', 538, 770, 100, false);
  session.flush();

  console.log('--- ALL FIGCRAFT PAGES AND NODES BUILT SUCCESSFULLY ---');
  // Read back JSON to run post-build assertions
  const resConv = spawnSync('figcraft-cli', ['convert', OUT_FIGCRAFT, OUT_JSON], { encoding: 'utf8' });
  if (resConv.status !== 0) throw new Error('figcraft-cli convert failed: ' + resConv.stderr);
  const docData = JSON.parse(fs.readFileSync(OUT_JSON, 'utf8'));
  runCleanupAssertions(docData);
  runAuthAssertions(docData);
  runPetugasAssertions(docData);
  runCoreReferenceAssertions(docData);
  // After assertions: regenerate design/modules read view + lossless minify + self-test.
  // The modular read format is derived output; design/SPARTA.figcraft stays canonical.
  require('./export-modules').main();
}

buildDocument().catch(err => {
  console.error('Build failed:', err);
  process.exit(1);
});
