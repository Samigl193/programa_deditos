include("conexion.php");

function togglePassword() {
    const input = document.getElementById("password");

    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}

/*esto llama al panel para que lo que esta en panel funcione el script al presionar ingresar en el login*/
function loginRedirect() {
    window.location.href = "panel.html";
    return false;

}


