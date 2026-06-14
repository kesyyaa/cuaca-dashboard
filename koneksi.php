<?php


$host = "localhost";
$user = "root";
$pass = "";
$db   = "stasiun";

$conn = mysqli_connect($host,$user,$pass,$db);

if(!$conn){
    die("Koneksi gagal");
}
?>