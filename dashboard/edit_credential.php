<?php

session_start();
require "../requirement/pdo.php";

if(!isset($_SESSION["user_id"])) {
    exit();
}

$id = $_GET["id"];

$stmt = $pdo->prepare("
SELECT * FROM credenziali
WHERE credential_id = :id
AND user_id = :uid
");

$stmt->execute([
":id" => $id,
":uid" => $_SESSION["user_id"]
]);

$credential = $stmt->fetch();

if(!$credential){
    echo "Credenziale non trovata";
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Modifica credenziale</title>
</head>
<body>

<h1>Modifica credenziale</h1>

<form method="POST" action="update_credential.php">

<input type="hidden" name="id" value="<?php echo $credential["credential_id"]; ?>">

Servizio:<br>
<input type="text" name="service_name"
value="<?php echo htmlspecialchars($credential["service_name"]); ?>"><br><br>

Username:<br>
<input type="text" name="username"
value="<?php echo htmlspecialchars($credential["username"]); ?>"><br><br>

Password (lascia vuoto per non cambiarla):<br>
<input type="password" name="password"><br><br>

URL:<br>
<input type="text" name="url"
value="<?php echo htmlspecialchars($credential["url"]); ?>"><br><br>

Note:<br>
<textarea name="notes"><?php echo htmlspecialchars($credential["notes"]); ?></textarea><br><br>

<input type="submit" value="Aggiorna">

</form>

</body>
</html>