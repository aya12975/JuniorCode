<?php

echo "admin: " . password_hash("123456", PASSWORD_DEFAULT) . "<br><br>";
echo "Aya: " . password_hash("1234567", PASSWORD_DEFAULT) . "<br><br>";
echo "Hanine: " . password_hash("12345678", PASSWORD_DEFAULT);
?>

