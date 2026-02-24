<?php
session_start();


$nombre_empleado = "Juan Pérez";
$cargo = "Administrativo";
$email = "j.perez@vina.cl";


?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel Empleado - Sistema Viña</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font para iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Flatpickr - Calendario simple -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>


</head>
<body>

<div class="panel-container">

    <div class="panel-box">

        <div class="panel-header">
            <h1><i class="fas fa-user-circle"></i> Bienvenido al Panel de Empleado</h1>  
            <p><i class="fas fa-id-card"></i> Tu sistema de gestión de personal - Viña</p>
        </div>

        <!-- Secciones del panel -->
        <div class="sections">

            <!-- Tarjeta de SOLICITUD DE PERMISO -->
            <div class="card permiso-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Solicitar Permiso</h3>
                
                <!-- Formulario con combo box de motivos -->
                <form id="formPermiso" class="permiso-form">
                    <!-- Selector de tipo de permiso -->
                    <div class="form-group">
                        <label for="tipo_permiso">
                            <i class="fas fa-clock"></i> Tipo de permiso
                        </label>
                        <select id="tipo_permiso" name="tipo_permiso" required>
                            <option value="">-- Selecciona tipo de permiso --</option>
                            <option value="1dia">📅 Permiso de 1 dia </option>
                            <option value="varios">📆 Permiso de varios dias (Calendarizado) </option>
                        </select>
                    </div>

                    <!-- Contenedor para fecha única (1 día) -->
                    <div class="form-group" id="fecha_unica_container">
                        <label for="fecha_permiso">
                            <i class="fas fa-calendar-alt"></i> Fecha del permiso
                        </label>
                        <input type="text" id="fecha_permiso" placeholder="Selecciona una fecha" readonly>
                    </div>

                    <!-- Contenedor para selección múltiple de días -->
                    <div id="multiselect_container" style="display: none;">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-calendar-check"></i> Selecciona los días de permiso 
                            </label>
                            <div class="multiselect-calendar">
                                <div class="calendar-navigation">
                                    <button type="button" class="month-nav" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                                    <span id="currentMonthYear">Febrero 2026</span>
                                    <button type="button" class="month-nav" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                <div class="calendar-weekdays">
                                    <div>Lu</div>
                                    <div>Ma</div>
                                    <div>Mi</div>
                                    <div>Ju</div>
                                    <div>Vi</div>
                                    <div>Sá</div>
                                    <div>Do</div>
                                </div>
                                <div class="calendar-days" id="calendarDays"></div>
                            </div>
                            <div class="selected-days-info">
                                <p><i class="fas fa-info-circle"></i> Días seleccionados: <span id="selectedCount"></span></p>
                            </div>
                            <input type="hidden" id="dias_seleccionados" name="dias_seleccionados">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tipo_motivo">
                            <i class="fas fa-list"></i> Tipo de motivo
                        </label>
                        <select id="tipo_motivo" name="tipo_motivo">
                            <option value="">-- Selecciona un motivo --</option>
                            <option value="IGGS">🏥 IGGS</option>
                            <option value="Enfermo">🤒 Enfermo</option>
                            <option value="Propositos personales">📋 Propósitos personales</option>
                            <option value="otro">✏️ Otro (especificar)</option>
                        </select>
                    </div>

                    <!-- Contenedor para motivo personalizado (aparece solo con "Otro") -->
                    <div id="motivo_personalizado_container">
                        <div class="form-group">
                            <label for="motivo_personalizado">
                                <i class="fas fa-pen"></i> Especifica el motivo
                            </label>
                            <textarea id="motivo_personalizado" rows="3" placeholder="Porfavor escribe el motivo de tu permiso..."></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-enviar">
                        <i class="fas fa-paper-plane"></i> Enviar solicitud
                    </button>

                    <div id="mensaje" class="mensaje-confirmacion"></div>
                </form>
            </div>

            <!-- Tarjeta de HORARIOS -->
            <div class="card">
                <i class="fas fa-clock"></i>
                <h3>Horarios</h3>
                <p>Consulta tus turnos, jornadas laborales y horarios asignados. Visualiza tu calendario de trabajo.</p>
                <a href="horarios_empleado.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Ver Horarios
                </a>
            </div>

        </div>

        <!-- Botón de cerrar sesión -->
        <div style="text-align: center;">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt" style="margin-right: 8px;"></i>
                Cerrar Sesión
            </a>
        </div>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Configuración del calendario para 1 día
        const fechaPicker = flatpickr("#fecha_permiso", {
            locale: 'es',
            dateFormat: "d/m/Y",
            minDate: "today",
            altInput: true,
            altFormat: "j F, Y",
            placeholder: "Selecciona una fecha"
        });

        // Variables para el calendario de selección múltiple
        let currentDate = new Date();
        let selectedDays = [];
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        // Elementos del DOM
        const tipoPermiso = document.getElementById('tipo_permiso');
        const fechaUnicaContainer = document.getElementById('fecha_unica_container');
        const multiselectContainer = document.getElementById('multiselect_container');
        const tipoMotivo = document.getElementById('tipo_motivo');
        const motivoPersonalizadoContainer = document.getElementById('motivo_personalizado_container');
        const motivoPersonalizado = document.getElementById('motivo_personalizado');
        const form = document.getElementById('formPermiso');
        const mensajeDiv = document.getElementById('mensaje');
        const calendarDays = document.getElementById('calendarDays');
        const currentMonthYear = document.getElementById('currentMonthYear');
        const prevMonth = document.getElementById('prevMonth');
        const nextMonth = document.getElementById('nextMonth');
        const selectedCount = document.getElementById('selectedCount');
        const selectedDaysList = document.getElementById('selectedDaysList');
        const diasSeleccionados = document.getElementById('dias_seleccionados');

        // Función para actualizar el calendario
        function renderCalendar() {
            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            const startingDay = firstDay.getDay(); // 0 = Domingo, 1 = Lunes, etc.
            
            // Ajustar para que la semana empiece en Lunes (1) en lugar de Domingo (0)
            let startOffset = startingDay === 0 ? 6 : startingDay - 1;
            
            const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            currentMonthYear.textContent = `${monthNames[currentMonth]} ${currentYear}`;

            let calendarHTML = '';
            
            // Días del mes anterior
            const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
            for (let i = 0; i < startOffset; i++) {
                const day = prevMonthLastDay - startOffset + i + 1;
                calendarHTML += `<div class="calendar-day other-month">${day}</div>`;
            }

            // Días del mes actual
            const today = new Date();
            const isCurrentMonth = today.getMonth() === currentMonth && today.getFullYear() === currentYear;
            
            for (let d = 1; d <= lastDay.getDate(); d++) {
                const dateStr = `${d.toString().padStart(2, '0')}/${(currentMonth + 1).toString().padStart(2, '0')}/${currentYear}`;
                const isSelected = selectedDays.includes(dateStr);
                const isPast = new Date(currentYear, currentMonth, d) < new Date(today.setHours(0,0,0,0));
                
                let classes = 'calendar-day';
                if (isCurrentMonth && d === today.getDate()) classes += ' today';
                if (isSelected) classes += ' selected';
                if (isPast) classes += ' past';
                
                calendarHTML += `<div class="${classes}" data-date="${dateStr}" data-day="${d}" data-month="${currentMonth}" data-year="${currentYear}">${d}</div>`;
            }

            // Días del mes siguiente para completar la cuadrícula
            const totalDays = Math.ceil((startOffset + lastDay.getDate()) / 7) * 7;
            const nextMonthDays = totalDays - (startOffset + lastDay.getDate());
            for (let i = 1; i <= nextMonthDays; i++) {
                calendarHTML += `<div class="calendar-day other-month">${i}</div>`;
            }

            calendarDays.innerHTML = calendarHTML;

            // Agregar event listeners a los días
            document.querySelectorAll('.calendar-day:not(.other-month):not(.past)').forEach(day => {
                day.addEventListener('click', function() {
                    const dateStr = this.dataset.date;
                    
                    if (selectedDays.includes(dateStr)) {
                        // Quitar selección
                        selectedDays = selectedDays.filter(d => d !== dateStr);
                        this.classList.remove('selected');
                    } else {
                        // Agregar selección si no se ha alcanzado el límite
                        if (selectedDays.length < 10) {
                            selectedDays.push(dateStr);
                            this.classList.add('selected');
                        } else {
                            mostrarMensaje('❌ Has alcanzado el límite máximo de 10 días', 'error');
                        }
                    }
                    
                    updateSelectedDaysInfo();
                });
            });
        }

        // Función para actualizar la información de días seleccionados
        function updateSelectedDaysInfo() {
            selectedCount.textContent = selectedDays.length;
            
            // Ordenar fechas
            selectedDays.sort((a, b) => {
                const [da, ma, ya] = a.split('/').map(Number);
                const [db, mb, yb] = b.split('/').map(Number);
                return new Date(ya, ma-1, da) - new Date(yb, mb-1, db);
            });
            
            // Mostrar lista de días seleccionados
            if (selectedDays.length > 0) {
                let listHTML = '<p><i class="fas fa-check-circle"></i> Días seleccionados:</p><ul>';
                selectedDays.forEach(date => {
                    listHTML += `<li>📅 ${date}</li>`;
                });
                listHTML += '</ul>';
                selectedDaysList.innerHTML = listHTML;
            } else {
                selectedDaysList.innerHTML = '';
            }
            
            // Actualizar campo oculto
            diasSeleccionados.value = selectedDays.join(',');
        }

        // Navegación del calendario
        prevMonth.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        });

        nextMonth.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        });

        // Mostrar/ocultar campos según tipo de permiso seleccionado
        tipoPermiso.addEventListener('change', function() {
            if (this.value === '1dia') {
                fechaUnicaContainer.style.display = 'block';
                multiselectContainer.style.display = 'none';
                // Limpiar selección múltiple
                selectedDays = [];
                updateSelectedDaysInfo();
                if (fechaPicker) {
                    fechaPicker.clear();
                }
            } else if (this.value === 'varios') {
                fechaUnicaContainer.style.display = 'none';
                multiselectContainer.style.display = 'block';
                // Limpiar fecha única
                if (fechaPicker) {
                    fechaPicker.clear();
                }
                // Renderizar calendario
                renderCalendar();
            }
        });

        // Mostrar/ocultar campo de motivo personalizado según selección
        tipoMotivo.addEventListener('change', function() {
            if (this.value === 'otro') {
                motivoPersonalizadoContainer.style.display = 'block';
                motivoPersonalizado.setAttribute('required', 'required');
            } else {
                motivoPersonalizadoContainer.style.display = 'none';
                motivoPersonalizado.removeAttribute('required');
                motivoPersonalizado.value = ''; // Limpiar el campo
            }
        });

        // Manejo del formulario
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const tipoPermisoVal = tipoPermiso.value;
            const tipo = tipoMotivo.value;

            // Validar que se haya seleccionado tipo de permiso
            if (!tipoPermisoVal) {
                mostrarMensaje('Por favor selecciona el tipo de permiso', 'error');
                return;
            }

            // Validar fechas según tipo de permiso
            let fechaVal = '';
            if (tipoPermisoVal === '1dia') {
                fechaVal = document.getElementById('fecha_permiso').value;
                if (!fechaVal) {
                    mostrarMensaje('Por favor selecciona una fecha', 'error');
                    return;
                }
            } else {
                fechaVal = diasSeleccionados.value;
                if (!fechaVal) {
                    mostrarMensaje('Por favor selecciona al menos un día', 'error');
                    return;
                }
            }

            // Validar que se haya seleccionado un motivo
            if (!tipo) {
                mostrarMensaje('Por favor selecciona un tipo de motivo', 'error');
                return;
            }

            // Determinar el motivo final
            let motivoFinal = '';
            if (tipo === 'otro') {
                motivoFinal = motivoPersonalizado.value.trim();
                if (!motivoFinal) {
                    mostrarMensaje('Por favor especifica el motivo', 'error');
                    return;
                }
                if (motivoFinal.length < 5) {
                    mostrarMensaje('El motivo debe tener al menos 5 caracteres', 'error');
                    return;
                }
            } else {
                motivoFinal = tipo; // Usar el valor del select
            }

            // Enviar a PHP mediante AJAX
            const formData = new FormData();
            formData.append('tipo_permiso', tipoPermisoVal);
            formData.append('fecha', fechaVal);
            formData.append('motivo', motivoFinal);

            fetch('procesar_permiso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje('✅ ' + data.message, 'exito');
                    
                    // Limpiar formulario
                    form.reset();
                    fechaPicker.clear();
                    selectedDays = [];
                    updateSelectedDaysInfo();
                    motivoPersonalizadoContainer.style.display = 'none';
                    fechaUnicaContainer.style.display = 'block';
                    multiselectContainer.style.display = 'none';
                    tipoPermiso.value = '';
                    
                    // Volver a mes actual
                    currentDate = new Date();
                    currentMonth = currentDate.getMonth();
                    currentYear = currentDate.getFullYear();
                } else {
                    mostrarMensaje('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                mostrarMensaje('❌ Error al conectar con el servidor', 'error');
                console.error('Error:', error);
            });
        });

        function mostrarMensaje(texto, tipo) {
            mensajeDiv.textContent = texto;
            mensajeDiv.className = 'mensaje-confirmacion ' + tipo;
            
            setTimeout(() => {
                mensajeDiv.className = 'mensaje-confirmacion';
            }, 3000);
        }
    });
