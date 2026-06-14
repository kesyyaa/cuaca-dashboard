CREATE DATABASE stasiun
    DEFAULT CHARACTER SET = 'utf8mb4';
USE stasiun

CREATE TABLE IF NOT EXISTS stasiun (
    id_stasiun   CHAR(5)       PRIMARY KEY,
    nama_stasiun VARCHAR(100)  NOT NULL,
    kota         VARCHAR(50)   NOT NULL,
    lintang      DECIMAL(8,5)  NOT NULL,
    bujur        DECIMAL(8,5)  NOT NULL,
    elevasi_m    INT           NOT NULL
);
CREATE TABLE pengamatan (
    id_pengamatan INT          PRIMARY KEY AUTO_INCREMENT,
    id_stasiun    CHAR(5)      NOT NULL,
    tanggal       DATE         NOT NULL,
    tn            DECIMAL(4,1),
    tx            DECIMAL(4,1),
    tavg          DECIMAL(4,1),
    rh_avg        DECIMAL(5,1),
    rr            DECIMAL(7,1),   -- NULL jika tidak terukur (kode 8888)
    ss            DECIMAL(4,1),
    ff_x          DECIMAL(4,1),
    ddd_x         DECIMAL(5,1),
    ff_avg        DECIMAL(4,1),
    ddd_car       VARCHAR(5),
     CONSTRAINT fk_stasiun  FOREIGN KEY (id_stasiun)
             REFERENCES stasiun(id_stasiun),
    CONSTRAINT fk_kategori FOREIGN KEY (id_kategori)
             REFERENCES kategori_curah_hujan(id_kategori),
    CONSTRAINT uq_stasiun_tanggal UNIQUE (id_stasiun, tanggal)

);
UPDATE pengamatan
SET id_kategori = 1
WHERE rr =0.0;

UPDATE pengamatan
SET id_kategori = 2
WHERE rr >= 0.1
  AND rr <= 20.0;

UPDATE pengamatan
SET id_kategori = 3
WHERE rr >= 20.1
  AND rr <= 50.0;

UPDATE pengamatan
SET id_kategori = 4
WHERE rr >= 50.1
  AND rr <= 100.0;

UPDATE pengamatan
SET id_kategori = 5
WHERE rr > 100.1
  AND rr <= 9999.9;

alter table pengamatan
add id_kategori INT,
add constraint fk_kategori foreign key (id_kategori) references kategori_curah_hujan(id_kategori);

CREATE TABLE kategori_curah_hujan (
    id_kategori   INT         PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(30) NOT NULL,
    rr_min        DECIMAL(7,1) NOT NULL,
    rr_max        DECIMAL(7,1) NOT NULL,
    keterangan    VARCHAR(100)
);


INSERT INTO stasiun VALUES
('96633','Stasiun Meteorologi Sultan Aji Muhammad Sulaiman Sepinggan','Balikpapan',-1.26000,116.90000,3),
('96607','Stasiun Meteorologi Aji Pangeran Tumenggung Pranoto','Samarinda',-0.48000,117.16000,10),
('96529','Stasiun Meteorologi Kalimarau', 'Berau',2.14562,117.43375,13);

