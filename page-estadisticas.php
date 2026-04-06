<?php
/**
 * Template Name: Estadísticas Generales
 * 
 * Plantilla para visualizar todos los rankings y métricas globales de los tenistas en la BD.
 */
get_header();

$pdo = null;
if (function_exists('get_tenis_db_connection')) {
    $pdo = get_tenis_db_connection();
}

$error_bd = "";

// Contenedores de estadísticas
$top_rendimiento = [];
$top_mas_ganados = [];
$top_mas_disputados = [];
$top_cuartos = [];
$top_semis = [];
$top_finales = [];
$top_rendimiento_polvo = [];
$top_rendimiento_dura = [];
$top_rendimiento_hierba = [];
$top_triunfos_rankeados = [];
$top_efectividad_finales = [];
$top_tie_breaks = [];
$racha_victorias = [];

if ($pdo) {
    $nombre_tabla = 'partidos';
    try {
        // 1. Mejor rendimiento general (Mínimo 5 partidos para ser justo)
        $stmtRend = $pdo->prepare("
            SELECT Tenista, COUNT(*) as PJ, 
                   ROUND((SUM(CASE WHEN Resultado = 'W' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as Rendimiento
            FROM $nombre_tabla WHERE estado = 'finalizado'
            GROUP BY Tenista HAVING PJ >= 3
            ORDER BY Rendimiento DESC, PJ DESC LIMIT 5
        ");
        $stmtRend->execute();
        $top_rendimiento = $stmtRend->fetchAll(PDO::FETCH_ASSOC);

        // 2. Más Partidos Ganados
        $stmtPG = $pdo->prepare("
            SELECT Tenista, COUNT(*) as PJ, SUM(CASE WHEN Resultado = 'W' THEN 1 ELSE 0 END) as PG
            FROM $nombre_tabla WHERE estado = 'finalizado'
            GROUP BY Tenista
            ORDER BY PG DESC, PJ ASC LIMIT 5
        ");
        $stmtPG->execute();
        $top_mas_ganados = $stmtPG->fetchAll(PDO::FETCH_ASSOC);

        // 3. Más Partidos Disputados
        $stmtPJ = $pdo->prepare("
            SELECT Tenista, COUNT(*) as PJ
            FROM $nombre_tabla WHERE estado = 'finalizado'
            GROUP BY Tenista
            ORDER BY PJ DESC LIMIT 5
        ");
        $stmtPJ->execute();
        $top_mas_disputados = $stmtPJ->fetchAll(PDO::FETCH_ASSOC);

        // 4. Triunfos a mejores rankeados
        $stmtTriunfos = $pdo->prepare("
            SELECT Tenista, Oponente, Torneo, Ranking_Oponente 
            FROM $nombre_tabla 
            WHERE Resultado = 'W' AND Ranking_Oponente IS NOT NULL AND Ranking_Oponente > 0
            ORDER BY CAST(Ranking_Oponente AS UNSIGNED) ASC LIMIT 5
        ");
        $stmtTriunfos->execute();
        $top_triunfos_rankeados = $stmtTriunfos->fetchAll(PDO::FETCH_ASSOC);

        // 5. Más Finales, Semis y Cuartos
        $stmtRondas = $pdo->prepare("
            SELECT Tenista,
                   SUM(CASE WHEN Ronda = 'Quarter-final' THEN 1 ELSE 0 END) as CF,
                   SUM(CASE WHEN Ronda = 'Semi-final' THEN 1 ELSE 0 END) as SF,
                   SUM(CASE WHEN Ronda = 'Final' THEN 1 ELSE 0 END) as F
            FROM $nombre_tabla WHERE estado = 'finalizado'
            GROUP BY Tenista
        ");
        $stmtRondas->execute();
        $rondas = $stmtRondas->fetchAll(PDO::FETCH_ASSOC);

        $top_cuartos = $rondas;
        usort($top_cuartos, fn($a, $b) => $b['CF'] <=> $a['CF']);
        $top_cuartos = array_slice($top_cuartos, 0, 5);
        $top_semis = $rondas;
        usort($top_semis, fn($a, $b) => $b['SF'] <=> $a['SF']);
        $top_semis = array_slice($top_semis, 0, 5);
        $top_finales = $rondas;
        usort($top_finales, fn($a, $b) => $b['F'] <=> $a['F']);
        $top_finales = array_slice($top_finales, 0, 5);

        // 6. Efectividad en Finales (Solo los que han jugado finales)
        $top_efectividad_finales = [];
        $stmtEftFin = $pdo->prepare("
            SELECT Tenista,
                   SUM(CASE WHEN Ronda = 'Final' THEN 1 ELSE 0 END) as FinalesJugadas,
                   SUM(CASE WHEN Ronda = 'Final' AND Resultado = 'W' THEN 1 ELSE 0 END) as FinalesGanadas
            FROM $nombre_tabla WHERE estado = 'finalizado'
            GROUP BY Tenista HAVING FinalesJugadas > 0
        ");
        $stmtEftFin->execute();
        $efectividad = $stmtEftFin->fetchAll(PDO::FETCH_ASSOC);
        foreach ($efectividad as $e) {
            $e['Efectividad'] = round(($e['FinalesGanadas'] / $e['FinalesJugadas']) * 100, 1);
            $top_efectividad_finales[] = $e;
        }
        usort($top_efectividad_finales, fn($a, $b) => $b['Efectividad'] <=> $a['Efectividad']);
        $top_efectividad_finales = array_slice($top_efectividad_finales, 0, 5);

        // 7. Rendimientos por Superficie
        $stmtSup = $pdo->prepare("
            SELECT Tenista, Superficie, COUNT(*) as PJ, 
                   ROUND((SUM(CASE WHEN Resultado = 'W' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as Rendimiento
            FROM $nombre_tabla WHERE estado = 'finalizado'
            GROUP BY Tenista, Superficie HAVING PJ >= 2
        ");
        $stmtSup->execute();
        $superficies = $stmtSup->fetchAll(PDO::FETCH_ASSOC);
        foreach ($superficies as $s) {
            $sup = strtolower(trim($s['Superficie']));
            if (strpos($sup, 'cl') !== false || strpos($sup, 'polvo') !== false || strpos($sup, 'arcilla') !== false) {
                $top_rendimiento_polvo[] = $s;
            } else if (strpos($sup, 'hard') !== false || strpos($sup, 'dura') !== false) {
                $top_rendimiento_dura[] = $s;
            } else if (strpos($sup, 'grass') !== false || strpos($sup, 'hierba') !== false || strpos($sup, 'cesped') !== false) {
                $top_rendimiento_hierba[] = $s;
            }
        }
        usort($top_rendimiento_polvo, fn($a, $b) => $b['Rendimiento'] <=> $a['Rendimiento']);
        $top_rendimiento_polvo = array_slice($top_rendimiento_polvo, 0, 5);
        usort($top_rendimiento_dura, fn($a, $b) => $b['Rendimiento'] <=> $a['Rendimiento']);
        $top_rendimiento_dura = array_slice($top_rendimiento_dura, 0, 5);
        usort($top_rendimiento_hierba, fn($a, $b) => $b['Rendimiento'] <=> $a['Rendimiento']);
        $top_rendimiento_hierba = array_slice($top_rendimiento_hierba, 0, 5);

        // 8. Tie breaks ganados (Contar ocurrencias de '7-6' en Scores para este Tenista)
        $stmtTB = $pdo->prepare("
            SELECT Tenista, SUM(ROUND((LENGTH(Scores) - LENGTH(REPLACE(Scores, '7-6', '')))/3)) as TBGanados
            FROM $nombre_tabla 
            WHERE estado = 'finalizado' AND Scores LIKE '%7-6%'
            GROUP BY Tenista
            ORDER BY TBGanados DESC LIMIT 5
        ");
        $stmtTB->execute();
        $top_tie_breaks = $stmtTB->fetchAll(PDO::FETCH_ASSOC);

        // 9. Lógica de Rachas (Streaks) de Victorias
        $stmtAll = $pdo->prepare("SELECT Tenista, Resultado FROM $nombre_tabla WHERE estado = 'finalizado' ORDER BY Tenista, fecha ASC");
        $stmtAll->execute();
        $todos_para_racha = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $rachas = [];
        $current_streak = 0;
        $max_streak = 0;
        $last_player = '';

        foreach ($todos_para_racha as $row) {
            if ($row['Tenista'] !== $last_player) {
                if ($last_player !== '') {
                    $rachas[] = ['Tenista' => $last_player, 'RachaMax' => $max_streak];
                }
                $last_player = $row['Tenista'];
                $current_streak = 0;
                $max_streak = 0;
            }
            if ($row['Resultado'] === 'W') {
                $current_streak++;
                if ($current_streak > $max_streak)
                    $max_streak = $current_streak;
            } else {
                $current_streak = 0;
            }
        }
        if ($last_player !== '') {
            $rachas[] = ['Tenista' => $last_player, 'RachaMax' => $max_streak];
        }
        usort($rachas, fn($a, $b) => $b['RachaMax'] <=> $a['RachaMax']);
        $racha_victorias = $rachas[0] ?? ['Tenista' => '-', 'RachaMax' => 0];

    } catch (PDOException $e) {
        $error_bd = "Hubo un error ejecutando la consulta: " . $e->getMessage();
    }
} else {
    $error_bd = "Falla al conectar a la BD.";
}

// Función Helper para renderizar tablas Top 5
function renderTopTable($titulo, $datos, $col_key, $col_sufijo = '')
{
    ?>
    <div class="leaderboard-card">
        <h4 class="leaderboard-title">
            <?php echo esc_html($titulo); ?>
        </h4>
        <ul class="leaderboard-list">
            <?php
            if (!empty($datos)):
                $rank = 1;
                foreach ($datos as $item):
                    // Evitar ceros en la UI de tablas
                    if (isset($item[$col_key]) && floatval($item[$col_key]) == 0 && $col_key != 'Ranking_Oponente')
                        continue;
                    ?>
                    <li class="leaderboard-item">
                        <div class="rank-badge rank-<?php echo $rank; ?>">
                            <?php echo $rank; ?>
                        </div>
                        <div class="player-info">
                            <strong>
                                <?php echo esc_html($item['Tenista'] ?? $item['Oponente']); ?>
                            </strong>
                            <?php if ($col_key === 'Ranking_Oponente'): ?>
                                <span class="subtext">a
                                    <?php echo esc_html($item['Oponente']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="score-value">
                            <?php echo esc_html($item[$col_key]) . $col_sufijo; ?>
                        </div>
                    </li>
                    <?php
                    $rank++;
                    if ($rank > 5)
                        break;
                endforeach;
            else: ?>
                <li class="empty-state">No hay suficientes datos.</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php
}
?>

<main id="main" class="site-main stats-global-page">
    <div class="container">
        <header class="profile-header text-center mb-5">
            <h1 class="player-name-title">Estadísticas Generales</h1>
            <p class="player-subtitle">RÉCORDS Y ESTADÍSTICAS GLOBALES DEL TENIS CHILENO</p>
        </header>

        <?php if (!empty($error_bd)): ?>
            <div style="background: #FEE2E2; color: #991B1B; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <strong>Error BD:</strong>
                <?php echo esc_html($error_bd); ?>
            </div>
        <?php endif; ?>

        <!-- Highlight Cards (Top 3 Single Metrics) -->
        <section class="stats-cards-wrapper">
            <div class="stat-card" style="border-top: 4px solid var(--accent-blue);">
                <span class="stat-label">Mejor Rendimiento Gral</span>
                <span class="stat-value text-blue">
                    <?php echo (!empty($top_rendimiento)) ? $top_rendimiento[0]['Rendimiento'] . '%' : '-'; ?>
                </span>
                <span class="stat-label" style="margin-top: 0.5rem; color: var(--text-dark);">
                    <?php echo (!empty($top_rendimiento)) ? $top_rendimiento[0]['Tenista'] : '-'; ?>
                </span>
            </div>
            <div class="stat-card" style="border-top: 4px solid #10B981;">
                <span class="stat-label">Más Partidos Ganados</span>
                <span class="stat-value text-success">
                    <?php echo (!empty($top_mas_ganados)) ? $top_mas_ganados[0]['PG'] : '-'; ?>
                </span>
                <span class="stat-label" style="margin-top: 0.5rem; color: var(--text-dark);">
                    <?php echo (!empty($top_mas_ganados)) ? $top_mas_ganados[0]['Tenista'] : '-'; ?>
                </span>
            </div>
            <div class="stat-card" style="border-top: 4px solid #F59E0B;">
                <span class="stat-label">Racha de Victorias Actual</span>
                <span class="stat-value" style="color: #F59E0B;">
                    <?php echo $racha_victorias['RachaMax']; ?>
                </span>
                <span class="stat-label" style="margin-top: 0.5rem; color: var(--text-dark);">
                    <?php echo $racha_victorias['Tenista']; ?>
                </span>
            </div>
        </section>

        <!-- Grid de Leaderboards -->
        <div class="leaderboards-grid">
            <?php renderTopTable('Mejor Rendimiento (%)', $top_rendimiento, 'Rendimiento', '%'); ?>
            <?php renderTopTable('Partidos Ganados', $top_mas_ganados, 'PG'); ?>
            <?php renderTopTable('Partidos Disputados', $top_mas_disputados, 'PJ'); ?>

            <?php renderTopTable('Llegadas a Cuartos de Final', $top_cuartos, 'CF'); ?>
            <?php renderTopTable('Llegadas a Semifinales', $top_semis, 'SF'); ?>
            <?php renderTopTable('Llegadas a Finales', $top_finales, 'F'); ?>

            <?php renderTopTable('Rendimiento en Polvo (%)', $top_rendimiento_polvo, 'Rendimiento', '%'); ?>
            <?php renderTopTable('Rendimiento en Dura (%)', $top_rendimiento_dura, 'Rendimiento', '%'); ?>
            <?php renderTopTable('Efectividad Finales (%)', $top_efectividad_finales, 'Efectividad', '%'); ?>

            <?php renderTopTable('Triunfos a Mejor Rankeados', $top_triunfos_rankeados, 'Ranking_Oponente', ' Rank'); ?>
            <?php renderTopTable('Tie Breaks Ganados', $top_tie_breaks, 'TBGanados'); ?>
        </div>

    </div>
</main>

<?php get_footer(); ?>