</script>

</body>
</html>

<style>
    /* Estilo general */
    body {
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #526513, #06a388);
        min-height: 100vh;
    }

    /* contenedor principal */
    .panel-container {
        min-height: 100vh;
        padding: 40px 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .panel-box {
        max-width: 900px;
        width: 100%;
        margin: auto;
    }

    /* encabezado */
    .panel-header {
        background: rgb(16, 40, 40);
        padding: 40px 30px;
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(204, 206, 108, 0.4);
        margin-bottom: 40px;
        text-align: center;
        border-bottom: 5px solid #41c7ce;
    }

    .panel-header h1 {
        margin: 0;
        font-size: 38px;
        color: #fdfeff;
        font-weight: 600;
        letter-spacing: 1px;
    }

    .panel-header p {
        margin-top: 15px;
        color: #e0e0e0;
        font-size: 18px;
        font-weight: 300;
    }

    .panel-header i {
        color: #41c7ce;
        margin-right: 10px;
    }

    /* grid para las tarjetas */
    .sections {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
    }

    /* responsive para móviles */
    @media (max-width: 700px) {
        .sections {
            grid-template-columns: 1fr;
        }
        
        .panel-header h1 {
            font-size: 28px;
        }
    }

    /* tarjetas base */
    .card {
        background: white;
        padding: 30px 25px;
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        transition: all 0.4s ease;
        text-align: center;
        border-left: 8px solid #2c7be5;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 35px rgba(0,0,0,0.25);
    }

    .card i {
        font-size: 50px;
        color: #2c7be5;
        margin-bottom: 15px;
    }

    .card h3 {
        margin: 10px 0 15px;
        color: #2c7be5;
        font-size: 24px;
        font-weight: 600;
    }

    /* Tarjeta de horarios */
    .card:last-child p {
        color: #666;
        font-size: 15px;
        line-height: 1.5;
        margin-bottom: 20px;
    }

    .card:last-child a {
        display: inline-block;
        padding: 10px 25px;
        background: #2c7be5;
        color: white;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .card:last-child a:hover {
        background: #41c7ce;
        transform: translateY(-2px);
    }

    /* ===== ESTILOS DEL FORMULARIO DE PERMISOS ===== */
    .permiso-card {
        border-left-color: #41c7ce;
        padding: 25px 20px;
    }

    .permiso-card i {
        color: #41c7ce;
    }

    .permiso-card h3 {
        color: #41c7ce;
        margin-top: 5px;
        margin-bottom: 20px;
    }

    .permiso-form {
        width: 100%;
        text-align: left;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 500;
        font-size: 15px;
    }

    .form-group label i {
        font-size: 14px;
        margin-right: 6px;
        color: #41c7ce;
    }

    /* Campos de fecha y select */
    #fecha_permiso, #tipo_permiso, #tipo_motivo {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 15px;
        font-family: inherit;
        transition: all 0.3s;
        box-sizing: border-box;
        background-color: #f9f9f9;
    }

    #fecha_permiso:focus, #tipo_permiso:focus, #tipo_motivo:focus {
        border-color: #41c7ce;
        outline: none;
        background-color: white;
        box-shadow: 0 0 0 3px rgba(65, 199, 206, 0.2);
    }

    /* SELECT - Combo box de motivos y tipo permiso */
    #tipo_permiso, #tipo_motivo {
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 16px;
    }

    /* Estilos del calendario de selección múltiple */
    .multiselect-calendar {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 15px;
        margin-top: 10px;
    }

    .calendar-navigation {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .month-nav {
        background: #41c7ce;
        color: white;
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .month-nav:hover {
        background: #2c7be5;
        transform: scale(1.1);
    }

    .month-nav i {
        font-size: 14px;
        margin: 0;
        color: white;
    }

    #currentMonthYear {
        font-weight: 600;
        color: #333;
    }

    .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        font-weight: 600;
        color: #41c7ce;
        margin-bottom: 10px;
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
    }

    .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f9f9f9;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
        border: 2px solid transparent;
    }

    .calendar-day:hover:not(.other-month):not(.past) {
        background: #41c7ce;
        color: white;
        transform: scale(1.05);
    }

    .calendar-day.selected {
        background: #2c7be5;
        color: white;
        border-color: #1e5fb4;
    }

    .calendar-day.today {
        border: 2px solid #41c7ce;
        font-weight: 600;
    }

    .calendar-day.other-month {
        color: #ccc;
        cursor: default;
    }

    .calendar-day.past {
        color: #ccc;
        cursor: not-allowed;
        background: #f0f0f0;
    }

    .calendar-day.past:hover {
        background: #f0f0f0;
        transform: none;
    }

    .selected-days-info {
        margin-top: 15px;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 12px;
    }

    .selected-days-list {
        max-height: 100px;
        overflow-y: auto;
        margin-top: 10px;
    }

    .selected-days-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .selected-days-list li {
        padding: 5px 10px;
        background: white;
        border-radius: 6px;
        margin-bottom: 5px;
        font-size: 13px;
        color: #2c7be5;
        border-left: 3px solid #41c7ce;
    }

    /* Contenedor para motivo personalizado (inicialmente oculto) */
    #motivo_personalizado_container {
        display: none;
        margin-top: 15px;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Campo de motivo personalizado */
    #motivo_personalizado {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 15px;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
        box-sizing: border-box;
        background-color: #f9f9f9;
        transition: all 0.3s;
    }

    #motivo_personalizado:focus {
        border-color: #41c7ce;
        outline: none;
        background-color: white;
        box-shadow: 0 0 0 3px rgba(65, 199, 206, 0.2);
    }

    /* Botón de enviar */
    .btn-enviar {
        width: 100%;
        padding: 14px 20px;
        background: #41c7ce;
        color: white;
        border: none;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 10px;
        border: 2px solid transparent;
    }

    .btn-enviar:hover {
        background: #2c7be5;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(44, 123, 229, 0.3);
    }

    .btn-enviar i {
        font-size: 16px;
        margin: 0;
        color: white;
    }

    /* Mensaje de confirmación */
    .mensaje-confirmacion {
        margin-top: 15px;
        padding: 10px;
        border-radius: 10px;
        font-size: 14px;
        text-align: center;
        display: none;
    }

    .mensaje-confirmacion.exito {
        display: block;
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .mensaje-confirmacion.error {
        display: block;
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* estilo del empleado  */
    .empleado-info {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 15px 25px;
        margin-top: 30px;
        text-align: center;
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .empleado-info p {
        margin: 5px 0;
        font-size: 16px;
    }

    .empleado-info i {
        margin-right: 8px;
        color: #41c7ce;
    }

    /* boton para el logout, cerrar sesion del panel */
    .btn-logout {
        display: inline-block;
        margin-top: 30px;
        padding: 10px 25px;
        background: rgba(255,255,255,0.15);
        color: white;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(5px);
    }

    .btn-logout:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
    }
</style>