INSERT INTO pengamatan
(id_stasiun,tanggal,tn,tx,tavg,rh_avg,rr,ss,ff_x,ddd_x,ff_avg,ddd_car)
VALUES
('96633','2026-04-01',25.0,32.1,27.7,88,3.4,0.2,3,90,1,'E'),
('96633','2026-04-02',25.1,32.1,27.7,89,0.0,6.5,3,80,1,'N'),
('96633','2026-04-03',24.8,28.4,25.6,96,3.1,3.9,2,200,1,'C'),
('96633','2026-04-04',23.7,32.2,27.3,86,4.9,0.0,3,130,1,'SE'),
('96633','2026-04-05',24.4,31.9,27.4,91,2.0,8.0,4,80,1,'E'),
('96633','2026-04-06',25.3,32.0,27.8,90,0.9,7.2,3,200,1,'E'),
('96633','2026-04-07',25.8,31.3,26.7,91,4.5,3.7,3,220,1,'N'),
('96633','2026-04-08',25.0,32.2,27.9,86,1.0,4.0,4,200,1,'N'),
('96633','2026-04-09',24.3,32.2,27.5,89,28.0,5.7,4,220,2,'S'),
('96633','2026-04-10',24.7,32.1,27.4,89,6.9,6.3,4,240,1,'SW'),
('96633','2026-04-11',23.8,30.4,26.5,91,7.4,1.7,3,260,1,'C'),
('96633','2026-04-12',24.0,32.1,27.6,87,0.2,1.6,7,290,1,'SW'),
('96633','2026-04-13',24.2,29.6,26.7,92,8.4,7.7,2,70,1,'N'),
('96633','2026-04-14',25.4,31.9,27.2,93,0.2,0.0,4,60,1,'N'),
('96633','2026-04-15',25.6,27.8,26.5,97,9.3,7.5,3,200,1,'S'),
('96633','2026-04-16',25.0,30.2,26.9,91,NULL,0.0,3,80,1,'C'),
('96633','2026-04-17',23.9,32.0,27.6,87,NULL,1.7,3,70,1,'N'),
('96633','2026-04-18',25.0,31.3,27.2,93,0.0,7.3,3,60,1,'C'),
('96633','2026-04-19',24.8,32.3,27.2,89,1.5,5.2,2,40,1,'C'),
('96633','2026-04-20',24.8,32.2,28.2,85,NULL,4.8,4,60,1,'E'),
('96633','2026-04-21',25.4,32.8,28.6,83,0.0,8.0,3,50,1,'NE'),
('96633','2026-04-22',25.3,31.8,27.4,91,0.5,7.3,2,90,1,'N'),
('96633','2026-04-23',25.0,31.7,27.8,90,11.3,2.0,3,70,1,'E'),
('96633','2026-04-24',25.4,33.1,28.9,86,NULL,6.5,8,80,2,'NE'),
('96633','2026-04-25',25.0,34.1,28.7,80,0.0,8.0,6,60,2,'NE'),
('96633','2026-04-26',25.2,33.4,29.2,84,0.0,8.0,4,80,2,'C'),
('96633','2026-04-27',25.4,32.4,28.5,89,0.0,8.0,3,80,1,'N'),
('96633','2026-04-28',25.0,31.7,27.8,88,3.9,7.3,3,90,1,'N'),
('96633','2026-04-29',25.3,32.6,28.9,86,0.0,6.9,3,80,1,'NE'),
('96633','2026-04-30',25.9,32.5,27.5,90,0.0,8.0,3,70,1,'N');

INSERT INTO pengamatan
(id_stasiun,tanggal,tn,tx,tavg,rh_avg,rr,ss,ff_x,ddd_x,ff_avg,ddd_car)
VALUES
('96607','2026-04-01',23.3,33.0,27.4,77,2.5,8.0,3,120,1,'E'),
('96607','2026-04-02',24.2,32.2,27.3,84,0.0,7.8,4,280,2,'W'),
('96607','2026-04-03',24.5,29.2,26.2,87,2.3,8.0,3,240,1,'SW'),
('96607','2026-04-04',23.5,32.8,27.3,80,2.5,7.3,4,70,1,'W'),
('96607','2026-04-05',24.0,33.3,27.8,80,0.0,8.0,4,90,2,'E'),
('96607','2026-04-06',24.5,33.2,28.1,77,0.0,8.0,4,310,2,'NW'),
('96607','2026-04-07',24.9,32.2,26.3,88,1.0,8.0,8,220,1,'E'),
('96607','2026-04-08',23.2,32.8,26.7,84,41.9,5.8,2,50,1,'S'),
('96607','2026-04-09',23.9,31.3,26.8,84,3.5,8.0,2,140,1,'SW'),
('96607','2026-04-10',24.0,28.1,26.1,89,12.1,8.0,3,320,1,'W'),
('96607','2026-04-11',23.3,31.4,27.2,82,1.6,7.7,3,210,1,'S'),
('96607','2026-04-12',24.6,32.8,27.3,86,2.4,8.0,8,180,2,'W'),
('96607','2026-04-13',23.3,30.7,26.8,84,40.3,7.4,3,250,1,'S'),
('96607','2026-04-14',24.6,32.4,27.6,85,2.0,7.5,3,130,2,'S'),
('96607','2026-04-15',24.9,31.0,27.4,83,1.0,8.0,3,70,1,'NE'),
('96607','2026-04-16',24.4,30.8,26.4,85,1.5,8.0,5,330,1,'NW'),
('96607','2026-04-17',23.2,32.1,27.3,82,4.0,7.4,4,40,1,'E'),
('96607','2026-04-18',24.0,32.7,27.7,76,NULL,8.0,4,50,2,'NE'),
('96607','2026-04-19',24.0,30.7,26.8,85,5.0,8.0,3,140,1,'E'),
('96607','2026-04-20',24.2,32.3,27.6,83,1.6,8.0,4,90,1,'E'),
('96607','2026-04-21',25.1,32.9,28.2,81,0.0,8.0,6,40,2,'NE'),
('96607','2026-04-22',23.6,32.3,27.7,80,0.0,8.0,6,40,2,'NE'),
('96607','2026-04-23',23.9,32.9,27.7,81,0.0,8.0,4,90,1,'E'),
('96607','2026-04-24',24.5,32.7,28.4,77,0.0,8.0,5,40,2,'NE'),
('96607','2026-04-25',23.4,33.8,28.1,76,0.0,8.0,7,30,3,'NE'),
('96607','2026-04-26',24.1,33.4,28.0,79,0.0,8.0,5,60,2,'E'),
('96607','2026-04-27',24.6,32.8,28.1,81,0.0,8.0,4,30,2,'E'),
('96607','2026-04-28',23.8,33.2,27.3,83,9.2,8.0,3,70,1,'N'),
('96607','2026-04-29',24.5,33.3,28.2,80,0.0,7.5,5,40,2,'NE'),
('96607','2026-04-30',24.4,33.1,28.4,80,0.0,8.0,5,80,2,'E');

