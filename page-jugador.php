<?php
/**
 * Template Name: Perfil de Jugador
 * 
 * Esta plantilla se usará para mostrar el perfil individual de un tenista.
 * Utiliza el título de la página (ej. "Alejandro Tabilo") para buscarlo en la Base de Datos.
 */
get_header();

$tenista = get_the_title(); // Obtiene el nombre del tenista del título de la página

$pdo = null;
if (function_exists('get_tenis_db_connection')) {
    $pdo = get_tenis_db_connection();
}

$error_bd = "";
$stats_generales = ['Rendimiento' => 0, 'TorneosGanados' => 0, 'PG' => 0, 'PP' => 0];
$stats_torneos = [];
$todos_partidos = [];

if ($pdo) {
    $nombre_tabla = 'partidos'; // Asumido por configuracion previa
    
    try {
        // 1. Estadísticas Generales (Tarjetas)
        $stmtGen = $pdo->prepare("
            SELECT 
                COUNT(*) as PJ,
                SUM(CASE WHEN Resultado = 'W' THEN 1 ELSE 0 END) as PG,
                SUM(CASE WHEN Resultado = 'L' THEN 1 ELSE 0 END) as PP,
                SUM(CASE WHEN Ronda = 'Final' AND Resultado = 'W' THEN 1 ELSE 0 END) as TorneosGanados
            FROM $nombre_tabla
            WHERE Tenista = :tenista AND estado = 'finalizado'
        ");
        $stmtGen->execute([':tenista' => $tenista]);
        $resGen = $stmtGen->fetch(PDO::FETCH_ASSOC);
        
        if ($resGen && $resGen['PJ'] > 0) {
            $stats_generales['PG'] = $resGen['PG'] ?? 0;
            $stats_generales['PP'] = $resGen['PP'] ?? 0;
            $stats_generales['TorneosGanados'] = $resGen['TorneosGanados'] ?? 0;
            $stats_generales['Rendimiento'] = round(($stats_generales['PG'] / $resGen['PJ']) * 100, 1);
        }

        // 2. Estadísticas por Torneo (Agrupado por Tipo)
        // El usuario pidió "extraido de la columna (Tipo)", agruparemos usando esa columna para listar 
        // e.g., Challenger, ATP Masters 1000, etc.
        $stmtTorneos = $pdo->prepare("
            SELECT 
                Tipo as Categoria,
                COUNT(*) as PJ,
                SUM(CASE WHEN Resultado = 'W' THEN 1 ELSE 0 END) as PG,
                SUM(CASE WHEN Resultado = 'L' THEN 1 ELSE 0 END) as PP,
                SUM(CASE WHEN Ronda = 'Quarter-final' THEN 1 ELSE 0 END) as CF,
                SUM(CASE WHEN Ronda = 'Semi-final' THEN 1 ELSE 0 END) as SF,
                SUM(CASE WHEN Ronda = 'Final' THEN 1 ELSE 0 END) as F,
                SUM(CASE WHEN Ronda = 'Final' AND Resultado = 'W' THEN 1 ELSE 0 END) as Campeon
            FROM $nombre_tabla
            WHERE Tenista = :tenista AND estado = 'finalizado'
            GROUP BY Tipo
            ORDER BY PJ DESC
        ");
        $stmtTorneos->execute([':tenista' => $tenista]);
        $stats_torneos = $stmtTorneos->fetchAll(PDO::FETCH_ASSOC);

        // 3. Todos los partidos (Historial completo de este tenista)
        $stmtPartidos = $pdo->prepare("
            SELECT * FROM $nombre_tabla 
            WHERE Tenista = :tenista 
            ORDER BY fecha DESC
        ");
        $stmtPartidos->execute([':tenista' => $tenista]);
        $todos_partidos = $stmtPartidos->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_bd = "Hubo un error ejecutando la consulta: " . $e->getMessage();
    }
} else {
    $error_bd = "Falla al conectar a la BD.";
}

?>

<main id="main" class="site-main player-profile-page">
    <div class="container">
        
        <?php if (!empty($error_bd)) : ?>
            <div style="background: #FEE2E2; color: #991B1B; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <strong>Falta configuración de datos:</strong> <?php echo esc_html($error_bd); ?>
            </div>
        <?php endif; ?>

        <!-- CABECERA DEL JUGADOR -->
        <header class="profile-header">
            <h1 class="player-name-title"><?php echo esc_html($tenista); ?></h1>
            <p class="player-subtitle">ESTADÍSTICAS DEL TENISTA</p>
        </header>

        <!-- TARJETAS DE ESTADÍSTICAS -->
        <section class="stats-cards-wrapper">
            <div class="stat-card">
                <span class="stat-label">Rendimiento</span>
                <span class="stat-value primary-color"><?php echo $stats_generales['Rendimiento']; ?>%</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Torneos Ganados</span>
                <span class="stat-value text-success"><?php echo $stats_generales['TorneosGanados']; ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Partidos Ganados</span>
                <span class="stat-value text-success"><?php echo $stats_generales['PG']; ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Partidos Perdidos</span>
                <span class="stat-value text-danger"><?php echo $stats_generales['PP']; ?></span>
            </div>
        </section>

        <!-- ESTADÍSTICAS POR TORNEO -->
        <section class="matches-section mt-5">
            <h2 class="section-title"><span class="title-accent"></span>ESTADÍSTICAS POR TIPO DE TORNEO</h2>
            <div class="theme-box">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>TIPO TORNEO</th>
                                <th class="text-center">PJ</th>
                                <th class="text-center">PG</th>
                                <th class="text-center">PP</th>
                                <th class="text-center">CF</th>
                                <th class="text-center">SF</th>
                                <th class="text-center">F</th>
                                <th class="text-center">CAMPEÓN</th>
                                <th class="text-center">REND.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($stats_torneos)) : ?>
                                <?php foreach ($stats_torneos as $st) : 
                                    $rend_torneo = ($st['PJ'] > 0) ? round(($st['PG'] / $st['PJ']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo esc_html($st['Categoria'] ?? '-'); ?></td>
                                    <td class="text-center"><?php echo esc_html($st['PJ']); ?></td>
                                    <td class="text-center text-success fw-bold"><?php echo esc_html($st['PG']); ?></td>
                                    <td class="text-center text-danger fw-bold"><?php echo esc_html($st['PP']); ?></td>
                                    <td class="text-center"><?php echo esc_html($st['CF']); ?></td>
                                    <td class="text-center"><?php echo esc_html($st['SF']); ?></td>
                                    <td class="text-center"><?php echo esc_html($st['F']); ?></td>
                                    <td class="text-center stat-highlight"><?php echo esc_html($st['Campeon']); ?></td>
                                    <td class="text-center fw-bold"><?php echo $rend_torneo; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9">Sin datos de torneos para mostrar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TODOS LOS PARTIDOS -->
        <section class="matches-section mt-5">
            <h2 class="section-title"><span class="title-accent"></span>TODOS LOS PARTIDOS</h2>
            <div class="theme-box">
                <div class="table-responsive">
                    <table class="data-table matches-table">
                        <thead>
                            <tr>
                                <th>FECHA</th>
                                <th>TORNEO</th>
                                <th>RONDA</th>
                                <th>TENISTA</th>
                                <th class="text-center">RESULTADO</th>
                                <th>OPONENTE</th>
                                <th>ESTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($todos_partidos)) : ?>
                                <?php foreach ($todos_partidos as $partido) : 
                                    $fecha = ($partido['fecha']) ? wp_date('Y-m-d', strtotime($partido['fecha'])) : '-';
                                    $resultado_str = ($partido['Resultado'] === 'W') ? '<span class="badge badge-win">W</span>' : (($partido['Resultado'] === 'L') ? '<span class="badge badge-loss">L</span>' : '-');
                                    // Determinar ganador para bold
                                    $is_w = ($partido['Resultado'] === 'W');
                                ?>
                                <tr>
                                    <td><?php echo esc_html($fecha); ?></td>
                                    <td>
                                        <span class="d-block fw-bold"><?php echo esc_html($partido['Torneo']); ?></span>
                                        <span class="text-muted small"><?php echo esc_html($partido['Tipo']); ?></span>
                                    </td>
                                    <td><?php echo esc_html($partido['Ronda']); ?></td>
                                    <td class="fw-bold <?php echo $is_w ? 'primary-color' : ''; ?>"><?php echo esc_html($partido['Tenista']); ?></td>
                                    <td class="text-center"><?php echo $resultado_str . ' <br><small>(' . esc_html($partido['Scores']) . ')</small>'; ?></td>
                                    <td class="<?php echo !$is_w ? 'fw-bold primary-color' : ''; ?>"><?php echo esc_html($partido['Oponente']); ?></td>
                                    <td>
                                        <?php if(strtolower($partido['estado']) == 'finalizado'): ?>
                                            <span class="badge badge-final">FINAL</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">POR JUGAR</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7">No hay partidos registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>
</main>

<?php get_footer(); ?>
