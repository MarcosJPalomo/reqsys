<?php
// solicitud_nueva.php - Formulario para crear una nueva solicitud
require_once 'config.php';
verificarSesion();
verificarRol(['solicitante']);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = conectarDB();
    
    // Sanitizar datos del formulario principal
    $usuario_id = (int)$_SESSION['usuario_id'];
    $destinado_a = sanitizar($db, $_POST['destinado_a']);
    $deseado_para = sanitizar($db, $_POST['deseado_para']);
    
    // Iniciar transacción
    $db->begin_transaction();
    
    try {
        // Insertar solicitud
        $query = "INSERT INTO solicitudes (usuario_id, destinado_a, deseado_para) 
                  VALUES ($usuario_id, '$destinado_a', '$deseado_para')";
        
        if (!$db->query($query)) {
            throw new Exception("Error al crear la solicitud: " . $db->error);
        }
        
        $solicitud_id = $db->insert_id;
        
        // Procesar partidas (pueden ser múltiples)
        $partidas = $_POST['partida'];
        $cantidades = $_POST['cantidad'];
        $unidades = $_POST['unidad'];
        $descripciones = $_POST['descripcion'];
        $referencias = isset($_POST['referencias']) ? $_POST['referencias'] : [];
        $comentarios = isset($_POST['comentarios']) ? $_POST['comentarios'] : [];
        
        // Requisitos anexos (arrays de checkboxes)
        $cert_seguridad = isset($_POST['certificado_seguridad']) ? $_POST['certificado_seguridad'] : [];
        $garantia = isset($_POST['garantia']) ? $_POST['garantia'] : [];
        $manual = isset($_POST['manual_operacion']) ? $_POST['manual_operacion'] : [];
        $asesoria = isset($_POST['asesoria']) ? $_POST['asesoria'] : [];
        $otro = isset($_POST['otro']) ? $_POST['otro'] : [];
        $boleta = isset($_POST['boleta_bascula']) ? $_POST['boleta_bascula'] : [];
        $carta = isset($_POST['carta_compromiso']) ? $_POST['carta_compromiso'] : [];
        $ficha = isset($_POST['ficha_tecnica']) ? $_POST['ficha_tecnica'] : [];
        $certificado = isset($_POST['certificado_diploma']) ? $_POST['certificado_diploma'] : [];
        $otro_desc = isset($_POST['otro_descripcion']) ? $_POST['otro_descripcion'] : [];
        
        // Insertar cada partida
        for ($i = 0; $i < count($partidas); $i++) {
            if (empty($partidas[$i]) || empty($cantidades[$i]) || empty($unidades[$i]) || empty($descripciones[$i])) {
                continue; // Saltar partidas vacías
            }
            
            $partida = sanitizar($db, $partidas[$i]);
            $cantidad = (float)$cantidades[$i];
            $unidad = sanitizar($db, $unidades[$i]);
            $descripcion = sanitizar($db, $descripciones[$i]);
            $referencia = isset($referencias[$i]) ? sanitizar($db, $referencias[$i]) : '';
            $comentario = isset($comentarios[$i]) ? sanitizar($db, $comentarios[$i]) : '';
            
            // Insertar partida
            $query = "INSERT INTO partidas_solicitud (solicitud_id, partida, cantidad, unidad, descripcion, referencias, comentarios) 
                      VALUES ($solicitud_id, '$partida', $cantidad, '$unidad', '$descripcion', '$referencia', '$comentario')";
            
            if (!$db->query($query)) {
                throw new Exception("Error al insertar partida: " . $db->error);
            }
            
            $partida_id = $db->insert_id;
            
            // Guardar requisitos anexos
            $cs = isset($cert_seguridad[$i]) ? 1 : 0;
            $ga = isset($garantia[$i]) ? 1 : 0;
            $mo = isset($manual[$i]) ? 1 : 0;
            $as = isset($asesoria[$i]) ? 1 : 0;
            $ot = isset($otro[$i]) ? 1 : 0;
            $bb = isset($boleta[$i]) ? 1 : 0;
            $cc = isset($carta[$i]) ? 1 : 0;
            $ft = isset($ficha[$i]) ? 1 : 0;
            $cd = isset($certificado[$i]) ? 1 : 0;
            $od = isset($otro_desc[$i]) ? sanitizar($db, $otro_desc[$i]) : '';
            
            $query = "INSERT INTO requisitos_anexos (partida_id, certificado_seguridad, garantia, manual_operacion, 
                     asesoria, otro, boleta_bascula, carta_compromiso, ficha_tecnica, certificado_diploma, otro_descripcion) 
                     VALUES ($partida_id, $cs, $ga, $mo, $as, $ot, $bb, $cc, $ft, $cd, '$od')";
            
            if (!$db->query($query)) {
                throw new Exception("Error al insertar requisitos anexos: " . $db->error);
            }
        }
        
        // Confirmar transacción
        $db->commit();
        mostrarAlerta('success', 'Solicitud creada exitosamente con folio: ' . $solicitud_id);
        header('Location: mis_solicitudes.php');
        exit;
        
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $db->rollback();
        mostrarAlerta('danger', $e->getMessage());
    }
    
    $db->close();
}