INSERT INTO pengamatan
(id_stasiun,tanggal,tn,tx,tavg,rh_avg,rr,ss,ff_x,ddd_x,ff_avg,ddd_car)
VALUES
('96529','2026-04-01',24.2,34.5,27.7,85,0.0,0.9,4,60,1,'E'),
('96529','2026-04-02',24.1,33.1,26.8,90,8.2,4.9,3,80,1,'SW'),
('96529','2026-04-03',23.8,33.6,26.8,89,0.2,7.7,3,110,1,'SW'),
('96529','2026-04-04',23.6,34.5,27.1,86,9.8,5.0,5,60,1,'S'),
('96529','2026-04-05',24.4,33.4,27.9,85,NULL,6.7,4,130,1,'S'),
('96529','2026-04-06',23.2,33.6,27.6,85,NULL,8.0,3,80,1,'E'),
('96529','2026-04-07',23.9,34.5,26.7,88,0.0,8.0,4,140,1,'SE'),
('96529','2026-04-08',23.3,34.8,28.1,81,0.2,8.0,3,250,1,'S'),
('96529','2026-04-09',24.2,35.5,28.6,81,0.0,0.7,3,300,2,'SW'),
('96529','2026-04-10',24.1,32.3,25.0,93,0.0,8.0,3,310,1,'W'),
('96529','2026-04-11',24.2,35.7,28.0,86,17.0,7.4,5,320,2,'SW'),
('96529','2026-04-12',23.4,34.1,26.7,88,18.6,8.0,4,140,1,'W'),
('96529','2026-04-13',23.3,26.8,24.7,94,NULL,7.6,5,320,1,'W'),
('96529','2026-04-14',22.9,32.4,27.0,89,8.6,0.3,3,280,1,'W'),
('96529','2026-04-15',23.6,34.0,27.7,84,0.4,8.0,3,70,1,'E'),
('96529','2026-04-16',23.8,34.5,27.3,87,0.0,8.0,4,120,1,'W'),
('96529','2026-04-17',23.4,35.9,27.8,84,1.4,0.5,5,20,1,'N'),
('96529','2026-04-18',25.0,30.6,25.6,96,NULL,8.0,4,90,1,'SW'),
('96529','2026-04-19',23.0,33.4,27.6,85,15.0,5.3,3,20,1,'W'),
('96529','2026-04-20',24.2,35.2,28.1,85,0.0,8.0,4,90,1,'E'),
('96529','2026-04-21',24.4,29.2,26.2,92,NULL,8.0,3,30,1,'SE'),
('96529','2026-04-22',23.2,34.0,26.7,88,0.4,8.0,2,300,1,'SW'),
('96529','2026-04-23',23.5,32.8,26.0,94,5.4,0.8,3,310,1,'S'),
('96529','2026-04-24',23.4,35.7,28.8,81,4.6,8.0,3,350,1,'NE'),
('96529','2026-04-25',22.7,33.6,26.6,87,0.0,8.0,3,20,1,'S'),
('96529','2026-04-26',22.6,33.5,26.4,89,5.3,8.0,5,10,1,'SW'),
('96529','2026-04-27',23.4,32.0,26.4,93,0.2,8.0,3,50,1,'SW'),
('96529','2026-04-28',24.1,34.6,28.1,86,30.6,8.0,4,20,1,'S'),
('96529','2026-04-29',23.9,34.1,27.6,89,1.6,8.0,4,90,1,'W'),
('96529','2026-04-30',24.1,33.2,26.0,94,1.0,7.0,2,200,1,'S');

