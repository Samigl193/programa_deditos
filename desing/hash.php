<!--Esto sirve para que la contraseña sea funcional para el login  -->

<?php
echo password_hash("hola123", PASSWORD_DEFAULT);