include 'header.php';
?>

<div class="card shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-file-earmark-plus"></i> Nueva Solicitud de Compra</h5>
    </div>
    <div class="card-body">
        <form id="solicitudForm" method="POST" action="solicitud_nueva.php">
            <!-- Datos generales de la solicitud -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="destinado_a" class="form-label">Destinado a (Departamento) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="destinado_a" name="destinado_a" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="deseado_para" class="form-label">Deseado para (Fecha) <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="deseado_para" name="deseado_para" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="fecha_solicitud" class="form-label">Fecha de Solicitud</label>
                        <input type="text" class="form-control" id="fecha_solicitud" value="<?php echo date('d/m/Y'); ?>" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Tabla para partidas -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-hover" id="tablaParts">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 10%">Partida <span class="text-danger">*</span></th>
                            <th style="width: 10%">Cantidad <span class="text-danger">*</span></th>
                            <th style="width: 10%">Unidad <span class="text-danger">*</span></th>
                            <th style="width: 35%">Descripción <span class="text-danger">*</span></th>
                            <th style="width: 15%">Referencias</th>
                            <th style="width: 15%">Comentarios</th>
                            <th style="width: 5%">Requisitos</th>
                            <th style="width: 5%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" class="form-control" name="partida[]" required></td>
                            <td><input type="number" step="0.01" min="0.01" class="form-control" name="cantidad[]" required></td>
                            <td><input type="text" class="form-control" name="unidad[]" required></td>
                            <td><textarea class="form-control" name="descripcion[]" rows="2" required></textarea></td>
                            <td><input type="text" class="form-control" name="referencias[]"></td>
                            <td><textarea class="form-control" name="comentarios[]" rows="2"></textarea></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-requisitos" data-bs-toggle="modal" data-bs-target="#requisitosModal" data-index="0">
                                    <i class="bi bi-list-check"></i>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger btn-eliminar" disabled>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-success" id="btnAgregarPartida">
                    <i class="bi bi-plus-circle"></i> Agregar Partida
                </button>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="mis_solicitudes.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Requisitos Anexos -->
