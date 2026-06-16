<?php
include 'koneksi.php';

$id_stasiun  = $_GET['stasiun']    ?? '96633';
$tab_aktif   = $_GET['tab']        ?? 'dashboard';
$filter_dari = $_GET['dari']       ?? '';
$filter_sampai = $_GET['sampai']   ?? '';
$sort_order  = $_GET['sort']       ?? 'ASC'; // ASC = terlama dulu, DESC = terbaru dulu

$nama_stasiun = [
    '96633' => 'Balikpapan',
    '96607' => 'Samarinda',
    '96529' => 'Berau'
];

// ── Statistik ringkasan
$stat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                AS jumlah_data,
        ROUND(AVG(tavg),  2)   AS rata_suhu,
        ROUND(SUM(rr),    2)   AS total_hujan,
        ROUND(AVG(rh_avg),2)   AS rata_rh,
        MAX(tx)                AS suhu_maks,
        MIN(tn)                AS suhu_min
    FROM pengamatan
    WHERE id_stasiun = '$id_stasiun'
"));

// ── Filter tanggal
$where_tanggal = "";
if ($filter_dari && $filter_sampai) {
    $fd = mysqli_real_escape_string($conn, $filter_dari);
    $fs2 = mysqli_real_escape_string($conn, $filter_sampai);
    $where_tanggal = " AND tanggal BETWEEN '$fd' AND '$fs2'";
} elseif ($filter_dari) {
    $fd = mysqli_real_escape_string($conn, $filter_dari);
    $where_tanggal = " AND tanggal >= '$fd'";
} elseif ($filter_sampai) {
    $fs2 = mysqli_real_escape_string($conn, $filter_sampai);
    $where_tanggal = " AND tanggal <= '$fs2'";
}

$order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';

