<?php
session_start();

if(!isset($_SESSION["user_id"])) {
    header("Location: ../index/user/login.php");
    exit();
}

require "../requirement/pdo.php";


$stmt = $pdo->prepare("
SELECT * FROM credenziali
WHERE user_id = :uid
");

$stmt->execute([
":uid" => $_SESSION["user_id"]
]);

$credentials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
</head>
<body>

<h1>Dashboard</h1>

<h2>Aggiungi credenziale</h2>

<form method="POST" action="add_credential.php">

Servizio:<br>
<input type="text" name="service_name"><br><br>

Username:<br>
<input type="text" name="username"><br><br>

Password:<br>
<input type="password" name="password"><br><br>

URL:<br>
<input type="text" name="url"><br><br>

Note:<br>
<textarea name="notes"></textarea><br><br>

<input type="submit" value="Salva">

</form>

<hr>

<h2>Le tue credenziali</h2>

<table border="1">

<tr>
<th>Servizio</th>
<th>Username</th>
<th>Password</th>
<th>Azioni</th>
</tr>

<?php foreach($credentials as $cred): ?>

<tr>

<td><?php echo htmlspecialchars($cred["service_name"]); ?></td>

<td><?php echo htmlspecialchars($cred["username"]); ?></td>

<td>********</td>

<td>

<a href="edit_credential.php?id=<?php echo $cred["credential_id"]; ?>">
Modifica
</a>

|

<a href="delete_credential.php?id=<?php echo $cred["credential_id"]; ?>">
Elimina
</a>

</td>

</tr>

<?php endforeach; ?>

</table>

</body>
</html>