<div class="modal fade" id="requisitosModal" tabindex="-1" aria-labelledby="requisitosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requisitosModalLabel">Requisitos Anexos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentIndex" value="0">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="certificado_seguridad_modal">
                            <label class="form-check-label" for="certificado_seguridad_modal">Certificado de seguridad</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="garantia_modal">
                            <label class="form-check-label" for="garantia_modal">Garantía</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="manual_operacion_modal">
                            <label class="form-check-label" for="manual_operacion_modal">Manual de operación</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="asesoria_modal">
                            <label class="form-check-label" for="asesoria_modal">Asesoría</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="otro_modal">
                            <label class="form-check-label" for="otro_modal">Otro</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="boleta_bascula_modal">
                            <label class="form-check-label" for="boleta_bascula_modal">Boleta de báscula</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="carta_compromiso_modal">
                            <label class="form-check-label" for="carta_compromiso_modal">Carta compromiso</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="ficha_tecnica_modal">
                            <label class="form-check-label" for="ficha_tecnica_modal">Ficha técnica</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input req-check" type="checkbox" id="certificado_diploma_modal">
                            <label class="form-check-label" for="certificado_diploma_modal">Certificado o diploma</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3" id="otroDescContainer">
                    <label for="otro_descripcion_modal" class="form-label">Especifique otro requisito:</label>
                    <input type="text" class="form-control" id="otro_descripcion_modal">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarRequisitos">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let rowCount = 1;
        
        // Ocultar campo de otro requisito inicialmente
        $("#otroDescContainer").hide();
        
        // Mostrar/ocultar campo de otro requisito cuando se marca/desmarca
        $("#otro_modal").change(function() {
            if($(this).is(":checked")) {
                $("#otroDescContainer").show();
            } else {
                $("#otroDescContainer").hide();
                $("#otro_descripcion_modal").val('');
            }
        });
        
        // Agregar nueva fila a la tabla de partidas
        $("#btnAgregarPartida").click(function() {
            rowCount++;
            let newRow = `
                <tr>
                    <td><input type="text" class="form-control" name="partida[]" required></td>
                    <td><input type="number" step="0.01" min="0.01" class="form-control" name="cantidad[]" required></td>
                    <td><input type="text" class="form-control" name="unidad[]" required></td>
                    <td><textarea class="form-control" name="descripcion[]" rows="2" required></textarea></td>
                    <td><input type="text" class="form-control" name="referencias[]"></td>
                    <td><textarea class="form-control" name="comentarios[]" rows="2"></textarea></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-requisitos" data-bs-toggle="modal" data-bs-target="#requisitosModal" data-index="${rowCount-1}">
                            <i class="bi bi-list-check"></i>
                        </button>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $("#tablaParts tbody").append(newRow);
            
            // Agregar los campos ocultos para los requisitos anexos
            agregarCamposRequisitos(rowCount-1);
        });
        
        // Eliminar fila de partida
        $(document).on("click", ".btn-eliminar", function() {
            $(this).closest("tr").remove();
        });
        
        // Cuando se abre el modal de requisitos, cargar los datos actuales
        $(document).on("click", ".btn-requisitos", function() {
            let index = $(this).data('index');
            $("#currentIndex").val(index);
            
            // Limpiar modal
            $(".req-check").prop('checked', false);
            $("#otro_descripcion_modal").val('');
            $("#otroDescContainer").hide();
            
            // Cargar valores actuales si existen
            $(`input[name="certificado_seguridad[${index}]"]`).is(":checked") && $("#certificado_seguridad_modal").prop('checked', true);
            $(`input[name="garantia[${index}]"]`).is(":checked") && $("#garantia_modal").prop('checked', true);
            $(`input[name="manual_operacion[${index}]"]`).is(":checked") && $("#manual_operacion_modal").prop('checked', true);
            $(`input[name="asesoria[${index}]"]`).is(":checked") && $("#asesoria_modal").prop('checked', true);
            $(`input[name="boleta_bascula[${index}]"]`).is(":checked") && $("#boleta_bascula_modal").prop('checked', true);
            $(`input[name="carta_compromiso[${index}]"]`).is(":checked") && $("#carta_compromiso_modal").prop('checked', true);
            $(`input[name="ficha_tecnica[${index}]"]`).is(":checked") && $("#ficha_tecnica_modal").prop('checked', true);
            $(`input[name="certificado_diploma[${index}]"]`).is(":checked") && $("#certificado_diploma_modal").prop('checked', true);
            
            if($(`input[name="otro[${index}]"]`).is(":checked")) {
                $("#otro_modal").prop('checked', true);
                $("#otroDescContainer").show();
                $("#otro_descripcion_modal").val($(`input[name="otro_descripcion[${index}]"]`).val());
            }
        });
        
        // Guardar los requisitos al cerrar el modal
        $("#btnGuardarRequisitos").click(function() {
            let index = $("#currentIndex").val();
            
            $(`input[name="certificado_seguridad[${index}]"]`).prop('checked', $("#certificado_seguridad_modal").is(":checked"));
            $(`input[name="garantia[${index}]"]`).prop('checked', $("#garantia_modal").is(":checked"));
            $(`input[name="manual_operacion[${index}]"]`).prop('checked', $("#manual_operacion_modal").is(":checked"));
            $(`input[name="asesoria[${index}]"]`).prop('checked', $("#asesoria_modal").is(":checked"));
            $(`input[name="otro[${index}]"]`).prop('checked', $("#otro_modal").is(":checked"));
            $(`input[name="boleta_bascula[${index}]"]`).prop('checked', $("#boleta_bascula_modal").is(":checked"));
            $(`input[name="carta_compromiso[${index}]"]`).prop('checked', $("#carta_compromiso_modal").is(":checked"));
            $(`input[name="ficha_tecnica[${index}]"]`).prop('checked', $("#ficha_tecnica_modal").is(":checked"));
            $(`input[name="certificado_diploma[${index}]"]`).prop('checked', $("#certificado_diploma_modal").is(":checked"));
            $(`input[name="otro_descripcion[${index}]"]`).val($("#otro_descripcion_modal").val());
            
            // Cerrar modal
            $('#requisitosModal').modal('hide');
        });
        
        // Función para agregar campos de requisitos ocultos al formulario
        function agregarCamposRequisitos(index) {
            // Verificar si ya existen los campos
            if ($(`input[name="certificado_seguridad[${index}]"]`).length === 0) {
                let campos = `
                    <input type="hidden" name="certificado_seguridad[${index}]" value="0">
                    <input type="hidden" name="garantia[${index}]" value="0">
                    <input type="hidden" name="manual_operacion[${index}]" value="0">
                    <input type="hidden" name="asesoria[${index}]" value="0">
                    <input type="hidden" name="otro[${index}]" value="0">
                    <input type="hidden" name="boleta_bascula[${index}]" value="0">
                    <input type="hidden" name="carta_compromiso[${index}]" value="0">
                    <input type="hidden" name="ficha_tecnica[${index}]" value="0">
                    <input type="hidden" name="certificado_diploma[${index}]" value="0">
                    <input type="hidden" name="otro_descripcion[${index}]" value="">
                `;
                $("#solicitudForm").append(campos);
            }
        }
        
        // Agregar campos de requisitos para la primera fila
        agregarCamposRequisitos(0);
    });
</script>

<?php include 'footer.php'; ?>