INSERT INTO kategori_curah_hujan
(nama_kategori, rr_min, rr_max, keterangan)
VALUES
('Tidak Hujan',0.0,0.0,'Tidak terjadi hujan'),
('Ringan',0.1,20.0,'Curah hujan ringan'),       x
('Sedang',20.1,50.0,'Curah hujan sedang'),
('Lebat',50.1,100.0,'Curah hujan lebat'),
('Sangat Lebat',100.1,9999.9,'Curah hujan sangat lebat');


-- ============================================================
-- OPERASI CRUD - BASIS DATA IKLIM HARIAN KALIMANTAN TIMUR
-- ============================================================

-- ============================================================
-- C. CREATE (INSERT INTO)
-- ============================================================

-- CREATE 1: Menambahkan data pengamatan baru untuk stasiun Balikpapan
INSERT INTO pengamatan
    (id_stasiun, tanggal, tn, tx, tavg, rh_avg, rr, ss, ff_x, ddd_x, ff_avg, ddd_car)
VALUES
    ('96633', '2026-05-01', 24.5, 33.0, 28.2, 86, 0.0, 7.8, 3, 90, 1, 'E');

-- CREATE 2: Menambahkan data pengamatan baru untuk stasiun Samarinda
INSERT INTO pengamatan
    (id_stasiun, tanggal, tn, tx, tavg, rh_avg, rr, ss, ff_x, ddd_x, ff_avg, ddd_car)
VALUES
    ('96607', '2026-05-01', 23.8, 34.2, 28.5, 80, 2.1, 8.0, 4, 70, 2, 'NE');

-- ============================================================
-- R. READ (SELECT)
-- ============================================================

-- READ 1: SELECT sederhana dengan WHERE dan ORDER BY
-- Menampilkan hari-hari dengan suhu rata-rata di atas 28°C, diurutkan terpanas
SELECT
    s.kota              AS stasiun,
    p.tanggal,
    p.tavg              AS suhu_rata,
    p.tx                AS suhu_maks,
    p.rh_avg            AS kelembapan
FROM pengamatan p
JOIN stasiun s ON p.id_stasiun = s.id_stasiun
WHERE p.tavg > 28.0
ORDER BY p.tavg DESC;

-- READ 2: Fungsi agregat COUNT, AVG, SUM, MAX, MIN
-- Statistik keseluruhan semua stasiun
SELECT
    COUNT(*)                AS jumlah_data,
    ROUND(AVG(tavg), 2)     AS rata_suhu,
    ROUND(SUM(rr), 2)       AS total_hujan,
    MAX(tx)                 AS suhu_tertinggi,
    MIN(tn)                 AS suhu_terendah,
    ROUND(AVG(rh_avg), 2)   AS rata_kelembapan
FROM pengamatan;

-- READ 3: GROUP BY – rata-rata cuaca per stasiun
-- Mengelompokkan statistik iklim per kota
SELECT
    s.kota                          AS stasiun,
    COUNT(p.id_pengamatan)          AS jumlah_hari,
    ROUND(AVG(p.tavg), 2)           AS rata_suhu,
    ROUND(AVG(p.rh_avg), 2)         AS rata_kelembapan,
    ROUND(SUM(p.rr), 2)             AS total_curah_hujan,
    MAX(p.tx)                       AS suhu_maks,
    MIN(p.tn)                       AS suhu_min