$data = mysqli_query($conn, "
    SELECT * FROM pengamatan
    WHERE id_stasiun = '$id_stasiun' $where_tanggal
    ORDER BY tanggal $order
");

$tanggal=[]; $suhu=[]; $hujan=[]; $rh=[]; $rows=[];
while ($row = mysqli_fetch_assoc($data)) {
    $tanggal[] = $row['tanggal'];
    $suhu[]    = $row['tavg'];
    $hujan[]   = $row['rr'] !== null ? floatval($row['rr']) : 0;
    $rh[]      = $row['rh_avg'];
    $rows[]    = $row;
}

// ── GROUP BY per stasiun
$grup = mysqli_query($conn, "
    SELECT
        s.kota,
        ROUND(AVG(p.tavg),  2)  AS rata_suhu,
        ROUND(SUM(p.rr),    2)  AS total_hujan,
        ROUND(AVG(p.rh_avg),2)  AS rata_rh,
        MAX(p.tx)               AS suhu_maks,
        MIN(p.tn)               AS suhu_min
    FROM pengamatan p
    JOIN stasiun s ON p.id_stasiun = s.id_stasiun
    GROUP BY s.kota
    ORDER BY rata_suhu DESC
");
$grup_rows = [];
while ($r = mysqli_fetch_assoc($grup)) $grup_rows[] = $r;

// ── JOIN + kategori hujan
$kat_res = mysqli_query($conn, "
    SELECT
        s.kota,
        p.tanggal,
        p.tavg,
        p.rr,
        p.id_pengamatan,
        p.tn, p.tx, p.rh_avg, p.ss, p.ff_x, p.ddd_x, p.ff_avg, p.ddd_car,
        CASE
            WHEN p.rr IS NULL THEN 'Tidak Terukur'
            WHEN p.rr = 0     THEN 'Tidak Hujan'
            WHEN p.rr <= 20   THEN 'Ringan'
            WHEN p.rr <= 50   THEN 'Sedang'
            WHEN p.rr <= 100  THEN 'Lebat'
            ELSE 'Sangat Lebat'
        END AS kategori_hujan
    FROM pengamatan p
    JOIN stasiun s ON p.id_stasiun = s.id_stasiun
    WHERE p.id_stasiun = '$id_stasiun' $where_tanggal
    ORDER BY p.tanggal $order
");
$kat_rows = [];
$kat_label_chart = []; $kat_count_chart = [];
$kat_count = [];
while ($r = mysqli_fetch_assoc($kat_res)) {
    $kat_rows[] = $r;
    $kat_count[$r['kategori_hujan']] = ($kat_count[$r['kategori_hujan']] ?? 0) + 1;
}
foreach ($kat_count as $k => $v) { $kat_label_chart[] = $k; $kat_count_chart[] = $v; }

// ── Data semua stasiun
$stasiun_list = mysqli_query($conn, "SELECT * FROM stasiun ORDER BY nama_stasiun");
$stasiun_rows = [];
while ($r = mysqli_fetch_assoc($stasiun_list)) $stasiun_rows[] = $r;

// ── CRUD Handler
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {

    if ($_POST['aksi'] === 'tambah') {
        $fs  = mysqli_real_escape_string($conn, $_POST['id_stasiun']);
        $ft  = mysqli_real_escape_string($conn, $_POST['tanggal']);
        $ftn = floatval($_POST['tn']);
        $ftx = floatval($_POST['tx']);
        $fta = floatval($_POST['tavg']);
        $frh = floatval($_POST['rh_avg']);
        $frr = $_POST['rr'] === '' ? 'NULL' : floatval($_POST['rr']);
        $fss = floatval($_POST['ss']);
        $ffx = floatval($_POST['ff_x']);
        $fdx = floatval($_POST['ddd_x']);
        $ffa = floatval($_POST['ff_avg']);
        $fdc = mysqli_real_escape_string($conn, $_POST['ddd_car']);

        $q = "INSERT INTO pengamatan
                (id_stasiun,tanggal,tn,tx,tavg,rh_avg,rr,ss,ff_x,ddd_x,ff_avg,ddd_car)
              VALUES
                ('$fs','$ft',$ftn,$ftx,$fta,$frh,$frr,$fss,$ffx,$fdx,$ffa,'$fdc')";
        if (mysqli_query($conn, $q))
            $pesan = '<div class="notif ok">✅ Data berhasil ditambahkan!</div>';
        else
            $pesan = '<div class="notif err">❌ Gagal: '.mysqli_error($conn).'</div>';
    }

    if ($_POST['aksi'] === 'edit') {
        $eid = intval($_POST['id_pengamatan']);
        $fs  = mysqli_real_escape_string($conn, $_POST['id_stasiun']);
        $ft  = mysqli_real_escape_string($conn, $_POST['tanggal']);
        $ftn = floatval($_POST['tn']);
        $ftx = floatval($_POST['tx']);
        $fta = floatval($_POST['tavg']);
        $frh = floatval($_POST['rh_avg']);
        $frr = $_POST['rr'] === '' ? 'NULL' : floatval($_POST['rr']);
        $fss = floatval($_POST['ss']);
        $ffx = floatval($_POST['ff_x']);
        $fdx = floatval($_POST['ddd_x']);
        $ffa = floatval($_POST['ff_avg']);
        $fdc = mysqli_real_escape_string($conn, $_POST['ddd_car']);
        $q = "UPDATE pengamatan SET
                id_stasiun='$fs', tanggal='$ft', tn=$ftn, tx=$ftx,
                tavg=$fta, rh_avg=$frh, rr=$frr, ss=$fss,
                ff_x=$ffx, ddd_x=$fdx, ff_avg=$ffa, ddd_car='$fdc'
              WHERE id_pengamatan=$eid";
        if (mysqli_query($conn, $q))
            $pesan = '<div class="notif ok">✏️ Data berhasil diperbarui!</div>';
        else
            $pesan = '<div class="notif err">❌ Gagal update: '.mysqli_error($conn).'</div>';
    }

    if ($_POST['aksi'] === 'hapus') {
        $del_id = intval($_POST['id_pengamatan']);
        mysqli_query($conn, "DELETE FROM pengamatan WHERE id_pengamatan = $del_id");
        $pesan = '<div class="notif ok">🗑️ Data berhasil dihapus.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Cuaca Kalimantan Timur</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ════ MODERN DARK PROFESSIONAL THEME ════ */
:root {
  --bg:       #0f1117;
  --surface:  #161b27;
  --card:     #1c2233;
  --card2:    #202840;
  --border:   #2a3352;
  --border2:  #334070;
  --primary:  #4f8ef7;
  --primary2: #6ea8fe;
  --accent:   #38bdf8;
  --success:  #34d399;
  --warning:  #fbbf24;
  --danger:   #f87171;
  --text:     #e2e8f0;
  --text2:    #94a3b8;
  --text3:    #64748b;
  --sidebar-w:240px;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ── SIDEBAR ─────────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  height: 100vh;
  background: var(--surface);
  position: fixed;
  top: 0; left: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border);
  z-index: 100;
  overflow-y: auto;
}
.sidebar-brand {
  padding: 20px 18px 16px;
  border-bottom: 1px solid var(--border);
}
.sidebar-brand .brand-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; margin-bottom: 10px;
}
.sidebar-brand h2 {
  font-size: 14px; font-weight: 700;
  color: var(--text); line-height: 1.4;
  letter-spacing: 0.2px;
}
.sidebar-brand p {
  font-size: 11px; color: var(--text3); margin-top: 2px;
}
.sidebar-nav {
  padding: 12px 10px;
  flex: 1;
}
.nav-label {
  font-size: 10px; color: var(--text3);
  font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase;
  padding: 8px 8px 4px;
}
.sidebar-nav a {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; color: var(--text2);
  text-decoration: none; border-radius: 8px;
  font-size: 13px; font-weight: 500;
  transition: all .15s;
  margin-bottom: 2px;
}
.sidebar-nav a .nav-icon {
  width: 20px; text-align: center; font-size: 15px;
  flex-shrink: 0;
}
.sidebar-nav a:hover {
  background: var(--card);
  color: var(--text);
}
.sidebar-nav a.active {
  background: rgba(79,142,247,.15);
  color: var(--primary2);
  font-weight: 600;
}
.sidebar-nav a.active .nav-icon { color: var(--primary); }

/* ── CONTENT ─────────────────────────────────────────── */
.content {
  margin-left: var(--sidebar-w);
  padding: 0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ── TOPBAR ──────────────────────────────────────────── */
.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 14px 24px;
  display: flex; align-items: center;
  justify-content: space-between; gap: 12px;
  flex-wrap: wrap;
  position: sticky; top: 0; z-index: 50;
}
.topbar-left { display: flex; align-items: center; gap: 12px; }
.topbar-left .page-title {
  font-size: 15px; font-weight: 700; color: var(--text);
}
.topbar-left .page-sub {
  font-size: 12px; color: var(--text3);
}
.topbar form {
  display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.topbar select {
  padding: 7px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px; color: var(--text);
  background: var(--card);
  cursor: pointer;
}
.topbar select:focus { outline: none; border-color: var(--primary); }
.btn-primary {
  padding: 7px 16px;
  background: var(--primary);
  color: #fff;
  border: none; border-radius: 8px;
  cursor: pointer; font-size: 13px; font-weight: 600;
  transition: background .15s;
}
.btn-primary:hover { background: #3a7de0; }

/* ── PAGE BODY ───────────────────────────────────────── */
.page-body { padding: 20px 24px; flex: 1; }

/* ── TAB CONTENT ─────────────────────────────────────── */
.tabcontent { display: none; }
.tabcontent.active { display: block; }

/* ── STAT CARDS ──────────────────────────────────────── */
.cards {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px 14px;
  position: relative;
  overflow: hidden;
  transition: transform .15s, box-shadow .15s;
}
.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0,0,0,.4);
}
.card-accent {
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
}
.card-accent.blue  { background: linear-gradient(90deg, #4f8ef7, #6ea8fe); }
.card-accent.cyan  { background: linear-gradient(90deg, #22d3ee, #38bdf8); }
.card-accent.green { background: linear-gradient(90deg, #34d399, #6ee7b7); }
.card-accent.teal  { background: linear-gradient(90deg, #2dd4bf, #5eead4); }
.card-accent.amber { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.card-accent.rose  { background: linear-gradient(90deg, #fb7185, #fda4af); }

.card .card-icon {
  font-size: 20px; margin-bottom: 8px;
}
.card .label {
  font-size: 11px; color: var(--text3);
  font-weight: 600; text-transform: uppercase;
  letter-spacing: .5px; margin-bottom: 6px;
}
.card .val {
  font-size: 26px; font-weight: 700;
  color: var(--text); line-height: 1;
}
.card .unit {
  font-size: 12px; color: var(--text2); margin-top: 4px;
}

/* ── BOX ─────────────────────────────────────────────── */
.box {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 18px 20px;
  margin-bottom: 16px;
}
.box-header {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.box h2, .box-title {
  font-size: 13px; font-weight: 700;
  color: var(--text); letter-spacing: .2px;
  display: flex; align-items: center; gap: 8px;
}
.box h2::before {
  content: '';
  display: inline-block;
  width: 3px; height: 14px;
  background: var(--primary);
  border-radius: 2px;
}

/* ── CHART GRID ──────────────────────────────────────── */
.chart-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

/* ── TABLE ───────────────────────────────────────────── */
.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th {
  background: rgba(79,142,247,.1);
  color: var(--primary2);
  font-weight: 600; font-size: 11px;
  text-transform: uppercase; letter-spacing: .4px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
td {
  padding: 9px 12px;
  border-bottom: 1px solid rgba(42,51,82,.6);
  color: var(--text2);
  text-align: center;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(79,142,247,.05); }

/* ── BADGE ───────────────────────────────────────────── */
.badge {
  display: inline-flex; align-items: center;
  padding: 2px 10px; border-radius: 20px;
  font-size: 11px; font-weight: 600; letter-spacing: .3px;
  white-space: nowrap;
}
.badge-nh  { background: rgba(56,189,248,.15); color: #38bdf8; border: 1px solid rgba(56,189,248,.3); }
.badge-r   { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.badge-s   { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); }
.badge-l   { background: rgba(251,146,60,.15); color: #fb923c; border: 1px solid rgba(251,146,60,.3); }
.badge-sl  { background: rgba(248,113,113,.15); color: #f87171; border: 1px solid rgba(248,113,113,.3); }
.badge-tk  { background: rgba(100,116,139,.15); color: #94a3b8; border: 1px solid rgba(100,116,139,.3); }

/* ── FILTER BAR ──────────────────────────────────────── */
.filter-bar {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 14px;
  display: flex; align-items: center;
  gap: 10px; flex-wrap: wrap;
}
.filter-bar .filter-label {
  font-size: 12px; color: var(--text3);
  font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
}
.filter-bar input[type=date] {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 13px; background: var(--card); color: var(--text);
}
.filter-bar input[type=date]:focus {
  outline: none; border-color: var(--primary);
}
.filter-bar .btn-filter {
  padding: 6px 14px;
  background: var(--primary);
  color: #fff; border: none; border-radius: 7px;
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.filter-bar .btn-filter:hover { background: #3a7de0; }
.filter-bar .btn-reset {
  padding: 6px 12px;
  background: transparent;
  color: var(--text3); border: 1px solid var(--border);
  border-radius: 7px; font-size: 13px; cursor: pointer;
  text-decoration: none; display: inline-block;
}
.filter-bar .btn-reset:hover { color: var(--text); border-color: var(--border2); }
.sort-select {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 13px; background: var(--card); color: var(--text);
  cursor: pointer;
}
.filter-count {
  margin-left: auto;
  font-size: 12px; color: var(--primary2);
  background: rgba(79,142,247,.1);
  padding: 3px 10px; border-radius: 20px;
}

/* ── FORM ────────────────────────────────────────────── */
.form-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.form-group label {
  display: block;
  font-size: 11px; color: var(--text3);
  font-weight: 600; text-transform: uppercase;
  letter-spacing: .4px; margin-bottom: 5px;
}
.form-group input,
.form-group select {
  width: 100%; padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 7px; font-size: 13px;
  background: var(--bg); color: var(--text);
  transition: border-color .15s;
}
.form-group input:focus,
.form-group select:focus {
  outline: none; border-color: var(--primary);
  box-shadow: 0 0 0 2px rgba(79,142,247,.15);
}
.btn-submit {
  grid-column: 1/-1;
  padding: 10px;
  background: var(--primary); color: #fff;
  border: none; border-radius: 8px;
  font-size: 14px; font-weight: 700; cursor: pointer;
  margin-top: 4px;
  transition: background .15s;
}
.btn-submit:hover { background: #3a7de0; }

/* ── ACTION BUTTONS ──────────────────────────────────── */
.btn-edit {
  padding: 4px 10px;
  background: rgba(79,142,247,.15);
  color: var(--primary2);
  border: 1px solid rgba(79,142,247,.3);
  border-radius: 6px; font-size: 12px; cursor: pointer;
  margin-right: 4px; transition: all .15s;
  font-weight: 600;
}
.btn-edit:hover {
  background: rgba(79,142,247,.3);
  color: #fff;
}
.btn-hapus {
  padding: 4px 10px;
  background: rgba(248,113,113,.15);
  color: var(--danger);
  border: 1px solid rgba(248,113,113,.3);
  border-radius: 6px; font-size: 12px; cursor: pointer;
  font-weight: 600; transition: all .15s;
}
.btn-hapus:hover {
  background: rgba(248,113,113,.3);
  color: #fff;
}

/* ── MODAL ───────────────────────────────────────────── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.7);
  z-index: 1000; align-items: center; justify-content: center;
  backdrop-filter: blur(4px);
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: 16px; padding: 28px;
  max-width: 820px; width: 95%;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 25px 60px rgba(0,0,0,.6);
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
}
.modal-header h3 {
  font-size: 15px; font-weight: 700; color: var(--text);
  display: flex; align-items: center; gap: 8px;
}
.modal-header h3 span.modal-badge {
  font-size: 11px; padding: 2px 8px;
  background: rgba(79,142,247,.15); color: var(--primary2);
  border-radius: 20px; font-weight: 600;
}
.modal-close {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  background: var(--card); border: 1px solid var(--border);
  border-radius: 8px; font-size: 15px; cursor: pointer; color: var(--text2);
  transition: all .15s;
}
.modal-close:hover { color: var(--danger); border-color: rgba(248,113,113,.3); }
.modal-divider { height: 1px; background: var(--border); margin-bottom: 18px; }

.btn-edit-save {
  grid-column: 1/-1; margin-top: 4px;
  padding: 10px; background: var(--primary); color: #fff;
  border: none; border-radius: 8px;
  font-size: 14px; font-weight: 700; cursor: pointer;
}
.btn-edit-save:hover { background: #3a7de0; }
.btn-cancel {
  grid-column: 1/-1;
  padding: 8px; background: transparent; color: var(--text3);
  border: 1px solid var(--border); border-radius: 8px;
  font-size: 13px; cursor: pointer;
}
.btn-cancel:hover { color: var(--text); }

/* ── INFO CARDS ──────────────────────────────────────── */
.info-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
.info-card {
  background: var(--card2);
  border: 1px solid var(--border);
  border-radius: 12px; padding: 18px;
}
.info-card h3 {
  color: var(--primary2); margin-bottom: 12px;
  font-size: 14px; font-weight: 700;
  display: flex; align-items: center; gap: 6px;
}
.info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-row .info-key { color: var(--text3); }
.info-row .info-val { color: var(--text); font-weight: 500; }

/* ── NOTIF ───────────────────────────────────────────── */
.notif {
  padding: 12px 16px; border-radius: 8px;
  margin-bottom: 16px; font-size: 13px;
  display: flex; align-items: center; gap: 10px;
}
.notif.ok  { background: rgba(52,211,153,.1); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.notif.err { background: rgba(248,113,113,.1); color: #f87171; border: 1px solid rgba(248,113,113,.3); }

/* ── SECTION DIVIDER ─────────────────────────────────── */
.section-head {
  font-size: 12px; font-weight: 700; color: var(--text3);
  text-transform: uppercase; letter-spacing: .8px;
  margin-bottom: 12px; margin-top: 4px;
  display: flex; align-items: center; gap: 8px;
}
.section-head::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:1200px){ .cards{grid-template-columns:repeat(3,1fr);} }
@media(max-width:900px){
  .chart-grid{grid-template-columns:1fr;}
  .info-grid{grid-template-columns:1fr;}
  .form-grid{grid-template-columns:1fr 1fr;}
  .content{margin-left:0;}
  .sidebar{transform:translateX(-100%);}
}
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🌤</div>
    <h2>Cuaca Kaltim</h2>
    <p>Monitoring BMKG</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="?stasiun=<?=$id_stasiun?>&tab=dashboard"    class="<?=$tab_aktif==='dashboard'   ?'active':''?>">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=stasiun"      class="<?=$tab_aktif==='stasiun'     ?'active':''?>">
      <span class="nav-icon">📍</span> Data Stasiun
    </a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=pengamatan"   class="<?=$tab_aktif==='pengamatan'  ?'active':''?>">
      <span class="nav-icon">📋</span> Data Pengamatan
    </a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=tambah"       class="<?=$tab_aktif==='tambah'      ?'active':''?>">
      <span class="nav-icon">➕</span> Tambah Data
    </a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=perbandingan" class="<?=$tab_aktif==='perbandingan'?'active':''?>">
      <span class="nav-icon">📈</span> Perbandingan
    </a>
  </nav>
</div>

<!-- ══ MODAL EDIT ══ -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal-box">
    <div class="modal-header">
      <h3>✏️ Edit Data Pengamatan <span class="modal-badge">ID: <span id="modal_id_display">—</span></span></h3>
      <button class="modal-close" onclick="tutupModal()">✕</button>
    </div>
    <div class="modal-divider"></div>
    <form method="POST">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_pengamatan" id="edit_id">
      <div class="form-grid">
        <div class="form-group">
          <label>Stasiun</label>
          <select name="id_stasiun" id="edit_stasiun">
            <?php foreach($nama_stasiun as $id=>$nm): ?>
            <option value="<?=$id?>"><?=$nm?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" id="edit_tanggal" required></div>
        <div class="form-group"><label>Suhu Min / Tn (°C)</label><input type="number" step="0.1" name="tn" id="edit_tn"></div>
        <div class="form-group"><label>Suhu Maks / Tx (°C)</label><input type="number" step="0.1" name="tx" id="edit_tx"></div>
        <div class="form-group"><label>Suhu Rata / Tavg (°C)</label><input type="number" step="0.1" name="tavg" id="edit_tavg" required></div>
        <div class="form-group"><label>Kelembapan / RH (%)</label><input type="number" step="0.1" name="rh_avg" id="edit_rh_avg"></div>
        <div class="form-group"><label>Curah Hujan / RR (mm)</label><input type="number" step="0.1" name="rr" id="edit_rr" placeholder="kosong = tidak terukur"></div>
        <div class="form-group"><label>Lama Penyinaran / SS (jam)</label><input type="number" step="0.1" name="ss" id="edit_ss"></div>
        <div class="form-group"><label>Kec. Angin Maks / FF_x (m/s)</label><input type="number" step="0.1" name="ff_x" id="edit_ff_x"></div>
        <div class="form-group"><label>Arah Angin Maks / DDD_x (°)</label><input type="number" step="1" name="ddd_x" id="edit_ddd_x"></div>
        <div class="form-group"><label>Kec. Angin Rata / FF_avg</label><input type="number" step="0.1" name="ff_avg" id="edit_ff_avg"></div>
        <div class="form-group"><label>Arah Angin / DDD_car</label><input type="text" name="ddd_car" id="edit_ddd_car" maxlength="5"></div>
        <button type="submit" class="btn-edit-save">💾 Simpan Perubahan</button>
        <button type="button" class="btn-cancel" onclick="tutupModal()">✕ Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ KONTEN UTAMA ══ -->
<div class="content">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <div>
        <div class="page-title">🌤 Dashboard Cuaca Kalimantan Timur</div>
        <div class="page-sub">BMKG · Stasiun <?= htmlspecialchars($nama_stasiun[$id_stasiun]) ?></div>
      </div>
    </div>
    <form method="GET">
      <input type="hidden" name="tab" value="<?=$tab_aktif?>">
      <select name="stasiun">
        <?php foreach($nama_stasiun as $id=>$nm): ?>
        <option value="<?=$id?>" <?=$id==$id_stasiun?'selected':''?>><?=$nm?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary">🔍 Tampilkan</button>
    </form>
  </div>

  <div class="page-body">

    <?= $pesan ?>

    <!-- ════ TAB DASHBOARD ════ -->
    <div id="dashboard" class="tabcontent <?=$tab_aktif==='dashboard'?'active':''?>">

      <div class="section-head">Ringkasan Statistik</div>
      <div class="cards">
        <div class="card">
          <div class="card-accent blue"></div>
          <div class="card-icon">📅</div>
          <div class="label">Jumlah Data</div>
          <div class="val"><?=$stat['jumlah_data']?></div>
          <div class="unit">hari</div>
        </div>
        <div class="card">
          <div class="card-accent amber"></div>
          <div class="card-icon">🌡️</div>
          <div class="label">Rata-rata Suhu</div>
          <div class="val"><?=$stat['rata_suhu']?></div>
          <div class="unit">°C</div>
        </div>
        <div class="card">
          <div class="card-accent cyan"></div>
          <div class="card-icon">🌧️</div>
          <div class="label">Total Curah Hujan</div>
          <div class="val"><?=$stat['total_hujan']?></div>
          <div class="unit">mm</div>
        </div>
        <div class="card">
          <div class="card-accent teal"></div>
          <div class="card-icon">💧</div>
          <div class="label">Rata-rata RH</div>
          <div class="val"><?=$stat['rata_rh']?></div>
          <div class="unit">%</div>
        </div>
        <div class="card">
          <div class="card-accent rose"></div>
          <div class="card-icon">🔥</div>
          <div class="label">Suhu Tertinggi</div>
          <div class="val"><?=$stat['suhu_maks']?></div>
          <div class="unit">°C</div>
        </div>
        <div class="card">
          <div class="card-accent green"></div>
          <div class="card-icon">❄️</div>
          <div class="label">Suhu Terendah</div>
          <div class="val"><?=$stat['suhu_min']?></div>
          <div class="unit">°C</div>
        </div>
      </div>

      <div class="section-head">Visualisasi Data</div>
      <div class="chart-grid">
        <div class="box">
          <h2>Tren Suhu Harian (°C)</h2>
          <canvas id="chartSuhu"></canvas>
        </div>
        <div class="box">
          <h2>Distribusi Kategori Curah Hujan</h2>
          <canvas id="chartKat" style="max-height:300px"></canvas>
        </div>
      </div>
      <div class="chart-grid">
        <div class="box">
          <h2>Curah Hujan Harian (mm)</h2>
          <canvas id="chartHujan"></canvas>
        </div>
        <div class="box">
          <h2>Kelembapan Harian (%)</h2>
          <canvas id="chartRh"></canvas>
        </div>
      </div>

    </div><!-- /dashboard -->

    <!-- ════ TAB DATA STASIUN ════ -->
    <div id="stasiun" class="tabcontent <?=$tab_aktif==='stasiun'?'active':''?>">
      <div class="box">
        <h2>Informasi Stasiun BMKG Kalimantan Timur</h2>
        <div class="info-grid" style="margin-top:14px;">
          <?php foreach($stasiun_rows as $s): ?>
          <div class="info-card">
            <h3>🏢 <?= htmlspecialchars($s['nama_stasiun']) ?></h3>
            <div class="info-row"><span class="info-key">🏙️ Kota</span><span class="info-val"><?=$s['kota']?></span></div>
            <div class="info-row"><span class="info-key">🆔 ID WMO</span><span class="info-val"><?=$s['id_stasiun']?></span></div>
            <div class="info-row"><span class="info-key">🌐 Lintang</span><span class="info-val"><?=$s['lintang']?>°</span></div>
            <div class="info-row"><span class="info-key">🌐 Bujur</span><span class="info-val"><?=$s['bujur']?>°</span></div>
            <div class="info-row"><span class="info-key">⛰️ Elevasi</span><span class="info-val"><?=$s['elevasi_m']?> m dpl</span></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ════ TAB DATA PENGAMATAN ════ -->
    <div id="pengamatan" class="tabcontent <?=$tab_aktif==='pengamatan'?'active':''?>">

      <!-- Filter Tanggal (hanya di tab ini) -->
      <div class="filter-bar">
        <span class="filter-label">🗓 Filter</span>
        <form method="GET" style="display:contents;">
          <input type="hidden" name="stasiun" value="<?=$id_stasiun?>">
          <input type="hidden" name="tab" value="pengamatan">
          <span style="font-size:13px;color:var(--text3)">Dari</span>
          <input type="date" name="dari" value="<?=htmlspecialchars($filter_dari)?>">
          <span style="font-size:13px;color:var(--text3)">s.d.</span>
          <input type="date" name="sampai" value="<?=htmlspecialchars($filter_sampai)?>">

          <!-- Urutan Waktu -->
          <span style="font-size:13px;color:var(--text3);margin-left:6px;">Urutan</span>
          <select name="sort" class="sort-select">
            <option value="ASC"  <?=$sort_order==='ASC' ?'selected':''?>>⬆ Terlama dulu</option>
            <option value="DESC" <?=$sort_order==='DESC'?'selected':''?>>⬇ Terbaru dulu</option>
          </select>

          <button type="submit" class="btn-filter">🔍 Terapkan</button>
          <?php if($filter_dari || $filter_sampai): ?>
          <a href="?stasiun=<?=$id_stasiun?>&tab=pengamatan" class="btn-reset">✕ Reset</a>
          <?php endif; ?>
        </form>
        <?php if($filter_dari || $filter_sampai): ?>
        <span class="filter-count"><?=count($kat_rows)?> data ditemukan</span>
        <?php endif; ?>
      </div>

      <div class="box">
        <h2>Data Pengamatan Harian — <?=$nama_stasiun[$id_stasiun]?></h2>
        <div class="tbl-wrap" style="margin-top:12px;">
        <table>
          <thead>
          <tr>
            <th>#</th><th>Tanggal</th><th>Tn (°C)</th><th>Tx (°C)</th>
            <th>Tavg (°C)</th><th>RH (%)</th><th>RR (mm)</th>
            <th>Kategori Hujan</th><th>SS (jam)</th>
            <th>FF_x</th><th>DDD_x</th><th>DDD_car</th>
            <th>Aksi</th>
          </tr>
          </thead>
          <tbody>
          <?php
          $badge_map = [
              'Tidak Hujan'   => 'badge-nh',
              'Ringan'        => 'badge-r',
              'Sedang'        => 'badge-s',
              'Lebat'         => 'badge-l',
              'Sangat Lebat'  => 'badge-sl',
              'Tidak Terukur' => 'badge-tk',
          ];
          foreach($kat_rows as $i=>$r):
              $cls = $badge_map[$r['kategori_hujan']] ?? 'badge-tk';
              $eid = $r['id_pengamatan'];
          ?>
          <tr>
            <td style="color:var(--text3)"><?=$i+1?></td>
            <td style="color:var(--text);font-weight:500"><?=$r['tanggal']?></td>
            <td><?=$r['tn']??'-'?></td>
            <td><?=$r['tx']??'-'?></td>
            <td style="color:var(--warning);font-weight:600"><?=$r['tavg']?></td>
            <td><?=$r['rh_avg']??'-'?></td>
            <td><?= $r['rr'] !== null ? $r['rr'] : '-' ?></td>
            <td><span class="badge <?=$cls?>"><?=$r['kategori_hujan']?></span></td>
            <td><?=$r['ss']??'-'?></td>
            <td><?=$r['ff_x']??'-'?></td>
            <td><?=$r['ddd_x']??'-'?></td>
            <td><?=$r['ddd_car']??'-'?></td>
            <td style="white-space:nowrap;">
              <button class="btn-edit" onclick="bukaEdit(
                <?=$eid?>,
                '<?=$id_stasiun?>',
                '<?=$r['tanggal']?>',
                <?=(float)($r['tn']??0)?>,
                <?=(float)($r['tx']??0)?>,
                <?=(float)($r['tavg']??0)?>,
                <?=(float)($r['rh_avg']??0)?>,
                '<?=$r['rr']!==null?$r['rr']:''?>',
                <?=(float)($r['ss']??0)?>,
                <?=(float)($r['ff_x']??0)?>,
                <?=(float)($r['ddd_x']??0)?>,
                <?=(float)($r['ff_avg']??0)?>,
                '<?=htmlspecialchars($r['ddd_car']??'')?>'
              )">✏️ Edit</button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id_pengamatan" value="<?=$eid?>">
                <button class="btn-hapus" onclick="return confirm('Hapus data tanggal <?=$r['tanggal']?>?')">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($kat_rows)): ?>
          <tr><td colspan="13" style="padding:40px;color:var(--text3);">Tidak ada data untuk rentang tanggal ini.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div><!-- /pengamatan -->

    <!-- ════ TAB TAMBAH DATA ════ -->
    <div id="tambah" class="tabcontent <?=$tab_aktif==='tambah'?'active':''?>">
      <div class="box">
        <h2>Tambah Data Pengamatan Baru</h2>
        <form method="POST" style="margin-top:16px;">
          <input type="hidden" name="aksi" value="tambah">
          <div class="form-grid">
            <div class="form-group">
              <label>Stasiun</label>
              <select name="id_stasiun">
                <?php foreach($nama_stasiun as $id=>$nm): ?>
                <option value="<?=$id?>" <?=$id==$id_stasiun?'selected':''?>><?=$nm?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" required></div>
            <div class="form-group"><label>Suhu Min / Tn (°C)</label><input type="number" step="0.1" name="tn" placeholder="24.5"></div>
            <div class="form-group"><label>Suhu Maks / Tx (°C)</label><input type="number" step="0.1" name="tx" placeholder="33.0"></div>
            <div class="form-group"><label>Suhu Rata / Tavg (°C)</label><input type="number" step="0.1" name="tavg" placeholder="28.0" required></div>
            <div class="form-group"><label>Kelembapan / RH (%)</label><input type="number" step="0.1" name="rh_avg" placeholder="85"></div>
            <div class="form-group"><label>Curah Hujan / RR (mm) — kosong jika 8888</label><input type="number" step="0.1" name="rr" placeholder="0.0"></div>
            <div class="form-group"><label>Lama Penyinaran / SS (jam)</label><input type="number" step="0.1" name="ss" placeholder="6.5"></div>
            <div class="form-group"><label>Kec. Angin Maks / FF_x (m/s)</label><input type="number" step="0.1" name="ff_x" placeholder="3"></div>
            <div class="form-group"><label>Arah Angin Maks / DDD_x (°)</label><input type="number" step="1" name="ddd_x" placeholder="90"></div>
            <div class="form-group"><label>Kec. Angin Rata / FF_avg</label><input type="number" step="0.1" name="ff_avg" placeholder="1"></div>
            <div class="form-group"><label>Arah Angin / DDD_car</label><input type="text" name="ddd_car" placeholder="E" maxlength="5"></div>
            <button type="submit" class="btn-submit">💾 Simpan Data</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ════ TAB PERBANDINGAN ════ -->
    <div id="perbandingan" class="tabcontent <?=$tab_aktif==='perbandingan'?'active':''?>">
      <div class="box">
        <h2>Perbandingan Statistik Antar Stasiun</h2>
        <div class="tbl-wrap" style="margin-top:12px;">
        <table>
          <thead>
          <tr>
            <th>Stasiun</th>
            <th>Rata Suhu (°C)</th>
            <th>Total Hujan (mm)</th>
            <th>Rata RH (%)</th>
            <th>Suhu Maks (°C)</th>
            <th>Suhu Min (°C)</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach($grup_rows as $g): ?>
          <tr>
            <td style="color:var(--text);font-weight:600"><?=$g['kota']?></td>
            <td style="color:var(--warning)"><?=$g['rata_suhu']?></td>
            <td style="color:var(--accent)"><?=$g['total_hujan']?></td>
            <td style="color:var(--success)"><?=$g['rata_rh']?></td>
            <td style="color:var(--danger)"><?=$g['suhu_maks']?></td>
            <td style="color:#a5b4fc"><?=$g['suhu_min']?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
      <div class="box">
        <h2>Grafik Perbandingan Rata-rata Suhu per Stasiun</h2>
        <canvas id="chartGrup" style="max-height:320px"></canvas>
      </div>
    </div>

  </div><!-- /page-body -->
</div><!-- /content -->

<script>
// ── Data dari PHP
const tanggal = <?= json_encode($tanggal) ?>;
const suhu    = <?= json_encode($suhu) ?>;
const hujan   = <?= json_encode($hujan) ?>;
const rh      = <?= json_encode($rh) ?>;
const katLabel = <?= json_encode($kat_label_chart) ?>;
const katData  = <?= json_encode($kat_count_chart) ?>;
const grupKota  = <?= json_encode(array_column($grup_rows,'kota')) ?>;
const grupSuhu  = <?= json_encode(array_column($grup_rows,'rata_suhu')) ?>;
const grupHujan = <?= json_encode(array_column($grup_rows,'total_hujan')) ?>;

// ── Modal Edit
function bukaEdit(id, stasiun, tgl, tn, tx, tavg, rh_avg, rr, ss, ff_x, ddd_x, ff_avg, ddd_car) {
    document.getElementById('edit_id').value       = id;
    document.getElementById('modal_id_display').textContent = id;
    document.getElementById('edit_tanggal').value  = tgl;
    document.getElementById('edit_tn').value       = tn;
    document.getElementById('edit_tx').value       = tx;
    document.getElementById('edit_tavg').value     = tavg;
    document.getElementById('edit_rh_avg').value   = rh_avg;
    document.getElementById('edit_rr').value       = rr;
    document.getElementById('edit_ss').value       = ss;
    document.getElementById('edit_ff_x').value     = ff_x;
    document.getElementById('edit_ddd_x').value    = ddd_x;
    document.getElementById('edit_ff_avg').value   = ff_avg;
    document.getElementById('edit_ddd_car').value  = ddd_car;
    var sel = document.getElementById('edit_stasiun');
    for (var i=0;i<sel.options.length;i++) {
        if (sel.options[i].value == stasiun) { sel.selectedIndex = i; break; }
    }
    document.getElementById('modalEdit').classList.add('open');
}
function tutupModal() {
    document.getElementById('modalEdit').classList.remove('open');
}
document.getElementById('modalEdit').addEventListener('click', function(e){
    if (e.target === this) tutupModal();
});

// ── Chart.js global defaults
Chart.defaults.color = '#64748b';
Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";

// ── Scale helper (dark style)
const sc = (tickCb, titleText, titleColor, extra={}) => ({
    grid: { color: 'rgba(42,51,82,0.8)', drawBorder: false },
    border: { color: 'rgba(42,51,82,0.5)' },
    ticks: { color: '#64748b', font: { size: 11 }, callback: tickCb },
    title: { display: !!titleText, text: titleText, color: titleColor, font: { size: 12, weight: '600' } },
    ...extra
});

// ── Tooltip helper
const tip = (borderClr, titleClr) => ({
    backgroundColor: '#1c2233',
    borderColor: borderClr,
    borderWidth: 1,
    titleColor: titleClr,
    bodyColor: '#94a3b8',
    padding: 12,
    cornerRadius: 8,
    displayColors: true,
});

// ══ CHART 1 — Suhu Harian — AMBER/ORANGE (kontras di bg gelap)
new Chart(document.getElementById('chartSuhu'), {
    type: 'line',
    data: { labels: tanggal, datasets: [{
        label: 'Suhu Rata-rata (°C)',
        data: suhu,
        borderColor: '#f59e0b',
        backgroundColor: ctx => {
            const g = ctx.chart.ctx.createLinearGradient(0,0,0,260);
            g.addColorStop(0,'rgba(245,158,11,0.35)');
            g.addColorStop(1,'rgba(245,158,11,0.0)');
            return g;
        },
        fill: true, tension: 0.4, borderWidth: 2.5,
        pointRadius: 3, pointBackgroundColor: '#f59e0b',
        pointBorderColor: '#0f1117', pointBorderWidth: 2,
        pointHoverRadius: 6, pointHoverBackgroundColor: '#fde68a'
    }]},
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position:'top', labels:{ color:'#e2e8f0', font:{size:12}, boxWidth:12 }},
            tooltip: { ...tip('#f59e0b','#fbbf24'), callbacks: { label: ctx=>`🌡 ${ctx.raw}°C` }}
        },
        scales: {
            x: sc(v=>v,'','#94a3b8',{ticks:{color:'#64748b',font:{size:10},maxTicksLimit:10,maxRotation:30}}),
            y: sc(v=>v+'°C','Suhu (°C)','#f59e0b')
        }
    }
});

// ══ CHART 2 — Doughnut Kategori Hujan — warna terang & kontras
const katColors = {
    'Tidak Hujan'  : '#38bdf8',   // sky blue
    'Ringan'       : '#4ade80',   // green
    'Sedang'       : '#facc15',   // yellow
    'Lebat'        : '#fb923c',   // orange
    'Sangat Lebat' : '#f87171',   // red
    'Tidak Terukur': '#94a3b8',   // slate
};
const pieColors = katLabel.map(l => katColors[l] || '#64748b');

const piePercentPlugin = {
    id: 'piePercent',
    afterDatasetsDraw(chart) {
        if (chart.config.type !== 'doughnut') return;
        const {ctx} = chart;
        const ds = chart.data.datasets[0];
        const total = ds.data.reduce((a,b)=>a+(parseFloat(b)||0),0);
        const meta  = chart.getDatasetMeta(0);
        ctx.save();
        meta.data.forEach((arc,i) => {
            const val = ds.data[i];
            if (!val) return;
            const pct = (val/total*100);
            if (pct < 4) return;
            const mid = arc.startAngle+(arc.endAngle-arc.startAngle)/2;
            const r   = (arc.innerRadius+arc.outerRadius)/2;
            const x   = arc.x+Math.cos(mid)*r*0.75;
            const y   = arc.y+Math.sin(mid)*r*0.75;
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 12px Segoe UI,sans-serif';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(pct.toFixed(1)+'%', x, y);
        });
        ctx.restore();
    }
};
Chart.register(piePercentPlugin);

new Chart(document.getElementById('chartKat'), {
    type: 'doughnut',
    data: { labels: katLabel, datasets: [{
        data: katData,
        backgroundColor: pieColors,
        borderColor: '#0f1117',
        borderWidth: 3,
        hoverBorderColor: '#fff',
        hoverOffset: 10
    }]},
    options: {
        responsive: true, cutout: '52%',
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    color: '#e2e8f0', font: { size: 12 }, padding: 14, boxWidth: 12,
                    generateLabels(chart) {
                        const d = chart.data;
                        const total = d.datasets[0].data.reduce((a,b)=>a+(+b||0),0);
                        return d.labels.map((label,i) => ({
                            text: `${label}  ${((d.datasets[0].data[i]/total)*100).toFixed(1)}%`,
                            fillStyle: pieColors[i], strokeStyle: pieColors[i],
                            hidden: false, index: i
                        }));
                    }
                }
            },
            tooltip: { ...tip('#ffffff','#ffffff'), callbacks: {
                label: ctx => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+(+b||0),0);
                    return `${ctx.label}: ${ctx.raw} hari (${((ctx.raw/total)*100).toFixed(1)}%)`;
                }
            }}
        }
    }
});

// ══ CHART 3 — Curah Hujan — CYAN bertingkat (lebih kontras dari bg)
new Chart(document.getElementById('chartHujan'), {
    type: 'bar',
    data: { labels: tanggal, datasets: [{
        label: 'Curah Hujan (mm)',
        data: hujan,
        backgroundColor: ctx => {
            const v = ctx.raw||0;
            if (v === 0)  return 'rgba(148,163,184,0.2)';
            if (v <= 20)  return '#38bdf8';
            if (v <= 50)  return '#0ea5e9';
            if (v <= 100) return '#0284c7';
            return '#0369a1';
        },
        borderColor: 'transparent',
        borderWidth: 1, borderRadius: 4,
        hoverBackgroundColor: '#7dd3fc'
    }]},
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position:'top', labels:{ color:'#e2e8f0', font:{size:12}, boxWidth:12 }},
            tooltip: { ...tip('#38bdf8','#7dd3fc'), callbacks: {
                label: ctx => `🌧 ${ctx.raw} mm`
            }}
        },
        scales: {
            x: sc(v=>v,'','#94a3b8',{ticks:{color:'#64748b',font:{size:10},maxTicksLimit:10,maxRotation:30}}),
            y: sc(v=>v+' mm','Curah Hujan (mm)','#38bdf8')
        }
    }
});

// ══ CHART 4 — Kelembapan — TEAL/EMERALD
new Chart(document.getElementById('chartRh'), {
    type: 'line',
    data: { labels: tanggal, datasets: [{
        label: 'Kelembapan (%)',
        data: rh,
        borderColor: '#2dd4bf',
        backgroundColor: ctx => {
            const g = ctx.chart.ctx.createLinearGradient(0,0,0,260);
            g.addColorStop(0,'rgba(45,212,191,0.3)');
            g.addColorStop(1,'rgba(45,212,191,0.0)');
            return g;
        },
        fill: true, tension: 0.4, borderWidth: 2.5,
        pointRadius: 3, pointBackgroundColor: '#2dd4bf',
        pointBorderColor: '#0f1117', pointBorderWidth: 2,
        pointHoverRadius: 6, pointHoverBackgroundColor: '#99f6e4'
    }]},
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position:'top', labels:{ color:'#e2e8f0', font:{size:12}, boxWidth:12 }},
            tooltip: { ...tip('#2dd4bf','#99f6e4'), callbacks: { label: ctx=>`💧 ${ctx.raw}%` }}
        },
        scales: {
            x: sc(v=>v,'','#94a3b8',{ticks:{color:'#64748b',font:{size:10},maxTicksLimit:10,maxRotation:30}}),
            y: sc(v=>v+'%','Kelembapan (%)','#2dd4bf',{min:40,max:100})
        }
    }
});

// ══ CHART 5 — Perbandingan Stasiun — ORANGE vs CYAN
new Chart(document.getElementById('chartGrup'), {
    type: 'bar',
    data: { labels: grupKota, datasets: [
        {
            label: 'Rata-rata Suhu (°C)',
            data: grupSuhu,
            backgroundColor: 'rgba(251,146,60,0.85)',
            borderColor: '#fb923c', borderWidth: 1,
            borderRadius: 8, yAxisID: 'y'
        },
        {
            label: 'Total Curah Hujan (mm)',
            data: grupHujan,
            backgroundColor: 'rgba(56,189,248,0.75)',
            borderColor: '#38bdf8', borderWidth: 1,
            borderRadius: 8, yAxisID: 'y1'
        }
    ]},
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position:'top', labels:{ color:'#e2e8f0', font:{size:12}, padding:14, boxWidth:12 }},
            tooltip: { ...tip('#fb923c','#fde68a'), callbacks: {
                label: ctx => {
                    const unit = ctx.datasetIndex===0?'°C':' mm';
                    const clr  = ctx.datasetIndex===0?'🟠':'🔵';
                    return `${clr} ${ctx.dataset.label}: ${ctx.raw}${unit}`;
                }
            }}
        },
        scales: {
            x: sc(v=>v,'','#94a3b8'),
            y:  {...sc(v=>v+'°C','Suhu (°C)','#fb923c'), position:'left'},
            y1: {...sc(v=>v+' mm','Hujan (mm)','#38bdf8'), position:'right', grid:{drawOnChartArea:false}}
        }
    }
});
</script>
</body>
</html>
