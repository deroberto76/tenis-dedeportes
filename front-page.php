<?php
/**
 * Plantilla de la página de inicio (Portada)
 */
get_header();


// START: DATOS CONEXIÓN
$pdo_result = null;
if (function_exists('get_tenis_db_connection')) {
    $pdo_result = get_tenis_db_connection();
}

$partidos_hoy = [];
$todos_partidos = [];
$mejores_rendimientos = [];
$error_bd = "";

if ($pdo_result instanceof PDO) {
    $pdo = $pdo_result;
    // REEMPLAZAR 'nombre_tabla' POR EL NOMBRE DE LA TABLA EN LA BASE DE DATOS
    $nombre_tabla = 'partidos';

    try {
        // Query: Partidos de Hoy
        // Según la imagen, los partidos tienen estado = 'por jugar' (o podría ser curdate)
        $stmtHoy = $pdo->prepare("SELECT * FROM $nombre_tabla WHERE estado = 'por jugar' OR fecha = CURDATE() ORDER BY fecha ASC LIMIT 5");
        $stmtHoy->execute();
        $partidos_hoy = $stmtHoy->fetchAll(PDO::FETCH_ASSOC);

        // Query: Todos los Partidos
        // Asumo todos los 'finalizado' recientes, excluyendo los que ya se muestran en 'Partidos de Hoy'
        $ids_hoy = array_column($partidos_hoy, 'ID'); // Extraer IDs

        if (!empty($ids_hoy)) {
            $in_clause = implode(',', array_fill(0, count($ids_hoy), '?'));
            $stmtTodos = $pdo->prepare("SELECT *, Pais FROM $nombre_tabla WHERE estado = 'finalizado' AND ID NOT IN ($in_clause) ORDER BY fecha DESC LIMIT 10");
            $stmtTodos->execute($ids_hoy);
        } else {
            $stmtTodos = $pdo->prepare("SELECT *, Pais FROM $nombre_tabla WHERE estado = 'finalizado' ORDER BY fecha DESC LIMIT 10");
            $stmtTodos->execute();
        }
        $todos_partidos = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

        // Query: Mejores Rendimientos
        // Cálculo de % Rendimiento: (Victorias / PJ) * 100
        $stmtRend = $pdo->prepare("
            SELECT Tenista,
                   COUNT(*) as PJ,
                   ROUND((SUM(CASE WHEN Resultado = 'W' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as Rendimiento
            FROM $nombre_tabla
            WHERE estado = 'finalizado'
            GROUP BY Tenista
            ORDER BY Rendimiento DESC, PJ DESC
        ");
        $stmtRend->execute();
        $mejores_rendimientos = $stmtRend->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_bd = "Hubo un error ejecutando la consulta: " . $e->getMessage();
    }
} else {
    $error_bd = "Falla al conectar a la BD: " . (is_string($pdo_result) ? $pdo_result : "Desconocido");
}

// Helper: Generador HTML de tarjetas de partido para no repetir código
function renderizar_tarjeta_partido($partido)
{
    // Fecha formato "4 de abril" si es finalizado, o la fecha
    $timestamp = strtotime($partido['fecha']);
    $fecha_format = wp_date('j \d\e F', $timestamp);
    if ($partido['estado'] === 'por jugar') {
        $hora_fecha = "Por jugar - " . $fecha_format;
    } else {
        $hora_fecha = $fecha_format;
    }

    $torneo = $partido['Torneo'] ?? 'Torneo Desconocido';
    $tenista = $partido['Tenista'] ?? $partido['tenista'] ?? '';
    $oponente = $partido['Oponente'] ?? $partido['oponente'] ?? '';
    $resultado = $partido['Resultado'] ?? $partido['resultado'] ?? ''; // W o L
    $scores_str = $partido['Scores'] ?? $partido['scores'] ?? ''; // e.g. "6-4, 6-2" o "6-1, 7-6(9)"

    // Extraer país del oponente de forma case-insensitive
    $pais_oponente = '';
    foreach ($partido as $k => $v) {
        if (strtolower($k) === 'pais') {
            $pais_oponente = trim($v);
            break;
        }
    }

    $tenista_ganador = ($resultado === 'W');
    $oponente_ganador = ($resultado === 'L');

    // Parseo rápido de scores
    $sets_tenista = [];
    $sets_oponente = [];
    if (!empty($scores_str) && strtolower(trim($scores_str)) !== 'null') {
        $sets = explode(',', $scores_str); // array("6-4", " 6-2") o array("6-1", " 7-6(9)")
        if (count($sets) > 0 && strpos($sets[0], '-') !== false) {
            foreach ($sets as $set) {
                $set = trim($set);
                // Extraer base Ej: 7 y 6. Ignorar tiebreak por ahora para simplificar display
                if (preg_match('/^(\d+)\s*-\s*(\d+)/', $set, $matches)) {
                    $sets_tenista[] = $matches[1];
                    $sets_oponente[] = $matches[2];
                } else {
                    $sets_tenista[] = $set;
                    $sets_oponente[] = '-';
                }
            }
        } else {
            // "Walkover", "Ret", etc.
            $sets_tenista[] = $scores_str;
            $sets_oponente[] = '';
        }
    }

    ?>
    <div class="match-card">
        <div class="match-info">
            <span class="match-time"><?php echo esc_html($hora_fecha); ?></span>
            <span class="match-tournament"><?php echo esc_html($torneo); ?></span>
        </div>
        <div class="match-players">
            <!-- Tenista Principal -->
            <div class="player-row">
                <div class="player-identity">
                    <span
                        class="player-name <?php echo $tenista_ganador ? 'fw-bold' : ''; ?>"><?php echo esc_html($tenista); ?></span>
                    <span class="player-country">CHI</span>
                </div>
                <div class="player-scores">
                    <?php if (empty($sets_tenista)): ?>
                        <span class="score">-</span>
                    <?php else: ?>
                        <?php foreach ($sets_tenista as $idx => $pts): ?>
                            <span
                                class="score <?php echo ($tenista_ganador && $idx == count($sets_tenista) - 1) ? 'winner-score' : ''; ?>"><?php echo esc_html($pts); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Oponente -->
            <div class="player-row">
                <div class="player-identity">
                    <span
                        class="player-name <?php echo $oponente_ganador ? 'fw-bold' : ''; ?>"><?php echo esc_html($oponente); ?></span>
                    <?php if (!empty($pais_oponente)): ?>
                        <span class="player-country"><?php echo esc_html(strtoupper($pais_oponente)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="player-scores">
                    <?php if (empty($sets_oponente)): ?>
                        <span class="score">-</span>
                    <?php else: ?>
                        <?php foreach ($sets_oponente as $idx => $pts): ?>
                            <span
                                class="score <?php echo ($oponente_ganador && $idx == count($sets_oponente) - 1) ? 'winner-score' : ''; ?>"><?php echo esc_html($pts); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<main id="main" class="site-main">
    <div class="container main-layout">

        <!-- Columna Principal -->
        <div class="content-area">

            <?php if (!empty($error_bd)): ?>
                <div style="background: #FEE2E2; color: #991B1B; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <strong>Falta configuración de datos:</strong> <?php echo esc_html($error_bd); ?>
                </div>
            <?php endif; ?>

            <!-- PARTIDOS DE HOY -->
            <section class="matches-section">
                <h2 class="section-title"><span class="title-accent"></span>PARTIDOS DE HOY</h2>
                <div class="matches-list">
                    <?php
                    if (!empty($partidos_hoy)) {
                        foreach ($partidos_hoy as $partido) {
                            renderizar_tarjeta_partido($partido);
                        }
                    } else if (empty($error_bd)) {
                        echo '<p>No hay partidos programados para hoy.</p>';
                    }
                    ?>
                </div>
            </section>

            <!-- TODOS LOS PARTIDOS -->
            <section class="matches-section mt-4">
                <h2 class="section-title"><span class="title-accent"></span>TODOS LOS PARTIDOS</h2>
                <div class="matches-list">
                    <?php
                    if (!empty($todos_partidos)) {
                        foreach ($todos_partidos as $partido) {
                            renderizar_tarjeta_partido($partido);
                        }
                    } else if (empty($error_bd)) {
                        echo '<p>No hay datos de partidos anteriores.</p>';
                    }
                    ?>
                </div>
            </section>

        </div>

        <!-- BARRA LATERAL -->
        <aside class="sidebar-area">
            <div class="widget widget-rendimientos">
                <h3 class="widget-title">Mejores Rendimientos</h3>
                <div class="table-responsive">
                    <table class="rendimientos-table">
                        <thead>
                            <tr>
                                <th>TENISTA</th>
                                <th class="text-center">PJ</th>
                                <th class="text-center">% REND</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($mejores_rendimientos)) {
                                foreach ($mejores_rendimientos as $rend) {
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo esc_html($rend['Tenista'] ?? '-'); ?></td>
                                        <td class="text-center"><?php echo esc_html($rend['PJ'] ?? '0'); ?></td>
                                        <td class="text-center rend-stat"><?php echo esc_html($rend['Rendimiento'] ?? '0'); ?>%
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else if (empty($error_bd)) {
                                echo '<tr><td colspan="3">Sin datos suficientes.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>

    </div>
</main><?php get_footer(); ?>