FROM pengamatan p
JOIN stasiun s ON p.id_stasiun = s.id_stasiun
GROUP BY s.kota
ORDER BY rata_suhu DESC;

-- READ 4: JOIN dua tabel + kategori curah hujan
-- Menampilkan data pengamatan beserta kategori curah hujannya
SELECT
    s.kota              AS stasiun,
    p.tanggal,
    p.tavg              AS suhu,
    p.rr                AS curah_hujan,
    CASE
        WHEN p.rr IS NULL       THEN 'Tidak Terukur'
        WHEN p.rr = 0           THEN 'Tidak Hujan'
        WHEN p.rr <= 20         THEN 'Ringan'
        WHEN p.rr <= 50         THEN 'Sedang'
        WHEN p.rr <= 100        THEN 'Lebat'
        ELSE                         'Sangat Lebat'
    END                 AS kategori_hujan
FROM pengamatan p
JOIN stasiun s ON p.id_stasiun = s.id_stasiun
ORDER BY s.kota, p.tanggal;

-- READ 5: Query untuk visualisasi – tren suhu dan hujan harian per stasiun
-- Digunakan sebagai data source grafik di dashboard
SELECT
    s.kota              AS stasiun,
    p.tanggal,
    p.tn,
    p.tx,
    p.tavg,
    p.rr,
    p.rh_avg,
    p.ss
FROM pengamatan p
JOIN stasiun s ON p.id_stasiun = s.id_stasiun
ORDER BY s.kota, p.tanggal;

-- ============================================================
-- U. UPDATE (UPDATE ... SET ... WHERE)
-- ============================================================

-- UPDATE 1: Mengoreksi nilai curah hujan yang salah input
-- (misal: tanggal 09 April Balikpapan tercatat 28.0 seharusnya 18.0)
UPDATE pengamatan
SET rr = 18.0
WHERE id_stasiun = '96633'
  AND tanggal   = '2026-04-09';

-- UPDATE 2: Memperbarui id_kategori berdasarkan nilai rr yang sudah ada
UPDATE pengamatan
SET id_kategori = CASE
    WHEN rr IS NULL  THEN NULL
    WHEN rr = 0      THEN 1   -- Tidak Hujan
    WHEN rr <= 20    THEN 2   -- Ringan
    WHEN rr <= 50    THEN 3   -- Sedang
    WHEN rr <= 100   THEN 4   -- Lebat
    ELSE                  5   -- Sangat Lebat
END;

-- ============================================================
-- D. DELETE (DELETE FROM ... WHERE)
-- ============================================================

-- DELETE 1: Menghapus data outlier – suhu maks ekstrem tidak wajar (> 40°C)
DELETE FROM pengamatan
WHERE tx > 40.0;

-- DELETE 2: Menghapus data duplikat jika ada stasiun + tanggal yang sama
-- (jalankan hanya jika constraint UNIQUE belum aktif)
DELETE p1
FROM pengamatan p1
INNER JOIN pengamatan p2
    ON  p1.id_stasiun = p2.id_stasiun
    AND p1.tanggal    = p2.tanggal
    AND p1.id_pengamatan > p2.id_pengamatan;


#hubungkan kategori dengan pengamatan
ALTER TABLE pengamatan 
ADD id_kategori INT;

ALTER TABLE pengamatan
ADD CONSTRAINT fk_kategori
FOREIGN KEY (id_kategori)
REFERENCES kategori_curah_hujan(id_kategori);

SELECT tanggal, tn, tx, tavg, rh_avg
FROM pengamatan
WHERE id_stasiun IN ('96529','96607','96633')
AND tavg > 28.0
ORDER BY tanggal;

SELECT
AVG(tavg) AS rata_suhu,
SUM(rr) AS total_hujan,
MAX(tx) AS suhu_maks,
MIN(tn) AS suhu_min
FROM pengamatan;

SELECT
s.nama_stasiun,
ROUND(AVG(p.tavg),2) AS rata_suhu
FROM pengamatan p
JOIN stasiun s
ON p.id_stasiun=s.id_stasiun
GROUP BY s.nama_stasiun;

SELECT
s.nama_stasiun,
ROUND(SUM(p.rr),2) AS total_hujan
FROM pengamatan p
JOIN stasiun s
ON p.id_stasiun=s.id_stasiun
GROUP BY s.nama_stasiun;



