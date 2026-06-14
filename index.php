<?php
include 'koneksi.php';

$id_stasiun = $_GET['stasiun'] ?? '96633';
$tab_aktif  = $_GET['tab']     ?? 'dashboard';

$nama_stasiun = [
    '96633' => 'Balikpapan',
    '96607' => 'Samarinda',
    '96529' => 'Berau'
];

// ── Statistik ringkasan (COUNT, AVG, SUM, MAX, MIN) ──────────────
$stat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                AS jumlah_data,
        ROUND(AVG(tavg),  2)    AS rata_suhu,
        ROUND(SUM(rr),    2)    AS total_hujan,
        ROUND(AVG(rh_avg),2)    AS rata_rh,
        MAX(tx)                 AS suhu_maks,
        MIN(tn)                 AS suhu_min
    FROM pengamatan
    WHERE id_stasiun = '$id_stasiun'
"));

// ── Data harian (untuk grafik & tabel) ───────────────────────────
$data = mysqli_query($conn, "
    SELECT * FROM pengamatan
    WHERE id_stasiun = '$id_stasiun'
    ORDER BY tanggal
");

$tanggal=[]; $suhu=[]; $hujan=[]; $rh=[]; $rows=[];
while ($row = mysqli_fetch_assoc($data)) {
    $tanggal[] = $row['tanggal'];
    $suhu[]    = $row['tavg'];
    $hujan[]   = $row['rr'] !== null ? floatval($row['rr']) : 0;
    $rh[]      = $row['rh_avg'];
    $rows[]    = $row;
}

// ── GROUP BY per stasiun (READ 3) ─────────────────────────────────
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

// ── JOIN + kategori hujan (READ 4) ────────────────────────────────
$kat_res = mysqli_query($conn, "
    SELECT
        s.kota,
        p.tanggal,
        p.tavg,
        p.rr,
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
    WHERE p.id_stasiun = '$id_stasiun'
    ORDER BY p.tanggal
");
$kat_rows = [];
$kat_label_chart = []; $kat_count_chart = [];
$kat_count = [];
while ($r = mysqli_fetch_assoc($kat_res)) {
    $kat_rows[] = $r;
    $kat_count[$r['kategori_hujan']] = ($kat_count[$r['kategori_hujan']] ?? 0) + 1;
}
foreach ($kat_count as $k => $v) { $kat_label_chart[] = $k; $kat_count_chart[] = $v; }

// ── Data semua stasiun ────────────────────────────────────────────
$stasiun_list = mysqli_query($conn, "SELECT * FROM stasiun ORDER BY nama_stasiun");
$stasiun_rows = [];
while ($r = mysqli_fetch_assoc($stasiun_list)) $stasiun_rows[] = $r;

// ── Form tambah data (CREATE) ─────────────────────────────────────
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
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f0f4f8;}

/* ── Sidebar ── */
.sidebar{
    width:210px;height:100vh;background:#1e3a5f;
    color:#fff;padding:24px 16px;position:fixed;top:0;left:0;
    display:flex;flex-direction:column;gap:6px;
}
.sidebar h2{font-size:16px;margin-bottom:20px;line-height:1.4;}
.sidebar a{
    display:block;padding:10px 14px;color:#cbd5e1;
    text-decoration:none;border-radius:8px;font-size:14px;
    transition:background .2s,color .2s;
}
.sidebar a:hover{background:#2d5986;color:#fff;}
.sidebar a.active{background:#2563eb;color:#fff;font-weight:bold;}

/* ── Konten ── */
.content{margin-left:210px;padding:24px;min-height:100vh;}

/* ── Header ── */
.topbar{
    background:#fff;border-radius:12px;padding:18px 24px;
    margin-bottom:20px;display:flex;align-items:center;
    justify-content:space-between;flex-wrap:wrap;gap:12px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}
.topbar h1{font-size:18px;color:#1e3a5f;}
.topbar form{display:flex;gap:8px;align-items:center;}
.topbar select{padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;}
.topbar button[type=submit]{
    padding:8px 16px;background:#2563eb;color:#fff;
    border:none;border-radius:8px;cursor:pointer;font-size:14px;
}
.topbar button[type=submit]:hover{background:#1d4ed8;}

/* ── Tab content ── */
.tabcontent{display:none;}
.tabcontent.active{display:block;}

/* ── Kartu statistik ── */
.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px;}
.card{
    background:#fff;padding:16px;border-radius:12px;
    box-shadow:0 2px 6px rgba(0,0,0,.07);text-align:center;
}
.card .label{font-size:12px;color:#6b7280;margin-bottom:6px;}
.card .val{font-size:22px;font-weight:bold;color:#1e3a5f;}
.card .unit{font-size:11px;color:#9ca3af;}

/* ── Box ── */
.box{
    background:#fff;padding:20px;border-radius:12px;
    margin-bottom:20px;box-shadow:0 2px 6px rgba(0,0,0,.07);
}
.box h2{font-size:15px;color:#374151;margin-bottom:14px;border-left:4px solid #2563eb;padding-left:10px;}

/* ── Grid 2 kolom grafik ── */
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;}

/* ── Tabel ── */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th,td{padding:9px 10px;border:1px solid #e5e7eb;text-align:center;}
th{background:#2563eb;color:#fff;font-weight:600;}
tr:nth-child(even){background:#f8fafc;}
tr:hover{background:#eff6ff;}

/* ── Badge kategori ── */
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;}
.badge-nh  {background:#e0f2fe;color:#0369a1;}
.badge-r   {background:#dcfce7;color:#166534;}
.badge-s   {background:#fef9c3;color:#854d0e;}
.badge-l   {background:#fed7aa;color:#9a3412;}
.badge-sl  {background:#fce7f3;color:#9d174d;}
.badge-tk  {background:#f3f4f6;color:#6b7280;}

/* ── Form tambah ── */
.form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
.form-grid label{font-size:13px;color:#374151;font-weight:600;}
.form-grid input,
.form-grid select{
    width:100%;padding:8px 10px;border:1px solid #d1d5db;
    border-radius:6px;font-size:13px;margin-top:4px;
}
.btn-tambah{
    grid-column:1/-1;margin-top:6px;
    padding:10px;background:#16a34a;color:#fff;
    border:none;border-radius:8px;font-size:14px;cursor:pointer;
}
.btn-tambah:hover{background:#15803d;}
.btn-hapus{
    padding:4px 10px;background:#ef4444;color:#fff;
    border:none;border-radius:6px;font-size:12px;cursor:pointer;
}
.btn-hapus:hover{background:#dc2626;}

/* ── Notif ── */
.notif{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;}
.notif.ok {background:#dcfce7;color:#166534;border:1px solid #86efac;}
.notif.err{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

/* ── Info card stasiun ── */
.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.info-card{background:#f0f7ff;border-radius:10px;padding:18px;border:1px solid #bfdbfe;}
.info-card h3{color:#1d4ed8;margin-bottom:10px;font-size:14px;}
.info-card p{font-size:13px;color:#374151;line-height:1.9;}

@media(max-width:1100px){.cards{grid-template-columns:repeat(3,1fr);}}
@media(max-width:800px){
    .chart-grid{grid-template-columns:1fr;}
    .info-grid{grid-template-columns:1fr;}
    .form-grid{grid-template-columns:1fr 1fr;}
    .content{margin-left:0;padding:14px;}
}
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar">
    <h2>🌦️ Cuaca<br>Kalimantan Timur</h2>
    <a href="?stasiun=<?=$id_stasiun?>&tab=dashboard"   class="<?=$tab_aktif==='dashboard'  ?'active':''?>">📊 Dashboard</a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=stasiun"     class="<?=$tab_aktif==='stasiun'    ?'active':''?>">📍 Data Stasiun</a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=pengamatan"  class="<?=$tab_aktif==='pengamatan' ?'active':''?>">📋 Data Pengamatan</a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=tambah"      class="<?=$tab_aktif==='tambah'     ?'active':''?>">➕ Tambah Data</a>
    <a href="?stasiun=<?=$id_stasiun?>&tab=perbandingan"class="<?=$tab_aktif==='perbandingan'?'active':''?>">📈 Perbandingan</a>
</div>

<!-- ══ KONTEN ══ -->
<div class="content">

    <!-- Header + dropdown -->
    <div class="topbar">
        <h1>📡 Stasiun <?= htmlspecialchars($nama_stasiun[$id_stasiun]) ?></h1>
        <form method="GET">
            <input type="hidden" name="tab" value="<?=$tab_aktif?>">
            <select name="stasiun">
                <?php foreach($nama_stasiun as $id=>$nm): ?>
                <option value="<?=$id?>" <?=$id==$id_stasiun?'selected':''?>><?=$nm?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Tampilkan</button>
        </form>
    </div>

    <?= $pesan ?>

    <!-- ════ TAB DASHBOARD ════ -->
    <div id="dashboard" class="tabcontent <?=$tab_aktif==='dashboard'?'active':''?>">

        <!-- Kartu statistik: COUNT, AVG, SUM, MAX, MIN -->
        <div class="cards">
            <div class="card"><div class="label">Jumlah Data</div><div class="val"><?=$stat['jumlah_data']?></div><div class="unit">hari</div></div>
            <div class="card"><div class="label">Rata-rata Suhu</div><div class="val"><?=$stat['rata_suhu']?></div><div class="unit">°C</div></div>
            <div class="card"><div class="label">Total Curah Hujan</div><div class="val"><?=$stat['total_hujan']?></div><div class="unit">mm</div></div>
            <div class="card"><div class="label">Rata-rata RH</div><div class="val"><?=$stat['rata_rh']?></div><div class="unit">%</div></div>
            <div class="card"><div class="label">Suhu Tertinggi</div><div class="val"><?=$stat['suhu_maks']?></div><div class="unit">°C</div></div>
            <div class="card"><div class="label">Suhu Terendah</div><div class="val"><?=$stat['suhu_min']?></div><div class="unit">°C</div></div>
        </div>

        <!-- Grafik 2 kolom -->
        <div class="chart-grid">
            <div class="box">
                <h2>Tren Suhu Harian (°C)</h2>
                <canvas id="chartSuhu"></canvas>
            </div>
            <div class="box">
                <h2>Distribusi Kategori Curah Hujan</h2>
                <canvas id="chartKat"></canvas>
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

    </div>

    <!-- ════ TAB DATA STASIUN ════ -->
    <div id="stasiun" class="tabcontent <?=$tab_aktif==='stasiun'?'active':''?>">
        <div class="box">
            <h2>Informasi Stasiun BMKG Kalimantan Timur</h2>
            <div class="info-grid">
                <?php foreach($stasiun_rows as $s): ?>
                <div class="info-card">
                    <h3><?= htmlspecialchars($s['nama_stasiun']) ?></h3>
                    <p>
                        🏙️ Kota &nbsp;&nbsp;&nbsp; : <?=$s['kota']?><br>
                        🆔 ID WMO &nbsp;: <?=$s['id_stasiun']?><br>
                        🌐 Lintang &nbsp; : <?=$s['lintang']?>°<br>
                        🌐 Bujur &nbsp;&nbsp;&nbsp;: <?=$s['bujur']?>°<br>
                        ⛰️ Elevasi &nbsp;: <?=$s['elevasi_m']?> m dpl
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ════ TAB DATA PENGAMATAN ════ -->
    <div id="pengamatan" class="tabcontent <?=$tab_aktif==='pengamatan'?'active':''?>">
        <div class="box">
            <h2>Data Pengamatan Harian — <?=$nama_stasiun[$id_stasiun]?> (JOIN + Kategori Hujan)</h2>
            <div class="tbl-wrap">
            <table>
                <tr>
                    <th>#</th><th>Tanggal</th><th>Tn (°C)</th><th>Tx (°C)</th>
                    <th>Tavg (°C)</th><th>RH (%)</th><th>RR (mm)</th>
                    <th>Kategori Hujan</th><th>SS (jam)</th>
                    <th>FF_x</th><th>DDD_x</th><th>DDD_car</th>
                    <th>Hapus</th>
                </tr>
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
                ?>
                <tr>
                    <td><?=$i+1?></td>
                    <td><?=$r['tanggal']?></td>
                    <td><?=$rows[$i]['tn']??'-'?></td>
                    <td><?=$rows[$i]['tx']??'-'?></td>
                    <td><?=$r['tavg']?></td>
                    <td><?=$rows[$i]['rh_avg']??'-'?></td>
                    <td><?= $r['rr'] !== null ? $r['rr'] : '-' ?></td>
                    <td><span class="badge <?=$cls?>"><?=$r['kategori_hujan']?></span></td>
                    <td><?=$rows[$i]['ss']??'-'?></td>
                    <td><?=$rows[$i]['ff_x']??'-'?></td>
                    <td><?=$rows[$i]['ddd_x']??'-'?></td>
                    <td><?=$rows[$i]['ddd_car']??'-'?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id_pengamatan" value="<?=$rows[$i]['id_pengamatan']?>">
                            <button class="btn-hapus" onclick="return confirm('Hapus data ini?')">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
        </div>
    </div>

    <!-- ════ TAB TAMBAH DATA (FORM INPUT - BONUS) ════ -->
    <div id="tambah" class="tabcontent <?=$tab_aktif==='tambah'?'active':''?>">
        <div class="box">
            <h2>➕ Tambah Data Pengamatan Baru</h2>
            <form method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="form-grid">
                    <div>
                        <label>Stasiun</label>
                        <select name="id_stasiun">
                            <?php foreach($nama_stasiun as $id=>$nm): ?>
                            <option value="<?=$id?>" <?=$id==$id_stasiun?'selected':''?>><?=$nm?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Tanggal</label><input type="date" name="tanggal" required></div>
                    <div><label>Suhu Min / Tn (°C)</label><input type="number" step="0.1" name="tn" placeholder="24.5"></div>
                    <div><label>Suhu Maks / Tx (°C)</label><input type="number" step="0.1" name="tx" placeholder="33.0"></div>
                    <div><label>Suhu Rata / Tavg (°C)</label><input type="number" step="0.1" name="tavg" placeholder="28.0" required></div>
                    <div><label>Kelembapan / RH (%)</label><input type="number" step="0.1" name="rh_avg" placeholder="85"></div>
                    <div><label>Curah Hujan / RR (mm) — kosongkan jika 8888</label><input type="number" step="0.1" name="rr" placeholder="0.0"></div>
                    <div><label>Lama Penyinaran / SS (jam)</label><input type="number" step="0.1" name="ss" placeholder="6.5"></div>
                    <div><label>Kec. Angin Maks / FF_x (m/s)</label><input type="number" step="0.1" name="ff_x" placeholder="3"></div>
                    <div><label>Arah Angin Maks / DDD_x (°)</label><input type="number" step="1" name="ddd_x" placeholder="90"></div>
                    <div><label>Kec. Angin Rata / FF_avg</label><input type="number" step="0.1" name="ff_avg" placeholder="1"></div>
                    <div><label>Arah Angin / DDD_car</label><input type="text" name="ddd_car" placeholder="E" maxlength="5"></div>
                    <button type="submit" class="btn-tambah">💾 Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ════ TAB PERBANDINGAN ANTAR STASIUN (GROUP BY) ════ -->
    <div id="perbandingan" class="tabcontent <?=$tab_aktif==='perbandingan'?'active':''?>">
        <div class="box">
            <h2>Perbandingan Statistik Antar Stasiun (GROUP BY)</h2>
            <div class="tbl-wrap">
            <table>
                <tr>
                    <th>Stasiun</th>
                    <th>Rata Suhu (°C)</th>
                    <th>Total Hujan (mm)</th>
                    <th>Rata RH (%)</th>
                    <th>Suhu Maks (°C)</th>
                    <th>Suhu Min (°C)</th>
                </tr>
                <?php foreach($grup_rows as $g): ?>
                <tr>
                    <td><strong><?=$g['kota']?></strong></td>
                    <td><?=$g['rata_suhu']?></td>
                    <td><?=$g['total_hujan']?></td>
                    <td><?=$g['rata_rh']?></td>
                    <td><?=$g['suhu_maks']?></td>
                    <td><?=$g['suhu_min']?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
        </div>

        <div class="box">
            <h2>Grafik Perbandingan Rata-rata Suhu per Stasiun</h2>
            <canvas id="chartGrup" style="max-height:320px"></canvas>
        </div>
    </div>

</div><!-- /content -->

<script>
// ── Data dari PHP ──────────────────────────────────────────────
const tanggal = <?= json_encode($tanggal) ?>;
const suhu    = <?= json_encode($suhu) ?>;
const hujan   = <?= json_encode($hujan) ?>;
const rh      = <?= json_encode($rh) ?>;
const katLabel = <?= json_encode($kat_label_chart) ?>;
const katData  = <?= json_encode($kat_count_chart) ?>;
const grupKota  = <?= json_encode(array_column($grup_rows,'kota')) ?>;
const grupSuhu  = <?= json_encode(array_column($grup_rows,'rata_suhu')) ?>;
const grupHujan = <?= json_encode(array_column($grup_rows,'total_hujan')) ?>;

// ── Plugin: label persentase di atas bar / di dalam pie ──────────
const pluginDatalabels = {
    id: 'customDatalabels',
    afterDatasetsDraw(chart) {
        const {ctx, data, chartArea} = chart;
        ctx.save();
        chart.data.datasets.forEach((dataset, di) => {
            const meta = chart.getDatasetMeta(di);
            if (meta.hidden) return;

            // Hitung total untuk persentase
            const total = dataset.data.reduce((a, b) => a + (parseFloat(b)||0), 0);

            meta.data.forEach((el, i) => {
                const val = dataset.data[i];
                if (!val || val == 0) return;
                const pct = total > 0 ? ((val / total) * 100).toFixed(1) + '%' : '';

                ctx.fillStyle = '#1f2937';
                ctx.font = 'bold 11px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';

                const type = chart.config.type;
                if (type === 'bar') {
                    // Label di atas bar
                    const {x, y} = el.tooltipPosition();
                    ctx.fillText(pct, x, y - 4);
                } else if (type === 'line') {
                    // Label di atas titik
                    const {x, y} = el.tooltipPosition();
                    ctx.fillText(pct, x, y - 8);
                }
            });
        });
        ctx.restore();
    }
};

// Plugin pie — pakai callback bawaan Chart.js (lebih akurat untuk pie)
const piePercentPlugin = {
    id: 'piePercent',
    afterDatasetsDraw(chart) {
        if (chart.config.type !== 'pie' && chart.config.type !== 'doughnut') return;
        const {ctx} = chart;
        const dataset = chart.data.datasets[0];
        const total = dataset.data.reduce((a,b) => a+(parseFloat(b)||0), 0);
        const meta  = chart.getDatasetMeta(0);

        ctx.save();
        meta.data.forEach((arc, i) => {
            const val = dataset.data[i];
            if (!val) return;
            const pct  = ((val / total) * 100).toFixed(1) + '%';
            const mid  = arc.startAngle + (arc.endAngle - arc.startAngle) / 2;
            const r    = (arc.innerRadius + arc.outerRadius) / 2;
            const x    = arc.x + Math.cos(mid) * r * 0.75;
            const y    = arc.y + Math.sin(mid) * r * 0.75;

            ctx.fillStyle = '#1f2937';
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(pct, x, y);
        });
        ctx.restore();
    }
};

Chart.register(pluginDatalabels);
Chart.register(piePercentPlugin);

// 1. Line chart – suhu harian (persentase relatif terhadap total)
new Chart(document.getElementById('chartSuhu'), {
    type:'line',
    data:{labels:tanggal,datasets:[{
        label:'Suhu Rata-rata (°C)',data:suhu,
        borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.1)',
        fill:true,tension:0.4,pointRadius:4
    }]},
    options:{
        responsive:true,
        plugins:{
            legend:{position:'top'},
            tooltip:{callbacks:{
                label: ctx => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+(parseFloat(b)||0),0);
                    const pct   = ((ctx.raw/total)*100).toFixed(1);
                    return `Suhu: ${ctx.raw}°C (${pct}% dari total)`;
                }
            }}
        }
    }
});

// 2. Pie chart – distribusi kategori hujan (persentase di tiap irisan)
new Chart(document.getElementById('chartKat'), {
    type:'pie',
    data:{labels:katLabel,datasets:[{
        data:katData,
        backgroundColor:['#bfdbfe','#86efac','#fde68a','#fdba74','#fda4af','#e5e7eb']
    }]},
    options:{
        responsive:true,
        plugins:{
            legend:{
                position:'right',
                labels:{
                    generateLabels(chart) {
                        const data  = chart.data;
                        const total = data.datasets[0].data.reduce((a,b)=>a+(parseFloat(b)||0),0);
                        return data.labels.map((label, i) => ({
                            text: `${label} — ${((data.datasets[0].data[i]/total)*100).toFixed(1)}%`,
                            fillStyle: data.datasets[0].backgroundColor[i],
                            hidden: false,
                            index: i
                        }));
                    }
                }
            },
            tooltip:{callbacks:{
                label: ctx => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+(parseFloat(b)||0),0);
                    const pct   = ((ctx.raw/total)*100).toFixed(1);
                    return `${ctx.label}: ${ctx.raw} hari (${pct}%)`;
                }
            }}
        }
    }
});

// 3. Bar chart – curah hujan harian (% di atas tiap bar)
new Chart(document.getElementById('chartHujan'), {
    type:'bar',
    data:{labels:tanggal,datasets:[{
        label:'Curah Hujan (mm)',data:hujan,
        backgroundColor:'rgba(37,99,235,.7)'
    }]},
    options:{
        responsive:true,
        plugins:{
            legend:{position:'top'},
            tooltip:{callbacks:{
                label: ctx => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+(parseFloat(b)||0),0);
                    const pct   = total > 0 ? ((ctx.raw/total)*100).toFixed(1) : '0.0';
                    return `Hujan: ${ctx.raw} mm (${pct}% dari total)`;
                }
            }}
        }
    }
});

// 4. Line chart – kelembapan
new Chart(document.getElementById('chartRh'), {
    type:'line',
    data:{labels:tanggal,datasets:[{
        label:'Kelembapan (%)',data:rh,
        borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.1)',
        fill:true,tension:0.4,pointRadius:4
    }]},
    options:{
        responsive:true,
        plugins:{
            legend:{position:'top'},
            tooltip:{callbacks:{
                label: ctx => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+(parseFloat(b)||0),0);
                    const pct   = ((ctx.raw/total)*100).toFixed(1);
                    return `RH: ${ctx.raw}% (${pct}% dari total)`;
                }
            }}
        }
    }
});

// 5. Bar chart – perbandingan antar stasiun + label %
new Chart(document.getElementById('chartGrup'), {
    type:'bar',
    data:{labels:grupKota,datasets:[
        {label:'Rata Suhu (°C)',   data:grupSuhu,  backgroundColor:'#f87171',yAxisID:'y'},
        {label:'Total Hujan (mm)', data:grupHujan, backgroundColor:'#60a5fa',yAxisID:'y1'}
    ]},
    options:{
        responsive:true,
        plugins:{
            legend:{position:'top'},
            tooltip:{callbacks:{
                label: ctx => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+(parseFloat(b)||0),0);
                    const pct   = total > 0 ? ((ctx.raw/total)*100).toFixed(1) : '0.0';
                    const unit  = ctx.datasetIndex === 0 ? '°C' : ' mm';
                    return `${ctx.dataset.label}: ${ctx.raw}${unit} (${pct}%)`;
                }
            }}
        },
        scales:{
            y: {type:'linear',position:'left', title:{display:true,text:'Suhu (°C)'}},
            y1:{type:'linear',position:'right',grid:{drawOnChartArea:false},title:{display:true,text:'Hujan (mm)'}}
        }
    }
});
</script>
</body>
</html>
