<?php
header("Content-Type: application/json; charset=utf-8");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//fungsi untuk mengirimkan respons JSON dan menghentikan eksekusi skrip
function kirim($data, $kode = 200)
{
    http_response_code($kode);
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

try {
    //koneksi ke database MySQL
    $conn = new mysqli("localhost", "root", "", "gcpbandung");
    $conn->set_charset("utf8mb4");

    // ===== GET: ambil semua data gcp=====
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $rows = $conn->query("SELECT id, desa, kec, latitude, longitude, ellipsoid FROM gcp ORDER BY id")
            ->fetch_all(MYSQLI_ASSOC);

        kirim(array_map(fn($r) => [
            "id"        => (int) $r["id"],
            "desa"      => $r["desa"],
            "kec"       => $r["kec"],
            "latitude"  => (float) $r["latitude"],
            "longitude" => (float) $r["longitude"],
            "ellipsoid" => (float) $r["ellipsoid"],
        ], $rows));
    }

    // ===== POST: menerima permintaan create / update / delete =====
    $in = json_decode(file_get_contents("php://input"), true);
    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !is_array($in)) {
        kirim(["error" => "Permintaan tidak valid."], 400);
    }

    $aksi = $in["action"] ?? "";
    $id   = (int) ($in["id"] ?? 0);

    if ($aksi === "delete") {
        if ($id <= 0) kirim(["error" => "ID tidak valid."], 422);

        $stmt = $conn->prepare("DELETE FROM gcp WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if (!$stmt->affected_rows) kirim(["error" => "Data dengan ID $id tidak ditemukan."], 404);
        kirim(["message" => "Data titik ID $id berhasil dihapus."]);
    }

    if (!in_array($aksi, ["create", "update"], true)) {
        kirim(["error" => "Aksi tidak dikenal."], 400);
    }

    // Validasi (dipakai create dan update)
    $desa = trim($in["desa"] ?? "");
    $kec  = trim($in["kec"] ?? "");
    $lat  = $in["latitude"] ?? "";
    $lon  = $in["longitude"] ?? "";
    $ell  = $in["ellipsoid"] ?? "";

    if ($desa === "" || $kec === "") {
        kirim(["error" => "Desa dan kecamatan wajib diisi."], 422);
    }
    if (!is_numeric($lat) || !is_numeric($lon) || !is_numeric($ell)) {
        kirim(["error" => "Latitude, longitude, dan ellipsoid harus berupa angka."], 422);
    }
    [$lat, $lon, $ell] = [(float) $lat, (float) $lon, (float) $ell];
    if (abs($lat) > 90 || abs($lon) > 180) {
        kirim(["error" => "Latitude harus -90 s/d 90 dan longitude -180 s/d 180."], 422);
    }

    if ($aksi === "create") {
        $id = (int) $conn->query("SELECT COALESCE(MAX(id), 0) + 1 FROM gcp")->fetch_row()[0];

        $stmt = $conn->prepare("INSERT INTO gcp (id, desa, kec, latitude, longitude, ellipsoid) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddd", $id, $desa, $kec, $lat, $lon, $ell);
        $stmt->execute();

        kirim([
            "message" => "Titik \"$desa\" berhasil ditambahkan dengan ID $id. Letakkan fotonya di images/titik$id.jpg",
            "id"      => $id,
        ], 201);
    }

    if ($id <= 0) kirim(["error" => "ID tidak valid."], 422);

    $stmt = $conn->prepare("UPDATE gcp SET desa = ?, kec = ?, latitude = ?, longitude = ?, ellipsoid = ? WHERE id = ?");
    $stmt->bind_param("ssdddi", $desa, $kec, $lat, $lon, $ell, $id);
    $stmt->execute();

    kirim(["message" => "Data titik ID $id berhasil diperbarui."]);
} catch (Throwable $e) {
    kirim(["error" => "Kesalahan server: " . $e->getMessage()], 500);
}
