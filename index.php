<?php
header("Content-Type: application/json; charset=utf-8");
 
$host     = "localhost";
$user     = "root";
$password = "";
$database = "gcpbandung";
$tabel    = "gcp";
 
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, $user, $password, $database);
 
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi database gagal: " . $conn->connect_error]);
    exit;
}
 
$conn->set_charset("utf8mb4");
$result = $conn->query("SELECT id, desa, kec, latitude, longitude, ellipsoid FROM `$tabel` ORDER BY id ASC");
 
if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "Query gagal: " . $conn->error]);
    $conn->close();
    exit;
}
 
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id"        => (int) $row["id"],
        "desa"      => $row["desa"],
        "kec"       => $row["kec"],
        "latitude"  => (float) $row["latitude"],
        "longitude" => (float) $row["longitude"],
        "ellipsoid" => (float) $row["ellipsoid"],
    ];
}
 
$result->free();
$conn->close();
 
echo json_encode($data, JSON_UNESCAPED_UNICODE);
 