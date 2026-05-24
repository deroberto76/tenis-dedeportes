<?php
/**
 * Template Name: Roland Garros
 * 
 * Plantilla dedicada a la sección de Roland Garros.
 * Usa la base de datos pjdmenag_rolandgarros.
 */
get_header();

$pdo_result = null;
if (function_exists('get_rolandgarros_db_connection')) {
    $pdo_result = get_rolandgarros_db_connection();
}

$partidos_hoy = [];
$ultimos_partidos = [];
$rendimiento_pais = [];
$error_bd = "";

if ($pdo_result instanceof PDO) {
    $pdo = $pdo_result;

    try {
        // Query: Partidos de Hoy
        // Se asume que la columna ID en tenistas es 'id'.
        $stmtHoy = $pdo->prepare("
            SELECT 
                p.*, 
                t1.nombre as tenista1, t1.pais as pais1,
                t2.nombre as tenista2, t2.pais as pais2
            FROM partidos p
            LEFT JOIN tenistas t1 ON p.id_jugador1 = t1.id
            LEFT JOIN tenistas t2 ON p.id_jugador2 = t2.id
            WHERE p.fecha = CURDATE()
            ORDER BY p.hora ASC
        ");
        $stmtHoy->execute();
        $partidos_hoy = $stmtHoy->fetchAll(PDO::FETCH_ASSOC);

        // Query: Últimos Partidos (Finalizados)
        $stmtTodos = $pdo->prepare("
            SELECT 
                p.*, 
                t1.nombre as tenista1, t1.pais as pais1,
                t2.nombre as tenista2, t2.pais as pais2
            FROM partidos p
            LEFT JOIN tenistas t1 ON p.id_jugador1 = t1.id
            LEFT JOIN tenistas t2 ON p.id_jugador2 = t2.id
            WHERE p.estado = 'finalizado'
            ORDER BY p.fecha DESC, p.hora DESC
            LIMIT 15
        ");
        $stmtTodos->execute();
        $ultimos_partidos = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

        // Query: Rendimiento por país
        // Hay que contar partidos jugados y victorias por país.
        // Un partido involucra a dos jugadores (y por tanto, dos países).
        // Calcularemos esto en PHP para simplificar la consulta SQL dado que hay dos columnas de jugador.
        $stmtPaises = $pdo->prepare("
            SELECT 
                p.ganador,
                t1.pais as pais1,
                t2.pais as pais2
            FROM partidos p
            LEFT JOIN tenistas t1 ON p.id_jugador1 = t1.id
            LEFT JOIN tenistas t2 ON p.id_jugador2 = t2.id
            WHERE p.estado = 'finalizado'
        ");
        $stmtPaises->execute();
        $resultados_paises = $stmtPaises->fetchAll(PDO::FETCH_ASSOC);
        
        $stats_paises = [];
        foreach ($resultados_paises as $rp) {
            $pais1 = strtoupper(trim($rp['pais1']));
            $pais2 = strtoupper(trim($rp['pais2']));
            $ganador = $rp['ganador']; // 1 o 2
            
            // Inicializar países si no existen
            if (!empty($pais1) && !isset($stats_paises[$pais1])) {
                $stats_paises[$pais1] = ['pj' => 0, 'pg' => 0];
            }
            if (!empty($pais2) && !isset($stats_paises[$pais2])) {
                $stats_paises[$pais2] = ['pj' => 0, 'pg' => 0];
            }
            
            // Contabilizar PJ
            if (!empty($pais1)) $stats_paises[$pais1]['pj']++;
            if (!empty($pais2)) $stats_paises[$pais2]['pj']++;
            
            // Contabilizar PG
            if ($ganador == 1 && !empty($pais1)) {
                $stats_paises[$pais1]['pg']++;
            } elseif ($ganador == 2 && !empty($pais2)) {
                $stats_paises[$pais2]['pg']++;
            }
        }
        
        // Calcular porcentaje y ordenar
        foreach ($stats_paises as $pais => &$stats) {
            $stats['rendimiento'] = ($stats['pj'] > 0) ? round(($stats['pg'] / $stats['pj']) * 100, 1) : 0;
            $stats['pais'] = $pais;
        }
        unset($stats);
        
        usort($stats_paises, function($a, $b) {
            if ($a['rendimiento'] == $b['rendimiento']) {
                return $b['pj'] <=> $a['pj'];
            }
            return $b['rendimiento'] <=> $a['rendimiento'];
        });
        
        $rendimiento_pais = $stats_paises;

    } catch (PDOException $e) {
        // En caso de que la columna id sea diferente, intentamos fallback
        if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 't1.id') !== false) {
             try {
                // Fallback usando id_tenista
                $stmtHoy = $pdo->prepare("
                    SELECT 
                        p.*, 
                        t1.nombre as tenista1, t1.pais as pais1,
                        t2.nombre as tenista2, t2.pais as pais2
                    FROM partidos p
                    LEFT JOIN tenistas t1 ON p.id_jugador1 = t1.id_tenista
                    LEFT JOIN tenistas t2 ON p.id_jugador2 = t2.id_tenista
                    WHERE p.fecha = CURDATE()
                    ORDER BY p.hora ASC
                ");
                $stmtHoy->execute();
                $partidos_hoy = $stmtHoy->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtTodos = $pdo->prepare("
                    SELECT 
                        p.*, 
                        t1.nombre as tenista1, t1.pais as pais1,
                        t2.nombre as tenista2, t2.pais as pais2
                    FROM partidos p
                    LEFT JOIN tenistas t1 ON p.id_jugador1 = t1.id_tenista
                    LEFT JOIN tenistas t2 ON p.id_jugador2 = t2.id_tenista
                    WHERE p.estado = 'finalizado'
                    ORDER BY p.fecha DESC, p.hora DESC
                    LIMIT 15
                ");
                $stmtTodos->execute();
                $ultimos_partidos = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

                $stmtPaises = $pdo->prepare("
                    SELECT 
                        p.ganador,
                        t1.pais as pais1,
                        t2.pais as pais2
                    FROM partidos p
                    LEFT JOIN tenistas t1 ON p.id_jugador1 = t1.id_tenista
                    LEFT JOIN tenistas t2 ON p.id_jugador2 = t2.id_tenista
                    WHERE p.estado = 'finalizado'
                ");
                $stmtPaises->execute();
                $resultados_paises = $stmtPaises->fetchAll(PDO::FETCH_ASSOC);
                // (Omitimos el cálculo de rendimiento aquí por brevedad del fallback, el error se mostrará si falla)
                
             } catch (PDOException $e2) {
                 $error_bd = "Hubo un error ejecutando la consulta (fallback): " . $e2->getMessage();
             }
        } else {
            $error_bd = "Hubo un error ejecutando la consulta: " . $e->getMessage();
        }
    }
} else {
    $error_bd = "Falla al conectar a la BD: " . (is_string($pdo_result) ? $pdo_result : "Desconocido");
}

// Función auxiliar para renderizar tarjetas de Roland Garros
function renderizar_tarjeta_rg($partido, $mostrar_fecha = false) {
    $hora_str = $partido['hora'] ?? '';
    if (!empty($hora_str) && strlen($hora_str) > 5) {
        $hora_str = substr($hora_str, 0, 5); // 05:00
    }
    
    $timestamp = strtotime($partido['fecha']);
    $hora_fecha = $hora_str;
    
    if ($mostrar_fecha) {
        $hora_fecha = wp_date('j \d\e F', $timestamp);
    } elseif (strtolower($partido['estado']) === 'finalizado') {
         $hora_fecha = "Final";
    }

    $ronda = $partido['ronda'] ?? 'Roland Garros';
    
    $tenista1 = $partido['tenista1'] ?? 'Jugador 1';
    $pais1 = $partido['pais1'] ?? '';
    
    $tenista2 = $partido['tenista2'] ?? 'Jugador 2';
    $pais2 = $partido['pais2'] ?? '';
    
    $ganador = $partido['ganador']; // 1 o 2
    
    $t1_ganador = ($ganador == 1);
    $t2_ganador = ($ganador == 2);
    
    $resultado_str = $partido['resultado'] ?? ''; // ej "6-7(3), 6-3, 2-6, 7-5, 6-3"
    
    $sets_t1 = [];
    $sets_t2 = [];
    
    if (!empty($resultado_str) && strtolower(trim($resultado_str)) !== 'null') {
        $sets = explode(',', $resultado_str);
        if (count($sets) > 0 && strpos($sets[0], '-') !== false) {
            foreach ($sets as $set) {
                $set = trim($set);
                // Extraer base Ej: 6-7(3) -> 6 y 7
                if (preg_match('/^(\d+)\s*-\s*(\d+)/', $set, $matches)) {
                    $sets_t1[] = $matches[1];
                    $sets_t2[] = $matches[2];
                } else {
                    $sets_t1[] = $set;
                    $sets_t2[] = '-';
                }
            }
        } else {
            $sets_t1[] = $resultado_str;
            $sets_t2[] = '';
        }
    }

    // Links
    $url_t1 = function_exists('get_player_profile_url') ? get_player_profile_url($tenista1) : null;
    $url_t2 = function_exists('get_player_profile_url') ? get_player_profile_url($tenista2) : null;

    $t1_html = $url_t1 ? '<a href="' . esc_url($url_t1) . '" class="player-link">' . esc_html($tenista1) . '</a>' : esc_html($tenista1);
    $t2_html = $url_t2 ? '<a href="' . esc_url($url_t2) . '" class="player-link">' . esc_html($tenista2) . '</a>' : esc_html($tenista2);

    ?>
    <div class="match-card">
        <div class="match-info">
            <span class="match-time"><?php echo esc_html($hora_fecha); ?></span>
            <span class="match-tournament"><?php echo esc_html($ronda); ?></span>
        </div>
        <div class="match-players">
            <!-- Tenista 1 -->
            <div class="player-row">
                <div class="player-identity">
                    <span class="player-name <?php echo $t1_ganador ? 'fw-bold' : ''; ?>"><?php echo $t1_html; ?></span>
                    <?php if (!empty($pais1)): ?>
                        <span class="player-country"><?php echo esc_html(strtoupper($pais1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="player-scores">
                    <?php if (empty($sets_t1)): ?>
                        <span class="score">-</span>
                    <?php else: ?>
                        <?php foreach ($sets_t1 as $idx => $pts): ?>
                            <span class="score <?php echo ($t1_ganador && $idx == count($sets_t1) - 1) ? 'winner-score' : ''; ?>"><?php echo esc_html($pts); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Tenista 2 -->
            <div class="player-row">
                <div class="player-identity">
                    <span class="player-name <?php echo $t2_ganador ? 'fw-bold' : ''; ?>"><?php echo $t2_html; ?></span>
                    <?php if (!empty($pais2)): ?>
                        <span class="player-country"><?php echo esc_html(strtoupper($pais2)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="player-scores">
                    <?php if (empty($sets_t2)): ?>
                        <span class="score">-</span>
                    <?php else: ?>
                        <?php foreach ($sets_t2 as $idx => $pts): ?>
                            <span class="score <?php echo ($t2_ganador && $idx == count($sets_t2) - 1) ? 'winner-score' : ''; ?>"><?php echo esc_html($pts); ?></span>
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

            <header class="profile-header mb-4">
                <h1 class="player-name-title" style="color: #004638;">ROLAND GARROS</h1>
                <p class="player-subtitle" style="color: #D35400;">EL GRAND SLAM DE TIERRA BATIDA</p>
            </header>

            <!-- PARTIDOS DE HOY -->
            <section class="matches-section mt-4">
                <h2 class="section-title"><span class="title-accent" style="background-color: #D35400;"></span>PARTIDOS DEL DÍA</h2>
                <div class="matches-list">
                    <?php
                    if (!empty($partidos_hoy)) {
                        foreach ($partidos_hoy as $partido) {
                            renderizar_tarjeta_rg($partido, false);
                        }
                    } else if (empty($error_bd)) {
                        echo '<p>No hay partidos programados para hoy en Roland Garros.</p>';
                    }
                    ?>
                </div>
            </section>

            <!-- ÚLTIMOS PARTIDOS -->
            <section class="matches-section mt-5">
                <h2 class="section-title"><span class="title-accent" style="background-color: #D35400;"></span>ÚLTIMOS PARTIDOS</h2>
                <div class="matches-list">
                    <?php
                    if (!empty($ultimos_partidos)) {
                        foreach ($ultimos_partidos as $partido) {
                            renderizar_tarjeta_rg($partido, true);
                        }
                    } else if (empty($error_bd)) {
                        echo '<p>No hay datos de partidos finalizados.</p>';
                    }
                    ?>
                </div>
            </section>

        </div>

        <!-- BARRA LATERAL -->
        <aside class="sidebar-area">
            <div class="widget widget-rendimientos" style="border-top: 4px solid #004638;">
                <h3 class="widget-title">Rendimiento por País</h3>
                <div class="table-responsive">
                    <table class="rendimientos-table">
                        <thead>
                            <tr>
                                <th style="color: #004638;">PAÍS</th>
                                <th class="text-center" style="color: #004638;">PJ</th>
                                <th class="text-center" style="color: #004638;">% REND</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($rendimiento_pais)) {
                                foreach ($rendimiento_pais as $rend) {
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo esc_html($rend['pais'] ?? '-'); ?></td>
                                        <td class="text-center"><?php echo esc_html($rend['pj'] ?? '0'); ?></td>
                                        <td class="text-center rend-stat" style="color: #D35400;"><?php echo esc_html($rend['rendimiento'] ?? '0'); ?>%
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
</main>
<?php get_footer(); ?>
